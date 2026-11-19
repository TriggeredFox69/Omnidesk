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
$isLoggedInAdmin = ($currentUser && in_array(($currentUser['role'] ?? ''), ['admin', 'superadmin'], true));

// Only show the administrative portal if the user is authenticated AND (explicitly viewing portal ?portal=1 OR has an active impersonated session from Super Admin)
// Direct visits to /cc or cc.php or admin.php without ?portal=1 will ALWAYS present the Company Admin Login form
$isPortalView = (isset($_GET['portal']) || isset($_GET['dashboard']) || !empty($currentUser['is_impersonated']));
$isAdmin = ($isLoggedInAdmin && $isPortalView && !isset($_GET['login']) && !isset($_GET['switch']));

// Logout action
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
    }
    @session_destroy();
    header('Location: /cc');
    exit;
}

// Load company details
$companiesFile = __DIR__ . '/data/companies.json';
$companies = file_exists($companiesFile) ? json_decode(file_get_contents($companiesFile), true) : [];
$companyId = !empty($_GET['company_id'])
    ? $_GET['company_id']
    : ($currentUser['company_id'] ?? 'COMP-001');

$activeCompany = null;
if (is_array($companies)) {
    foreach ($companies as $c) {
        if ($c['id'] === $companyId || ($c['slug'] ?? '') === $companyId) {
            $activeCompany = $c;
            break;
        }
    }
}
if (!$activeCompany && ($companyId === 'COMP-004' || $companyId === 'metalink')) {
    $activeCompany = [
        'id'                    => 'COMP-004',
        'name'                  => 'Metalink',
        'slug'                  => 'metalink',
        'plan'                  => 'Enterprise Growth',
        'default_message_limit' => 250,
        'logo'                  => 'assets/images/metalink-logo.webp'
    ];
} elseif (!$activeCompany) {
    $activeCompany = [
        'id'                    => $companyId ?: 'COMP-001',
        'name'                  => $currentUser['company_name'] ?? ($currentUser['name'] ?? 'Company Admin'),
        'slug'                  => strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $currentUser['username'] ?? 'company')),
        'plan'                  => 'Enterprise Pro',
        'default_message_limit' => 250,
        'fallback_phone'        => '+923318036576',
        'logo'                  => 'assets/images/chatly-icon.png'
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <base href="/">
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
  <title><?php echo $isAdmin ? ('Chatly Company Admin — ' . htmlspecialchars($activeCompany['name'])) : 'Chatly — Company Administrator Portal'; ?></title>
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
  <div style="min-height: 80vh; display: flex; align-items: center; justify-content: center; padding: 20px;">
    <div style="width: 100%; max-width: 440px; background: var(--bg-surface); border: 1px solid var(--border-accent); border-radius: 20px; padding: 36px; box-shadow: 0 20px 50px rgba(0,0,0,0.5); text-align: center;">
      <div style="width: 60px; height: 60px; border-radius: 16px; background: linear-gradient(135deg, #10b981, #059669); margin: 0 auto 16px; display: flex; align-items: center; justify-content: center; font-size: 26px;">🏢</div>
      <h2 style="font-size: 22px; font-weight: 800; margin-bottom: 6px;">Company Admin Portal</h2>
      <p style="font-size: 13px; color: var(--text-secondary); margin-bottom: 24px;">Enter company administrator credentials to manage your workforce and sales desk.</p>

      <form id="adminLoginForm" onsubmit="handleAdminLogin(event)">
        <div class="form-group" style="text-align: left;">
          <label class="form-label">Username / Email</label>
          <input type="text" id="aUsername" class="form-control" placeholder="Company admin username or email" required autofocus>
        </div>
        <div class="form-group" style="text-align: left; margin-bottom: 22px;">
          <label class="form-label">Password</label>
          <input type="password" id="aPassword" class="form-control" placeholder="••••••••" required>
        </div>
        <div id="loginError" style="display: none; padding: 10px; border-radius: 8px; background: rgba(244, 63, 94, 0.15); border: 1px solid rgba(244, 63, 94, 0.3); color: #fb7185; font-size: 12.5px; margin-bottom: 14px;"></div>
        <button type="submit" id="adminLoginBtn" class="btn btn-emerald" style="width: 100%; justify-content: center; padding: 12px; font-weight: 700;">
          Access Company Control 🏢
        </button>
      </form>
      <div style="margin-top: 22px; font-size: 12px; color: var(--text-secondary);">
        <a href="/" style="color: var(--accent-emerald); text-decoration: none; font-weight: 600;">&larr; Return to Chatly Homepage</a>
      </div>
    </div>
  </div>

