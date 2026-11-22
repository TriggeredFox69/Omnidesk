#!/usr/bin/env node
/*
 * One-off CRM cleanup. Older campaigns copied their whole cold audience into each
 * company's leads.json (Metalink: ~14,000 leads, 10 MB, of which only a handful were ever
 * messaged or replied). Every dashboard poll and every campaign send then had to read and
 * rewrite that file, which made big campaigns lag.
 *
 * This moves leads that were NEVER contacted (no messages at all, still "fresh", nothing
 * unread, no owner-entered payment) into tenants/<company>/leads_archive_cold.json.
 *
 * It also moves PERSONAL-CHAT leads: ones the bridge, PHP or the browser auto-created from chats
 * a closer had on their own phone (interest "WhatsApp outreach" / "WhatsApp Direct Sync" /
 * "TL Cold Outreach" / "TM Closer Chat" / "Direct WhatsApp", or the auto-sync notes), which
 * Chatly no longer creates. A lead is kept if a campaign ever messaged it (the campaign's CRM
 * sync stamps lastSenderPhone/lastSenderName, or a "msg_c_" message) or if it is paid / in
 * payment review. These go to tenants/<company>/leads_archive_personal.json.
 *
 * Nothing is deleted: the archives keep every moved lead, and a timestamped backup of
 * leads.json is written first. It also removes leftover *.tmp files older than one hour.
 *
 *   node archive-cold-leads.js <path-to-chatly-data-dir>            # dry run: report only
 *   node archive-cold-leads.js <path-to-chatly-data-dir> --apply    # do it
 */
const fs = require('fs');
const path = require('path');

const dataDir = process.argv[2];
const apply = process.argv.includes('--apply');
if (!dataDir || !fs.existsSync(path.join(dataDir, 'tenants'))) {
  console.error('Usage: node archive-cold-leads.js <chatly data dir containing tenants/> [--apply]');
  process.exit(1);
}

const neverContacted = l =>
  (!Array.isArray(l.messages) || l.messages.length === 0) &&
  (l.leadState || 'fresh') === 'fresh' &&
  !(Number(l.unreadCount) > 0) &&
  !l.paidDetails && !l.paymentProof;

// Interests / notes stamped by the code paths that turned personal chats into leads:
// the bridge and PHP inbox sync, and the browser (live-inbox.js + update_lead), which made
// "Unassigned Pool" chats named after the phone owner's WhatsApp name.
const PERSONAL_INTERESTS = new Set(['WhatsApp outreach', 'WhatsApp Direct Sync', 'TL Cold Outreach', 'TM Closer Chat', 'Direct WhatsApp']);
const PERSONAL_NOTES = new Set(['Conversation synced from connected WhatsApp', 'Inbound contact automatically synchronized.']);
const touchedByCampaign = l =>
  Object.prototype.hasOwnProperty.call(l, 'lastSenderPhone') || Object.prototype.hasOwnProperty.call(l, 'lastSenderName') ||
  (Array.isArray(l.messages) && l.messages.some(m => String((m && m.id) || '').startsWith('msg_c_')));
const personalChat = l =>
  (PERSONAL_INTERESTS.has(String(l.interest || '')) || PERSONAL_NOTES.has(String(l.notes || '').trim())) &&
  !touchedByCampaign(l) &&
  !['paid', 'pending_verification'].includes(String(l.leadState || '')) &&
  !l.paidDetails && !l.paymentProof && !l.isTakeover;

function appendArchive(file, rows) {
  let archived = [];
  try { archived = JSON.parse(fs.readFileSync(file, 'utf8')); } catch (_) {}
  if (!Array.isArray(archived)) archived = [];
  fs.writeFileSync(file, JSON.stringify(archived.concat(rows.map(l => ({ ...l, archivedAt: stamp })))));
}

const stamp = new Date().toISOString().replace(/[:.]/g, '-');
let totalMoved = 0;

for (const company of fs.readdirSync(path.join(dataDir, 'tenants'))) {
  const dir = path.join(dataDir, 'tenants', company);
  const file = path.join(dir, 'leads.json');
  if (!fs.statSync(dir).isDirectory() || !fs.existsSync(file)) continue;

  // leftover temp files from interrupted writes
  const stale = fs.readdirSync(dir).filter(f => /^leads\.json\..*tmp/.test(f) || /^leads\.json\.tmp\./.test(f))
    .map(f => path.join(dir, f)).filter(f => Date.now() - fs.statSync(f).mtimeMs > 3600e3);

  let leads;
  try { leads = JSON.parse(fs.readFileSync(file, 'utf8')); } catch (e) { console.log(`${company}: cannot read leads.json (${e.message}), skipped`); continue; }
  if (!Array.isArray(leads)) continue;
  const cold = [], personal = [], keep = [];
  for (const l of leads) {
    if (!l || typeof l !== 'object') { keep.push(l); continue; }
    if (neverContacted(l)) cold.push(l);
    else if (personalChat(l)) personal.push(l);
    else keep.push(l);
  }
  const move = cold.concat(personal);
  const sizeMB = (fs.statSync(file).size / 1048576).toFixed(1);
  console.log(`${company}: ${leads.length} leads (${sizeMB} MB) -> keep ${keep.length}, archive ${cold.length} never-contacted + ${personal.length} personal-chat; ${stale.length} stale temp files`);
  if (personal.length) {
    const sample = personal.slice(0, 5).map(l => `${l.name || l.phone} [owner: ${l.ownerName || '?'}]`).join(', ');
    console.log(`  personal-chat examples: ${sample}${personal.length > 5 ? ', ...' : ''}`);
  }
  if (!apply || (!move.length && !stale.length)) continue;

  if (move.length) {
    fs.copyFileSync(file, path.join(dir, `leads.backup-${stamp}.json`));
    if (cold.length) appendArchive(path.join(dir, 'leads_archive_cold.json'), cold);
    if (personal.length) appendArchive(path.join(dir, 'leads_archive_personal.json'), personal);
    const tmp = file + '.archive.tmp';
    fs.writeFileSync(tmp, JSON.stringify(keep, null, 2));
    fs.renameSync(tmp, file);
    // Nunno keeps a legacy mirror at data/leads.json
    if (company === 'COMP-001' && fs.existsSync(path.join(dataDir, 'leads.json'))) fs.writeFileSync(path.join(dataDir, 'leads.json'), JSON.stringify(keep, null, 2));
    totalMoved += move.length;
  }
  for (const f of stale) { try { fs.unlinkSync(f); } catch (_) {} }
}
console.log(apply ? `Done. ${totalMoved} leads archived (never-contacted + personal-chat).` : 'Dry run only. Add --apply to archive.');
