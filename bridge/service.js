'use strict';
const http = require('node:http');
const { timingSafeEqual } = require('node:crypto');
const { submitOnce } = require('./submit');
const { installMessageIdCompatibility } = require('./compatibility');
const { createInbox } = require('./inbox');
const { readPresence } = require('./presence');
const { createCampaignManager } = require('./campaign');
const { createDevicePool } = require('./devices');

// Dependency injection keeps tests independent of a real WhatsApp account.
function createService({ createClient, createDeviceClient, qrImage, token, storageFile, makeMedia }) {
  if (!token || token.length < 32) throw new Error('A private bridge token of at least 32 characters is required.');
  let client, starting, phase = 'idle', qr = null, number = '', error = '';
  let sendQueue = Promise.resolve();
  const receipts = new Map();
  const requests = new Map();
  let devicePoolRef = null;
  const inbox = createInbox({ getClient: () => client, storageFile, getMediaClients: () => (devicePoolRef ? devicePoolRef.clients() : []) });
  const bridgeDataDir = storageFile ? require('node:path').dirname(storageFile) : require('node:path').join(require('node:os').tmpdir(), 'omnidesk-bridge');

  // Each Team Member's own WhatsApp, used to send their share of a campaign.
  // Replies to those numbers land in the same inbox, tagged with the TM.
  async function devicePhoneFor(devClient, id) {
    if (!id || /@(g\.us|broadcast|newsletter)$/.test(id)) return null;
    if (/@(c\.us|s\.whatsapp\.net)$/.test(id)) return id.split('@')[0].split(':')[0];
    try {
      if (typeof devClient.getContactLidAndPhone === 'function') {
        const pn = (await devClient.getContactLidAndPhone([id]))?.[0]?.pn;
        if (pn) return String(pn).split('@')[0].split(':')[0];
      }
      if (typeof devClient.resolveLid === 'function') {
        const r = await devClient.resolveLid(id);
        if (r) return r;
      }
    } catch (_) {}
    return null;
  }
  const devicePool = createDevicePool({
    dataDir: bridgeDataDir,
    qrImage,
    createClient: createDeviceClient || ((authPath, deviceName) => {
      const { BaileysClient } = require('./baileys-client');
      return new BaileysClient({ authPath, deviceName });
    }),
    onMessage: (device, devClient, message, knownPhone) => {
      (async () => {
        if (!message?.id?._serialized) return;
        const fromMe = Boolean(message.fromMe || message.id.fromMe);
        const remote = message.id?.remote?._serialized || message.id?.remote || (fromMe ? message.to : message.from);
        const phone = knownPhone || await devicePhoneFor(devClient, remote);
        if (!phone || !/^\d{10,18}$/.test(phone)) return;
        await inbox.ingest(message, phone, { phone, agentName: device.name, viaUserId: device.id, viaNumber: device.number });
      })().catch(err => console.error('Device inbox:', err.message));
    },
    onAck: (_device, _devClient, message, ack) => inbox.ack(message, ack)
  });
  devicePoolRef = devicePool;

  const campaignManager = createCampaignManager({
    send,
    deviceSend: (id, body) => devicePool.send(id, body),
    deviceReady: id => devicePool.isReady(id),
    // The company's main linked WhatsApp, so a TM whose phone is linked there also counts.
    mainStatus: () => ({ ready: phase === 'ready', number }),
    inbox,
    dataDir: bridgeDataDir,
    getLeadsPath: (companyId = 'COMP-001') => {
      const cleanId = String(companyId || 'COMP-001').replace(/[^a-zA-Z0-9_-]/g, '') || 'COMP-001';
      const tenantPath = require('node:path').resolve(__dirname, `../data/tenants/${cleanId}/leads.json`);
      if (require('node:fs').existsSync(tenantPath)) return tenantPath;
      if (cleanId === 'COMP-001') {
        const legacy = require('node:path').resolve(__dirname, '../data/leads.json');
        if (require('node:fs').existsSync(legacy)) return legacy;
      }
      const dir = require('node:path').dirname(tenantPath);
      if (!require('node:fs').existsSync(dir)) {
        try { require('node:fs').mkdirSync(dir, { recursive: true }); } catch (_) {}
      }
      return tenantPath;
    },
    getUsersPath: () => require('node:path').resolve(__dirname, '../data/users.json')
  });
  const ackStatus = ack => ack < 0 ? 'failed' : ack >= 3 ? 'read' : ack === 2 ? 'delivered' : ack === 1 ? 'sent' : 'accepted';
  const remember = (map, key, value) => {
    map.set(key, value);
    if (map.size > 2000) map.delete(map.keys().next().value);
  };
  const snapshot = () => ({ success: true, mode: 'whatsapp_web', state: phase,
    is_connected: phase === 'ready', is_configured: phase === 'ready',
    connected_number: number, qr, message: error, version: '18.4', engine: 'baileys' });

  async function disconnect(wipe = false) {
    phase = 'disconnected'; qr = null; number = ''; error = '';
    if (client) {
      client.removeAllListeners();
      if (wipe && typeof client.resetSession === 'function') {
        await client.resetSession().catch(() => {});
      } else {
        await client.destroy().catch(() => {});
      }
      client = null;
    }
  }

  function connect(force = false) {
    if (!force && (starting || ['ready', 'qr', 'authenticated'].includes(phase))) return;
    phase = 'starting'; qr = null; number = ''; error = '';
    starting = (async () => {
      if (client) { client.removeAllListeners(); await client.destroy().catch(() => {}); }
      client = createClient();
      const active = client;
      client.on('qr', async value => {
        phase = 'qr';
        try {
          const rendered = await qrImage(value);
          if (client === active && phase === 'qr') qr = rendered;
        } catch (_) { phase = 'error'; error = 'Could not generate the QR code. Try connecting again.'; }
      });
      client.on('authenticated', () => { phase = 'authenticated'; qr = null; });
      client.on('ready', async () => {
        try {
          await installMessageIdCompatibility(active);
          if (client !== active) return;
          phase = 'ready'; qr = null; error = '';
          number = active.info?.wid?.user || '';
        } catch (err) {
          phase = 'error'; qr = null;
          error = 'Could not initialize WhatsApp message tracking. Reconnect the sender.';
          console.error('WhatsApp message adapter failed:', err.message);
        }
      });
      client.on('auth_failure', () => { phase = 'error'; qr = null; number = ''; error = 'WhatsApp authentication failed. Link your phone again.'; });
      client.on('disconnected', () => {
        if (phase === 'ready' || number) {
          phase = 'starting';
          error = 'WhatsApp connection interrupted. Reconnecting automatically...';
        } else if (phase === 'qr' || phase === 'starting') {
          // Socket was closed while waiting for QR scan (normal Baileys QR rotation or handshake retry).
          // Keep QR state alive and do NOT wipe qr or revert to disconnected!
          error = 'Waiting for QR scan...';
        } else {
          phase = 'disconnected';
          qr = null;
          number = '';
          error = 'WhatsApp disconnected. Reconnect to continue.';
        }
      });
      client.on('message_ack', (message, ack) => {
        if (message.id?.fromMe) remember(receipts, message.id._serialized, { status: ackStatus(ack), error: ack < 0 ? 'WhatsApp reported delivery failure.' : null });
        inbox.ack(message, ack);
      });
      client.on('message_create', message => { inbox.ingest(message).catch(err => console.error('Inbox message:', err.message)); });
      await client.initialize();
    })().catch(err => {
      phase = 'error'; qr = null;
      error = 'WhatsApp could not start. Check the bridge log and browser dependencies, then retry.';
      console.error('WhatsApp initialization failed:', err.message);
    }).finally(() => { starting = null; });
  }

  async function send(body) {
    if (!body || typeof body !== 'object') {
      throw Object.assign(new Error('Invalid send payload.'), { status: 400 });
    }
    let cleanPhone = String(body.phone || '').replace(/\D/g, '');
    if (cleanPhone.startsWith('0092')) cleanPhone = cleanPhone.slice(2);
    else if (cleanPhone.startsWith('03') && cleanPhone.length === 11) cleanPhone = '92' + cleanPhone.slice(1);
    else if (cleanPhone.startsWith('3') && cleanPhone.length === 10) cleanPhone = '92' + cleanPhone;
    body.phone = cleanPhone;

    if (!cleanPhone || !/^\d{10,18}$/.test(cleanPhone)) {
      throw Object.assign(new Error('A valid recipient phone number (10–18 digits) is required.'), { status: 400 });
    }
    if (typeof body.message !== 'string') body.message = '';
    if (!body.message.trim() && !body.media) {
      throw Object.assign(new Error('A message or media attachment is required.'), { status: 400 });
    }
    if (body.message.length > 4096) {
      throw Object.assign(new Error('Message text must not exceed 4096 characters.'), { status: 400 });
    }

    if (body.media) {
      if (typeof body.media.data !== 'string' || body.media.data.length > 14*1024*1024) {
        throw Object.assign(new Error('Attachment must be up to 10 MB.'), { status: 400 });
      }
      body.media.data = body.media.data.replace(/\s+/g, '');
      const validMime = /^(image\/(jpeg|png|webp|gif)|audio\/[a-z0-9.+-]+(;[a-z0-9=;+._ -]+)?|video\/(mp4|3gpp)|application\/(pdf|msword|vnd\.[a-z0-9.-]+|zip|octet-stream)|text\/(plain|csv))$/i.test(body.media.mimetype || '');
      if (!validMime) {
        throw Object.assign(new Error('Supported attachments: images, voice/audio notes, and documents (PDF, Word, Excel, text) up to 10 MB.'), { status: 400 });
      }
    }
    if (typeof body.request_id !== 'string' || !/^[a-zA-Z0-9_-]{8,120}$/.test(body.request_id)) {
      throw Object.assign(new Error('A send request ID is required.'), { status: 400 });
    }
    const signature = require('node:crypto').createHash('sha256').update(JSON.stringify([body.phone, body.message, body.media])).digest('hex');
    if (requests.has(body.request_id)) {
      const prior = requests.get(body.request_id);
      if (prior.signature !== signature) throw Object.assign(new Error('Request ID already used for another message.'), { status: 409 });
      return prior.promise;
    }
    const promise = sendQueue.then(async () => {
      if (phase !== 'ready') {
        requests.delete(body.request_id); // Nothing was submitted; safe to resume after linking.
        if (body.simulate) {
          const simId = 'sim_' + Date.now() + '_' + Math.random().toString(36).slice(2, 7);
          return {
            success: true,
            simulated: true,
            message_id: simId,
            status: 'accepted',
            sender_phone: body.sender_phone || number || '923318036576'
          };
        }
        throw Object.assign(new Error(`WhatsApp is not connected (status: ${phase}). Please link your phone via QR code before sending.`), { status: 409 });
      }
      const recipient = await client.getNumberId(body.phone);
      if (!recipient) {
        requests.delete(body.request_id);
        throw Object.assign(new Error('This number is not registered on WhatsApp.'), { status: 400 });
      }
      let content;
      if (body.media) {
        if (makeMedia) {
          content = makeMedia(body.media);
        } else {
          try {
            const { MessageMedia } = require('whatsapp-web.js');
            content = new MessageMedia(body.media.mimetype, body.media.data, body.media.filename || 'attachment');
          } catch (_) {
            content = body.media;
          }
        }
      } else {
        content = body.message;
      }
      const sent = await submitOnce(client, recipient._serialized, content, 60000,
        body.media ? { sendAudioAsVoice: Boolean(body.media.voice && body.media.mimetype.startsWith('audio/')), caption: body.message } : {});
      await inbox.ingest(sent, body.phone);
      const id = sent.id._serialized;
      if (!receipts.has(id)) remember(receipts, id, { status: ackStatus(sent.ack || 0), error: null });
      return { success: true, dispatch_mode: 'whatsapp_web', from_number: number, to_number: body.phone, message_id: id };
    });
    // Retain the result (including uncertain failures) so retries cannot duplicate a send.
    remember(requests, body.request_id, { signature, promise });
    sendQueue = promise.catch(() => {});
    return promise;
  }

  const server = http.createServer(async (req, res) => {
    res.setHeader('Content-Type', 'application/json');
    res.setHeader('Cache-Control', 'no-store');
    const reply = (status, data) => { res.writeHead(status); res.end(JSON.stringify(data)); };
    const supplied = Buffer.from(req.headers.authorization || '');
    const expected = Buffer.from(`Bearer ${token}`);
    if (supplied.length !== expected.length || !timingSafeEqual(supplied, expected)) {
      reply(401, { success: false, message: 'Unauthorized bridge request.' }); return;
    }
    try {
      const url = new URL(req.url, 'http://localhost');
      if (req.method === 'GET' && url.pathname === '/status') { reply(200, snapshot()); return; }
      if (req.method === 'GET' && url.pathname === '/events') {
        const ev = inbox.events(url.searchParams.get('after'));
        if (client && typeof client.getRecentPresences === 'function') {
          ev.presences = client.getRecentPresences();
        }
        reply(200, ev);
        return;
      }
      if (req.method === 'GET' && url.pathname === '/media') { reply(200, await inbox.media(url.searchParams.get('id'))); return; }
      if (req.method === 'GET' && url.pathname === '/presence') { reply(200, await readPresence(client, url.searchParams.get('phone') || '')); return; }
      if (req.method === 'GET' && url.pathname === '/receipt') {
        const id = url.searchParams.get('id');
        let receipt = receipts.get(id);
        if (!receipt && phase === 'ready') {
          const message = await client.getMessageById(id).catch(() => null);
          if (message?.id?.fromMe) receipt = { status: ackStatus(message.ack || 0) };
        }
        reply(200, { success: true, ...(receipt || { status: 'accepted' }) }); return;
      }
      if (req.method === 'GET' && url.pathname === '/devices') { reply(200, { success: true, devices: devicePool.list() }); return; }
      if (req.method === 'GET' && url.pathname === '/device/status') { reply(200, devicePool.get(url.searchParams.get('id') || '')); return; }
      if (url.pathname === '/campaign/status') {
        reply(200, campaignManager.getStatus());
        return;
      }
      if (req.method !== 'POST') { reply(404, { success: false, message: 'Unknown route.' }); return; }
      let raw = '';
      for await (const chunk of req) {
        raw += chunk;
        if (Buffer.byteLength(raw) > 15*1024*1024) throw Object.assign(new Error('Request too large.'), { status: 413 });
      }
      let body;
      try { body = JSON.parse(raw || '{}'); } catch (_) { throw Object.assign(new Error('Invalid JSON.'), { status: 400 }); }
      if (url.pathname === '/connect') { connect(false); reply(200, snapshot()); return; }
      if (url.pathname === '/reconnect') {
        await disconnect(false);
        connect(true);
        reply(200, { success: true, message: 'Reconnecting WhatsApp bridge...', ...snapshot() });
        return;
      }
      if (url.pathname === '/reset') {
        await disconnect(true);
        connect(true);
        reply(200, { success: true, message: 'Session reset cleanly. Generating fresh QR code.', ...snapshot() });
        return;
      }
      // Historical per-chat fetches are intentionally disabled. Live Chatly
      // sends and new replies still arrive through the socket event stream.
      if (url.pathname === '/sync-chat') { reply(200, { success: true, synced: false, message: 'Historical WhatsApp sync is disabled.' }); return; }
      if (url.pathname === '/presence') {
        const phone = body.phone || '';
        const type = body.type || 'composing';
        if (client && typeof client.sendPresence === 'function') {
          await client.sendPresence(phone, type);
        }
        reply(200, { success: true });
        return;
      }
      if (url.pathname === '/device/adopt') { reply(200, await devicePool.adopt(body.from, body.to, body.name)); return; }
      if (url.pathname === '/device/send') { reply(200, await devicePool.send(body.id, body)); return; }
      if (url.pathname === '/device/connect') { reply(200, devicePool.connect(body.id, body.name, Boolean(body.force))); return; }
      if (url.pathname === '/device/reset') {
        await devicePool.disconnect(body.id, true);
        reply(200, body.relink ? devicePool.connect(body.id, body.name, true) : { success: true, state: 'disconnected', is_connected: false });
        return;
      }
      if (url.pathname === '/campaign/start') {
        reply(200, campaignManager.start(body));
        return;
      }
      if (url.pathname === '/campaign/pause') {
        reply(200, campaignManager.pause());
        return;
      }
      if (url.pathname === '/campaign/resume') {
        reply(200, campaignManager.resume());
        return;
      }
      if (url.pathname === '/campaign/stop') {
        reply(200, campaignManager.stop());
        return;
      }
      if (url.pathname === '/campaign/clear') {
        reply(200, campaignManager.clear ? campaignManager.clear() : campaignManager.stop());
        return;
      }
      if (url.pathname === '/send') { reply(200, await send(body)); return; }
      reply(404, { success: false, message: 'Unknown route.' });
    } catch (err) { reply(err.status || 502, { success: false, message: err.message }); }
  });
  return { server, connect, snapshot, campaignManager, devicePool, close: async () => {
    if (client) { client.removeAllListeners(); await client.destroy().catch(() => {}); }
    await devicePool.closeAll().catch(() => {});
    server.closeAllConnections();
    await new Promise(resolve => server.close(resolve));
  } };
}
module.exports = { createService };
