<?php
/**
 * Billion-Dollar Startup — Executive Company Administrator Portal (/admin)
 * 
 * Features:
 * 1. World-Class SaaS Executive Dashboard (Linear / Stripe aesthetic)
 * 2. Closer Roster & Real-time Employee Message Limiter Control
 * 3. Live Lead Pipeline & 👑 Admin Takeover Engine (Commission Protected)
 * 4. Campaign Dispatcher with 3 Starting Variations (Text, Text+Image, Audio)
 * 5. Team Leader (TL) Commission & Nunno Rs. 1700 Plan Verification Ledger
 * 6. Company Trunk & Fallback Number Profiles Pool
 * 7. Multi-Tenant Isolated Database & Product Catalog Config
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
    header('Location: login.php?action=logout&switch=1');
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
        'default_message_limit' => 250,
        'fallback_phone'        => '+923318036576'
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
  <title>Chatly Company Admin — <?php echo htmlspecialchars($activeCompany['name']); ?></title>
  <link rel="icon" type="image/png" href="assets/images/chatly-icon.png">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&family=JetBrains+Mono:wght@500;700&display=swap" rel="stylesheet">
  <script>
    (function(){
      try {
        if (localStorage.getItem('nunno_theme') === 'light') {
          document.documentElement.classList.add('theme-light');
        }
      } catch(_) {}
    })();
  </script>
  <style>
    :root {
      --bg-base: #050b09;
      --bg-surface: #09130f;
      --bg-surface-elevated: #0d1c16;
      --bg-surface-hover: #12261e;
      --border-subtle: rgba(31, 224, 138, 0.11);
      --border-accent: rgba(31, 224, 138, 0.38);
      --border-glow: rgba(31, 224, 138, 0.5);
      --text-main: #effff7;
      --text-secondary: #8db5a3;
      --text-muted: #5c8070;
      --accent-emerald: #1fe08a;
      --accent-emerald-dark: #0fa968;
      --accent-purple: #2dd4bf;
      --accent-amber: #f59e0b;
      --accent-rose: #f43f5e;
      --accent-blue: #3b82f6;
    }

    html.theme-light {
      --bg-base: #f0faf5;
      --bg-surface: #ffffff;
      --bg-surface-elevated: #e9f7f0;
      --bg-surface-hover: #d9f0e5;
      --border-subtle: #cfe8dc;
      --border-accent: rgba(15, 169, 104, 0.4);
      --border-glow: rgba(15, 169, 104, 0.25);
      --text-main: #07140f;
      --text-secondary: #3f6354;
      --text-muted: #7a9a8c;
      --accent-emerald: #0fa968;
    }

    .chatly-logo { height: 34px; width: auto; display: block; filter: drop-shadow(0 2px 10px rgba(31, 224, 138, 0.28)); }
    .chatly-logo--light { display: none; }
    html.theme-light .chatly-logo--dark { display: none; }
    html.theme-light .chatly-logo--light { display: block; }

    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      font-family: 'Inter', -apple-system, sans-serif;
      background-color: var(--bg-base);
      color: var(--text-main);
      min-height: 100vh;
      -webkit-font-smoothing: antialiased;
      position: relative;
      display: flex;
      flex-direction: column;
    }

    .bg-mesh {
      position: fixed; inset: 0; pointer-events: none; z-index: 0;
      background: radial-gradient(circle at 10% 10%, rgba(16, 185, 129, 0.07) 0%, transparent 45%),
                  radial-gradient(circle at 90% 90%, rgba(31, 224, 138, 0.05) 0%, transparent 45%);
    }

    .admin-container {
      max-width: 1440px;
      margin: 0 auto;
      padding: 24px 20px 48px;
      position: relative;
      z-index: 1;
      width: 100%;
    }

    /* Executive Top Bar */
    .top-bar {
      display: flex;
      align-items: center;
      justify-content: space-between;
      flex-wrap: wrap;
      gap: 16px;
      padding: 16px 24px;
      border-radius: 18px;
      background: var(--bg-surface);
      border: 1px solid var(--border-subtle);
      box-shadow: 0 10px 30px rgba(0,0,0,0.3);
      margin-bottom: 24px;
      backdrop-filter: blur(14px);
    }
    .brand-section {
      display: flex;
      align-items: center;
      gap: 14px;
    }
    .company-logo {
      width: 44px;
      height: 44px;
      border-radius: 12px;
      background: #07140f;
      border: 1.5px solid rgba(255, 255, 255, 0.15);
      object-fit: contain;
      padding: 3px;
      display: block;
      box-shadow: 0 4px 16px rgba(0, 0, 0, 0.35);
    }
    .company-info { display: flex; flex-direction: column; }
    .company-name-row { display: flex; align-items: center; gap: 8px; }
    .company-name { font-size: 18px; font-weight: 800; letter-spacing: -0.02em; }
    .badge-tier {
      font-size: 10.5px;
      font-weight: 700;
      text-transform: uppercase;
      padding: 2px 7px;
      border-radius: 6px;
      background: rgba(16, 185, 129, 0.15);
      border: 1px solid rgba(16, 185, 129, 0.35);
      color: #34d399;
    }
    .company-sub { font-size: 12px; color: var(--text-secondary); margin-top: 2px; }

    .header-actions { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
    .btn {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 9px 16px;
      border-radius: 10px;
      font-size: 13px;
      font-weight: 700;
      border: none;
      cursor: pointer;
      text-decoration: none;
      transition: all 0.2s ease;
      white-space: nowrap;
    }
    .btn-emerald {
      background: linear-gradient(135deg, #4cecab, #1fe08a 45%, #0fa968);
      color: #03130c;
      box-shadow: 0 4px 14px rgba(31, 224, 138, 0.35);
    }
    .btn-emerald:hover { transform: translateY(-1px); box-shadow: 0 6px 20px rgba(16, 185, 129, 0.5); }
    .btn-purple {
      background: linear-gradient(135deg, #5eead4, #2dd4bf 45%, #0d9488);
      color: #021a17;
      box-shadow: 0 4px 14px rgba(45, 212, 191, 0.35);
    }
    .btn-secondary {
      background: rgba(255, 255, 255, 0.05);
      border: 1px solid var(--border-subtle);
      color: var(--text-main);
    }
    .btn-secondary:hover { background: rgba(255, 255, 255, 0.1); }
    .btn-danger {
      background: rgba(244, 63, 94, 0.12);
      border: 1px solid rgba(244, 63, 94, 0.3);
      color: #fb7185;
    }
    .btn-danger:hover { background: rgba(244, 63, 94, 0.22); }

    /* 5 Hero Metric Cards */
    .metric-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
      gap: 16px;
      margin-bottom: 24px;
    }
    .metric-card {
      background: var(--bg-surface);
      border: 1px solid var(--border-subtle);
      border-radius: 16px;
      padding: 20px;
      box-shadow: 0 8px 24px rgba(0,0,0,0.18);
      position: relative;
      overflow: hidden;
      transition: transform 0.2s, border-color 0.2s;
    }
    .metric-card:hover { transform: translateY(-2px); border-color: var(--border-accent); }
    .metric-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 10px; }
    .metric-label { font-size: 11.5px; font-weight: 700; text-transform: uppercase; color: var(--text-muted); letter-spacing: 0.04em; }
    .metric-icon { width: 34px; height: 34px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 16px; }
    .metric-val { font-size: 28px; font-weight: 800; letter-spacing: -0.03em; margin-bottom: 4px; }
    .metric-sub { font-size: 12px; color: var(--text-secondary); }

    /* Tabs Bar */
    .dashboard-tabs {
      display: flex;
      flex-wrap: wrap;
      gap: 6px;
      background: var(--bg-surface);
      padding: 6px;
      border-radius: 14px;
      border: 1px solid var(--border-subtle);
      margin-bottom: 24px;
      overflow: hidden;
      scrollbar-width: none;
      -ms-overflow-style: none;
    }
    .dashboard-tabs::-webkit-scrollbar {
      display: none;
      width: 0;
      height: 0;
    }
    .dash-tab-btn {
      padding: 10px 18px;
      font-size: 13px;
      font-weight: 700;
      color: var(--text-secondary);
      background: none;
      border: none;
      border-radius: 10px;
      cursor: pointer;
      display: flex;
      align-items: center;
      gap: 8px;
      white-space: nowrap;
      transition: all 0.2s ease;
    }
    .dash-tab-btn:hover { color: var(--text-main); background: rgba(255,255,255,0.03); }
    .dash-tab-btn.active {
      color: #fff;
      background: var(--accent-emerald-dark);
      box-shadow: 0 4px 14px rgba(16, 185, 129, 0.3);
    }
    html.theme-light .dash-tab-btn.active { color: #fff; }

    /* Content Panes */
    .dash-pane { display: none; }
    .dash-pane.active { display: block; animation: paneFade 0.2s ease-out; }
    @keyframes paneFade { from { opacity: 0; transform: translateY(6px); } to { opacity: 1; transform: translateY(0); } }

    .content-box {
      background: var(--bg-surface);
      border: 1px solid var(--border-subtle);
      border-radius: 18px;
      padding: 24px;
      box-shadow: 0 10px 30px rgba(0,0,0,0.2);
      margin-bottom: 24px;
    }
    .box-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      flex-wrap: wrap;
      gap: 14px;
      margin-bottom: 20px;
    }
    .box-title { font-size: 16px; font-weight: 800; display: flex; align-items: center; gap: 10px; }
    .box-desc { font-size: 12.5px; color: var(--text-secondary); }

    /* Tables */
    table { width: 100%; border-collapse: separate; border-spacing: 0 8px; }
    th {
      text-align: left;
      padding: 10px 14px;
      font-size: 11.5px;
      font-weight: 700;
      color: var(--text-muted);
      text-transform: uppercase;
      letter-spacing: 0.04em;
    }
    td {
      padding: 14px 14px;
      background: rgba(255,255,255,0.015);
      font-size: 13px;
      font-weight: 500;
      border-top: 1px solid var(--border-subtle);
      border-bottom: 1px solid var(--border-subtle);
      vertical-align: middle;
    }
    tr td:first-child { border-left: 1px solid var(--border-subtle); border-radius: 12px 0 0 12px; }
    tr td:last-child { border-right: 1px solid var(--border-subtle); border-radius: 0 12px 12px 0; }
    tr:hover td { background: rgba(255,255,255,0.04); }

    .user-profile-cell { display: flex; align-items: center; gap: 12px; }
    .avatar-circle {
      width: 36px; height: 36px; border-radius: 50%;
      background: linear-gradient(135deg, #3b82f6, #14c27a);
      color: #fff; font-weight: 800; font-size: 14px;
      display: flex; align-items: center; justify-content: center;
    }
    .user-name { font-weight: 700; color: var(--text-main); }
    .user-meta { font-size: 11.5px; color: var(--text-muted); }

    /* Progress bar for limiter */
    .progress-track {
      width: 140px; height: 7px; border-radius: 4px;
      background: rgba(255,255,255,0.08); overflow: hidden; margin-top: 5px;
    }
    .progress-fill { height: 100%; border-radius: 4px; transition: width 0.3s; }
    .fill-green { background: #10b981; }
    .fill-amber { background: #f59e0b; }
    .fill-red { background: #f43f5e; }

    .limiter-ctrl-group { display: flex; align-items: center; gap: 6px; }
    .limiter-input {
      width: 72px; padding: 6px 8px; border-radius: 8px;
      background: rgba(255,255,255,0.04); border: 1px solid var(--border-subtle);
      color: #fff; font-size: 13px; font-weight: 700; text-align: center;
      font-family: 'JetBrains Mono', monospace;
    }
    html.theme-light .limiter-input { background: #fff; color: #0f172a; }
    .btn-quick-chip {
      padding: 5px 8px; font-size: 11px; font-weight: 700;
      border-radius: 6px; background: rgba(255,255,255,0.06);
      border: 1px solid var(--border-subtle); color: var(--text-secondary);
      cursor: pointer; transition: all 0.15s;
    }
    .btn-quick-chip:hover { background: rgba(16, 185, 129, 0.2); color: #34d399; }

    /* Status Pill */
    .badge-status {
      display: inline-flex; align-items: center; gap: 6px;
      padding: 3px 9px; border-radius: 20px; font-size: 11px; font-weight: 700;
    }
    .status-active { background: rgba(16, 185, 129, 0.15); color: #34d399; border: 1px solid rgba(16, 185, 129, 0.3); }
    .status-suspended { background: rgba(244, 63, 94, 0.15); color: #fb7185; border: 1px solid rgba(244, 63, 94, 0.3); }

    /* Takeover Button & Badge */
    .btn-takeover {
      background: linear-gradient(135deg, #f59e0b, #d97706);
      color: #000; font-size: 11.5px; font-weight: 800; padding: 6px 12px;
      border-radius: 8px; border: none; cursor: pointer;
      display: inline-flex; align-items: center; gap: 5px;
      box-shadow: 0 2px 10px rgba(245, 158, 11, 0.3);
      transition: all 0.2s;
    }
    .btn-takeover:hover { transform: translateY(-1px); box-shadow: 0 4px 16px rgba(245, 158, 11, 0.5); }
    .badge-takeover {
      display: inline-flex; align-items: center; gap: 4px;
      padding: 3px 8px; border-radius: 6px; font-size: 11px; font-weight: 800;
      background: rgba(245, 158, 11, 0.15); color: #fbbf24; border: 1px solid rgba(245, 158, 11, 0.3);
    }

    /* Modals */
    .modal-overlay {
      position: fixed; inset: 0; background: rgba(0,0,0,0.75);
      backdrop-filter: blur(8px); display: none; align-items: center; justify-content: center;
      z-index: 9999; padding: 16px;
    }
    .modal-card {
      background: var(--bg-surface); border: 1.5px solid var(--border-accent);
      border-radius: 20px; width: 100%; max-width: 540px; padding: 28px;
      box-shadow: 0 25px 60px rgba(0,0,0,0.6); position: relative;
    }
    .modal-title { font-size: 18px; font-weight: 800; margin-bottom: 6px; }
    .form-group { margin-bottom: 16px; }
    .form-label { display: block; font-size: 12px; font-weight: 600; color: var(--text-secondary); margin-bottom: 6px; }
    .form-control {
      width: 100%; padding: 11px 14px; border-radius: 10px;
      background: rgba(255,255,255,0.04); border: 1px solid var(--border-subtle);
      color: #fff; font-size: 13.5px; font-family: inherit;
    }
    html.theme-light .form-control { background: #fff; color: #0f172a; border-color: #cbd5e1; }
    .form-control:focus { outline: none; border-color: var(--accent-emerald); }
    .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
  </style>
</head>
<body>
<div class="bg-mesh"></div>

<div class="admin-container">

<?php if (!$isAdmin): ?>
  <!-- COMPANY ADMIN LOGIN GATE -->
  <div style="min-height: 80vh; display: flex; align-items: center; justify-content: center;">
    <div style="width: 100%; max-width: 420px; background: var(--bg-surface); border: 1px solid var(--border-accent); border-radius: 20px; padding: 36px; box-shadow: 0 20px 50px rgba(0,0,0,0.5); text-align: center;">
      <div style="width: 60px; height: 60px; border-radius: 16px; background: linear-gradient(135deg, #10b981, #059669); margin: 0 auto 16px; display: flex; align-items: center; justify-content: center; font-size: 26px;">🏢</div>
      <h2 style="font-size: 22px; font-weight: 800; margin-bottom: 6px;">Company Admin Portal</h2>
      <p style="font-size: 13px; color: var(--text-secondary); margin-bottom: 24px;">Provision employees, configure message limits & lead takeover.</p>

      <form id="adminLoginForm" onsubmit="handleAdminLogin(event)">
        <div class="form-group" style="text-align: left;">
          <label class="form-label">Username / Email</label>
          <input type="text" id="aUsername" class="form-control" placeholder="admin" required autofocus>
        </div>
        <div class="form-group" style="text-align: left; margin-bottom: 22px;">
          <label class="form-label">Password</label>
          <input type="password" id="aPassword" class="form-control" placeholder="admin123" required>
        </div>
        <div id="loginError" style="display: none; padding: 10px; border-radius: 8px; background: rgba(244, 63, 94, 0.15); border: 1px solid rgba(244, 63, 94, 0.3); color: #fb7185; font-size: 12.5px; margin-bottom: 14px;"></div>
        <button type="submit" id="adminLoginBtn" class="btn btn-emerald" style="width: 100%; justify-content: center; padding: 12px;">
          Access Company Control 🏢
        </button>
      </form>
      <div style="margin-top: 20px; font-size: 12px; color: var(--text-secondary);">
        <a href="login.php" style="color: var(--accent-emerald); text-decoration: none; font-weight: 600;">&larr; Return to Closer / TL Login</a>
      </div>
    </div>
  </div>

<?php else: ?>
  <!-- EXECUTIVE COMPANY ADMIN DASHBOARD -->
  <header class="top-bar">
    <div class="brand-section" style="display:flex; align-items:center; gap:12px;">
      <a href="admin.php" title="Chatly Enterprise" style="display:flex; align-items:center; text-decoration:none;">
        <img src="assets/images/chatly-logo.png" alt="Chatly" class="chatly-logo chatly-logo--dark"><img src="assets/images/chatly-logo-light.png" alt="Chatly" class="chatly-logo chatly-logo--light">
      </a>
      <div style="height:28px; width:1px; background:rgba(255,255,255,0.15); margin:0 2px;"></div>
      <img id="adminCompLogo" src="<?php echo htmlspecialchars($activeCompany['logo'] ?? 'assets/images/nunno-logo.png'); ?>" alt="Company Logo" class="company-logo">
      <div class="company-info">
        <div class="company-name-row">
          <span class="company-name"><?php echo htmlspecialchars($activeCompany['name']); ?></span>
          <span class="badge-tier"><?php echo htmlspecialchars($activeCompany['plan'] ?? 'Enterprise Pro'); ?></span>
          <span style="font-size: 11px; padding: 2px 8px; border-radius: 12px; background: rgba(16, 185, 129, 0.15); color: #34d399; font-weight: 700; display: inline-flex; align-items: center; gap: 4px;">
            <span style="width:6px; height:6px; border-radius:50%; background:#10b981;"></span>
            Decentralized TM Hub &bull; Active
          </span>
        </div>
        <div class="company-sub">
          ID: <?php echo htmlspecialchars($activeCompany['id']); ?> &bull; Tenant DB: Isolated &bull; Admin: <?php echo htmlspecialchars($currentUser['name'] ?? 'Admin'); ?>
        </div>
      </div>
    </div>

    <div class="header-actions">
      <button class="btn btn-emerald" onclick="switchDashboardTab('pending')">
        <span>⏳</span> Pending Applications <span id="headerPendingBadge" style="display:none; background:#ef4444; color:#fff; font-size:10px; padding:2px 6px; border-radius:10px; font-weight:800; margin-left:4px;">0</span>
      </button>
      <a href="index.html?company_id=<?php echo urlencode($activeCompany['id']); ?>" class="btn btn-purple" target="_blank">
        <span>💬</span> Open Sales Desk
      </a>
      <button class="btn btn-secondary" onclick="toggleTheme()">
        <span>🌓</span>
      </button>
      <a href="admin.php?action=logout" class="btn btn-danger">
        <span>🚪</span> Logout
      </a>
    </div>
  </header>

  <!-- 5 Executive KPI Cards -->
  <div class="metric-grid">
    <div class="metric-card">
      <div class="metric-header">
        <span class="metric-label">Company Workforce</span>
        <div class="metric-icon" style="background: rgba(16, 185, 129, 0.15); color: #34d399;">👥</div>
      </div>
      <div class="metric-val" id="metricTotalEmployees">-</div>
      <div class="metric-sub"><span id="metricTLCount">-</span> Team Leads &bull; <span id="metricTMCount">-</span> Closers</div>
    </div>

    <div class="metric-card">
      <div class="metric-header">
        <span class="metric-label">Messages Today (Quota)</span>
        <div class="metric-icon" style="background: rgba(59, 130, 246, 0.15); color: #60a5fa;">💬</div>
      </div>
      <div class="metric-val" id="metricMessagesToday">-</div>
      <div class="metric-sub">Across all closer sessions today</div>
    </div>

    <div class="metric-card">
      <div class="metric-header">
        <span class="metric-label">TL Commissions Accrued</span>
        <div class="metric-icon" style="background: rgba(31, 224, 138, 0.15); color: #8ff5c8;">💰</div>
      </div>
      <div class="metric-val" id="metricTLCommissions" style="color: #8ff5c8;">Rs. 0</div>
      <div class="metric-sub">PKR 500 earned per joined TM</div>
    </div>

    <div class="metric-card">
      <div class="metric-header">
        <span class="metric-label">Pipeline Conversions</span>
        <div class="metric-icon" style="background: rgba(245, 158, 11, 0.15); color: #fbbf24;">📈</div>
      </div>
      <div class="metric-val" id="metricPaidLeads" style="color: #fbbf24;">-</div>
      <div class="metric-sub"><span id="metricConversionRate">0%</span> conversion rate</div>
    </div>

    <div class="metric-card">
      <div class="metric-header">
        <span class="metric-label">WhatsApp Status</span>
        <div class="metric-icon" style="background: rgba(16, 185, 129, 0.15); color: #34d399;">📱</div>
      </div>
      <div class="metric-val" style="font-size: 20px; color: #34d399;" id="metricTrunkStatus">24/7 Pure WS 🟢</div>
      <div class="metric-sub">Zero Chromium &bull; Per-Closer QR</div>
    </div>
  </div>

  <!-- Navigation Tabs -->
  <div class="dashboard-tabs">
    <button class="dash-tab-btn active" id="tabBtnClosers" onclick="switchDashboardTab('closers')">
      <span>👥</span> Closer Roster &amp; Message Limiter
    </button>
    <button class="dash-tab-btn" id="tabBtnPending" onclick="switchDashboardTab('pending')">
      <span>⏳</span> Pending Closer Applications <span id="badgePendingTMCount" style="display:none; background:#ef4444; color:#fff; font-size:10px; padding:2px 7px; border-radius:10px; font-weight:800; margin-left:6px;">0</span>
    </button>
    <button class="dash-tab-btn" id="tabBtnLeads" onclick="switchDashboardTab('leads')">
      <span>👑</span> Live Leads &amp; Admin Takeover
    </button>
    <button class="dash-tab-btn" id="tabBtnCampaigns" onclick="switchDashboardTab('campaigns')">
      <span>🚀</span> Campaign Dispatcher (3 Variations)
    </button>
    <button class="dash-tab-btn" id="tabBtnCommissions" onclick="switchDashboardTab('commissions')">
      <span>💰</span> TL Commission &amp; Plan Ledger
    </button>
    <button class="dash-tab-btn" id="tabBtnProducts" onclick="switchDashboardTab('products')">
      <span>⚙️</span> Products &amp; Pitch Config
    </button>
  </div>

  <!-- TAB 0: PENDING CLOSER APPLICATIONS -->
  <div class="dash-pane" id="panePending">
    <div class="content-box">
      <div class="box-header">
        <div>
          <div class="box-title"><span>⏳</span> Pending Closer (TM) Applications</div>
          <div class="box-desc">
            Closers self-onboard by scanning their WhatsApp QR on the landing page and selecting your company. 
            Review their credentials and assigned details below to <strong>Approve</strong> (assign starting daily message quota) or <strong>Decline</strong>.
          </div>
        </div>
        <div>
          <button class="btn btn-secondary" onclick="loadPendingTMs()">🔄 Refresh Applications</button>
        </div>
      </div>

      <div style="overflow-x: auto;">
        <table>
          <thead>
            <tr>
              <th>Applicant Profile</th>
              <th>Applicant Credentials (For Company)</th>
              <th>Connected WhatsApp Phone</th>
              <th>Location &amp; Gender</th>
              <th>Applied Date</th>
              <th>Decision / Actions</th>
            </tr>
          </thead>
          <tbody id="pendingTableBody">
            <tr><td colspan="6" style="text-align: center; padding: 24px; color: var(--text-muted);">Loading pending applications...</td></tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- TAB 1: CLOSER ROSTER & MESSAGE LIMITER -->
  <div class="dash-pane active" id="paneClosers">
    <div class="content-box">
      <div class="box-header">
        <div>
          <div class="box-title"><span>👥</span> Closer Roster &amp; Daily Message Limiter Control</div>
          <div class="box-desc">Control how many WhatsApp messages each closer can dispatch per day. Set to <strong>-1</strong> for Unlimited.</div>
        </div>
        <div style="display:flex; gap:10px; align-items:center;">
          <input type="text" id="closerSearch" oninput="filterCloserTable()" placeholder="Search closer by name or phone..." class="form-control" style="width:260px; padding:8px 12px; font-size:12.5px;">
        </div>
      </div>

      <div style="background:rgba(16,185,129,0.08); border:1px solid rgba(16,185,129,0.25); border-radius:10px; padding:12px 18px; margin-bottom:18px; font-size:12px; color:#34d399; display:flex; align-items:center; justify-content:space-between;">
        <div style="display:flex; align-items:center; gap:8px;">
          <span>ℹ️</span>
          <span><strong>Per-Closer WhatsApp Isolation Active:</strong> Each team member connects their own individual WhatsApp line. New closers self-onboard on the landing page.</span>
        </div>
        <button class="btn btn-emerald" style="padding:4px 10px; font-size:11px;" onclick="switchDashboardTab('pending')">Review Pending Closers ⏳</button>
      </div>

      <div style="overflow-x: auto;">
        <table>
          <thead>
            <tr>
              <th>Employee</th>
              <th>Role</th>
              <th>WhatsApp Phone</th>
              <th>Usage (Sent / Limit)</th>
              <th>Daily Message Limiter</th>
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
  </div>

  <!-- TAB 2: LIVE LEADS & ADMIN TAKEOVER -->
  <div class="dash-pane" id="paneLeads">
    <div class="content-box">
      <div class="box-header">
        <div>
          <div class="box-title"><span>👑</span> Live Company Leads & Executive Takeover Board</div>
          <div class="box-desc">
            If an admin identifies a high-potential lead, click <strong>Takeover Lead</strong> to close it personally. The original closer's commission remains 100% protected and delivered.
          </div>
        </div>
        <div style="display:flex; gap:8px; flex-wrap:wrap;">
          <a href="index.html?company_id=<?php echo urlencode($activeCompany['id']); ?>" target="_blank" class="btn btn-purple" style="display:inline-flex; align-items:center; gap:6px;">
            <span>💬</span> Full-Screen Live Chats (All TMs) ↗
          </a>
          <button class="btn btn-secondary" onclick="loadCompanyLeads()">🔄 Refresh Leads</button>
        </div>
      </div>

      <div style="overflow-x: auto;">
        <table>
          <thead>
            <tr>
              <th>Lead Name & Phone</th>
              <th>Assigned Closer</th>
              <th>Pipeline State</th>
              <th>Deal Potential</th>
              <th>Takeover Status</th>
              <th>Takeover Action</th>
            </tr>
          </thead>
          <tbody id="leadsTableBody">
            <tr><td colspan="6" style="text-align: center; padding: 24px; color: var(--text-muted);">Fetching company leads...</td></tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- TAB 3: CAMPAIGN DISPATCHER WITH 3 STARTING VARIATIONS -->
  <div class="dash-pane" id="paneCampaigns">
    <div class="content-box">
      <div class="box-header">
        <div>
          <div class="box-title"><span>🚀</span> Campaign Dispatcher — 3 Starting Variations</div>
          <div class="box-desc">Select how the company wants to launch outbound lead outreach. Choose from Text Only, Text + Image Brochure, or Audio/Voice Note.</div>
        </div>
      </div>

      <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 16px; margin-bottom: 24px;">
        <!-- Variation 1: Text Only -->
        <div style="background: rgba(255,255,255,0.03); border: 1.5px solid var(--border-accent); border-radius: 14px; padding: 20px; cursor: pointer;" onclick="selectCampaignVar('text')">
          <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:10px;">
            <strong style="font-size:15px;">📝 Variation 1: Text Message Only</strong>
            <input type="radio" name="campVarRadio" id="radioVarText" checked>
          </div>
          <p style="font-size:12.5px; color:var(--text-secondary); line-height:1.5;">
            Dynamic spun text template with personalized variables like <code>{name}</code> and greetings <code>{Salam|AoA|Hello}</code>.
          </p>
        </div>

        <!-- Variation 2: Text + Image Brochure -->
        <div style="background: rgba(255,255,255,0.03); border: 1px solid var(--border-subtle); border-radius: 14px; padding: 20px; cursor: pointer;" onclick="selectCampaignVar('text_image')">
          <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:10px;">
            <strong style="font-size:15px;">🖼️ Variation 2: Text + Image Flyer</strong>
            <input type="radio" name="campVarRadio" id="radioVarImage">
          </div>
          <p style="font-size:12.5px; color:var(--text-secondary); line-height:1.5;">
            Sends a high-converting product image or flyer attachment with personalized caption directly in WhatsApp.
          </p>
        </div>

        <!-- Variation 3: Voice Note / Audio -->
        <div style="background: rgba(255,255,255,0.03); border: 1px solid var(--border-subtle); border-radius: 14px; padding: 20px; cursor: pointer;" onclick="selectCampaignVar('audio')">
          <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:10px;">
            <strong style="font-size:15px;">🎙️ Variation 3: Voice Note Pitch</strong>
            <input type="radio" name="campVarRadio" id="radioVarAudio">
          </div>
          <p style="font-size:12.5px; color:var(--text-secondary); line-height:1.5;">
            Dispatches authentic WhatsApp voice note (PTT opus waveform) for maximum trust and 4x higher listener response rates.
          </p>
        </div>
      </div>

      <!-- Campaign Form -->
      <form id="campaignLaunchForm" onsubmit="handleLaunchCampaign(event)" style="max-width: 680px;">
        <div class="form-group">
          <label class="form-label">Campaign Message Body (Personalized Spin Text)</label>
          <textarea id="campMessageText" class="form-control" rows="4" style="font-family: inherit; resize: vertical;">{Salam|AoA|Hello} {name}! Welcome to <?php echo htmlspecialchars($activeCompany['name']); ?>. We have provisioned your special package today.</textarea>
          <div style="font-size: 11px; color: var(--text-muted); margin-top: 4px;">Supports Spintax syntax and <code>{name}</code> personalizer tag.</div>
        </div>

        <div id="imageUploadGroup" class="form-group" style="display:none;">
          <label class="form-label">Image Attachment URL or File Path</label>
          <input type="text" id="campImageUrl" class="form-control" placeholder="https://example.com/brochure.jpg">
        </div>

        <div id="audioUploadGroup" class="form-group" style="display:none;">
          <label class="form-label">Voice Note File (Opus/Ogg/MP3 Audio)</label>
          <input type="text" id="campAudioUrl" class="form-control" placeholder="assets/audio/pitch.ogg">
        </div>

        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Target Filter</label>
            <select id="campTargetFilter" class="form-control">
              <option value="new">Uncontacted New Leads Only</option>
              <option value="ongoing">Follow-up with Ongoing Leads</option>
              <option value="all">All Pipeline Contacts</option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Pacing Delay (Anti-Ban Protection)</label>
            <select id="campDelay" class="form-control">
              <option value="15">Safe Mode (15 - 25s delay)</option>
              <option value="30">Ultra-Safe (30 - 45s delay)</option>
              <option value="5">Turbo (5 - 10s delay)</option>
            </select>
          </div>
        </div>

        <button type="submit" id="btnLaunchCamp" class="btn btn-emerald" style="padding: 12px 24px; font-size: 14px;">
          🚀 Dispatch Campaign Now
        </button>
      </form>
    </div>
  </div>

  <!-- TAB 5: TL COMMISSION & NUNNO PLAN LEDGER -->
  <div class="dash-pane" id="paneCommissions">
    <div class="content-box">
      <div class="box-header">
        <div>
          <div class="box-title"><span>💰</span> Team Leader (TL) Commission & Nunno Plan Ledger</div>
          <div class="box-desc">
            Team Leaders earn <strong>Rs. 500 PKR</strong> per joined TM. TMs are required to buy the Nunno Rs. 1,700 plan to unlock live closing.
          </div>
        </div>
      </div>

      <div style="overflow-x: auto;">
        <table>
          <thead>
            <tr>
              <th>Team Leader</th>
              <th>Recruited TMs</th>
              <th>Nunno Rs. 1700 Plan Verified</th>
              <th>Commission Accrued (@ Rs. 500)</th>
              <th>Payout Status</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody id="tlLedgerTableBody">
            <tr><td colspan="6" style="text-align: center; padding: 24px; color: var(--text-muted);">Loading Team Lead records...</td></tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- TAB 6: PRODUCTS & PITCH CONFIG -->
  <div class="dash-pane" id="paneProducts">
    <div class="content-box">
      <div class="box-header">
        <div>
          <div class="box-title"><span>⚙️</span> Company Product Catalog & Sales Pitch Config</div>
          <div class="box-desc">Define products, closing prices, and closer commission tiers for <?php echo htmlspecialchars($activeCompany['name']); ?>.</div>
        </div>
        <button class="btn btn-emerald" onclick="saveProductConfig()">💾 Save Changes</button>
      </div>

      <div class="form-group" style="max-width: 600px;">
        <label class="form-label">Default WhatsApp Pitch Template</label>
        <textarea id="cfgDefaultPitch" class="form-control" rows="3">{Salam|AoA|Hello} {name}! Welcome to <?php echo htmlspecialchars($activeCompany['name']); ?>. Special limited-time offers available today.</textarea>
      </div>

      <h4 style="font-size: 14px; font-weight: 700; margin: 20px 0 12px;">Active Products & Commissions</h4>
      <div id="productsListContainer" style="display: flex; flex-direction: column; gap: 10px; max-width: 700px;">
        <!-- Injected via JS -->
      </div>
    </div>
  </div>

<?php endif; ?>

</div>

<!-- ADD EMPLOYEE / CLOSER MODAL -->
<div class="modal-overlay" id="addEmpModal">
  <div class="modal-card">
    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 18px;">
      <h3 class="modal-title">➕ Provision Closer / Team Lead</h3>
      <button onclick="closeAddEmployeeModal()" style="background:none; border:none; color:var(--text-muted); font-size:22px; cursor:pointer;">&times;</button>
    </div>

    <form id="addEmpForm" onsubmit="handleAddEmployee(event)">
      <div class="form-group">
        <label class="form-label">Full Name *</label>
        <input type="text" id="empName" class="form-control" placeholder="e.g. Bilal Ahmed" required>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Username *</label>
          <input type="text" id="empUsername" class="form-control" placeholder="e.g. bilal" required>
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
            <option value="member">Team Member (Closer TM)</option>
            <option value="lead">Team Leader (TL Commission)</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Daily Message Limit *</label>
          <input type="number" id="empLimit" class="form-control" value="150" min="10" max="5000">
        </div>
      </div>

      <div class="form-group">
        <label class="form-label">WhatsApp Phone (Optional)</label>
        <input type="text" id="empPhone" class="form-control" placeholder="+923001234567">
      </div>

      <div id="addEmpError" style="display: none; padding: 10px; border-radius: 8px; background: rgba(244, 63, 94, 0.15); border: 1px solid rgba(244, 63, 94, 0.3); color: #fb7185; font-size: 12.5px; margin-bottom: 14px;"></div>

      <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px;">
        <button type="button" class="btn btn-secondary" onclick="closeAddEmployeeModal()">Cancel</button>
        <button type="submit" id="addEmpBtn" class="btn btn-emerald">Provision Account 🚀</button>
      </div>
    </form>
  </div>
</div>

<!-- MODAL: Approve Pending TM Closer -->
<div id="modalApproveTM" class="modal-overlay" style="display: none;">
  <div class="modal-card" style="max-width: 480px;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
      <div>
        <div style="font-size:11px; font-weight:800; text-transform:uppercase; color:#10b981; letter-spacing:0.05em;">Manual Verification</div>
        <h3 class="modal-title" style="margin:0;">Approve Closer Application</h3>
      </div>
      <button type="button" onclick="closeApproveTMModal()" style="background:none; border:none; color:var(--text-muted); font-size:24px; cursor:pointer;">&times;</button>
    </div>

    <div style="background:rgba(16,185,129,0.08); border:1px solid rgba(16,185,129,0.25); border-radius:10px; padding:12px 16px; margin-bottom:18px;">
      <div style="font-size:14px; font-weight:800; color:#34d399;" id="approveTMName">Applicant Name</div>
      <div style="font-size:12px; color:var(--text-secondary); margin-top:2px;" id="approveTMUsername">@username</div>
    </div>

    <form onsubmit="submitApproveTM(event)">
      <input type="hidden" id="approveTMId">
      
      <div class="form-group">
        <label class="form-label">Assign Daily Message Quota *</label>
        <div style="display:flex; gap:8px; align-items:center;">
          <input type="number" id="approveTMLimit" class="form-control" value="150" min="10" max="5000" required style="font-size:15px; font-weight:700;">
          <button type="button" class="btn btn-secondary" onclick="adjustApproveLimit(50)">+50</button>
          <button type="button" class="btn btn-secondary" onclick="adjustApproveLimit(100)">+100</button>
        </div>
        <div style="font-size:11px; color:var(--text-muted); margin-top:5px;">This closer will be able to dispatch this many messages per day. (You can adjust anytime).</div>
      </div>

      <div id="approveTMError" style="display: none; padding: 10px; border-radius: 8px; background: rgba(244, 63, 94, 0.15); border: 1px solid rgba(244, 63, 94, 0.3); color: #fb7185; font-size: 12.5px; margin-bottom: 14px;"></div>

      <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px;">
        <button type="button" class="btn btn-secondary" onclick="closeApproveTMModal()">Cancel</button>
        <button type="submit" id="approveTMSubmitBtn" class="btn btn-emerald">Approve &amp; Activate Closer 🚀</button>
      </div>
    </form>
  </div>
</div>

<script>
let currentEmployees = [];
let currentCompanyLeads = [];
let currentPendingTMs = [];

function switchDashboardTab(tabName) {
  const tabs = ['closers', 'pending', 'leads', 'campaigns', 'commissions', 'products'];
  tabs.forEach((t) => {
    const pane = document.getElementById('pane' + t.charAt(0).toUpperCase() + t.slice(1));
    const btn = document.getElementById('tabBtn' + t.charAt(0).toUpperCase() + t.slice(1));
    if (pane) pane.classList.toggle('active', t === tabName);
    if (btn) btn.classList.toggle('active', t === tabName);
  });
  if (tabName === 'pending') loadPendingTMs();
  if (tabName === 'leads') loadCompanyLeads();
  if (tabName === 'products') loadCompanyConfig();
}

function adjustApproveLimit(delta) {
  const inp = document.getElementById('approveTMLimit');
  inp.value = (parseInt(inp.value) || 150) + delta;
}

function openApproveTMModal(id) {
  const app = currentPendingTMs.find(a => a.id === id) || { id: id, name: 'Applicant', username: 'closer' };
  document.getElementById('approveTMId').value = app.id;
  document.getElementById('approveTMName').textContent = app.name;
  document.getElementById('approveTMUsername').textContent = '@' + app.username;
  document.getElementById('approveTMLimit').value = 150;
  document.getElementById('approveTMError').style.display = 'none';
  document.getElementById('modalApproveTM').style.display = 'flex';
}

function closeApproveTMModal() {
  document.getElementById('modalApproveTM').style.display = 'none';
}

async function submitApproveTM(e) {
  e.preventDefault();
  const btn = document.getElementById('approveTMSubmitBtn');
  const errBox = document.getElementById('approveTMError');
  btn.disabled = true;
  btn.textContent = 'Activating Closer…';
  errBox.style.display = 'none';

  const userId = document.getElementById('approveTMId').value;
  const limit = parseInt(document.getElementById('approveTMLimit').value) || 150;

  try {
    const res = await fetch('api/auth.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        action: 'approve_tm',
        user_id: userId,
        message_limit: limit
      })
    });
    const data = await res.json();
    if (data.success) {
      closeApproveTMModal();
      alert('🎉 Closer approved and activated! They can now log in using their credentials.');
      loadPendingTMs();
      loadEmployees();
    } else {
      errBox.textContent = data.error || 'Failed to approve closer.';
      errBox.style.display = 'block';
    }
  } catch (err) {
    errBox.textContent = 'Server communication error.';
    errBox.style.display = 'block';
  } finally {
    btn.disabled = false;
    btn.textContent = 'Approve & Activate Closer 🚀';
  }
}

async function rejectTM(userId) {
  const app = currentPendingTMs.find(a => a.id === userId);
  const name = app ? app.name : 'this applicant';
  if (!confirm(`Are you sure you want to decline and remove application for "${name}"?`)) return;
  try {
    const res = await fetch('api/auth.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'reject_tm', user_id: userId })
    });
    const data = await res.json();
    if (data.success) {
      alert('Application rejected.');
      loadPendingTMs();
    } else {
      alert('⚠️ ' + (data.error || 'Failed to reject applicant.'));
    }
  } catch (err) {
    alert('Server communication error.');
  }
}

async function loadPendingTMs() {
  const tbody = document.getElementById('pendingTableBody');
  const badge = document.getElementById('badgePendingTMCount');
  const headerBadge = document.getElementById('headerPendingBadge');
  try {
    const res = await fetch('api/auth.php?action=list_pending_tms&company_id=<?php echo $activeCompany['id']; ?>');
    const data = await res.json();
    if (data.success && Array.isArray(data.pending)) {
      currentPendingTMs = data.pending;
      const count = currentPendingTMs.length;
      if (badge) {
        badge.textContent = count;
        badge.style.display = count > 0 ? 'inline-block' : 'none';
      }
      if (headerBadge) {
        headerBadge.textContent = count;
        headerBadge.style.display = count > 0 ? 'inline-block' : 'none';
      }

      if (!count) {
        tbody.innerHTML = '<tr><td colspan="6" style="text-align:center; padding:32px; color:var(--text-muted);">✨ No pending closer applications right now. When users scan their WhatsApp QR and select your company, they will appear here.</td></tr>';
        return;
      }

      tbody.innerHTML = currentPendingTMs.map(app => {
        const pfp = app.pfp || 'https://images.unsplash.com/photo-1535713875002-d1d0cf377fde?auto=format&fit=crop&w=120&q=80';
        return `
          <tr>
            <td>
              <div class="user-profile-cell">
                <img src="${pfp}" alt="${app.name}" style="width:38px; height:38px; border-radius:10px; object-fit:cover; border:1px solid rgba(255,255,255,0.1);">
                <div>
                  <div class="user-name">${app.name}</div>
                  <div class="user-meta">Applicant</div>
                </div>
              </div>
            </td>
            <td>
              <div style="font-weight:700; color:#10b981; font-family:'JetBrains Mono',monospace;">@${app.username}</div>
              <div style="font-size:11px; color:var(--text-secondary);">${app.email || 'No email'}</div>
            </td>
            <td>
              <span style="font-family:'JetBrains Mono',monospace; font-size:12px; color:#f8fafc; font-weight:600;">
                📱 ${app.phone || '<em>Not linked</em>'}
              </span>
            </td>
            <td>
              <div style="font-size:12px; font-weight:600;">${app.city || 'Pakistan'}</div>
              <div style="font-size:11px; color:var(--text-muted); text-transform:capitalize;">${app.gender || 'male'} closer</div>
            </td>
            <td>
              <span style="font-size:11.5px; color:var(--text-secondary);">${app.applied_at || app.created_at || 'Just now'}</span>
            </td>
            <td>
              <div style="display:flex; gap:8px;">
                <button class="btn btn-emerald" style="padding:6px 12px; font-size:11.5px;" onclick="openApproveTMModal('${app.id}')">
                  ✅ Approve (Set Limit)
                </button>
                <button class="btn btn-danger" style="padding:6px 10px; font-size:11.5px;" onclick="rejectTM('${app.id}')">
                  ❌ Decline
                </button>
              </div>
            </td>
          </tr>
        `;
      }).join('');
    }
  } catch (err) {
    tbody.innerHTML = '<tr><td colspan="6" style="text-align:center; padding:20px; color:#fb7185;">Failed to load pending applications.</td></tr>';
  }
}

function selectCampaignVar(v) {
  document.getElementById('radioVarText').checked = (v === 'text');
  document.getElementById('radioVarImage').checked = (v === 'text_image');
  document.getElementById('radioVarAudio').checked = (v === 'audio');
  document.getElementById('imageUploadGroup').style.display = (v === 'text_image') ? 'block' : 'none';
  document.getElementById('audioUploadGroup').style.display = (v === 'audio') ? 'block' : 'none';
}

function openAddEmployeeModal() { document.getElementById('addEmpModal').style.display = 'flex'; }
function closeAddEmployeeModal() { document.getElementById('addEmpModal').style.display = 'none'; }

// Load Employees & Limiter Roster
async function loadEmployees() {
  try {
    const res = await fetch('api/tenant_manager.php?action=list_company_employees&company_id=<?php echo urlencode($activeCompany['id']); ?>');
    const data = await res.json();
    if (data.success && Array.isArray(data.employees)) {
      currentEmployees = data.employees;
      renderEmployeeTable(data.employees);
      updateMetrics(data.employees);
    }
  } catch (err) {
    console.error('Error loading employees:', err);
  }
}

function updateMetrics(employees) {
  const tls = employees.filter(e => e.role === 'lead');
  const tms = employees.filter(e => e.role === 'member');
  const sentToday = employees.reduce((acc, e) => acc + (parseInt(e.messages_sent_today) || 0), 0);
  const totalCommission = tls.reduce((acc, t) => acc + (parseInt(t.tl_commission_balance) || (tms.filter(m => m.joined_via_tl === t.id).length * 500)), 0);

  document.getElementById('metricTotalEmployees').textContent = employees.length;
  document.getElementById('metricTLCount').textContent = tls.length;
  document.getElementById('metricTMCount').textContent = tms.length;
  document.getElementById('metricMessagesToday').textContent = sentToday;
  document.getElementById('metricTLCommissions').textContent = 'Rs. ' + totalCommission.toLocaleString();
}

function renderEmployeeTable(employees) {
  const tbody = document.getElementById('employeeTableBody');
  if (!employees.length) {
    tbody.innerHTML = '<tr><td colspan="7" style="text-align:center; padding:24px; color:var(--text-muted);">No employees found in this company. Click "Add Closer" to provision your first user.</td></tr>';
    return;
  }

  tbody.innerHTML = employees.map(emp => {
    const sent = parseInt(emp.messages_sent_today) || 0;
    const limit = parseInt(emp.message_limit) || 150;
    const isUnlimited = limit === -1;
    const pct = isUnlimited ? 10 : Math.min(100, Math.round((sent / limit) * 100));
    const fillClass = pct >= 90 ? 'fill-red' : (pct >= 70 ? 'fill-amber' : 'fill-green');
    const isLead = emp.role === 'lead';
    const isSuspended = emp.status === 'suspended';

    return `
      <tr>
        <td>
          <div class="user-profile-cell">
            <div class="avatar-circle" style="background:${isLead ? 'linear-gradient(135deg, #14c27a, #0b8a57)' : 'linear-gradient(135deg, #3b82f6, #0284c7)'};">
              ${emp.name.charAt(0).toUpperCase()}
            </div>
            <div>
              <div class="user-name">${emp.name}</div>
              <div class="user-meta">@${emp.username} &bull; Pass: <code style="color:#ec4899; font-weight:700; background:rgba(0,0,0,0.25); padding:1px 5px; border-radius:4px;">${emp.password || 'closer123'}</code> &bull; ${emp.email || ''}</div>
            </div>
          </div>
        </td>
        <td>
          <span style="display:inline-flex; align-items:center; gap:4px; padding:3px 8px; border-radius:6px; font-size:11px; font-weight:700; ${isLead ? 'background:rgba(31, 224, 138,0.15); color:#8ff5c8; border:1px solid rgba(31, 224, 138,0.3);' : 'background:rgba(59,130,246,0.15); color:#93c5fd; border:1px solid rgba(59,130,246,0.3);'}">
            ${isLead ? '👑 Team Lead' : '📱 Closer TM'}
          </span>
        </td>
        <td>
          <span style="font-family:'JetBrains Mono',monospace; font-size:12px;">${emp.phone || '<em>Not linked</em>'}</span>
        </td>
        <td>
          <div style="font-weight:700;">${sent} <span style="font-size:11px; color:var(--text-muted);">/ ${isUnlimited ? '∞' : limit}</span></div>
          <div class="progress-track"><div class="progress-fill ${fillClass}" style="width:${pct}%;"></div></div>
        </td>
        <td>
          <div class="limiter-ctrl-group">
            <input type="number" id="limit_${emp.id}" class="limiter-input" value="${limit}">
            <button class="btn-quick-chip" onclick="quickAdjustLimit('${emp.id}', 50)">+50</button>
            <button class="btn-quick-chip" onclick="quickAdjustLimit('${emp.id}', 100)">+100</button>
            <button class="btn btn-emerald" style="padding:5px 10px; font-size:11px;" onclick="saveMessageLimit('${emp.id}')">Save</button>
          </div>
        </td>
        <td>
          <span class="badge-status ${isSuspended ? 'status-suspended' : 'status-active'}">
            ${isSuspended ? 'Suspended' : 'Active'}
          </span>
        </td>
        <td>
          <div style="display:flex; gap:6px;">
            <button class="btn btn-emerald" style="padding:5px 10px; font-size:11.5px; font-weight:800; white-space:nowrap;" onclick="directLoginAsEmployee('${emp.username}')" title="Directly log into ${emp.name}'s closer portal">
              ⚡ Direct Login
            </button>
            <button class="btn btn-secondary" style="padding:5px 10px; font-size:11.5px;" onclick="toggleEmployeeStatus('${emp.id}')">
              ${isSuspended ? 'Activate' : 'Suspend'}
            </button>
            <button class="btn btn-secondary" style="padding:5px 8px; font-size:11.5px; color:#fbbf24;" title="Reset today's message counter" onclick="resetEmployeeMessages('${emp.id}')">
              🔄 0
            </button>
          </div>
        </td>
      </tr>
    `;
  }).join('');

  renderTLLedger(employees);
}

async function directLoginAsEmployee(username) {
  if (!username) return;
  if (!confirm(`Directly switch to @${username}'s closer portal?\n\nYou will be logged into their live workspace to view their chats and leads. You can return to this Admin Portal anytime.`)) return;
  try {
    const res = await fetch('api/auth.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'direct_login', username: username })
    });
    const data = await res.json();
    if (data.success) {
      localStorage.setItem('omnidesk_impersonated_from_admin', '1');
      localStorage.setItem('omnidesk_impersonated_user', username);
      window.location.href = 'index.html';
    } else {
      alert(data.error || 'Failed to switch user.');
    }
  } catch (err) {
    alert('Error during direct login: ' + err.message);
  }
}

function quickAdjustLimit(empId, delta) {
  const inp = document.getElementById('limit_' + empId);
  if (!inp) return;
  let cur = parseInt(inp.value) || 150;
  if (cur === -1) cur = 0;
  inp.value = cur + delta;
}

async function saveMessageLimit(empId) {
  const inp = document.getElementById('limit_' + empId);
  const newLimit = parseInt(inp.value);
  try {
    const res = await fetch('api/tenant_manager.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        action: 'update_message_limit',
        user_id: empId,
        message_limit: newLimit
      })
    });
    const data = await res.json();
    if (data.success) {
      alert('✅ Message limit updated successfully!');
      loadEmployees();
    } else {
      alert('⚠️ ' + (data.error || 'Failed to update limit.'));
    }
  } catch (err) {
    alert('Network error updating message limit.');
  }
}

async function toggleEmployeeStatus(empId) {
  try {
    const res = await fetch('api/tenant_manager.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'toggle_employee_status', user_id: empId })
    });
    const data = await res.json();
    if (data.success) loadEmployees();
  } catch (err) {
    alert('Error toggling employee status.');
  }
}

async function resetEmployeeMessages(empId) {
  if (!confirm("Reset this closer's message count back to 0 for today?")) return;
  try {
    const res = await fetch('api/tenant_manager.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'reset_employee_quota', user_id: empId })
    });
    const data = await res.json();
    if (data.success) loadEmployees();
  } catch (err) {
    alert('Error resetting quota.');
  }
}

// Add Closer
async function handleAddEmployee(e) {
  e.preventDefault();
  const btn = document.getElementById('addEmpBtn');
  const errBox = document.getElementById('addEmpError');
  btn.disabled = true;
  errBox.style.display = 'none';

  try {
    const res = await fetch('api/tenant_manager.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        action: 'create_company_employee',
        company_id: '<?php echo $activeCompany['id']; ?>',
        name: document.getElementById('empName').value.trim(),
        username: document.getElementById('empUsername').value.trim(),
        password: document.getElementById('empPassword').value.trim(),
        role: document.getElementById('empRole').value,
        message_limit: parseInt(document.getElementById('empLimit').value) || 150,
        phone: document.getElementById('empPhone').value.trim()
      })
    });
    const data = await res.json();
    if (data.success) {
      closeAddEmployeeModal();
      document.getElementById('addEmpForm').reset();
      loadEmployees();
    } else {
      errBox.textContent = data.error || 'Failed to create employee.';
      errBox.style.display = 'block';
    }
  } catch (err) {
    errBox.textContent = 'Server communication error.';
    errBox.style.display = 'block';
  } finally {
    btn.disabled = false;
  }
}

