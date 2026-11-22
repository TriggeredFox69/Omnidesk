'use strict';
// Which company a WhatsApp conversation belongs to, and where its leads live.
//
// Every company keeps its leads in data/tenants/<company_id>/leads.json (Nunno,
// COMP-001, also mirrors data/leads.json for old code). A closer's linked WhatsApp
// belongs to the closer's company (users.json). The company main number is the
// legacy Nunno sender (COMP-001) unless CHATLY_MAIN_COMPANY says otherwise.
const fs = require('node:fs');
const path = require('node:path');

const DATA_DIR = path.resolve(__dirname, '../data');
const MAIN_COMPANY = process.env.CHATLY_MAIN_COMPANY || 'COMP-001';
const clean = id => String(id || '').replace(/[^a-zA-Z0-9_-]/g, '');
const digits = p => {
  let c = String(p || '').replace(/\D/g, '');
  if (c.startsWith('0092')) c = c.slice(2);
  else if (c.startsWith('03') && c.length === 11) c = '92' + c.slice(1);
  else if (c.startsWith('3') && c.length === 10) c = '92' + c;
  return c;
};
const samePhone = (a, b) => {
  a = digits(a); b = digits(b);
  return !!a && !!b && (a === b || (a.length >= 9 && b.length >= 9 && a.slice(-9) === b.slice(-9)));
};

let usersCache = { at: 0, byId: new Map() };
function usersById() {
  if (Date.now() - usersCache.at < 10000) return usersCache.byId;
  const byId = new Map();
  try {
    const users = JSON.parse(fs.readFileSync(path.join(DATA_DIR, 'users.json'), 'utf8'));
    for (const u of Array.isArray(users) ? users : []) if (u && u.id) byId.set(String(u.id), u);
  } catch (_) {}
  usersCache = { at: Date.now(), byId };
  return byId;
}

function companyOfUser(userId) {
  const u = userId ? usersById().get(String(userId)) : null;
  const c = clean(u && u.company_id);
  return c && c !== 'GLOBAL' ? c : (u ? MAIN_COMPANY : null);
}

// Company of a message: the closer's company for their own WhatsApp, else the main sender's.
function companyOfDevice(viaUserId) {
  return viaUserId ? (companyOfUser(viaUserId) || MAIN_COMPANY) : MAIN_COMPANY;
}

function leadsFile(companyId) {
  const id = clean(companyId) || MAIN_COMPANY;
  const tenant = path.join(DATA_DIR, 'tenants', id, 'leads.json');
  if (!fs.existsSync(tenant) && id === 'COMP-001') {
    const legacy = path.join(DATA_DIR, 'leads.json');
    if (fs.existsSync(legacy)) return legacy;
  }
  return tenant;
}

function companyIds() {
  try {
    return fs.readdirSync(path.join(DATA_DIR, 'tenants'), { withFileTypes: true })
      .filter(e => e.isDirectory() && clean(e.name) === e.name && e.name !== 'GLOBAL' && e.name !== 'backups')
      .map(e => e.name);
  } catch (_) { return [MAIN_COMPANY]; }
}

function readLeads(companyId) {
  try {
    const leads = JSON.parse(fs.readFileSync(leadsFile(companyId), 'utf8'));
    return Array.isArray(leads) ? leads : [];
  } catch (_) { return []; }
}

// Write-then-rename so PHP never reads half a file; Nunno also mirrors data/leads.json.
function writeLeads(companyId, leads) {
  const file = leadsFile(companyId);
  const json = JSON.stringify(leads, null, 2);
  const put = target => {
    const tmp = `${target}.bridge-${process.pid}.tmp`;
    try {
      fs.mkdirSync(path.dirname(target), { recursive: true });
      fs.writeFileSync(tmp, json, 'utf8');
      fs.renameSync(tmp, target);
    } catch (_) { try { fs.unlinkSync(tmp); } catch (__) {} }
  };
  put(file);
  const legacy = path.join(DATA_DIR, 'leads.json');
  if (clean(companyId) === 'COMP-001' && file !== legacy && fs.existsSync(legacy)) put(legacy);
}

// Phone index per leads file, rebuilt only when the file changes. isLeadOf runs for every
// message on every linked phone (personal chats included), and parsing a 10 MB leads.json
// each time was a major source of bridge lag. Keys match samePhone(): numbers of 9+ digits
// compare on their last 9, shorter ones exactly.
const phoneKey = d => (d.length >= 9 ? d.slice(-9) : d);
const phoneIndex = new Map();   // leads file -> { sig, keys }
function leadPhoneKeys(companyId) {
  const file = leadsFile(companyId);
  let sig = '';
  try { const st = fs.statSync(file); sig = `${st.mtimeMs}:${st.size}`; } catch (_) { return new Set(); }
  const hit = phoneIndex.get(file);
  if (hit && hit.sig === sig) return hit.keys;
  const keys = new Set();
  for (const l of readLeads(companyId)) { const d = digits(l && l.phone); if (d) keys.add(phoneKey(d)); }
  phoneIndex.set(file, { sig, keys });
  return keys;
}

function isLeadOf(companyId, phone) {
  const d = digits(phone);
  return !!d && leadPhoneKeys(companyId).has(phoneKey(d));
}

module.exports = { DATA_DIR, MAIN_COMPANY, digits, samePhone, usersById, companyOfUser, companyOfDevice, leadsFile, companyIds, readLeads, writeLeads, isLeadOf };
