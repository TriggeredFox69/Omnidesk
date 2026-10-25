const { test } = require('node:test');
const assert = require('node:assert/strict');
const { createInbox, statusOf } = require('../inbox');
const { readPresence } = require('../presence');
const { createService } = require('../service');
const { EventEmitter } = require('node:events');

test('incoming text, audio and images are deduplicated and mapped from LID to a phone', async () => {
  const inbox = createInbox({ getClient: () => ({ getContactLidAndPhone: async () => [{ pn: '923001234567@c.us' }] }) });
  for (const type of ['chat','ptt','image']) {
    const message = { id: { _serialized: 'id-' + type, remote: '123@lid', fromMe: false }, body: 'hello', type, hasMedia: type !== 'chat', timestamp: 100 };
    await inbox.ingest(message); await inbox.ingest(message);
  }
  const result = inbox.events(0);
  assert.equal(result.messages.length, 3);
  assert.deepEqual(result.messages.map(m => m.type), ['text','audio','image']);
  assert.ok(result.messages.every(m => m.phone === '923001234567' && m.sender === 'lead'));
  assert.equal(inbox.events(result.cursor).messages.length, 0);
});

test('ACKs distinguish a single sent tick, delivered double tick and read double tick', async () => {
  const inbox = createInbox({ getClient: () => ({}) });
  const message = { id: { _serialized: 'outgoing', remote: '923001234567@c.us', fromMe: true }, body: 'hi', timestamp: 100 };
  await inbox.ingest(message);
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
