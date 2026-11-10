'use strict';
// Local-only adapter: serves the UI and a single-user API without PHP or cPanel.
const http = require('node:http');
const fs = require('node:fs');
const path = require('node:path');
const { randomBytes } = require('node:crypto');
const { spawn } = require('node:child_process');
const { createService } = require('./service');
const root = path.resolve(__dirname, '..');

async function startLocal({ createClient, qrImage, port = 8080, openBrowser = true, bridgePort = 0, bridgeToken } = {}) {
  let restoreSession = false;
  if (!createClient) {
    const QRCode = require('qrcode');
    const { BaileysClient } = require('./baileys-client');
    const dataPath = path.join(root, 'data/whatsapp-bridge/local');
    fs.mkdirSync(dataPath, { recursive: true, mode: 0o700 });
    const authPath = path.join(dataPath, 'baileys_auth');
    restoreSession = fs.existsSync(path.join(authPath, 'creds.json'));
    createClient = () => new BaileysClient({ authPath, deviceName: 'OmniDesk' });
    qrImage = value => QRCode.toDataURL(value, { width: 280, margin: 2 });
  }
  const token = bridgeToken || randomBytes(32).toString('hex');
  const session = randomBytes(32).toString('hex');
  const bridge = createService({ createClient, qrImage, token,
    storageFile: openBrowser === false && port === 0 ? undefined : path.join(root, 'data/whatsapp-bridge/local/inbox.json') });
  await new Promise((resolve, reject) => {
    bridge.server.once('error', reject);
    bridge.server.listen(bridgePort, '127.0.0.1', resolve);
  });
  if (restoreSession) bridge.connect();
  const bridgeUrl = `http://127.0.0.1:${bridge.server.address().port}`;
  const usersPath = path.join(root, 'data/users.json');
  function loadLocalUsers() {
    try {
      if (fs.existsSync(usersPath)) {
        const parsed = JSON.parse(fs.readFileSync(usersPath, 'utf8'));
        if (Array.isArray(parsed) && parsed.length) return parsed;
      }
    } catch (_) {}
    return [
      { id: 'USR-ADMIN-01', name: 'System Administrator', username: 'admin', role: 'admin', designation: 'Admin', gender: 'male', status: 'active' },
      { id: 'EMP-TL-01', name: 'Ali Raza (Team Lead)', username: 'ali', role: 'lead', designation: 'Male Team Lead', gender: 'male', status: 'active' },
      { id: 'EMP-TL-02', name: 'Asima Noor (Team Lead)', username: 'asima', role: 'lead', designation: 'Female Team Lead', gender: 'female', status: 'active' },
      { id: 'EMP-101', name: 'Hamza Khan', username: 'hamza', role: 'member', designation: 'Closer Specialist', gender: 'male', status: 'active' }
    ];
  }
  let currentLocalUser = null;
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
          const allUsers = loadLocalUsers();
          if (action === 'check') {
            const userToReturn = currentLocalUser || allUsers[0];
            json(200, { success: true, authenticated: true, user: userToReturn });
            return;
          }
          if (action === 'login') {
            const username = String(body.username || '').trim().toLowerCase();
            const password = String(body.password || '').trim();
            const matched = allUsers.find(u => u.username.toLowerCase() === username || (u.email && u.email.toLowerCase() === username));
            let isValid = false;
            if (matched) {
              if (matched.username === 'admin' && (password === 'admin123' || password === 'Admin@Omnidesk2026!')) isValid = true;
              else if (matched.username === 'ali' && (password === 'ali123' || password === 'Lead@123')) isValid = true;
              else if (matched.username === 'asima' && (password === 'asima123' || password === 'Lead@123')) isValid = true;
              else if (matched.username === 'hamza' && (password === 'closer123' || password === 'Agent@123')) isValid = true;
              else if (matched.password && matched.password === password) isValid = true;
            }
            if (matched && isValid) {
              currentLocalUser = matched;
              json(200, { success: true, authenticated: true, user: matched });
            } else {
              json(401, { success: false, error: 'Invalid username or password.' });
            }
            return;
          }
          if (action === 'logout') {
            currentLocalUser = null;
            json(200, { success: true });
            return;
          }
          if (action === 'list_employees') {
            json(200, { success: true, employees: allUsers });
            return;
          }
          if (action === 'create_employee') {
            const newEmp = {
              id: 'EMP-' + Date.now().toString(36).toUpperCase(),
              name: body.name || 'New Employee',
              username: body.username || 'emp_' + Date.now().toString(36),
              role: body.role || 'member',
              designation: body.designation || 'Closer Specialist',
              gender: body.gender || 'male',
              phone: body.phone || '',
              status: 'active'
            };
            allUsers.push(newEmp);
            try { fs.writeFileSync(usersPath, JSON.stringify(allUsers, null, 2), 'utf8'); } catch (_) {}
            json(200, { success: true, employee: newEmp });
            return;
          }
          if (action === 'toggle_status') {
            const target = allUsers.find(u => u.id === body.id);
            if (target) {
              target.status = target.status === 'active' ? 'suspended' : 'active';
              try { fs.writeFileSync(usersPath, JSON.stringify(allUsers, null, 2), 'utf8'); } catch (_) {}
              json(200, { success: true, message: `Status updated to ${target.status}` });
            } else {
              json(404, { success: false, error: 'Employee not found' });
            }
            return;
          }
          if (action === 'delete_employee') {
            const filtered = allUsers.filter(u => u.id !== body.id);
            try { fs.writeFileSync(usersPath, JSON.stringify(filtered, null, 2), 'utf8'); } catch (_) {}
            json(200, { success: true, message: 'Employee removed' });
            return;
          }
        }
        if (url.pathname === '/api/leads_sync.php') {
          if (action === 'get_leads') { json(200, { success: true, leads: [...leads.values()] }); return; }
          if (action === 'delete_leads' || action === 'delete_lead') {
            const ids = Array.isArray(body.ids) ? body.ids : (body.id ? [body.id] : []);
            ids.forEach(id => leads.delete(id));
            const inboxFile = path.join(root, 'data/whatsapp-bridge/local/inbox.json');
            try {
              if (fs.existsSync(inboxFile)) {
                const inbox = JSON.parse(fs.readFileSync(inboxFile, 'utf8') || '[]');
                const phonesToDelete = ids.map(id => String(id).replace(/\D/g, ''));
                const newInbox = inbox.filter(m => {
                  const s = String(m.from || m.sender || '').replace(/\D/g, '');
                  return !phonesToDelete.includes(s);
                });
                fs.writeFileSync(inboxFile, JSON.stringify(newInbox, null, 2), 'utf8');
              }
            } catch (_) {}
            json(200, { success: true, deleted_count: ids.length, remaining_count: leads.size });
            return;
          }
          if (action === 'update_lead' && req.method === 'POST') {
            const lead = { ...(leads.get(body.id) || { messages: [] }), ...body };
            if (body.newMessage && !lead.messages.some(m => m.id === body.newMessage.id)) lead.messages.push(body.newMessage);
            if (body.updateMessage) lead.messages = lead.messages.map(m => m.waMessageId === body.updateMessage.waMessageId ? { ...m, ...body.updateMessage } : m);
            leads.set(body.id, lead); json(200, { success: true }); return;
          }
        }
        if (url.pathname === '/api/withdrawals.php') {
          const wdFile = path.join(root, 'data/withdrawals.json');
          const loadWds = () => {
            try {
              if (fs.existsSync(wdFile)) return JSON.parse(fs.readFileSync(wdFile, 'utf8') || '[]');
            } catch (_) {}
            return [];
          };
          const saveWds = (arr) => {
            try {
              fs.writeFileSync(wdFile, JSON.stringify(arr, null, 2), 'utf8');
            } catch (_) {}
          };

          if (action === 'get_withdrawals') {
            const list = loadWds();
            const targetUser = url.searchParams.get('user_id') || body.user_id;
            const isAdmin = currentLocalUser && currentLocalUser.role === 'admin';
            const resList = (!isAdmin && targetUser) ? list.filter(w => w.userId === targetUser) : list;
            json(200, { success: true, withdrawals: resList });
            return;
          }
          if (action === 'request_withdrawal' && req.method === 'POST') {
            const list = loadWds();
            const newWd = {
              id: 'WD-' + Date.now().toString().slice(-5) + Math.floor(Math.random() * 90 + 10),
              userId: body.userId || (currentLocalUser ? currentLocalUser.id : 'EMP-TL-01'),
              userName: body.userName || (currentLocalUser ? currentLocalUser.name : 'Team Lead'),
              userRole: body.userRole || (currentLocalUser ? currentLocalUser.role : 'lead'),
              amount: Number(body.amount) || 0,
              method: body.method || 'EasyPaisa',
              accountTitle: body.accountTitle || '',
              accountNumber: body.accountNumber || '',
              note: body.note || '',
              status: 'pending',
              created_at: new Date().toISOString()
            };
            list.unshift(newWd);
            saveWds(list);
            json(200, { success: true, withdrawal: newWd, withdrawals: list });
            return;
          }
          if (action === 'update_status' && req.method === 'POST') {
            const list = loadWds();
            const item = list.find(w => w.id === body.id);
            if (item) {
              item.status = body.status;
              if (body.txRef) item.txRef = body.txRef;
              item.updated_at = new Date().toISOString();
              saveWds(list);
              json(200, { success: true, withdrawal: item, withdrawals: list });
            } else {
              json(404, { success: false, error: 'Withdrawal not found' });
            }
            return;
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
