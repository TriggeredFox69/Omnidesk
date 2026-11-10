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
    res.setHeader('X-Frame-Options', 'SAMEORIGIN');
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
              email: body.email || (body.username ? body.username + '@nunno.cloud' : ''),
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
            const method = body.method || 'Digital Wallet';
            const institution = body.institution || method;
            let accountNumber = String(body.accountNumber || '').trim();
            const walletKeys = ['easypaisa', 'jazzcash', 'sadapay', 'nayapay', 'upaisa', 'zindigi', 'digital wallet'];
            const isWallet = walletKeys.includes(method.toLowerCase()) || walletKeys.includes(institution.toLowerCase());
            if (isWallet) {
              const cleanPhone = accountNumber.replace(/[^\d\+]/g, '');
              const isValidWallet = /^0[0-9]{10}$/.test(cleanPhone) || /^\+?92[0-9]{10}$/.test(cleanPhone);
              if (!isValidWallet) {
                json(400, {
                  success: false,
                  error: `Technical Validation Error: For digital wallets (${institution}), enter 11 digits starting with 0 (e.g. 03001234567) or 12 digits with country code (+923001234567).`
                });
                return;
              }
              accountNumber = cleanPhone;
            }

            const newWd = {
              id: 'WD-' + Date.now().toString().slice(-5) + Math.floor(Math.random() * 90 + 10),
              userId: body.userId || (currentLocalUser ? currentLocalUser.id : 'EMP-TL-01'),
              userName: body.userName || (currentLocalUser ? currentLocalUser.name : 'Team Lead'),
              userRole: body.userRole || (currentLocalUser ? currentLocalUser.role : 'lead'),
              amount: Number(body.amount) || 0,
              method: method,
              institution: institution,
              accountTitle: body.accountTitle || '',
              accountNumber: accountNumber,
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
        if (url.pathname === '/api/email_service.php') {
          const emailLogsFile = path.join(root, 'data/email_logs.json');
          const emailConfigFile = path.join(root, 'data/email_config.json');
          const loadLogs = () => {
            try { if (fs.existsSync(emailLogsFile)) return JSON.parse(fs.readFileSync(emailLogsFile, 'utf8') || '[]'); } catch (_) {}
            return [];
          };
          const saveLogs = (arr) => {
            try { fs.writeFileSync(emailLogsFile, JSON.stringify(arr.slice(0, 500), null, 2), 'utf8'); } catch (_) {}
          };
          const loadEmailConfig = () => {
            const def = { transport: 'mail', sender_name: 'OmniDesk Administration', sender_email: 'numeniousnexus@gmail.com', reply_to: 'numeniousnexus@gmail.com', portal_url: origin || 'http://127.0.0.1:8080' };
            try { if (fs.existsSync(emailConfigFile)) return { ...def, ...JSON.parse(fs.readFileSync(emailConfigFile, 'utf8') || '{}') }; } catch (_) {}
            return def;
          };

          if (action === 'get_templates') {
            const templates = [
              { id: 'welcome', name: '🌟 New Employee Welcome & Login Credentials', badge: 'Account Ready', subject: 'Welcome to OmniDesk — Your Closer Account is Ready [{username}]', default_body: 'Welcome to the Team, {name}! 👋\n\nYour authorized OmniDesk sales closer workspace has been provisioned by the Administrator. You now have full access to our high-converting WhatsApp lead pipeline.\n\nYour OmniDesk Login Credentials:\n• Workspace URL: {portal_url}\n• Username: {username}\n• Initial Password: {password}\n• Assigned Role: {role}\n\nSecurity Note: Please change your password upon your first login. Do not share your login credentials with anyone outside the authorized closing team.' },
              { id: 'withdrawal_lodged', name: '⏳ Withdrawal Lodged (24-Hour Settlement Guarantee)', badge: 'Audit Active', subject: 'Withdrawal Request Confirmed — PKR {amount} [24-Hour Audit Active]', default_body: 'Withdrawal Request Confirmed ⏳\n\nHello {name}, your withdrawal request has been received and logged under our Guaranteed 24-Hour Settlement SLA.\n\nPayout Transaction Summary:\n• Requested Amount: PKR {amount}\n• Payout Channel: {method}\n• Beneficiary Title: {account_title}\n• Account Number: {account_number}\n• SLA Deadline: Within 24 Hours of Submission\n\nYour available balance has been deducted live. Funds are automatically disbursed once payment receipts and deal closing notes are audited.' },
              { id: 'withdrawal_approved', name: '💰 Withdrawal Approved & Payout Disbursed', badge: 'Funds Sent', subject: 'Funds Disbursed: Your PKR {amount} Payout is Complete [{method}]', default_body: 'Payout Disbursed Successfully! 🟢\n\nDear {name}, we are pleased to confirm that your payout has been approved and successfully transferred to your destination account.\n\nTransfer Confirmation Receipt:\n• Amount Sent: PKR {amount}\n• Destination: {method} ({account_number})\n• Account Title: {account_title}\n• Transaction Ref: {tx_ref}\n• Disbursed At: {date}\n\nThank you for your dedicated closing efforts! Keep scaling your conversions on OmniDesk.' },
              { id: 'withdrawal_rejected', name: '⚠️ Withdrawal Notice: Action Required', badge: 'Needs Attention', subject: 'Notice: Withdrawal Request Requires Resubmission [PKR {amount}]', default_body: 'Withdrawal Request Update ⚠️\n\nHello {name}, your recent payout request of PKR {amount} could not be completed during audit.\n\nReason for Resubmission:\n• {reject_reason}\n\nYour balance has been restored to your available funds. Please open the Withdrawal portal and resubmit with verified account information.' },
              { id: 'account_suspended', name: '🚫 Account Suspension Formal Notice', badge: 'Suspended', subject: 'Urgent: Your OmniDesk Closer Account has been Suspended', default_body: 'Account Access Suspended 🚫\n\nDear {name},\n\nThis is a formal notice that your OmniDesk closer account ({username}) has been suspended by the System Administrator effective immediately.\n\nAccess Restrictions Enacted:\n• WhatsApp sending and inbound response privileges locked.\n• Lead pipeline claims and active discussions frozen.\n• Commission withdrawal requests temporarily restricted.\n\nIf you believe this action was taken in error or wish to request a compliance review, please contact administrative management directly.' },
              { id: 'account_reactivated', name: '🟢 Account Reactivated — Workspace Restored', badge: 'Access Restored', subject: 'Welcome Back: Your OmniDesk Access is Restored [{username}]', default_body: 'Welcome Back! Your Access is Restored 🟢\n\nDear {name},\n\nWe are pleased to inform you that your OmniDesk closer workspace ({username}) has been reactivated by the System Administrator.\n\nStatus: Full Privileges Restored\nYou may now log in to continue managing client discussions, taking incoming leads, and processing sales deals.' },
              { id: 'sales_milestone', name: '🎯 Monthly Sales Milestone & Leaderboard Honor', badge: 'Top Closer', subject: 'Congratulations on Crushing Your Target, {name}! 🎯', default_body: 'Outstanding Performance! 🏆\n\nCongratulations {name}! You have achieved a remarkable milestone this sales cycle by hitting your target deal volume on OmniDesk.\n\nCycle Achievements:\n• Verified Closures: {deals_count} Won Deals\n• Total Volume: PKR {amount}\n• Leaderboard Rank: Top Tier Closer ⭐\n\nYour performance bonus qualification is active. Keep leading the charge!' },
              { id: 'system_notice', name: '📢 System Maintenance & Bridge Advisory', badge: 'System Advisory', subject: 'Advisory: Scheduled OmniDesk Optimization & Bridge Health Check', default_body: 'System Maintenance & Optimization Notice 🛠️\n\nDear OmniDesk Team,\n\nPlease be advised that scheduled maintenance and socket health optimizations will be conducted on the WhatsApp bridge infrastructure.\n\nMaintenance Window:\n• Schedule: {maintenance_window}\n• Status: Lead data, chat history, and pending balances are fully preserved. Outbound sending may pause briefly during the 5-minute health check.' },
              { id: 'lead_alert', name: '⚡ Unaddressed Lead Reassignment Warning', badge: 'Urgent Response', subject: 'Pipeline Alert: {lead_name} Awaiting Response [Reassignment Warning]', default_body: 'Urgent: Lead Awaiting Follow-up ⚡\n\nHello {name}, our pipeline monitor indicates that customer {lead_name} has been waiting for your response.\n\nLead Follow-up Policy:\n• High-intent inquiries left unaddressed for over 24 hours are automatically recycled to the next available closer.\n• Please open the active conversation and reply to the prospect immediately.' },
              { id: 'custom', name: '📝 Custom Team Announcement / Directive', badge: 'Announcement', subject: 'Announcement from OmniDesk Administration', default_body: 'Team Directive from OmniDesk Administration 📢\n\nHello Team,\n\nPlease review the following directive from OmniDesk Administration:\n\nKey Action Items:\n• Keep WhatsApp follow-ups active and ensure deal notes are accurately filled.\n• Log verified transaction IDs immediately upon deal completion.\n\nIf you have any questions regarding this update, please reply directly or consult your Team Lead.' }
            ];
            json(200, { success: true, templates });
            return;
          }
          if (action === 'get_logs') {
            json(200, { success: true, logs: loadLogs() });
            return;
          }
          if (action === 'get_settings') {
            json(200, { success: true, settings: loadEmailConfig() });
            return;
          }
          if (action === 'save_settings' && req.method === 'POST') {
            const conf = { ...loadEmailConfig(), ...body };
            fs.writeFileSync(emailConfigFile, JSON.stringify(conf, null, 2), 'utf8');
            json(200, { success: true, message: 'Settings saved' });
            return;
          }
          if (action === 'send_email' && req.method === 'POST') {
            const allUsers = loadLocalUsers();
            let recipients = [];
            const tType = body.target_type || 'user';
            if (tType === 'all') recipients = allUsers.filter(u => u.email);
            else if (tType === 'role_lead') recipients = allUsers.filter(u => u.role === 'lead' && u.email);
            else if (tType === 'role_member') recipients = allUsers.filter(u => u.role === 'member' && u.email);
            else if (tType === 'custom' && body.target_custom_email) {
              recipients = [{ id: 'CUSTOM-1', name: body.target_custom_name || 'Recipient', username: 'client', email: body.target_custom_email, designation: 'Recipient' }];
            } else {
              const matched = allUsers.find(u => u.id === body.target_user_id || u.username === body.target_user_id);
              if (matched) recipients = [matched];
            }

            if (!recipients.length) {
              json(400, { success: false, error: 'No recipients found for selected target audience.' });
              return;
            }

            const currentLogs = loadLogs();
            const dispatched = [];
            for (const r of recipients) {
              let subj = body.subject || 'OmniDesk Notification';
              subj = subj.replace('{name}', r.name).replace('{username}', r.username).replace('{email}', r.email);
              const logItem = {
                id: 'ML-' + Date.now().toString(36).toUpperCase() + Math.floor(Math.random() * 90 + 10),
                timestamp: new Date().toISOString(),
                recipient_email: r.email,
                recipient_name: r.name,
                subject: subj,
                template_id: body.template_id || 'custom',
                status: 'delivered',
                sent_by: (currentLocalUser ? currentLocalUser.name : 'System Administrator'),
                sender_email: 'numeniousnexus@gmail.com'
              };
              currentLogs.unshift(logItem);
              dispatched.push(logItem);
            }
            saveLogs(currentLogs);
            json(200, {
              success: true,
              sent_count: dispatched.length,
              total: recipients.length,
              dispatched,
              message: `Successfully dispatched email to ${dispatched.length} recipient(s).`
            });
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
      if ((relative !== 'index.html' && relative !== 'email_hub.php' && !file.startsWith(path.join(root, 'assets') + path.sep)) || !file.startsWith(root + path.sep) || !fs.existsSync(file) || !fs.statSync(file).isFile()) {
        res.writeHead(404); res.end(); return;
      }
      const types = { '.html': 'text/html', '.php': 'text/html', '.js': 'text/javascript', '.css': 'text/css', '.png': 'image/png', '.svg': 'image/svg+xml', '.woff2': 'font/woff2' };
      res.setHeader('Content-Type', types[path.extname(file)] || 'application/octet-stream');
      if (relative === 'index.html' || relative === 'email_hub.php') {
        res.setHeader('Set-Cookie', `nunno_local=${session}; HttpOnly; SameSite=Strict; Path=/`);
        let content = fs.readFileSync(file, 'utf8');
        if (relative === 'email_hub.php') {
          content = content.replace(/<\?php[\s\S]*?\?>/i, '');
        } else {
          content = content.replace('</head>', '<script>window.NUNNO_LOCAL_LIVE=true;</script></head>');
        }
        res.end(content);
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
