const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

test('saved voice history loads before linking; reconnect resumes live chat sync', async () => {
  // Chats only exist for numbers that are already leads; an event for any other number is ignored.
  const state = { currentUser: { id: 'test' }, senderConfigured: false, currentTab: 'inbox',
    leads: [{ id: 'lead_923001234567', phone: '923001234567', name: 'Lead', messages: [] }, { id: 'lead_x', phone: '923119998877', name: 'Other', messages: [] }] };
  const calls = [];
  const presence = {};
  const context = vm.createContext({ window: {}, setInterval() {}, AbortSignal,
    document: { hidden: false, addEventListener: () => {}, getElementById: () => presence },
    fetch: async (url, options) => {
      const request = JSON.parse(options.body);
      if (!url || String(url).includes('direct_sender.php')) calls.push(request.action);
      return { ok: true, json: async () => request.action === 'get_events'
        ? { success: true, epoch: 'saved', cursor: 1, messages: request.after ? [] : [
          { id: 'voice', waMessageId: 'voice-id', phone: '923001234567', type: 'audio', sender: 'lead', timestamp: 100, hasMedia: true }
        ] }
        : { success: true, state: 'unknown', label: 'Unavailable' } };
    }
  });
  vm.runInContext(fs.readFileSync(path.resolve(__dirname, '../../assets/js/live-inbox.js'), 'utf8'), context);
  const inbox = context.window.NunnoLiveInbox({ state, getLead: id => state.leads.find(l => l.id === id),
    renderList() {}, renderChat() {}, toast() {}, stopAudio() {} });
  await inbox.poll();
  assert.equal(state.leads[0].messages[0].type, 'audio');
  assert.deepEqual(calls, ['get_events']);
  assert.ok(presence.textContent === '' || /Connection unavailable/.test(presence.textContent));
  state.senderConfigured = true;
  await inbox.poll();
  assert.equal(state.leads[0].messages.length, 1);
  // Historical chat sync was removed on purpose (Chatly keeps only messages after linking).
  assert.deepEqual(calls.slice(1), ['get_events', 'get_presence', 'get_media']);
});
