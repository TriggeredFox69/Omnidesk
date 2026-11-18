<?php
/**
 * Billion-Dollar Startup — Company Administrator Portal (/admin)
 * Features: Employee Provisioning, Team Lead Tracking, and Employee Message Limiter
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
$isAdmin = ($currentUser && in_array(($currentUser['role'] ?? ''), ['admin', 'superadmin'], true));

// Logout action
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
    }
    session_destroy();
    header('Location: admin.php');
    exit;
}

// Load company details
$companiesFile = __DIR__ . '/data/companies.json';
$companies = file_exists($companiesFile) ? json_decode(file_get_contents($companiesFile), true) : [];
$companyId = ($currentUser['role'] ?? '') === 'superadmin' && !empty($_GET['company_id'])
    ? $_GET['company_id']
    : ($currentUser['company_id'] ?? 'COMP-001');

$activeCompany = null;
if (is_array($companies)) {
    foreach ($companies as $c) {
        if ($c['id'] === $companyId) {
            $activeCompany = $c;
            break;
        }
    }
}
if (!$activeCompany) {
    $activeCompany = [
        'id'                    => 'COMP-001',
        'name'                  => 'Nunno Official',
        'slug'                  => 'nunno',
        'plan'                  => 'Enterprise Pro',
        'default_message_limit' => 200
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
  <title>Company Admin Portal — <?php echo htmlspecialchars($activeCompany['name']); ?></title>
  <link rel="icon" type="image/png" href="assets/images/nunno-logo.png">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet">
  <script>
    (function(){
      try {
        if (localStorage.getItem('nunno_theme') === 'light' || (!localStorage.getItem('nunno_theme') && window.matchMedia && window.matchMedia('(prefers-color-scheme: light)').matches)) {
          document.documentElement.classList.add('theme-light');
        }
      } catch(_) {}
    })();
  </script>
  <style>
    :root {
      --bg-dark: #07070d;
      --bg-card: rgba(18, 17, 30, 0.85);
      --bg-card-hover: rgba(28, 27, 45, 0.95);
      --border-dark: rgba(139, 92, 246, 0.2);
      --border-glow: rgba(139, 92, 246, 0.45);
      --text-main: #f8fafc;
      --text-muted: #94a3b8;
      --accent-purple: #8b5cf6;
      --accent-emerald: #10b981;
      --accent-amber: #f59e0b;
      --accent-rose: #f43f5e;
    }
    html.theme-light {
      --bg-dark: #f8fafc;
      --bg-card: #ffffff;
      --bg-card-hover: #f1f5f9;
      --border-dark: #e2e8f0;
      --border-glow: rgba(139, 92, 246, 0.3);
      --text-main: #0f172a;
      --text-muted: #64748b;
    }
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      font-family: 'Inter', -apple-system, sans-serif;
      background-color: var(--bg-dark);
      color: var(--text-main);
      min-height: 100vh;
      -webkit-font-smoothing: antialiased;
      position: relative;
    }
    .bg-mesh {
      position: fixed; inset: 0; pointer-events: none; z-index: 0;
      background: radial-gradient(circle at 10% 10%, rgba(139, 92, 246, 0.08) 0%, transparent 40%),
                  radial-gradient(circle at 90% 90%, rgba(16, 185, 129, 0.06) 0%, transparent 40%);
    }
    .container { max-width: 1350px; margin: 0 auto; padding: 24px; position: relative; z-index: 1; }

    /* Top Bar */
    .top-bar {
      display: flex; align-items: center; justify-content: space-between;
      padding: 16px 24px; border-radius: 18px;
      background: var(--bg-card); border: 1px solid var(--border-dark);
      box-shadow: 0 10px 30px rgba(0,0,0,0.3); margin-bottom: 24px;
      backdrop-filter: blur(12px);
    }
    .brand-group { display: flex; align-items: center; gap: 14px; }
    .brand-logo {
      width: 44px; height: 44px; border-radius: 12px;
      background: linear-gradient(135deg, #10b981, #059669);
      display: flex; align-items: center; justify-content: center;
      font-size: 20px; font-weight: 900; color: #fff;
      box-shadow: 0 0 20px rgba(16, 185, 129, 0.4);
    }
    .brand-title { font-size: 18px; font-weight: 800; letter-spacing: -0.02em; }
    .brand-badge {
      font-size: 11px; font-weight: 700; text-transform: uppercase;
      padding: 3px 8px; border-radius: 6px;
      background: rgba(16, 185, 129, 0.2); color: #6ee7b7;
      border: 1px solid rgba(16, 185, 129, 0.4); margin-left: 8px;
    }
    .top-actions { display: flex; align-items: center; gap: 12px; }
    .btn {
      display: inline-flex; align-items: center; gap: 8px;
      padding: 10px 18px; border-radius: 12px; font-size: 13px; font-weight: 600;
      cursor: pointer; transition: all 0.2s ease; border: none; outline: none; text-decoration: none;
    }
    .btn-emerald {
      background: linear-gradient(135deg, #10b981, #059669);
      color: #fff; box-shadow: 0 4px 18px rgba(16, 185, 129, 0.35);
    }
    .btn-emerald:hover { transform: translateY(-1px); box-shadow: 0 6px 24px rgba(16, 185, 129, 0.5); }
    .btn-secondary {
      background: rgba(255,255,255,0.06); color: var(--text-main);
      border: 1px solid var(--border-dark);
    }
    .btn-secondary:hover { background: rgba(255,255,255,0.12); }
    .btn-danger { background: rgba(244, 63, 94, 0.15); color: #fb7185; border: 1px solid rgba(244, 63, 94, 0.3); }

    /* Metric Grid */
    .metric-grid {
      display: grid; grid-template-columns: repeat(auto-fit, minmax(230px, 1fr));
      gap: 16px; margin-bottom: 28px;
    }
    .metric-card {
      background: var(--bg-card); border: 1px solid var(--border-dark);
      border-radius: 16px; padding: 20px;
      box-shadow: 0 8px 24px rgba(0,0,0,0.2);
    }
    .metric-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 10px; }
    .metric-label { font-size: 12px; font-weight: 600; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.04em; }
    .metric-val { font-size: 28px; font-weight: 800; letter-spacing: -0.03em; margin-bottom: 4px; }
    .metric-sub { font-size: 12px; color: var(--text-muted); }

    /* Content Box */
    .content-box {
      background: var(--bg-card); border: 1px solid var(--border-dark);
      border-radius: 18px; padding: 24px; margin-bottom: 24px;
      box-shadow: 0 10px 30px rgba(0,0,0,0.25);
    }
    .box-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px; }
    .box-title { font-size: 16px; font-weight: 700; display: flex; align-items: center; gap: 10px; }

    /* Table & Limiter Styles */
    table { width: 100%; border-collapse: separate; border-spacing: 0 8px; }
    th {
      text-align: left; padding: 10px 16px; font-size: 12px; font-weight: 600;
      color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.03em;
    }
    td {
      padding: 14px 16px; background: rgba(255,255,255,0.02);
      font-size: 13px; font-weight: 500; border-top: 1px solid rgba(255,255,255,0.04);
      border-bottom: 1px solid rgba(255,255,255,0.04); vertical-align: middle;
    }
    tr td:first-child { border-left: 1px solid rgba(255,255,255,0.04); border-radius: 12px 0 0 12px; }
    tr td:last-child { border-right: 1px solid rgba(255,255,255,0.04); border-radius: 0 12px 12px 0; }
    tr:hover td { background: rgba(255,255,255,0.05); }

    .role-badge {
      display: inline-flex; align-items: center; gap: 4px;
      padding: 3px 8px; border-radius: 6px; font-size: 11px; font-weight: 700;
    }
    .role-admin { background: rgba(245, 158, 11, 0.15); color: #fbbf24; border: 1px solid rgba(245, 158, 11, 0.3); }
    .role-lead { background: rgba(139, 92, 246, 0.15); color: #c4b5fd; border: 1px solid rgba(139, 92, 246, 0.3); }
    .role-member { background: rgba(59, 130, 246, 0.15); color: #93c5fd; border: 1px solid rgba(59, 130, 246, 0.3); }

    /* Progress bar for message usage */
    .progress-track {
      width: 130px; height: 8px; border-radius: 4px;
      background: rgba(255,255,255,0.08); overflow: hidden; margin-top: 5px;
    }
    .progress-fill {
      height: 100%; border-radius: 4px; transition: width 0.3s;
    }
    .fill-green { background: #10b981; }
    .fill-amber { background: #f59e0b; }
    .fill-red { background: #f43f5e; }

    .limit-input-group {
      display: flex; align-items: center; gap: 8px;
    }
    .limit-input {
      width: 76px; padding: 6px 8px; border-radius: 8px;
      background: rgba(255,255,255,0.06); border: 1px solid var(--border-dark);
      color: #fff; font-size: 13px; font-weight: 600; text-align: center;
    }
    .limit-input:focus { outline: none; border-color: var(--accent-purple); }

    /* Modal */
    .modal-overlay {
      position: fixed; inset: 0; background: rgba(0,0,0,0.75);
      backdrop-filter: blur(8px); display: none; align-items: center; justify-content: center;
      z-index: 100; padding: 16px;
    }
    .modal-card {
      background: #0f0e1c; border: 1.5px solid var(--border-glow);
      border-radius: 20px; width: 100%; max-width: 520px; padding: 26px;
      box-shadow: 0 20px 50px rgba(0,0,0,0.8);
    }
    .form-group { margin-bottom: 14px; }
    .form-label { display: block; font-size: 12px; font-weight: 600; color: var(--text-muted); margin-bottom: 5px; }
    .form-control {
      width: 100%; padding: 10px 12px; border-radius: 10px;
      background: rgba(255,255,255,0.05); border: 1px solid var(--border-dark);
      color: #fff; font-size: 13px; font-family: inherit;
    }
    .form-control:focus { outline: none; border-color: var(--accent-emerald); }
    .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }

    .login-center { min-height: 80vh; display: flex; align-items: center; justify-content: center; }
    .login-box {
      width: 100%; max-width: 420px; padding: 36px 28px;
      background: var(--bg-card); border: 1.5px solid var(--border-glow);
      border-radius: 24px; text-align: center;
    }
  </style>
</head>
<body>
<div class="bg-mesh"></div>

<div class="container">

<?php if (!$isAdmin): ?>
  <!-- COMPANY ADMIN LOGIN GATE -->
  <div class="login-center">
    <div class="login-box">
      <div class="brand-logo" style="margin: 0 auto 16px; width: 64px; height: 64px; font-size: 30px;">🏢</div>
      <h2 style="font-size: 22px; font-weight: 800; margin-bottom: 6px;">Company Admin Portal</h2>
      <p style="font-size: 13px; color: var(--text-muted); margin-bottom: 24px;">Sign in to manage employees, permissions & message limits</p>

      <form id="adminLoginForm" onsubmit="handleAdminLogin(event)">
        <div class="form-group" style="text-align: left;">
          <label class="form-label">Username / Email</label>
          <input type="text" id="aUsername" class="form-control" placeholder="admin" required autofocus>
        </div>
        <div class="form-group" style="text-align: left; margin-bottom: 24px;">
          <label class="form-label">Password</label>
          <input type="password" id="aPassword" class="form-control" placeholder="••••••••" required>
        </div>
        <div id="loginError" style="display: none; padding: 10px; border-radius: 8px; background: rgba(244, 63, 94, 0.2); color: #fb7185; font-size: 13px; margin-bottom: 16px;"></div>
        <button type="submit" id="adminLoginBtn" class="btn btn-emerald" style="width: 100%; justify-content: center; padding: 12px;">
          Access Company Portal 🏢
        </button>
      </form>
      <div style="margin-top: 20px; font-size: 12px; color: var(--text-muted);">
        <a href="login.php" style="color: var(--accent-emerald); text-decoration: none;">← Return to Closer / TL Login</a>
      </div>
    </div>
  </div>

<?php else: ?>
  <!-- COMPANY ADMIN DASHBOARD -->
  <header class="top-bar">
    <div class="brand-group">
      <div class="brand-logo">🏢</div>
      <div>
        <div style="display: flex; align-items: center;">
          <span class="brand-title"><?php echo htmlspecialchars($activeCompany['name']); ?></span>
          <span class="brand-badge">Company Admin</span>
        </div>
        <div style="font-size: 12px; color: var(--text-muted);">
          ID: <?php echo htmlspecialchars($activeCompany['id']); ?> &bull; Tier: <?php echo htmlspecialchars($activeCompany['plan'] ?? 'Pro'); ?>
        </div>
      </div>
    </div>
    <div class="top-actions">
      <button class="btn btn-emerald" onclick="openAddEmployeeModal()">
        <span>➕</span> Add Employee
      </button>
      <a href="index.html?company_id=<?php echo urlencode($activeCompany['id']); ?>" class="btn btn-secondary" target="_blank">
        <span>💬</span> Open Sales Desk &amp; Chats
      </a>
      <a href="admin.php?action=logout" class="btn btn-danger">
        <span>🚪</span> Logout
      </a>
    </div>
  </header>

  <!-- Metric Overview -->
  <div class="metric-grid">
    <div class="metric-card">
      <div class="metric-header">
        <span class="metric-label">Active Employees</span>
        <div style="color: #6ee7b7; font-size: 18px;">👥</div>
      </div>
      <div class="metric-val" id="cntEmployees">-</div>
      <div class="metric-sub"><span id="cntTLs">-</span> Team Leads &bull; <span id="cntTMs">-</span> Closers</div>
    </div>

    <div class="metric-card">
      <div class="metric-header">
        <span class="metric-label">Messages Today (Company)</span>
        <div style="color: #fde68a; font-size: 18px;">💬</div>
      </div>
      <div class="metric-val" id="cntMessagesToday">-</div>
      <div class="metric-sub">Across all active closers</div>
    </div>

    <div class="metric-card">
      <div class="metric-header">
        <span class="metric-label">TL Commissions Accrued</span>
        <div style="color: #c4b5fd; font-size: 18px;">💰</div>
      </div>
      <div class="metric-val" id="cntCommissions">Rs. 0</div>
      <div class="metric-sub">500 PKR earned per joined TM</div>
    </div>

    <div class="metric-card">
      <div class="metric-header">
        <span class="metric-label">WhatsApp Status</span>
        <div style="color: #34d399; font-size: 18px;">📱</div>
      </div>
      <div class="metric-val" style="font-size: 20px; color: #34d399;">Multi-Session 🟢</div>
      <div class="metric-sub">Per-user isolated QR journeys</div>
    </div>
  </div>

  <!-- Employee Roster with Message Limiter Control -->
  <div class="content-box">
    <div class="box-header">
      <div class="box-title">
        <span>⚙️</span> Employee Roster & Message Limiter Control
      </div>
      <div style="font-size: 13px; color: var(--text-muted);">
        Configure daily message limits per closer. Enter <span style="font-family: monospace; color: #a78bfa;">-1</span> for Unlimited.
      </div>
    </div>

    <div style="overflow-x: auto;">
      <table>
        <thead>
          <tr>
            <th>Employee</th>
            <th>Role</th>
            <th>WhatsApp Session</th>
            <th>Today's Usage (Sent / Limit)</th>
            <th>Daily Message Limit</th>
            <th>Account Status</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody id="employeeTableBody">
          <tr><td colspan="7" style="text-align: center; padding: 24px; color: var(--text-muted);">Loading employee roster...</td></tr>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Team Leader Commission Breakdown -->
  <div class="content-box">
    <div class="box-header">
      <div class="box-title">
        <span>👑</span> Team Leader (TL) Commission & Roster Overview
      </div>
      <div style="font-size: 13px; color: var(--text-muted);">
        Team Leads recruit & provision TMs and earn Rs. 500 per activated TM.
      </div>
    </div>

    <div style="overflow-x: auto;">
      <table>
        <thead>
          <tr>
            <th>Team Lead</th>
            <th>Direct TMs Recruited</th>
            <th>Commission Rate</th>
            <th>Accrued Balance</th>
            <th>Payout Actions</th>
          </tr>
        </thead>
        <tbody id="tlTableBody">
          <tr><td colspan="5" style="text-align: center; padding: 20px; color: var(--text-muted);">Loading Team Lead records...</td></tr>
        </tbody>
      </table>
    </div>
  </div>

<?php endif; ?>

</div>

<!-- ADD EMPLOYEE MODAL -->
<div class="modal-overlay" id="addEmpModal">
  <div class="modal-card">
    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 18px;">
      <h3 style="font-size: 17px; font-weight: 800;">➕ Provision Company Employee</h3>
      <button onclick="closeAddEmployeeModal()" style="background:none; border:none; color:var(--text-muted); font-size:22px; cursor:pointer;">&times;</button>
    </div>

    <form id="addEmpForm" onsubmit="handleAddEmployee(event)">
      <div class="form-group">
        <label class="form-label">Full Name *</label>
        <input type="text" id="empName" class="form-control" placeholder="e.g. Usman Ali" required>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Username *</label>
          <input type="text" id="empUsername" class="form-control" placeholder="e.g. usman" required>
        </div>
        <div class="form-group">
          <label class="form-label">Password *</label>
          <input type="text" id="empPassword" class="form-control" placeholder="Min 6 characters" required>
        </div>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Role</label>
          <select id="empRole" class="form-control">
            <option value="member">Team Member (Sales Closer)</option>
            <option value="lead">Team Leader (Commission Lead)</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Gender (Voice Notes)</label>
          <select id="empGender" class="form-control">
            <option value="female">Female (Voice Recording Allowed)</option>
            <option value="male">Male (Text Only Policy)</option>
          </select>
        </div>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Initial Message Limit (Day)</label>
          <input type="number" id="empLimit" class="form-control" value="150" min="10" max="5000">
        </div>
        <div class="form-group">
          <label class="form-label">WhatsApp Phone (Optional)</label>
          <input type="text" id="empPhone" class="form-control" placeholder="+923001234567">
        </div>
      </div>

      <div id="addEmpError" style="display: none; padding: 10px; border-radius: 8px; background: rgba(244, 63, 94, 0.2); color: #fb7185; font-size: 13px; margin-bottom: 14px;"></div>

      <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 18px;">
        <button type="button" class="btn btn-secondary" onclick="closeAddEmployeeModal()">Cancel</button>
        <button type="submit" id="addEmpBtn" class="btn btn-emerald">Provision Account 🚀</button>
      </div>
    </form>
  </div>
</div>

<script>
async function handleAdminLogin(e) {
  e.preventDefault();
  const btn = document.getElementById('adminLoginBtn');
  const errBox = document.getElementById('loginError');
  btn.disabled = true;
  btn.textContent = 'Authenticating...';
  errBox.style.display = 'none';

  try {
    const res = await fetch('api/auth.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        action: 'login',
        username: document.getElementById('aUsername').value.trim(),
        password: document.getElementById('aPassword').value.trim()
      })
    });
    const data = await res.json();
    if (data.success && data.user && (data.user.role === 'admin' || data.user.role === 'superadmin')) {
      window.location.reload();
    } else {
      errBox.textContent = data.error || 'Access Denied: Company Admin privileges required.';
      errBox.style.display = 'block';
    }
  } catch (err) {
    errBox.textContent = 'Server communication error.';
    errBox.style.display = 'block';
  } finally {
    btn.disabled = false;
    btn.textContent = 'Access Company Portal 🏢';
  }
}

function openAddEmployeeModal() { document.getElementById('addEmpModal').style.display = 'flex'; }
function closeAddEmployeeModal() { document.getElementById('addEmpModal').style.display = 'none'; }

async function loadCompanyEmployees() {
  try {
    const res = await fetch('api/auth.php?action=list_company_employees&company_id=<?php echo urlencode($companyId); ?>');
    const data = await res.json();
    if (!data.success) return;

    const emps = data.employees || [];
    let tls = 0, tms = 0, msgs = 0, commTotal = 0;

    const tbody = document.getElementById('employeeTableBody');
    if (!emps.length) {
      tbody.innerHTML = '<tr><td colspan="7" style="text-align:center; padding:20px;">No employees in roster. Click "Add Employee" above!</td></tr>';
      return;
    }

    const tlList = [];

    tbody.innerHTML = emps.map(e => {
      if (e.role === 'lead') {
        tls++;
        tlList.push(e);
      } else if (e.role === 'member') {
        tms++;
      }

      const m = e.quota_metrics || { limit: 100, sent: 0, percent: 0, remaining: 100 };
      msgs += m.sent;
      commTotal += (Number(e.tl_commission_balance) || 0);

      const colorClass = m.percent > 85 ? 'fill-red' : (m.percent > 60 ? 'fill-amber' : 'fill-green');
      const limitVal = m.limit < 0 ? -1 : m.limit;

      return `
        <tr>
          <td>
            <div style="font-weight: 700;">${e.name}</div>
            <div style="font-size: 11px; color: var(--text-muted); font-family: monospace;">@${e.username} &bull; ${e.id}</div>
          </td>
          <td>
            <span class="role-badge ${e.role === 'lead' ? 'role-lead' : 'role-member'}">
              ${e.role === 'lead' ? '👑 Team Lead' : '🎯 Team Member'}
            </span>
            <div style="font-size: 10px; color: var(--text-muted); margin-top: 3px;">
              ${e.gender === 'female' ? '🎙️ Voice Enabled' : '🚫 Text Only'}
            </div>
          </td>
          <td>
            ${e.phone ? `<span style="color:#34d399; font-weight:600;">🟢 Linked</span><div style="font-size:11px; color:var(--text-muted); font-family:monospace;">${e.phone}</div>` : '<span style="color:var(--text-muted);">⚪ Unlinked (Pending QR)</span>'}
          </td>
          <td>
            <div style="font-size: 12px; font-weight: 700;">
              ${m.sent} / ${m.limit < 0 ? '∞ Unlimited' : m.limit}
            </div>
            ${m.limit > 0 ? `
              <div class="progress-track">
                <div class="progress-fill ${colorClass}" style="width: ${m.percent}%;"></div>
              </div>
            ` : '<div style="font-size: 10px; color: #a78bfa;">No Cap</div>'}
          </td>
          <td>
            <div class="limit-input-group">
              <input type="number" id="limit_${e.id}" class="limit-input" value="${limitVal}" title="Set -1 for Unlimited">
              <button class="btn btn-secondary" style="padding: 5px 10px; font-size: 11px;" onclick="saveMessageLimit('${e.id}')">
                💾 Save
              </button>
            </div>
          </td>
          <td>
            <span style="font-size: 11px; font-weight: 700; color: ${e.status === 'active' ? '#34d399' : '#fb7185'};">
              ● ${e.status === 'active' ? 'Active' : 'Suspended'}
            </span>
          </td>
          <td>
            <div style="display: flex; gap: 6px;">
              <button class="btn btn-secondary" style="padding: 4px 8px; font-size: 11px;" onclick="resetCountToday('${e.id}')" title="Reset today's counter to 0">
                🔄 Reset
              </button>
              <button class="btn btn-secondary" style="padding: 4px 8px; font-size: 11px;" onclick="toggleEmpStatus('${e.id}', '${e.status}')">
                ${e.status === 'active' ? '⏸️ Suspend' : '▶️ Activate'}
              </button>
            </div>
          </td>
        </tr>
      `;
    }).join('');

    document.getElementById('cntEmployees').textContent = emps.length;
    document.getElementById('cntTLs').textContent = tls;
    document.getElementById('cntTMs').textContent = tms;
    document.getElementById('cntMessagesToday').textContent = msgs;
    document.getElementById('cntCommissions').textContent = 'Rs. ' + commTotal.toLocaleString();

    // Render TLs Table
    const tlTbody = document.getElementById('tlTableBody');
    if (!tlList.length) {
      tlTbody.innerHTML = '<tr><td colspan="5" style="text-align:center; padding:16px;">No Team Leads provisioned yet.</td></tr>';
    } else {
      tlTbody.innerHTML = tlList.map(tl => {
        const directTMs = emps.filter(u => u.tl_id === tl.id || u.joined_via_tl === tl.id);
        const bal = tl.tl_commission_balance || (directTMs.length * 500);
        return `
          <tr>
            <td>
              <div style="font-weight: 700;">${tl.name}</div>
              <div style="font-size: 11px; color: var(--text-muted);">@${tl.username}</div>
            </td>
            <td><strong>${directTMs.length}</strong> TMs under team</td>
            <td>Rs. 500 per TM</td>
            <td><strong style="color: #34d399; font-size: 15px;">PKR ${bal.toLocaleString()}</strong></td>
            <td>
              <span style="font-size: 11px; padding: 3px 8px; border-radius: 6px; background: rgba(16, 185, 129, 0.15); color: #6ee7b7;">
                Ready for Settlement
              </span>
            </td>
          </tr>
        `;
      }).join('');
    }

  } catch (err) {
    console.error('Error loading employees:', err);
  }
}

async function saveMessageLimit(empId) {
  const input = document.getElementById('limit_' + empId);
  const newLimit = parseInt(input.value, 10);
  try {
    const res = await fetch('api/auth.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        action: 'update_message_limit',
        id: empId,
        message_limit: newLimit
      })
    });
    const d = await res.json();
    if (d.success) {
      alert(d.message || 'Message limit updated successfully!');
      loadCompanyEmployees();
    } else {
      alert(d.error || 'Failed to update message limit.');
    }
  } catch (err) {
    alert('Network error');
  }
}

