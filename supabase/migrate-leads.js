#!/usr/bin/env node
/*
 * One-time copy of every company's chats (data/tenants/<company>/leads.json) into Supabase.
 *
 *   node supabase/migrate-leads.js <chatly data dir>            # dry run: counts only
 *   node supabase/migrate-leads.js <chatly data dir> --apply    # copy
 *
 * Safe to run again: leads and messages that are already in Supabase are kept as they are,
 * only missing ones are added. The leads.json files are not changed. Needs Node 18+, no packages.
 * Reads the Supabase URL and key from <data dir>/supabase.php.
 */
'use strict';
const fs = require('node:fs');
const path = require('node:path');
const crypto = require('node:crypto');

const dataDir = process.argv[2];
const apply = process.argv.includes('--apply');
if (!dataDir || !fs.existsSync(path.join(dataDir, 'tenants'))) {
  console.error('Usage: node migrate-leads.js <chatly data dir containing tenants/> [--apply]');
  process.exit(1);
}

const php = fs.existsSync(path.join(dataDir, 'supabase.php')) ? fs.readFileSync(path.join(dataDir, 'supabase.php'), 'utf8') : '';
const url = ((/'url'\s*=>\s*'([^']+)'/.exec(php) || [])[1] || '').replace(/\/+$/, '');
const key = (/'key'\s*=>\s*'([^']+)'/.exec(php) || [])[1] || '';
if (apply && (!/^https:\/\/[a-z0-9-]+\.supabase\.co$/i.test(url) || !key)) { console.error('data/supabase.php is missing or incomplete.'); process.exit(1); }

async function rpc(fn, args) {
  const headers = { apikey: key, 'Content-Type': 'application/json' };
  if (!key.startsWith('sb_')) headers.Authorization = 'Bearer ' + key;
  for (let attempt = 1; ; attempt++) {
    try {
      const res = await fetch(`${url}/rest/v1/rpc/${fn}`, { method: 'POST', headers, body: JSON.stringify(args), signal: AbortSignal.timeout(120000) });
      const text = await res.text();
      if (!res.ok) throw new Error(`${fn}: HTTP ${res.status} ${text.slice(0, 300)}`);
      return text ? JSON.parse(text) : null;
    } catch (err) {
      if (attempt >= 4) throw err;
      await new Promise(r => setTimeout(r, 2000 * attempt));
    }
  }
}

const digits = p => {
  let c = String(p || '').replace(/\D/g, '');
  if (c.startsWith('0092')) c = c.slice(2);
  else if (c.startsWith('03') && c.length === 11) c = '92' + c.slice(1);
  else if (c.startsWith('3') && c.length === 10) c = '92' + c;
  return c;
};
const toSec = v => { let n = Math.floor(Number(v) || 0); if (n > 20000000000) n = Math.floor(n / 1000); return n; };

function companyFiles() {
  const out = [];
  for (const name of fs.readdirSync(path.join(dataDir, 'tenants'))) {
    const file = path.join(dataDir, 'tenants', name, 'leads.json');
    if (/^[A-Za-z0-9_-]+$/.test(name) && name !== 'GLOBAL' && fs.existsSync(file)) out.push([name, file]);
  }
  // Nunno's legacy file, only when it has no tenant file
  if (!out.some(([c]) => c === 'COMP-001') && fs.existsSync(path.join(dataDir, 'leads.json'))) out.push(['COMP-001', path.join(dataDir, 'leads.json')]);
  return out;
}

(async () => {
  let totalLeads = 0, totalMsgs = 0;
  for (const [company, file] of companyFiles()) {
    let leads;
    try { leads = JSON.parse(fs.readFileSync(file, 'utf8')); } catch (e) { console.log(`${company}: cannot read ${file} (${e.message}), skipped`); continue; }
    if (!Array.isArray(leads)) continue;
    const rows = [], items = [];
    for (const raw of leads) {
      if (!raw || typeof raw !== 'object') continue;
      const lead = { ...raw };
      const phone = digits(lead.phone);
      if (!phone && !lead.id) continue;
      if (phone) lead.phone = phone;
      if (!lead.id) lead.id = 'lead_' + phone;
      const msgs = Array.isArray(lead.messages) ? lead.messages.filter(m => m && typeof m === 'object') : [];
      delete lead.messages;
      // A message without a time keeps its place: it gets a time just before the next one.
      const times = new Array(msgs.length);
      let next = (toSec(lead.lastMessageAt) || Math.floor(Date.now() / 1000)) + 1;
      for (let i = msgs.length - 1; i >= 0; i--) {
        const t = toSec(msgs[i].timestamp);
        times[i] = t && t < next ? t : next - 1;
        next = times[i];
      }
      msgs.forEach((m, i) => {
        const msg = { ...m };
        if (!toSec(msg.timestamp)) msg.timestamp = times[i];
        if (!msg.waMessageId && !msg.id) {
          msg.id = 'h_' + crypto.createHash('sha1').update(`${phone}|${msg.timestamp}|${msg.time || ''}|${msg.sender || ''}|${msg.text || ''}`).digest('hex').slice(0, 20);
        }
        items.push({ phone, msg, touch: false });
      });
      rows.push(lead);
    }
    const mb = (fs.statSync(file).size / 1048576).toFixed(1);
    console.log(`${company}: ${rows.length} leads, ${items.length} messages (${mb} MB file)`);
    totalLeads += rows.length; totalMsgs += items.length;
    if (!apply) continue;

    for (let i = 0; i < rows.length; i += 500) {
      await rpc('chatly_upsert_leads', { p_company: company, p_leads: rows.slice(i, i + 500), p_mode: 'insert' });
      process.stdout.write(`\r  leads ${Math.min(i + 500, rows.length)}/${rows.length}   `);
    }
    let added = 0;
    for (let i = 0; i < items.length; i += 1000) {
      added += Number(await rpc('chatly_add_messages', { p_company: company, p_items: items.slice(i, i + 1000) })) || 0;
      process.stdout.write(`\r  messages ${Math.min(i + 1000, items.length)}/${items.length}   `);
    }
    await rpc('chatly_recount', { p_company: company });
    console.log(`\r  copied: ${rows.length} leads checked, ${added} new messages.                 `);
  }
  console.log(apply ? `Done. ${totalLeads} leads and ${totalMsgs} messages are in Supabase.` : `Dry run: ${totalLeads} leads, ${totalMsgs} messages would be copied. Add --apply to copy.`);
})().catch(err => { console.error('\nCopy failed:', err.message); process.exit(1); });