<?php else: ?>
  <!-- EXECUTIVE COMPANY ADMIN DASHBOARD -->
  <?php if (!empty($currentUser['is_impersonated'])): ?>
    <div style="background: linear-gradient(90deg, #eab308, #ca8a04); color: #000; padding: 10px 18px; font-size: 13px; font-weight: 700; display: flex; align-items: center; justify-content: space-between; border-radius: 12px; margin-bottom: 18px; box-shadow: 0 4px 15px rgba(234, 179, 8, 0.25);">
      <div style="display:flex; align-items:center; gap:8px;">
        <span style="font-size:16px;">👑</span>
        <span>Super Admin Impersonation: You are managing <strong><?php echo htmlspecialchars($activeCompany['name']); ?></strong> (<?php echo htmlspecialchars($activeCompany['id']); ?>) with full administrative privileges.</span>
      </div>
      <a href="/ss?portal=1" style="background: #000; color: #facc15; padding: 5px 14px; border-radius: 8px; text-decoration: none; font-size: 12px; font-weight: 800; display: inline-flex; align-items: center; gap: 4px;">&larr; Exit to Super Admin HQ</a>
    </div>
  <?php endif; ?>
  <header class="top-bar">
    <div class="brand-section" style="display:flex; align-items:center; gap:12px;">
      <a href="/" title="Chatly Homepage" style="display:flex; align-items:center; text-decoration:none;">
        <img src="assets/images/chatly-logo.png" alt="Chatly" class="chatly-logo chatly-logo--dark"><img src="assets/images/chatly-logo-light.png" alt="Chatly" class="chatly-logo chatly-logo--light">
      </a>
      <div style="height:28px; width:1px; background:rgba(255,255,255,0.15); margin:0 2px;"></div>
      <a href="/cc?portal=1&company_id=<?php echo urlencode($activeCompany['id']); ?>" style="display:flex; align-items:center; gap:12px; text-decoration:none; color:inherit;" title="Refresh Company Admin Portal">
        <img id="adminCompLogo" src="<?php echo htmlspecialchars($activeCompany['logo'] ?? 'assets/images/chatly-icon.png'); ?>" alt="Company Logo" class="company-logo">
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
      </a>
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
      <a href="/cc?login=1" class="btn btn-secondary" style="font-size:12px;" title="Switch Company Account">
        <span>🔄</span> Switch
      </a>
      <a href="/cc?action=logout" class="btn btn-danger">
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
      <span>🚀</span> Multi-Device Campaign (Round-Robin)
    </button>
    <button class="dash-tab-btn" id="tabBtnCommissions" onclick="switchDashboardTab('commissions')">
      <span>💰</span> TL Commission &amp; Plan Ledger
    </button>
    <button class="dash-tab-btn" id="tabBtnProducts" onclick="switchDashboardTab('products')">
      <span>⚙️</span> Products &amp; Pitch Config
    </button>
    <button class="dash-tab-btn" id="tabBtnWithdrawals" onclick="switchDashboardTab('withdrawals')">
      <span>💸</span> Closer Withdrawals &amp; Payouts <span id="badgePendingWdCount" style="display:none; background:#f59e0b; color:#000; font-size:10px; padding:2px 7px; border-radius:10px; font-weight:800; margin-left:6px;">0</span>
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

  <!-- TAB 3: MULTI-DEVICE CAMPAIGN DISPATCHER (ROUND-ROBIN) -->
  <div class="dash-pane" id="paneCampaigns">
    <!-- Active Connected WhatsApps Pool Matrix -->
    <div class="content-box" style="margin-bottom: 20px;">
      <div class="box-header">
        <div>
          <div class="box-title"><span>📱</span> Connected WhatsApp Devices Roster (Multi-Sender Pool)</div>
          <div class="box-desc">
            All connected TMs and TLs for <strong><?php echo htmlspecialchars($activeCompany['name']); ?></strong> are aligned in the sender pool. Outbound campaigns dispatch strictly <strong>1-by-1 in round-robin order</strong> across all numbers, repeating the full cycle until the target audience is completed.
          </div>
        </div>
        <div style="display:flex; align-items:center; gap:10px;">
          <span id="campaignPoolCountBadge" class="badge" style="background: rgba(16, 185, 129, 0.15); color: #10b981; font-weight: 700; padding: 6px 12px; font-size: 13px;">
            Loading devices...
          </span>
          <button class="btn btn-secondary" onclick="loadCampaignSenders()" style="padding: 6px 12px; font-size: 12px;">🔄 Refresh Pool</button>
        </div>
      </div>

      <!-- Senders Cards Grid -->
      <div id="campaignSendersPoolContainer" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 14px; margin-top: 14px;">
        <div style="color: var(--text-muted); font-size: 13px;">Loading company WhatsApp numbers...</div>
      </div>

      <!-- Round-Robin Visual Sequence Banner -->
      <div style="margin-top: 18px; padding: 14px 18px; background: rgba(16, 185, 129, 0.05); border: 1px dashed rgba(16, 185, 129, 0.3); border-radius: 12px;">
        <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 8px;">
          <div style="font-size: 12px; font-weight: 700; color: #10b981; display: flex; align-items: center; gap: 6px;">
            <span>🔄</span> Round-Robin Dispatch Sequence:
          </div>
          <div id="campaignCycleMathInfo" style="font-size: 11.5px; color: var(--text-secondary);">
            Messages alternate 1-by-1 across all devices &bull; Cycle repeats until completed
          </div>
        </div>
        <div id="campaignRotationFlowPreview" style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap; margin-top: 10px; font-size: 12px; font-family: 'JetBrains Mono', monospace;">
          <!-- Dynamically populated: [Device 1] ➔ [Device 2] ➔ [Device 3] ➔ [Repeat] -->
        </div>
      </div>
    </div>

    <!-- Live Real-Time Campaign Progress & Control Terminal (Hidden until running/paused/active) -->
    <div id="campaignLiveMonitorBox" class="content-box" style="display: none; margin-bottom: 20px; border-color: rgba(16, 185, 129, 0.4); box-shadow: 0 0 25px rgba(16, 185, 129, 0.1);">
      <div class="box-header">
        <div>
          <div style="display: flex; align-items: center; gap: 10px;">
            <span class="box-title"><span>⚡</span> Live Multi-Device Campaign Monitor</span>
            <span id="liveCampStatusBadge" class="badge" style="background:#10b981; color:#050b09; font-weight:800; font-size:11px; text-transform:uppercase;">IDLE</span>
          </div>
          <div id="liveCampSubTitle" class="box-desc" style="margin-top:4px;">Dispatches rotating across company WhatsApp devices in real time.</div>
        </div>
        <div style="display: flex; align-items: center; gap: 8px;">
          <button id="btnCampPause" class="btn btn-secondary" onclick="pauseLiveCampaign()" style="padding: 6px 12px; font-size: 12px;">⏸️ Pause</button>
          <button id="btnCampResume" class="btn btn-emerald" onclick="resumeLiveCampaign()" style="display:none; padding: 6px 12px; font-size: 12px;">▶️ Resume</button>
          <button id="btnCampStop" class="btn btn-danger" onclick="stopLiveCampaign()" style="padding: 6px 12px; font-size: 12px;">⏹️ Halt</button>
        </div>
      </div>

      <!-- Real-time Metrics Matrix -->
      <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px; margin-top: 14px; margin-bottom: 16px;">
        <div style="background: rgba(255,255,255,0.03); border: 1px solid var(--border-subtle); border-radius: 10px; padding: 12px;">
          <div style="font-size: 11px; color: var(--text-muted); text-transform: uppercase;">Current Round-Robin Cycle</div>
          <div id="liveCampCycleVal" style="font-size: 20px; font-weight: 800; color: #10b981; margin-top: 4px;">Cycle 1 / 1</div>
          <div id="liveCampCycleSub" style="font-size: 11px; color: var(--text-secondary); margin-top: 2px;">Device rotation active</div>
        </div>
        <div style="background: rgba(255,255,255,0.03); border: 1px solid var(--border-subtle); border-radius: 10px; padding: 12px;">
          <div style="font-size: 11px; color: var(--text-muted); text-transform: uppercase;">Active Sending Device</div>
          <div id="liveCampActiveSenderVal" style="font-size: 15px; font-weight: 700; color: #38bdf8; margin-top: 4px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">Device 1</div>
          <div id="liveCampActiveSenderRole" style="font-size: 11px; color: var(--text-secondary); margin-top: 2px;">TM Closer</div>
        </div>
        <div style="background: rgba(255,255,255,0.03); border: 1px solid var(--border-subtle); border-radius: 10px; padding: 12px;">
          <div style="font-size: 11px; color: var(--text-muted); text-transform: uppercase;">Total Sent / Audience</div>
          <div id="liveCampSentRatio" style="font-size: 20px; font-weight: 800; color: #fff; margin-top: 4px;">0 / 0</div>
          <div id="liveCampFailedCount" style="font-size: 11px; color: #ef4444; margin-top: 2px;">0 failed</div>
        </div>
        <div style="background: rgba(255,255,255,0.03); border: 1px solid var(--border-subtle); border-radius: 10px; padding: 12px;">
          <div style="font-size: 11px; color: var(--text-muted); text-transform: uppercase;">Pacing Cadence</div>
          <div id="liveCampCountdownVal" style="font-size: 20px; font-weight: 800; color: #fbbf24; margin-top: 4px;">Next in: 0s</div>
          <div style="font-size: 11px; color: var(--text-secondary); margin-top: 2px;">Anti-ban jitter protection</div>
        </div>
      </div>

      <!-- Progress Bar -->
      <div style="margin-bottom: 16px;">
        <div style="display:flex; justify-content:space-between; font-size:12px; margin-bottom:6px;">
          <span style="color:var(--text-secondary);">Campaign Progress</span>
          <span id="liveCampPct" style="font-weight:700; color:#10b981;">0%</span>
        </div>
        <div style="height: 8px; background: rgba(255,255,255,0.08); border-radius: 999px; overflow: hidden;">
          <div id="liveCampProgressBar" style="height: 100%; width: 0%; background: linear-gradient(90deg, #10b981, #38bdf8); transition: width 0.3s ease;"></div>
        </div>
      </div>

      <!-- Live Terminal Console -->
      <div>
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 6px;">
          <span style="font-size: 12px; font-weight: 700; color: var(--text-secondary); display: flex; align-items: center; gap: 6px;">
            <span>💻</span> Real-Time Multi-Device Dispatch Terminal
          </span>
          <span style="font-size: 11px; color: var(--text-muted); font-family: monospace;">auto-scrolls</span>
        </div>
        <div id="liveCampTerminal" style="height: 180px; overflow-y: auto; background: #050b09; border: 1px solid rgba(16, 185, 129, 0.25); border-radius: 8px; padding: 12px; font-family: 'JetBrains Mono', Consolas, monospace; font-size: 12px; line-height: 1.6; color: #94a3b8;">
          <div style="color: #64748b;">Ready. Dispatches will stream here live...</div>
        </div>
      </div>
    </div>

    <!-- Campaign Launch Configuration Box -->
    <div class="content-box">
      <div class="box-header">
        <div>
          <div class="box-title"><span>🚀</span> Launch Multi-Device Campaign — 3 Starting Variations</div>
          <div class="box-desc">Choose from Text Message, Flyer Image Brochure, or Voice Note Pitch. The campaign will automatically rotate through all active devices above.</div>
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
            Dynamic spun text template with personalized variables like <code>{name}</code>, <code>{company}</code>, and greetings <code>{Salam|AoA|Hello}</code>.
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
      <form id="campaignLaunchForm" onsubmit="handleLaunchCampaign(event)" style="max-width: 720px;">
        <div class="form-group">
          <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
            <label class="form-label" style="margin: 0;">Campaign Message Body (Personalized Spin Text)</label>
            <div style="display: flex; gap: 6px;">
              <button type="button" class="btn btn-secondary" onclick="insertCampTag('{name}')" style="padding: 2px 8px; font-size: 11px;">+ {name}</button>
              <button type="button" class="btn btn-secondary" onclick="insertCampTag('{company}')" style="padding: 2px 8px; font-size: 11px;">+ {company}</button>
              <button type="button" class="btn btn-secondary" onclick="insertCampTag('{Salam|AoA|Hello}')" style="padding: 2px 8px; font-size: 11px;">+ Greeting</button>
            </div>
          </div>
          <textarea id="campMessageText" class="form-control" rows="4" style="font-family: inherit; resize: vertical;">{Salam|AoA|Hello} {name}! Welcome to <?php echo htmlspecialchars($activeCompany['name']); ?>. We have provisioned your special package today. Reply here to activate.</textarea>
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
            <label class="form-label">Target Audience Filter</label>
            <select id="campTargetFilter" class="form-control" onchange="calculateCampaignCycles()">
              <option value="demo">⭐ Quick 10-Prospect Partner Demo (2 Full Cycles)</option>
              <option value="new">Uncontacted Fresh Leads Only</option>
              <option value="ongoing">Follow-up with Ongoing Leads</option>
              <option value="all">All Pipeline Contacts</option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Pacing Delay & Speed</label>
            <select id="campDelay" class="form-control">
              <option value="3">⚡ Fast Partner Demo (3s - 5s delay — instant visual test)</option>
              <option value="6">Fast Outreach (6s - 10s delay)</option>
              <option value="15">Safe Mode (15s - 25s delay)</option>
              <option value="30">Ultra-Safe (30s - 45s delay)</option>
            </select>
          </div>
        </div>

        <!-- Dynamic Cycle Preview Card -->
        <div id="campaignCalcPreviewCard" style="background: rgba(56, 189, 248, 0.06); border: 1px solid rgba(56, 189, 248, 0.25); border-radius: 12px; padding: 14px 18px; margin-bottom: 20px;">
          <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px;">
            <div>
              <div style="font-size: 13px; font-weight: 700; color: #38bdf8;">
                📊 Round-Robin Cycle Calculation:
              </div>
              <div id="calcSummaryText" style="font-size: 12px; color: var(--text-secondary); margin-top: 4px;">
                Audience: <strong>10 contacts</strong> &bull; Connected Devices: <strong>5 WhatsApps</strong> &bull; Total: <strong>2 full cycles</strong>
              </div>
            </div>
            <div id="calcOrderTag" style="font-size: 11.5px; font-weight: 700; background: rgba(56, 189, 248, 0.15); color: #38bdf8; padding: 4px 10px; border-radius: 6px;">
              Order: 1 ➔ 2 ➔ 3 ➔ 4 ➔ 5 (Repeat)
            </div>
          </div>
        </div>

        <button type="submit" id="btnLaunchCamp" class="btn btn-emerald" style="padding: 12px 28px; font-size: 14px; font-weight: 700; display: inline-flex; align-items: center; gap: 8px;">
          <span>🚀</span> Dispatch Multi-Device Campaign (Round-Robin)
        </button>
      </form>
    </div>
  </div>

  <!-- TAB 5: TL COMMISSION & NUNNO PLAN LEDGER -->
  <div class="dash-pane" id="paneCommissions">
    <!-- TL Commission Bounty Configuration for this Company -->
    <div class="content-box" style="margin-bottom: 20px;">
      <div class="box-header">
        <div>
          <div class="box-title"><span>⚙️</span> Team Leader (TL) Commission Bounty Configuration</div>
          <div class="box-desc">
            Configure how much commission bounty <strong><?php echo htmlspecialchars($activeCompany['name']); ?></strong> awards to Team Leaders (TL) for each approved Sales Closer (TM) they bring to the company.
          </div>
        </div>
        <button class="btn btn-emerald" onclick="saveTLCommissionRate()">💾 Save Bounty Rate</button>
      </div>

      <div style="display: flex; align-items: flex-end; gap: 16px; max-width: 500px; margin-top: 8px;">
        <div style="flex: 1;">
          <label class="form-label">TL Commission Bounty Per Approved TM (PKR)</label>
          <div style="position: relative;">
            <span style="position: absolute; left: 12px; top: 11px; font-weight: 700; color: #10b981;">Rs.</span>
            <input type="number" id="inputTLCommissionRate" class="form-control" style="padding-left: 45px; font-weight: 800; font-size: 16px; color: #34d399;" value="<?php echo intval($activeCompany['tl_commission_per_tm'] ?? 500); ?>" min="0" step="50">
          </div>
        </div>
        <div>
          <span id="tlCommRateSavedIndicator" style="display: none; color: #10b981; font-size: 12px; font-weight: 700; padding-bottom: 10px;">✓ Saved</span>
        </div>
      </div>
      <div style="font-size: 11.5px; color: var(--text-muted); margin-top: 8px;">
        💡 Whenever an applicant recruited by a TL is approved by this company admin, this bounty is instantly credited to that TL's commission balance.
      </div>
    </div>

    <div class="content-box">
      <div class="box-header">
        <div>
          <div class="box-title"><span>💰</span> Team Leader (TL) Commission & Nunno Plan Ledger</div>
          <div class="box-desc" id="tlLedgerDesc">
            Team Leaders earn commission bounties per recruited TM for <?php echo htmlspecialchars($activeCompany['name']); ?>. TMs are required to buy the Nunno Rs. 1,700 plan to unlock live closing.
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
              <th id="thTLLedgerRate">Commission Accrued (@ Rs. <?php echo number_format(intval($activeCompany['tl_commission_per_tm'] ?? 500)); ?>)</th>
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

  <!-- TAB 6: PRODUCTS & PRICING CONFIG (INTEGRATED WITH CLOSER DESK) -->
  <div class="dash-pane" id="paneProducts">
    <div class="content-box">
      <div class="box-header">
        <div>
          <div class="box-title"><span>⚙️</span> Company Product Catalog &amp; Pricing Configuration</div>
          <div class="box-desc">
            Define products, packages, and closing prices for <strong><?php echo htmlspecialchars($activeCompany['name']); ?></strong>. When TMs or TLs mark deals as paid in their sales chat desk, this exact list and prices will automatically populate their payment verification form.
          </div>
        </div>
        <div style="display:flex; gap:10px; align-items:center;">
          <button type="button" class="btn btn-secondary" onclick="addProductRow()" style="font-weight:700;">
            <span>➕ Add Product</span>
          </button>
          <button type="button" class="btn btn-emerald" onclick="saveProductConfig()" style="font-weight:700;">
            <span>💾 Save Products &amp; Pricing</span>
          </button>
        </div>
      </div>

      <div style="margin-bottom: 24px; max-width: 850px; background: rgba(56, 189, 248, 0.05); border: 1px dashed rgba(56, 189, 248, 0.3); border-radius: 12px; padding: 14px 18px;">
        <div style="display:flex; align-items:center; gap:8px; font-size:12.5px; font-weight:700; color:#38bdf8; margin-bottom:4px;">
          <span>💡</span> Sales Desk Integration:
        </div>
        <div style="font-size:12px; color:var(--text-secondary); line-height:1.5;">
          Whenever a closer (TM or TL) clicks <strong>"Mark Paid"</strong> on a customer lead, they select from this product catalog. The customer's total confirmed price and the closer's commission are calculated automatically according to the values defined here.
        </div>
      </div>

      <div class="form-group" style="max-width: 850px; margin-bottom: 24px;">
        <label class="form-label" style="font-weight:700;">Default WhatsApp Pitch Template</label>
        <textarea id="cfgDefaultPitch" class="form-control" rows="3">{Salam|AoA|Hello} {name}! Welcome to <?php echo htmlspecialchars($activeCompany['name']); ?>. Special limited-time offers available today.</textarea>
        <div style="font-size: 11px; color: var(--text-muted); margin-top: 4px;">Supports Spintax syntax and <code>{name}</code> personalizer tag.</div>
      </div>

      <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 12px; max-width: 850px;">
        <h4 style="font-size: 14px; font-weight: 800; margin: 0; color:var(--text-primary); display:flex; align-items:center; gap:8px;">
          <span>📦</span> Active Products &amp; Pricing Tiers
        </h4>
        <button type="button" class="btn btn-secondary btn-sm" onclick="addProductRow()">+ Add Product</button>
      </div>

      <div id="productsListContainer" style="display: flex; flex-direction: column; gap: 12px; max-width: 850px;">
        <!-- Injected via JS -->
      </div>

      <div style="margin-top: 18px; max-width: 850px;">
        <button type="button" class="btn btn-secondary" onclick="addProductRow()" style="font-weight:700; border-style:dashed; width:100%; padding:10px;">
          ➕ Add Another Product / Package
        </button>
      </div>
    </div>
  </div>

  <!-- TAB 7: CLOSER WITHDRAWALS & PAYOUTS -->
  <div class="dash-pane" id="paneWithdrawals">
    <div class="content-box">
      <div class="box-header">
        <div>
          <div class="box-title"><span>💸</span> Closer Commission Withdrawals &amp; Disbursement Ledger</div>
          <div class="box-desc">
            Audit and settle payout requests submitted by your sales closers (TMs) and team leaders (TLs). Settle directly to EasyPaisa, JazzCash, or Pakistani Bank accounts.
          </div>
        </div>
        <div style="display:flex; gap:10px; align-items:center;">
          <button class="btn btn-secondary" onclick="loadCompanyWithdrawals()">
            <span>🔄</span> Refresh Ledger
          </button>
          <button class="btn btn-emerald" onclick="openAdminPayoutModal()">
            <span>➕</span> Request Company Profit Payout
          </button>
        </div>
      </div>

      <!-- Withdrawal Summary Metrics -->
      <div class="metric-grid" style="grid-template-columns: repeat(3, 1fr); margin-bottom: 20px;">
        <div class="metric-card">
          <div class="metric-header">
            <span class="metric-label">Pending Closer Requests</span>
            <div style="font-size:18px;">⏳</div>
          </div>
          <div class="metric-val" id="compPendingWdAmount" style="color:#fbbf24;">PKR 0</div>
          <div class="metric-sub" id="compPendingWdSub">0 requests awaiting verification</div>
        </div>
        <div class="metric-card">
          <div class="metric-header">
            <span class="metric-label">Total Settled Payouts</span>
            <div style="font-size:18px;">✅</div>
          </div>
          <div class="metric-val" id="compSettledWdAmount" style="color:#34d399;">PKR 0</div>
          <div class="metric-sub" id="compSettledWdSub">0 disbursements completed</div>
        </div>
        <div class="metric-card">
          <div class="metric-header">
            <span class="metric-label">Company Closer Workforce</span>
            <div style="font-size:18px;">👥</div>
          </div>
          <div class="metric-val" id="compCloserCount" style="color:#60a5fa;">0 Closers</div>
          <div class="metric-sub">Earning commission on closed sales</div>
        </div>
      </div>

      <!-- Withdrawals Table -->
      <div style="overflow-x:auto;">
        <table>
          <thead>
            <tr>
              <th>Applicant / Closer</th>
              <th>Amount</th>
              <th>Payout Channel &amp; Institution</th>
              <th>Beneficiary Title &amp; Account / Phone</th>
              <th>Date &amp; Time</th>
              <th>Status</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody id="tableCompanyWithdrawalsBody">
            <tr>
              <td colspan="7" style="text-align:center; padding:30px; color:var(--text-muted);">
                Loading closer withdrawal requests...
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>

