'use strict';
// WhatsApp device pool: one WhatsApp login per Team Member, separate from the
// company's main inbox number.
//
// A TM links their phone by scanning the login QR (or the "My WhatsApp for
// Campaigns" card). The scan starts in a temporary "login_…" slot; once the phone
// is known and matched to an account, the slot is adopted by that TM's user id.
// Their session then stays linked: campaigns send their share from it, their own
// chats go out from it, and replies come back into Chatly.
//
// Sessions live in <dataDir>/devices/<dir>/ (dir never changes after creation, so
// the running WhatsApp socket keeps writing its keys to the same place) and are
// restored on bridge start. meta.json records which user owns the folder.
const fs = require('node:fs');
const path = require('node:path');
const { submitOnce } = require('./submit');

const safeId = id => String(id || '').replace(/[^a-zA-Z0-9_-]/g, '').slice(0, 64);
const LOGIN_PREFIX = 'login_';
const LOGIN_TTL_MS = 30 * 60 * 1000;      // an unclaimed login scan is dropped after 30 minutes

function normalizePhone(raw) {
  let p = String(raw || '').replace(/\D/g, '');
  if (p.startsWith('0092')) p = p.slice(2);
  else if (p.startsWith('03') && p.length === 11) p = '92' + p.slice(1);
  else if (p.startsWith('3') && p.length === 10) p = '92' + p;
  return p;
}
const samePhone = (a, b) => {
  a = normalizePhone(a); b = normalizePhone(b);
  return !!a && !!b && (a === b || (a.length >= 9 && b.length >= 9 && a.slice(-9) === b.slice(-9)));
};

