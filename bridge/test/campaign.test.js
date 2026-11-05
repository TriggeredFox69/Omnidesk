'use strict';
const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const os = require('node:os');
const { createCampaignManager, resolveSpintax, personalizeMessage } = require('../campaign');

test('resolveSpintax picks options and ignores variables', () => {
  const template = '{Salam|AoA|Hello} {name}!';
  const resolved = resolveSpintax(template);
  assert.match(resolved, /^(Salam|AoA|Hello) \{name\}!$/);
});

test('personalizeMessage injects contact details and fallback', () => {
  const contact = { name: 'Zeeshan', phone: '923001234567', display: '+92 300 1234567', city: 'Lahore' };
  const template = 'Hi {name}, in {city}!';
  const personalized = personalizeMessage(template, contact);
  assert.equal(personalized, 'Hi Zeeshan, in Lahore!');
});

test('createCampaignManager starts, executes, pauses, and stops', async () => {
  const tmpDir = fs.mkdtempSync(path.join(os.tmpdir(), 'camp-test-'));
  const sent = [];
  const fakeSend = async (payload) => {
    sent.push(payload);
    return { success: true, message_id: 'wa_' + sent.length };
  };

  const leadsFile = path.join(tmpDir, 'leads.json');
  fs.writeFileSync(leadsFile, JSON.stringify([]));

  const mgr = createCampaignManager({
    send: fakeSend,
    dataDir: tmpDir,
    getLeadsPath: () => leadsFile
  });

  const audience = [
    { phone: '923001111111', name: 'Lead 1', display: '+92 300 1111111' },
    { phone: '923002222222', name: 'Lead 2', display: '+92 300 2222222' }
  ];

  const status = mgr.start({
    name: 'Test Outreach',
    message: 'Hello {name}',
    audience,
    minDelay: 1,
    maxDelay: 1
  });

  assert.equal(status.status, 'running');
  assert.equal(status.totalContacts, 2);

  // Wait a moment for first step to fire
  await new Promise(r => setTimeout(r, 50));
  assert.equal(sent.length >= 1, true);

  const paused = mgr.pause();
  assert.equal(paused.status, 'paused');

  const stopped = mgr.stop();
  assert.equal(stopped.status, 'stopped');

  // Verify leads.json was updated with outbound message
  const savedLeads = JSON.parse(fs.readFileSync(leadsFile, 'utf8'));
  assert.equal(savedLeads.length >= 1, true);

  fs.rmSync(tmpDir, { recursive: true, force: true });
});
