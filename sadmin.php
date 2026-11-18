<?php
/**
 * Billion-Dollar Startup — Master Super Administrator Dashboard (/sadmin)
 * Platform Owner Command Center: Multi-Company Provisioning & Global Telemetry
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
$isSuperAdmin = ($currentUser && ($currentUser['role'] ?? '') === 'superadmin');

// Quick logout action
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
    }
    session_destroy();
    header('Location: sadmin.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
  <title>Super Admin Command Center — Global Multi-Tenant Control</title>
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
      --accent-purple-hover: #a78bfa;
      --accent-emerald: #10b981;
      --accent-emerald-glow: rgba(16, 185, 129, 0.35);
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
      background: radial-gradient(circle at 15% 15%, rgba(139, 92, 246, 0.08) 0%, transparent 45%),
                  radial-gradient(circle at 85% 85%, rgba(16, 185, 129, 0.06) 0%, transparent 45%);
    }
    .container { max-width: 1400px; margin: 0 auto; padding: 24px; position: relative; z-index: 1; }
    
    /* Top Navigation Header */
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
      background: linear-gradient(135deg, #8b5cf6, #3b82f6);
      display: flex; align-items: center; justify-content: center;
      font-size: 22px; font-weight: 900; color: #fff;
      box-shadow: 0 0 20px rgba(139, 92, 246, 0.5);
    }
    .brand-title { font-size: 18px; font-weight: 800; letter-spacing: -0.02em; }
    .brand-badge {
      font-size: 11px; font-weight: 700; text-transform: uppercase;
      padding: 3px 8px; border-radius: 6px;
      background: rgba(139, 92, 246, 0.2); color: #c4b5fd;
      border: 1px solid rgba(139, 92, 246, 0.4); margin-left: 8px;
    }
    .top-actions { display: flex; align-items: center; gap: 12px; }
    .btn {
      display: inline-flex; align-items: center; gap: 8px;
      padding: 10px 18px; border-radius: 12px; font-size: 13px; font-weight: 600;
      cursor: pointer; transition: all 0.2s ease; border: none; outline: none; text-decoration: none;
    }
    .btn-primary {
      background: linear-gradient(135deg, #8b5cf6, #6d28d9);
      color: #fff; box-shadow: 0 4px 18px rgba(139, 92, 246, 0.4);
    }
    .btn-primary:hover { transform: translateY(-1px); box-shadow: 0 6px 24px rgba(139, 92, 246, 0.6); }
    .btn-secondary {
      background: rgba(255,255,255,0.06); color: var(--text-main);
      border: 1px solid var(--border-dark);
    }
    .btn-secondary:hover { background: rgba(255,255,255,0.12); }
    .btn-danger { background: rgba(244, 63, 94, 0.15); color: #fb7185; border: 1px solid rgba(244, 63, 94, 0.3); }
    .btn-danger:hover { background: rgba(244, 63, 94, 0.25); }

    /* Metric Grid */
    .metric-grid {
      display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
      gap: 18px; margin-bottom: 28px;
    }
    .metric-card {
      background: var(--bg-card); border: 1px solid var(--border-dark);
      border-radius: 16px; padding: 20px;
      box-shadow: 0 8px 24px rgba(0,0,0,0.2);
      transition: transform 0.2s, border-color 0.2s;
    }
    .metric-card:hover { transform: translateY(-2px); border-color: var(--border-glow); }
    .metric-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px; }
    .metric-label { font-size: 12px; font-weight: 600; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.04em; }
    .metric-icon { width: 34px; height: 34px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 16px; }
    .metric-val { font-size: 30px; font-weight: 800; letter-spacing: -0.03em; margin-bottom: 4px; }
    .metric-sub { font-size: 12px; color: var(--text-muted); display: flex; align-items: center; gap: 6px; }
    .pill-green { color: #34d399; font-weight: 600; }

    /* Main Section Tabs & Tables */
    .content-box {
      background: var(--bg-card); border: 1px solid var(--border-dark);
      border-radius: 18px; padding: 24px; margin-bottom: 24px;
      box-shadow: 0 10px 30px rgba(0,0,0,0.25);
    }
    .box-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px; }
    .box-title { font-size: 16px; font-weight: 700; letter-spacing: -0.01em; display: flex; align-items: center; gap: 10px; }

    table { width: 100%; border-collapse: separate; border-spacing: 0 8px; }
    th {
      text-align: left; padding: 10px 16px; font-size: 12px; font-weight: 600;
      color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.03em;
    }
    td {
      padding: 14px 16px; background: rgba(255,255,255,0.02);
      font-size: 13px; font-weight: 500; border-top: 1px solid rgba(255,255,255,0.04);
      border-bottom: 1px solid rgba(255,255,255,0.04);
    }
    tr td:first-child { border-left: 1px solid rgba(255,255,255,0.04); border-radius: 12px 0 0 12px; }
    tr td:last-child { border-right: 1px solid rgba(255,255,255,0.04); border-radius: 0 12px 12px 0; }
    tr:hover td { background: rgba(255,255,255,0.05); }

    .status-badge {
      display: inline-flex; align-items: center; gap: 6px;
      padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 700;
      text-transform: uppercase;
    }
    .status-active { background: rgba(16, 185, 129, 0.15); color: #34d399; border: 1px solid rgba(16, 185, 129, 0.3); }
    .status-suspended { background: rgba(244, 63, 94, 0.15); color: #fb7185; border: 1px solid rgba(244, 63, 94, 0.3); }

    /* Modal Styling */
    .modal-overlay {
      position: fixed; inset: 0; background: rgba(0,0,0,0.75);
      backdrop-filter: blur(8px); display: none; align-items: center; justify-content: center;
      z-index: 100; padding: 16px;
    }
    .modal-card {
      background: #0f0e1c; border: 1.5px solid var(--border-glow);
      border-radius: 20px; width: 100%; max-width: 580px; padding: 28px;
      box-shadow: 0 20px 50px rgba(0,0,0,0.8); position: relative;
    }
    .modal-title { font-size: 18px; font-weight: 800; margin-bottom: 18px; }
    .form-group { margin-bottom: 16px; }
    .form-label { display: block; font-size: 12px; font-weight: 600; color: var(--text-muted); margin-bottom: 6px; }
    .form-control {
      width: 100%; padding: 12px 14px; border-radius: 10px;
      background: rgba(255,255,255,0.05); border: 1px solid var(--border-dark);
      color: #fff; font-size: 14px; font-family: inherit; transition: border-color 0.2s;
    }
    .form-control:focus { outline: none; border-color: var(--accent-purple); box-shadow: 0 0 12px rgba(139, 92, 246, 0.3); }
    .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }

    /* Login Form if not logged in */
    .login-center {
      min-height: 80vh; display: flex; align-items: center; justify-content: center;
    }
    .login-box {
      width: 100%; max-width: 420px; padding: 36px 28px;
      background: var(--bg-card); border: 1.5px solid var(--border-glow);
      border-radius: 24px; box-shadow: 0 25px 60px rgba(0,0,0,0.6);
      text-align: center;
    }
  </style>
</head>
<body>
<div class="bg-mesh"></div>

<div class="container">

<?php if (!$isSuperAdmin): ?>
  <!-- SUPER ADMIN AUTHENTICATION GATE -->
  <div class="login-center">
    <div class="login-box">
      <div class="brand-logo" style="margin: 0 auto 16px; width: 64px; height: 64px; font-size: 30px;">⚡</div>
      <h2 style="font-size: 22px; font-weight: 800; margin-bottom: 6px;">Super Admin Portal</h2>
      <p style="font-size: 13px; color: var(--text-muted); margin-bottom: 24px;">Platform Owner Master Command Access</p>

      <form id="sadminLoginForm" onsubmit="handleSadminLogin(event)">
        <div class="form-group" style="text-align: left;">
          <label class="form-label">Username / Key</label>
          <input type="text" id="sUsername" class="form-control" placeholder="sadmin" required autofocus>
        </div>
        <div class="form-group" style="text-align: left; margin-bottom: 24px;">
          <label class="form-label">Master Password</label>
          <input type="password" id="sPassword" class="form-control" placeholder="••••••••" required>
        </div>
        <div id="loginError" style="display: none; padding: 10px; border-radius: 8px; background: rgba(244, 63, 94, 0.2); color: #fb7185; font-size: 13px; margin-bottom: 16px;"></div>
        <button type="submit" id="loginBtn" class="btn btn-primary" style="width: 100%; justify-content: center; padding: 12px; font-size: 14px;">
          Unlock Platform Command 🚀
        </button>
      </form>
      <div style="margin-top: 20px; font-size: 12px; color: var(--text-muted);">
        <a href="login.php" style="color: var(--accent-purple); text-decoration: none;">← Return to Closer / TL Login</a>
      </div>
    </div>
  </div>

<?php else: ?>
  <!-- SUPER ADMIN AUTHENTICATED COMMAND CENTER -->
  <header class="top-bar">
    <div class="brand-group">
      <div class="brand-logo">⚡</div>
      <div>
        <div style="display: flex; align-items: center;">
          <span class="brand-title">Startup Super Command</span>
          <span class="brand-badge">SADMIN / Platform Owner</span>
        </div>
        <div style="font-size: 12px; color: var(--text-muted);">Master multi-company telemetry & enterprise infrastructure</div>
      </div>
    </div>
    <div class="top-actions">
      <button class="btn btn-primary" onclick="openProvisionModal()">
        <span>➕</span> Provision New Company
      </button>
      <button class="btn btn-secondary" onclick="loadDashboardData()">
        <span>🔄</span> Refresh
      </button>
      <a href="sadmin.php?action=logout" class="btn btn-danger">
        <span>🚪</span> Exit
      </a>
    </div>
  </header>

  <!-- Global Metrics -->
  <div class="metric-grid">
    <div class="metric-card">
      <div class="metric-header">
        <span class="metric-label">Registered Companies</span>
        <div class="metric-icon" style="background: rgba(139, 92, 246, 0.2); color: #c4b5fd;">🏢</div>
      </div>
      <div class="metric-val" id="mTotalCompanies">-</div>
      <div class="metric-sub"><span class="pill-green" id="mActiveCompanies">-</span> active on platform</div>
    </div>

    <div class="metric-card">
      <div class="metric-header">
        <span class="metric-label">Total Personnel</span>
        <div class="metric-icon" style="background: rgba(59, 130, 246, 0.2); color: #93c5fd;">👥</div>
      </div>
      <div class="metric-val" id="mTotalEmployees">-</div>
      <div class="metric-sub"><span id="mTotalTLs">-</span> Team Leads &bull; <span id="mTotalTMs">-</span> Closers</div>
    </div>

    <div class="metric-card">
      <div class="metric-header">
        <span class="metric-label">Active WhatsApp Sessions</span>
        <div class="metric-icon" style="background: rgba(16, 185, 129, 0.2); color: #6ee7b7;">📱</div>
      </div>
      <div class="metric-val" id="mActiveSessions">-</div>
      <div class="metric-sub"><span class="pill-green">● Isolated & Duplex</span> Zero-Chromium</div>
    </div>

    <div class="metric-card">
      <div class="metric-header">
        <span class="metric-label">Messages Today (Global)</span>
        <div class="metric-icon" style="background: rgba(245, 158, 11, 0.2); color: #fde68a;">💬</div>
      </div>
      <div class="metric-val" id="mMessagesToday">-</div>
      <div class="metric-sub"><span class="pill-green">100% Rate-Limited</span> Bridge Healthy</div>
    </div>
  </div>

  <!-- Multi-Company Matrix -->
  <div class="content-box">
    <div class="box-header">
      <div class="box-title">
        <span>🏢</span> Multi-Company Management Matrix
      </div>
      <div style="font-size: 13px; color: var(--text-muted);">
        Click "Open Portal" to inspect and manage any company.
      </div>
    </div>

    <div style="overflow-x: auto;">
      <table>
        <thead>
          <tr>
            <th>Company Name / Slug</th>
            <th>Plan Tier</th>
            <th>Owner & Admin</th>
            <th>Seats / TMs</th>
            <th>Messages Today</th>
            <th>Status</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody id="companyTableBody">
          <tr><td colspan="7" style="text-align: center; padding: 24px; color: var(--text-muted);">Loading companies telemetry...</td></tr>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Company Number Profiles & Fallback Trunk Pool -->
  <div class="content-box">
    <div class="box-header">
      <div>
        <div class="box-title">
          <span>📱</span> Company Number Profiles & Fallback Pool
        </div>
        <div style="font-size: 13px; color: var(--text-muted); margin-top: 3px;">
          Company-owned WhatsApp numbers assigned to closers who cannot arrange their own phone until they pair a personal device.
        </div>
      </div>
      <button class="btn btn-emerald" style="padding: 8px 16px; font-size: 12px;" onclick="openAddNumberModal()">
        <span>➕</span> Add Number Profile
      </button>
    </div>

    <div style="overflow-x: auto;">
      <table>
        <thead>
          <tr>
            <th>Number Profile & Tag</th>
            <th>Daily Message Capacity</th>
            <th>Assigned Closer</th>
            <th>Pool State</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody id="numberTableBody">
          <tr><td colspan="5" style="text-align: center; padding: 20px; color: var(--text-muted);">Loading company number profiles...</td></tr>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Global Audit Feed -->
  <div class="content-box">
    <div class="box-header">
      <div class="box-title">
        <span>📜</span> Global Activity & Audit Trail
      </div>
      <button class="btn btn-secondary" style="padding: 6px 12px; font-size: 12px;" onclick="loadAuditLogs()">
        Refresh Feed
      </button>
    </div>

    <div style="max-height: 280px; overflow-y: auto;" id="auditContainer">
      <div style="padding: 16px; text-align: center; color: var(--text-muted);">Loading platform logs...</div>
    </div>
  </div>

<?php endif; ?>

</div>

<!-- ADD NUMBER PROFILE MODAL -->
<div class="modal-overlay" id="addNumberModal">
  <div class="modal-card" style="max-width: 480px;">
    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 18px;">
      <h3 class="modal-title">📱 Add Company Number Profile</h3>
      <button onclick="closeAddNumberModal()" style="background:none; border:none; color:var(--text-muted); font-size:22px; cursor:pointer;">&times;</button>
    </div>
    <form id="addNumberForm" onsubmit="handleAddNumber(event)">
      <div class="form-group">
        <label class="form-label">WhatsApp Number *</label>
        <input type="text" id="numPhone" class="form-control" placeholder="e.g. +923318036576" required>
      </div>
      <div class="form-group">
        <label class="form-label">Profile Label / Trunk Name *</label>
        <input type="text" id="numLabel" class="form-control" placeholder="e.g. Main Sales Pool #1" required>
      </div>
      <div class="form-group">
        <label class="form-label">Daily Capacity (Messages/Day)</label>
        <input type="number" id="numCapacity" class="form-control" value="500" min="50" max="5000">
      </div>
      <div id="addNumError" style="display: none; padding: 10px; border-radius: 8px; background: rgba(244, 63, 94, 0.2); color: #fb7185; font-size: 13px; margin-bottom: 14px;"></div>
      <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 18px;">
        <button type="button" class="btn btn-secondary" onclick="closeAddNumberModal()">Cancel</button>
        <button type="submit" id="addNumSubmitBtn" class="btn btn-emerald">Add to Company Pool 📱</button>
      </div>
    </form>
  </div>
</div>

<!-- ASSIGN NUMBER TO CLOSER MODAL -->
<div class="modal-overlay" id="assignNumberModal">
  <div class="modal-card" style="max-width: 480px;">
    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 18px;">
      <h3 class="modal-title">👤 Assign Number to Closer</h3>
      <button onclick="closeAssignModal()" style="background:none; border:none; color:var(--text-muted); font-size:22px; cursor:pointer;">&times;</button>
    </div>
    <form id="assignNumberForm" onsubmit="handleAssignNumber(event)">
      <input type="hidden" id="assignProfId">
      <div style="padding: 12px 14px; border-radius: 10px; background: rgba(139, 92, 246, 0.15); border: 1px solid var(--border-glow); margin-bottom: 16px; font-size: 13px;">
        Assigning: <strong id="assignProfLabel">-</strong>
      </div>
      <div class="form-group">
        <label class="form-label">Select Employee without Phone</label>
        <select id="assignUserSelect" class="form-control" required>
          <option value="">Loading eligible employees...</option>
        </select>
      </div>
      <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 18px;">
        <button type="button" class="btn btn-secondary" onclick="closeAssignModal()">Cancel</button>
        <button type="submit" id="assignSubmitBtn" class="btn btn-primary">Confirm Assignment 🚀</button>
      </div>
    </form>
  </div>
</div>

<!-- PROVISION COMPANY MODAL -->
<div class="modal-overlay" id="provisionModal">
  <div class="modal-card">
    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px;">
      <h3 class="modal-title">➕ Provision New Company</h3>
      <button onclick="closeProvisionModal()" style="background:none; border:none; color:var(--text-muted); font-size:22px; cursor:pointer;">&times;</button>
    </div>

    <form id="provisionForm" onsubmit="handleProvisionCompany(event)">
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Company Brand Name *</label>
          <input type="text" id="pCompName" class="form-control" placeholder="e.g. Apex Global" required oninput="generateSlug(this.value)">
        </div>
        <div class="form-group">
          <label class="form-label">System Slug (Code) *</label>
          <input type="text" id="pCompSlug" class="form-control" placeholder="e.g. apex" required>
        </div>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Owner Full Name</label>
          <input type="text" id="pOwnerName" class="form-control" placeholder="e.g. Usman Tariq" required>
        </div>
        <div class="form-group">
          <label class="form-label">Owner Email *</label>
          <input type="email" id="pOwnerEmail" class="form-control" placeholder="owner@company.com" required>
        </div>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Company Admin Username *</label>
          <input type="text" id="pAdminUsername" class="form-control" placeholder="e.g. apex_admin" required>
        </div>
        <div class="form-group">
          <label class="form-label">Initial Admin Password *</label>
          <input type="text" id="pAdminPassword" class="form-control" placeholder="Min 6 characters" required>
        </div>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Seat Limit (Max Employees)</label>
          <input type="number" id="pMaxEmployees" class="form-control" value="50" min="1" max="1000">
        </div>
        <div class="form-group">
          <label class="form-label">Default Message Limit (Day)</label>
          <input type="number" id="pDefaultLimit" class="form-control" value="250" min="10" max="10000">
        </div>
      </div>

      <div class="form-group">
        <label class="form-label">Subscription Tier</label>
        <select id="pPlanTier" class="form-control">
          <option value="Enterprise Billion">Enterprise Billion (Unlimited Power)</option>
          <option value="Scale Pro">Scale Pro (100 Seats)</option>
          <option value="Growth Starter">Growth Starter (25 Seats)</option>
        </select>
      </div>

      <div id="provisionError" style="display: none; padding: 10px; border-radius: 8px; background: rgba(244, 63, 94, 0.2); color: #fb7185; font-size: 13px; margin-bottom: 16px;"></div>

      <div style="display: flex; justify-content: flex-end; gap: 12px; margin-top: 20px;">
        <button type="button" class="btn btn-secondary" onclick="closeProvisionModal()">Cancel</button>
        <button type="submit" id="provisionSubmitBtn" class="btn btn-primary">Provision Company & Admin 🚀</button>
      </div>
    </form>
  </div>
</div>

<script>
async function handleSadminLogin(e) {
  e.preventDefault();
  const btn = document.getElementById('loginBtn');
  const errBox = document.getElementById('loginError');
  btn.disabled = true;
  btn.textContent = 'Verifying Master Credentials...';
  errBox.style.display = 'none';

  try {
    const res = await fetch('api/auth.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        action: 'login',
        username: document.getElementById('sUsername').value.trim(),
        password: document.getElementById('sPassword').value.trim()
      })
    });
    const data = await res.json();
    if (data.success && data.user && data.user.role === 'superadmin') {
      window.location.reload();
    } else {
      errBox.textContent = data.error || 'Access Denied: Super Admin privileges required.';
      errBox.style.display = 'block';
    }
  } catch (err) {
    errBox.textContent = 'Server communication error. Check bridge or network.';
    errBox.style.display = 'block';
  } finally {
    btn.disabled = false;
    btn.textContent = 'Unlock Platform Command 🚀';
  }
}

