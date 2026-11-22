#!/usr/bin/env node
/*
 * Uploads the Auto Data number pool to Supabase (see schema.sql).
 *
 *   node supabase/import-numbers.js "<folder with .txt number lists>" --dry   # read + report only
 *   node supabase/import-numbers.js "<folder with .txt number lists>"         # upload
 *
 * Reads every .txt file under the folder, keeps valid Pakistani mobile numbers (03xx…,
 * 923xx…, 00923xx…, 3xx…), removes duplicates, shuffles them in a fixed order (same result on
 * every run, so a re-run never moves anyone's place in the list) and uploads them 1,000 per
 * row. Needs Node 18+ and no packages.
 *
 * Supabase settings come from .env.supabase in the project folder (never committed):
 *   SUPABASE_URL=https://<project>.supabase.co
 *   SUPABASE_SECRET_KEY=<secret key (sb_secret_…) or legacy service_role key>
 */
'use strict';
const fs = require('node:fs');
const path = require('node:path');

const CHUNK = 1000;
const BATCH_ROWS = 100;          // rows per upload request (~1 MB of JSON)
const PARALLEL = 4;
const SEED = 0x5eed2026;

const args = process.argv.slice(2);
const folder = args.find(a => !a.startsWith('--'));
const dry = args.includes('--dry');
const force = args.includes('--force');
if (!folder || !fs.existsSync(folder)) {
  console.error('Usage: node supabase/import-numbers.js "<numbers folder>" [--dry] [--force]');
  process.exit(1);
}

