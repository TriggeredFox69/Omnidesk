<?php
/**
 * OmniDesk Enterprise Email Communications Hub (Admin Standalone View)
 */

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 60 * 60 * 24 * 7,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

$currentUser = $_SESSION['omnidesk_user'] ?? null;
// Auto-login fallback for local development if needed
if (!$currentUser && (in_array($_SERVER['SERVER_NAME'] ?? '', ['localhost', '127.0.0.1']) || !isset($_SERVER['SERVER_NAME']))) {
    $usersFile = __DIR__ . '/data/users.json';
    if (file_exists($usersFile)) {
        $uList = json_decode(file_get_contents($usersFile), true);
        if (is_array($uList) && isset($uList[0])) $currentUser = $uList[0];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>OmniDesk — Enterprise Email Dispatch Studio</title>
  <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%236366f1'><path d='M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z'/><polyline points='22,6 12,13 2,6'/></svg>">
  <link rel="stylesheet" href="assets/css/whatsapp.css?v=15.0">
  <style>
    :root {
      --hub-bg: #f8fafc;
      --hub-surface: #ffffff;
      --hub-border: #e2e8f0;
      --hub-text: #0f172a;
      --hub-muted: #64748b;
    }
    body.theme-dark {
      --hub-bg: #090810;
      --hub-surface: #121020;
      --hub-border: rgba(255, 255, 255, 0.1);
      --hub-text: #f8fafc;
      --hub-muted: #94a3b8;
    }
    body {
      background: var(--hub-bg);
      color: var(--hub-text);
      font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
      margin: 0;
      padding: 0;
      min-height: 100vh;
    }
    .hub-container {
      max-width: 1280px;
      margin: 0 auto;
      padding: 24px 20px 40px;
    }
    .hub-topbar {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 16px 20px;
      background: var(--hub-surface);
      border: 1px solid var(--hub-border);
      border-radius: 16px;
      box-shadow: 0 4px 20px rgba(0,0,0,0.03);
      margin-bottom: 24px;
    }
    .hub-brand {
      display: flex;
      align-items: center;
      gap: 12px;
    }
    .hub-brand-logo {
      width: 40px;
      height: 40px;
      border-radius: 10px;
      background: linear-gradient(135deg, #6366f1, #8b5cf6);
      display: flex;
      align-items: center;
      justify-content: center;
      color: #fff;
      font-size: 20px;
    }
    .hub-tabs {
      display: flex;
      gap: 8px;
      background: rgba(99, 102, 241, 0.08);
      padding: 4px;
      border-radius: 10px;
    }
    .hub-tab-btn {
      padding: 8px 16px;
      font-size: 13px;
      font-weight: 700;
      border-radius: 8px;
      border: none;
      background: transparent;
      color: var(--hub-muted);
      cursor: pointer;
      transition: all 0.15s;
    }
    .hub-tab-btn.active {
      background: var(--hub-surface);
      color: var(--hub-text);
      box-shadow: 0 2px 6px rgba(0,0,0,0.08);
    }
    .hub-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 24px;
    }
    @media (max-width: 900px) {
      .hub-grid { grid-template-columns: 1fr; }
    }
    .hub-card {
      background: var(--hub-surface);
      border: 1px solid var(--hub-border);
      border-radius: 16px;
      padding: 24px;
      box-shadow: 0 4px 20px rgba(0,0,0,0.03);
    }
    .hub-preview-frame {
      width: 100%;
      height: 540px;
      border: 1px solid var(--hub-border);
      border-radius: 12px;
      background: #ffffff;
      overflow-y: auto;
    }
    .variable-chip {
      display: inline-flex;
      align-items: center;
      gap: 4px;
      background: rgba(99, 102, 241, 0.1);
      border: 1px solid rgba(99, 102, 241, 0.25);
      color: #6366f1;
      font-family: var(--font-mono, monospace);
      font-size: 11px;
      font-weight: 700;
      padding: 3px 8px;
      border-radius: 6px;
      cursor: pointer;
      user-select: none;
      transition: all 0.15s;
    }
    .variable-chip:hover {
      background: #6366f1;
      color: #fff;
    }
    .log-table {
      width: 100%;
      border-collapse: collapse;
      font-size: 12.5px;
    }
    .log-table th {
      text-align: left;
      padding: 10px 12px;
      border-bottom: 2px solid var(--hub-border);
      color: var(--hub-muted);
      font-size: 11px;
      text-transform: uppercase;
      font-weight: 800;
    }
    .log-table td {
      padding: 12px;
      border-bottom: 1px solid var(--hub-border);
      vertical-align: middle;
    }
  </style>
</head>
<body>
  <div class="hub-container">
    <!-- Topbar -->
    <header class="hub-topbar">
      <div class="hub-brand">
        <div class="hub-brand-logo">✉️</div>
        <div>
          <div style="font-size:16px;font-weight:900;display:flex;align-items:center;gap:8px;">
            <span>OmniDesk Email Communications Hub</span>
            <span class="badge-role-admin" style="font-size:9.5px;">Admin Suite</span>
          </div>
          <div style="font-size:11.5px;color:var(--hub-muted);">Dispatch transactional notifications, receipts, credentials &amp; announcements</div>
        </div>
      </div>
      <div style="display:flex;align-items:center;gap:12px;">
        <nav class="hub-tabs" aria-label="Email studio views">
          <button type="button" class="hub-tab-btn active" data-tab="compose">✍️ Compose &amp; Send</button>
          <button type="button" class="hub-tab-btn" data-tab="history">📜 Sent History</button>
          <button type="button" class="hub-tab-btn" data-tab="settings">⚙️ Mailer Settings</button>
        </nav>
        <a href="index.html" class="btn btn-ghost btn-sm" style="font-weight:700;text-decoration:none;">
          &larr; Back to OmniDesk
        </a>
      </div>
    </header>

    <!-- Tab 1: Compose & Send -->
    <div id="tabCompose" class="hub-tab-content">
      <div class="hub-grid">
        <!-- Left: Form Controls -->
        <div class="hub-card">
          <h2 style="font-size:16px;font-weight:800;margin:0 0 16px;display:flex;align-items:center;gap:8px;">
            <span>Create Dispatch</span>
          </h2>

          <div class="auth-alert" id="hubAlert" style="display:none;margin-bottom:14px;"></div>

          <form id="emailDispatchForm" onsubmit="return false;" style="display:flex;flex-direction:column;gap:14px;">
            <!-- Audience Selection -->
            <div class="form-group" style="margin:0;">
              <label class="form-label" style="font-weight:700;">1. Target Audience</label>
              <select id="hubTargetType" class="form-select">
                <option value="user" selected>🎯 Target Single Employee</option>
                <option value="all">📢 All Active Employees &amp; Closers (Broadcast)</option>
                <option value="role_lead">⚡ All Team Leads Only</option>
                <option value="role_member">👤 All Closers / Members Only</option>
                <option value="custom">✉️ Custom External Email Address</option>
              </select>
            </div>

            <!-- Employee Picker -->
            <div class="form-group" id="hubUserGroup" style="margin:0;">
              <label class="form-label">Select Employee</label>
              <select id="hubUserSelect" class="form-select"></select>
            </div>

            <!-- Custom Email Inputs -->
            <div id="hubCustomGroup" style="display:none;flex-direction:column;gap:10px;">
              <div class="form-group" style="margin:0;">
                <label class="form-label">Recipient Email Address</label>
                <input type="email" id="hubCustomEmail" class="form-input" placeholder="e.g. employee@gmail.com">
              </div>
              <div class="form-group" style="margin:0;">
                <label class="form-label">Recipient Full Name</label>
                <input type="text" id="hubCustomName" class="form-input" placeholder="e.g. Usman Ali">
              </div>
            </div>

            <!-- Template Preset -->
            <div class="form-group" style="margin:0;">
              <label class="form-label" style="font-weight:700;">2. Email Template Preset</label>
              <select id="hubTemplateSelect" class="form-select"></select>
            </div>

            <!-- Clickable Placeholders -->
            <div>
              <label class="form-label" style="font-size:11px;color:var(--hub-muted);margin-bottom:6px;">Dynamic Variables (Click to insert):</label>
              <div style="display:flex;flex-wrap:wrap;gap:6px;" id="hubVariableChips">
                <span class="variable-chip" data-var="{name}">{name}</span>
                <span class="variable-chip" data-var="{username}">{username}</span>
                <span class="variable-chip" data-var="{email}">{email}</span>
                <span class="variable-chip" data-var="{role}">{role}</span>
                <span class="variable-chip" data-var="{amount}">{amount}</span>
                <span class="variable-chip" data-var="{method}">{method}</span>
                <span class="variable-chip" data-var="{date}">{date}</span>
                <span class="variable-chip" data-var="{portal_url}">{portal_url}</span>
              </div>
            </div>

            <!-- Subject Line -->
            <div class="form-group" style="margin:0;">
              <label class="form-label" style="font-weight:700;">3. Subject Line</label>
              <input type="text" id="hubSubject" class="form-input" placeholder="Enter email subject" required>
            </div>

            <!-- Badge Text -->
            <div class="form-group" style="margin:0;">
              <label class="form-label" style="font-weight:700;">4. Header Tag / Badge</label>
              <input type="text" id="hubBadge" class="form-input" placeholder="e.g. Account Ready, Funds Sent" value="Notice">
            </div>

            <!-- Content HTML -->
            <div class="form-group" style="margin:0;">
              <label class="form-label" style="font-weight:700;">5. Email Body (HTML &amp; Text)</label>
              <textarea id="hubBodyHtml" class="form-input" rows="8" style="font-family:var(--font-mono, monospace);font-size:12px;resize:vertical;"></textarea>
            </div>

            <button type="submit" class="btn btn-wa" id="hubSendBtn" style="padding:12px;font-size:13.5px;font-weight:800;justify-content:center;">
              <span>✉️ Dispatch Email Now</span>
              <span>&rarr;</span>
            </button>
          </form>
        </div>

        <!-- Right: Live Responsive Preview -->
        <div class="hub-card" style="display:flex;flex-direction:column;">
          <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;">
            <h2 style="font-size:16px;font-weight:800;margin:0;">Live Desktop &amp; Mobile Preview</h2>
            <span style="font-size:11px;color:var(--hub-muted);">Responsive HTML5 Mailer</span>
          </div>
          <div class="hub-preview-frame" id="hubPreviewContainer" style="flex:1;">
            <iframe id="hubPreviewIframe" style="width:100%;height:100%;border:none;"></iframe>
          </div>
        </div>
      </div>
    </div>

    <!-- Tab 2: Sent History & Logs -->
    <div id="tabHistory" class="hub-tab-content" style="display:none;">
      <div class="hub-card">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
          <h2 style="font-size:16px;font-weight:800;margin:0;">Email Dispatch Audit Log</h2>
          <button type="button" class="btn btn-ghost btn-xs" id="hubRefreshLogsBtn">🔄 Refresh Logs</button>
        </div>
        <div style="overflow-x:auto;">
          <table class="log-table">
            <thead>
              <tr>
                <th>ID</th>
                <th>Recipient</th>
                <th>Subject</th>
                <th>Template</th>
                <th>Sent At</th>
                <th>Sender</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody id="hubLogsTbody">
              <tr><td colspan="7" style="text-align:center;padding:24px;color:var(--hub-muted);">Loading logs...</td></tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Tab 3: Mailer Settings -->
    <div id="tabSettings" class="hub-tab-content" style="display:none;">
      <div class="hub-card" style="max-width:680px;margin:0 auto;">
        <h2 style="font-size:16px;font-weight:800;margin:0 0 16px;">Mailer &amp; SMTP Configuration</h2>
        <div class="auth-alert" id="hubSettingsAlert" style="display:none;margin-bottom:14px;"></div>
        <form id="hubSettingsForm" onsubmit="return false;" style="display:flex;flex-direction:column;gap:14px;">
          <div class="form-group" style="margin:0;">
            <label class="form-label">Transport Mechanism</label>
            <select id="hubTransport" class="form-select">
              <option value="mail" selected>PHP Native Mailer (mail() — cPanel Recommended)</option>
              <option value="smtp">Custom SMTP Server (Titan, Google Workspace, Private)</option>
            </select>
          </div>
          <div class="form-group" style="margin:0;">
            <label class="form-label">Default Sender Display Name</label>
            <input type="text" id="hubSenderName" class="form-input" value="OmniDesk Administration">
          </div>
          <div class="form-group" style="margin:0;">
            <label class="form-label">Default Sender Email</label>
            <input type="email" id="hubSenderEmail" class="form-input" value="admin@nunno.cloud">
          </div>
          <div class="form-group" style="margin:0;">
            <label class="form-label">Reply-To Address</label>
            <input type="email" id="hubReplyTo" class="form-input" value="numeniousnexus@gmail.com">
          </div>
          <div id="hubSmtpFields" style="display:none;flex-direction:column;gap:12px;background:rgba(99,102,241,0.04);padding:14px;border-radius:10px;border:1px solid var(--hub-border);">
            <div class="form-group" style="margin:0;">
              <label class="form-label">SMTP Host</label>
              <input type="text" id="hubSmtpHost" class="form-input" placeholder="mail.lalagpt.pk">
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
              <div class="form-group" style="margin:0;">
                <label class="form-label">SMTP Port</label>
                <input type="number" id="hubSmtpPort" class="form-input mono" value="465">
              </div>
              <div class="form-group" style="margin:0;">
                <label class="form-label">Encryption</label>
                <select id="hubSmtpSecure" class="form-select">
                  <option value="ssl" selected>SSL (Port 465)</option>
                  <option value="tls">TLS (Port 587)</option>
                  <option value="none">None</option>
                </select>
              </div>
            </div>
            <div class="form-group" style="margin:0;">
              <label class="form-label">SMTP Username / Email</label>
              <input type="text" id="hubSmtpUser" class="form-input">
            </div>
            <div class="form-group" style="margin:0;">
              <label class="form-label">SMTP Password</label>
              <input type="password" id="hubSmtpPass" class="form-input" placeholder="••••••••">
            </div>
          </div>
          <button type="submit" class="btn btn-wa" id="hubSaveSettingsBtn" style="padding:10px;font-weight:700;">
            Save Mailer Configuration
          </button>
        </form>
      </div>
    </div>
  </div>

  <script>
    let employees = [];
    let templates = [];

    // Theme sync
    const savedTheme = localStorage.getItem('nunno_theme') || 'light';
    if (savedTheme === 'dark') document.body.classList.add('theme-dark');

    // Tab switching
    document.querySelectorAll('.hub-tab-btn').forEach(btn => {
      btn.addEventListener('click', () => {
        document.querySelectorAll('.hub-tab-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        const tab = btn.dataset.tab;
        document.getElementById('tabCompose').style.display = tab === 'compose' ? 'block' : 'none';
        document.getElementById('tabHistory').style.display = tab === 'history' ? 'block' : 'none';
        document.getElementById('tabSettings').style.display = tab === 'settings' ? 'block' : 'none';
        if (tab === 'history') loadLogs();
      });
    });

    // Load initial data
    async function init() {
      // Load employees
      try {
        const res = await fetch('api/auth.php?action=list_employees').then(r => r.json());
        if (res && res.success && Array.isArray(res.employees)) {
          employees = res.employees;
        }
      } catch (_) {
        const local = localStorage.getItem('nunno_local_employees');
        if (local) employees = JSON.parse(local);
      }
      populateUserSelect();

      // Load templates
      try {
        const res = await fetch('api/email_service.php?action=get_templates').then(r => r.json());
        if (res && res.success && Array.isArray(res.templates)) {
          templates = res.templates;
          populateTemplateSelect();
        }
      } catch (_) {}

      // Load settings
      try {
        const res = await fetch('api/email_service.php?action=get_settings').then(r => r.json());
        if (res && res.success && res.settings) {
          const s = res.settings;
          if (s.transport) document.getElementById('hubTransport').value = s.transport;
          if (s.sender_name) document.getElementById('hubSenderName').value = s.sender_name;
          if (s.sender_email) document.getElementById('hubSenderEmail').value = s.sender_email;
          if (s.reply_to) document.getElementById('hubReplyTo').value = s.reply_to;
          if (s.smtp_host) document.getElementById('hubSmtpHost').value = s.smtp_host;
          if (s.smtp_port) document.getElementById('hubSmtpPort').value = s.smtp_port;
          if (s.smtp_secure) document.getElementById('hubSmtpSecure').value = s.smtp_secure;
          if (s.smtp_user) document.getElementById('hubSmtpUser').value = s.smtp_user;
          toggleSmtpFields();
        }
      } catch (_) {}
    }

    function populateUserSelect() {
      const select = document.getElementById('hubUserSelect');
      if (!select) return;
      select.innerHTML = employees.map(e => `
        <option value="${e.id}">
          ${e.name} (@${e.username}) — ${e.email || 'no email'} [${e.status === 'suspended' ? 'Suspended' : 'Active'}]
        </option>
      `).join('');
    }

    function populateTemplateSelect() {
      const select = document.getElementById('hubTemplateSelect');
      if (!select) return;
      select.innerHTML = templates.map(t => `<option value="${t.id}">${t.name}</option>`).join('');
      if (templates.length) selectTemplate(templates[0].id);
    }

    function selectTemplate(tmplId) {
      const tmpl = templates.find(t => t.id === tmplId);
      if (!tmpl) return;
      document.getElementById('hubSubject').value = tmpl.subject;
      document.getElementById('hubBadge').value = tmpl.badge;
      document.getElementById('hubBodyHtml').value = tmpl.default_body;
      updatePreview();
    }

    document.getElementById('hubTemplateSelect').addEventListener('change', (e) => {
      selectTemplate(e.target.value);
    });

    document.getElementById('hubTargetType').addEventListener('change', (e) => {
      const val = e.target.value;
      document.getElementById('hubUserGroup').style.display = val === 'user' ? 'block' : 'none';
      document.getElementById('hubCustomGroup').style.display = val === 'custom' ? 'flex' : 'none';
      updatePreview();
    });

    document.getElementById('hubUserSelect').addEventListener('change', updatePreview);
    document.getElementById('hubSubject').addEventListener('input', updatePreview);
    document.getElementById('hubBadge').addEventListener('input', updatePreview);
    document.getElementById('hubBodyHtml').addEventListener('input', updatePreview);

    // Variable chip click insertion
    document.querySelectorAll('.variable-chip').forEach(chip => {
      chip.addEventListener('click', () => {
        const text = chip.dataset.var;
        const textarea = document.getElementById('hubBodyHtml');
        const start = textarea.selectionStart;
        const end = textarea.selectionEnd;
        textarea.value = textarea.value.substring(0, start) + text + textarea.value.substring(end);
        textarea.focus();
        textarea.selectionStart = textarea.selectionEnd = start + text.length;
        updatePreview();
      });
    });

    function updatePreview() {
      const targetType = document.getElementById('hubTargetType').value;
      let targetUser = employees[0] || { name: 'Ali Raza', username: 'ali', email: 'ali@nunno.cloud', designation: 'Closer' };
      if (targetType === 'user') {
        const selectedId = document.getElementById('hubUserSelect').value;
        const found = employees.find(e => e.id === selectedId);
        if (found) targetUser = found;
      } else if (targetType === 'custom') {
        targetUser = {
          name: document.getElementById('hubCustomName').value || 'Recipient',
          username: 'client',
          email: document.getElementById('hubCustomEmail').value || 'client@example.com',
          designation: 'External Recipient'
        };
      }

      let subject = document.getElementById('hubSubject').value || 'Preview Subject';
      let badge = document.getElementById('hubBadge').value || 'Notice';
      let body = document.getElementById('hubBodyHtml').value || '<p>No content</p>';

      const vars = {
        name: targetUser.name || 'Team Member',
        username: targetUser.username || 'user',
        email: targetUser.email || 'user@nunno.cloud',
        role: targetUser.designation || targetUser.role || 'Closer',
        amount: '5,000',
        method: 'EasyPaisa',
        account_title: targetUser.name || 'Beneficiary',
        account_number: '03081234567',
        tx_ref: 'TX-891042',
        password: 'Omni@' + Math.floor(Math.random() * 900 + 100) + '!',
        date: new Date().toLocaleDateString('en-PK', { month: 'short', day: 'numeric', year: 'numeric' }),
        portal_url: window.location.origin
      };

      for (const [k, v] of Object.entries(vars)) {
        subject = subject.replaceAll('{' + k + '}', v);
        body = body.replaceAll('{' + k + '}', v);
      }

      const previewHtml = `
        <!DOCTYPE html>
        <html>
        <head>
          <meta charset="utf-8">
          <style>
            body { font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;background:#f8fafc;margin:0;padding:20px;color:#1e293b; }
            .card { max-width:580px;margin:0 auto;background:#fff;border-radius:16px;border:1px solid #e2e8f0;overflow:hidden;box-shadow:0 8px 24px rgba(15,23,42,0.06); }
            .accent { height:5px;background:linear-gradient(90deg, #6366f1, #a855f7, #10b981); }
            .head { padding:20px 24px 14px;border-bottom:1px solid #f1f5f9;display:flex;justify-content:space-between;align-items:center; }
            .brand { font-size:18px;font-weight:900;letter-spacing:-0.03em;color:#0f172a; }
            .brand span { color:#6366f1; }
            .badge { font-size:10.5px;font-weight:800;background:#ede9fe;color:#5b21b6;padding:3px 10px;border-radius:999px; }
            .body { padding:24px;font-size:13.5px;line-height:1.6; }
            .btn { display:inline-block;padding:11px 24px;background:#10b981;color:#fff;text-decoration:none;font-weight:700;border-radius:10px;font-size:13px; }
            .foot { background:#f8fafc;padding:14px 24px;border-top:1px solid #e2e8f0;font-size:11px;color:#64748b; }
          </style>
        </head>
        <body>
          <div class="card">
            <div class="accent"></div>
            <div class="head">
              <div class="brand"><span>Omni</span>Desk</div>
              <div class="badge">${badge}</div>
            </div>
            <div class="body">
              ${body}
              <div style="text-align:center;margin-top:24px;">
                <a href="${vars.portal_url}" class="btn" target="_blank">Open OmniDesk Workspace &rarr;</a>
              </div>
            </div>
            <div class="foot">
              &copy; 2026 Nunno Technologies Inc. Confidential transmission for authorized closer personnel only.
            </div>
          </div>
        </body>
        </html>
      `;

      const iframe = document.getElementById('hubPreviewIframe');
      iframe.srcdoc = previewHtml;
    }

    // Submit Dispatch
    document.getElementById('emailDispatchForm').addEventListener('submit', async () => {
      const btn = document.getElementById('hubSendBtn');
      const alert = document.getElementById('hubAlert');
      alert.style.display = 'none';

      btn.disabled = true;
      btn.innerHTML = '<span>⏳ Dispatching...</span>';

      const targetType = document.getElementById('hubTargetType').value;
      const targetUserId = document.getElementById('hubUserSelect').value;
      const targetCustomEmail = document.getElementById('hubCustomEmail').value;
      const targetCustomName = document.getElementById('hubCustomName').value;
      const templateId = document.getElementById('hubTemplateSelect').value;
      const subject = document.getElementById('hubSubject').value;
      const badge = document.getElementById('hubBadge').value;
      const messageHtml = document.getElementById('hubBodyHtml').value;

      try {
        const res = await fetch('api/email_service.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            action: 'send_email',
            target_type: targetType,
            target_user_id: targetUserId,
            target_custom_email: targetCustomEmail,
            target_custom_name: targetCustomName,
            template_id: templateId,
            subject,
            badge,
            message_html: messageHtml
          })
        });
        const data = await res.json();
        if (data && data.success) {
          alert.className = 'auth-alert success';
          alert.textContent = `✅ ${data.message || 'Email successfully dispatched!'}`;
          alert.style.display = 'block';
        } else {
          alert.className = 'auth-alert error';
          alert.textContent = `⚠️ Error: ${data.error || 'Failed to dispatch email.'}`;
          alert.style.display = 'block';
        }
      } catch (err) {
        alert.className = 'auth-alert error';
        alert.textContent = `⚠️ Network/Server error: ${err.message}`;
        alert.style.display = 'block';
      } finally {
        btn.disabled = false;
        btn.innerHTML = '<span>✉️ Dispatch Email Now</span><span>&rarr;</span>';
      }
    });

    // Load History Logs
    async function loadLogs() {
      const tbody = document.getElementById('hubLogsTbody');
      tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;padding:20px;">Fetching logs...</td></tr>';
      try {
        const res = await fetch('api/email_service.php?action=get_logs').then(r => r.json());
        if (res && res.success && Array.isArray(res.logs) && res.logs.length) {
          tbody.innerHTML = res.logs.map(l => `
            <tr>
              <td class="mono" style="font-size:11px;color:var(--hub-muted);">${l.id}</td>
              <td><strong>${escapeHtml(l.recipient_name)}</strong><br><span style="font-size:11px;color:var(--hub-muted);">${escapeHtml(l.recipient_email)}</span></td>
              <td>${escapeHtml(l.subject)}</td>
              <td><span style="font-size:10.5px;padding:2px 7px;border-radius:4px;background:rgba(99,102,241,0.1);color:#6366f1;font-weight:700;">${l.template_id}</span></td>
              <td style="font-size:11px;color:var(--hub-muted);">${new Date(l.timestamp).toLocaleString()}</td>
              <td style="font-size:11px;">${escapeHtml(l.sent_by || 'Admin')}</td>
              <td><span style="padding:2px 7px;border-radius:999px;font-size:10px;font-weight:800;background:rgba(16,185,129,0.15);color:#059669;">✓ Sent</span></td>
            </tr>
          `).join('');
        } else {
          tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;padding:24px;color:var(--hub-muted);">No emails dispatched yet.</td></tr>';
        }
      } catch (_) {
        tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;padding:24px;color:#ef4444;">Failed to load email history.</td></tr>';
      }
    }
    document.getElementById('hubRefreshLogsBtn').addEventListener('click', loadLogs);

    function toggleSmtpFields() {
      const isSmtp = document.getElementById('hubTransport').value === 'smtp';
      document.getElementById('hubSmtpFields').style.display = isSmtp ? 'flex' : 'none';
    }
    document.getElementById('hubTransport').addEventListener('change', toggleSmtpFields);

    function escapeHtml(str) {
      return String(str || '').replace(/[&<>'"]/g, tag => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;'
      }[tag] || tag));
    }

    init();
  </script>
</body>
</html>
