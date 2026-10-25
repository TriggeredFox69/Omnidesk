const { test } = require('node:test');
const assert = require('node:assert/strict');
const { EventEmitter } = require('node:events');
const { createService } = require('../service');
const token = 'test-token-'.repeat(5);

async function setup(t) {
  const client = new EventEmitter();
  let sends = 0, creates = 0;
  client.initialize = async () => { client.emit('qr', 'test-qr'); };
  client.destroy = async () => {};
  client.info = { wid: { user: '923001234567' } };
  client.getNumberId = async number => number === '920000000000' ? null : { _serialized: `${number}@c.us` };
  client.sendMessage = async () => {
    sends++;
    await new Promise(resolve => setTimeout(resolve, 15));
    return { id: { _serialized: `message-${sends}` }, ack: 0 };
  };
  client.getMessageById = async () => null;
  const service = createService({ token, createClient: () => { creates++; return client; }, qrImage: async () => 'data:image/png;base64,test' });
  await new Promise(resolve => service.server.listen(0, '127.0.0.1', resolve));
  t.after(() => service.close());
  const base = `http://127.0.0.1:${service.server.address().port}`;
  const request = async (path, body, auth = token) => {
    const res = await fetch(base + path, { method: body ? 'POST' : 'GET',
      headers: { Authorization: `Bearer ${auth}`, 'Content-Type': 'application/json' },
      ...(body ? { body: JSON.stringify(body) } : {}) });
    return { code: res.status, ...(await res.json()) };
  };
  return { service, client, request, sends: () => sends, creates: () => creates };
}
const message = { phone: '923001234567', message: 'Hello', request_id: 'campaign-request-1' };

test('bridge rejects unauthorized access including QR and send', async t => {
  const { request, sends } = await setup(t);
  assert.equal((await request('/status', null, 'wrong')).code, 401);
  assert.equal((await request('/send', message, '')).code, 401);
  assert.equal(sends(), 0);
});

test('link lifecycle clears QR after authentication, restores number only when ready', async t => {
  const { request, client, creates } = await setup(t);
  assert.equal((await request('/status')).is_connected, false);
  await request('/connect', {});
  await request('/connect', {});
  assert.equal(creates(), 1);
  const qr = await request('/status');
  assert.equal(qr.state, 'qr');
  assert.match(qr.qr, /^data:image/);
  client.emit('authenticated');
  assert.equal((await request('/status')).qr, null);
  assert.equal((await request('/status')).is_connected, false);
  client.emit('ready');
  assert.equal((await request('/status')).connected_number, '923001234567');
  assert.equal((await request('/status')).is_connected, true);
  client.emit('disconnected');
  assert.equal((await request('/status')).is_connected, false);
});

test('send waits for a real connection; same request can resume after linking', async t => {
  const { request, client, sends } = await setup(t);
  assert.equal((await request('/send', message)).success, false);
  assert.equal(sends(), 0);
  await request('/connect', {}); client.emit('ready');
  assert.equal((await request('/send', message)).success, true);
  assert.equal(sends(), 1);
});

test('concurrent and repeated submissions send only once; changed payload rejected', async t => {
  const { request, client, sends } = await setup(t);
  await request('/connect', {}); client.emit('ready');
  const results = await Promise.all([request('/send', message), request('/send', message)]);
  assert.ok(results.every(result => result.success));
  assert.equal(results[0].message_id, results[1].message_id);
  assert.equal(sends(), 1);
  assert.equal((await request('/send', { ...message, message: 'Changed' })).code, 409);
  assert.equal(sends(), 1);
});

test('unknown recipient is not submitted and ACK events supply actual delivery state', async t => {
  const { request, client, sends } = await setup(t);
  await request('/connect', {}); client.emit('ready');
  assert.equal((await request('/send', { ...message, phone: '920000000000' })).code, 400);
  assert.equal(sends(), 0);
  const sent = await request('/send', message);
  assert.equal((await request('/receipt?id=' + sent.message_id)).status, 'accepted');
  client.emit('message_ack', { id: { fromMe: true, _serialized: sent.message_id } }, 2);
  assert.equal((await request('/receipt?id=' + sent.message_id)).status, 'delivered');
  client.emit('message_ack', { id: { fromMe: true, _serialized: sent.message_id } }, -1);
  assert.equal((await request('/receipt?id=' + sent.message_id)).status, 'failed');
});

test('invalid request data never reaches WhatsApp', async t => {
  const { request, client, sends } = await setup(t);
  await request('/connect', {}); client.emit('ready');
  for (const body of [{ ...message, phone: 'bad' }, { ...message, message: '' }, { ...message, request_id: '' }]) {
    assert.equal((await request('/send', body)).code, 400);
  }
  assert.equal(sends(), 0);
});
