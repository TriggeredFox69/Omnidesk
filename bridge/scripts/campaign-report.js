#!/usr/bin/env node
/*
 * What the campaigns did, per company and per closer's WhatsApp: sent, failed, numbers that
 * are not on WhatsApp, refused by WhatsApp, paused, speed. Also the refusals WhatsApp logged.
 * Read-only.
 *   node campaign-report.js <chatly data dir>
 */
'use strict';
const fs = require('node:fs');
const path = require('node:path');
const dataDir = process.argv[2] || path.resolve(__dirname, '../../data');
const dir = path.join(dataDir, 'whatsapp-bridge');

for (const f of fs.readdirSync(dir).filter(f => /^campaign_state.*\.json$/.test(f))) {
  let s;
  try { s = JSON.parse(fs.readFileSync(path.join(dir, f), 'utf8')); } catch (_) { continue; }
  const a = s.audience || [];
  const count = test => a.filter(test).length;
  const started = s.startedAt ? new Date(s.startedAt).toISOString().replace('T', ' ').slice(0, 16) + ' UTC' : '-';
  console.log(`\n${s.companyName || f} (${s.companyId || '?'}): "${s.name || ''}" ${s.status}, started ${started}, Auto Data: ${s.autoData ? 'yes' : 'no'}`);
  console.log(`  numbers ${a.length} | sent ${s.totalSent} | failed ${s.totalFailed} | not on WhatsApp ${count(c => /not registered/i.test(c.error || ''))}` +
    ` | refused by WhatsApp ${count(c => /refused|463|forbidden|limit/i.test(c.error || ''))} | waiting ${count(c => c.status === 'pending')} | gap ${s.minDelay}-${s.maxDelay}s`);
  for (const l of s.lanes || []) {
    const paused = l.pausedUntil && l.pausedUntil > Date.now() ? ` | PAUSED (${l.pauseReason}) until ${new Date(l.pausedUntil).toISOString().slice(0, 16)} UTC` : '';
    console.log(`  - ${l.name} ${l.display || ''}: sent ${l.sent || 0}, failed ${l.failed || 0}, of ${l.assigned || 0}${paused}`);
  }
}

const logFile = path.join(dir, 'bridge.log');
if (fs.existsSync(logFile)) {
  const size = fs.statSync(logFile).size;
  const fd = fs.openSync(logFile, 'r');
  const len = Math.min(size, 8 * 1024 * 1024);
  const buf = Buffer.alloc(len);
  fs.readSync(fd, buf, 0, len, size - len);
  fs.closeSync(fd);
  const refused = buf.toString('utf8').split('\n').filter(l => /refused message/i.test(l));
  const codes = {};
  for (const l of refused) { const c = (/:\s*(\S+)\s*$/.exec(l) || [])[1] || '?'; codes[c] = (codes[c] || 0) + 1; }
  console.log(`\nWhatsApp refusals in the bridge log (last 8 MB): ${refused.length}` + (refused.length ? ` by code: ${JSON.stringify(codes)}` : ''));
  for (const l of refused.slice(-5)) console.log('  ' + l.slice(0, 160));
}