async function resetCountToday(empId) {
  if (!confirm("Reset this employee's message count back to 0 for today?")) return;
  try {
    const res = await fetch('api/auth.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        action: 'update_message_limit',
        id: empId,
        message_limit: parseInt(document.getElementById('limit_' + empId).value, 10),
        reset_today: true
      })
    });
    const d = await res.json();
    if (d.success) {
      alert("Today's message count reset to 0!");
      loadCompanyEmployees();
    }
  } catch (err) {
    alert('Network error');
  }
}

async function toggleEmpStatus(empId, currentStatus) {
  try {
    const res = await fetch('api/auth.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'toggle_status', id: empId })
    });
    const d = await res.json();
    if (d.success) loadCompanyEmployees();
    else alert(d.error || 'Failed to update employee status');
  } catch (err) {
    alert('Network error');
  }
}

async function handleAddEmployee(e) {
  e.preventDefault();
  const btn = document.getElementById('addEmpBtn');
  const errBox = document.getElementById('addEmpError');
  btn.disabled = true;
  btn.textContent = 'Provisioning...';
  errBox.style.display = 'none';

  const payload = {
    action: 'create_employee',
    name: document.getElementById('empName').value.trim(),
    username: document.getElementById('empUsername').value.trim(),
    password: document.getElementById('empPassword').value.trim(),
    role: document.getElementById('empRole').value,
    gender: document.getElementById('empGender').value,
    phone: document.getElementById('empPhone').value.trim(),
    message_limit: parseInt(document.getElementById('empLimit').value, 10)
  };

  try {
    const res = await fetch('api/auth.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    });
    const data = await res.json();
    if (data.success) {
      alert(`Account provisioned successfully for ${payload.name} (@${payload.username})!`);
      closeAddEmployeeModal();
      document.getElementById('addEmpForm').reset();
      loadCompanyEmployees();
    } else {
      errBox.textContent = data.error || 'Failed to create account.';
      errBox.style.display = 'block';
    }
  } catch (err) {
    errBox.textContent = 'Communication failure.';
    errBox.style.display = 'block';
  } finally {
    btn.disabled = false;
    btn.textContent = 'Provision Account 🚀';
  }
}

<?php if ($isAdmin): ?>
document.addEventListener('DOMContentLoaded', loadCompanyEmployees);
<?php endif; ?>
</script>
</body>
</html>