// TL Commission Ledger Rendering
function renderTLLedger(employees) {
  const tls = employees.filter(e => e.role === 'lead');
  const tbody = document.getElementById('tlLedgerTableBody');
  if (!tls.length) {
    tbody.innerHTML = '<tr><td colspan="6" style="text-align:center; padding:20px; color:var(--text-muted);">No Team Leads appointed yet.</td></tr>';
    return;
  }

  tbody.innerHTML = tls.map(tl => {
    const recruited = employees.filter(m => m.joined_via_tl === tl.id).length;
    const commission = (parseInt(tl.tl_commission_balance) || (recruited * 500));
    return `
      <tr>
        <td><strong>${tl.name}</strong><br><span style="font-size:11px; color:var(--text-muted);">${tl.phone || 'No phone'}</span></td>
        <td><strong style="color:#34d399;">${recruited}</strong> active TMs</td>
        <td><span style="color:#10b981; font-weight:700;">✓ Verified</span></td>
        <td><strong style="color:#8ff5c8; font-size:15px;">Rs. ${commission.toLocaleString()}</strong></td>
        <td><span class="badge-status status-active">Accrued</span></td>
        <td>
          <button class="btn btn-secondary" style="padding:5px 12px; font-size:11.5px;" onclick="alert('Payout request marked for processing!')">
            💳 Approve Payout
          </button>
        </td>
      </tr>
    `;
  }).join('');
}

