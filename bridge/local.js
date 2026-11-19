'use strict';
// Local-only adapter: serves the WhatsApp Web Landing Page, Executive Admin, Super Admin, and UI API without PHP.
const http = require('node:http');
const fs = require('node:fs');
const path = require('node:path');
const { randomBytes } = require('node:crypto');
const { spawn } = require('node:child_process');
const { createService } = require('./service');
const root = path.resolve(__dirname, '..');

function templateLoginPhp(content) {
  let rendered = content.replace(/<\?php[\s\S]*?\?>/, '');
  rendered = rendered.replace(/<\?php[\s\S]*?\?>/g, '');
  return rendered;
}

function templateAdminPhp(content, currentUser, targetCompId = null, isPortal = false) {
  const isAdmin = currentUser && (currentUser.role === 'admin' || currentUser.role === 'superadmin') && isPortal;
  let rendered = content;
  if (!isAdmin) {
    rendered = rendered.replace(/<\?php if \(!\$isAdmin\): \?>([\s\S]*?)<\?php else: \?>([\s\S]*?)<\?php endif; \?>/, '$1');
  } else {
    rendered = rendered.replace(/<\?php if \(!\$isAdmin\): \?>([\s\S]*?)<\?php else: \?>([\s\S]*?)<\?php endif; \?>/, '$2');
  }
  let compName = 'Nunno Official';
  let compPlan = 'Enterprise Billion';
  let compLogo = 'assets/images/nunno-logo.png';
  const compId = targetCompId || currentUser?.company_id || 'COMP-001';
  try {
    const comps = JSON.parse(fs.readFileSync(path.join(root, 'data/companies.json'), 'utf8') || '[]');
    const match = comps.find(c => c.id === compId || c.slug === compId);
    if (match) {
      compName = match.name || compName;
      compPlan = match.plan || compPlan;
      compLogo = match.logo || compLogo;
    }
  } catch (_) {}
  rendered = rendered.replace(/<\?php echo \$isAdmin \? \('Chatly Company Admin — ' \. htmlspecialchars\(\$activeCompany\['name'\]\)\) : 'Chatly — Company Administrator Portal'; \?>/g, isAdmin ? ('Chatly Company Admin — ' + compName) : 'Chatly — Company Administrator Portal');
  rendered = rendered.replace(/<\?php echo htmlspecialchars\(\$activeCompany\['name'\]\); \?>/g, compName);
  rendered = rendered.replace(/<\?php echo htmlspecialchars\(\$activeCompany\['plan'\] \?\? 'Enterprise Pro'\); \?>/g, compPlan);
  rendered = rendered.replace(/<\?php echo htmlspecialchars\(\$activeCompany\['logo'\] \?\? '[^']*'\); \?>/g, compLogo);
  rendered = rendered.replace(/<\?php echo htmlspecialchars\(\$activeCompany\['fallback_phone'\] \?\? '\+923318036576'\); \?>/g, '+923318036576');
  rendered = rendered.replace(/<\?php echo htmlspecialchars\(\$activeCompany\['id'\]\); \?>/g, compId);
  rendered = rendered.replace(/<\?php echo htmlspecialchars\(\$currentUser\['name'\] \?\? 'Admin'\); \?>/g, currentUser?.name || 'System Administrator');
  rendered = rendered.replace(/<\?php echo urlencode\(\$activeCompany\['id'\]\); \?>/g, compId);
  rendered = rendered.replace(/<\?php echo \$activeCompany\['id'\]; \?>/g, compId);
  rendered = rendered.replace(/<\?php[\s\S]*?\?>/g, '');
  return rendered;
}

function templateSadminPhp(content, currentUser, isPortal = false) {
  const isSuperAdmin = currentUser && currentUser.role === 'superadmin' && isPortal;
  let rendered = content;
  if (!isSuperAdmin) {
    rendered = rendered.replace(/<\?php if \(!\$isSuperAdmin\): \?>([\s\S]*?)<\?php else: \?>([\s\S]*?)<\?php endif; \?>/, '$1');
  } else {
    rendered = rendered.replace(/<\?php if \(!\$isSuperAdmin\): \?>([\s\S]*?)<\?php else: \?>([\s\S]*?)<\?php endif; \?>/, '$2');
  }
  rendered = rendered.replace(/<\?php[\s\S]*?\?>/g, '');
  return rendered;
}

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
  bridge.connect();
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
      { id: 'USR-SADMIN-01', name: 'Master Administrator', username: 'sadmin', role: 'superadmin', designation: 'Platform Owner', gender: 'male', status: 'active', company_id: 'GLOBAL' },
      { id: 'USR-ADMIN-01', name: 'System Administrator', username: 'admin', role: 'admin', designation: 'Admin', gender: 'male', status: 'active', company_id: 'COMP-001' },
      { id: 'USR-ADMIN-04', name: 'Zeeshan (Metalink)', username: 'metalink', role: 'admin', designation: 'Company Admin', gender: 'male', status: 'active', company_id: 'COMP-004', password: 'Zeeshan123' },
      { id: 'USR-ADMIN-04-ALT', name: 'Zeeshan (Metalink)', username: 'zeeshan', role: 'admin', designation: 'Company Admin', gender: 'male', status: 'active', company_id: 'COMP-004', password: 'Zeeshan123' },
      { id: 'EMP-TL-01', name: 'Ali Raza (Team Lead)', username: 'ali', role: 'lead', designation: 'Male Team Lead', gender: 'male', status: 'active', company_id: 'COMP-001', tl_commission_balance: 1000 },
      { id: 'EMP-TL-02', name: 'Asima Noor (Team Lead)', username: 'asima', role: 'lead', designation: 'Female Team Lead', gender: 'female', status: 'active', company_id: 'COMP-001', tl_commission_balance: 500 },
      { id: 'EMP-101', name: 'Hamza Khan', username: 'hamza', role: 'member', designation: 'Closer Specialist', gender: 'male', status: 'active', company_id: 'COMP-001', message_limit: 150, messages_sent_today: 12 }
    ];
  }

  function saveLocalUsers(users) {
    try {
      fs.writeFileSync(usersPath, JSON.stringify(users, null, 2), 'utf8');
    } catch (_) {}
  }

  let currentLocalUser = null;
  const leads = new Map();

  function saveLeadsDisk() {
    try {
      const arr = [...leads.values()];
      fs.writeFileSync(path.join(root, 'data/leads.json'), JSON.stringify(arr, null, 2), 'utf8');
      fs.writeFileSync(path.join(root, 'data/tenants/COMP-001/leads.json'), JSON.stringify(arr, null, 2), 'utf8');
    } catch (_) {}
  }

  // Load existing leads from data/leads.json or data/tenants/COMP-001/leads.json
  try {
    const leadsFile = path.join(root, 'data/leads.json');
    const tenantLeadsFile = path.join(root, 'data/tenants/COMP-001/leads.json');
    let loaded = null;
    if (fs.existsSync(leadsFile)) {
      loaded = JSON.parse(fs.readFileSync(leadsFile, 'utf8') || '[]');
    } else if (fs.existsSync(tenantLeadsFile)) {
      loaded = JSON.parse(fs.readFileSync(tenantLeadsFile, 'utf8') || '[]');
    }
    if (Array.isArray(loaded)) {
      loaded.forEach(l => { if (l && l.id) leads.set(l.id, l); });
    }
  } catch (_) {}

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
        // Loopback security check: Host header must match origin
        if (req.headers.origin && req.headers.origin !== origin) { json(403, { success: false }); return; }
        if (!['GET', 'POST'].includes(req.method)) { json(405, { success: false }); return; }
        let body = {};
        if (req.method === 'POST') {
          let raw = '';
          for await (const chunk of req) { raw += chunk; if (Buffer.byteLength(raw) > 15*1024*1024) { json(413, { success: false }); return; } }
          if (raw && raw.trim()) {
            try { body = JSON.parse(raw); } catch (_) { body = {}; }
          }
        }
        const action = body.action || url.searchParams.get('action');

        // 1. Authentication API
        if (url.pathname === '/api/auth.php') {
          const allUsers = loadLocalUsers();
          if (action === 'check') {
            if (currentLocalUser) {
              json(200, { success: true, authenticated: true, user: currentLocalUser });
            } else {
              json(200, { success: false, authenticated: false, user: null });
            }
            return;
          }
          if (action === 'check_phone') {
            const phone = String(body.phone || url.searchParams.get('phone') || '').replace(/\D/g, '');
            const matched = allUsers.find(u => {
              const uPhone = String(u.phone || '').replace(/\D/g, '');
              return uPhone && (uPhone === phone || (uPhone.length >= 9 && phone.length >= 9 && uPhone.slice(-9) === phone.slice(-9)));
            });
            if (matched) {
              json(200, {
                success: true,
                exists: true,
                user: {
                  id: matched.id,
                  name: matched.name,
                  username: matched.username,
                  phone: matched.phone,
                  status: matched.status || 'active',
                  company_id: matched.company_id || 'COMP-001'
                }
              });
            } else {
              json(200, { success: true, exists: false });
            }
            return;
          }
          if (action === 'tm_register') {
            const username = String(body.username || '').trim().toLowerCase();
            const password = String(body.password || '').trim();
            const name = String(body.name || body.full_name || '').trim();
            const phone = String(body.phone || '').trim();
            const cleanPhone = phone.replace(/\D/g, '');
            const email = body.email || (username + '@closer.cloud');
            const company_id = body.company_id || 'COMP-001';
            const gender = body.gender || 'male';
            const city = body.city || 'Pakistan';
            const pfp = body.pfp || 'https://images.unsplash.com/photo-1535713875002-d1d0cf377fde?auto=format&fit=crop&w=120&q=80';

            if (!username || !password || !name || !phone) {
              json(400, { success: false, error: 'Username, Password, Full Name, and WhatsApp Phone are required.' });
              return;
            }

            const existingU = allUsers.find(u => u.username.toLowerCase() === username);
            if (existingU) {
              json(409, { success: false, error: `Username "@${username}" is already taken. Please choose another.` });
              return;
            }

            const existingPhone = allUsers.find(u => {
              const uPhone = String(u.phone || '').replace(/\D/g, '');
              return uPhone && (uPhone === cleanPhone || (uPhone.length >= 9 && cleanPhone.length >= 9 && uPhone.slice(-9) === cleanPhone.slice(-9)));
            });
            if (existingPhone) {
              json(409, { success: false, error: `This WhatsApp number (${phone}) is already registered to user @${existingPhone.username}.`, exists: true, username: existingPhone.username });
              return;
            }

            const newTM = {
              id: 'EMP-TM-' + Date.now().toString(36).toUpperCase(),
              name: name,
              username: username,
              email: email,
              password: password,
              role: 'member',
              designation: 'Closer Specialist (Applicant)',
              gender: gender,
              city: city,
              phone: phone,
              pfp: pfp,
              status: 'pending', // Awaiting manual Company Admin approval
              company_id: company_id,
              message_limit: 150,
              messages_sent_today: 0,
              applied_at: new Date().toISOString(),
              created_at: new Date().toISOString()
            };
            allUsers.push(newTM);
            saveLocalUsers(allUsers);
            json(200, { success: true, message: 'Application submitted successfully! Your account is pending manual approval by company administration.', user: newTM });
            return;
          }
          if (action === 'list_pending_tms') {
            const compId = url.searchParams.get('company_id') || (currentLocalUser?.role === 'superadmin' ? null : currentLocalUser?.company_id);
            const pending = allUsers.filter(u => u.status === 'pending' && (!compId || u.company_id === compId));
            json(200, { success: true, pending, count: pending.length });
            return;
          }
          if (action === 'approve_tm') {
            const userId = body.user_id || body.id;
            const target = allUsers.find(u => u.id === userId);
            if (target) {
              target.status = 'active';
              target.designation = 'Closer Specialist';
              target.message_limit = Number(body.message_limit) || 150;
              target.approved_at = new Date().toISOString();
              target.approved_by = currentLocalUser?.username || 'admin';
              saveLocalUsers(allUsers);
              json(200, { success: true, message: 'Closer applicant approved and activated successfully!' });
            } else {
              json(404, { success: false, error: 'Applicant not found.' });
            }
            return;
          }
          if (action === 'reject_tm') {
            const userId = body.user_id || body.id;
            const idx = allUsers.findIndex(u => u.id === userId);
            if (idx !== -1) {
              allUsers.splice(idx, 1);
              saveLocalUsers(allUsers);
              json(200, { success: true, message: 'Applicant rejected and removed.' });
            } else {
              json(404, { success: false, error: 'Applicant not found.' });
            }
            return;
          }
          if (action === 'list_logins') {
            const loginsFile = path.join(root, 'data/logins.json');
            let logs = [];
            try { logs = JSON.parse(fs.readFileSync(loginsFile, 'utf8') || '[]'); } catch (_) {}
            if (currentLocalUser && currentLocalUser.role !== 'superadmin') {
              const userComp = currentLocalUser.company_id || 'COMP-001';
              logs = logs.filter(l => l.company_id === userComp);
            }
            json(200, { success: true, logins: logs });
            return;
          }
          if (action === 'login') {
            const username = String(body.username || '').trim().toLowerCase();
            const password = String(body.password || '').trim();
            const matched = allUsers.find(u => u.username.toLowerCase() === username || (u.email && u.email.toLowerCase() === username));
            let isValid = false;
            if (matched) {
              if (matched.username === 'admin' && (password === 'admin123' || password === 'admin' || password === 'Admin@Omnidesk2026!')) isValid = true;
              else if ((matched.username === 'sadmin' || matched.role === 'superadmin') && (password === 'sadmin123' || password === 'sadmin' || password === 'SuperAdmin@2026!')) isValid = true;
              else if (matched.username === 'apex_admin' && (password === 'admin123' || password === 'admin' || password === 'apex123')) isValid = true;
              else if (matched.username === 'ali' && (password === 'ali123' || password === 'Lead@123')) isValid = true;
              else if (matched.username === 'asima' && (password === 'asima123' || password === 'Lead@123')) isValid = true;
              else if (matched.username === 'hamza' && (password === 'closer123' || password === 'Agent@123')) isValid = true;
              else if (matched.username === 'zain_closer' && (password === 'closer123' || password === 'zain123')) isValid = true;
              else if (['metalink', 'zeeshan'].includes(matched.username) && password === 'Zeeshan123') isValid = true;
              else if (matched.password && matched.password === password) isValid = true;
            }
            if (matched && isValid) {
              if (matched.status === 'pending') {
                json(403, {
                  success: false,
                  error: '⏳ Your account is pending manual approval by company administration. Please contact your company administrator or wait for approval.'
                });
                return;
              }
              if (matched.status === 'suspended') {
                json(403, {
                  success: false,
                  error: '🚫 Your account has been suspended by company administration.'
                });
                return;
              }
              currentLocalUser = matched;
              // Append to logins audit
              try {
                const loginsFile = path.join(root, 'data/logins.json');
                const logs = JSON.parse(fs.readFileSync(loginsFile, 'utf8') || '[]');
                logs.unshift({
                  id: 'LOG-' + Date.now().toString(36).toUpperCase(),
                  timestamp: new Date().toISOString().replace('T', ' ').slice(0, 19),
                  username: matched.username,
                  name: matched.name,
                  role: matched.role,
                  company_id: matched.company_id || 'COMP-001',
                  company_name: matched.company_id === 'GLOBAL' ? 'Platform HQ' : (matched.company_id || 'Nunno Official'),
                  ip: '127.0.0.1',
                  city: 'Localhost',
                  device: req.headers['user-agent'] ? req.headers['user-agent'].slice(0, 40) : 'Chrome / Local',
                  status: 'active'
                });
                fs.writeFileSync(loginsFile, JSON.stringify(logs.slice(0, 100), null, 2), 'utf8');
              } catch (_) {}
              json(200, { success: true, authenticated: true, user: matched, message: 'Logged in successfully.' });
            } else {
              json(401, { success: false, error: 'Invalid username or password.' });
            }
            return;
          }
          if (action === 'qr_login') {
            const phone = String(body.phone || '').replace(/\D/g, '');
            let closerUser = null;
            if (phone) {
              closerUser = allUsers.find(u => {
                const uPhone = String(u.phone || '').replace(/\D/g, '');
                return uPhone && (uPhone === phone || (uPhone.length >= 9 && phone.length >= 9 && uPhone.slice(-9) === phone.slice(-9)));
              });
            }
            if (!closerUser && body.demo) {
              closerUser = allUsers.find(u => u.role === 'member' && u.status === 'active') || allUsers.find(u => u.role === 'member');
            }
            if (!closerUser) {
              json(404, { success: false, error: 'No account linked to this WhatsApp number. Please complete registration.' });
              return;
            }
            if (closerUser.status === 'pending') {
              json(403, { success: false, error: 'Your account is pending company approval.', status: 'pending', user: closerUser });
              return;
            }
            currentLocalUser = closerUser;
            json(200, { success: true, message: 'Closer session activated via WhatsApp Web pairing.', user: closerUser });
            return;
          }
          if (action === 'tl_register') {
            const name = String(body.name || '').trim();
            const username = String(body.username || '').trim().toLowerCase();
            const password = String(body.password || '').trim();
            const phone = String(body.phone || '').trim();
            const email = body.email || (username + '@nunno.cloud');
            if (!name || !username || !password) {
              json(400, { success: false, error: 'Full Name, Username, and Password are required.' });
              return;
            }
            const existing = allUsers.find(u => u.username.toLowerCase() === username);
            if (existing) {
              json(409, { success: false, error: `Username "${username}" is already taken.` });
              return;
            }
            const newTL = {
              id: 'EMP-TL-' + Date.now().toString(36).toUpperCase(),
              name: name + ' (Team Lead)',
              username: username,
              email: email,
              password: password,
              role: 'lead',
              designation: 'Commission Team Lead',
              gender: 'male',
              phone: phone,
              status: 'active',
              company_id: 'COMP-001',
              tl_commission_balance: 0,
              message_limit: 500,
              messages_sent_today: 0,
              created_at: new Date().toISOString()
            };
            allUsers.push(newTL);
            saveLocalUsers(allUsers);
            currentLocalUser = newTL;
            json(200, { success: true, message: 'Team Leader account registered! Welcome to the commission portal.', user: newTL });
            return;
          }
          if (action === 'logout') {
            currentLocalUser = null;
            res.setHeader('Set-Cookie', 'nunno_local=; HttpOnly; SameSite=Strict; Path=/; Expires=Thu, 01 Jan 1970 00:00:00 GMT');
            json(200, { success: true, message: 'Logged out successfully.' });
            return;
          }
          if (action === 'direct_login' || action === 'impersonate') {
            const targetId = String(body.username || body.id || '').trim().toLowerCase();
            const target = allUsers.find(u => u.username.toLowerCase() === targetId || u.id === targetId);
            if (!target) {
              json(404, { success: false, error: 'Employee account not found.' });
              return;
            }
            const prevAdmin = currentLocalUser || allUsers.find(u => u.role === 'admin');
            target._impersonated_by = prevAdmin?.username || 'admin';
            currentLocalUser = target;
            json(200, { success: true, message: `Logged in directly as ${target.name}`, user: target });
            return;
          }
          if (action === 'return_to_admin') {
            const admin = allUsers.find(u => u.role === 'admin') || allUsers.find(u => u.role === 'superadmin');
            currentLocalUser = admin;
            json(200, { success: true, message: 'Restored administrator session.', user: admin });
            return;
          }
          if (action === 'list_employees') {
            const compId = url.searchParams.get('company_id') || currentLocalUser?.company_id || 'COMP-001';
            const closers = allUsers.filter(u => {
              if (u.role === 'superadmin' || u.company_id === 'GLOBAL') return false;
              if (u.role === 'admin') return false; // Admins are not closer employees
              const uComp = u.company_id || 'COMP-001';
              return uComp === compId;
            });
            json(200, { success: true, employees: closers });
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
            saveLocalUsers(allUsers);
            json(200, { success: true, employee: newEmp });
            return;
          }
          if (action === 'toggle_status') {
            const target = allUsers.find(u => u.id === body.id);
            if (target) {
              target.status = target.status === 'active' ? 'suspended' : 'active';
              saveLocalUsers(allUsers);
              json(200, { success: true, message: `Status updated to ${target.status}` });
            } else {
              json(404, { success: false, error: 'Employee not found' });
            }
            return;
          }
          if (action === 'delete_employee') {
            const filtered = allUsers.filter(u => u.id !== body.id);
            saveLocalUsers(filtered);
            json(200, { success: true, message: 'Employee removed' });
            return;
          }
          if (action === 'tl_create_tm') {
            const newEmp = {
              id: 'EMP-' + Date.now().toString(36).toUpperCase(),
              name: body.name || 'New Closer',
              username: (body.username || 'closer_' + Date.now().toString(36)).toLowerCase(),
              email: (body.username || 'closer') + '@nunno.cloud',
              role: 'member',
              designation: 'Team Member (Closer)',
              gender: body.gender || 'male',
              phone: body.phone || '',
              status: 'active',
              company_id: (currentLocalUser ? currentLocalUser.company_id : 'COMP-001') || 'COMP-001',
              tl_id: currentLocalUser ? currentLocalUser.id : 'EMP-TL-01',
              joined_via_tl: currentLocalUser ? currentLocalUser.id : 'EMP-TL-01',
              plan_status: 'pending_activation',
              plan_required: 1700,
              message_limit: 150,
              messages_sent_today: 0,
              quota_reset_date: new Date().toISOString().slice(0, 10),
              created_at: new Date().toISOString()
            };
            allUsers.push(newEmp);
            if (currentLocalUser) {
              currentLocalUser.tl_commission_balance = (currentLocalUser.tl_commission_balance || 0) + 500;
              const curInList = allUsers.find(u => u.id === currentLocalUser.id);
              if (curInList) curInList.tl_commission_balance = currentLocalUser.tl_commission_balance;
            }
            saveLocalUsers(allUsers);
            json(200, { success: true, employee: newEmp, commission_earned: 500, new_tl_balance: currentLocalUser?.tl_commission_balance || 500 });
            return;
          }
          if (action === 'list_my_tms') {
            const tlId = currentLocalUser ? currentLocalUser.id : 'EMP-TL-01';
            const myTms = allUsers.filter(u => u.tl_id === tlId || u.joined_via_tl === tlId);
            const totalCommission = myTms.length * 500;
            json(200, { success: true, tms: myTms, total_tms: myTms.length, total_commission: totalCommission, balance: currentLocalUser?.tl_commission_balance || totalCommission });
            return;
          }
          if (action === 'update_message_limit') {
            const target = allUsers.find(u => u.id === body.id || u.id === body.employee_id);
            if (target) {
              target.message_limit = body.message_limit !== undefined ? Number(body.message_limit) : 100;
              if (body.reset_today) {
                target.messages_sent_today = 0;
                target.quota_reset_date = new Date().toISOString().slice(0, 10);
              }
              saveLocalUsers(allUsers);
              json(200, { success: true, message: `Message limit updated to ${target.message_limit}` });
            } else {
              json(404, { success: false, error: 'Employee not found' });
            }
            return;
          }
          if (action === 'list_company_employees') {
            const compId = url.searchParams.get('company_id') || body.company_id || currentLocalUser?.company_id || 'COMP-001';
            const filtered = allUsers.filter(u => {
              if (u.role === 'superadmin' || u.company_id === 'GLOBAL') return false;
              if (u.role === 'admin') return false; // Admins are not closer employees
              const uComp = u.company_id || 'COMP-001';
              return uComp === compId;
            }).map(u => {
              const lim = u.message_limit !== undefined ? Number(u.message_limit) : 100;
              const sent = Number(u.messages_sent_today) || 0;
              return {
                ...u,
                quota_metrics: {
                  limit: lim,
                  sent: sent,
                  remaining: lim < 0 ? 'Unlimited' : Math.max(0, lim - sent),
                  percent: lim <= 0 ? 0 : Math.min(100, Math.round((sent / lim) * 100))
                }
              };
            });
            json(200, { success: true, employees: filtered });
            return;
          }
        }

        // 2. Tenant Manager API (Company Admin Controls)
        if (url.pathname === '/api/tenant_manager.php') {
          const allUsers = loadLocalUsers();
          if (action === 'list_company_employees' || action === 'get_employees' || action === 'list_employees') {
            const compId = url.searchParams.get('company_id') || body.company_id || currentLocalUser?.company_id || 'COMP-001';
            const filtered = allUsers.filter(u => {
              if (u.role === 'superadmin' || u.company_id === 'GLOBAL') return false;
              if (u.role === 'admin') return false; // Admins are not closer employees
              const uComp = u.company_id || 'COMP-001';
              return uComp === compId;
            }).map(u => {
              const lim = u.message_limit !== undefined ? Number(u.message_limit) : 100;
              const sent = Number(u.messages_sent_today) || 0;
              return {
                ...u,
                quota_metrics: {
                  limit: lim,
                  sent: sent,
                  remaining: lim < 0 ? 'Unlimited' : Math.max(0, lim - sent),
                  percent: lim <= 0 ? 0 : Math.min(100, Math.round((sent / lim) * 100))
                }
              };
            });
            json(200, { success: true, employees: filtered });
            return;
          }
          if (action === 'update_message_limit') {
            const targetId = body.employee_id || body.id;
            const target = allUsers.find(u => u.id === targetId);
            if (target) {
              target.message_limit = body.message_limit !== undefined ? Number(body.message_limit) : 100;
              if (body.reset_today) {
                target.messages_sent_today = 0;
                target.quota_reset_date = new Date().toISOString().slice(0, 10);
              }
              saveLocalUsers(allUsers);
              json(200, { success: true, message: `Message limit updated to ${target.message_limit}` });
            } else {
              json(404, { success: false, error: 'Employee not found' });
            }
            return;
          }
          if (action === 'toggle_status') {
            const targetId = body.employee_id || body.id;
            const target = allUsers.find(u => u.id === targetId);
            if (target) {
              target.status = target.status === 'active' ? 'suspended' : 'active';
              saveLocalUsers(allUsers);
              json(200, { success: true, message: `Status updated to ${target.status}` });
            } else {
              json(404, { success: false, error: 'Employee not found' });
            }
            return;
          }
          if (action === 'reset_messages') {
            const targetId = body.employee_id || body.id;
            const target = allUsers.find(u => u.id === targetId);
            if (target) {
              target.messages_sent_today = 0;
              saveLocalUsers(allUsers);
              json(200, { success: true, message: 'Message counter reset.' });
            } else {
              json(404, { success: false, error: 'Employee not found' });
            }
            return;
          }
          if (action === 'create_employee') {
            const newEmp = {
              id: 'EMP-' + Date.now().toString(36).toUpperCase(),
              name: body.name || 'New Closer',
              username: (body.username || 'emp_' + Date.now().toString(36)).toLowerCase(),
              email: body.email || (body.username ? body.username + '@nunno.cloud' : ''),
              role: body.role || 'member',
              designation: body.designation || 'Closer Specialist',
              gender: body.gender || 'male',
              phone: body.phone || '',
              company_id: body.company_id || 'COMP-001',
              status: 'active',
              message_limit: Number(body.message_limit) || 150,
              messages_sent_today: 0,
              created_at: new Date().toISOString()
            };
            allUsers.push(newEmp);
            saveLocalUsers(allUsers);
            json(200, { success: true, employee: newEmp, message: 'Employee created successfully.' });
            return;
          }
        }

        // 3. Multi-Company & Platform Headquarters API
        if (url.pathname === '/api/companies.php') {
          const companiesFile = path.join(root, 'data/companies.json');
          const loadComps = () => {
            try { return JSON.parse(fs.readFileSync(companiesFile, 'utf8') || '[]'); } catch (_) { return []; }
          };
          const saveComps = (arr) => {
            try { fs.writeFileSync(companiesFile, JSON.stringify(arr, null, 2), 'utf8'); } catch (_) {}
          };
          const allUsers = loadLocalUsers();

          if (action === 'get_telemetry') {
            const comps = loadComps();
            const pendingTMs = allUsers.filter(u => u.status === 'pending').length;
            let mrr = 0;
            comps.forEach(c => {
              const p = (c.plan || '').toLowerCase();
              if (p.includes('billion') || p.includes('vip')) mrr += 250000;
              else if (p.includes('pro')) mrr += 150000;
              else mrr += 75000;
            });
            json(200, {
              success: true,
              telemetry: {
                total_companies: comps.length,
                active_companies: comps.filter(c => c.status === 'active').length,
                total_employees: allUsers.filter(u => u.role !== 'superadmin' && u.status !== 'pending').length,
                total_tls: allUsers.filter(u => u.role === 'lead').length,
                total_tms: allUsers.filter(u => u.role === 'member' && u.status === 'active').length,
                pending_tms: pendingTMs,
                platform_mrr: mrr,
                total_messages_today: allUsers.reduce((acc, u) => acc + (Number(u.messages_sent_today) || 0), 0),
                active_sessions: allUsers.filter(u => u.phone && u.status === 'active').length,
                system_health: 'optimal',
                subsecond_tick: Date.now(),
                server_time: new Date().toISOString()
              }
            });
            return;
          }
          if (action === 'get_live_chat_stream') {
            const comps = loadComps();
            const compMap = {};
            comps.forEach(c => { compMap[c.id] = c.name; });
            const stream = [];
            
            // Check in-memory leads
            leads.forEach(lead => {
              const leadName = lead.name || lead.display || 'Lead';
              (lead.messages || []).forEach(m => {
                stream.push({
                  id: lead.id + '-' + Math.floor(Math.random() * 9000 + 1000),
                  company_id: lead.company_id || 'COMP-001',
                  company_name: compMap[lead.company_id || 'COMP-001'] || 'Nunno Official',
                  lead_name: leadName,
                  lead_phone: lead.phone || '',
                  closer_name: lead.ownerName || 'Closer Specialist',
                  sender: m.sender || 'lead',
                  text: m.text || '',
                  time: m.time || 'Just now',
                  state: lead.leadState || 'ongoing'
                });
              });
            });

            // Also check data/tenants/*/leads.json
            try {
              const tenantsDir = path.join(root, 'data/tenants');
              if (fs.existsSync(tenantsDir)) {
                fs.readdirSync(tenantsDir).forEach(tFolder => {
                  const tFile = path.join(tenantsDir, tFolder, 'leads.json');
                  if (fs.existsSync(tFile)) {
                    const parsedLeads = JSON.parse(fs.readFileSync(tFile, 'utf8') || '[]');
                    if (Array.isArray(parsedLeads)) {
                      parsedLeads.forEach(l => {
                        const lName = l.name || l.display || 'Lead';
                        (l.messages || []).forEach(m => {
                          stream.push({
                            id: l.id + '-' + Math.floor(Math.random() * 9000 + 1000),
                            company_id: tFolder,
                            company_name: compMap[tFolder] || tFolder,
                            lead_name: lName,
                            lead_phone: l.phone || '',
                            closer_name: l.ownerName || 'Closer Specialist',
                            sender: m.sender || 'lead',
                            text: m.text || '',
                            time: m.time || 'Just now',
                            state: l.leadState || 'ongoing'
                          });
                        });
                      });
                    }
                  }
                });
              }
            } catch (_) {}

            if (stream.length === 0) {
              stream.push(
                { id: 'LIVE-01', company_id: 'COMP-001', company_name: 'Nunno Official', lead_name: 'Ali Trader', lead_phone: '+923001234567', closer_name: 'Askari Raza', sender: 'agent', text: 'Salam Ali bhai! Your license is active.', time: 'Just now', state: 'paid' },
                { id: 'LIVE-02', company_id: 'COMP-002', company_name: 'Apex Crypto Signals', lead_name: 'Waqas BTC', lead_phone: '+923219988776', closer_name: 'Daniyal Sheikh', sender: 'lead', text: 'Bro whats the monthly VIP pricing?', time: '1 min ago', state: 'ongoing' }
              );
            }

            json(200, { success: true, stream: stream.reverse().slice(0, 50) });
            return;
          }
          if (action === 'list') {
            const comps = loadComps();
            comps.forEach(c => {
              const compUsers = allUsers.filter(u => (u.company_id || 'COMP-001') === c.id);
              c.employee_count = compUsers.length;
              c.tl_count = compUsers.filter(u => u.role === 'lead').length;
              c.tm_count = compUsers.filter(u => u.role === 'member').length;
              c.messages_today = compUsers.reduce((acc, u) => acc + (Number(u.messages_sent_today) || 0), 0);
            });
            json(200, { success: true, companies: comps });
            return;
          }
          if (action === 'create' && req.method === 'POST') {
            const comps = loadComps();
            const slug = (body.slug || '').toLowerCase().replace(/[^a-z0-9]/g, '');
            const newComp = {
              id: 'COMP-' + String(comps.length + 1).padStart(3, '0'),
              name: body.name || 'New Company',
              slug: slug,
              owner_name: body.owner_name || 'Admin',
              owner_email: body.owner_email || 'admin@' + slug + '.com',
              plan: body.plan || 'Enterprise Billion',
              logo: body.logo || 'assets/images/chatly-icon.png',
              fallback_phone: body.fallback_phone || '+923318036576',
              max_employees: Number(body.max_employees) || 50,
              default_message_limit: Number(body.default_message_limit) || 200,
              status: 'active',
              created_at: new Date().toISOString()
            };
            comps.push(newComp);
            saveComps(comps);
            json(200, { success: true, company: newComp, admin_username: body.admin_username || slug + '_admin' });
            return;
          }
          if (action === 'toggle_status' && req.method === 'POST') {
            const comps = loadComps();
            const target = comps.find(c => c.id === body.company_id);
            if (target) {
              target.status = target.status === 'active' ? 'suspended' : 'active';
              saveComps(comps);
              json(200, { success: true, message: 'Status updated' });
            } else {
              json(404, { success: false, error: 'Company not found' });
            }
            return;
          }
          if (action === 'delete_company' && req.method === 'POST') {
            const comps = loadComps();
            const filtered = comps.filter(c => c.id !== body.company_id);
            saveComps(filtered);
            json(200, { success: true, message: 'Company purged' });
            return;
          }
          if (action === 'get_company_deep_stats') {
            const compId = url.searchParams.get('company_id') || body.company_id || 'COMP-001';
            const compUsers = allUsers.filter(u => (u.company_id || 'COMP-001') === compId);
            const leadsList = [...leads.values()];
            json(200, {
              success: true,
              stats: {
                company_id: compId,
                total_leads: leadsList.length || 15,
                paid_leads: leadsList.filter(l => l.leadState === 'paid').length || 2,
                ongoing_leads: leadsList.filter(l => l.leadState === 'ongoing').length || 8,
                dead_leads: leadsList.filter(l => l.leadState === 'useless').length || 3,
                closers_count: compUsers.filter(u => u.role === 'member').length || 1,
                tls_count: compUsers.filter(u => u.role === 'lead').length || 2,
                messages_today: compUsers.reduce((acc, u) => acc + (Number(u.messages_sent_today) || 0), 0) || 12,
                total_quota: compUsers.reduce((acc, u) => acc + (Number(u.message_limit) || 100), 0) || 450,
                trunk_number: '+923318036576',
                trunk_status: 'online'
              }
            });
            return;
          }
          if (action === 'impersonate' && req.method === 'POST') {
            const compId = body.company_id || url.searchParams.get('company_id') || 'COMP-001';
            const comps = loadComps();
            const targetComp = comps.find(c => c.id === compId || c.slug === compId) || comps[0] || { id: compId, name: 'Company' };
            const compAdmin = allUsers.find(u => u.role === 'admin' && (u.company_id || 'COMP-001') === targetComp.id) || {
              id: 'USR-ADMIN-' + targetComp.id,
              name: targetComp.name + ' Administrator',
              username: targetComp.slug + '_admin',
              role: 'admin',
              company_id: targetComp.id,
              status: 'active'
            };
            compAdmin.is_impersonated = true;
            compAdmin.company_id = targetComp.id;
            compAdmin.company_name = targetComp.name;
            currentLocalUser = compAdmin;
            json(200, { success: true, message: `Impersonating Admin for ${targetComp.name}`, redirect: `/cc?portal=1&company_id=${targetComp.id}` });
            return;
          }
          if (action === 'get_company_leads') {
            const leadsList = [...leads.values()];
            json(200, { success: true, leads: leadsList, count: leadsList.length });
            return;
          }
          if (action === 'takeover_lead' && req.method === 'POST') {
            const leadId = body.lead_id || body.id;
            const targetLead = leads.get(leadId);
            if (targetLead) {
              targetLead.leadState = 'ongoing';
              targetLead.takenOverByAdmin = true;
              targetLead.adminTakeoverTime = new Date().toISOString();
              targetLead.closerCommissionProtected = true;
              targetLead.messages = targetLead.messages || [];
              targetLead.messages.push({
                sender: 'agent',
                agentName: 'System Administrator (Takeover)',
                text: '👑 ADMIN TAKEOVER: Company Admin has taken over this lead. Closer attribution and 100% sales commission remain protected.',
                time: new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
              });
              leads.set(leadId, targetLead);
              json(200, { success: true, message: 'Lead successfully taken over! Closer commission is 100% protected.', lead: targetLead });
            } else {
              json(200, { success: true, message: 'Takeover recorded. Closer commission is 100% protected.' });
            }
            return;
          }
          if (action === 'get_company_config') {
            const compId = url.searchParams.get('company_id') || body.company_id || 'COMP-001';
            json(200, {
              success: true,
              config: {
                company_id: compId,
                company_name: 'Nunno Official',
                products: [
                  { id: 'pro', name: 'Nunno Pro', amount: 5000, commission: 2000, badge: '💎 Most Popular' },
                  { id: 'starter', name: 'Nunno Starter', amount: 1700, commission: 700, badge: '⚡ Scalper Entry' },
                  { id: 'whale', name: 'Whale VIP', amount: 15000, commission: 10000, badge: '🐋 Institutional Alpha' }
                ],
                default_pitch: '{Salam|AoA|Hello} {name}! Welcome to Nunno. Special limited-time offers available today.'
              }
            });
            return;
          }
          if (action === 'update_company_config' && req.method === 'POST') {
            json(200, { success: true, message: 'Company config updated successfully.' });
            return;
          }
          if (action === 'get_audit_logs') {
            const auditFile = path.join(root, 'data/audit_logs.json');
            let logs = [];
            try { logs = JSON.parse(fs.readFileSync(auditFile, 'utf8') || '[]'); } catch (_) {}
            json(200, { success: true, logs: logs.reverse().slice(0, 50) });
            return;
          }
          const numFile = path.join(root, 'data/company_numbers.json');
          const loadNums = () => {
            try { return JSON.parse(fs.readFileSync(numFile, 'utf8') || '[]'); } catch (_) { return []; }
          };
          const saveNums = (arr) => {
            try { fs.writeFileSync(numFile, JSON.stringify(arr, null, 2), 'utf8'); } catch (_) {}
          };
          if (action === 'list_number_profiles') {
            json(200, { success: true, profiles: loadNums() });
            return;
          }
          if (action === 'create_number_profile' && req.method === 'POST') {
            const nums = loadNums();
            const phone = String(body.phone || '').replace(/\D/g, '');
            const newProf = {
              id: 'NUM-' + String(nums.length + 1).padStart(3, '0'),
              phone: phone,
              display: '+' + phone,
              label: body.label || 'Company WhatsApp Trunk',
              company_id: body.company_id || 'COMP-001',
              assigned_to_user_id: null,
              assigned_to_user_name: null,
              daily_capacity: Number(body.daily_capacity) || 500,
              messages_sent_today: 0,
              status: 'available',
              created_at: new Date().toISOString()
            };
            nums.push(newProf);
            saveNums(nums);
            json(200, { success: true, profile: newProf });
            return;
          }
          if (action === 'assign_number_profile' && req.method === 'POST') {
            const nums = loadNums();
            const prof = nums.find(p => p.id === body.profile_id);
            const user = allUsers.find(u => u.id === body.user_id);
            if (!prof || !user) { json(404, { success: false, error: 'Profile or User not found' }); return; }
            prof.assigned_to_user_id = user.id;
            prof.assigned_to_user_name = user.name;
            prof.status = 'in_use';
            user.phone = prof.phone;
            user.using_company_fallback = true;
            saveNums(nums);
            saveLocalUsers(allUsers);
            json(200, { success: true, message: `Number ${prof.display} assigned to ${user.name}` });
            return;
          }
          if (action === 'release_number_profile' && req.method === 'POST') {
            const nums = loadNums();
            const prof = nums.find(p => p.id === body.profile_id);
            if (!prof) { json(404, { success: false, error: 'Profile not found' }); return; }
            const prevUserId = prof.assigned_to_user_id;
            prof.assigned_to_user_id = null;
            prof.assigned_to_user_name = null;
            prof.status = 'available';
            if (prevUserId) {
              const u = allUsers.find(x => x.id === prevUserId);
              if (u && u.using_company_fallback) {
                u.phone = '';
                delete u.using_company_fallback;
                saveLocalUsers(allUsers);
              }
            }
            saveNums(nums);
            json(200, { success: true, message: 'Number returned to pool' });
            return;
          }
        }

        // 4. Leads Sync API
        if (url.pathname === '/api/leads_sync.php') {
          if (action === 'get_leads') { json(200, { success: true, leads: [...leads.values()] }); return; }
          if (action === 'delete_leads' || action === 'delete_lead') {
            const ids = Array.isArray(body.ids) ? body.ids : (body.id ? [body.id] : []);
            ids.forEach(id => leads.delete(id));
            saveLeadsDisk();
            json(200, { success: true, deleted_count: ids.length, remaining_count: leads.size });
            return;
          }
          if (action === 'update_lead' && req.method === 'POST') {
            const lead = { ...(leads.get(body.id) || { messages: [] }), ...body };
            if (body.newMessage && !lead.messages.some(m => m.id === body.newMessage.id)) lead.messages.push(body.newMessage);
            if (body.updateMessage) lead.messages = lead.messages.map(m => m.waMessageId === body.updateMessage.waMessageId ? { ...m, ...body.updateMessage } : m);
            leads.set(body.id, lead);
            saveLeadsDisk();
            json(200, { success: true }); return;
          }
        }

        // 4b. Withdrawals & Payouts API
        if (url.pathname === '/api/withdrawals.php') {
          const wdFile = path.join(root, 'data/withdrawals.json');
          const loadWd = () => {
            try { return JSON.parse(fs.readFileSync(wdFile, 'utf8') || '[]'); } catch (_) { return []; }
          };
          const saveWd = (arr) => {
            try { fs.writeFileSync(wdFile, JSON.stringify(arr, null, 2), 'utf8'); } catch (_) {}
          };
          if (action === 'get_withdrawals' || !action) {
            json(200, { success: true, withdrawals: loadWd() });
            return;
          }
          if (action === 'request_withdrawal' && req.method === 'POST') {
            const amount = Number(body.amount) || 0;
            if (amount <= 0) { json(400, { success: false, error: 'Please enter a valid withdrawal amount.' }); return; }
            const method = String(body.method || 'EasyPaisa').trim();
            const institution = String(body.institution || method).trim();
            const accountTitle = String(body.accountTitle || '').trim();
            const accountNumber = String(body.accountNumber || '').trim();
            if (!accountTitle || !accountNumber) {
              json(400, { success: false, error: 'Account title and account number are required.' });
              return;
            }
            const list = loadWd();
            const newWd = {
              id: 'WD-' + Date.now().toString(36).toUpperCase(),
              userId: body.userId || currentLocalUser?.id || 'EMP-TM-01',
              userName: body.userName || currentLocalUser?.name || 'Closer Specialist',
              userRole: body.userRole || currentLocalUser?.role || 'member',
              amount: amount,
              method: method,
              institution: institution,
              accountTitle: accountTitle,
              accountNumber: accountNumber,
              note: body.note || '',
              status: 'pending',
              created_at: new Date().toISOString()
            };
            list.unshift(newWd);
            saveWd(list);
            json(200, { success: true, withdrawal: newWd, withdrawals: list });
            return;
          }
          if (action === 'update_status' && req.method === 'POST') {
            const list = loadWd();
            const target = list.find(w => w.id === body.id);
            if (target) {
              target.status = body.status || 'approved';
              if (body.txRef) target.txRef = body.txRef;
              target.updated_at = new Date().toISOString();
              saveWd(list);
              json(200, { success: true, withdrawals: list, message: `Withdrawal ${target.id} updated to ${target.status}` });
            } else {
              json(404, { success: false, error: 'Withdrawal request not found.' });
            }
            return;
          }
        }

        // 5. Direct Sender API (Baileys WhatsApp Web Bridge)
        if (url.pathname === '/api/direct_sender.php') {
          const routes = {
            get_sender_info: '/status',
            connect_whatsapp: '/connect',
            reconnect_whatsapp: '/reconnect',
            reset_whatsapp: '/reset',
            send_direct: '/send',
            get_events: '/events?after=' + encodeURIComponent(body.after || 0),
            get_media: '/media?id=' + encodeURIComponent(body.message_id || ''),
            sync_chat: '/sync-chat',
            get_presence: '/presence?phone=' + encodeURIComponent(body.phone || ''),
            send_presence: '/presence',
            get_send_status: '/receipt?id=' + encodeURIComponent(body.message_id || ''),
            start_campaign: '/campaign/start',
            pause_campaign: '/campaign/pause',
            resume_campaign: '/campaign/resume',
            stop_campaign: '/campaign/stop',
            get_campaign_status: '/campaign/status'
          };
          if (routes[action]) {
            const post = [
              'connect_whatsapp',
              'reconnect_whatsapp',
              'reset_whatsapp',
              'send_direct',
              'sync_chat',
              'send_presence',
              'start_campaign',
              'pause_campaign',
              'resume_campaign',
              'stop_campaign'
            ].includes(action);
            if (post && req.method !== 'POST') { json(405, { success: false }); return; }
            if (action === 'send_direct') {
              body.phone = String(body.phone || '').replace(/\D/g, '');
              if (/^0092/.test(body.phone)) body.phone = body.phone.slice(2);
              else if (/^03\d{9}$/.test(body.phone)) body.phone = '92' + body.phone.slice(1);
              else if (/^3\d{9}$/.test(body.phone)) body.phone = '92' + body.phone;

              // Quota / Limiter check for non-admin users
              const allUsers = loadLocalUsers();
              const activeUser = body.userId ? allUsers.find(u => u.id === body.userId) : currentLocalUser;
              if (activeUser && !['admin', 'superadmin'].includes(activeUser.role)) {
                const limit = activeUser.message_limit !== undefined ? Number(activeUser.message_limit) : 100;
                const sent = Number(activeUser.messages_sent_today || 0);
                if (limit > 0 && sent >= limit) {
                  json(429, {
                    success: false,
                    quota_exceeded: true,
                    message: `Daily message quota reached (${sent}/${limit} sent today). Please contact your Company Administrator to increase your message allowance.`
                  });
                  return;
                }
              }
            }
            if (action === 'get_send_status' && String(body.message_id || '').startsWith('sim_')) {
              json(200, { success: true, simulated: true, status: 'accepted' });
              return;
            }
            const upstream = await fetch(bridgeUrl + routes[action], { method: post ? 'POST' : 'GET',
              headers: { Authorization: `Bearer ${token}`, 'Content-Type': 'application/json' },
              ...(post ? { body: JSON.stringify(body) } : {}) });
            if (action === 'send_direct' && upstream.status === 409 && (body.simulate || body.demo)) {
              const simId = 'sim_' + Date.now();
              const simResult = {
                success: true,
                simulated: true,
                message_id: simId,
                status: 'accepted',
                note: 'WhatsApp socket unlinked: duplex verified via simulation'
              };
              const all = loadLocalUsers();
              const activeId = body.userId || currentLocalUser?.id;
              const u = all.find(x => x.id === activeId);
              if (u) {
                u.messages_sent_today = (Number(u.messages_sent_today) || 0) + 1;
                saveLocalUsers(all);
                const lim = u.message_limit !== undefined ? Number(u.message_limit) : 100;
                const cur = Number(u.messages_sent_today);
                simResult.quota_metrics = {
                  limit: lim,
                  sent: cur,
                  remaining: lim < 0 ? 'Unlimited' : Math.max(0, lim - cur)
                };
              }
              json(200, simResult);
              return;
            }
            const result = await upstream.json();
            if (action === 'send_direct' && result && result.success) {
              const all = loadLocalUsers();
              const activeId = body.userId || currentLocalUser?.id;
              const u = all.find(x => x.id === activeId);
              if (u) {
                u.messages_sent_today = (Number(u.messages_sent_today) || 0) + 1;
                saveLocalUsers(all);
                const lim = u.message_limit !== undefined ? Number(u.message_limit) : 100;
                const cur = Number(u.messages_sent_today);
                result.quota_metrics = {
                  limit: lim,
                  sent: cur,
                  remaining: lim < 0 ? 'Unlimited' : Math.max(0, lim - cur)
                };
              }
            }
            json(upstream.status, result); return;
          }
        }

        json(400, { success: false, message: 'Endpoint action not recognized.' }); return;
      }

      // Static Page & Resource Routing
      const pathname = decodeURIComponent(url.pathname);
      let relative = pathname;
      if (relative.startsWith('/')) relative = relative.slice(1);
      if (relative.endsWith('/')) relative = relative.slice(0, -1);

      // Handle GET logout or switch account parameter
      if (url.searchParams.get('action') === 'logout' || url.searchParams.get('switch') === '1' || url.searchParams.get('logout') === '1') {
        currentLocalUser = null;
        res.writeHead(302, { 'Location': '/' });
        res.end();
        return;
      }
      
      // Default / -> login.php (The authentic WhatsApp Web landing page)
      if (!relative) {
        relative = 'login.php';
      }

      if (relative === 'index.html' || relative === 'index.php') {
        if (!currentLocalUser) {
          res.writeHead(302, { 'Location': '/' }); res.end(); return;
        }
        relative = 'index.html';
      }

      if (relative === 'login' || relative === 'login.html') relative = 'login.php';
      if (relative === 'admin' || relative === 'admin.html' || relative === 'cc' || relative === 'cc.html' || relative === 'cc.php' || relative === 'cc/index.php') relative = 'admin.php';
      if (relative === 'sadmin' || relative === 'sadmin.html' || relative === 'ss' || relative === 'ss.html' || relative === 'ss.php' || relative === 'ss/index.php') relative = 'sadmin.php';

      const file = path.resolve(root, relative);
      const allowedPages = ['index.html', 'email_hub.php', 'sadmin.php', 'admin.php', 'login.php', 'cc.php', 'ss.php'];
      if ((!allowedPages.includes(relative) && !file.startsWith(path.join(root, 'assets') + path.sep)) || !file.startsWith(root + path.sep) || !fs.existsSync(file) || !fs.statSync(file).isFile()) {
        res.writeHead(404); res.end(); return;
      }

      const types = { '.html': 'text/html', '.php': 'text/html', '.js': 'text/javascript', '.css': 'text/css', '.png': 'image/png', '.jpg': 'image/jpeg', '.jpeg': 'image/jpeg', '.webp': 'image/webp', '.svg': 'image/svg+xml', '.woff2': 'font/woff2' };
      res.setHeader('Content-Type', types[path.extname(file)] || 'application/octet-stream');

      if (allowedPages.includes(relative)) {
        res.setHeader('Set-Cookie', `nunno_local=${session}; HttpOnly; SameSite=Strict; Path=/`);
        let content = fs.readFileSync(file, 'utf8');
        if (relative === 'login.php') {
          if (url.searchParams.get('action') === 'logout' || url.searchParams.get('switch') === '1' || url.searchParams.get('logout') === '1') {
            currentLocalUser = null;
          }
          content = templateLoginPhp(content);
        } else if (relative === 'admin.php' || relative === 'cc.php') {
          if (url.searchParams.get('action') === 'logout' || url.searchParams.get('switch') === '1' || url.searchParams.get('login') === '1') {
            currentLocalUser = null;
          }
          content = templateAdminPhp(content, currentLocalUser, url.searchParams.get('company_id'), url.searchParams.has('portal') || url.searchParams.has('dashboard'));
        } else if (relative === 'sadmin.php' || relative === 'ss.php') {
          if (url.searchParams.get('action') === 'logout' || url.searchParams.get('switch') === '1' || url.searchParams.get('login') === '1') {
            currentLocalUser = null;
          }
          content = templateSadminPhp(content, currentLocalUser, url.searchParams.has('portal') || url.searchParams.has('dashboard'));
        } else if (relative === 'index.html') {
          content = content.replace('</head>', '<script>window.NUNNO_LOCAL_LIVE=true;</script></head>');
        }
        res.end(content);
      } else {
        res.end(fs.readFileSync(file));
      }
    } catch (err) { console.error('Local error:', err); if (!res.headersSent) json(500, { success: false, message: 'Local request failed: ' + err.message }); }
  });

  await new Promise((resolve, reject) => { server.once('error', reject); server.listen(port, '127.0.0.1', resolve); }).catch(async error => { await bridge.close(); throw error; });
  origin = `http://127.0.0.1:${server.address().port}`;
  console.log(`\n======================================================`);
  console.log(`🌟 OmniDesk Local Server: ${origin}`);
  console.log(`📱 WhatsApp Web Landing Page: ${origin}/login.php`);
  console.log(`🏢 Company Admin Dashboard:   ${origin}/admin.php`);
  console.log(`⚡ Super Admin Headquarters:  ${origin}/sadmin.php`);
  console.log(`======================================================\n`);
  if (openBrowser && process.platform === 'win32') spawn('explorer.exe', [origin], { windowsHide: true, stdio: 'ignore' }).unref();
  return { origin, close: async () => { server.closeAllConnections(); await new Promise(resolve => server.close(resolve)); await bridge.close(); } };
}

if (require.main === module) startLocal({ port: Number(process.env.NUNNO_LOCAL_PORT || 8080) }).then(app => {
  for (const signal of ['SIGINT', 'SIGTERM']) process.on(signal, async () => { await app.close(); process.exit(0); });
}).catch(error => { console.error(error.message); process.exitCode = 1; });

module.exports = { startLocal };
