'use strict';
// Regression: the inbox poll used to send one leads_sync request per message. A page
// load replays up to 2,000 messages, so Chrome ran out of connections
// (ERR_INSUFFICIENT_RESOURCES) and "Start campaign" failed with "Failed to fetch".
const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

function setup(eventsFor) {
  const state = { currentUser: { id: 'admin', role: 'admin' }, senderConfigured: false, leads: [], currentTab: 'inbox' };
  const synced = [];
  const context = vm.createContext({ window: {}, setInterval() {}, AbortSignal,
    document: { hidden: false, addEventListener: () => {}, getElementById: () => ({}) },
    fetch: async (url, options) => {
      const request = JSON.parse(options.body);
      return { ok: true, json: async () => request.action === 'get_events' ? eventsFor(request.after) : { success: true } };
    }
  });
  vm.runInContext(fs.readFileSync(path.resolve(__dirname, '../../assets/js/live-inbox.js'), 'utf8'), context);
  context.window.NUNNO_SYNC_LEAD = (lead, extra) => synced.push(extra.newMessage.waMessageId);
  const inbox = context.window.NunnoLiveInbox({ state, getLead: id => state.leads.find(l => l.id === id),
    renderList() {}, renderChat() {}, toast() {}, stopAudio() {} });
  return { state, synced, inbox };
}

const msg = (i, extra = {}) => ({ id: 'm' + i, waMessageId: 'wa' + i, phone: '9230012345' + String(i % 40).padStart(2, '0'),
  type: 'text', text: 'hi ' + i, sender: i % 2 ? 'lead' : 'agent', timestamp: 1000 + i, deliveryStatus: 'sent', ...extra });

test('loading 2,000 saved messages sends no lead-save requests', async () => {
  const history = Array.from({ length: 2000 }, (_, i) => msg(i));
  const { synced, inbox, state } = setup(after => ({ success: true, epoch: 'e1', cursor: 2000, messages: after ? [] : history }));
  await inbox.poll();
  assert.equal(state.leads.length, 40);
  assert.equal(synced.length, 0);
});

test('after loading, only new messages and tick changes are saved, once each', async () => {
  let round = 0;
  const { synced, inbox } = setup(() => {
    round++;
    if (round === 1) return { success: true, epoch: 'e1', cursor: 2, messages: [msg(1), msg(2)] };
    if (round === 2) return { success: true, epoch: 'e1', cursor: 4, messages: [msg(3), msg(2, { deliveryStatus: 'read' })] };
    return { success: true, epoch: 'e1', cursor: 4, messages: [] };
  });
  await inbox.poll();
  await inbox.poll();
  await inbox.poll();
  assert.deepEqual(synced.sort(), ['wa2', 'wa3']);
});

test('a "bridge offline" placeholder does not restart the inbox and replay everything', async () => {
  let round = 0;
  const history = Array.from({ length: 300 }, (_, i) => msg(i));
  const seenAfter = [];
  const { synced, inbox } = setup(after => {
    round++; seenAfter.push(after);
    if (round === 2) return { success: true, epoch: 'online', cursor: after, messages: [] };   // PHP fallback
    return { success: true, epoch: 'e1', cursor: 300, messages: after ? [] : history };
  });
  await inbox.poll();   // load
  await inbox.poll();   // bridge slow: placeholder
  await inbox.poll();   // back
  assert.deepEqual(seenAfter, [0, 300, 300], 'cursor kept; no reload from zero');
  assert.equal(synced.length, 0);
});