<?php endif; ?>

</div>

<!-- COMPANY ADMIN PAYOUT REQUEST MODAL -->
<div class="modal-overlay" id="modalAdminPayout">
  <div class="modal-card">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
      <div>
        <h3 class="modal-title">Request Company Profit / Admin Payout</h3>
        <div style="font-size:12px; color:var(--text-secondary);">Disburse company earnings to your verified account</div>
      </div>
      <button onclick="closeAdminPayoutModal()" style="background:none; border:none; color:var(--text-muted); font-size:22px; cursor:pointer;">&times;</button>
    </div>
    <form onsubmit="handleAdminPayoutSubmit(event)">
      <div class="form-group">
        <label class="form-label">Payout Amount (PKR) *</label>
        <input type="number" id="adminWdAmount" class="form-control" placeholder="e.g. 15000" min="1000" step="500" required>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Payout Method *</label>
          <select id="adminWdMethod" class="form-control" required>
            <option value="EasyPaisa">EasyPaisa</option>
            <option value="JazzCash">JazzCash</option>
            <option value="Meezan Bank" selected>Meezan Bank Limited</option>
            <option value="HBL">Habib Bank Limited (HBL)</option>
            <option value="Bank Alfalah">Bank Alfalah</option>
            <option value="SadaPay">SadaPay</option>
            <option value="NayaPay">NayaPay</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Beneficiary Account Title *</label>
          <input type="text" id="adminWdTitle" class="form-control" placeholder="e.g. <?php echo htmlspecialchars($activeCompany['owner_name']); ?>" required>
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">Account Number / IBAN / Wallet Phone *</label>
        <input type="text" id="adminWdNumber" class="form-control" placeholder="e.g. PK36MEZN... or 03001234567" required>
      </div>
      <div class="form-group">
        <label class="form-label">Note / Reference</label>
        <input type="text" id="adminWdNote" class="form-control" placeholder="e.g. Weekly company revenue payout">
      </div>
      <div style="display:flex; justify-content:flex-end; gap:10px; margin-top:20px;">
        <button type="button" class="btn btn-secondary" onclick="closeAdminPayoutModal()">Cancel</button>
        <button type="submit" class="btn btn-emerald">Submit Payout Request 🚀</button>
      </div>
    </form>
  </div>
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