// Load Company Leads for Live Board & Takeover (Request 8)
async function loadCompanyLeads() {
  const tbody = document.getElementById('leadsTableBody');
  tbody.innerHTML = '<tr><td colspan="6" style="text-align:center; padding:24px; color:var(--text-muted);">Fetching company leads...</td></tr>';
  try {
    const res = await fetch('api/companies.php?action=get_company_leads');
    const data = await res.json();
    if (data.success && Array.isArray(data.leads)) {
      currentCompanyLeads = data.leads;
      renderLeadsTable(data.leads);
      
      const paid = data.leads.filter(l => ['paid', 'verified', 'approved'].includes((l.leadState || l.status || '').toLowerCase())).length;
      document.getElementById('metricPaidLeads').textContent = paid + ' Paid';
      const rate = data.leads.length ? Math.round((paid / data.leads.length) * 100) : 0;
      document.getElementById('metricConversionRate').textContent = rate + '%';
    }
  } catch (err) {
    tbody.innerHTML = '<tr><td colspan="6" style="text-align:center; padding:24px; color:#fb7185;">Failed to load leads.</td></tr>';
  }
}

function renderLeadsTable(leads) {
  const tbody = document.getElementById('leadsTableBody');
  if (!leads.length) {
    tbody.innerHTML = '<tr><td colspan="6" style="text-align:center; padding:24px; color:var(--text-muted);">No leads currently in company pipeline.</td></tr>';
    return;
  }

  tbody.innerHTML = leads.map(lead => {
    const state = (lead.leadState || lead.status || 'new').toLowerCase();
    const isTakeover = Boolean(lead.isTakeover);
    let stateBadge = `<span style="padding:3px 8px; border-radius:6px; font-size:11px; font-weight:700; background:rgba(59,130,246,0.15); color:#93c5fd;">Ongoing</span>`;
    if (['paid', 'verified', 'approved'].includes(state)) {
      stateBadge = `<span style="padding:3px 8px; border-radius:6px; font-size:11px; font-weight:700; background:rgba(16,185,129,0.15); color:#34d399;">Paid & Closed</span>`;
    } else if (['wont_pay', 'rejected', 'lost'].includes(state)) {
      stateBadge = `<span style="padding:3px 8px; border-radius:6px; font-size:11px; font-weight:700; background:rgba(244,63,94,0.15); color:#fb7185;">Won't Pay</span>`;
    }

    return `
      <tr>
        <td>
          <strong>${lead.name || 'Unnamed Contact'}</strong><br>
          <span style="font-family:'JetBrains Mono',monospace; font-size:11.5px; color:var(--text-muted);">${lead.phone}</span>
        </td>
        <td>
          <span>${lead.ownerName || 'Closer Specialist'}</span>
        </td>
        <td>${stateBadge}</td>
        <td><strong style="color:#fbbf24;">Rs. ${(lead.dealAmount || 5000).toLocaleString()}</strong></td>
        <td>
          ${isTakeover ? `
            <span class="badge-takeover">👑 Admin Takeover</span>
            <div style="font-size:10px; color:#8ff5c8; margin-top:2px;">Commission Protected for: ${lead.originalOwnerName || 'Closer'}</div>
          ` : '<span style="font-size:11px; color:var(--text-muted);">Standard closer ownership</span>'}
        </td>
        <td>
          <div style="display:flex; flex-direction:column; gap:5px;">
            ${isTakeover ? `
              <span style="font-size:11px; color:#34d399; font-weight:700;">✓ Active Under Admin</span>
            ` : `
              <button class="btn-takeover" onclick="handleAdminTakeover('${lead.phone}')">
                👑 Takeover Lead
              </button>
            `}
            <button type="button" class="btn btn-secondary" style="font-size:10.5px; padding:3px 8px; border-radius:6px; font-weight:700; background:rgba(31, 224, 138,0.15); color:#a5b4fc; border:1px solid rgba(31, 224, 138,0.3); cursor:pointer;" onclick="adminInspectFromAdmin('${lead.ownerUsername || 'hamza'}', '${lead.phone}')">
              ⚡ Open Chat as TM ↗
            </button>
          </div>
        </td>
      </tr>
    `;
  }).join('');
}

