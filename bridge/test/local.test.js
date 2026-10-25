const { test } = require('node:test');
const assert = require('node:assert/strict');
const { EventEmitter } = require('node:events');
const { startLocal } = require('../local');

test('PC connection exposes only the authenticated bridge, with the same local session', async t => {
  const net = require('node:net');
  const probe = net.createServer();
  await new Promise(resolve => probe.listen(0, '127.0.0.1', resolve));
  const bridgePort = probe.address().port;
  await new Promise(resolve => probe.close(resolve));
  const client = new EventEmitter();
  client.initialize = async () => client.emit('ready');
  client.destroy = async () => {};
  const bridgeToken = 'a'.repeat(64);
  const app = await startLocal({ port: 0, openBrowser: false, bridgePort, bridgeToken,
    createClient: () => client, qrImage: async () => '' });
  t.after(() => app.close());
  const bridgeOrigin = `http://127.0.0.1:${bridgePort}`;
  const headers = { Authorization: `Bearer ${bridgeToken}` };
  for (const route of ['/', '/status', '/events', '/data/whatsapp-bridge/token']) {
    assert.equal((await fetch(bridgeOrigin + route)).status, 401);
  }
  assert.equal((await fetch(bridgeOrigin + '/', { headers })).status, 404);
  assert.equal((await fetch(bridgeOrigin + '/status', { headers })).status, 200);
  const home = await fetch(app.origin);
  const cookie = home.headers.get('set-cookie').split(';')[0];
  await fetch(app.origin + '/api/direct_sender.php', { method: 'POST',
    headers: { Cookie: cookie, 'Content-Type': 'application/json' }, body: JSON.stringify({ action: 'connect_whatsapp' }) });
  await new Promise(resolve => setImmediate(resolve));
  assert.equal((await (await fetch(bridgeOrigin + '/status', { headers })).json()).state, 'ready');
});

test('local launcher serves UI, protects private files and proxies QR/send without PHP', async t => {
  const client = new EventEmitter();
  client.initialize = async () => client.emit('qr', 'test');
  client.destroy = async () => {};
  client.info = { wid: { user: '923001234567' } };
  client.getNumberId = async number => ({ _serialized: number + '@c.us' });
  const sent = [];
  client.sendMessage = async (recipient, message) => { sent.push({ recipient, message }); return { id: { _serialized: 'test-message' } }; };
  const app = await startLocal({ port: 0, openBrowser: false, createClient: () => client, qrImage: async () => 'data:image/png;base64,test' });
  t.after(() => app.close());
  const home = await fetch(app.origin);
  const cookie = home.headers.get('set-cookie').split(';')[0];
  assert.match(await home.text(), /window.NUNNO_LOCAL_LIVE=true/);
  assert.equal((await fetch(app.origin + '/api/direct_sender.php?action=get_sender_info')).status, 401);
  for (const target of ['/data/whatsapp-bridge/token', '/bridge/server.js', '/assets/..%2fdata/whatsapp-bridge/token', '/api/config.php']) {
    assert.ok([401, 404].includes((await fetch(app.origin + target)).status));
  }
  const request = (body, origin = app.origin) => fetch(app.origin + '/api/direct_sender.php', {
    method: 'POST', headers: { Cookie: cookie, Origin: origin, 'Content-Type': 'application/json' }, body: JSON.stringify(body)
  });
  assert.equal((await request({ action: 'connect_whatsapp' }, 'https://other.example')).status, 403);
  assert.equal((await request({ action: 'connect_whatsapp' })).status, 200);
  const status = await (await fetch(app.origin + '/api/direct_sender.php?action=get_sender_info', { headers: { Cookie: cookie } })).json();
  assert.equal(status.state, 'qr');
  client.emit('ready');
  assert.equal((await (await request({ action: 'send_direct', phone: '03001234567', message: 'Test', request_id: 'local-test-1' })).json()).success, true);
  assert.deepEqual(sent, [{ recipient: '923001234567@c.us', message: 'Test' }]);
});
