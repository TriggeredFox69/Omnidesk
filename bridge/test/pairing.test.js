const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const { EventEmitter } = require('node:events');
const { createDevicePool } = require('../devices');

// A fake WhatsApp client: produces a QR shortly after start, and returns a pairing code.
function fakeClientFactory(log) {
  return () => {
    const c = new EventEmitter();
    c.initialize = async () => { setTimeout(() => c.emit('qr', 'qr-data'), 20); };
    c.destroy = async () => {};
    c.requestPairingCode = async phone => { log.push(phone); return 'ABCD1234'; };
    return c;
  };
}

test('link with phone number: starts the device, waits for its QR, returns a formatted code', async t => {
  const dataDir = fs.mkdtempSync(path.join(os.tmpdir(), 'chatly-pair-'));
  t.after(() => fs.rmSync(dataDir, { recursive: true, force: true }));
  const asked = [];
  const pool = createDevicePool({ dataDir, createClient: fakeClientFactory(asked), qrImage: async v => 'img:' + v });
  const snap = await pool.pairWithPhone('tm-1', '0300 1234567', 'Ali');
  assert.equal(snap.pairing_code, 'ABCD-1234');
  assert.equal(snap.pairing_phone, '923001234567');
  assert.deepEqual(asked, ['923001234567'], 'Pakistani 03… numbers are sent in international form');
  assert.equal(pool.get('tm-1').pairing_code, 'ABCD-1234', 'the status poll sees the code while it is valid');
});

test('link with phone number: rejects a number that is too short, without starting anything', async t => {
  const dataDir = fs.mkdtempSync(path.join(os.tmpdir(), 'chatly-pair-'));
  t.after(() => fs.rmSync(dataDir, { recursive: true, force: true }));
  const asked = [];
  const pool = createDevicePool({ dataDir, createClient: fakeClientFactory(asked), qrImage: async v => v });
  await assert.rejects(pool.pairWithPhone('tm-2', '12345'), /country code/);
  assert.equal(pool.get('tm-2').state, 'disconnected');
  assert.deepEqual(asked, []);
});
