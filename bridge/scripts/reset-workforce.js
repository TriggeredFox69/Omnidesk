#!/usr/bin/env node
/*
 * Fresh start after the testing period: removes every Team Member / Team Leader account (except
 * the ones kept), logs their linked WhatsApps out, and deletes ALL chats and messages (leads.json
 * files and, when connected, the Supabase chat tables). Company admins and the super admin stay.
 * The Auto Data number pool and each company's position in it stay (no number is given twice).
 *
 *   node reset-workforce.js <chatly data dir>                       # dry run: shows what would go
 *   node reset-workforce.js <chatly data dir> --apply               # do it (backup first)
 *   node reset-workforce.js <chatly data dir> --keep=gullzaman,ali  # usernames to keep (default: gullzaman)
 *
 * Run it while the WhatsApp bridge is running (it logs the removed WhatsApps out through it),
 * then restart the bridge (bash deploy.sh) so it starts clean.
 * Everything removed is copied to <data dir>/backups/reset-<time>/ first.
 */
'use strict';
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');

const dataDir = process.argv[2];
const apply = process.argv.includes('--apply');
const keepArg = (process.argv.find(a => a.startsWith('--keep=')) || '--keep=gullzaman').slice(7);
const keep = new Set(keepArg.split(',').map(s => s.trim().toLowerCase()).filter(Boolean));
if (!dataDir || !fs.existsSync(path.join(dataDir, 'users.json'))) {
  console.error('Usage: node reset-workforce.js <chatly data dir containing users.json> [--apply] [--keep=user1,user2]');
  process.exit(1);
}
const readJson = (f, d) => { try { return JSON.parse(fs.readFileSync(f, 'utf8')); } catch (_) { return d; } };
const writeJson = (f, v) => { fs.writeFileSync(f + '.reset.tmp', JSON.stringify(v, null, 2)); fs.renameSync(f + '.reset.tmp', f); };
const bridgeDir = path.join(dataDir, 'whatsapp-bridge');

// ---- what goes ----
const users = readJson(path.join(dataDir, 'users.json'), []);
const isCloser = u => ['member', 'lead'].includes(String(u.role || '').toLowerCase());
const kept = users.filter(u => isCloser(u) && keep.has(String(u.username || '').toLowerCase()));
const removed = users.filter(u => isCloser(u) && !keep.has(String(u.username || '').toLowerCase()));
const leadFiles = [];
for (const c of fs.existsSync(path.join(dataDir, 'tenants')) ? fs.readdirSync(path.join(dataDir, 'tenants')) : []) {
  const f = path.join(dataDir, 'tenants', c, 'leads.json');
  if (fs.existsSync(f)) leadFiles.push([c, f]);
}
if (fs.existsSync(path.join(dataDir, 'leads.json'))) leadFiles.push(['COMP-001 (legacy)', path.join(dataDir, 'leads.json')]);
const campaignStates = fs.existsSync(bridgeDir) ? fs.readdirSync(bridgeDir).filter(f => /^campaign_state.*\.json$/.test(f)) : [];

// Supabase (if this server is connected)
const php = fs.existsSync(path.join(dataDir, 'supabase.php')) ? fs.readFileSync(path.join(dataDir, 'supabase.php'), 'utf8') : '';
const sbUrl = ((/'url'\s*=>\s*'([^']+)'/.exec(php) || [])[1] || '').replace(/\/+$/, '');
const sbKey = (/'key'\s*=>\s*'([^']+)'/.exec(php) || [])[1] || '';
async function sb(method, table, query, extra = {}) {
  const headers = { apikey: sbKey, 'Content-Type': 'application/json', ...extra };
  if (!sbKey.startsWith('sb_')) headers.Authorization = 'Bearer ' + sbKey;
  const res = await fetch(`${sbUrl}/rest/v1/${table}?${query}`, { method, headers, signal: AbortSignal.timeout(60000) });
  if (!res.ok) throw new Error(`${table}: HTTP ${res.status} ${(await res.text()).slice(0, 200)}`);
  return res;
}
async function sbCount(table) {
  const res = await sb('HEAD', table, 'select=company_id', { Prefer: 'count=exact', Range: '0-0' });
  return Number(((res.headers.get('content-range') || '').split('/')[1]) || 0);
}

// The bridge (to log WhatsApps out properly, and to check the kept closers' WhatsApp)
function bridgeToken() {
  try { const t = fs.readFileSync(path.join(bridgeDir, 'token'), 'utf8').trim(); if (t) return t; } catch (_) {}
  try { const m = /'token'\s*=>\s*'([^']+)'/.exec(fs.readFileSync(path.join(bridgeDir, 'connection.php'), 'utf8')); if (m) return m[1]; } catch (_) {}
  return process.env.WHATSAPP_BRIDGE_TOKEN || 'c8e4acd8219dafce2a74e37954fa4a12c3fd1256bff8fcb518b7f137b4f9af20';
}
async function bridge(method, p, body) {
  const res = await fetch('http://127.0.0.1:3210' + p, { method, headers: { Authorization: 'Bearer ' + bridgeToken(), 'Content-Type': 'application/json' },
    body: body ? JSON.stringify(body) : undefined, signal: AbortSignal.timeout(20000) });
  return res.json();
}

