'use strict';
const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const os = require('node:os');
const { EventEmitter } = require('node:events');
const { normalizeProxyUrl, maskProxyUrl, buildProxyAgent, resolveProxy } = require('../proxy');
const { createDevicePool } = require('../devices');

test('proxy URL normalization and credential masking', () => {
  assert.equal(normalizeProxyUrl('http://user:secret@1.2.3.4:8080'), 'http://user:secret@1.2.3.4:8080/');
  assert.equal(maskProxyUrl('http://user:secret@1.2.3.4:8080'), 'http://1.2.3.4:8080');
  assert.equal(maskProxyUrl('socks5://u:p@10.0.0.5:1080'), 'socks5://10.0.0.5:1080');
  assert.equal(normalizeProxyUrl('ftp://1.2.3.4:21'), '');
  assert.equal(buildProxyAgent('not a url'), null);

  const httpAgent = buildProxyAgent('http://user:pass@1.2.3.4:8080');
  assert.ok(httpAgent && httpAgent.agent && httpAgent.fetchAgent);
  assert.equal(httpAgent.masked, 'http://1.2.3.4:8080');

  const socksAgent = buildProxyAgent('socks5://user:pass@1.2.3.4:1080');
  assert.ok(socksAgent && socksAgent.agent && socksAgent.fetchAgent);
  assert.equal(socksAgent.masked, 'socks5://1.2.3.4:1080');
});

test('resolveProxy picks sticky proxy per device, user, company, or deterministic pool', () => {
  const dir = fs.mkdtempSync(path.join(os.tmpdir(), 'proxy-cfg-'));
  const dataDir = path.join(dir, 'whatsapp-bridge');
  fs.mkdirSync(dataDir, { recursive: true });
  const usersFile = path.join(dir, 'users.json');
  fs.writeFileSync(usersFile, JSON.stringify([
    { id: 'TM-1', company_id: 'COMP-1', proxy_url: 'http://u:p@10.0.0.1:8000' },
    { id: 'TM-2', company_id: 'COMP-2' },
    { id: 'TM-3', company_id: 'COMP-3' }
  ]));
  fs.writeFileSync(path.join(dataDir, 'proxies.json'), JSON.stringify({
    devices: { 'TM-OVERRIDE': 'socks5://10.0.0.99:1080' },
    companies: { 'COMP-2': 'http://10.0.0.2:8000' },
    pool: ['http://10.0.1.1:8000', 'http://10.0.1.2:8000', 'http://10.0.1.3:8000']
  }));

  assert.equal(resolveProxy({ deviceId: 'TM-OVERRIDE', metaProxy: 'http://10.0.0.50:8080', dataDir, usersFile }), 'http://10.0.0.50:8080/');
  assert.equal(resolveProxy({ deviceId: 'TM-OVERRIDE', dataDir, usersFile }), 'socks5://10.0.0.99:1080');
  assert.equal(resolveProxy({ deviceId: 'TM-1', dataDir, usersFile }), 'http://u:p@10.0.0.1:8000/');
  assert.equal(resolveProxy({ deviceId: 'TM-2', dataDir, usersFile }), 'http://10.0.0.2:8000/');

  const p3a = resolveProxy({ deviceId: 'TM-3', dataDir, usersFile });
  const p3b = resolveProxy({ deviceId: 'TM-3', dataDir, usersFile });
  assert.ok(p3a.startsWith('http://10.0.1.'));
  assert.equal(p3a, p3b, 'pool assignment is sticky per device');

  fs.rmSync(dir, { recursive: true, force: true });
});

test('devicePool setProxy persists sticky proxy in meta.json and masks snapshot', async () => {
  const dir = fs.mkdtempSync(path.join(os.tmpdir(), 'dev-proxy-'));
  const seenOpts = [];
  const pool = createDevicePool({
    dataDir: dir,
    qrImage: async v => v,
    createClient: (_authPath, _name, opts) => {
      seenOpts.push(opts);
      const c = new EventEmitter();
      c.initialize = async () => {};
      c.destroy = async () => {};
      return c;
    }
  });
  const snap = pool.setProxy('TM-ASKARI', 'socks5://user:secret@45.10.20.30:1080');
  assert.equal(snap.proxy_configured, true);
  assert.equal(snap.proxy_host, 'socks5://45.10.20.30:1080');

  pool.connect('TM-ASKARI', 'Askari Raza', true);
  await new Promise(r => setTimeout(r, 30));
  assert.equal(seenOpts[0].proxyUrl, 'socks5://user:secret@45.10.20.30:1080');

  await pool.closeAll();
  fs.rmSync(dir, { recursive: true, force: true });
});
