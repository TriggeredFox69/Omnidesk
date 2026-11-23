'use strict';
const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const os = require('node:os');
const { createCampaignManager: realCampaignManager, resolveSpintax, personalizeMessage } = require('../campaign');
// Live campaigns never send faster than one message every 30 s; tests run with 1 s gaps.
const createCampaignManager = opts => realCampaignManager({ minGapS: 1, ...opts });

test('live campaigns never send faster than every 30 s, nor slower than every 15 min', () => {
  const fs_ = require('node:fs'), os_ = require('node:os'), path_ = require('node:path');
  const dir = fs_.mkdtempSync(path_.join(os_.tmpdir(), 'camp-gap-'));
  const mgr = realCampaignManager({ send: async () => ({ success: true }), dataDir: dir, getLeadsPath: () => path_.join(dir, 'l.json'),
    getUsersPath: () => path_.join(dir, 'u.json'), mainStatus: () => ({ ready: true }) });
  const st = mgr.start({ message: 'Hi', delay: 10, audience: [{ phone: '923001112223' }], senders: [{ id: 'TM-1', name: 'A', roleType: 'member' }] });
  assert.equal(st.minDelay, 30);
  mgr.stop();
  const st2 = mgr.start({ message: 'Hi', delay: 5000, audience: [{ phone: '923001112224' }], senders: [{ id: 'TM-1', name: 'A', roleType: 'member' }] });
  assert.equal(st2.minDelay, 900);
  mgr.stop();
  fs_.rmSync(dir, { recursive: true, force: true });
});

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
    getLeadsPath: () => leadsFile,
    getUsersPath: () => path.join(tmpDir, 'users.json'),
    // the company's main WhatsApp is linked, so a TM without their own device sends through it
    mainStatus: () => ({ ready: true })
  });

  const audience = [
    { phone: '923001111111', name: 'Lead 1', display: '+92 300 1111111' },
    { phone: '923002222222', name: 'Lead 2', display: '+92 300 2222222' }
  ];

  const status = mgr.start({
    name: 'Test Outreach',
    message: 'Hello {name}',
    audience,
    senders: [{ id: 'TM-1', name: 'TM One', phone: '923000000001', roleType: 'member' }],
    minDelay: 1,
    maxDelay: 1
  });

  assert.equal(status.status, 'running');
  assert.equal(status.totalContacts, 2);

  // Wait a moment for first step to fire (step triggers after 100ms)
  await new Promise(r => setTimeout(r, 250));
  assert.equal(sent.length >= 1, true);

  const paused = mgr.pause();
  assert.equal(paused.status, 'paused');

  const stopped = mgr.stop();
  assert.equal(stopped.status, 'stopped');
  // stop() answers at once and saves right after (big campaigns take a moment to write)
  await new Promise(r => setImmediate(r));

  // Verify leads.json was updated with outbound message
  const savedLeads = JSON.parse(fs.readFileSync(leadsFile, 'utf8'));
  assert.equal(savedLeads.length >= 1, true);

  fs.rmSync(tmpDir, { recursive: true, force: true });
});

test('daily sent counts start again on a new Pakistan day; stop hands back unsent Auto Data numbers', async () => {
  const tmpDir = fs.mkdtempSync(path.join(os.tmpdir(), 'camp-test-'));
  const usersFile = path.join(tmpDir, 'users.json');
  // yesterday's count must not carry over (and must not swallow today's sends)
  fs.writeFileSync(usersFile, JSON.stringify([{ id: 'TM-1', name: 'TM One', company_id: 'COMP-001', role: 'member', status: 'active', has_nunno_plan: true,
    messages_sent_today: 57, quota_reset_date: '2000-01-01' }]));
  const leadsFile = path.join(tmpDir, 'leads.json');
  fs.writeFileSync(leadsFile, '[]');
  let sends = 0;
  const mgr = createCampaignManager({
    send: async () => ({ success: true, message_id: 'wa_' + (++sends) }),
    dataDir: tmpDir, getLeadsPath: () => leadsFile, getUsersPath: () => usersFile, mainStatus: () => ({ ready: true })
  });
  const audience = Array.from({ length: 6 }, (_, i) => ({ phone: '92300111111' + i }));
  mgr.start({ message: 'Hi', audience, auto_data: true, minDelay: 6, maxDelay: 9,
    senders: [{ id: 'TM-1', name: 'TM One', phone: '923000000001', roleType: 'member' }] });
  assert.deepEqual(mgr.leftover().phones, [], 'nothing handed back while running');
  await new Promise(r => setTimeout(r, 400));          // first send only (then a 6 s+ gap)
  mgr.stop();
  await new Promise(r => setImmediate(r));
  const left = mgr.leftover().phones;
  assert.equal(left.length, audience.length - sends, 'every number not sent comes back');
  const tm = JSON.parse(fs.readFileSync(usersFile, 'utf8'))[0];
  const pkToday = new Date(Date.now() + 5 * 3600 * 1000).toISOString().slice(0, 10);
  assert.equal(tm.quota_reset_date, pkToday);
  assert.equal(tm.messages_sent_today, sends);
  fs.rmSync(tmpDir, { recursive: true, force: true });
});

