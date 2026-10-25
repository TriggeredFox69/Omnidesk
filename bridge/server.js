'use strict';
const fs = require('node:fs');
const path = require('node:path');
const { randomBytes } = require('node:crypto');
const { Client, LocalAuth } = require('whatsapp-web.js');
const QRCode = require('qrcode');
const { createService } = require('./service');
const dataPath = path.resolve(__dirname, '../data/whatsapp-bridge');
fs.mkdirSync(dataPath, { recursive: true, mode: 0o700 });
const tokenPath = path.join(dataPath, 'token');
if (!process.env.WHATSAPP_BRIDGE_TOKEN && !fs.existsSync(tokenPath)) {
  fs.writeFileSync(tokenPath, randomBytes(32).toString('hex'), { mode: 0o600, flag: 'wx' });
}
const service = createService({
  token: process.env.WHATSAPP_BRIDGE_TOKEN || fs.readFileSync(tokenPath, 'utf8').trim(),
  qrImage: value => QRCode.toDataURL(value, { width: 280, margin: 2 }),
  createClient: () => new Client({
    authStrategy: new LocalAuth({ dataPath: path.join(dataPath, 'auth') }),
    webVersionCache: { type: 'local', path: path.join(dataPath, 'cache') },
    deviceName: 'Nunno Campaigns',
    puppeteer: { headless: true,
      ...(process.env.WHATSAPP_BROWSER_PATH ? { executablePath: process.env.WHATSAPP_BROWSER_PATH } : {}) }
  })
});
const port = Number(process.env.WHATSAPP_BRIDGE_PORT || 3210);
service.server.listen(port, process.env.WHATSAPP_BRIDGE_HOST || '127.0.0.1', () => {
  console.log(`Nunno WhatsApp bridge listening on port ${port}. Open Connect WhatsApp in the app to link your phone.`);
  // Restore a saved session on startup; a fresh install waits for Connect in the app.
  if (fs.existsSync(path.join(dataPath, 'auth', 'session'))) service.connect();
});
for (const signal of ['SIGINT', 'SIGTERM']) process.on(signal, async () => { await service.close(); process.exit(0); });