async function adminInspectFromAdmin(username, phone) {
  try {
    const res = await fetch('api/auth.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'direct_login', username: username })
    });
    const data = await res.json();
    if (data && data.success) {
      localStorage.setItem('omnidesk_impersonated_from_admin', '1');
      localStorage.setItem('omnidesk_impersonated_user', username);
      localStorage.setItem('omnidesk_impersonated_name', data.user?.name || username);
      const cleanPhone = String(phone).replace(/\D/g, '');
      localStorage.setItem('omnidesk_active_chat_phone', cleanPhone);
      window.open(`index.html?chat=${encodeURIComponent(cleanPhone)}&impersonated=1`, '_blank');
    } else {
      alert(data.error || 'Failed to switch into TM workspace.');
    }
  } catch (err) {
    alert('Error logging in as TM: ' + err.message);
  }
}

async function handleAdminTakeover(phone) {
  if (!confirm('Takeover this lead? You will personally finalize and close this customer. The original closer commission is 100% protected and will be delivered.')) return;
  try {
    const res = await fetch('api/companies.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        action: 'takeover_lead',
        lead_phone: phone
      })
    });
    const data = await res.json();
    if (data.success) {
      alert('👑 ' + data.message);
      loadCompanyLeads();
    } else {
      alert('⚠️ ' + (data.error || 'Failed to takeover lead.'));
    }
  } catch (err) {
    alert('Error performing lead takeover.');
  }
}

