'use strict';
// The bridge's Supabase chat store, with a fake Supabase (no network).
const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const { createStore } = require('../store');

function setup(t, { on = true } = {}) {
  const dir = fs.mkdtempSync(path.join(os.tmpdir(), 'chatly-store-'));
  t.after(() => fs.rmSync(dir, { recursive: true, force: true }));
  fs.writeFileSync(path.join(dir, 'supabase.php'), "<?php return ['url' => 'https://abc.supabase.co', 'key' => 'sb_secret_x'];");
  if (on) fs.writeFileSync(path.join(dir, 'leads_store_supabase.on'), '');
  const calls = [];
  let down = false, keys = { rev: 5, rows: [['001112233', 'TM-1', '', false]] };
  const fetchImpl = async (url, opts) => {
    const fn = url.split('/rpc/')[1];
    const body = JSON.parse(opts.body);
    calls.push({ fn, body, apikey: opts.headers.apikey, auth: opts.headers.Authorization });
    if (down) throw new Error('offline');
    const out = fn === 'chatly_lead_keys' ? keys : fn === 'chatly_add_messages' ? body.p_items.length : 1;
    return { ok: true, status: 200, text: async () => JSON.stringify(out) };
  };
  const store = createStore({ dataDir: dir, fetchImpl, flushMs: 10, log: { warn() {} } });
  return { dir, store, calls, setDown: v => { down = v; }, setKeys: k => { keys = k; } };
}

test('off without the switch file; on with it (secret key sent as apikey only)', async t => {
  assert.equal(setup(t, { on: false }).store.enabled(), false);
  const { store, calls } = setup(t);
  assert.equal(store.enabled(), true);
  store.queueMessage('COMP-4', '923001112233', { id: 'a' }, true);
  await store.flush();
  assert.equal(calls[0].apikey, 'sb_secret_x');
  assert.equal(calls[0].auth, undefined);
});

test('messages are batched per company and written before ticks', async t => {
  const { store, calls } = setup(t);
  store.queueStatus('COMP-4', 'WA1', 'read');
  store.queueMessage('COMP-4', '923001112233', { id: 'm1', waMessageId: 'WA1' }, true);
  store.queueMessage('COMP-4', '923001112233', { id: 'm2', waMessageId: 'WA2' }, true);
  store.queueMessage('COMP-9', '923009998877', { id: 'm3' }, false);
  await store.flush();
  assert.deepEqual(calls.map(c => c.fn), ['chatly_add_messages', 'chatly_add_messages', 'chatly_set_statuses']);
  assert.equal(calls[0].body.p_items.length, 2);
  assert.equal(store.queuedCount(), 0);
});

test('when Supabase is down nothing is lost: kept in order, saved to a file, written later', async t => {
  const { dir, store, calls, setDown } = setup(t);
  setDown(true);
  store.queueMessage('COMP-4', '923001112233', { id: 'm1' }, true);
  store.queueStatus(null, 'WA1', 'delivered');
  await store.flush();
  store.queueMessage('COMP-4', '923001112233', { id: 'm2' }, true);   // arrives while down
  assert.equal(store.queuedCount(), 3);
  assert.ok(fs.existsSync(path.join(dir, 'whatsapp-bridge', 'store_queue.json')));
  setDown(false);
  calls.length = 0;
  await store.flush();
  assert.deepEqual(calls[0].body.p_items.map(i => i.msg.id), ['m1', 'm2'], 'oldest first');
  assert.equal(store.queuedCount(), 0);
  assert.ok(!fs.existsSync(path.join(dir, 'whatsapp-bridge', 'store_queue.json')), 'file removed once written');
});

test('is-a-lead check uses a local copy, catches up on unknown numbers, and learns new leads', async t => {
  const { store, calls, setKeys } = setup(t);
  assert.equal(await store.isLead('COMP-4', '+92 300 1112233'), true);
  const asked = calls.length;
  assert.equal(await store.isLead('COMP-4', '923001112233'), true);
  assert.equal(calls.length, asked, 'a known number needs no database call');
  setKeys({ rev: 6, rows: [] });
  assert.equal(await store.isLead('COMP-4', '923007776655'), false, 'unknown stays unknown');
  store.rememberLead('COMP-4', '923007776655');
  assert.equal(await store.isLead('COMP-4', '923007776655'), true, 'a lead the bridge just created is known at once');
});