<!-- MODAL: Approve Pending TM / TL Closer -->
<div id="modalApproveTM" class="modal-overlay" style="display: none;">
  <div class="modal-card" style="max-width: 490px;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
      <div>
        <div style="font-size:11px; font-weight:800; text-transform:uppercase; color:#10b981; letter-spacing:0.05em;">Manual Verification</div>
        <h3 class="modal-title" id="approveModalHeading" style="margin:0;">Approve Application</h3>
      </div>
      <button type="button" onclick="closeApproveTMModal()" style="background:none; border:none; color:var(--text-muted); font-size:24px; cursor:pointer;">&times;</button>
    </div>

    <div style="background:rgba(16,185,129,0.08); border:1px solid rgba(16,185,129,0.25); border-radius:10px; padding:12px 16px; margin-bottom:18px;">
      <div style="display:flex; align-items:center; justify-content:space-between;">
        <div style="font-size:14px; font-weight:800; color:#34d399;" id="approveTMName">Applicant Name</div>
        <span id="approveTMRoleBadge" style="font-size:11px; font-weight:700; padding:2px 8px; border-radius:6px; background:rgba(16,185,129,0.2); color:#34d399;">Sales Closer (TM)</span>
      </div>
      <div style="font-size:12px; color:var(--text-secondary); margin-top:2px;" id="approveTMUsername">@username</div>
    </div>

    <form onsubmit="submitApproveTM(event)">
      <input type="hidden" id="approveTMId">
      
      <!-- Attributed TL selection (Only when approving a Sales Closer TM) -->
      <div class="form-group" id="approveTMAttributedTLGroup">
        <label class="form-label">Sponsoring Team Leader (TL) for Bounty</label>
        <select id="approveTMAttributedTL" class="form-control" style="cursor:pointer; font-size:13px;">
          <option value="">-- No Sponsoring TL (Direct Applicant) --</option>
        </select>
        <div style="font-size:11px; color:var(--text-muted); margin-top:4px;">When approved, this Team Leader will be credited with <?php echo htmlspecialchars($activeCompany['name']); ?>'s commission bounty.</div>
      </div>

      <div class="form-group">
        <label class="form-label">Assign Daily Message Quota *</label>
        <div style="display:flex; gap:8px; align-items:center;">
          <input type="number" id="approveTMLimit" class="form-control" value="150" min="10" max="5000" required style="font-size:15px; font-weight:700;">
          <button type="button" class="btn btn-secondary" onclick="adjustApproveLimit(50)">+50</button>
          <button type="button" class="btn btn-secondary" onclick="adjustApproveLimit(100)">+100</button>
        </div>
        <div id="approveTMLimitNote" style="font-size:11px; color:var(--text-muted); margin-top:5px;">Quota of WhatsApp messages this employee can dispatch per day.</div>
      </div>

      <div id="approveTMError" style="display: none; padding: 10px; border-radius: 8px; background: rgba(244, 63, 94, 0.15); border: 1px solid rgba(244, 63, 94, 0.3); color: #fb7185; font-size: 12.5px; margin-bottom: 14px;"></div>

      <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px;">
        <button type="button" class="btn btn-secondary" onclick="closeApproveTMModal()">Cancel</button>
        <button type="submit" id="approveTMSubmitBtn" class="btn btn-emerald">Approve &amp; Activate 🚀</button>
      </div>
    </form>
  </div>
</div>

<script>
let currentEmployees = [];
let currentCompanyLeads = [];
let currentPendingTMs = [];
window.currentCompanyTLCommissionRate = <?php echo intval($activeCompany['tl_commission_per_tm'] ?? 500); ?>;

async function saveTLCommissionRate() {
  const rateInp = document.getElementById('inputTLCommissionRate');
  const rate = parseInt(rateInp?.value) || 500;
  try {
    const res = await fetch('api/companies.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        action: 'update_company_config',
        tl_commission_per_tm: rate
      })
    });
    const data = await res.json();
    if (data.success) {
      window.currentCompanyTLCommissionRate = rate;
      const thRate = document.getElementById('thTLLedgerRate');
      if (thRate) thRate.textContent = `Commission Accrued (@ Rs. ${rate.toLocaleString()})`;
      const ind = document.getElementById('tlCommRateSavedIndicator');
      if (ind) {
        ind.style.display = 'inline-block';
        setTimeout(() => { ind.style.display = 'none'; }, 2500);
      }
      alert('✅ TL Commission per TM saved: Rs. ' + rate.toLocaleString() + ' PKR');
      loadEmployees();
    } else {
      alert('⚠️ ' + (data.error || 'Failed to save TL commission rate.'));
    }
  } catch (err) {
    alert('Error saving TL commission rate: ' + err.message);
  }
}

function switchDashboardTab(tabName) {
  const tabs = ['closers', 'pending', 'leads', 'campaigns', 'commissions', 'products', 'withdrawals'];
  tabs.forEach((t) => {
    const pane = document.getElementById('pane' + t.charAt(0).toUpperCase() + t.slice(1));
    const btn = document.getElementById('tabBtn' + t.charAt(0).toUpperCase() + t.slice(1));
    if (pane) pane.classList.toggle('active', t === tabName);
    if (btn) btn.classList.toggle('active', t === tabName);
  });
  if (tabName === 'pending') loadPendingTMs();
  if (tabName === 'leads') loadCompanyLeads();
  if (tabName === 'campaigns') { loadCampaignSenders(); pollCampaignStatus(); }
  if (tabName === 'products') loadCompanyConfig();
  if (tabName === 'commissions') loadEmployees();
  if (tabName === 'withdrawals') loadCompanyWithdrawals();
}

function adjustApproveLimit(delta) {
  const inp = document.getElementById('approveTMLimit');
  inp.value = (parseInt(inp.value) || 150) + delta;
}