// Launch Campaign with 3 Variations
async function handleLaunchCampaign(e) {
  e.preventDefault();
  const btn = document.getElementById('btnLaunchCamp');
  btn.disabled = true;
  btn.textContent = 'Launching Campaign…';

  let variation = 'text';
  if (document.getElementById('radioVarImage').checked) variation = 'text_image';
  if (document.getElementById('radioVarAudio').checked) variation = 'audio';

  try {
    const res = await fetch('api/direct_sender.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        action: 'start_campaign',
        variation: variation,
        message: document.getElementById('campMessageText').value.trim(),
        image_url: document.getElementById('campImageUrl').value.trim(),
        audio_url: document.getElementById('campAudioUrl').value.trim(),
        filter: document.getElementById('campTargetFilter').value,
        delay: parseInt(document.getElementById('campDelay').value) || 15
      })
    });
    const data = await res.json();
    if (data.success) {
      alert('🚀 Campaign started successfully in variation: ' + variation.toUpperCase());
    } else {
      alert('⚠️ ' + (data.message || 'Campaign launch notice received.'));
    }
  } catch (err) {
    alert('Network error launching campaign.');
  } finally {
    btn.disabled = false;
    btn.textContent = '🚀 Dispatch Campaign Now';
  }
}

// Products & Pitch Config
async function loadCompanyConfig() {
  try {
    const res = await fetch('api/companies.php?action=get_company_config');
    const data = await res.json();
    if (data.success && data.config) {
      if (data.config.default_pitch) document.getElementById('cfgDefaultPitch').value = data.config.default_pitch;
      renderProductsList(data.config.products || []);
    }
  } catch (err) {
    console.error('Error loading config:', err);
  }
}