function generateSlug(val) {
  const slug = val.toLowerCase().replace(/[^a-z0-9]/g, '');
  document.getElementById('pCompSlug').value = slug;
  if (!document.getElementById('pAdminUsername').value || document.getElementById('pAdminUsername').dataset.auto) {
    document.getElementById('pAdminUsername').value = slug ? slug + '_admin' : '';
    document.getElementById('pAdminUsername').dataset.auto = 'true';
  }
}

function openProvisionModal() {
  document.getElementById('provisionModal').style.display = 'flex';
}
function closeProvisionModal() {
  document.getElementById('provisionModal').style.display = 'none';
}

async function loadDashboardData() {
  try {
    // 1. Load Telemetry
    const rTel = await fetch('api/companies.php?action=get_telemetry');
    const dTel = await rTel.json();
    if (dTel.success) {
      const t = dTel.telemetry;
      document.getElementById('mTotalCompanies').textContent = t.total_companies;
      document.getElementById('mActiveCompanies').textContent = t.active_companies;
      document.getElementById('mTotalEmployees').textContent = t.total_employees;
      document.getElementById('mTotalTLs').textContent = t.total_tls;
      document.getElementById('mTotalTMs').textContent = t.total_tms;
      document.getElementById('mActiveSessions').textContent = t.active_sessions;
      document.getElementById('mMessagesToday').textContent = t.total_messages_today;
    }

    // 2. Load Companies
    const rComp = await fetch('api/companies.php?action=list');
    const dComp = await rComp.json();
    const tbody = document.getElementById('companyTableBody');
    if (dComp.success && dComp.companies) {
      if (!dComp.companies.length) {
        tbody.innerHTML = '<tr><td colspan="7" style="text-align:center; padding:20px;">No companies provisioned yet. Click "Provision New Company" above!</td></tr>';
      } else {
        tbody.innerHTML = dComp.companies.map(c => `
          <tr>
            <td>
              <div style="font-weight: 700; font-size: 14px;">${c.name}</div>
              <div style="font-size: 11px; color: var(--text-muted); font-family: monospace;">/${c.slug} &bull; ${c.id}</div>
            </td>
            <td><span style="padding: 3px 8px; border-radius: 6px; background: rgba(139, 92, 246, 0.15); color: #c4b5fd; font-size: 11px; font-weight: 600;">${c.plan || 'Standard'}</span></td>
            <td>
              <div>${c.owner_name || 'Admin'}</div>
              <div style="font-size: 11px; color: var(--text-muted);">${c.owner_email || '-'}</div>
            </td>
            <td>
              <div><strong>${c.employee_count || 0}</strong> / ${c.max_employees || 50}</div>
              <div style="font-size: 11px; color: var(--text-muted);">${c.tl_count || 0} TLs &bull; ${c.tm_count || 0} TMs</div>
            </td>
            <td><strong>${c.messages_today || 0}</strong> msgs</td>
            <td>
              <span class="status-badge ${c.status === 'active' ? 'status-active' : 'status-suspended'}">
                ● ${c.status}
              </span>
            </td>
            <td>
              <div style="display: flex; gap: 8px;">
                <a href="admin.php?company_id=${c.id}" class="btn btn-secondary" style="padding: 6px 12px; font-size: 11px;">
                  🏢 Open Portal
                </a>
                <button class="btn btn-secondary" style="padding: 6px 10px; font-size: 11px;" onclick="toggleCompanyStatus('${c.id}')">
                  ${c.status === 'active' ? '⏸️' : '▶️'}
                </button>
              </div>
            </td>
          </tr>
        `).join('');
      }
    }

    loadAuditLogs();
    loadNumberProfiles();
  } catch (err) {
    console.error('Super Admin fetch error:', err);
  }
}