function openApproveTMModal(id) {
  const app = currentPendingTMs.find(a => a.id === id) || { id: id, name: 'Applicant', username: 'closer', role: 'member' };
  const isTL = (app.role === 'lead');

  document.getElementById('approveTMId').value = app.id;
  document.getElementById('approveTMName').textContent = app.name;
  document.getElementById('approveTMUsername').textContent = '@' + app.username;
  document.getElementById('approveTMLimit').value = isTL ? 500 : 150;
  document.getElementById('approveTMError').style.display = 'none';

  const headingEl = document.getElementById('approveModalHeading');
  const roleBadge = document.getElementById('approveTMRoleBadge');
  const tlGroup = document.getElementById('approveTMAttributedTLGroup');
  const submitBtn = document.getElementById('approveTMSubmitBtn');

  if (headingEl) headingEl.textContent = isTL ? 'Approve Team Leader (TL)' : 'Approve Sales Closer (TM)';
  if (roleBadge) {
    roleBadge.textContent = isTL ? '🚀 Team Leader (TL)' : '🎯 Sales Closer (TM)';
    roleBadge.style.background = isTL ? 'rgba(147, 51, 234, 0.2)' : 'rgba(16, 185, 129, 0.2)';
    roleBadge.style.color = isTL ? '#c084fc' : '#34d399';
  }
  if (submitBtn) {
    submitBtn.textContent = isTL ? 'Approve & Activate Team Leader 🚀' : 'Approve & Activate Closer 🚀';
  }

  // Populate TL dropdown if applicant is TM
  if (tlGroup) {
    if (isTL) {
      tlGroup.style.display = 'none';
    } else {
      tlGroup.style.display = 'block';
      const tlSelect = document.getElementById('approveTMAttributedTL');
      if (tlSelect) {
        tlSelect.innerHTML = '<option value="">-- No Sponsoring TL (Direct Applicant) --</option>';
        const tls = currentEmployees.filter(e => e.role === 'lead');
        tls.forEach(tl => {
          const opt = document.createElement('option');
          opt.value = tl.id;
          opt.textContent = `${tl.name} (@${tl.username}) - Accrued: Rs. ${(parseInt(tl.tl_commission_balance) || 0).toLocaleString()}`;
          if (app.joined_via_tl && (app.joined_via_tl === tl.id || app.joined_via_tl === tl.username || app.joined_via_tl === tl.phone)) {
            opt.selected = true;
          }
          tlSelect.appendChild(opt);
        });
      }
    }
  }

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
  btn.textContent = 'Activating…';
  errBox.style.display = 'none';

  const userId = document.getElementById('approveTMId').value;
  const limit = parseInt(document.getElementById('approveTMLimit').value) || 150;
  const attributedTL = document.getElementById('approveTMAttributedTL')?.value || '';

  try {
    const res = await fetch('api/auth.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        action: 'approve_tm',
        user_id: userId,
        message_limit: limit,
        attributed_tl: attributedTL
      })
    });
    const data = await res.json();
    if (data.success) {
      closeApproveTMModal();
      alert('🎉 ' + (data.message || 'Applicant approved and activated!'));
      loadPendingTMs();
      loadEmployees();
    } else {
      errBox.textContent = data.error || 'Failed to approve applicant.';
      errBox.style.display = 'block';
    }
  } catch (err) {
    errBox.textContent = 'Server communication error.';
    errBox.style.display = 'block';
  } finally {
    btn.disabled = false;
    btn.textContent = 'Approve & Activate 🚀';
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
        tbody.innerHTML = '<tr><td colspan="6" style="text-align:center; padding:32px; color:var(--text-muted);">✨ No pending applications right now. When users scan their WhatsApp QR and select your company, they will appear here.</td></tr>';
        return;
      }

      tbody.innerHTML = currentPendingTMs.map(app => {
        const pfp = app.pfp || 'https://images.unsplash.com/photo-1535713875002-d1d0cf377fde?auto=format&fit=crop&w=120&q=80';
        const isTL = (app.role === 'lead');
        return `
          <tr>
            <td>
              <div class="user-profile-cell">
                <img src="${pfp}" alt="${app.name}" style="width:38px; height:38px; border-radius:10px; object-fit:cover; border:1px solid rgba(255,255,255,0.1);">
                <div>
                  <div class="user-name">${app.name}</div>
                  <div style="margin-top:2px;">
                    ${isTL 
                      ? '<span style="background:rgba(147, 51, 234, 0.2); color:#c084fc; border:1px solid rgba(147, 51, 234, 0.4); font-size:10px; font-weight:700; padding:2px 6px; border-radius:4px;">🚀 Team Leader (TL)</span>'
                      : '<span style="background:rgba(16, 185, 129, 0.2); color:#34d399; border:1px solid rgba(16, 185, 129, 0.4); font-size:10px; font-weight:700; padding:2px 6px; border-radius:4px;">🎯 Sales Closer (TM)</span>'}
                  </div>
                  ${app.joined_via_tl ? `<div style="font-size:10.5px; color:#fbbf24; margin-top:2px;">Referred by TL: <strong>${app.joined_via_tl}</strong></div>` : ''}
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
              <div style="font-size:11px; color:var(--text-muted); text-transform:capitalize;">${app.gender || 'male'} ${isTL ? 'lead' : 'closer'}</div>
            </td>
            <td>
              <span style="font-size:11.5px; color:var(--text-secondary);">${app.applied_at || app.created_at || 'Just now'}</span>
            </td>
            <td>
              <div style="display:flex; gap:8px;">
                <button class="btn btn-emerald" style="padding:6px 12px; font-size:11.5px;" onclick="openApproveTMModal('${app.id}')">
                  ✅ Approve
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
  const rate = window.currentCompanyTLCommissionRate || 500;
  const thRate = document.getElementById('thTLLedgerRate');
  if (thRate) thRate.textContent = `Commission Accrued (@ Rs. ${rate.toLocaleString()})`;

  if (!tls.length) {
    tbody.innerHTML = '<tr><td colspan="6" style="text-align:center; padding:20px; color:var(--text-muted);">No Team Leads appointed yet for this company.</td></tr>';
    return;
  }

  tbody.innerHTML = tls.map(tl => {
    const recruited = employees.filter(m => m.joined_via_tl && (m.joined_via_tl === tl.id || m.joined_via_tl === tl.username || m.joined_via_tl === tl.phone)).length;
    const balance = parseInt(tl.tl_commission_balance);
    const commission = !isNaN(balance) ? balance : (recruited * rate);
    return `
      <tr>
        <td>
          <strong>${tl.name}</strong>
          <div style="font-size:11px; color:#10b981; font-family:'JetBrains Mono',monospace;">@${tl.username}</div>
          <span style="font-size:11px; color:var(--text-muted);">${tl.phone || 'No phone'}</span>
        </td>
        <td><strong style="color:#34d399; font-size:14px;">${recruited}</strong> active TMs</td>
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

// --- Multi-Device Round-Robin Campaign Dispatcher Engine ---
window.currentCampaignSenders = [];
window.campaignPollTimer = null;
window.isCampaignPollActive = false;

function insertCampTag(tag) {
  const textarea = document.getElementById('campMessageText');
  if (!textarea) return;
  const start = textarea.selectionStart || 0;
  const end = textarea.selectionEnd || 0;
  const text = textarea.value;
  textarea.value = text.substring(0, start) + tag + text.substring(end);
  textarea.focus();
  textarea.selectionStart = textarea.selectionEnd = start + tag.length;
}

async function loadCampaignSenders() {
  const container = document.getElementById('campaignSendersPoolContainer');
  const badge = document.getElementById('campaignPoolCountBadge');
  const preview = document.getElementById('campaignRotationFlowPreview');
  const mathInfo = document.getElementById('campaignCycleMathInfo');

  try {
    const res = await fetch('api/tenant_manager.php?action=get_campaign_senders&company_id=<?php echo urlencode($activeCompany['id']); ?>');
    const data = await res.json();
    if (data.success && Array.isArray(data.senders)) {
      window.currentCampaignSenders = data.senders;
      const senders = data.senders;

      if (badge) {
        badge.textContent = `${senders.length} WhatsApp ${senders.length === 1 ? 'Device' : 'Devices'} Connected`;
      }

      if (container) {
        if (!senders.length) {
          container.innerHTML = `<div style="color:var(--text-muted); padding:16px;">No connected WhatsApp devices found. Scan QR on landing page to link devices.</div>`;
        } else {
          container.innerHTML = senders.map((s, idx) => {
            const isTL = (s.roleType === 'lead');
            const isAdmin = (s.roleType === 'admin');
            const badgeBg = isTL ? 'linear-gradient(135deg, #10b981, #059669)' : (isAdmin ? 'linear-gradient(135deg, #8b5cf6, #6366f1)' : 'linear-gradient(135deg, #38bdf8, #0284c7)');
            const roleTag = isTL ? 'Team Leader (TL)' : (isAdmin ? 'Admin' : 'TM Closer');
            const roleColor = isTL ? '#34d399' : (isAdmin ? '#c084fc' : '#38bdf8');

            return `
              <div style="background: rgba(255,255,255,0.03); border: 1.5px solid var(--border-subtle); border-radius: 12px; padding: 14px; position: relative;">
                <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom: 8px;">
                  <span style="font-size: 11px; font-weight: 800; background: rgba(255,255,255,0.08); padding: 2px 8px; border-radius: 6px; font-family: monospace; color: #fff;">
                    Device #${s.index}
                  </span>
                  <span style="font-size: 10.5px; font-weight: 700; color: #10b981; display:flex; align-items:center; gap:4px;">
                    <span style="width:6px; height:6px; border-radius:50%; background:#10b981; display:inline-block;"></span> READY
                  </span>
                </div>
                <div style="display:flex; align-items:center; gap:10px;">
                  <div style="width:36px; height:36px; border-radius:50%; background:${badgeBg}; display:flex; align-items:center; justify-content:center; font-weight:800; color:#fff; font-size:14px; flex-shrink:0;">
                    ${(s.name || 'W').charAt(0).toUpperCase()}
                  </div>
                  <div style="min-width:0; flex:1;">
                    <div style="font-weight:700; font-size:13.5px; color:#fff; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                      ${escapeHtml(s.name)}
                    </div>
                    <div style="font-size:11px; font-weight:700; color:${roleColor}; margin-top:1px;">
                      ${roleTag}
                    </div>
                  </div>
                </div>
                <div style="margin-top: 10px; padding-top: 8px; border-top: 1px solid rgba(255,255,255,0.05); display:flex; justify-content:space-between; align-items:center; font-size:11.5px;">
                  <span style="font-family:monospace; color:var(--text-secondary);">${escapeHtml(s.display || s.phone)}</span>
                  <span style="color:var(--text-muted); font-size:11px;">${s.messages_sent || 0} sent</span>
                </div>
              </div>
            `;
          }).join('');
        }
      }

      // Render Visual Rotation Breadcrumbs
      if (preview) {
        if (!senders.length) {
          preview.innerHTML = `<span style="color:var(--text-muted);">No devices connected</span>`;
        } else {
          const items = senders.map(s => {
            const roleShort = s.roleType === 'lead' ? 'TL' : (s.roleType === 'admin' ? 'Admin' : 'TM');
            return `<span style="background: rgba(16, 185, 129, 0.12); color: #34d399; padding: 4px 8px; border-radius: 6px; border: 1px solid rgba(16, 185, 129, 0.25);">📱 #${s.index} ${escapeHtml(s.name.split(' ')[0])} (${roleShort})</span>`;
          });
          items.push(`<span style="color: #fbbf24; font-weight: 700; padding: 4px 8px;">🔁 Repeat Cycle (1 ➔ ${senders.length})</span>`);
          preview.innerHTML = items.join('<span style="color: var(--text-muted); font-size: 14px;"> ➔ </span>');
        }
      }

      if (mathInfo) {
        mathInfo.innerHTML = `Round-robin order: 1-by-1 per message across all ${senders.length} devices &bull; Repeats until audience finishes.`;
      }

      calculateCampaignCycles();
    }
  } catch (err) {
    console.error('Error loading campaign senders:', err);
  }
}

function calculateCampaignCycles() {
  const filter = document.getElementById('campTargetFilter')?.value || 'demo';
  const poolSize = Math.max(1, (window.currentCampaignSenders || []).length);
  let estContacts = 10;
  if (filter === 'new') {
    const uncontacted = (window.currentCompanyLeads || []).filter(l => l.leadState === 'fresh' || l.status === 'new');
    estContacts = Math.max(1, uncontacted.length);
  } else if (filter === 'ongoing') {
    const ongoing = (window.currentCompanyLeads || []).filter(l => l.leadState === 'ongoing');
    estContacts = Math.max(1, ongoing.length);
  } else if (filter === 'all') {
    estContacts = Math.max(1, (window.currentCompanyLeads || []).length);
  }

  const totalCycles = Math.ceil(estContacts / poolSize);
  const summaryEl = document.getElementById('calcSummaryText');
  const orderTag = document.getElementById('calcOrderTag');

  if (summaryEl) {
    summaryEl.innerHTML = `Target Audience: <strong>${estContacts} contacts</strong> &bull; Connected Devices: <strong>${poolSize} WhatsApps</strong> &bull; Total: <strong>${totalCycles} Full ${totalCycles === 1 ? 'Cycle' : 'Cycles'}</strong>`;
  }
  if (orderTag) {
    if (poolSize === 1) {
      orderTag.textContent = 'Single Device Mode (All dispatches via Device #1)';
    } else {
      orderTag.textContent = `Order: 1 ➔ ${poolSize} (Repeats every ${poolSize} messages)`;
    }
  }
}

// Launch Multi-Device Campaign
async function handleLaunchCampaign(e) {
  e.preventDefault();
  const btn = document.getElementById('btnLaunchCamp');
  btn.disabled = true;
  btn.textContent = '🚀 Dispatching Multi-Device Campaign…';

  let variation = 'text';
  if (document.getElementById('radioVarImage')?.checked) variation = 'text_image';
  if (document.getElementById('radioVarAudio')?.checked) variation = 'audio';

  const filter = document.getElementById('campTargetFilter')?.value || 'demo';
  const delaySec = parseInt(document.getElementById('campDelay')?.value, 10) || 3;
  const message = document.getElementById('campMessageText')?.value.trim();
  const imageUrl = document.getElementById('campImageUrl')?.value.trim();
  const audioUrl = document.getElementById('campAudioUrl')?.value.trim();

  try {
    const res = await fetch('api/direct_sender.php?action=start_campaign', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        action: 'start_campaign',
        company_id: '<?php echo $activeCompany['id']; ?>',
        company_name: '<?php echo htmlspecialchars($activeCompany['name']); ?>',
        variation: variation,
        message: message,
        image_url: imageUrl,
        audio_url: audioUrl,
        filter: filter,
        delay: delaySec,
        senders: window.currentCampaignSenders || []
      })
    });
    const data = await res.json();
    if (data.success) {
      // Reveal monitor box immediately
      const monitorBox = document.getElementById('campaignLiveMonitorBox');
      if (monitorBox) {
        monitorBox.style.display = 'block';
        monitorBox.scrollIntoView({ behavior: 'smooth', block: 'start' });
      }
      startCampaignPolling();
    } else {
      alert('⚠️ ' + (data.message || 'Campaign launch notice received.'));
    }
  } catch (err) {
    alert('Network error launching campaign: ' + err.message);
  } finally {
    btn.disabled = false;
    btn.innerHTML = '<span>🚀</span> Dispatch Multi-Device Campaign (Round-Robin)';
  }
}

