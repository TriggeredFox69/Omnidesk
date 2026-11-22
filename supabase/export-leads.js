#!/usr/bin/env node
/*
 * The way back: writes every company's chats from Supabase into data/tenants/<company>/leads.json
 * (the old format, messages inside each lead). Each existing leads.json is backed up first.
 * Use it before removing data/leads_store_supabase.on if you want to return to files.
 *
 *   node supabase/export-leads.js <chatly data dir>            # dry run: counts only
 *   node supabase/export-leads.js <chatly data dir> --apply    # write the files
 */
'use strict';
const fs = require('node:fs');
const path = require('node:path');

const dataDir = process.argv[2];
const apply = process.argv.includes('--apply');
if (!dataDir || !fs.existsSync(path.join(dataDir, 'tenants'))) {
  console.error('Usage: node export-leads.js <chatly data dir containing tenants/> [--apply]');
  process.exit(1);
}
const php = fs.readFileSync(path.join(dataDir, 'supabase.php'), 'utf8');
const url = ((/'url'\s*=>\s*'([^']+)'/.exec(php) || [])[1] || '').replace(/\/+$/, '');
const key = (/'key'\s*=>\s*'([^']+)'/.exec(php) || [])[1] || '';

async function rpc(fn, args) {
  const headers = { apikey: key, 'Content-Type': 'application/json' };
  if (!key.startsWith('sb_')) headers.Authorization = 'Bearer ' + key;
  const res = await fetch(`${url}/rest/v1/rpc/${fn}`, { method: 'POST', headers, body: JSON.stringify(args), signal: AbortSignal.timeout(120000) });
  const text = await res.text();
  if (!res.ok) throw new Error(`${fn}: HTTP ${res.status} ${text.slice(0, 300)}`);
  return JSON.parse(text);
}

(async () => {
  const stamp = new Date().toISOString().replace(/[:.]/g, '-');
  for (const company of fs.readdirSync(path.join(dataDir, 'tenants'))) {
    if (!/^[A-Za-z0-9_-]+$/.test(company) || company === 'GLOBAL' || !fs.statSync(path.join(dataDir, 'tenants', company)).isDirectory()) continue;
    const leads = [];
    for (let offset = 0; ; offset += 1000) {
      const page = await rpc('chatly_leads_dump', { p_company: company, p_offset: offset, p_limit: 1000, p_with_messages: true });
      for (const l of page) { delete l.messageCount; leads.push(l); }
      if (page.length < 1000) break;
    }
    const msgs = leads.reduce((n, l) => n + (l.messages || []).length, 0);
    console.log(`${company}: ${leads.length} leads, ${msgs} messages`);
    if (!apply || !leads.length) continue;
    const file = path.join(dataDir, 'tenants', company, 'leads.json');
    if (fs.existsSync(file)) fs.copyFileSync(file, path.join(dataDir, 'tenants', company, `leads.backup-before-export-${stamp}.json`));
    fs.writeFileSync(file + '.export.tmp', JSON.stringify(leads, null, 2));
    fs.renameSync(file + '.export.tmp', file);
    if (company === 'COMP-001' && fs.existsSync(path.join(dataDir, 'leads.json'))) fs.writeFileSync(path.join(dataDir, 'leads.json'), JSON.stringify(leads, null, 2));
  }
  console.log(apply ? 'Done: chats written back to leads.json files.' : 'Dry run only. Add --apply to write the files.');
})().catch(err => { console.error('Export failed:', err.message); process.exit(1); });
