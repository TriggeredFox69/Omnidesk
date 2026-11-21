'use strict';
// bridge.bundle.js is what the server runs (it has Baileys built in, so cPanel needs
// no npm install). It must be rebuilt whenever bridge code changes: cd bridge && npm run build
const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

test('bridge.bundle.js is rebuilt from the current bridge code', () => {
  const dir = path.join(__dirname, '..');
  const bundle = fs.readFileSync(path.join(dir, 'bridge.bundle.js'), 'utf8');
  const version = /version: '([^']+)', engine: 'baileys'/.exec(fs.readFileSync(path.join(dir, 'service.js'), 'utf8'))?.[1];
  assert.ok(version, 'service.js declares a version');
  assert.ok(bundle.includes(`version:"${version}"`), `bundle reports a different version than service.js (${version}). Run: cd bridge && npm run build`);
  const php = fs.readFileSync(path.join(dir, '../api/whatsapp_bridge.php'), 'utf8');
  assert.ok(php.includes(`EXPECTED_BRIDGE_VERSION = '${version}'`), 'api/whatsapp_bridge.php EXPECTED_BRIDGE_VERSION must equal the bridge version');
  // Every source module must be inside the bundle (spot-check new modules).
  for (const marker of ['createAtomicAuthState', 'lid-mapping.update', 'createDevicePool']) {
    assert.ok(bundle.includes(marker), `bundle is missing ${marker}; rebuild it`);
  }
});