function startCampaignPolling() {
  if (window.campaignPollTimer) clearInterval(window.campaignPollTimer);
  window.isCampaignPollActive = true;
  pollCampaignStatus();
  window.campaignPollTimer = setInterval(pollCampaignStatus, 1800);
}

async function pollCampaignStatus() {
  try {
    const res = await fetch('api/direct_sender.php?action=get_campaign_status');
    const data = await res.json();
    if (data && data.success) {
      updateCampaignMonitorUI(data);
      if (data.status === 'completed' || data.status === 'stopped') {
        if (window.campaignPollTimer) {
          clearInterval(window.campaignPollTimer);
          window.campaignPollTimer = null;
        }
      }
    }
  } catch (err) {
    console.warn('Campaign status polling error:', err);
  }
}

function updateCampaignMonitorUI(data) {
  const monitorBox = document.getElementById('campaignLiveMonitorBox');
  if (!monitorBox) return;

  if (['running', 'paused', 'completed'].includes(data.status)) {
    monitorBox.style.display = 'block';
  }

  // Status Badge
  const statusBadge = document.getElementById('liveCampStatusBadge');
  const btnPause = document.getElementById('btnCampPause');
  const btnResume = document.getElementById('btnCampResume');

  if (statusBadge) {
    statusBadge.textContent = (data.status || 'IDLE').toUpperCase();
    if (data.status === 'running') {
      statusBadge.style.background = '#10b981';
      statusBadge.style.color = '#050b09';
      if (btnPause) btnPause.style.display = 'inline-block';
      if (btnResume) btnResume.style.display = 'none';
    } else if (data.status === 'paused') {
      statusBadge.style.background = '#fbbf24';
      statusBadge.style.color = '#050b09';
      if (btnPause) btnPause.style.display = 'none';
      if (btnResume) btnResume.style.display = 'inline-block';
    } else if (data.status === 'completed') {
      statusBadge.style.background = '#38bdf8';
      statusBadge.style.color = '#050b09';
      if (btnPause) btnPause.style.display = 'none';
      if (btnResume) btnResume.style.display = 'none';
    }
  }

  // Metrics
  const cycleVal = document.getElementById('liveCampCycleVal');
  const cycleSub = document.getElementById('liveCampCycleSub');
  if (cycleVal) cycleVal.textContent = `Cycle ${data.currentCycle || 1} / ${data.totalCycles || 1}`;
  if (cycleSub) cycleSub.textContent = `Order: ${data.poolSize || 1}-device round-robin`;

  const activeSenderVal = document.getElementById('liveCampActiveSenderVal');
  const activeSenderRole = document.getElementById('liveCampActiveSenderRole');
  if (activeSenderVal) {
    activeSenderVal.textContent = data.activeSender ? data.activeSender.name : `Device ${(data.activeSenderIndex || 0) + 1}`;
  }
  if (activeSenderRole) {
    activeSenderRole.textContent = data.activeSender ? `${data.activeSender.role} · ${data.activeSender.display || data.activeSender.phone}` : 'WhatsApp Device';
  }

  const ratioEl = document.getElementById('liveCampSentRatio');
  if (ratioEl) ratioEl.textContent = `${data.totalSent || 0} / ${data.totalContacts || 0}`;

  const failedEl = document.getElementById('liveCampFailedCount');
  if (failedEl) failedEl.textContent = `${data.totalFailed || 0} failed`;

  const countdownEl = document.getElementById('liveCampCountdownVal');
  if (countdownEl) {
    countdownEl.textContent = data.status === 'running' ? `Next in: ${data.remainingSeconds || 0}s` : (data.status === 'paused' ? 'Paused' : 'Done');
  }

  // Progress Bar
  const total = Math.max(1, data.totalContacts || 1);
  const current = data.currentIndex || 0;
  const pct = data.status === 'completed' ? 100 : Math.min(100, Math.round((current / total) * 100));
  const pctEl = document.getElementById('liveCampPct');
  const barEl = document.getElementById('liveCampProgressBar');
  if (pctEl) pctEl.textContent = `${pct}%`;
  if (barEl) barEl.style.width = `${pct}%`;

  // Terminal Logs
  const termEl = document.getElementById('liveCampTerminal');
  if (termEl && Array.isArray(data.logs) && data.logs.length > 0) {
    termEl.innerHTML = data.logs.map(log => {
      let color = '#94a3b8';
      if (log.type === 'success') color = '#34d399';
      else if (log.type === 'error') color = '#f87171';
      else if (log.type === 'warn') color = '#fbbf24';
      else if (log.message && log.message.includes('Cycle')) color = '#38bdf8';

      return `<div style="margin-bottom: 3px; color: ${color};"><span style="color: #64748b; font-size: 11px;">[${log.time}]</span> ${escapeHtml(log.message)}</div>`;
    }).join('');
    termEl.scrollTop = termEl.scrollHeight;
  }
}