async function loadAuditLogs() {
  try {
    const res = await fetch('api/companies.php?action=get_audit_logs');
    const data = await res.json();
    const container = document.getElementById('auditContainer');
    if (data.success && data.logs) {
      if (!data.logs.length) {
        container.innerHTML = '<div style="padding: 16px; text-align: center; color: var(--text-muted);">No activity logged yet.</div>';
      } else {
        container.innerHTML = data.logs.map(l => `
          <div style="padding: 10px 14px; border-bottom: 1px solid rgba(255,255,255,0.04); display: flex; align-items: center; justify-content: space-between; font-size: 12px;">
            <div>
              <span style="font-weight: 700; color: #a78bfa;">[${l.action}]</span>
              <span style="margin-left: 6px;">${l.details}</span>
              <span style="font-size: 11px; color: var(--text-muted); margin-left: 8px;">(Actor: ${l.actor_name})</span>
            </div>
            <div style="font-family: monospace; font-size: 11px; color: var(--text-muted);">${l.timestamp}</div>
          </div>
        `).join('');
      }
    }
  } catch (_) {}
}

async function toggleCompanyStatus(id) {
  if (!confirm('Are you sure you want to toggle the active status of this company?')) return;
  try {
    const res = await fetch('api/companies.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'toggle_status', company_id: id })
    });
    const d = await res.json();
    if (d.success) loadDashboardData();
    else alert(d.error || 'Failed to toggle status');
  } catch (err) {
    alert('Network error');
  }
}

