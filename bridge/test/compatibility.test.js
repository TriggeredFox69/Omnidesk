const { test } = require('node:test');
const assert = require('node:assert/strict');
const vm = require('node:vm');
const { installMessageIdCompatibility } = require('../compatibility');

test('new WhatsApp MsgKey without _serialized supports lookups and serialized events', async () => {
  class MsgKey { constructor() { this.fromMe = true; this.remote = '123@lid'; this.id = 'ABC'; this.self = 'out'; } toString() { return 'true_123@lid_ABC_out'; } }
  const context = vm.createContext({ window: { require: () => MsgKey, WWebJS: { getMessageModel: m => ({ id: { ...m.id }, ack: 3 }) } } });
  const client = { pupPage: { evaluate: fn => vm.runInContext(`(${fn})()`, context) } };
  await installMessageIdCompatibility(client);
  await installMessageIdCompatibility(client);
  const message = { id: new MsgKey() };
  assert.equal(message.id._serialized, 'true_123@lid_ABC_out');
  assert.equal(context.window.WWebJS.getMessageModel(message).id._serialized, 'true_123@lid_ABC_out');
  assert.equal(context.window.WWebJS.getMessageModel(message).ack, 3);
});

test('legacy MsgKey constructors can still assign their own _serialized field', async () => {
  class MsgKey { constructor() { this._serialized = 'legacy-id'; } toString() { return this._serialized; } }
  const context = vm.createContext({ window: { require: () => MsgKey, WWebJS: { getMessageModel: m => ({ id: { ...m.id } }) } } });
  await installMessageIdCompatibility({ pupPage: { evaluate: fn => vm.runInContext(`(${fn})()`, context) } });
  assert.equal(new MsgKey()._serialized, 'legacy-id');
});