async function pauseLiveCampaign() {
  try {
    const res = await fetch('api/direct_sender.php?action=pause_campaign', { method: 'POST' });
    const data = await res.json();
    if (data.success) pollCampaignStatus();
  } catch (_) {}
}

async function resumeLiveCampaign() {
  try {
    const res = await fetch('api/direct_sender.php?action=resume_campaign', { method: 'POST' });
    const data = await res.json();
    if (data.success) {
      startCampaignPolling();
    }
  } catch (_) {}
}

async function stopLiveCampaign() {
  if (!confirm('Are you sure you want to halt this campaign?')) return;
  try {
    const res = await fetch('api/direct_sender.php?action=stop_campaign', { method: 'POST' });
    const data = await res.json();
    if (data.success) pollCampaignStatus();
  } catch (_) {}
}

// Products & Pitch Config
async function loadCompanyConfig() {
  try {
    const res = await fetch('api/companies.php?action=get_company_config');
    const data = await res.json();
    if (data.success && data.config) {
      if (data.config.default_pitch) document.getElementById('cfgDefaultPitch').value = data.config.default_pitch;
      if (data.config.tl_commission_per_tm !== undefined) {
        window.currentCompanyTLCommissionRate = parseInt(data.config.tl_commission_per_tm) || 500;
        const rateInp = document.getElementById('inputTLCommissionRate');
        if (rateInp) rateInp.value = window.currentCompanyTLCommissionRate;
        const thRate = document.getElementById('thTLLedgerRate');
        if (thRate) thRate.textContent = `Commission Accrued (@ Rs. ${window.currentCompanyTLCommissionRate.toLocaleString()})`;
      }
      window.companyProducts = Array.isArray(data.config.products) && data.config.products.length > 0 
        ? data.config.products 
        : [
            { id: 'p_1', name: 'Starter Package', amount: 2500, commission: 1000, badge: '⚡ Starter' },
            { id: 'p_2', name: 'Enterprise Package', amount: 7500, commission: 3000, badge: '💎 Enterprise' }
          ];
      renderProductsList();
    }
  } catch (err) {
    console.error('Error loading config:', err);
  }
}

window.companyProducts = [];

function renderProductsList() {
  const container = document.getElementById('productsListContainer');
  if (!container) return;

  if (!window.companyProducts.length) {
    container.innerHTML = `
      <div style="padding: 24px; text-align: center; color: var(--text-muted); background: rgba(255,255,255,0.02); border: 1px dashed var(--border-subtle); border-radius: 12px;">
        No products defined yet. Click "➕ Add Product" below to configure products for your sales closers.
      </div>
    `;
    return;
  }

  container.innerHTML = window.companyProducts.map((p, idx) => `
    <div class="product-config-row" style="display:grid; grid-template-columns: 2fr 1.2fr 1.2fr 1fr auto; gap:12px; align-items:center; background:rgba(255,255,255,0.03); border:1.5px solid var(--border-subtle); border-radius:12px; padding:14px;">
      <div>
        <label style="font-size:11px; font-weight:700; color:var(--text-muted); display:block; margin-bottom:4px;">Product / Plan Name *</label>
        <input type="text" id="prodName_${idx}" class="form-control" style="font-weight:700;" value="${escapeHtml(p.name || '')}" placeholder="e.g. Starter Package">
      </div>
      <div>
        <label style="font-size:11px; font-weight:700; color:#34d399; display:block; margin-bottom:4px;">Price (PKR) *</label>
        <div style="position:relative;">
          <span style="position:absolute; left:10px; top:10px; font-size:11px; font-weight:700; color:#10b981;">Rs.</span>
          <input type="number" id="prodPrice_${idx}" class="form-control" style="padding-left:36px; font-weight:800; color:#34d399;" value="${p.amount || 0}" min="0" step="100">
        </div>
      </div>
      <div>
        <label style="font-size:11px; font-weight:700; color:#38bdf8; display:block; margin-bottom:4px;">Closer Commission *</label>
        <div style="position:relative;">
          <span style="position:absolute; left:10px; top:10px; font-size:11px; font-weight:700; color:#38bdf8;">Rs.</span>
          <input type="number" id="prodComm_${idx}" class="form-control" style="padding-left:36px; font-weight:800; color:#38bdf8;" value="${p.commission || 0}" min="0" step="50">
        </div>
      </div>
      <div>
        <label style="font-size:11px; font-weight:700; color:var(--text-muted); display:block; margin-bottom:4px;">Badge / Icon</label>
        <input type="text" id="prodBadge_${idx}" class="form-control" value="${escapeHtml(p.badge || '📦')}" placeholder="e.g. ⚡ Starter">
      </div>
      <div style="padding-top:18px;">
        <button type="button" class="btn btn-danger" onclick="removeProductRow(${idx})" style="padding:8px 12px; font-size:13px;" title="Delete Product">🗑️</button>
      </div>
    </div>
  `).join('');
}

function addProductRow() {
  window.companyProducts.push({
    id: 'prod_' + Date.now() + '_' + Math.random().toString(36).slice(2, 6),
    name: 'New Product Package',
    amount: 5000,
    commission: 1500,
    badge: '⭐ Package'
  });
  renderProductsList();
}

function removeProductRow(idx) {
  if (window.companyProducts.length <= 1) {
    if (!confirm('This is the only product. Are you sure you want to remove it?')) return;
  }
  window.companyProducts.splice(idx, 1);
  renderProductsList();
}

async function saveProductConfig() {
  const defaultPitch = document.getElementById('cfgDefaultPitch')?.value.trim() || '';
  const updatedProducts = [];

  for (let i = 0; i < window.companyProducts.length; i++) {
    const nameInp = document.getElementById('prodName_' + i);
    const priceInp = document.getElementById('prodPrice_' + i);
    const commInp = document.getElementById('prodComm_' + i);
    const badgeInp = document.getElementById('prodBadge_' + i);

    if (nameInp && priceInp) {
      const name = nameInp.value.trim();
      const amount = parseInt(priceInp.value, 10) || 0;
      const commission = parseInt(commInp ? commInp.value : 0, 10) || 0;
      const badge = badgeInp ? badgeInp.value.trim() : '📦';

      if (name && amount > 0) {
        updatedProducts.push({
          id: window.companyProducts[i].id || ('p_' + i),
          name: name,
          amount: amount,
          commission: commission,
          badge: badge
        });
      }
    }
  }

  if (!updatedProducts.length) {
    alert('Please define at least one product with a valid name and price.');
    return;
  }

  try {
    const res = await fetch('api/companies.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        action: 'update_company_config',
        company_id: '<?php echo $activeCompany['id']; ?>',
        default_pitch: defaultPitch,
        products: updatedProducts
      })
    });
    const data = await res.json();
    if (data.success) {
      window.companyProducts = updatedProducts;
      renderProductsList();
      alert('✅ Company products & pricing saved successfully! Sales closers (TMs/TLs) will now see these options when closing leads.');
    } else {
      alert('⚠️ ' + (data.error || 'Failed to save configuration.'));
    }
  } catch (err) {
    alert('Network error saving configuration: ' + err.message);
  }
}

async function handleAdminLogin(e) {
  e.preventDefault();
  const btn = document.getElementById('adminLoginBtn');
  const errBox = document.getElementById('loginError');
  btn.disabled = true;
  btn.textContent = 'Authenticating…';
  if (errBox) errBox.style.display = 'none';

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
    if (data.success && (data.user.role === 'admin' || data.user.role === 'superadmin')) {
      const compId = data.user.company_id || 'COMP-001';
      window.location.href = '/cc?portal=1&company_id=' + encodeURIComponent(compId);
    } else {
      if (errBox) {
        errBox.textContent = data.error || 'Access Denied: Company Administrator privileges required.';
        errBox.style.display = 'block';
      } else {
        alert(data.error || 'Access Denied: Company Administrator privileges required.');
      }
    }
  } catch (err) {
    if (errBox) {
      errBox.textContent = 'Server communication error.';
      errBox.style.display = 'block';
    } else {
      alert('Server communication error.');
    }
  } finally {
    btn.disabled = false;
    btn.textContent = 'Access Company Control 🏢';
  }
}

// ==========================================
// 7. CLOSER WITHDRAWALS & PAYOUTS MANAGEMENT
// ==========================================
let currentCompanyWithdrawals = [];

async function loadCompanyWithdrawals() {
  try {
    const res = await fetch(`api/withdrawals.php?action=get_withdrawals&company_id=<?php echo htmlspecialchars($activeCompany['id']); ?>`);
    const data = await res.json();
    if (!data.success) return;
    currentCompanyWithdrawals = data.withdrawals || [];
    renderCompanyWithdrawals(currentCompanyWithdrawals);
  } catch (err) {
    console.error('Error loading withdrawals:', err);
  }
}

