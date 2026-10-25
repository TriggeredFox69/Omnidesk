const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const http = require('node:http');
const { spawn } = require('node:child_process');

test('PHP adapter authenticates, restricts QR access, normalizes numbers and logs submissions', { skip: !process.env.PHP_TEST_BIN }, async t => {
  const dir = fs.mkdtempSync(path.join(os.tmpdir(), 'nunno-php-test-'));
  fs.mkdirSync(path.join(dir, 'api')); fs.mkdirSync(path.join(dir, 'data'));
  for (const file of ['direct_sender.php', 'whatsapp_bridge.php', 'config.php']) {
    fs.copyFileSync(path.resolve(__dirname, '../../api', file), path.join(dir, 'api', file));
  }
  let recipient;
  const token = 'private-test-token'.repeat(3);
  const server = http.createServer((req, res) => {
    if (req.headers.authorization !== `Bearer ${token}`) { res.writeHead(401); res.end('{}'); return; }
    res.setHeader('Content-Type', 'application/json');
    if (req.url === '/status' || req.url === '/connect') {
      res.end(JSON.stringify({ success: true, state: 'qr', qr: 'data:image/png;base64,test', is_configured: false }));
    } else {
      let raw = ''; req.on('data', chunk => raw += chunk);
      req.on('end', () => {
        recipient = JSON.parse(raw).phone;
        res.end(JSON.stringify({ success: true, dispatch_mode: 'whatsapp_web', message_id: 'fake-id', from_number: '923001111111' }));
      });
    }
  });
  await new Promise(resolve => server.listen(0, '127.0.0.1', resolve));
  t.after(() => server.close());
  async function run(user, input) {
    const encode = value => Buffer.from(JSON.stringify(value)).toString('base64');
    fs.writeFileSync(path.join(dir, 'test.php'), `<?php session_start(); $_SESSION['omnidesk_user']=json_decode(base64_decode('${encode(user)}'),true); $_SERVER['REQUEST_METHOD']='POST'; $_POST=json_decode(base64_decode('${encode(input)}'),true); require __DIR__.'/api/direct_sender.php';`);
    const args = process.env.PHP_TEST_EXT_DIR ? ['-d', `extension_dir=${process.env.PHP_TEST_EXT_DIR}`, '-d', 'extension=curl'] : [];
    return new Promise((resolve, reject) => {
      const child = spawn(process.env.PHP_TEST_BIN, [...args, path.join(dir, 'test.php')], {
        windowsHide: true, env: { ...process.env, WHATSAPP_SEND_MODE: 'whatsapp_web',
          WHATSAPP_BRIDGE_TOKEN: token, WHATSAPP_BRIDGE_URL: `http://127.0.0.1:${server.address().port}` }
      });
      let out = '', err = '';
      child.stdout.on('data', chunk => out += chunk); child.stderr.on('data', chunk => err += chunk);
      child.on('error', reject);
      child.on('exit', code => {
        try { assert.equal(code, 0); assert.equal(err, ''); resolve(JSON.parse(out)); } catch (error) { reject(error); }
      });
    });
  }
  assert.equal((await run(null, { action: 'get_sender_info' })).success, false);
  assert.equal((await run({ role: 'member' }, { action: 'connect_whatsapp' })).success, false);
  assert.equal((await run({ role: 'member' }, { action: 'get_sender_info' })).qr, undefined);
  assert.match((await run({ role: 'admin' }, { action: 'get_sender_info' })).qr, /^data:image/);
  assert.equal((await run({ role: 'admin' }, { action: 'send_direct', phone: '03001234567', message: 'Test', request_id: 'test-request' })).success, true);
  assert.equal(recipient, '923001234567');
  assert.equal(JSON.parse(fs.readFileSync(path.join(dir, 'data/campaign_logs.json'))).length, 1);
});