function createDevicePool({ dataDir, createClient, qrImage, onMessage }) {
  const root = path.join(dataDir, 'devices');
  fs.mkdirSync(root, { recursive: true, mode: 0o700 });
  const devices = new Map();   // owner id (user id, or login_… while unclaimed) -> device

  const metaFile = d => path.join(root, d.dir, 'meta.json');
  function saveMeta(d) {
    try {
      fs.writeFileSync(metaFile(d), JSON.stringify({ owner: d.id, name: d.name, number: d.number, linkedAt: d.linkedAt, createdAt: d.createdAt }), { mode: 0o600 });
    } catch (_) {}
  }

  function snapshot(d) {
    if (!d) return { success: true, state: 'disconnected', is_connected: false, connected_number: '', qr: null };
    return {
      success: true, user_id: d.id, name: d.name, state: d.phase, is_connected: d.phase === 'ready',
      connected_number: d.number, qr: d.phase === 'qr' ? d.qr : null, message: d.error || '',
      linked_at: d.linkedAt || null, sent: d.sent || 0, claimed: !d.id.startsWith(LOGIN_PREFIX),
    };
  }

  function get(id) { return devices.get(safeId(id)) || null; }

  function newDevice(id, name, dir) {
    return { id, dir: dir || id, name: name || id, phase: 'idle', qr: null, number: '', error: '', client: null,
      queue: Promise.resolve(), sent: 0, linkedAt: null, createdAt: Date.now() };
  }

  function connect(id, name, force = false) {
    id = safeId(id);
    if (!id) throw Object.assign(new Error('A user id is required.'), { status: 400 });
    let d = devices.get(id);
    if (!d) { d = newDevice(id, name); devices.set(id, d); }
    else if (name && !id.startsWith(LOGIN_PREFIX)) d.name = name;
    if (!force && (d.starting || ['ready', 'qr', 'authenticated', 'starting'].includes(d.phase))) return snapshot(d);
    start(d);
    return snapshot(d);
  }

  function start(d) {
    d.phase = 'starting'; d.qr = null; d.error = '';
    const dir = path.join(root, d.dir);
    fs.mkdirSync(dir, { recursive: true, mode: 0o700 });
    d.starting = (async () => {
      if (d.client) { d.client.removeAllListeners(); await d.client.destroy().catch(() => {}); }
      const client = createClient(path.join(dir, 'baileys_auth'), 'Chatly');
      d.client = client;
      client.on('qr', async value => {
        if (d.client !== client) return;
        d.phase = 'qr';
        try { d.qr = await qrImage(value); } catch (_) { d.phase = 'error'; d.error = 'Could not generate the QR code. Try again.'; }
      });
      client.on('authenticated', () => { if (d.client === client) { d.phase = 'authenticated'; d.qr = null; } });
      client.on('ready', () => {
        if (d.client !== client) return;
        d.phase = 'ready'; d.qr = null; d.error = '';
        d.number = normalizePhone(client.info?.wid?.user || d.number);
        if (!d.linkedAt) d.linkedAt = Date.now();
        saveMeta(d);
      });
      client.on('auth_failure', () => {
        if (d.client !== client) return;
        d.phase = 'disconnected'; d.qr = null; d.number = ''; d.error = 'This WhatsApp was logged out. Link it again.';
        try { fs.unlinkSync(metaFile(d)); } catch (_) {}
      });
      client.on('disconnected', () => {
        if (d.client !== client) return;
        if (d.phase === 'ready') { d.phase = 'starting'; d.error = 'Connection interrupted. Reconnecting...'; }
      });
      client.on('message_create', message => {
        if (typeof onMessage === 'function') onMessage(d, client, message);
      });
      await client.initialize();
    })().catch(err => {
      d.phase = 'error'; d.qr = null;
      d.error = 'WhatsApp could not start for this device. Try again.';
      console.error(`[Devices] ${d.id} failed to start:`, err.message);
    }).finally(() => { d.starting = null; });
  }

  async function disconnect(id, wipe = true) {
    const d = get(id);
    if (!d) return snapshot(null);
    d.phase = 'disconnected'; d.qr = null; d.number = ''; d.error = '';
    if (d.client) {
      const c = d.client; d.client = null;
      c.removeAllListeners();
      if (wipe && typeof c.resetSession === 'function') await c.resetSession().catch(() => {});
      else await c.destroy().catch(() => {});
    }
    if (wipe) {
      try { fs.rmSync(path.join(root, d.dir), { recursive: true, force: true }); } catch (_) {}
      devices.delete(d.id);
    }
    return snapshot(null);
  }

  // A login scan becomes the TM's own device. Any older device of that TM is replaced.
  async function adopt(fromId, toId, name) {
    fromId = safeId(fromId); toId = safeId(toId);
    const d = devices.get(fromId);
    if (!d) throw Object.assign(new Error('That login session has expired. Scan the QR again.'), { status: 404 });
    if (fromId === toId) return snapshot(d);
    const old = devices.get(toId);
    if (old && old !== d) await disconnect(toId, !samePhone(old.number, d.number));
    devices.delete(fromId);
    d.id = toId;
    if (name) d.name = name;
    devices.set(toId, d);
    saveMeta(d);
    return snapshot(d);
  }

  // Send one message from a TM's own WhatsApp. Serialized per device.
  function send(id, body) {
    const d = get(id);
    const job = (d ? d.queue : Promise.resolve()).then(async () => {
      if (!d || d.phase !== 'ready' || !d.client) {
        throw Object.assign(new Error('This TM\'s WhatsApp is not connected.'), { status: 409, deviceOffline: true });
      }
      const phone = normalizePhone(body.phone);
      if (!/^\d{10,18}$/.test(phone)) throw Object.assign(new Error('Invalid recipient number.'), { status: 400 });
      const message = typeof body.message === 'string' ? body.message : '';
      if (!message.trim() && !body.media) throw Object.assign(new Error('A message is required.'), { status: 400 });
      const recipient = await d.client.getNumberId(phone);
      if (!recipient) throw Object.assign(new Error('This number is not registered on WhatsApp.'), { status: 400 });
      let content = message;
      if (body.media) {
        try { const { MessageMedia } = require('whatsapp-web.js'); content = new MessageMedia(body.media.mimetype, body.media.data, body.media.filename || 'attachment'); }
        catch (_) { content = body.media; }
      }
      const sent = await submitOnce(d.client, recipient._serialized, content, 60000,
        body.media ? { sendAudioAsVoice: Boolean(body.media.voice && String(body.media.mimetype || '').startsWith('audio/')), caption: message } : {});
      d.sent = (d.sent || 0) + 1;
      if (typeof onMessage === 'function') onMessage(d, d.client, sent, phone);
      return { success: true, dispatch_mode: 'tm_device', from_number: d.number, to_number: phone, message_id: sent?.id?._serialized || ('dev_' + Date.now()) };
    });
    if (d) d.queue = job.catch(() => {});
    return job;
  }

  function list() {
    return [...devices.values()].filter(d => !d.id.startsWith(LOGIN_PREFIX)).map(snapshot);
  }

  function isReady(id) { const d = get(id); return !!(d && d.phase === 'ready'); }

  // Bring back every TM that had linked before the restart.
  function restoreAll() {
    let entries = [];
    try { entries = fs.readdirSync(root, { withFileTypes: true }).filter(e => e.isDirectory()); } catch (_) {}
    for (const e of entries) {
      const dir = e.name;
      if (safeId(dir) !== dir) continue;
      let meta = {};
      try { meta = JSON.parse(fs.readFileSync(path.join(root, dir, 'meta.json'), 'utf8')); } catch (_) {}
      const owner = safeId(meta.owner || dir);
      const hasCreds = fs.existsSync(path.join(root, dir, 'baileys_auth', 'creds.json'));
      // Unclaimed login scans are not kept across restarts.
      if (!hasCreds || owner.startsWith(LOGIN_PREFIX)) {
        if (owner.startsWith(LOGIN_PREFIX)) { try { fs.rmSync(path.join(root, dir), { recursive: true, force: true }); } catch (_) {} }
        continue;
      }
      if (devices.has(owner)) continue;
      const d = newDevice(owner, meta.name || owner, dir);
      d.number = meta.number || '';
      d.linkedAt = meta.linkedAt || null;
      devices.set(owner, d);
      start(d);
    }
  }

  // Drop login scans nobody claimed (abandoned signups), so they don't stay linked.
  const sweeper = setInterval(() => {
    const now = Date.now();
    for (const d of [...devices.values()]) {
      if (d.id.startsWith(LOGIN_PREFIX) && now - (d.createdAt || now) > LOGIN_TTL_MS) disconnect(d.id, true).catch(() => {});
    }
  }, 5 * 60 * 1000);
  if (sweeper.unref) sweeper.unref();

  async function closeAll() {
    clearInterval(sweeper);
    for (const d of devices.values()) {
      if (d.client) { d.client.removeAllListeners(); await d.client.destroy().catch(() => {}); }
    }
  }

  return { connect, disconnect, adopt, send, list, get: id => snapshot(get(id)), isReady, restoreAll, closeAll };
}

module.exports = { createDevicePool, normalizePhone, samePhone };