function renderCompanyWithdrawals(wds) {
  const pending = wds.filter(w => w.status === 'pending');
  const settled = wds.filter(w => w.status === 'approved');

  const pendingAmt = pending.reduce((sum, w) => sum + (Number(w.amount) || 0), 0);
  const settledAmt = settled.reduce((sum, w) => sum + (Number(w.amount) || 0), 0);

  const pendingEl = document.getElementById('compPendingWdAmount');
  const pendingSub = document.getElementById('compPendingWdSub');
  const settledEl = document.getElementById('compSettledWdAmount');
  const settledSub = document.getElementById('compSettledWdSub');
  const closerCountEl = document.getElementById('compCloserCount');
  const badgeEl = document.getElementById('badgePendingWdCount');

  if (pendingEl) pendingEl.textContent = 'PKR ' + pendingAmt.toLocaleString();
  if (pendingSub) pendingSub.textContent = `${pending.length} request${pending.length === 1 ? '' : 's'} awaiting verification`;
  if (settledEl) settledEl.textContent = 'PKR ' + settledAmt.toLocaleString();
  if (settledSub) settledSub.textContent = `${settled.length} disbursement${settled.length === 1 ? '' : 's'} completed`;
  if (closerCountEl) closerCountEl.textContent = `${currentEmployees.filter(e => e.role === 'member').length} Closers`;

  if (badgeEl) {
    if (pending.length > 0) {
      badgeEl.textContent = pending.length;
      badgeEl.style.display = 'inline-block';
    } else {
      badgeEl.style.display = 'none';
    }
  }

  const tbody = document.getElementById('tableCompanyWithdrawalsBody');
  if (!tbody) return;

  if (wds.length === 0) {
    tbody.innerHTML = `<tr><td colspan="7" style="text-align:center; padding:30px; color:var(--text-muted);">No withdrawal requests lodged yet for this company.</td></tr>`;
    return;
  }

  const methodIcons = {
    'Digital Wallet': '📱',
    'EasyPaisa': '📱',
    'JazzCash': '📱',
    'SadaPay': '📱',
    'NayaPay': '📱',
    'UPaisa': '📱',
    'Zindigi': '📱',
    'Bank Account': '🏦',
    'Bank Transfer': '🏦',
    'Meezan Bank': '🏦',
    'HBL': '🏦',
    'Bank Alfalah': '🏦'
  };

  tbody.innerHTML = wds.map(w => {
    const isPending = (w.status === 'pending');
    const isApproved = (w.status === 'approved');
    const isRejected = (w.status === 'rejected');
    const icon = methodIcons[w.institution] || methodIcons[w.method] || '📱';
    const dateStr = w.created_at ? new Date(w.created_at).toLocaleDateString('en-PK', { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' }) : 'Recently';

    const roleBadge = (w.userRole === 'lead')
      ? `<span style="font-size:10px; padding:1px 6px; border-radius:4px; background:rgba(168, 85, 247, 0.15); color:#c084fc; font-weight:700;">TL</span>`
      : (w.userRole === 'admin')
        ? `<span style="font-size:10px; padding:1px 6px; border-radius:4px; background:rgba(16, 185, 129, 0.15); color:#34d399; font-weight:700;">ADMIN</span>`
        : `<span style="font-size:10px; padding:1px 6px; border-radius:4px; background:rgba(59, 130, 246, 0.15); color:#60a5fa; font-weight:700;">TM</span>`;

    const statusBadge = isApproved
      ? `<span class="badge-status status-active">🟢 Paid & Settled</span>`
      : isPending
        ? `<span class="badge-status" style="background:rgba(245, 158, 11, 0.15); color:#fbbf24; border:1px solid rgba(245, 158, 11, 0.3);">🟡 Pending Audit</span>`
        : `<span class="badge-status status-suspended">🔴 Rejected</span>`;

    const actions = isPending ? `
      <div style="display:flex; gap:6px;">
        <button class="btn btn-emerald" style="padding:4px 8px; font-size:11px;" onclick="settleCompanyWithdrawal('${escapeHtml(w.id)}', '${escapeHtml(w.userName)}', ${w.amount})">
          ✓ Settle &amp; Mark Paid
        </button>
        <button class="btn btn-danger" style="padding:4px 8px; font-size:11px;" onclick="rejectCompanyWithdrawal('${escapeHtml(w.id)}', '${escapeHtml(w.userName)}')">
          ✕ Reject
        </button>
      </div>
    ` : (w.txRef ? `<span style="font-size:11px; color:var(--text-muted); font-family:monospace;">Tx: ${escapeHtml(w.txRef)}</span>` : `<span style="font-size:11px; color:var(--text-muted);">-</span>`);

    return `
      <tr>
        <td>
          <div style="display:flex; align-items:center; gap:8px;">
            <div style="font-weight:700; color:var(--text-primary); font-size:13px;">${escapeHtml(w.userName || 'Closer')}</div>
            ${roleBadge}
          </div>
          <div style="font-size:11px; color:var(--text-muted);">${escapeHtml(w.userId || '')}</div>
        </td>
        <td style="font-weight:800; font-size:14px; color:#34d399;">
          PKR ${Number(w.amount).toLocaleString()}
        </td>
        <td>
          <div style="font-size:12.5px; font-weight:600;">${icon} ${escapeHtml(w.institution || w.method || 'Digital Wallet')}</div>
          ${w.note ? `<div style="font-size:11px; color:var(--text-secondary); font-style:italic;">"${escapeHtml(w.note)}"</div>` : ''}
        </td>
        <td>
          <div style="font-weight:700; font-size:12.5px; color:var(--text-primary);">${escapeHtml(w.accountTitle || '-')}</div>
          <div style="font-family:monospace; font-size:12px; color:var(--text-muted);">${escapeHtml(w.accountNumber || '-')}</div>
        </td>
        <td style="font-size:12px; color:var(--text-secondary);">${escapeHtml(dateStr)}</td>
        <td>${statusBadge}</td>
        <td>${actions}</td>
      </tr>
    `;
  }).join('');
}

async function settleCompanyWithdrawal(wdId, closerName, amount) {
  const txRef = prompt(`Disbursing PKR ${Number(amount).toLocaleString()} to ${closerName}.\nEnter Bank / Digital Wallet Transaction Reference (e.g. EP-992104 or Meezan-TX-8812):`, 'TX-' + Math.floor(100000 + Math.random() * 900000));
  if (txRef === null) return;

  try {
    const res = await fetch('api/withdrawals.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'update_status', id: wdId, status: 'approved', txRef: txRef.trim() })
    });
    const d = await res.json();
    if (d.success) {
      alert(`✅ Payout of PKR ${Number(amount).toLocaleString()} marked as settled! Notification sent.`);
      loadCompanyWithdrawals();
    } else {
      alert('⚠️ ' + (d.error || 'Failed to update withdrawal status.'));
    }
  } catch (err) {
    alert('Network error updating withdrawal.');
  }
}

async function rejectCompanyWithdrawal(wdId, closerName) {
  if (!confirm(`Are you sure you want to reject the payout request for ${closerName}? Their available balance will be restored.`)) return;

  try {
    const res = await fetch('api/withdrawals.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'update_status', id: wdId, status: 'rejected' })
    });
    const d = await res.json();
    if (d.success) {
      alert(`Payout request rejected.`);
      loadCompanyWithdrawals();
    } else {
      alert('⚠️ ' + (d.error || 'Failed to reject withdrawal.'));
    }
  } catch (err) {
    alert('Network error updating withdrawal.');
  }
}

function openAdminPayoutModal() {
  document.getElementById('modalAdminPayout').style.display = 'flex';
}

function closeAdminPayoutModal() {
  document.getElementById('modalAdminPayout').style.display = 'none';
}

async function handleAdminPayoutSubmit(e) {
  e.preventDefault();
  const amt = Number(document.getElementById('adminWdAmount').value);
  const method = document.getElementById('adminWdMethod').value;
  const title = document.getElementById('adminWdTitle').value.trim();
  const num = document.getElementById('adminWdNumber').value.trim();
  const note = document.getElementById('adminWdNote').value.trim();

  if (!amt || amt <= 0 || !title || !num) {
    alert('Please fill all required payout fields.');
    return;
  }

  try {
    const res = await fetch('api/withdrawals.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        action: 'request_withdrawal',
        userId: '<?php echo htmlspecialchars($activeCompany['id']); ?>-ADMIN',
        userName: '<?php echo htmlspecialchars($activeCompany['owner_name']); ?> (Admin)',
        userRole: 'admin',
        company_id: '<?php echo htmlspecialchars($activeCompany['id']); ?>',
        company_name: '<?php echo htmlspecialchars($activeCompany['name']); ?>',
        amount: amt,
        method: method,
        institution: method,
        accountTitle: title,
        accountNumber: num,
        note: note
      })
    });
    const d = await res.json();
    if (d.success) {
      alert(`✅ Payout request of PKR ${amt.toLocaleString()} submitted!`);
      closeAdminPayoutModal();
      loadCompanyWithdrawals();
    } else {
      alert('⚠️ ' + (d.error || 'Failed to submit payout.'));
    }
  } catch (err) {
    alert('Network error submitting payout.');
  }
}

// Initial boot for authenticated company admin
<?php if ($isAdmin): ?>
loadEmployees();
loadPendingTMs();
loadCompanyLeads();
loadCompanyConfig();
loadCompanyWithdrawals();
loadCampaignSenders();
pollCampaignStatus();
setInterval(loadPendingTMs, 8000);
setInterval(loadCompanyWithdrawals, 8000);
<?php endif; ?>

function toggleTheme() {
  const isLight = document.documentElement.classList.toggle('theme-light');
  try { localStorage.setItem('nunno_theme', isLight ? 'light' : 'dark'); } catch(_) {}
}
</script>
</body>
</html>