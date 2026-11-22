'use strict';
// Regression: the inbox poll used to send one leads_sync request per message. A page
// load replays up to 2,000 messages, so Chrome ran out of connections
// (ERR_INSUFFICIENT_RESOURCES) and "Start campaign" failed with "Failed to fetch".
const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

// The 40 numbers the messages below use are existing leads (chats only exist for leads).
const leadPhones = Array.from({ length: 40 }, (_, i) => '9230012345' + String(i).padStart(2, '0'));
function setup(eventsFor) {
  const state = { currentUser: { id: 'admin', role: 'admin' }, senderConfigured: false, currentTab: 'inbox',
    leads: leadPhones.map(phone => ({ id: 'lead_' + phone, phone, name: phone, messages: [] })) };
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
  assert.equal(state.leads.reduce((n, l) => n + l.messages.length, 0), 2000);
  assert.equal(synced.length, 0);
});

test('a chat with a number that is not a lead (personal chat) never becomes a lead', async () => {
  let round = 0;
  const stranger = { id: 'p1', waMessageId: 'personal-1', phone: '923459990000', type: 'text', text: 'subha uth kr message krna',
    sender: 'agent', name: 'Askari', timestamp: 5000, deliveryStatus: 'sent' };
  const { synced, inbox, state } = setup(() => {
    round++;
    if (round === 1) return { success: true, epoch: 'e1', cursor: 1, messages: [] };
    return { success: true, epoch: 'e1', cursor: 2, messages: round === 2 ? [stranger, { ...stranger, waMessageId: 'personal-2', sender: 'lead' }] : [] };
  });
  await inbox.poll();
  await inbox.poll();
  assert.equal(state.leads.length, 40, 'no "Unassigned Pool" chat was created');
  assert.ok(!state.leads.some(l => l.phone === '923459990000'));
  assert.equal(synced.length, 0, 'nothing was saved to the server');
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