test('a number WhatsApp refuses twice stops for 24 h; its numbers are not pushed onto teammates', async t => {
  const tmpDir = fs.mkdtempSync(path.join(os.tmpdir(), 'camp-test-'));
  fs.writeFileSync(path.join(tmpDir, 'leads.json'), '[]');
  fs.writeFileSync(path.join(tmpDir, 'users.json'), '[]');
  // TM-1 gets numbers 0, 2, 4 and TM-2 gets 1, 3, 5. TM-1's second send is refused outright.
  const sentTo = [];
  const mgr = createCampaignManager({
    send: async p => {
      if (p.phone === '923002222222') throw new Error('WhatsApp server error 463');
      sentTo.push(p.phone);
      return { success: true, message_id: 'R' + p.phone.slice(-1) };
    },
    dataDir: tmpDir, getLeadsPath: () => path.join(tmpDir, 'leads.json'), getUsersPath: () => path.join(tmpDir, 'users.json'),
    mainStatus: () => ({ ready: true })
  });
  t.after(() => { mgr.stop(); fs.rmSync(tmpDir, { recursive: true, force: true }); });
  mgr.start({ message: 'Hi', audience: Array.from({ length: 6 }, (_, i) => ({ phone: '92300222222' + i })), minDelay: 6, maxDelay: 6,
    senders: [{ id: 'TM-1', name: 'One', phone: '923000000001', roleType: 'member' }, { id: 'TM-2', name: 'Two', phone: '923000000002', roleType: 'member' }] });
  await new Promise(r => setTimeout(r, 900));          // first send of each lane
  mgr.noteAck('R0', -1);                               // WhatsApp refuses TM-1's first message
  mgr.noteAck('R0', -1);                               // the same receipt twice counts once
  let lane = mgr.getStatus().lanes.find(l => l.id === 'TM-1');
  assert.ok(!lane.pausedUntil, 'one refusal is not enough');
  assert.equal(mgr.getStatus().totalFailed, 1, 'the refused message counts as failed');
  await new Promise(r => setTimeout(r, 7000));         // TM-1's next send (6 s later) is refused too
  lane = mgr.getStatus().lanes.find(l => l.id === 'TM-1');
  assert.equal(lane.pauseReason, 'restricted');
  assert.ok(lane.pausedUntil - Date.now() > 23 * 3600e3, 'paused for 24 hours');
  const tm2 = mgr.getStatus().lanes.find(l => l.id === 'TM-2');
  assert.equal(tm2.assigned, 3, "TM-1's numbers were not moved onto TM-2");
  assert.ok(!sentTo.includes('923002222224'), 'nothing more sent from the paused number');
});

