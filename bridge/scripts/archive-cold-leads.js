#!/usr/bin/env node
/*
 * One-off CRM cleanup. Older campaigns copied their whole cold audience into each
 * company's leads.json (Metalink: ~14,000 leads, 10 MB, of which only a handful were ever
 * messaged or replied). Every dashboard poll and every campaign send then had to read and
 * rewrite that file, which made big campaigns lag.
 *
 * This moves leads that were NEVER contacted (no messages at all, still "fresh", nothing
 * unread, no owner-entered payment) into tenants/<company>/leads_archive_cold.json.
 * Nothing is deleted: the archive keeps every moved lead, and a timestamped backup of
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
  const keep = leads.filter(l => !neverContacted(l));
  const move = leads.filter(neverContacted);
  const sizeMB = (fs.statSync(file).size / 1048576).toFixed(1);
  console.log(`${company}: ${leads.length} leads (${sizeMB} MB) -> keep ${keep.length}, archive ${move.length} never-contacted; ${stale.length} stale temp files`);
  if (!apply || (!move.length && !stale.length)) continue;

  if (move.length) {
    fs.copyFileSync(file, path.join(dir, `leads.backup-${stamp}.json`));
    const archiveFile = path.join(dir, 'leads_archive_cold.json');
    let archived = [];
    try { archived = JSON.parse(fs.readFileSync(archiveFile, 'utf8')); } catch (_) {}
    if (!Array.isArray(archived)) archived = [];
    fs.writeFileSync(archiveFile, JSON.stringify(archived.concat(move.map(l => ({ ...l, archivedAt: stamp })))));
    const tmp = file + '.archive.tmp';
    fs.writeFileSync(tmp, JSON.stringify(keep, null, 2));
    fs.renameSync(tmp, file);
    // Nunno keeps a legacy mirror at data/leads.json
    if (company === 'COMP-001' && fs.existsSync(path.join(dataDir, 'leads.json'))) fs.writeFileSync(path.join(dataDir, 'leads.json'), JSON.stringify(keep, null, 2));
    totalMoved += move.length;
  }
  for (const f of stale) { try { fs.unlinkSync(f); } catch (_) {} }
}
console.log(apply ? `Done. ${totalMoved} never-contacted leads archived.` : 'Dry run only. Add --apply to archive.');
