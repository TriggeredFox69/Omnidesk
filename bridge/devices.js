'use strict';
// Campaign device pool: one WhatsApp login per Team Member, separate from the
// company's main inbox number. Each TM links their own phone (QR in their
// workspace); campaigns then send each TM's share of the numbers from that TM's
// own WhatsApp. Sessions live in <dataDir>/devices/<userId>/ and are restored on
// bridge start, so a restart does not log anyone out.
const fs = require('node:fs');
const path = require('node:path');
const { submitOnce } = require('./submit');

const safeId = id => String(id || '').replace(/[^a-zA-Z0-9_-]/g, '').slice(0, 64);

function normalizePhone(raw) {
  let p = String(raw || '').replace(/\D/g, '');
  if (p.startsWith('0092')) p = p.slice(2);
  else if (p.startsWith('03') && p.length === 11) p = '92' + p.slice(1);
  else if (p.startsWith('3') && p.length === 10) p = '92' + p;
  return p;
}

function createDevicePool({ dataDir, createClient, qrImage, onMessage }) {
  const root = path.join(dataDir, 'devices');
  fs.mkdirSync(root, { recursive: true, mode: 0o700 });
  const devices = new Map();   // userId -> device

  const metaFile = id => path.join(root, id, 'meta.json');
  function saveMeta(d) {
    try { fs.writeFileSync(metaFile(d.id), JSON.stringify({ id: d.id, name: d.name, number: d.number, linkedAt: d.linkedAt }), { mode: 0o600 }); } catch (_) {}
  }

  function snapshot(d) {
    if (!d) return { success: true, state: 'disconnected', is_connected: false, connected_number: '', qr: null };
    return {
      success: true, user_id: d.id, name: d.name, state: d.phase, is_connected: d.phase === 'ready',
      connected_number: d.number, qr: d.phase === 'qr' ? d.qr : null, message: d.error || '',
      linked_at: d.linkedAt || null, sent: d.sent || 0,
    };
  }

  function get(id) { return devices.get(safeId(id)) || null; }

  function connect(id, name, force = false) {
    id = safeId(id);
    if (!id) throw Object.assign(new Error('A user id is required.'), { status: 400 });
    let d = devices.get(id);
    if (!d) {
      d = { id, name: name || id, phase: 'idle', qr: null, number: '', error: '', client: null, queue: Promise.resolve(), sent: 0, linkedAt: null };
      devices.set(id, d);
    } else if (name) d.name = name;
    if (!force && (d.starting || ['ready', 'qr', 'authenticated', 'starting'].includes(d.phase))) return snapshot(d);
    d.phase = 'starting'; d.qr = null; d.error = '';
    const dir = path.join(root, id);
    fs.mkdirSync(dir, { recursive: true, mode: 0o700 });
    d.starting = (async () => {
      if (d.client) { d.client.removeAllListeners(); await d.client.destroy().catch(() => {}); }
      const client = createClient(path.join(dir, 'baileys_auth'), `Chatly Campaign · ${d.name}`);
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
        d.number = client.info?.wid?.user || d.number;
        if (!d.linkedAt) d.linkedAt = Date.now();
        saveMeta(d);
      });
      client.on('auth_failure', () => {
        if (d.client !== client) return;
        d.phase = 'disconnected'; d.qr = null; d.number = ''; d.error = 'This WhatsApp was logged out. Link it again.';
        try { fs.unlinkSync(metaFile(id)); } catch (_) {}
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
      console.error(`[Devices] ${id} failed to start:`, err.message);
    }).finally(() => { d.starting = null; });
    return snapshot(d);
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
      try { fs.rmSync(path.join(root, d.id), { recursive: true, force: true }); } catch (_) {}
      devices.delete(d.id);
    }
    return snapshot(null);
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
        body.media ? { caption: message } : {});
      d.sent = (d.sent || 0) + 1;
      if (typeof onMessage === 'function') onMessage(d, d.client, sent, phone);
      return { success: true, dispatch_mode: 'tm_device', from_number: d.number, to_number: phone, message_id: sent?.id?._serialized || ('dev_' + Date.now()) };
    });
    if (d) d.queue = job.catch(() => {});
    return job;
  }

  function list() {
    return [...devices.values()].map(snapshot);
  }

  function isReady(id) { const d = get(id); return !!(d && d.phase === 'ready'); }

  // Bring back every TM that had linked before the restart.
  function restoreAll() {
    let entries = [];
    try { entries = fs.readdirSync(root, { withFileTypes: true }).filter(e => e.isDirectory()); } catch (_) {}
    for (const e of entries) {
      const id = safeId(e.name);
      if (!id || !fs.existsSync(path.join(root, id, 'baileys_auth', 'creds.json'))) continue;
      let meta = {};
      try { meta = JSON.parse(fs.readFileSync(metaFile(id), 'utf8')); } catch (_) {}
      const d = { id, name: meta.name || id, phase: 'idle', qr: null, number: meta.number || '', error: '', client: null, queue: Promise.resolve(), sent: 0, linkedAt: meta.linkedAt || null };
      devices.set(id, d);
      connect(id, d.name, true);
    }
  }

  async function closeAll() {
    for (const d of devices.values()) {
      if (d.client) { d.client.removeAllListeners(); await d.client.destroy().catch(() => {}); }
    }
  }

  return { connect, disconnect, send, list, get: id => snapshot(get(id)), isReady, restoreAll, closeAll };
}

module.exports = { createDevicePool, normalizePhone };
