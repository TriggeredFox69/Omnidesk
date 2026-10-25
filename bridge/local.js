'use strict';
// Local-only adapter: serves the UI and a single-user API without PHP or cPanel.
const http = require('node:http');
const fs = require('node:fs');
const path = require('node:path');
const { randomBytes } = require('node:crypto');
const { spawn } = require('node:child_process');
const { createService } = require('./service');
const root = path.resolve(__dirname, '..');

async function startLocal({ createClient, qrImage, port = 8080, openBrowser = true } = {}) {
  let restoreSession = false;
  if (!createClient) {
    const { Client, LocalAuth } = require('whatsapp-web.js');
    const QRCode = require('qrcode');
    const dataPath = path.join(root, 'data/whatsapp-bridge/local');
    fs.mkdirSync(dataPath, { recursive: true, mode: 0o700 });
    restoreSession = fs.existsSync(path.join(dataPath, 'auth', 'session'));
    createClient = () => new Client({ authStrategy: new LocalAuth({ dataPath: path.join(dataPath, 'auth') }),
      webVersionCache: { type: 'local', path: path.join(dataPath, 'cache') }, deviceName: 'Nunno Local Test',
      puppeteer: { headless: true, ...(process.env.WHATSAPP_BROWSER_PATH ? { executablePath: process.env.WHATSAPP_BROWSER_PATH } : {}) } });
    qrImage = value => QRCode.toDataURL(value, { width: 280, margin: 2 });
  }
  const token = randomBytes(32).toString('hex');
  const session = randomBytes(32).toString('hex');
  const bridge = createService({ createClient, qrImage, token,
    storageFile: openBrowser === false && port === 0 ? undefined : path.join(root, 'data/whatsapp-bridge/local/inbox.json') });
  await new Promise(resolve => bridge.server.listen(0, '127.0.0.1', resolve));
  if (restoreSession) bridge.connect();
  const bridgeUrl = `http://127.0.0.1:${bridge.server.address().port}`;
  const user = { id: 'local-admin', name: 'Local Tester', username: 'local', role: 'admin' };
  const leads = new Map();
  let origin;
  const server = http.createServer(async (req, res) => {
    res.setHeader('Cache-Control', 'no-store');
    res.setHeader('X-Content-Type-Options', 'nosniff');
    res.setHeader('X-Frame-Options', 'DENY');
    const json = (status, body) => { res.writeHead(status, { 'Content-Type': 'application/json' }); res.end(JSON.stringify(body)); };
    if (req.headers.host !== new URL(origin).host) { json(403, { success: false }); return; }
    try {
      const url = new URL(req.url, origin);
      if (url.pathname.startsWith('/api/')) {
        if (!(req.headers.cookie || '').split(';').some(c => c.trim() === `nunno_local=${session}`)) { json(401, { success: false }); return; }
        if (req.headers.origin && req.headers.origin !== origin) { json(403, { success: false }); return; }
        if (!['GET', 'POST'].includes(req.method)) { json(405, { success: false }); return; }
        let body = {};
        if (req.method === 'POST') {
          if (!req.headers['content-type']?.startsWith('application/json')) { json(415, { success: false }); return; }
          let raw = '';
          for await (const chunk of req) { raw += chunk; if (Buffer.byteLength(raw) > 15*1024*1024) { json(413, { success: false }); return; } }
          body = JSON.parse(raw || '{}');
        }
        const action = body.action || url.searchParams.get('action');
        if (url.pathname === '/api/auth.php') {
          if (['check', 'login'].includes(action)) { json(200, { success: true, authenticated: true, user }); return; }
          if (action === 'list_employees') { json(200, { success: true, employees: [] }); return; }
          if (action === 'logout') { json(200, { success: true }); return; }
        }
        if (url.pathname === '/api/leads_sync.php') {
          if (action === 'get_leads') { json(200, { success: true, leads: [...leads.values()] }); return; }
          if (action === 'update_lead' && req.method === 'POST') {
            const lead = { ...(leads.get(body.id) || { messages: [] }), ...body };
            if (body.newMessage && !lead.messages.some(m => m.id === body.newMessage.id)) lead.messages.push(body.newMessage);
            if (body.updateMessage) lead.messages = lead.messages.map(m => m.waMessageId === body.updateMessage.waMessageId ? { ...m, ...body.updateMessage } : m);
            leads.set(body.id, lead); json(200, { success: true }); return;
          }
        }
        if (url.pathname === '/api/direct_sender.php') {
          const routes = { get_sender_info: '/status', connect_whatsapp: '/connect', send_direct: '/send',
            get_events: '/events?after=' + encodeURIComponent(body.after || 0),
            get_media: '/media?id=' + encodeURIComponent(body.message_id || ''), sync_chat: '/sync-chat',
            get_presence: '/presence?phone=' + encodeURIComponent(body.phone || ''),
            get_send_status: '/receipt?id=' + encodeURIComponent(body.message_id || '') };
          if (routes[action]) {
            const post = ['connect_whatsapp', 'send_direct', 'sync_chat'].includes(action);
            if (post && req.method !== 'POST') { json(405, { success: false }); return; }
            if (action === 'send_direct') {
              body.phone = String(body.phone || '').replace(/\D/g, '');
              if (/^0092/.test(body.phone)) body.phone = body.phone.slice(2);
              else if (/^03\d{9}$/.test(body.phone)) body.phone = '92' + body.phone.slice(1);
              else if (/^3\d{9}$/.test(body.phone)) body.phone = '92' + body.phone;
            }
            const upstream = await fetch(bridgeUrl + routes[action], { method: post ? 'POST' : 'GET',
              headers: { Authorization: `Bearer ${token}`, 'Content-Type': 'application/json' },
              ...(post ? { body: JSON.stringify(body) } : {}) });
            json(upstream.status, await upstream.json()); return;
          }
        }
        json(400, { success: false, message: 'This local launcher supports campaign testing; employee administration is unavailable.' }); return;
      }
      const pathname = decodeURIComponent(url.pathname);
      const relative = pathname === '/' || pathname === '/index.html' ? 'index.html' : pathname.slice(1);
      const file = path.resolve(root, relative);
      if ((relative !== 'index.html' && !file.startsWith(path.join(root, 'assets') + path.sep)) || !file.startsWith(root + path.sep) || !fs.existsSync(file) || !fs.statSync(file).isFile()) {
        res.writeHead(404); res.end(); return;
      }
      const types = { '.html': 'text/html', '.js': 'text/javascript', '.css': 'text/css', '.png': 'image/png', '.svg': 'image/svg+xml', '.woff2': 'font/woff2' };
      res.setHeader('Content-Type', types[path.extname(file)] || 'application/octet-stream');
      if (relative === 'index.html') {
        res.setHeader('Set-Cookie', `nunno_local=${session}; HttpOnly; SameSite=Strict; Path=/`);
        res.end(fs.readFileSync(file, 'utf8').replace('</head>', '<script>window.NUNNO_LOCAL_LIVE=true;</script></head>'));
      } else res.end(fs.readFileSync(file));
    } catch (_) { if (!res.headersSent) json(500, { success: false, message: 'Local request failed. Check the terminal and reconnect.' }); }
  });
  await new Promise((resolve, reject) => { server.once('error', reject); server.listen(port, '127.0.0.1', resolve); }).catch(async error => { await bridge.close(); throw error; });
  origin = `http://127.0.0.1:${server.address().port}`;
  console.log(`Local WhatsApp: ${origin} — real sends after QR linking. Keep this terminal open.`);
  if (openBrowser && process.platform === 'win32') spawn('explorer.exe', [origin], { windowsHide: true, stdio: 'ignore' }).unref();
  return { origin, close: async () => { server.closeAllConnections(); await new Promise(resolve => server.close(resolve)); await bridge.close(); } };
}
if (require.main === module) startLocal({ port: Number(process.env.NUNNO_LOCAL_PORT || 8080) }).then(app => {
  for (const signal of ['SIGINT', 'SIGTERM']) process.on(signal, async () => { await app.close(); process.exit(0); });
}).catch(error => { console.error(error.message); process.exitCode = 1; });
module.exports = { startLocal };