test('a closer at their daily limit is paused until 12 AM instead of sending', async () => {
  const tmpDir = fs.mkdtempSync(path.join(os.tmpdir(), 'camp-test-'));
  fs.writeFileSync(path.join(tmpDir, 'leads.json'), '[]');
  const pkToday = new Date(Date.now() + 5 * 3600 * 1000).toISOString().slice(0, 10);
  fs.writeFileSync(path.join(tmpDir, 'users.json'), JSON.stringify([{ id: 'TM-1', message_limit: 20, messages_sent_today: 20, quota_reset_date: pkToday }]));
  let sends = 0;
  const mgr = createCampaignManager({
    send: async () => ({ success: true, message_id: 'D' + (++sends) }),
    dataDir: tmpDir, getLeadsPath: () => path.join(tmpDir, 'leads.json'), getUsersPath: () => path.join(tmpDir, 'users.json'),
    mainStatus: () => ({ ready: true })
  });
  mgr.start({ message: 'Hi', audience: [{ phone: '923003333331' }], minDelay: 6, maxDelay: 9, senders: [{ id: 'TM-1', name: 'One', phone: '923000000001', roleType: 'member' }] });
  await new Promise(r => setTimeout(r, 500));
  assert.equal(sends, 0, 'nothing sent over the limit');
  const lane = mgr.getStatus().lanes[0];
  assert.equal(lane.pauseReason, 'daily_limit');
  assert.ok(lane.pausedUntil > Date.now());
  mgr.stop();
  fs.rmSync(tmpDir, { recursive: true, force: true });
});

test('the chosen gap is exact and counts from the start of each send (a slow send does not add to it)', async t => {
  const tmpDir = fs.mkdtempSync(path.join(os.tmpdir(), 'camp-test-'));
  fs.writeFileSync(path.join(tmpDir, 'leads.json'), '[]');
  fs.writeFileSync(path.join(tmpDir, 'users.json'), '[]');
  const at = [];
  const mgr = createCampaignManager({
    send: async () => { at.push(Date.now()); await new Promise(r => setTimeout(r, 700)); return { success: true, message_id: 'G' + at.length }; },
    dataDir: tmpDir, getLeadsPath: () => path.join(tmpDir, 'leads.json'), getUsersPath: () => path.join(tmpDir, 'users.json'),
    mainStatus: () => ({ ready: true })
  });
  t.after(() => { mgr.stop(); fs.rmSync(tmpDir, { recursive: true, force: true }); });
  mgr.start({ message: 'Hi', audience: [{ phone: '923004444441' }, { phone: '923004444442' }, { phone: '923004444443' }], minDelay: 2, maxDelay: 2,
    senders: [{ id: 'TM-1', name: 'One', phone: '923000000001', roleType: 'member' }] });
  assert.equal(mgr.getStatus().minDelay, 2, 'no 6 s floor any more');
  await new Promise(r => setTimeout(r, 4800));
  assert.equal(at.length, 3);
  const gaps = [at[1] - at[0], at[2] - at[1]].map(g => Math.round(g / 100) / 10);
  for (const g of gaps) assert.ok(g >= 1.9 && g <= 2.4, `gap ${g}s should be 2s`);
});

test('a closer stopped by the admin gets no campaign numbers; stopping mid-campaign moves theirs to the others', async t => {
  const tmpDir = fs.mkdtempSync(path.join(os.tmpdir(), 'camp-test-'));
  fs.writeFileSync(path.join(tmpDir, 'leads.json'), '[]');
  const usersFile = path.join(tmpDir, 'users.json');
  const users = [{ id: 'TM-1', name: 'One' }, { id: 'TM-2', name: 'Two' }, { id: 'TM-3', name: 'Three', campaign_paused: true }];
  fs.writeFileSync(usersFile, JSON.stringify(users));
  const sentBy = [];
  const mgr = createCampaignManager({
    send: async p => { sentBy.push(p.phone); return { success: true, message_id: 'S' + sentBy.length }; },
    dataDir: tmpDir, getLeadsPath: () => path.join(tmpDir, 'leads.json'), getUsersPath: () => usersFile,
    mainStatus: () => ({ ready: true }), offlineGraceMs: 0
  });
  t.after(() => { mgr.stop(); fs.rmSync(tmpDir, { recursive: true, force: true }); });
  // 60 numbers, 30 each, one per second: still running when the admin stops TM-2
  const st = mgr.start({ message: 'Hi', audience: Array.from({ length: 60 }, (_, i) => ({ phone: '9230055555' + String(i).padStart(2, '0') })), minDelay: 1, maxDelay: 1,
    senders: users.map(u => ({ id: u.id, name: u.name, phone: '92300000000' + u.id.slice(-1), roleType: 'member' })) });
  assert.deepEqual(st.lanes.map(l => l.id), ['TM-1', 'TM-2'], 'TM-3 (stopped) is left out');
  users[1].campaign_paused = true;
  fs.writeFileSync(usersFile, JSON.stringify(users));
  await new Promise(r => setTimeout(r, 11500));   // users.json is re-read every 10 s
  const lanes = mgr.getStatus().lanes;
  const tm1 = lanes.find(l => l.id === 'TM-1'), tm2 = lanes.find(l => l.id === 'TM-2');
  assert.equal(tm2.status, 'done', "TM-2's lane ended");
  assert.equal(tm2.remaining, 0, 'nothing left waiting for TM-2');
  assert.equal(tm1.assigned + tm2.assigned, 60);
  assert.ok(tm1.assigned > 30, "TM-2's unsent numbers moved to TM-1");
});

