'use strict';
// Chats in Supabase, for the bridge (see supabase/schema_leads.sql).
//
// On while data/leads_store_supabase.on exists (the same switch PHP uses) and data/supabase.php
// holds the project URL and secret key. Checked every 10 s, so switching needs no restart.
//
//  * New messages and ticks are queued and written in one call per company about every
//    second, instead of rewriting a company's whole leads.json for each one. If Supabase is
//    unreachable they stay queued (and survive a restart in a small file) and are retried.
//  * "Is this number a lead?" is answered from a local copy of the company's lead numbers,
//    brought up to date with only the changes.
const fs = require('node:fs');
const path = require('node:path');

const key9 = p => { const d = String(p || '').replace(/\D/g, ''); return d.length >= 9 ? d.slice(-9) : d; };

function createStore({ dataDir = path.resolve(__dirname, '../data'), fetchImpl, flushMs = 800, log = console } = {}) {
  const doFetch = fetchImpl || ((...a) => fetch(...a));
  const switchFile = path.join(dataDir, 'leads_store_supabase.on');
  const queueFile = path.join(dataDir, 'whatsapp-bridge', 'store_queue.json');
  let cfgCache = { at: 0, cfg: null };

  function config() {
    if (Date.now() - cfgCache.at < 10000) return cfgCache.cfg;
    let cfg = null;
    try {
      if (fs.existsSync(switchFile)) {
        const php = fs.readFileSync(path.join(dataDir, 'supabase.php'), 'utf8');
        const url = ((/'url'\s*=>\s*'([^']+)'/.exec(php) || [])[1] || '').replace(/\/+$/, '');
        const key = (/'key'\s*=>\s*'([^']+)'/.exec(php) || [])[1] || '';
        if (/^https:\/\/[a-z0-9-]+\.supabase\.co$/i.test(url) && key) cfg = { url, key };
      }
    } catch (_) {}
    cfgCache = { at: Date.now(), cfg };
    return cfg;
  }
  const enabled = () => !!config();

  async function rpc(fn, args, timeoutMs = 20000) {
    const cfg = config();
    if (!cfg) throw new Error('Supabase chat store is off');
    const headers = { apikey: cfg.key, 'Content-Type': 'application/json' };
    if (!cfg.key.startsWith('sb_')) headers.Authorization = 'Bearer ' + cfg.key;
    const res = await doFetch(`${cfg.url}/rest/v1/rpc/${fn}`, { method: 'POST', headers, body: JSON.stringify(args), signal: AbortSignal.timeout(timeoutMs) });
    const text = await res.text();
    if (!res.ok) throw new Error(`${fn}: HTTP ${res.status} ${text.slice(0, 200)}`);
    return text ? JSON.parse(text) : null;
  }

  // ---------------------------------------------------------- write queue
  let messageQueue = new Map();   // company -> [{phone, msg, touch}]
  let statusQueue = [];           // [{company|null, key, status, error}]
  let timer = null, flushing = false, failures = 0;
  try {
    if (fs.existsSync(queueFile)) {
      const saved = JSON.parse(fs.readFileSync(queueFile, 'utf8'));
      for (const [c, items] of Object.entries(saved.messages || {})) messageQueue.set(c, items);
      statusQueue = Array.isArray(saved.statuses) ? saved.statuses : [];
      fs.unlinkSync(queueFile);
    }
  } catch (_) {}

  function schedule(ms = flushMs) { if (!timer) timer = setTimeout(() => { timer = null; flush().catch(() => {}); }, ms); }
  function queuedCount() { let n = statusQueue.length; for (const v of messageQueue.values()) n += v.length; return n; }

  function queueMessage(companyId, phone, msg, touch = true) {
    if (!msg || !phone) return;
    if (!messageQueue.has(companyId)) messageQueue.set(companyId, []);
    messageQueue.get(companyId).push({ phone: String(phone), msg, touch });
    schedule();
  }
  function queueStatus(companyId, key, status, error = null) {
    if (!key || !status) return;
    statusQueue.push({ company: companyId || null, key, status, error });
    schedule();
  }

  function spill() {
    try {
      fs.mkdirSync(path.dirname(queueFile), { recursive: true });
      fs.writeFileSync(queueFile, JSON.stringify({ messages: Object.fromEntries(messageQueue), statuses: statusQueue }));
    } catch (_) {}
  }

  async function flush() {
    if (flushing) { schedule(); return; }
    if (!enabled()) return;
    flushing = true;
    const messages = messageQueue; messageQueue = new Map();
    const statuses = statusQueue; statusQueue = [];
    const pending = new Map(messages);   // company -> items not written yet
    let statusesLeft = statuses;
    try {
      for (const [company, items] of messages) {
        for (let i = 0; i < items.length; i += 500) {
          await rpc('chatly_add_messages', { p_company: company, p_items: items.slice(i, i + 500) }, 30000);
          pending.set(company, items.slice(i + 500));
        }
        pending.delete(company);
      }
      // ticks after messages, so a tick never arrives before its message
      for (let i = 0; i < statuses.length; i += 500) {
        await rpc('chatly_set_statuses', { p_items: statuses.slice(i, i + 500) }, 30000);
        statusesLeft = statuses.slice(i + 500);
      }
      statusesLeft = [];
      failures = 0;
      try { if (fs.existsSync(queueFile)) fs.unlinkSync(queueFile); } catch (_) {}
    } catch (err) {
      // everything not written goes back in front of what arrived meanwhile
      for (const [company, items] of pending) {
        if (items.length) messageQueue.set(company, items.concat(messageQueue.get(company) || []));
      }
      statusQueue = statusesLeft.concat(statusQueue);
      failures++;
      if (failures === 1 || failures % 20 === 0) log.warn(`[Store] Supabase write failed (${queuedCount()} waiting, will retry): ${err.message}`);
      // never grow without limit: keep the newest 50,000
      if (queuedCount() > 50000) { statusQueue = statusQueue.slice(-10000); }
      spill();
      schedule(Math.min(30000, 1000 * 2 ** Math.min(failures, 5)));
    } finally {
      flushing = false;
    }
    if (queuedCount() && !timer) schedule();
  }

  // ------------------------------------------------------ lead number index
  const keyIndex = new Map();   // company -> { rev, keys: Set, at }
  async function refreshKeys(companyId) {
    const cur = keyIndex.get(companyId) || { rev: 0, keys: new Set(), at: 0 };
    const d = await rpc('chatly_lead_keys', { p_company: companyId, p_since: cur.rev }, 30000);
    const keys = cur.rev ? cur.keys : new Set();
    for (const [k, , , deleted] of (d && d.rows) || []) { if (!k) continue; if (deleted) keys.delete(k); else keys.add(k); }
    const next = { rev: Number(d && d.rev) || cur.rev, keys, at: Date.now() };
    keyIndex.set(companyId, next);
    return next;
  }
  // true / false; stale-but-usable when Supabase is slow
  async function isLead(companyId, phone) {
    const k = key9(phone);
    if (!k) return false;
    let idx = keyIndex.get(companyId);
    if (idx && idx.keys.has(k)) return true;
    // unknown number: catch up with changes (a lead added a moment ago), at most every 1.5 s
    if (!idx || Date.now() - idx.at > 1500) {
      try { idx = await refreshKeys(companyId); } catch (err) { if (!idx) log.warn('[Store] lead numbers unavailable:', err.message); }
    }
    return !!(idx && idx.keys.has(k));
  }
  function rememberLead(companyId, phone) {
    const idx = keyIndex.get(companyId);
    if (idx) idx.keys.add(key9(phone));
  }

  // ------------------------------------------------------------- reads / leads
  async function findLeads(companyId, phones) {
    const out = [];
    for (let i = 0; i < phones.length; i += 1000) {
      const d = await rpc('chatly_find_leads', { p_company: companyId, p_phones: phones.slice(i, i + 1000) }, 30000);
      if (Array.isArray(d)) out.push(...d);
    }
    return out;
  }
  async function saveLeads(companyId, leads) {
    for (let i = 0; i < leads.length; i += 500) {
      await rpc('chatly_upsert_leads', { p_company: companyId, p_leads: leads.slice(i, i + 500), p_mode: 'replace' }, 30000);
    }
    for (const l of leads) rememberLead(companyId, l.phone);
  }
  async function addMessagesNow(companyId, items) {
    for (let i = 0; i < items.length; i += 500) await rpc('chatly_add_messages', { p_company: companyId, p_items: items.slice(i, i + 500) }, 30000);
  }

  return { enabled, rpc, queueMessage, queueStatus, flush, queuedCount, isLead, rememberLead, refreshKeys, findLeads, saveLeads, addMessagesNow };
}

let shared = null;
function store() { if (!shared) shared = createStore(); return shared; }

module.exports = { createStore, store, key9 };