async function handleProvisionCompany(e) {
  e.preventDefault();
  const btn = document.getElementById('provisionSubmitBtn');
  const errBox = document.getElementById('provisionError');
  btn.disabled = true;
  btn.textContent = 'Provisioning Infrastructure...';
  errBox.style.display = 'none';

  const payload = {
    action: 'create',
    name: document.getElementById('pCompName').value.trim(),
    slug: document.getElementById('pCompSlug').value.trim(),
    owner_name: document.getElementById('pOwnerName').value.trim(),
    owner_email: document.getElementById('pOwnerEmail').value.trim(),
    admin_username: document.getElementById('pAdminUsername').value.trim(),
    admin_password: document.getElementById('pAdminPassword').value.trim(),
    max_employees: document.getElementById('pMaxEmployees').value,
    default_message_limit: document.getElementById('pDefaultLimit').value,
    plan: document.getElementById('pPlanTier').value
  };

  try {
    const res = await fetch('api/companies.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    });
    const data = await res.json();
    if (data.success) {
      alert(`Company "${payload.name}" provisioned successfully!\nAdmin Username: ${payload.admin_username}\nThey can now login at /admin`);
      closeProvisionModal();
      document.getElementById('provisionForm').reset();
      loadDashboardData();
    } else {
      errBox.textContent = data.error || 'Provisioning failed.';
      errBox.style.display = 'block';
    }
  } catch (err) {
    errBox.textContent = 'Communication failure.';
    errBox.style.display = 'block';
  } finally {
    btn.disabled = false;
    btn.textContent = 'Provision Company & Admin 🚀';
  }
}

function openAddNumberModal() { document.getElementById('addNumberModal').style.display = 'flex'; }
function closeAddNumberModal() { document.getElementById('addNumberModal').style.display = 'none'; }
function closeAssignModal() { document.getElementById('assignNumberModal').style.display = 'none'; }

async function loadNumberProfiles() {
  try {
    const res = await fetch('api/companies.php?action=list_number_profiles');
    const data = await res.json();
    const tbody = document.getElementById('numberTableBody');
    if (!tbody) return;

    if (data.success && data.profiles) {
      if (!data.profiles.length) {
        tbody.innerHTML = '<tr><td colspan="5" style="text-align:center; padding:18px;">No number profiles created yet. Click "Add Number Profile" above!</td></tr>';
      } else {
        tbody.innerHTML = data.profiles.map(p => `
          <tr>
            <td>
              <div style="font-weight: 700; font-size: 14px;">${p.display || p.phone}</div>
              <div style="font-size: 11px; color: var(--text-muted);">${p.label || 'Company Fallback Trunk'}</div>
            </td>
            <td><strong>${p.daily_capacity || 500}</strong> msgs/day</td>
            <td>
              ${p.assigned_to_user_name 
                ? `<span style="color:#34d399; font-weight:700;">🟢 Assigned: ${p.assigned_to_user_name}</span>`
                : '<span style="color:var(--text-muted);">⚪ Available in Pool</span>'}
            </td>
            <td>
              <span class="status-badge ${p.status === 'in_use' ? 'status-active' : 'status-suspended'}">
                ● ${p.status}
              </span>
            </td>
            <td>
              <div style="display: flex; gap: 8px;">
                ${!p.assigned_to_user_name ? `
                  <button class="btn btn-secondary" style="padding: 5px 10px; font-size: 11px;" onclick="openAssignModal('${p.id}', '${p.label || p.phone}')">
                    👤 Assign to Closer
                  </button>
                ` : `
                  <button class="btn btn-danger" style="padding: 5px 10px; font-size: 11px;" onclick="releaseNumber('${p.id}')">
                    🔄 Return to Pool
                  </button>
                `}
              </div>
            </td>
          </tr>
        `).join('');
      }
    }
  } catch (err) {
    console.error('Failed to load number profiles:', err);
  }
}

async function handleAddNumber(e) {
  e.preventDefault();
  const btn = document.getElementById('addNumSubmitBtn');
  const errBox = document.getElementById('addNumError');
  btn.disabled = true;
  btn.textContent = 'Adding to Pool...';
  errBox.style.display = 'none';

  const payload = {
    action: 'create_number_profile',
    phone: document.getElementById('numPhone').value.trim(),
    label: document.getElementById('numLabel').value.trim(),
    daily_capacity: parseInt(document.getElementById('numCapacity').value, 10)
  };

  try {
    const res = await fetch('api/companies.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    });
    const d = await res.json();
    if (d.success) {
      alert('Company Number Profile added to pool successfully!');
      closeAddNumberModal();
      document.getElementById('addNumberForm').reset();
      loadNumberProfiles();
    } else {
      errBox.textContent = d.error || 'Failed to add number';
      errBox.style.display = 'block';
    }
  } catch (_) {
    errBox.textContent = 'Network error';
    errBox.style.display = 'block';
  } finally {
    btn.disabled = false;
    btn.textContent = 'Add to Company Pool 📱';
  }
}

async function openAssignModal(profId, label) {
  document.getElementById('assignProfId').value = profId;
  document.getElementById('assignProfLabel').textContent = label;
  const select = document.getElementById('assignUserSelect');
  select.innerHTML = '<option value="">Loading eligible employees...</option>';
  document.getElementById('assignNumberModal').style.display = 'flex';

  try {
    const res = await fetch('api/auth.php?action=list_company_employees');
    const d = await res.json();
    if (d.success && d.employees) {
      const needPhone = d.employees.filter(e => !e.phone || e.using_company_fallback);
      if (!needPhone.length) {
        select.innerHTML = '<option value="">All active closers currently have numbers!</option>';
      } else {
        select.innerHTML = '<option value="">-- Choose Employee --</option>' + 
          needPhone.map(e => `<option value="${e.id}">${e.name} (@${e.username}) - ${e.role === 'lead' ? 'Team Lead' : 'Closer'}</option>`).join('');
      }
    }
  } catch (_) {
    select.innerHTML = '<option value="">Failed to load employees</option>';
  }
}

async function handleAssignNumber(e) {
  e.preventDefault();
  const btn = document.getElementById('assignSubmitBtn');
  const profId = document.getElementById('assignProfId').value;
  const userId = document.getElementById('assignUserSelect').value;
  if (!userId) return alert('Please select an employee');

  btn.disabled = true;
  btn.textContent = 'Assigning...';

  try {
    const res = await fetch('api/companies.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'assign_number_profile', profile_id: profId, user_id: userId })
    });
    const d = await res.json();
    if (d.success) {
      alert(d.message);
      closeAssignModal();
      loadNumberProfiles();
      loadDashboardData();
    } else {
      alert(d.error || 'Failed to assign number');
    }
  } catch (_) {
    alert('Network error');
  } finally {
    btn.disabled = false;
    btn.textContent = 'Confirm Assignment 🚀';
  }
}

async function releaseNumber(profId) {
  if (!confirm('Release this company number back to the available pool?')) return;
  try {
    const res = await fetch('api/companies.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'release_number_profile', profile_id: profId })
    });
    const d = await res.json();
    if (d.success) {
      loadNumberProfiles();
      loadDashboardData();
    } else {
      alert(d.error || 'Failed to release number');
    }
  } catch (_) {
    alert('Network error');
  }
}

<?php if ($isSuperAdmin): ?>
document.addEventListener('DOMContentLoaded', loadDashboardData);
<?php endif; ?>
</script>
</body>
</html>
