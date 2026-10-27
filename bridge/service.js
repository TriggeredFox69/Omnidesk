'use strict';
const http = require('node:http');
const { timingSafeEqual } = require('node:crypto');
const { submitOnce } = require('./submit');
const { installMessageIdCompatibility } = require('./compatibility');
const { createInbox } = require('./inbox');
const { readPresence } = require('./presence');

// Dependency injection keeps tests independent of a real WhatsApp account.
function createService({ createClient, qrImage, token, storageFile, makeMedia }) {
  if (!token || token.length < 32) throw new Error('A private bridge token of at least 32 characters is required.');
  let client, starting, phase = 'idle', qr = null, number = '', error = '';
  let sendQueue = Promise.resolve();
  const receipts = new Map();
  const requests = new Map();
  const inbox = createInbox({ getClient: () => client, storageFile });
  const ackStatus = ack => ack < 0 ? 'failed' : ack >= 3 ? 'read' : ack === 2 ? 'delivered' : ack === 1 ? 'sent' : 'accepted';
  const remember = (map, key, value) => {
    map.set(key, value);
    if (map.size > 2000) map.delete(map.keys().next().value);
  };
  const snapshot = () => ({ success: true, mode: 'whatsapp_web', state: phase,
    is_connected: phase === 'ready', is_configured: phase === 'ready',
    connected_number: number, qr, message: error });

  function connect() {
    if (starting || ['ready', 'qr', 'authenticated'].includes(phase)) return;
    phase = 'starting'; qr = null; number = ''; error = '';
    starting = (async () => {
      if (client) { client.removeAllListeners(); await client.destroy().catch(() => {}); }
      client = createClient();
      const active = client;
      client.on('qr', async value => {
        phase = 'qr'; qr = null;
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
          inbox.syncRecent().catch(err => console.error('Inbox sync:', err.message));
        } catch (err) {
          phase = 'error'; qr = null;
          error = 'Could not initialize WhatsApp message tracking. Reconnect the sender.';
          console.error('WhatsApp message adapter failed:', err.message);
        }
      });
      client.on('auth_failure', () => { phase = 'error'; qr = null; number = ''; error = 'WhatsApp authentication failed. Link your phone again.'; });
      client.on('disconnected', () => { phase = 'disconnected'; qr = null; number = ''; error = 'WhatsApp disconnected. Reconnect to continue.'; });
      client.on('message_ack', (message, ack) => {
        if (message.id?.fromMe) remember(receipts, message.id._serialized, { status: ackStatus(ack), error: ack < 0 ? 'WhatsApp reported delivery failure.' : null });
        inbox.ingest(message).then(() => inbox.ack(message, ack)).catch(err => console.error('Inbox receipt:', err.message));
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
    if (typeof body.phone !== 'string' || !/^\d{10,15}$/.test(body.phone) ||
        typeof body.message !== 'string' || (!body.message.trim() && !body.media) || body.message.length > 4096) {
      throw Object.assign(new Error('A valid recipient and message (1–4096 characters) are required.'), { status: 400 });
    }
    if (body.media && (typeof body.media.data !== 'string' || body.media.data.length > 14*1024*1024 ||
        !/^[A-Za-z0-9+/]+={0,2}$/.test(body.media.data) ||
        !/^(image\/(png|jpeg|webp|gif)|audio\/[a-z0-9.+-]+)(;.*)?$/i.test(body.media.mimetype || ''))) {
      throw Object.assign(new Error('Use an image or audio attachment up to 10 MB.'), { status: 400 });
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
        throw Object.assign(new Error('Link WhatsApp with the QR code before sending.'), { status: 409 });
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
      if (req.method === 'GET' && url.pathname === '/events') { reply(200, inbox.events(url.searchParams.get('after'))); return; }
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
      if (req.method !== 'POST') { reply(404, { success: false, message: 'Unknown route.' }); return; }
      let raw = '';
      for await (const chunk of req) {
        raw += chunk;
        if (Buffer.byteLength(raw) > 15*1024*1024) throw Object.assign(new Error('Request too large.'), { status: 413 });
      }
      let body;
      try { body = JSON.parse(raw || '{}'); } catch (_) { throw Object.assign(new Error('Invalid JSON.'), { status: 400 }); }
      if (!body || typeof body !== 'object') throw Object.assign(new Error('Invalid request.'), { status: 400 });
      if (url.pathname === '/connect') { connect(); reply(200, snapshot()); return; }
      if (url.pathname === '/sync-chat') { await inbox.syncChat(body.phone); reply(200, { success: true }); return; }
      if (url.pathname === '/send') { reply(200, await send(body)); return; }
      reply(404, { success: false, message: 'Unknown route.' });
    } catch (err) { reply(err.status || 502, { success: false, message: err.message }); }
  });
  return { server, connect, snapshot, close: async () => {
    if (client) { client.removeAllListeners(); await client.destroy().catch(() => {}); }
    server.closeAllConnections();
    await new Promise(resolve => server.close(resolve));
  } };
}
module.exports = { createService };
