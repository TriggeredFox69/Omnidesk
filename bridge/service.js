'use strict';
const http = require('node:http');
const { timingSafeEqual } = require('node:crypto');

// Dependency injection keeps tests independent of a real WhatsApp account.
function createService({ createClient, qrImage, token }) {
  if (!token || token.length < 32) throw new Error('A private bridge token of at least 32 characters is required.');
  let client, starting, phase = 'idle', qr = null, number = '', error = '';
  let sendQueue = Promise.resolve();
  const receipts = new Map();
  const requests = new Map();
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
      client.on('ready', () => {
        phase = 'ready'; qr = null; error = '';
        number = client.info?.wid?.user || '';
      });
      client.on('auth_failure', () => { phase = 'error'; qr = null; number = ''; error = 'WhatsApp authentication failed. Link your phone again.'; });
      client.on('disconnected', () => { phase = 'disconnected'; qr = null; number = ''; error = 'WhatsApp disconnected. Reconnect to continue.'; });
      client.on('message_ack', (message, ack) => {
        if (message.id?.fromMe) remember(receipts, message.id._serialized, { status: ackStatus(ack), error: ack < 0 ? 'WhatsApp reported delivery failure.' : null });
      });
      await client.initialize();
    })().catch(err => {
      phase = 'error'; qr = null;
      error = 'WhatsApp could not start. Check the bridge log and browser dependencies, then retry.';
      console.error('WhatsApp initialization failed:', err.message);
    }).finally(() => { starting = null; });
  }

  async function send(body) {
    if (typeof body.phone !== 'string' || !/^\d{10,15}$/.test(body.phone) ||
        typeof body.message !== 'string' || !body.message.trim() || body.message.length > 4096) {
      throw Object.assign(new Error('A valid recipient and message (1–4096 characters) are required.'), { status: 400 });
    }
    if (typeof body.request_id !== 'string' || !/^[a-zA-Z0-9_-]{8,120}$/.test(body.request_id)) {
      throw Object.assign(new Error('A send request ID is required.'), { status: 400 });
    }
    const signature = JSON.stringify([body.phone, body.message]);
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
      const sent = await client.sendMessage(recipient._serialized, body.message, { sendSeen: false });
      if (!sent?.id?._serialized) throw new Error('WhatsApp did not confirm message submission. Check the chat before retrying.');
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
        if (Buffer.byteLength(raw) > 32768) throw Object.assign(new Error('Request too large.'), { status: 413 });
      }
      let body;
      try { body = JSON.parse(raw || '{}'); } catch (_) { throw Object.assign(new Error('Invalid JSON.'), { status: 400 }); }
      if (!body || typeof body !== 'object') throw Object.assign(new Error('Invalid request.'), { status: 400 });
      if (url.pathname === '/connect') { connect(); reply(200, snapshot()); return; }
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