test('Ultimate campaign: closers from several companies, each chat lands in the sender\'s own company; busy WhatsApps are skipped', async t => {
  const tmpDir = fs.mkdtempSync(path.join(os.tmpdir(), 'camp-test-'));
  const usersFile = path.join(tmpDir, 'users.json');
  fs.writeFileSync(usersFile, JSON.stringify([
    { id: 'NU-1', name: 'Nunno TM', company_id: 'COMP-001', role: 'member', status: 'active', has_nunno_plan: true },
    { id: 'NU-2', name: 'Nunno TM without plan', company_id: 'COMP-001', role: 'member', status: 'active' },
    { id: 'ML-1', name: 'Metalink TM', company_id: 'COMP-004', role: 'member', status: 'active' },
    { id: 'ML-2', name: 'Busy Metalink TM', company_id: 'COMP-004', role: 'member', status: 'active' }
  ]));
  const leadsOf = id => path.join(tmpDir, 'tenants', id, 'leads.json');
  const sentFrom = [];
  const mgr = createCampaignManager({
    send: async () => { throw new Error('the main number must not be used'); },
    deviceSend: async (id, p) => { sentFrom.push([id, p.phone]); return { success: true, message_id: 'wa_' + sentFrom.length }; },
    deviceReady: () => true,
    dataDir: tmpDir, getLeadsPath: id => leadsOf(id), getUsersPath: () => usersFile, mainStatus: () => ({ ready: false }),
    stateFile: path.join(tmpDir, 'campaign_state_CHATLY-HQ.json'),
    isBusyElsewhere: id => id === 'ML-2'   // already sending Metalink's own campaign
  });
  t.after(() => { mgr.stop(); fs.rmSync(tmpDir, { recursive: true, force: true }); });
  const st = mgr.start({
    company_id: 'CHATLY-HQ', company_name: 'Chatly', message: 'Hi', minDelay: 1, maxDelay: 1,
    audience: ['923001110001', '923001110002', '923001110003', '923001110004'].map(phone => ({ phone })),
    senders: [
      { id: 'NU-1', name: 'Nunno TM', roleType: 'member', company_id: 'COMP-001', phone: '923000000001' },
      { id: 'ML-1', name: 'Metalink TM', roleType: 'member', company_id: 'COMP-004', phone: '923000000002' },
      { id: 'ML-2', name: 'Busy Metalink TM', roleType: 'member', company_id: 'COMP-004', phone: '923000000003' }
    ]
  });
  assert.equal(st.success, true);
  assert.deepEqual(st.lanes.map(l => l.id), ['NU-1', 'ML-1'], 'the busy WhatsApp is left out');
  assert.equal(mgr.isLaneActive('ML-1'), true);
  assert.equal(mgr.isLaneActive('ML-2'), false);
  await new Promise(r => setTimeout(r, 1800));   // 2 numbers per lane, 1 s apart
  mgr.stop();
  await new Promise(r => setImmediate(r));
  const nunno = JSON.parse(fs.readFileSync(leadsOf('COMP-001'), 'utf8'));
  const metalink = JSON.parse(fs.readFileSync(leadsOf('COMP-004'), 'utf8'));
  assert.ok(nunno.length >= 1 && nunno.every(l => l.ownerId === 'NU-1' && l.company_id === 'COMP-001'), 'Nunno TM\'s chats are in Nunno');
  assert.ok(metalink.length >= 1 && metalink.every(l => l.ownerId === 'ML-1' && l.company_id === 'COMP-004'), 'Metalink TM\'s chats are in Metalink');
  assert.equal(fs.existsSync(leadsOf('CHATLY-HQ')), false, 'nothing is stored under CHATLY-HQ');
  assert.ok(sentFrom.every(([id]) => id !== 'ML-2'));
});