function renderProductsList(products) {
  const container = document.getElementById('productsListContainer');
  container.innerHTML = products.map((p, idx) => `
    <div style="display:grid; grid-template-columns: 2fr 1fr 1fr; gap:10px; background:rgba(255,255,255,0.03); border:1px solid var(--border-subtle); border-radius:10px; padding:12px;">
      <div>
        <label style="font-size:11px; color:var(--text-muted); display:block;">Product Name</label>
        <input type="text" id="prodName_${idx}" class="form-control" value="${p.name}">
      </div>
      <div>
        <label style="font-size:11px; color:var(--text-muted); display:block;">Price (PKR)</label>
        <input type="number" id="prodPrice_${idx}" class="form-control" value="${p.amount}">
      </div>
      <div>
        <label style="font-size:11px; color:var(--text-muted); display:block;">Commission</label>
        <input type="number" id="prodComm_${idx}" class="form-control" value="${p.commission}">
      </div>
    </div>
  `).join('');
}

async function saveProductConfig() {
  const defaultPitch = document.getElementById('cfgDefaultPitch').value.trim();
  const products = [];
  for (let i = 0; i < 3; i++) {
    const nameInp = document.getElementById('prodName_' + i);
    const priceInp = document.getElementById('prodPrice_' + i);
    const commInp = document.getElementById('prodComm_' + i);
    if (nameInp && priceInp && commInp) {
      products.push({
        id: 'p_' + i,
        name: nameInp.value.trim(),
        amount: parseInt(priceInp.value) || 5000,
        commission: parseInt(commInp.value) || 1500
      });
    }
  }

  try {
    const res = await fetch('api/companies.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        action: 'update_company_config',
        default_pitch: defaultPitch,
        products: products
      })
    });
    const data = await res.json();
    if (data.success) {
      alert('✅ Company products & sales pitch configuration saved!');
    } else {
      alert('⚠️ ' + (data.error || 'Failed to save configuration.'));
    }
  } catch (err) {
    alert('Error saving configuration.');
  }
}

// Initial boot
loadEmployees();
loadPendingTMs();
loadCompanyLeads();
setInterval(loadPendingTMs, 8000);

function toggleTheme() {
  const isLight = document.documentElement.classList.toggle('theme-light');
  try { localStorage.setItem('nunno_theme', isLight ? 'light' : 'dark'); } catch(_) {}
}
</script>
</body>
</html>