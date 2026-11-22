const { test } = require('node:test');
const assert = require('node:assert/strict');
const { createInbox, statusOf } = require('../inbox');
const { readPresence } = require('../presence');
const { createService } = require('../service');
const { EventEmitter } = require('node:events');

// A number Chatly just messaged (campaign / Sales Desk), so its replies count as a lead's
// even before the campaign's batched CRM write lands. Uses a number no real CRM has.
const TEST_PHONE = '923009990001';
const chatlySend = (id, phone = TEST_PHONE) => ({ id: { _serialized: id, remote: phone + '@c.us', fromMe: true }, fromMe: true, body: 'hi', timestamp: Math.floor(Date.now() / 1000) });

test('incoming text, audio and images are deduplicated and mapped from LID to a phone', async () => {
  const inbox = createInbox({ getClient: () => ({ getContactLidAndPhone: async () => [{ pn: TEST_PHONE + '@c.us' }] }) });
  await inbox.ingest(chatlySend('first-send'), TEST_PHONE, { fromChatly: true });
  for (const type of ['chat','ptt','image']) {
    const message = { id: { _serialized: 'id-' + type, remote: '123@lid', fromMe: false }, body: 'hello', type, hasMedia: type !== 'chat', timestamp: Math.floor(Date.now() / 1000) };
    await inbox.ingest(message); await inbox.ingest(message);
  }
  const result = inbox.events(0);
  const replies = result.messages.filter(m => m.sender === 'lead');
  assert.equal(replies.length, 3);
  assert.deepEqual(replies.map(m => m.type), ['text','audio','image']);
  assert.ok(replies.every(m => m.phone === TEST_PHONE));
  assert.equal(inbox.events(result.cursor).messages.length, 0);
});

test('personal chats on a linked phone are never stored: messages typed to, or received from, non-leads', async () => {
  const inbox = createInbox({ getClient: () => ({}) });
  const now = Math.floor(Date.now() / 1000);
  // typed on the closer's own phone to a friend (not a lead, not sent by Chatly)
  await inbox.ingest({ id: { _serialized: 'typed-on-phone', remote: '923009990002@c.us', fromMe: true }, fromMe: true, body: 'dinner?', timestamp: now });
  // a friend writing to the closer
  await inbox.ingest({ id: { _serialized: 'friend-writes', remote: '923009990003@c.us', fromMe: false }, body: 'hey', timestamp: now });
  assert.equal(inbox.events(0).messages.length, 0);
  // a Chatly send to the same friend's number IS kept, and so is the reply after it
  await inbox.ingest(chatlySend('chatly-send', '923009990003'), '923009990003', { fromChatly: true });
  await inbox.ingest({ id: { _serialized: 'reply', remote: '923009990003@c.us', fromMe: false }, body: 'interested', timestamp: now });
  assert.deepEqual(inbox.events(0).messages.map(m => m.waMessageId), ['chatly-send', 'reply']);
});

test('ACKs distinguish a single sent tick, delivered double tick and read double tick', async () => {
  const inbox = createInbox({ getClient: () => ({}) });
  const message = chatlySend('outgoing');
  await inbox.ingest(message, TEST_PHONE, { fromChatly: true });
  for (const [ack, status] of [[1,'sent'],[2,'delivered'],[3,'read']]) {
    inbox.ack(message, ack); assert.equal(inbox.events(0).messages[0].deliveryStatus, status);
  }
  assert.equal(statusOf(0), 'accepted'); assert.equal(statusOf(-1), 'failed');
});

test('incoming media downloads return real data, and unavailable media fails explicitly', async () => {
  const client = { getMessageById: async () => ({ hasMedia: true, downloadMedia: async () => ({ mimetype:'image/png',data:'aGVsbG8=' }) }) };
  const inbox = createInbox({ getClient: () => client });
  assert.equal((await inbox.media('image')).data_url, 'data:image/png;base64,aGVsbG8=');
  client.getMessageById = async () => null;
  await assert.rejects(inbox.media('missing'), /unavailable/);
});

test('missing presence is unknown rather than fabricated online or last-seen', async () => {
  const result = await readPresence({}, '923001234567');
  assert.equal(result.state, 'unknown'); assert.match(result.label, /unavailable/);
});

test('media requests pass real bytes to the client with voice-note option and deduplicate', async t => {
  const client = new EventEmitter(); const calls = [];
  client.initialize = async () => client.emit('ready'); client.destroy = async () => {};
  client.getNumberId = async () => ({ _serialized:'923001234567@c.us' });
  client.sendMessage = async (id, media, options) => {
    calls.push({ id, media, options });
    return { id:{_serialized:'sent-media',fromMe:true,remote:id},hasMedia:true,type:'ptt',timestamp:100,ack:1 };
  };
  const token = 'a'.repeat(40);
  const service = createService({ createClient:()=>client, qrImage:async()=>'', token, makeMedia: value=>value });
  await new Promise(r=>service.server.listen(0,'127.0.0.1',r)); t.after(()=>service.close());
  service.connect(); await new Promise(r=>setImmediate(r));
  const send = body => fetch(`http://127.0.0.1:${service.server.address().port}/send`,{method:'POST',headers:{Authorization:`Bearer ${token}`,'Content-Type':'application/json'},body:JSON.stringify(body)});
  const body={phone:'923001234567',message:'',request_id:'audio-request-1',media:{mimetype:'audio/ogg',data:'aGVsbG8=',voice:true}};
  assert.equal((await send(body)).status,200); assert.equal((await send(body)).status,200);
  assert.equal(calls.length,1); assert.equal(calls[0].media.data,'aGVsbG8='); assert.equal(calls[0].options.sendAudioAsVoice,true);
  assert.equal((await send({...body,request_id:'bad-media-request',media:{mimetype:'text/html',data:'aGVsbG8='}})).status,400);
});
