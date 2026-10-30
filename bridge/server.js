'use strict';
const fs = require('node:fs');
const path = require('node:path');
const { randomBytes } = require('node:crypto');
let QRCode = null;
try { QRCode = require('qrcode'); } catch (_) {}
const { createService } = require('./service');
const { BaileysClient } = require('./baileys-client');

const dataPath = process.env.WHATSAPP_DATA_DIR ||
  (fs.existsSync(path.resolve(__dirname, '../data'))
    ? path.resolve(__dirname, '../data/whatsapp-bridge')
    : path.resolve(__dirname, '../../data/whatsapp-bridge'));
fs.mkdirSync(dataPath, { recursive: true, mode: 0o700 });
const MASTER_TOKEN = 'c8e4acd8219dafce2a74e37954fa4a12c3fd1256bff8fcb518b7f137b4f9af20';
const tokenPath = path.join(dataPath, 'token');
if (!process.env.WHATSAPP_BRIDGE_TOKEN && !fs.existsSync(tokenPath)) {
  fs.writeFileSync(tokenPath, MASTER_TOKEN, { mode: 0o600 });
}

// ⚡ Permanent Pure WebSocket WhatsApp engine (Baileys) — Zero Chromium required, cPanel 24/7 native
console.log('⚡ Using pure WebSocket WhatsApp engine (Baileys) — Zero Chromium required, cPanel 24/7 native.');
const createClientFn = () => new BaileysClient({
  authPath: path.join(dataPath, 'baileys_auth'),
  deviceName: 'Omnidesk cPanel 24/7'
});

const activeToken = process.env.WHATSAPP_BRIDGE_TOKEN ||
  (fs.existsSync(tokenPath) ? fs.readFileSync(tokenPath, 'utf8').trim() : MASTER_TOKEN);

const service = createService({
  storageFile: path.join(dataPath, 'inbox.json'),
  token: activeToken,
  qrImage: async value => {
    if (QRCode && typeof QRCode.toDataURL === 'function') {
      try { return await QRCode.toDataURL(value, { width: 280, margin: 2 }); } catch (_) {}
    }
    return `https://api.qrserver.com/v1/create-qr-code/?size=280x280&margin=2&data=${encodeURIComponent(value)}`;
  },
  createClient: createClientFn
});
const port = Number(process.env.PORT || process.env.WHATSAPP_BRIDGE_PORT || 3210);
const host = process.env.WHATSAPP_BRIDGE_HOST || (process.env.PORT ? '0.0.0.0' : '127.0.0.1');
service.server.listen(port, host, () => {
  console.log(`Nunno WhatsApp bridge listening on http://${host}:${port}.`);
  
  // Auto-detect cloud host public URL (Render, Railway, or manual PUBLIC_BRIDGE_URL)
  const publicUrl = process.env.PUBLIC_BRIDGE_URL ||
    process.env.RENDER_EXTERNAL_URL ||
    (process.env.RAILWAY_PUBLIC_DOMAIN ? `https://${process.env.RAILWAY_PUBLIC_DOMAIN}` : '');

  if (publicUrl) {
    const bridgeToken = process.env.WHATSAPP_BRIDGE_TOKEN || fs.readFileSync(tokenPath, 'utf8').trim();
    const omnideskTarget = process.env.OMNIDESK_API_URL || 'https://omnidesk.lalagpt.pk/api/direct_sender.php';
    console.log(`Auto-registering bridge (${publicUrl}) with Omnidesk: ${omnideskTarget}...`);
    (async () => {
      for (let attempt = 1; attempt <= 3; attempt++) {
        try {
          const r = await fetch(omnideskTarget, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
              action: 'register_bridge',
              url: publicUrl,
              token: bridgeToken,
              secret: 'omnidesk_live_bridge_2026'
            }),
            signal: AbortSignal.timeout(10000)
          });
          const res = await r.json();
          if (res.success) {
            console.log('✓ Successfully registered 24/7 cloud bridge with Omnidesk!');
            break;
          } else {
            console.warn(`Bridge registration attempt ${attempt}:`, res.message);
          }
        } catch (err) {
          console.warn(`Bridge registration attempt ${attempt} notice:`, err.message);
          await new Promise(res => setTimeout(res, 3000));
        }
      }
    })();
  }

  // Restore a saved session on startup; a fresh install waits for Connect in the app.
  const hasSavedSession = fs.existsSync(path.join(dataPath, 'auth', 'session')) ||
    fs.existsSync(path.join(dataPath, 'baileys_auth', 'creds.json'));
  if (hasSavedSession) service.connect();
});
for (const signal of ['SIGINT', 'SIGTERM']) process.on(signal, async () => { await service.close(); process.exit(0); });