function loadEnv() {
  const file = path.join(__dirname, '..', '.env.supabase');
  const env = {};
  if (fs.existsSync(file)) {
    for (const line of fs.readFileSync(file, 'utf8').split(/\r?\n/)) {
      const m = /^\s*([A-Z0-9_]+)\s*=\s*(.*?)\s*$/.exec(line);
      if (m && !line.trim().startsWith('#')) env[m[1]] = m[2].replace(/^["']|["']$/g, '');
    }
  }
  return {
    url: String(process.env.SUPABASE_URL || env.SUPABASE_URL || '').replace(/\/+$/, ''),
    key: String(process.env.SUPABASE_SECRET_KEY || env.SUPABASE_SECRET_KEY || env.SUPABASE_SERVICE_ROLE_KEY || '')
  };
}

// ---- 1. read and clean ----
function listFiles(dir) {
  const out = [];
  (function walk(d) {
    for (const e of fs.readdirSync(d, { withFileTypes: true })) {
      const p = path.join(d, e.name);
      if (e.isDirectory()) walk(p);
      else if (/\.(txt|csv)$/i.test(e.name)) out.push(p);
    }
  })(dir);
  return out;
}

// One digit string -> the 10-digit 3xxxxxxxxx numbers in it (several when numbers were glued together).
function numbersIn(d, push) {
  const cut = (len, prefix) => {
    if (d.length % len !== 0) return false;
    const parts = [];
    for (let i = 0; i < d.length; i += len) {
      const p = d.slice(i, i + len);
      if (!p.startsWith(prefix)) return false;
      parts.push(p.slice(-10));
    }
    parts.forEach(push);
    return true;
  };
  if (d.length === 10 && d[0] === '3') { push(d); return; }
  if (cut(11, '03')) return;
  if (cut(12, '923')) return;
  cut(14, '00923');
}

let values = new Uint32Array(16_000_000);
let count = 0, lines = 0, skipped = 0;
function push(ten) {
  const v = Number(ten) - 3000000000;          // 0 .. 999,999,999
  if (!(v >= 0 && v < 1e9)) return;
  if (count === values.length) { const bigger = new Uint32Array(values.length * 2); bigger.set(values); values = bigger; }
  values[count++] = v;
}

const files = listFiles(folder);
console.log(`Reading ${files.length} files…`);
for (const f of files) {
  const text = fs.readFileSync(f, 'latin1');
  for (const line of text.split(/\r?\n/)) {
    if (!line.trim()) continue;
    lines++;
    const before = count;
    // Split on anything that is not part of a phone number (commas, tabs, letters, …), then
    // drop spaces / dashes / dots / brackets / + inside each piece.
    for (const seg of line.split(/[^0-9+\-\s().]+/)) {
      const d = seg.replace(/\D+/g, '');
      if (d.length >= 10) numbersIn(d, push);
    }
    if (count === before) skipped++;
  }
}

// ---- 2. dedupe ----
const all = values.subarray(0, count).sort();
let unique = 0;
for (let i = 0; i < all.length; i++) if (i === 0 || all[i] !== all[i - 1]) all[unique++] = all[i];
const pool = all.subarray(0, unique);
console.log(`${lines.toLocaleString()} lines, ${count.toLocaleString()} numbers, ${unique.toLocaleString()} unique, ${skipped.toLocaleString()} lines without a valid number.`);

// ---- 3. shuffle (fixed seed: same order every run) ----
let s = SEED >>> 0;
const rnd = () => { s ^= s << 13; s >>>= 0; s ^= s >>> 17; s ^= s << 5; s >>>= 0; return s; };
for (let i = pool.length - 1; i > 0; i--) {
  const j = Math.floor((rnd() / 4294967296) * (i + 1));
  const t = pool[i]; pool[i] = pool[j]; pool[j] = t;
}
const rows = Math.ceil(pool.length / CHUNK);
const sample = Array.from(pool.subarray(0, 5), v => '0' + String(3000000000 + v));
console.log(`Shuffled. ${rows.toLocaleString()} rows of ${CHUNK}. First numbers: ${sample.join(', ')}`);

if (dry) { console.log('Dry run: nothing uploaded.'); process.exit(0); }

// ---- 4. upload ----
const { url, key } = loadEnv();
if (!/^https:\/\/[a-z0-9-]+\.supabase\.co$/i.test(url) || !key) {
  console.error('Missing SUPABASE_URL / SUPABASE_SECRET_KEY in .env.supabase');
  process.exit(1);
}
const headers = { apikey: key, 'Content-Type': 'application/json' };
if (!key.startsWith('sb_')) headers.Authorization = 'Bearer ' + key;

async function rest(method, pathAndQuery, body, extra = {}) {
  for (let attempt = 1; ; attempt++) {
    try {
      const res = await fetch(url + '/rest/v1/' + pathAndQuery, { method, headers: { ...headers, ...extra }, body: body === undefined ? undefined : JSON.stringify(body) });
      const text = await res.text();
      if (!res.ok) throw new Error(`HTTP ${res.status}: ${text.slice(0, 300)}`);
      return text ? JSON.parse(text) : null;
    } catch (err) {
      if (attempt >= 4) throw err;
      await new Promise(r => setTimeout(r, 1500 * attempt));
    }
  }
}

(async () => {
  const meta = await rest('GET', 'chatly_pool_meta?id=eq.1&select=total');
  const cursors = await rest('GET', 'chatly_pool_cursors?select=company_id&limit=1');
  if (meta && meta[0] && Number(meta[0].total) !== pool.length && cursors && cursors.length && !force) {
    console.error(`The pool already holds ${meta[0].total} numbers and companies have used it. A different list would move everyone's place. Re-run with --force only if that is intended.`);
    process.exit(1);
  }

  let next = 0, done = 0;
  const started = Date.now();
  async function worker() {
    while (next < rows) {
      const first = next; next += BATCH_ROWS;
      const batch = [];
      for (let k = first; k < Math.min(first + BATCH_ROWS, rows); k++) {
        batch.push({ chunk: k, nums: Array.from(pool.subarray(k * CHUNK, Math.min((k + 1) * CHUNK, pool.length))) });
      }
      await rest('POST', 'chatly_numbers?on_conflict=chunk', batch, { Prefer: 'resolution=merge-duplicates,return=minimal' });
      done += batch.length;
      process.stdout.write(`\rUploaded ${done.toLocaleString()} / ${rows.toLocaleString()} rows (${Math.round(done * 100 / rows)}%)   `);
    }
  }
  await Promise.all(Array.from({ length: PARALLEL }, worker));
  // rows beyond the new end (only if an older, longer list was uploaded before)
  await rest('DELETE', `chatly_numbers?chunk=gte.${rows}`, undefined, { Prefer: 'return=minimal' });
  // the pool is usable only once every row is in
  await rest('POST', 'chatly_pool_meta?on_conflict=id', [{ id: 1, total: pool.length, chunk_size: CHUNK, imported_at: new Date().toISOString(), source: path.basename(folder) }],
    { Prefer: 'resolution=merge-duplicates,return=minimal' });
  console.log(`\nDone in ${Math.round((Date.now() - started) / 1000)} s: ${pool.length.toLocaleString()} numbers in the pool.`);
})().catch(err => { console.error('\nUpload failed:', err.message); process.exit(1); });
