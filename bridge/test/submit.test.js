const { test } = require('node:test');
const assert = require('node:assert/strict');
const { EventEmitter } = require('node:events');
const { submitOnce } = require('../submit');
const recipient = '923001234567@c.us';
const outgoing = { id: { _serialized: 'outgoing-id', fromMe: true, remote: recipient }, to: recipient, body: 'Hello', ack: 1 };

test('undefined return with outgoing event confirms submission without resending', async () => {
  const client = new EventEmitter(); let sends = 0;
  client.sendMessage = async (_, __, options) => {
    sends++; assert.equal(options.waitUntilMsgSent, true);
    client.emit('message_create', outgoing);
    return undefined;
  };
  assert.equal((await submitOnce(client, recipient, 'Hello', 100)).id._serialized, 'outgoing-id');
  assert.equal(sends, 1);
  assert.equal(client.listenerCount('message_create'), 0);
});

test('delayed matching acknowledgement recovers missing return and ignores unrelated messages', async () => {
  const client = new EventEmitter();
  client.sendMessage = async () => {
    client.emit('message_create', { ...outgoing, to: 'another', id: { ...outgoing.id, remote: 'another' } });
    client.emit('message_create', { ...outgoing, body: 'Different text' });
    setTimeout(() => client.emit('message_ack', outgoing, 1), 10);
  };
  assert.equal((await submitOnce(client, recipient, 'Hello', 100)).id._serialized, 'outgoing-id');
});

test('missing confirmation stays uncertain; no automatic retry or invented success', async () => {
  const client = new EventEmitter(); let sends = 0;
  client.sendMessage = async () => { sends++; };
  await assert.rejects(submitOnce(client, recipient, 'Hello', 20), /may already have been sent/);
  assert.equal(sends, 1);
  assert.equal(client.listenerCount('message_ack'), 0);
});

test('LID aliases match the same recipient', async () => {
  const client = new EventEmitter();
  client.getContactLidAndPhone = async () => [{ pn: recipient, lid: '123@lid' }];
  client.sendMessage = async () => { client.emit('message_create', { ...outgoing, to: '123@lid', id: { ...outgoing.id, remote: '123@lid' } }); };
  assert.equal((await submitOnce(client, recipient, 'Hello', 100)).id._serialized, 'outgoing-id');
});

test('new outgoing chat record confirms a send when neither return nor events provide an ID', async () => {
  const client = new EventEmitter(); let sends = 0;
  const old = { ...outgoing, id: { ...outgoing.id, _serialized: 'old-id' }, timestamp: Math.floor(Date.now() / 1000) };
  const fresh = { ...outgoing, timestamp: Math.floor(Date.now() / 1000) };
  client.getChatById = async () => ({ fetchMessages: async () => sends ? [old, fresh] : [old] });
  client.sendMessage = async () => { sends++; };
  assert.equal((await submitOnce(client, recipient, 'Hello', 2000)).id._serialized, 'outgoing-id');
  assert.equal(sends, 1);
});

test('an identical pre-existing chat message is never confirmation of a new send', async () => {
  const client = new EventEmitter();
  const old = { ...outgoing, timestamp: Math.floor(Date.now() / 1000) };
  client.getChatById = async () => ({ fetchMessages: async () => [old] });
  client.sendMessage = async () => undefined;
  await assert.rejects(submitOnce(client, recipient, 'Hello', 650), /unconfirmed/);
});