(async () => {
  let totalLeads = 0;
  console.log(`Closers to REMOVE (${removed.length}):`);
  for (const u of removed) console.log(`  - ${u.name || u.username} (@${u.username}, ${u.role}, ${u.company_id || 'COMP-001'})`);
  console.log(`Closers KEPT (${kept.length}): ${kept.map(u => '@' + u.username).join(', ') || 'none'}`);
  for (const [c, f] of leadFiles) { const n = readJson(f, []).length; totalLeads += n; console.log(`  chats in ${c}: ${n}`); }
  let sbLeads = 0, sbMsgs = 0;
  if (sbUrl && sbKey) {
    try { sbLeads = await sbCount('chatly_leads'); sbMsgs = await sbCount('chatly_messages'); console.log(`  Supabase: ${sbLeads} chats, ${sbMsgs} messages`); }
    catch (e) { console.log('  Supabase: could not count (' + e.message + ')'); }
  }
  for (const u of kept) {
    try { const s = await bridge('GET', '/device/status?id=' + encodeURIComponent(u.id)); console.log(`  @${u.username}'s WhatsApp: ${s.is_connected ? 'CONNECTED ' + (s.connected_number || '') : (s.state || 'not connected')}`); }
    catch (e) { console.log(`  @${u.username}'s WhatsApp: bridge not reachable (${e.message})`); }
  }
  if (!apply) { console.log('\nDry run: nothing changed. Add --apply to do it.'); return; }

  // ---- backup ----
  const stamp = new Date().toISOString().replace(/[:.]/g, '-');
  const backup = path.join(dataDir, 'backups', 'reset-' + stamp);
  fs.mkdirSync(backup, { recursive: true });
  const save = (src, name) => { if (fs.existsSync(src)) fs.copyFileSync(src, path.join(backup, name)); };
  save(path.join(dataDir, 'users.json'), 'users.json');
  save(path.join(dataDir, 'withdrawals.json'), 'withdrawals.json');
  save(path.join(dataDir, 'nunno_sales.json'), 'nunno_sales.json');
  save(path.join(bridgeDir, 'inbox.json'), 'inbox.json');
  for (const [c, f] of leadFiles) save(f, 'leads-' + c.replace(/[^A-Za-z0-9_-]/g, '_') + '.json');
  for (const f of campaignStates) save(path.join(bridgeDir, f), f);
  if (sbUrl && sbKey && (sbLeads || sbMsgs)) {
    for (const table of ['chatly_leads', 'chatly_messages']) {
      try { const res = await sb('GET', table, 'select=*'); fs.writeFileSync(path.join(backup, table + '.json'), await res.text()); } catch (e) { console.log('  backup of ' + table + ' failed: ' + e.message); }
    }
  }
  console.log('Backup: ' + backup);

  // ---- log the removed WhatsApps out ----
  let loggedOut = 0;
  for (const u of removed) {
    try { const r = await bridge('POST', '/device/reset', { id: u.id }); if (r && r.success !== false) loggedOut++; } catch (_) {}
  }
  console.log(`WhatsApps logged out: ${loggedOut} of ${removed.length}`);
  // Their saved sessions too (so nothing reconnects after the restart, even if the bridge was down).
  const devicesDir = path.join(bridgeDir, 'devices');
  let wiped = 0;
  const removedOwners = new Set(removed.map(u => String(u.id)));
  for (const e of fs.existsSync(devicesDir) ? fs.readdirSync(devicesDir, { withFileTypes: true }) : []) {
    if (!e.isDirectory()) continue;
    const meta = readJson(path.join(devicesDir, e.name, 'meta.json'), {});
    if (removedOwners.has(String(meta.owner || e.name))) {
      try { fs.rmSync(path.join(devicesDir, e.name), { recursive: true, force: true }); wiped++; } catch (_) {}
    }
  }
  console.log(`Saved WhatsApp sessions removed: ${wiped}`);

  // ---- accounts ----
  const removedIds = new Set(removed.map(u => u.id));
  writeJson(path.join(dataDir, 'users.json'), users.filter(u => !removedIds.has(u.id)));
  const wds = readJson(path.join(dataDir, 'withdrawals.json'), null);
  if (Array.isArray(wds)) writeJson(path.join(dataDir, 'withdrawals.json'), wds.filter(w => !removedIds.has(w.userId)));
  const sales = readJson(path.join(dataDir, 'nunno_sales.json'), null);
  if (sales && Array.isArray(sales.manual_sales)) { sales.manual_sales = sales.manual_sales.filter(s => !removedIds.has(s.tm_id)); writeJson(path.join(dataDir, 'nunno_sales.json'), sales); }
  console.log(`Accounts removed: ${removed.length}`);

  // ---- chats and messages ----
  for (const [, f] of leadFiles) writeJson(f, []);
  if (fs.existsSync(path.join(bridgeDir, 'inbox.json'))) writeJson(path.join(bridgeDir, 'inbox.json'), []);
  for (const f of ['store_queue.json', ...campaignStates]) { try { fs.unlinkSync(path.join(bridgeDir, f)); } catch (_) {} }
  if (sbUrl && sbKey) {
    try {
      await sb('DELETE', 'chatly_messages', 'company_id=neq.__none__', { Prefer: 'return=minimal' });
      await sb('DELETE', 'chatly_leads', 'company_id=neq.__none__', { Prefer: 'return=minimal' });
      console.log('Supabase chats and messages deleted.');
    } catch (e) { console.log('Supabase delete failed: ' + e.message); }
  }
  for (const f of fs.readdirSync(os.tmpdir())) if (f.startsWith('chatly_leadkeys_')) { try { fs.unlinkSync(path.join(os.tmpdir(), f)); } catch (_) {} }
  console.log(`Chats deleted: ${totalLeads} from files${sbUrl ? `, ${sbLeads} from Supabase` : ''}.`);
  console.log('\nDone. Now restart the bridge so it starts clean:  cd ~/repositories/Omnidesk && bash deploy.sh');
})().catch(err => { console.error('Reset failed:', err.message); process.exit(1); });
