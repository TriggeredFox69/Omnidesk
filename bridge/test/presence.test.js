'use strict';
const { test } = require('node:test');
const assert = require('node:assert/strict');
const { EventEmitter } = require('node:events');
const { readPresence } = require('../presence');
const { createService } = require('../service');

test('readPresence returns unknown when client is uninitialized', async () => {
  const result = await readPresence({}, '923046588806');
  assert.equal(result.success, true);
  assert.equal(result.state, 'unknown');
});

test('readPresence delegates to BaileysClient getPresence and subscribePresence', async () => {
  let subscribed = false;
  const mockClient = {
    subscribePresence: async phone => { subscribed = (phone === '923046588806'); },
    getPresence: phone => ({ success: true, state: 'typing', label: 'typing...', timestamp: Date.now() })
  };

  const result = await readPresence(mockClient, '923046588806');
  assert.equal(result.success, true);
  assert.equal(result.state, 'typing');
  assert.equal(result.label, 'typing...');
  assert.equal(subscribed, true);
});

test('service POST /presence sends outbound presence through client', async t => {
  const token = 'presence-token-'.repeat(3);
  let presenceCalled = null;

  const mockClient = new EventEmitter();
  mockClient.initialize = async () => {};
  mockClient.destroy = async () => {};
  mockClient.sendPresence = async (phone, type) => {
    presenceCalled = { phone, type };
  };

  const service = createService({
    token,
    createClient: () => mockClient,
    qrImage: async () => ''
  });
  await new Promise(r => service.server.listen(0, '127.0.0.1', r));
  t.after(() => service.close());

  service.connect();

  const res = await fetch(`http://127.0.0.1:${service.server.address().port}/presence`, {
    method: 'POST',
    headers: {
      Authorization: `Bearer ${token}`,
      'Content-Type': 'application/json'
    },
    body: JSON.stringify({ phone: '923046588806', type: 'recording' })
  });

  assert.equal(res.status, 200);
  const data = await res.json();
  assert.equal(data.success, true);
  assert.deepEqual(presenceCalled, { phone: '923046588806', type: 'recording' });
});
