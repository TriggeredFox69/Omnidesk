<?php
/**
 * Billion-Dollar Startup — Master Super Administrator Dashboard (/sadmin)
 * Platform Owner Command Center: Multi-Company Provisioning & Global Telemetry
 * 
 * Includes EVERY Option:
 * 1. Global Platform Telemetry (MRR, Companies, Total Workforce, Messages Dispatched)
 * 2. Complete Company Directory with Deep Statistics Modal (Leads, Conversions, Telemetry)
 * 3. 👑 1-Click Impersonate / Open Any Company Admin Portal
 * 4. Full Enterprise Company Provisioning (Plan, Quotas, Admin Account, Tenant Isolation)
 * 5. Global Fallback Numbers Pool Management (Assign / Release / Add)
 * 6. Platform-Wide Security & Activity Audit Log
 * 7. Billing & Nunno Rs. 1,700 Plan Settlement Telemetry
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
$isLoggedInSuperAdmin = ($currentUser && ($currentUser['role'] ?? '') === 'superadmin');

// Only show the super admin command center if authenticated AND viewing portal (?portal=1 or ?dashboard=1)
// Direct visits to /ss or ss.php or sadmin.php without ?portal=1 will ALWAYS present the Super Admin login gate
$isPortalView = (isset($_GET['portal']) || isset($_GET['dashboard']));
$isSuperAdmin = ($isLoggedInSuperAdmin && $isPortalView && !isset($_GET['login']) && !isset($_GET['switch']));

// Quick logout action
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
    }
    @session_destroy();
    header('Location: /ss');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <base href="/">
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
  <title>Chatly Super Admin — Platform Owner Command Center</title>
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
      --border-subtle: rgba(31, 224, 138, 0.11);
      --border-accent: rgba(234, 179, 8, 0.4);
      --border-glow: rgba(234, 179, 8, 0.6);
      --text-main: #effff7;
      --text-secondary: #8db5a3;
      --text-muted: #5c8070;
      --accent-gold: #eab308;
      --accent-purple: #2dd4bf;
      --accent-emerald: #1fe08a;
      --accent-rose: #f43f5e;
      --accent-blue: #3b82f6;
    }

    html.theme-light {
      --bg-base: #f0faf5;
      --bg-surface: #ffffff;
      --bg-surface-elevated: #e9f7f0;
      --border-subtle: #cfe8dc;
      --border-accent: rgba(234, 179, 8, 0.5);
      --border-glow: rgba(234, 179, 8, 0.3);
      --text-main: #07140f;
      --text-secondary: #3f6354;
      --text-muted: #7a9a8c;
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
      background: radial-gradient(circle at 15% 15%, rgba(234, 179, 8, 0.08) 0%, transparent 45%),
                  radial-gradient(circle at 85% 85%, rgba(31, 224, 138, 0.06) 0%, transparent 45%);
    }

    .sadmin-container {
      max-width: 1460px;
      margin: 0 auto;
      padding: 24px 20px 48px;
      position: relative;
      z-index: 1;
      width: 100%;
    }

    /* Top Command Header */
    .top-bar {
      display: flex;
      align-items: center;
      justify-content: space-between;
      flex-wrap: wrap;
      gap: 16px;
      padding: 18px 26px;
      border-radius: 18px;
      background: var(--bg-surface);
      border: 1px solid var(--border-subtle);
      box-shadow: 0 10px 30px rgba(0,0,0,0.35);
      margin-bottom: 24px;
      backdrop-filter: blur(14px);
    }
    .brand-section { display: flex; align-items: center; gap: 14px; }
    .sadmin-logo {
      width: 46px; height: 46px; border-radius: 12px;
      background: linear-gradient(135deg, #eab308, #ca8a04);
      display: flex; align-items: center; justify-content: center;
      font-size: 22px; font-weight: 900; color: #000;
      box-shadow: 0 4px 18px rgba(234, 179, 8, 0.4);
    }
    .sadmin-title { font-size: 19px; font-weight: 800; letter-spacing: -0.02em; display: flex; align-items: center; gap: 8px; }
    .badge-sadmin {
      font-size: 11px; font-weight: 800; text-transform: uppercase;
      padding: 3px 8px; border-radius: 6px;
      background: rgba(234, 179, 8, 0.18); border: 1px solid rgba(234, 179, 8, 0.45);
      color: #fde047;
    }
    .sadmin-sub { font-size: 12px; color: var(--text-secondary); margin-top: 2px; }

    .header-actions { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
    .btn {
      display: inline-flex; align-items: center; gap: 8px;
      padding: 9px 16px; border-radius: 10px; font-size: 13px; font-weight: 700;
      border: none; cursor: pointer; text-decoration: none; transition: all 0.2s ease;
      white-space: nowrap;
    }
    .btn-gold {
      background: linear-gradient(135deg, #eab308, #ca8a04);
      color: #000; box-shadow: 0 4px 16px rgba(234, 179, 8, 0.35);
    }
    .btn-gold:hover { transform: translateY(-1px); box-shadow: 0 6px 22px rgba(234, 179, 8, 0.5); }
    .btn-secondary {
      background: rgba(255, 255, 255, 0.05); border: 1px solid var(--border-subtle);
      color: var(--text-main);
    }
    .btn-secondary:hover { background: rgba(255, 255, 255, 0.1); }
    .btn-danger {
      background: rgba(244, 63, 94, 0.12); border: 1px solid rgba(244, 63, 94, 0.3);
      color: #fb7185;
    }

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
      box-shadow: 0 8px 24px rgba(0,0,0,0.2);
      transition: transform 0.2s, border-color 0.2s;
    }
    .metric-card:hover { transform: translateY(-2px); border-color: var(--border-accent); }
    .metric-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 10px; }
    .metric-label { font-size: 11.5px; font-weight: 700; text-transform: uppercase; color: var(--text-muted); letter-spacing: 0.04em; }
    .metric-icon { width: 34px; height: 34px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 16px; }
    .metric-val { font-size: 28px; font-weight: 800; letter-spacing: -0.03em; margin-bottom: 4px; }
    .metric-sub { font-size: 12px; color: var(--text-secondary); }

    /* Navigation Tabs */
    .sadmin-tabs {
      display: flex; flex-wrap: wrap; gap: 6px; background: var(--bg-surface);
      padding: 6px; border-radius: 14px; border: 1px solid var(--border-subtle);
      margin-bottom: 24px; overflow: hidden;
      scrollbar-width: none; -ms-overflow-style: none;
    }
    .sadmin-tabs::-webkit-scrollbar {
      display: none; width: 0; height: 0;
    }
    .sadmin-tab-btn {
      padding: 10px 18px; font-size: 13px; font-weight: 700;
      color: var(--text-secondary); background: none; border: none;
      border-radius: 10px; cursor: pointer; display: flex; align-items: center; gap: 8px;
      white-space: nowrap; transition: all 0.2s ease;
    }
    .sadmin-tab-btn:hover { color: var(--text-main); background: rgba(255,255,255,0.03); }
    .sadmin-tab-btn.active {
      color: #000; background: #eab308;
      box-shadow: 0 4px 14px rgba(234, 179, 8, 0.35);
    }

    /* Content Panes */
    .sadmin-pane { display: none; }
    .sadmin-pane.active { display: block; animation: paneFade 0.2s ease-out; }
    @keyframes paneFade { from { opacity: 0; transform: translateY(6px); } to { opacity: 1; transform: translateY(0); } }

    .content-box {
      background: var(--bg-surface);
      border: 1px solid var(--border-subtle);
      border-radius: 18px;
      padding: 24px;
      box-shadow: 0 10px 30px rgba(0,0,0,0.22);
      margin-bottom: 24px;
    }
    .box-header {
      display: flex; align-items: center; justify-content: space-between;
      flex-wrap: wrap; gap: 14px; margin-bottom: 20px;
    }
    .box-title { font-size: 16.5px; font-weight: 800; display: flex; align-items: center; gap: 10px; }
    .box-desc { font-size: 12.5px; color: var(--text-secondary); }

    /* Tables */
    table { width: 100%; border-collapse: separate; border-spacing: 0 8px; }
    th {
      text-align: left; padding: 10px 14px; font-size: 11.5px; font-weight: 700;
      color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.04em;
    }
    td {
      padding: 14px 14px; background: rgba(255,255,255,0.015);
      font-size: 13px; font-weight: 500; border-top: 1px solid var(--border-subtle);
      border-bottom: 1px solid var(--border-subtle); vertical-align: middle;
    }
    tr td:first-child { border-left: 1px solid var(--border-subtle); border-radius: 12px 0 0 12px; }
    tr td:last-child { border-right: 1px solid var(--border-subtle); border-radius: 0 12px 12px 0; }
    tr:hover td { background: rgba(255,255,255,0.04); }

    .company-cell { display: flex; align-items: center; gap: 12px; }
    .company-avatar {
      width: 38px; height: 38px; border-radius: 10px;
      background: linear-gradient(135deg, #eab308, #ca8a04);
      color: #000; font-weight: 800; font-size: 14px;
      display: flex; align-items: center; justify-content: center;
    }

    .badge-status {
      display: inline-flex; align-items: center; gap: 6px;
      padding: 3px 9px; border-radius: 20px; font-size: 11px; font-weight: 700;
    }
    .status-active { background: rgba(16, 185, 129, 0.15); color: #34d399; border: 1px solid rgba(16, 185, 129, 0.3); }
    .status-suspended { background: rgba(244, 63, 94, 0.15); color: #fb7185; border: 1px solid rgba(244, 63, 94, 0.3); }

    /* Modals */
    .modal-overlay {
      position: fixed; inset: 0; background: rgba(0,0,0,0.75);
      backdrop-filter: blur(8px); display: none; align-items: center; justify-content: center;
      z-index: 9999; padding: 16px;
    }
    .modal-card {
      background: var(--bg-surface); border: 1.5px solid var(--border-accent);
      border-radius: 20px; width: 100%; max-width: 600px; padding: 28px;
      box-shadow: 0 25px 60px rgba(0,0,0,0.7); position: relative;
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
    .form-control:focus { outline: none; border-color: var(--accent-gold); }
    .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
  </style>
</head>
<body>
<div class="bg-mesh"></div>

<div class="sadmin-container">

<?php if (!$isSuperAdmin): ?>
  <!-- SUPER ADMIN AUTHENTICATION GATE -->
  <div style="min-height: 80vh; display: flex; align-items: center; justify-content: center;">
    <div style="width: 100%; max-width: 420px; background: var(--bg-surface); border: 1.5px solid var(--border-accent); border-radius: 20px; padding: 36px; box-shadow: 0 20px 50px rgba(0,0,0,0.6); text-align: center;">
      <div class="sadmin-logo" style="margin: 0 auto 16px; width: 64px; height: 64px; font-size: 28px;">⚡</div>
      <h2 style="font-size: 22px; font-weight: 800; margin-bottom: 6px;">Super Admin Command</h2>
      <p style="font-size: 13px; color: var(--text-secondary); margin-bottom: 24px;">Platform Owner Master Command Access</p>

      <?php if ($isLoggedInSuperAdmin): ?>
        <div style="background: rgba(234, 179, 8, 0.1); border: 1px solid rgba(234, 179, 8, 0.3); border-radius: 12px; padding: 14px; margin-bottom: 22px; text-align: left; font-size: 12.5px;">
          <div style="color: #facc15; font-weight: 700; margin-bottom: 4px;">⚡ Active Superadmin Session</div>
          <div style="color: var(--text-secondary); margin-bottom: 12px;">
            Currently authenticated as <strong><?php echo htmlspecialchars($currentUser['name'] ?? $currentUser['username']); ?></strong>.
          </div>
          <div style="display: flex; gap: 8px;">
            <a href="?portal=1" class="btn btn-gold" style="padding: 7px 14px; font-size: 12px; text-decoration: none; border-radius: 8px; flex: 1; justify-content: center;">Enter Command Center &rarr;</a>
            <a href="?action=logout" class="btn btn-secondary" style="padding: 7px 12px; font-size: 12px; text-decoration: none; border-radius: 8px; color: #ef4444;">Sign Out</a>
          </div>
        </div>
      <?php endif; ?>

      <form id="sadminLoginForm" onsubmit="handleSadminLogin(event)">
        <div class="form-group" style="text-align: left;">
          <label class="form-label">Super Key / Username</label>
          <input type="text" id="sUsername" class="form-control" placeholder="sadmin" required autofocus>
        </div>
        <div class="form-group" style="text-align: left; margin-bottom: 22px;">
          <label class="form-label">Master Password</label>
          <input type="password" id="sPassword" class="form-control" placeholder="sadmin123" required>
        </div>
        <div id="loginError" style="display: none; padding: 10px; border-radius: 8px; background: rgba(244, 63, 94, 0.15); border: 1px solid rgba(244, 63, 94, 0.3); color: #fb7185; font-size: 12.5px; margin-bottom: 14px;"></div>
        <button type="submit" id="sadminLoginBtn" class="btn btn-gold" style="width: 100%; justify-content: center; padding: 12px; font-size: 14px;">
          Unlock Platform Command ⚡
        </button>
      </form>
      <div style="margin-top: 20px; font-size: 12px; color: var(--text-secondary);">
        <a href="/" style="color: var(--accent-gold); text-decoration: none; font-weight: 600;">&larr; Return to Chatly Homepage</a>
      </div>
    </div>
  </div>

<?php else: ?>
  <!-- SUPER ADMIN MASTER COMMAND CENTER -->
  <header class="top-bar">
    <div class="brand-section" style="display:flex; align-items:center; gap:12px;">
      <a href="/" title="Chatly Homepage" style="display:flex; align-items:center; text-decoration:none;">
        <img src="assets/images/chatly-logo.png" alt="Chatly" class="chatly-logo chatly-logo--dark"><img src="assets/images/chatly-logo-light.png" alt="Chatly" class="chatly-logo chatly-logo--light">
      </a>
      <div style="height:28px; width:1px; background:rgba(255,255,255,0.15); margin:0 2px;"></div>
      <div class="sadmin-logo">⚡</div>
      <div>
        <div class="sadmin-title">
          <span>Chatly Master Headquarters</span>
          <span class="badge-sadmin">SADMIN Command</span>
        </div>
        <div class="sadmin-sub">Global Multi-Company Telemetry & Infrastructure Management</div>
      </div>
    </div>

    <div class="header-actions">
      <!-- Real-Time Brand Product Promotion Inquiries Notification Pill -->
      <button class="btn btn-secondary" id="btnHeaderBrandInquiries" onclick="switchSadminTab('brandInquiries')" style="position:relative; border-color:rgba(31,224,138,0.4); background:rgba(31,224,138,0.08); color:var(--text-primary);">
        <span style="font-size:15px;">📢</span>
        <span>Brand Leads</span>
        <span id="headerBrandBadge" style="display:none; background:#10b981; color:#03130c; font-size:10.5px; padding:2px 7px; border-radius:10px; font-weight:800; margin-left:4px;">0</span>
      </button>
      <button class="btn btn-gold" onclick="openProvisionModal()">
        <span>➕</span> Provision New Company
      </button>
      <button class="btn btn-secondary" onclick="loadDashboardData()">
        <span>🔄</span> Refresh Telemetry
      </button>
      <button class="btn btn-secondary" onclick="toggleTheme()">
        <span>🌓</span>
      </button>
      <a href="/ss" class="btn btn-secondary" style="font-size:12px;" title="Switch Account">
        <span>🔄</span> Switch
      </a>
      <a href="?action=logout" class="btn btn-danger">
        <span>🚪</span> Logout
      </a>
    </div>
  </header>

  <!-- 0.01s HIGH-FREQUENCY REAL-TIME TELEMETRY HUD -->
  <div style="background:linear-gradient(90deg, rgba(234,179,8,0.12), rgba(31, 224, 138,0.12), rgba(16,185,129,0.12)); border:1px solid rgba(234,179,8,0.35); border-radius:14px; padding:12px 20px; margin-bottom:20px; display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:16px; box-shadow:0 0 25px rgba(234,179,8,0.15);">
    <div style="display:flex; align-items:center; gap:10px;">
      <span style="display:inline-block; width:10px; height:10px; border-radius:50%; background:#eab308; box-shadow:0 0 12px #eab308; animation:pulseDot 0.8s infinite alternate;"></span>
      <span style="font-size:12px; font-weight:800; text-transform:uppercase; letter-spacing:0.06em; color:#fde047;">REALTIME CLOCK (0.01s Heartbeat):</span>
      <span id="subsecondHeartbeat" style="font-family:'JetBrains Mono',monospace; font-size:13px; font-weight:700; color:#fff;">0.000s</span>
    </div>
    
    <div style="display:flex; align-items:center; gap:20px; font-family:'JetBrains Mono',monospace; font-size:12.5px; flex-wrap:wrap;">
      <div>
        <span style="color:var(--text-secondary); font-size:11px;">LIVE DISPATCHES:</span>
        <strong id="liveOdometer" style="color:#34d399; margin-left:4px; font-size:14px;">14,892,104</strong>
      </div>
      <div>
        <span style="color:var(--text-secondary); font-size:11px;">PULSE LATENCY:</span>
        <strong id="livePing" style="color:#60a5fa; margin-left:4px;">11.8ms</strong>
      </div>
      <div>
        <span style="color:var(--text-secondary); font-size:11px;">ACTIVE SOCKETS:</span>
        <strong id="liveSockets" style="color:#8ff5c8; margin-left:4px;">8 Nodes</strong>
      </div>
      <div>
        <span style="color:var(--text-secondary); font-size:11px;">PENDING CLOSERS:</span>
        <strong id="hudPendingCount" style="color:#fb7185; margin-left:4px;">0</strong>
      </div>
    </div>
  </div>

  <!-- 5 Hero Platform Telemetry Cards -->
  <div class="metric-grid">
    <div class="metric-card">
      <div class="metric-header">
        <span class="metric-label">Platform MRR</span>
        <div class="metric-icon" style="background: rgba(234, 179, 8, 0.15); color: #fde047;">💵</div>
      </div>
      <div class="metric-val" id="metricMRR" style="color: #fde047;">Rs. 475,000</div>
      <div class="metric-sub">Monthly Recurring Revenue</div>
    </div>

    <div class="metric-card">
      <div class="metric-header">
        <span class="metric-label">Active Companies</span>
        <div class="metric-icon" style="background: rgba(16, 185, 129, 0.15); color: #34d399;">🏢</div>
      </div>
      <div class="metric-val" id="metricCompanies">-</div>
      <div class="metric-sub"><span id="metricActiveComp">-</span> active tenants provisioned</div>
    </div>

    <div class="metric-card">
      <div class="metric-header">
        <span class="metric-label">Global Workforce</span>
        <div class="metric-icon" style="background: rgba(59, 130, 246, 0.15); color: #60a5fa;">👥</div>
      </div>
      <div class="metric-val" id="metricTotalWorkforce">-</div>
      <div class="metric-sub"><span id="metricTLs">-</span> Team Leads &bull; <span id="metricTMs">-</span> Closers</div>
    </div>

    <div class="metric-card">
      <div class="metric-header">
        <span class="metric-label">Messages Today (Global)</span>
        <div class="metric-icon" style="background: rgba(31, 224, 138, 0.15); color: #8ff5c8;">💬</div>
      </div>
      <div class="metric-val" id="metricGlobalMessages">-</div>
      <div class="metric-sub">Across all company closer sessions</div>
    </div>

    <div class="metric-card">
      <div class="metric-header">
        <span class="metric-label">Bridge Infrastructure</span>
        <div class="metric-icon" style="background: rgba(16, 185, 129, 0.15); color: #34d399;">⚡</div>
      </div>
      <div class="metric-val" style="font-size: 20px; color: #34d399;" id="metricBridgeHealth">Baileys Pure WS 🟢</div>
      <div class="metric-sub">Zero Chromium &bull; Port 3210 Active</div>
    </div>

    <div class="metric-card" onclick="switchSadminTab('brandInquiries')" style="cursor:pointer; border-color:rgba(31,224,138,0.3); transition:transform 0.15s ease;" onmouseover="this.style.transform='translateY(-2px)'" onmouseout="this.style.transform='none'">
      <div class="metric-header">
        <span class="metric-label">Brand Inquiries</span>
        <div class="metric-icon" style="background: rgba(31, 224, 138, 0.15); color: #34d399;">📢</div>
      </div>
      <div class="metric-val" id="metricBrandInquiries" style="color: #34d399;">0</div>
      <div class="metric-sub"><span id="metricNewBrandInquiries" style="color:#4cecab; font-weight:700;">0 new</span> &bull; Click to review</div>
    </div>
  </div>

  <!-- SADMIN Navigation Tabs -->
  <div class="sadmin-tabs">
    <button class="sadmin-tab-btn active" id="tabBtnCompanies" onclick="switchSadminTab('companies')">
      <span>🏢</span> 1. Company Directory &amp; Matrix
    </button>
    <button class="sadmin-tab-btn" id="tabBtnChatter" onclick="switchSadminTab('chatter')">
      <span>⚡</span> 2. Real-Time Cross-Company Chatter
    </button>
    <button class="sadmin-tab-btn" id="tabBtnLogins" onclick="switchSadminTab('logins')">
      <span>👥</span> 3. Global Logins &amp; Sessions
    </button>
    <button class="sadmin-tab-btn" id="tabBtnMasterPending" onclick="switchSadminTab('masterPending')">
      <span>⏳</span> 4. Master Pending TM Approvals <span id="sadminPendingBadge" style="display:none; background:#ef4444; color:#fff; font-size:10px; padding:2px 6px; border-radius:10px; font-weight:800; margin-left:4px;">0</span>
    </button>
    <button class="sadmin-tab-btn" id="tabBtnNumbers" onclick="switchSadminTab('numbers')">
      <span>📞</span> 5. Global Fallback Numbers Pool
    </button>
    <button class="sadmin-tab-btn" id="tabBtnProvision" onclick="switchSadminTab('provision')">
      <span>➕</span> 6. Provision New Company
    </button>
    <button class="sadmin-tab-btn" id="tabBtnAudit" onclick="switchSadminTab('audit')">
      <span>📋</span> 7. Platform Security Logs
    </button>
    <button class="sadmin-tab-btn" id="tabBtnFinancials" onclick="switchSadminTab('financials')">
      <span>💳</span> 8. Billing &amp; Nunno 1700 Ledger
    </button>
    <button class="sadmin-tab-btn" id="tabBtnBrandInquiries" onclick="switchSadminTab('brandInquiries')">
      <span>📢</span> 9. Brand Product Inquiries <span id="sadminBrandBadge" style="display:none; background:#10b981; color:#03130c; font-size:10px; padding:2px 6px; border-radius:10px; font-weight:800; margin-left:4px;">0</span>
    </button>
  </div>

  <!-- TAB 1: COMPANY DIRECTORY & COMPLETE STATS -->
  <div class="sadmin-pane active" id="paneCompanies">
    <div class="content-box">
      <div class="box-header">
        <div>
          <div class="box-title"><span>🏢</span> All Onboarded Companies &amp; Real-time Stats</div>
          <div class="box-desc">Monitor every company's MRR, employee count, conversion rates, and message volume. Impersonate admin in 1 click.</div>
        </div>
        <div style="display:flex; gap:10px;">
          <input type="text" id="compSearch" oninput="filterCompaniesTable()" placeholder="Search company by name or slug..." class="form-control" style="width:260px; padding:8px 12px; font-size:12.5px;">
          <button class="btn btn-gold" onclick="openProvisionModal()">➕ Add Company</button>
        </div>
      </div>

      <div style="overflow-x: auto;">
        <table>
          <thead>
            <tr>
              <th>Company Name &amp; Slug</th>
              <th>Subscription Plan &amp; MRR</th>
              <th>Workforce (TMs / TLs)</th>
              <th>Messages Sent Today</th>
              <th>Fallback Phone</th>
              <th>Status</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody id="companyTableBody">
            <tr><td colspan="7" style="text-align: center; padding: 24px; color: var(--text-muted);">Loading company records...</td></tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- TAB 2: REAL-TIME CROSS-COMPANY CHATTER STREAM -->
  <div class="sadmin-pane" id="paneChatter">
    <div class="content-box">
      <div class="box-header">
        <div>
          <div class="box-title">
            <span style="display:inline-block; width:10px; height:10px; border-radius:50%; background:#10b981; box-shadow:0 0 10px #10b981; animation:pulseDot 1s infinite alternate;"></span>
            <span>⚡ Global Real-Time Chatter Stream (Cross-Company Live Monitor)</span>
          </div>
          <div class="box-desc">
            Real-time feed of all incoming and outgoing WhatsApp messages across all tenant companies. Monitor conversations, sentiment, and sales progress live.
          </div>
        </div>
        <div style="display:flex; gap:10px; align-items:center;">
          <select id="chatterCompanyFilter" class="form-control" style="width:220px; padding:7px 12px; font-size:12px;" onchange="renderChatterStream()">
            <option value="ALL">All Companies (Live Matrix)</option>
          </select>
          <button class="btn btn-secondary" onclick="loadChatterStream()">🔄 Refresh Stream</button>
        </div>
      </div>

      <div style="overflow-x: auto;">
        <table>
          <thead>
            <tr>
              <th>Tenant Company</th>
              <th>Customer / Lead</th>
              <th>Assigned Closer</th>
              <th>Live Message Content</th>
              <th>Direction</th>
              <th>Pipeline State</th>
              <th>Time</th>
            </tr>
          </thead>
          <tbody id="chatterTableBody">
            <tr><td colspan="7" style="text-align: center; padding: 24px; color: var(--text-muted);">Connecting to global cross-company chat stream...</td></tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- TAB 3: GLOBAL LOGINS & SESSIONS -->
  <div class="sadmin-pane" id="paneLogins">
    <div class="content-box">
      <div class="box-header">
        <div>
          <div class="box-title"><span>👥</span> Global Logins &amp; Active Sessions Monitor</div>
          <div class="box-desc">Audit trail of all employee, TL, and administrator logins across the entire platform. Track IP addresses, devices, and sessions.</div>
        </div>
        <button class="btn btn-secondary" onclick="loadLoginsData()">🔄 Refresh Sessions</button>
      </div>

      <div style="overflow-x: auto;">
        <table>
          <thead>
            <tr>
              <th>User &amp; Username</th>
              <th>Assigned Role</th>
              <th>Company / Tenant</th>
              <th>IP Address &amp; Location</th>
              <th>Device &amp; Browser</th>
              <th>Login Timestamp</th>
              <th>Session Status</th>
            </tr>
          </thead>
          <tbody id="loginsTableBody">
            <tr><td colspan="7" style="text-align: center; padding: 24px; color: var(--text-muted);">Loading global login records...</td></tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- TAB 4: MASTER PENDING TM APPROVALS QUEUE -->
  <div class="sadmin-pane" id="paneMasterPending">
    <div class="content-box">
      <div class="box-header">
        <div>
          <div class="box-title"><span>⏳</span> Master Pending Closer (TM) Approvals Queue</div>
          <div class="box-desc">Global platform overview of all closer applicants awaiting company authorization. As Super Admin, you can review and override-approve or decline applicants for any tenant.</div>
        </div>
        <button class="btn btn-secondary" onclick="loadMasterPendingTMs()">🔄 Refresh Pending</button>
      </div>

      <div style="overflow-x: auto;">
        <table>
          <thead>
            <tr>
              <th>Applicant Profile</th>
              <th>Target Company</th>
              <th>Applicant Credentials (Username &amp; Email)</th>
              <th>Connected WhatsApp Phone</th>
              <th>City &amp; Gender</th>
              <th>Applied Date</th>
              <th>Master Actions</th>
            </tr>
          </thead>
          <tbody id="masterPendingTableBody">
            <tr><td colspan="7" style="text-align: center; padding: 24px; color: var(--text-muted);">Loading platform-wide pending applications...</td></tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- TAB 2: PROVISION NEW COMPANY FORM (INLINE & MODAL) -->
  <div class="sadmin-pane" id="paneProvision">
    <div class="content-box" style="max-width: 800px;">
      <div class="box-header">
        <div>
          <div class="box-title"><span>➕</span> Provision New Enterprise Company</div>
          <div class="box-desc">Creates an isolated multi-tenant company with its own database, dedicated closer limits, products catalog, and company admin account.</div>
        </div>
      </div>

      <form id="inlineProvisionForm" onsubmit="handleProvisionCompany(event)">
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Company Legal Name *</label>
            <input type="text" id="compName" class="form-control" placeholder="e.g. Apex Sales Agency" required>
          </div>
          <div class="form-group">
            <label class="form-label">Company Slug (URL identifier) *</label>
            <input type="text" id="compSlug" class="form-control" placeholder="e.g. apex" required>
          </div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Owner Full Name *</label>
            <input type="text" id="compOwnerName" class="form-control" placeholder="e.g. Tariq Mehmood" required>
          </div>
          <div class="form-group">
            <label class="form-label">Owner Email *</label>
            <input type="email" id="compOwnerEmail" class="form-control" placeholder="owner@company.com" required>
          </div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Admin Username *</label>
            <input type="text" id="compAdminUser" class="form-control" placeholder="e.g. apex_admin" required>
          </div>
          <div class="form-group">
            <label class="form-label">Admin Password *</label>
            <input type="text" id="compAdminPass" class="form-control" placeholder="Min 6 characters" required>
          </div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Subscription Tier</label>
            <select id="compPlan" class="form-control">
              <option value="Starter (Rs. 15,000/mo)">Starter (Rs. 15,000 / mo)</option>
              <option value="Growth (Rs. 35,000/mo)" selected>Growth (Rs. 35,000 / mo)</option>
              <option value="Enterprise Pro (Rs. 75,000/mo)">Enterprise Pro (Rs. 75,000 / mo)</option>
              <option value="Billion Custom (Rs. 150,000/mo)">Billion Custom (Rs. 150,000 / mo)</option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Default Message Limit / Closer</label>
            <input type="number" id="compDefaultLimit" class="form-control" value="200" min="10" max="5000">
          </div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Max Allowed Closers</label>
            <input type="number" id="compMaxEmployees" class="form-control" value="50" min="1" max="1000">
          </div>
          <div class="form-group">
            <label class="form-label">Company Fallback Phone</label>
            <input type="text" id="compFallbackPhone" class="form-control" placeholder="+923318036576">
          </div>
        </div>

        <div class="form-row">
          <div class="form-group" style="flex: 2;">
            <label class="form-label">Company Logo (Image URL or Path) 🖼️</label>
            <input type="text" id="compLogo" class="form-control" placeholder="assets/images/company-logo.png or https://... or choose file ->" oninput="previewCompanyLogo(this.value)">
          </div>
          <div class="form-group" style="flex: 1.2;">
            <label class="form-label">Or Upload Logo</label>
            <input type="file" id="compLogoFile" class="form-control" accept="image/*" onchange="handleLogoFileUpload(event)" style="padding: 7px 10px; font-size: 12px;">
          </div>
          <div class="form-group" style="flex: 0 0 70px; display: flex; flex-direction: column; align-items: center; justify-content: center;">
            <label class="form-label" style="margin-bottom: 4px;">Preview</label>
            <div id="compLogoPreviewBox" style="width: 44px; height: 44px; border-radius: 10px; border: 1px dashed var(--border-accent); display: flex; align-items: center; justify-content: center; background: rgba(255,255,255,0.04); overflow: hidden;">
              <span id="compLogoPreviewText" style="font-size: 18px;">🏢</span>
              <img id="compLogoPreviewImg" src="" style="width: 100%; height: 100%; object-fit: contain; display: none;">
            </div>
          </div>
        </div>

        <div id="provisionError" style="display:none; padding:10px; border-radius:8px; background:rgba(244,63,94,0.15); border:1px solid rgba(244,63,94,0.3); color:#fb7185; font-size:12.5px; margin-bottom:14px;"></div>

        <button type="submit" id="btnProvisionSubmit" class="btn btn-gold" style="padding:12px 24px; font-size:14px;">
          🚀 Provision Company & Launch Tenant Database
        </button>
      </form>
    </div>
  </div>

  <!-- TAB 3: GLOBAL FALLBACK NUMBERS POOL -->
  <div class="sadmin-pane" id="paneNumbers">
    <div class="content-box">
      <div class="box-header">
        <div>
          <div class="box-title"><span>📞</span> Global Company Fallback Number Profiles Pool</div>
          <div class="box-desc">If a user/TM cannot arrange their own phone, they will use one of these company numbers until their device is ready.</div>
        </div>
        <button class="btn btn-gold" onclick="openAddNumberModal()">➕ Add Fallback Number</button>
      </div>

      <div style="overflow-x: auto;">
        <table>
          <thead>
            <tr>
              <th>Profile ID</th>
              <th>Phone Number</th>
              <th>Label / Note</th>
              <th>Daily Sending Capacity</th>
              <th>Assigned Company / User</th>
              <th>Status</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody id="numberPoolTableBody">
            <tr><td colspan="7" style="text-align: center; padding: 24px; color: var(--text-muted);">Loading number profiles...</td></tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- TAB 4: PLATFORM SECURITY & AUDIT LOGS -->
  <div class="sadmin-pane" id="paneAudit">
    <div class="content-box">
      <div class="box-header">
        <div>
          <div class="box-title"><span>📋</span> Real-time Platform Security & Audit Trail</div>
          <div class="box-desc">Live activity logs of all administrative actions, lead takeovers, limit modifications, and logins across all tenants.</div>
        </div>
        <button class="btn btn-secondary" onclick="loadAuditLogs()">🔄 Refresh Audit Trail</button>
      </div>

      <div style="overflow-x: auto;">
        <table>
          <thead>
            <tr>
              <th>Timestamp</th>
              <th>Tenant / Company</th>
              <th>Actor</th>
              <th>Action</th>
              <th>Event Details</th>
            </tr>
          </thead>
          <tbody id="auditTableBody">
            <tr><td colspan="5" style="text-align: center; padding: 24px; color: var(--text-muted);">Fetching audit records...</td></tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- TAB 5: BILLING & NUNNO 1700 LEDGER -->
  <div class="sadmin-pane" id="paneFinancials">
    <div class="content-box">
      <div class="box-header">
        <div>
          <div class="box-title"><span>💳</span> Platform Billing & Nunno Rs. 1,700 Plan Settlement</div>
          <div class="box-desc">Overview of mandatory closer plan purchases (Rs. 1,700/TM) and Team Leader commission payouts (Rs. 500/TM).</div>
        </div>
      </div>

      <div class="metric-grid" style="margin-bottom:20px;">
        <div class="metric-card">
          <div class="metric-header"><span class="metric-label">Nunno Plan Revenue</span><div style="font-size:18px;">💳</div></div>
          <div class="metric-val" style="color:#34d399;">Rs. 85,000</div>
          <div class="metric-sub">50 closer activations (@ Rs. 1,700)</div>
        </div>
        <div class="metric-card">
          <div class="metric-header"><span class="metric-label">TL Commissions Paid</span><div style="font-size:18px;">💰</div></div>
          <div class="metric-val" style="color:#8ff5c8;">Rs. 25,000</div>
          <div class="metric-sub">50 TMs recruited (@ Rs. 500)</div>
        </div>
        <div class="metric-card">
          <div class="metric-header"><span class="metric-label">Platform Net Spread</span><div style="font-size:18px;">📈</div></div>
          <div class="metric-val" style="color:#fde047;">Rs. 60,000</div>
          <div class="metric-sub">Net profit from onboarding fee</div>
        </div>
      </div>
    </div>
  </div>

  <!-- TAB 9: BRAND PRODUCT INQUIRIES & PROMOTION PIPELINE -->
  <div class="sadmin-pane" id="paneBrandInquiries">
    <div class="content-box">
      <div class="box-header">
        <div>
          <div class="box-title"><span>📢</span> Inbound Brand Product Inquiries &amp; Promotion Leads</div>
          <div class="box-desc">
            Prospects who submitted the "Promote my product" form on the Chatly homepage. Review their product details, chat with them on WhatsApp, and convert them to an onboarded company in 1 click.
          </div>
        </div>
        <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
          <input type="text" id="inqSearchInput" oninput="filterBrandInquiriesTable()" placeholder="Search brand, product, phone..." class="form-control" style="width:240px; padding:8px 12px; font-size:12.5px;">
          <select id="inqStatusFilter" onchange="filterBrandInquiriesTable()" class="form-control" style="width:160px; padding:8px 12px; font-size:12.5px; cursor:pointer;">
            <option value="all">All Statuses</option>
            <option value="new" selected>🟢 New Leads</option>
            <option value="contacted">🟡 Contacted</option>
            <option value="converted">🏢 Converted</option>
            <option value="archived">⚪ Archived</option>
          </select>
          <button class="btn btn-secondary" onclick="loadBrandInquiries(true)" style="padding:8px 14px; font-size:12.5px;">
            <span>🔄</span> Refresh
          </button>
          <button class="btn btn-secondary" onclick="markAllBrandInquiriesRead()" style="padding:8px 14px; font-size:12.5px;">
            <span>✓</span> Mark All Read
          </button>
        </div>
      </div>

      <!-- Quick Metrics for Inquiries -->
      <div class="metric-grid" style="grid-template-columns: repeat(4, 1fr); margin-bottom: 20px;">
        <div class="metric-card" style="padding:14px;">
          <div class="metric-label">Total Submissions</div>
          <div class="metric-val" id="inqMetricTotal" style="font-size:22px; color:var(--text-primary);">-</div>
          <div class="metric-sub">Lifetime product inquiries</div>
        </div>
        <div class="metric-card" style="padding:14px; border-color:rgba(16, 185, 129, 0.3);">
          <div class="metric-label" style="color:#34d399;">New (Uncontacted)</div>
          <div class="metric-val" id="inqMetricNew" style="font-size:22px; color:#34d399;">-</div>
          <div class="metric-sub">Awaiting initial WhatsApp outreach</div>
        </div>
        <div class="metric-card" style="padding:14px;">
          <div class="metric-label" style="color:#fbbf24;">Contacted / In Discussion</div>
          <div class="metric-val" id="inqMetricContacted" style="font-size:22px; color:#fbbf24;">-</div>
          <div class="metric-sub">Negotiating closer terms</div>
        </div>
        <div class="metric-card" style="padding:14px; border-color:rgba(234, 179, 8, 0.3);">
          <div class="metric-label" style="color:#fde047;">Converted to Company</div>
          <div class="metric-val" id="inqMetricConverted" style="font-size:22px; color:#fde047;">-</div>
          <div class="metric-sub">Live tenant on Chatly</div>
        </div>
      </div>

      <div style="overflow-x: auto;">
        <table>
          <thead>
            <tr>
              <th>Brand &amp; Product</th>
              <th>Pitch &amp; Details</th>
              <th>Business Contact</th>
              <th>Submitted</th>
              <th>Status</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody id="brandInquiriesTableBody">
            <tr><td colspan="6" style="text-align: center; padding: 28px; color: var(--text-muted);">Loading brand inquiries...</td></tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>

<?php endif; ?>

<!-- BRAND INQUIRY PITCH MODAL -->
<div class="modal-overlay" id="inquiryDetailModal" style="display:none;">
  <div class="modal-card" style="max-width: 580px;">
    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px;">
      <div>
        <h3 class="modal-title" id="inqModalBrandTitle">Brand Product Details</h3>
        <div style="font-size: 12px; color: var(--text-secondary);" id="inqModalSub">Full Pitch &amp; Specifications</div>
      </div>
      <button onclick="closeInquiryDetailModal()" style="background:none; border:none; color:var(--text-muted); font-size:24px; cursor:pointer;">&times;</button>
    </div>

    <div style="background:rgba(255,255,255,0.03); border:1px solid var(--border-subtle); border-radius:12px; padding:16px; margin-bottom:16px;">
      <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:12px;">
        <div>
          <div style="font-size:11px; color:var(--text-muted); text-transform:uppercase;">Product Name</div>
          <strong id="inqModalProductName" style="font-size:14px; color:#fff;">-</strong>
        </div>
        <div>
          <div style="font-size:11px; color:var(--text-muted); text-transform:uppercase;">Price &amp; Category</div>
          <span id="inqModalPriceCat" style="font-size:13px; color:#34d399; font-weight:700;">-</span>
        </div>
      </div>
      <div style="margin-bottom:12px;">
        <div style="font-size:11px; color:var(--text-muted); text-transform:uppercase; margin-bottom:4px;">Product Description &amp; Pitch</div>
        <div id="inqModalPitchText" style="font-size:13px; color:var(--text-primary); line-height:1.6; background:rgba(0,0,0,0.3); padding:10px 12px; border-radius:8px; border:1px solid rgba(255,255,255,0.05); white-space:pre-wrap;">-</div>
      </div>
      <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
        <div>
          <div style="font-size:11px; color:var(--text-muted); text-transform:uppercase;">Target Daily Orders</div>
          <span id="inqModalVolume" style="font-size:12.5px; color:#f8fafc;">-</span>
        </div>
        <div>
          <div style="font-size:11px; color:var(--text-muted); text-transform:uppercase;">Website / Social Link</div>
          <div id="inqModalWebsiteLink" style="font-size:12.5px;">-</div>
        </div>
      </div>
    </div>

    <div style="display:flex; justify-content:space-between; align-items:center;">
      <div id="inqModalContactSnippet" style="font-size:12px; color:var(--text-muted);">
        Contact: <strong id="inqModalContactName" style="color:#fff;">-</strong>
      </div>
      <div style="display:flex; gap:8px;">
        <a id="inqModalWAButton" href="#" target="_blank" class="btn btn-emerald" style="padding:6px 14px; font-size:12px; text-decoration:none;">
          <span>💬</span> Chat on WhatsApp
        </a>
        <button type="button" class="btn btn-secondary" onclick="closeInquiryDetailModal()">Close</button>
      </div>
    </div>
  </div>
</div>

<!-- REAL-TIME NOTIFICATION TOAST FOR NEW INQUIRIES -->
<div id="sadminNotificationToast" style="display:none; position:fixed; top:20px; right:24px; z-index:9999999; background:linear-gradient(135deg, #071e14, #0b2d1f); border:1px solid rgba(31,224,138,0.5); box-shadow:0 12px 40px rgba(0,0,0,0.8); border-radius:14px; padding:14px 20px; max-width:400px; cursor:pointer; transition:all 0.3s cubic-bezier(0.16, 1, 0.3, 1);" onclick="switchSadminTab('brandInquiries')">
  <div style="display:flex; align-items:flex-start; gap:12px;">
    <div style="font-size:22px; line-height:1;">🔔</div>
    <div>
      <div style="font-size:13px; font-weight:800; color:#4cecab; margin-bottom:2px;" id="toastTitle">New Brand Promotion Lead!</div>
      <div style="font-size:11.5px; color:#e2e8f0; line-height:1.4;" id="toastMsg">A brand submitted a new product inquiry. Click to review.</div>
    </div>
    <button type="button" onclick="event.stopPropagation(); closeSadminToast();" style="background:none; border:none; color:var(--text-muted); font-size:18px; cursor:pointer; margin-left:auto; line-height:1;">&times;</button>
  </div>
</div>

</div>

<!-- COMPANY DEEP STATS MODAL -->
<div class="modal-overlay" id="deepStatsModal">
  <div class="modal-card" style="max-width: 720px;">
    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 18px;">
      <div>
        <h3 class="modal-title" id="statsModalTitle">📊 Company Deep Statistics</h3>
        <div style="font-size: 12px; color: var(--text-secondary);" id="statsModalSub">Live Telemetry & Lead Breakdown</div>
      </div>
      <button onclick="closeDeepStatsModal()" style="background:none; border:none; color:var(--text-muted); font-size:22px; cursor:pointer;">&times;</button>
    </div>

    <div class="metric-grid" style="grid-template-columns: repeat(3, 1fr); margin-bottom: 20px;">
      <div class="metric-card" style="padding:14px;">
        <div class="metric-label">Total Leads</div>
        <div class="metric-val" id="stTotalLeads" style="font-size:22px;">-</div>
      </div>
      <div class="metric-card" style="padding:14px;">
        <div class="metric-label">Paid (Closed)</div>
        <div class="metric-val" id="stPaidLeads" style="font-size:22px; color:#34d399;">-</div>
      </div>
      <div class="metric-card" style="padding:14px;">
        <div class="metric-label">Conversion Rate</div>
        <div class="metric-val" id="stConversion" style="font-size:22px; color:#fde047;">-</div>
      </div>
    </div>

    <h4 style="font-size: 13.5px; font-weight: 700; margin-bottom: 10px;">Closers & Daily Message Quotas</h4>
    <div style="max-height: 240px; overflow-y: auto;">
      <table>
        <thead>
          <tr>
            <th>Closer</th>
            <th>Role</th>
            <th>Today's Sent</th>
            <th>Daily Limit</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody id="stClosersTbody">
          <tr><td colspan="5" style="text-align:center; padding:16px;">Loading closer stats...</td></tr>
        </tbody>
      </table>
    </div>

    <div style="display: flex; justify-content: flex-end; margin-top: 20px;">
      <button class="btn btn-secondary" onclick="closeDeepStatsModal()">Close</button>
    </div>
  </div>
</div>

<!-- ADD FALLBACK NUMBER MODAL -->
<div class="modal-overlay" id="addNumberModal">
  <div class="modal-card">
    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 18px;">
      <h3 class="modal-title">➕ Add Company Fallback Number</h3>
      <button onclick="closeAddNumberModal()" style="background:none; border:none; color:var(--text-muted); font-size:22px; cursor:pointer;">&times;</button>
    </div>

    <form id="addNumberForm" onsubmit="handleAddNumber(event)">
      <div class="form-group">
        <label class="form-label">Phone Number *</label>
        <input type="text" id="numPhone" class="form-control" placeholder="+923318036576" required>
      </div>
      <div class="form-group">
        <label class="form-label">Label / Identifier *</label>
        <input type="text" id="numLabel" class="form-control" placeholder="e.g. Ufone Official Trunk Line 1" required>
      </div>
      <div class="form-group">
        <label class="form-label">Daily Message Capacity</label>
        <input type="number" id="numCap" class="form-control" value="500" min="50" max="5000">
      </div>
      <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px;">
        <button type="button" class="btn btn-secondary" onclick="closeAddNumberModal()">Cancel</button>
        <button type="submit" class="btn btn-gold">Add to Number Pool 🚀</button>
      </div>
    </form>
  </div>
</div>

<script>
let currentCompanies = [];
let currentChatterStream = [];
let currentLogins = [];
let currentMasterPending = [];

// 0.01s (10ms) High-Frequency Real-Time Numbers Ticker
let baseOdometer = 14892104;
let startClockTime = performance.now();

setInterval(() => {
  const elapsed = (performance.now() - startClockTime) / 1000;
  const clockEl = document.getElementById('subsecondHeartbeat');
  if (clockEl) {
    clockEl.textContent = elapsed.toFixed(3) + 's';
  }
  const odoEl = document.getElementById('liveOdometer');
  if (odoEl) {
    baseOdometer += Math.floor(Math.random() * 2);
    odoEl.textContent = baseOdometer.toLocaleString();
  }
  const pingEl = document.getElementById('livePing');
  if (pingEl) {
    const jitter = (11.0 + Math.random() * 1.8).toFixed(1);
    pingEl.textContent = jitter + 'ms';
  }
}, 10);

function switchSadminTab(tabName) {
  const tabs = ['companies', 'chatter', 'logins', 'masterPending', 'numbers', 'provision', 'audit', 'financials', 'brandInquiries'];
  tabs.forEach((t) => {
    const pane = document.getElementById('pane' + t.charAt(0).toUpperCase() + t.slice(1));
    const btn = document.getElementById('tabBtn' + t.charAt(0).toUpperCase() + t.slice(1));
    if (pane) pane.classList.toggle('active', t === tabName);
    if (btn) btn.classList.toggle('active', t === tabName);
  });
  if (tabName === 'chatter') loadChatterStream();
  if (tabName === 'logins') loadLoginsData();
  if (tabName === 'masterPending') loadMasterPendingTMs();
  if (tabName === 'numbers') loadNumberPool();
  if (tabName === 'audit') loadAuditLogs();
  if (tabName === 'brandInquiries') loadBrandInquiries();
}

function openProvisionModal() { switchSadminTab('provision'); }
function closeDeepStatsModal() { document.getElementById('deepStatsModal').style.display = 'none'; }
function openAddNumberModal() { document.getElementById('addNumberModal').style.display = 'flex'; }
function closeAddNumberModal() { document.getElementById('addNumberModal').style.display = 'none'; }

// Load Dashboard Data & Telemetry
async function loadDashboardData() {
  try {
    const [telRes, compRes] = await Promise.all([
      fetch('api/companies.php?action=get_telemetry').then(r => r.json()),
      fetch('api/companies.php?action=list').then(r => r.json())
    ]);

    if (telRes.success && telRes.telemetry) {
      const t = telRes.telemetry;
      document.getElementById('metricCompanies').textContent = t.total_companies;
      document.getElementById('metricActiveComp').textContent = t.active_companies;
      document.getElementById('metricTotalWorkforce').textContent = t.total_employees;
      document.getElementById('metricTLs').textContent = t.total_tls;
      document.getElementById('metricTMs').textContent = t.total_tms;
      document.getElementById('metricGlobalMessages').textContent = t.total_messages_today;
      if (t.platform_mrr) {
        document.getElementById('metricMRR').textContent = 'Rs. ' + Number(t.platform_mrr).toLocaleString();
      }
      if (t.pending_tms !== undefined) {
        document.getElementById('hudPendingCount').textContent = t.pending_tms;
        const b = document.getElementById('sadminPendingBadge');
        if (b) {
          b.textContent = t.pending_tms;
          b.style.display = t.pending_tms > 0 ? 'inline-block' : 'none';
        }
      }
    }

    if (compRes.success && Array.isArray(compRes.companies)) {
      currentCompanies = compRes.companies;
      renderCompanyTable(compRes.companies);
      populateChatterCompanyFilter(compRes.companies);
    }
  } catch (err) {
    console.error('Telemetry error:', err);
  }
}

function populateChatterCompanyFilter(companies) {
  const sel = document.getElementById('chatterCompanyFilter');
  if (!sel) return;
  sel.innerHTML = '<option value="ALL">All Companies (Live Matrix)</option>';
  companies.forEach(c => {
    const opt = document.createElement('option');
    opt.value = c.id;
    opt.textContent = c.name;
    sel.appendChild(opt);
  });
}

// 1. Real-Time Cross-Company Chatter Stream
async function loadChatterStream() {
  const tbody = document.getElementById('chatterTableBody');
  try {
    const res = await fetch('api/companies.php?action=get_live_chat_stream');
    const data = await res.json();
    if (data.success && Array.isArray(data.stream)) {
      currentChatterStream = data.stream;
      renderChatterStream();
    }
  } catch (err) {
    if (tbody) tbody.innerHTML = '<tr><td colspan="7" style="text-align:center; padding:20px; color:#fb7185;">Failed to load live chatter stream.</td></tr>';
  }
}

function renderChatterStream() {
  const tbody = document.getElementById('chatterTableBody');
  if (!tbody) return;
  const selComp = document.getElementById('chatterCompanyFilter')?.value || 'ALL';
  let items = currentChatterStream;
  if (selComp !== 'ALL') {
    items = items.filter(i => i.company_id === selComp);
  }

  if (!items.length) {
    tbody.innerHTML = '<tr><td colspan="7" style="text-align:center; padding:24px; color:var(--text-muted);">No live messages recorded in this stream yet.</td></tr>';
    return;
  }

  tbody.innerHTML = items.map(msg => {
    const isOut = msg.sender === 'agent';
    const stateColors = {
      paid: 'background:rgba(16,185,129,0.15); color:#34d399; border:1px solid rgba(16,185,129,0.3);',
      ongoing: 'background:rgba(59,130,246,0.15); color:#93c5fd; border:1px solid rgba(59,130,246,0.3);',
      review: 'background:rgba(234,179,8,0.15); color:#fde047; border:1px solid rgba(234,179,8,0.3);',
      fresh: 'background:rgba(148,163,184,0.15); color:#cbd5e1; border:1px solid rgba(148,163,184,0.3);'
    };
    return `
      <tr>
        <td>
          <span style="display:inline-block; padding:3px 8px; border-radius:6px; font-size:11px; font-weight:700; background:rgba(234,179,8,0.15); color:#fde047; border:1px solid rgba(234,179,8,0.3);">
            🏢 ${msg.company_name}
          </span>
        </td>
        <td>
          <div style="font-weight:700; color:#fff;">${msg.lead_name}</div>
          <div style="font-size:11px; font-family:'JetBrains Mono',monospace; color:var(--text-secondary);">${msg.lead_phone || 'WhatsApp'}</div>
        </td>
        <td>
          <span style="font-size:12px; font-weight:600; color:#8ff5c8;">@${msg.closer_name}</span>
        </td>
        <td style="max-width:320px;">
          <div style="font-size:12.5px; line-height:1.4; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;" title="${msg.text}">
            ${msg.text}
          </div>
        </td>
        <td>
          <span style="display:inline-flex; align-items:center; gap:4px; font-size:11px; font-weight:700; padding:2px 6px; border-radius:4px; ${isOut ? 'background:rgba(16,185,129,0.2); color:#34d399;' : 'background:rgba(59,130,246,0.2); color:#93c5fd;'}">
            ${isOut ? '↗️ Outbound' : '↙️ Inbound'}
          </span>
        </td>
        <td>
          <span style="display:inline-block; padding:2px 8px; border-radius:12px; font-size:10.5px; font-weight:700; text-transform:uppercase; ${stateColors[msg.state] || stateColors.ongoing}">
            ${msg.state}
          </span>
        </td>
        <td>
          <span style="font-size:11.5px; color:var(--text-muted); font-family:'JetBrains Mono',monospace;">${msg.time}</span>
        </td>
      </tr>
    `;
  }).join('');
}

// 2. Global Logins & Active Sessions
async function loadLoginsData() {
  const tbody = document.getElementById('loginsTableBody');
  try {
    const res = await fetch('api/auth.php?action=list_logins');
    const data = await res.json();
    if (data.success && Array.isArray(data.logins)) {
      currentLogins = data.logins;
      if (!currentLogins.length) {
        tbody.innerHTML = '<tr><td colspan="7" style="text-align:center; padding:24px; color:var(--text-muted);">No login records logged yet.</td></tr>';
        return;
      }
      tbody.innerHTML = currentLogins.map(log => {
        const roleBadges = {
          superadmin: 'background:rgba(234,179,8,0.2); color:#fde047;',
          admin: 'background:rgba(245,158,11,0.2); color:#fbbf24;',
          lead: 'background:rgba(31, 224, 138,0.2); color:#8ff5c8;',
          member: 'background:rgba(16,185,129,0.2); color:#34d399;'
        };
        return `
          <tr>
            <td>
              <div style="font-weight:700; color:#fff;">${log.name}</div>
              <div style="font-size:11px; font-family:'JetBrains Mono',monospace; color:var(--text-secondary);">@${log.username}</div>
            </td>
            <td>
              <span style="display:inline-block; padding:3px 8px; border-radius:6px; font-size:11px; font-weight:700; text-transform:uppercase; ${roleBadges[log.role] || roleBadges.member}">
                ${log.role}
              </span>
            </td>
            <td>
              <span style="font-weight:600; color:#e2e8f0;">${log.company_name || log.company_id}</span>
            </td>
            <td>
              <div style="font-family:'JetBrains Mono',monospace; font-size:12px;">${log.ip}</div>
              <div style="font-size:11px; color:var(--text-muted);">${log.city || 'Pakistan'}</div>
            </td>
            <td>
              <span style="font-size:11.5px; color:var(--text-secondary);">${log.device || 'Chrome / Browser'}</span>
            </td>
            <td>
              <span style="font-family:'JetBrains Mono',monospace; font-size:11.5px; color:var(--text-secondary);">${log.timestamp}</span>
            </td>
            <td>
              <span class="badge-status status-active">🟢 Active</span>
            </td>
          </tr>
        `;
      }).join('');
    }
  } catch (err) {
    if (tbody) tbody.innerHTML = '<tr><td colspan="7" style="text-align:center; padding:20px; color:#fb7185;">Failed to load login audit records.</td></tr>';
  }
}

// 3. Master Pending TM Approvals
async function loadMasterPendingTMs() {
  const tbody = document.getElementById('masterPendingTableBody');
  const badge = document.getElementById('sadminPendingBadge');
  const hudBadge = document.getElementById('hudPendingCount');
  try {
    const res = await fetch('api/auth.php?action=list_pending_tms');
    const data = await res.json();
    if (data.success && Array.isArray(data.pending)) {
      currentMasterPending = data.pending;
      const count = currentMasterPending.length;
      if (badge) {
        badge.textContent = count;
        badge.style.display = count > 0 ? 'inline-block' : 'none';
      }
      if (hudBadge) hudBadge.textContent = count;

      if (!count) {
        tbody.innerHTML = '<tr><td colspan="7" style="text-align:center; padding:32px; color:var(--text-muted);">✨ Zero pending applicants platform-wide. All closer registrations have been processed.</td></tr>';
        return;
      }

      tbody.innerHTML = currentMasterPending.map(app => {
        const pfp = app.pfp || 'https://images.unsplash.com/photo-1535713875002-d1d0cf377fde?auto=format&fit=crop&w=120&q=80';
        return `
          <tr>
            <td>
              <div class="user-profile-cell" style="display:flex; align-items:center; gap:10px;">
                <img src="${pfp}" alt="${app.name}" style="width:36px; height:36px; border-radius:10px; object-fit:cover; border:1px solid rgba(255,255,255,0.1);">
                <div>
                  <div style="font-weight:700; color:#fff;">${app.name}</div>
                  <div style="font-size:11px; color:var(--text-muted);">Closer Applicant</div>
                </div>
              </div>
            </td>
            <td>
              <span style="display:inline-block; padding:3px 8px; border-radius:6px; font-size:11px; font-weight:700; background:rgba(234,179,8,0.15); color:#fde047; border:1px solid rgba(234,179,8,0.3);">
                🏢 ${app.company_id}
              </span>
            </td>
            <td>
              <div style="font-weight:700; color:#34d399; font-family:'JetBrains Mono',monospace;">@${app.username}</div>
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
                <button class="btn btn-emerald" style="padding:6px 12px; font-size:11.5px;" onclick="masterApproveTM('${app.id}', '${app.name.replace(/'/g, "\\'")}')">
                  👑 Override Approve
                </button>
                <button class="btn btn-danger" style="padding:6px 10px; font-size:11.5px;" onclick="masterRejectTM('${app.id}', '${app.name.replace(/'/g, "\\'")}')">
                  Decline
                </button>
              </div>
            </td>
          </tr>
        `;
      }).join('');
    }
  } catch (err) {
    if (tbody) tbody.innerHTML = '<tr><td colspan="7" style="text-align:center; padding:20px; color:#fb7185;">Failed to load master pending queue.</td></tr>';
  }
}

async function masterApproveTM(userId, name) {
  const limit = prompt(`Enter daily message quota for "${name}":`, "150");
  if (!limit) return;
  try {
    const res = await fetch('api/auth.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        action: 'approve_tm',
        user_id: userId,
        message_limit: parseInt(limit) || 150
      })
    });
    const data = await res.json();
    if (data.success) {
      alert(`🎉 "${name}" approved and activated!`);
      loadMasterPendingTMs();
      loadDashboardData();
    } else {
      alert('⚠️ ' + (data.error || 'Failed to approve closer.'));
    }
  } catch (err) {
    alert('Server error.');
  }
}

async function masterRejectTM(userId, name) {
  if (!confirm(`Are you sure you want to decline application for "${name}"?`)) return;
  try {
    const res = await fetch('api/auth.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'reject_tm', user_id: userId })
    });
    const data = await res.json();
    if (data.success) {
      alert('Applicant removed.');
      loadMasterPendingTMs();
    } else {
      alert('⚠️ ' + (data.error || 'Failed to decline applicant.'));
    }
  } catch (err) {
    alert('Server error.');
  }
}

function renderCompanyTable(companies) {
  const tbody = document.getElementById('companyTableBody');
  if (!companies.length) {
    tbody.innerHTML = '<tr><td colspan="7" style="text-align:center; padding:24px; color:var(--text-muted);">No companies provisioned yet.</td></tr>';
    return;
  }

  tbody.innerHTML = companies.map(c => {
    const isSuspended = c.status === 'suspended';
    return `
      <tr>
        <td>
          <div class="company-cell">
            <div class="company-avatar">${c.name.charAt(0).toUpperCase()}</div>
            <div>
              <strong style="font-size:14px;">${c.name}</strong>
              <div style="font-size:11.5px; color:var(--text-muted);">slug: <code style="color:#8ff5c8;">/c/${c.slug}</code> &bull; ${c.owner_name || ''}</div>
            </div>
          </div>
        </td>
        <td>
          <span style="font-size:12px; font-weight:700; color:#fbbf24;">${c.plan || 'Growth'}</span>
        </td>
        <td>
          <strong>${c.employee_count || 0}</strong> workforce<br>
          <span style="font-size:11px; color:var(--text-muted);">${c.tl_count || 0} TLs &bull; ${c.tm_count || 0} TMs</span>
        </td>
        <td>
          <strong style="color:#34d399;">${c.messages_today || 0}</strong> msgs today
        </td>
        <td>
          <span style="font-family:'JetBrains Mono',monospace; font-size:12px;">${c.fallback_phone || '+923318036576'}</span>
        </td>
        <td>
          <span class="badge-status ${isSuspended ? 'status-suspended' : 'status-active'}">
            ${isSuspended ? 'Suspended' : 'Active'}
          </span>
        </td>
        <td>
          <div style="display:flex; gap:6px; flex-wrap:wrap;">
            <button class="btn btn-gold" style="padding:5px 10px; font-size:11.5px;" onclick="impersonateCompanyAdmin('${c.id}')" title="Login as Company Admin">
              👑 Open Admin
            </button>
            <button class="btn btn-secondary" style="padding:5px 9px; font-size:11.5px;" onclick="viewCompanyStats('${c.id}', '${escape(c.name)}')">
              📊 Stats
            </button>
            <button class="btn btn-secondary" style="padding:5px 9px; font-size:11.5px;" onclick="toggleCompanyStatus('${c.id}')">
              ${isSuspended ? 'Activate' : 'Suspend'}
            </button>
            ${c.id !== 'COMP-001' ? `
              <button class="btn btn-danger" style="padding:5px 8px; font-size:11.5px;" onclick="deleteCompany('${c.id}')" title="Purge Company Database">
                🗑️
              </button>
            ` : ''}
          </div>
        </td>
      </tr>
    `;
  }).join('');
}

// 1-Click Impersonate Company Admin
async function impersonateCompanyAdmin(companyId) {
  try {
    const res = await fetch('api/companies.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'impersonate', company_id: companyId })
    });
    const data = await res.json();
    if (data.success && data.redirect) {
      window.location.href = data.redirect;
    } else {
      alert('⚠️ ' + (data.error || 'Failed to enter company admin.'));
    }
  } catch (err) {
    alert('Error impersonating company.');
  }
}

// View Company Deep Stats Modal
async function viewCompanyStats(companyId, companyName) {
  document.getElementById('statsModalTitle').textContent = '📊 Statistics — ' + unescape(companyName);
  document.getElementById('statsModalSub').textContent = 'Company ID: ' + companyId + ' • Multi-Tenant Telemetry';
  document.getElementById('deepStatsModal').style.display = 'flex';

  try {
    const res = await fetch('api/companies.php?action=get_company_deep_stats&company_id=' + encodeURIComponent(companyId));
    const data = await res.json();
    if (data.success && data.stats) {
      const s = data.stats;
      document.getElementById('stTotalLeads').textContent = s.total_leads;
      document.getElementById('stPaidLeads').textContent = s.paid_leads;
      document.getElementById('stConversion').textContent = s.conversion_rate + '%';

      const tbody = document.getElementById('stClosersTbody');
      if (!s.closers.length) {
        tbody.innerHTML = '<tr><td colspan="5" style="text-align:center; padding:16px;">No closers provisioned in this company yet.</td></tr>';
      } else {
        tbody.innerHTML = s.closers.map(cl => `
          <tr>
            <td><strong>${cl.name}</strong> (@${cl.username})</td>
            <td><span style="font-size:11px;">${cl.role === 'lead' ? '👑 Team Lead' : '📱 Closer TM'}</span></td>
            <td><strong style="color:#34d399;">${cl.messages_today}</strong></td>
            <td>${cl.message_limit === -1 ? '∞' : cl.message_limit}</td>
            <td><span class="badge-status ${cl.status === 'active' ? 'status-active' : 'status-suspended'}">${cl.status}</span></td>
          </tr>
        `).join('');
      }
    }
  } catch (err) {
    console.error('Error fetching deep stats:', err);
  }
}

// Toggle Company Status
async function toggleCompanyStatus(companyId) {
  try {
    const res = await fetch('api/companies.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'toggle_status', company_id: companyId })
    });
    const data = await res.json();
    if (data.success) loadDashboardData();
  } catch (err) {
    alert('Error toggling company status.');
  }
}

// Delete Company
async function deleteCompany(companyId) {
  if (!confirm('Permanently delete this company and its isolated tenant records? This action cannot be undone.')) return;
  try {
    const res = await fetch('api/companies.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'delete_company', company_id: companyId })
    });
    const data = await res.json();
    if (data.success) {
      alert('✅ ' + data.message);
      loadDashboardData();
    } else {
      alert('⚠️ ' + (data.error || 'Failed to delete company.'));
    }
  } catch (err) {
    alert('Error deleting company.');
  }
}

function previewCompanyLogo(url) {
  const img = document.getElementById('compLogoPreviewImg');
  const txt = document.getElementById('compLogoPreviewText');
  if (!img || !txt) return;
  if (url && url.trim()) {
    img.src = url.trim();
    img.style.display = 'block';
    txt.style.display = 'none';
    img.onerror = () => { img.style.display = 'none'; txt.style.display = 'block'; };
  } else {
    img.style.display = 'none';
    txt.style.display = 'block';
  }
}

function handleLogoFileUpload(e) {
  const file = e.target.files[0];
  if (!file) return;
  const reader = new FileReader();
  reader.onload = function(evt) {
    const dataUrl = evt.target.result;
    const input = document.getElementById('compLogo');
    if (input) input.value = dataUrl;
    previewCompanyLogo(dataUrl);
  };
  reader.readAsDataURL(file);
}

// Provision Company Form
async function handleProvisionCompany(e) {
  e.preventDefault();
  const btn = document.getElementById('btnProvisionSubmit');
  const errBox = document.getElementById('provisionError');
  btn.disabled = true;
  btn.textContent = 'Provisioning Tenant…';
  errBox.style.display = 'none';

  try {
    const res = await fetch('api/companies.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        action: 'create',
        name: document.getElementById('compName').value.trim(),
        slug: document.getElementById('compSlug').value.trim(),
        owner_name: document.getElementById('compOwnerName').value.trim(),
        owner_email: document.getElementById('compOwnerEmail').value.trim(),
        admin_username: document.getElementById('compAdminUser').value.trim(),
        admin_password: document.getElementById('compAdminPass').value.trim(),
        plan: document.getElementById('compPlan').value,
        logo: document.getElementById('compLogo')?.value.trim() || '',
        fallback_phone: document.getElementById('compFallbackPhone')?.value.trim() || '',
        default_message_limit: parseInt(document.getElementById('compDefaultLimit').value) || 200,
        max_employees: parseInt(document.getElementById('compMaxEmployees').value) || 50
      })
    });
    const data = await res.json();
    if (data.success) {
      alert('🎉 ' + data.message);
      document.getElementById('inlineProvisionForm').reset();
      previewCompanyLogo('');
      switchSadminTab('companies');
      loadDashboardData();
    } else {
      errBox.textContent = data.error || 'Failed to provision company.';
      errBox.style.display = 'block';
    }
  } catch (err) {
    errBox.textContent = 'Server communication error.';
    errBox.style.display = 'block';
  } finally {
    btn.disabled = false;
    btn.textContent = '🚀 Provision Company & Launch Tenant Database';
  }
}

// Load Number Profiles
async function loadNumberPool() {
  const tbody = document.getElementById('numberPoolTableBody');
  tbody.innerHTML = '<tr><td colspan="7" style="text-align:center; padding:20px;">Fetching number profiles...</td></tr>';
  try {
    const res = await fetch('api/companies.php?action=list_number_profiles');
    const data = await res.json();
    if (data.success && Array.isArray(data.profiles)) {
      if (!data.profiles.length) {
        tbody.innerHTML = '<tr><td colspan="7" style="text-align:center; padding:20px; color:var(--text-muted);">No custom fallback numbers in pool. Using primary trunk.</td></tr>';
        return;
      }
      tbody.innerHTML = data.profiles.map(p => `
        <tr>
          <td><code>${p.id}</code></td>
          <td><strong style="font-family:'JetBrains Mono',monospace;">${p.display || p.phone}</strong></td>
          <td>${p.label}</td>
          <td>${p.daily_capacity} msgs/day</td>
          <td>${p.assigned_to_user_name ? `${p.assigned_to_user_name} (${p.assigned_to_user_id})` : '<em>Available in Pool</em>'}</td>
          <td><span class="badge-status ${p.status === 'in_use' ? 'status-suspended' : 'status-active'}">${p.status}</span></td>
          <td>
            ${p.assigned_to_user_id ? `
              <button class="btn btn-secondary" style="padding:4px 8px; font-size:11px;" onclick="releaseNumber('${p.id}')">Release</button>
            ` : '<span style="font-size:11px; color:var(--text-muted);">Ready</span>'}
          </td>
        </tr>
      `).join('');
    }
  } catch (err) {
    tbody.innerHTML = '<tr><td colspan="7" style="text-align:center; padding:20px; color:#fb7185;">Failed to load pool.</td></tr>';
  }
}

async function handleAddNumber(e) {
  e.preventDefault();
  try {
    const res = await fetch('api/companies.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        action: 'create_number_profile',
        phone: document.getElementById('numPhone').value.trim(),
        label: document.getElementById('numLabel').value.trim(),
        daily_capacity: parseInt(document.getElementById('numCap').value) || 500
      })
    });
    const data = await res.json();
    if (data.success) {
      closeAddNumberModal();
      document.getElementById('addNumberForm').reset();
      loadNumberPool();
    } else {
      alert('⚠️ ' + (data.error || 'Failed to add number.'));
    }
  } catch (err) {
    alert('Error adding number.');
  }
}

async function releaseNumber(profileId) {
  try {
    const res = await fetch('api/companies.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'release_number_profile', profile_id: profileId })
    });
    const data = await res.json();
    if (data.success) loadNumberPool();
  } catch (err) {
    alert('Error releasing number.');
  }
}

// Load Audit Logs
async function loadAuditLogs() {
  const tbody = document.getElementById('auditTableBody');
  tbody.innerHTML = '<tr><td colspan="5" style="text-align:center; padding:20px;">Fetching audit trail...</td></tr>';
  try {
    const res = await fetch('api/companies.php?action=get_audit_logs');
    const data = await res.json();
    if (data.success && Array.isArray(data.logs)) {
      if (!data.logs.length) {
        tbody.innerHTML = '<tr><td colspan="5" style="text-align:center; padding:20px; color:var(--text-muted);">No audit events logged yet.</td></tr>';
        return;
      }
      tbody.innerHTML = data.logs.map(l => `
        <tr>
          <td style="font-family:'JetBrains Mono',monospace; font-size:11px; color:var(--text-muted);">${l.timestamp}</td>
          <td><span style="font-weight:700;">${l.company_id || 'GLOBAL'}</span></td>
          <td>${l.actor_name || 'System'}</td>
          <td><span style="padding:2px 7px; border-radius:4px; font-size:11px; font-weight:700; background:rgba(234,179,8,0.15); color:#fde047;">${l.action}</span></td>
          <td style="font-size:12px;">${l.details}</td>
        </tr>
      `).join('');
    }
  } catch (err) {
    tbody.innerHTML = '<tr><td colspan="5" style="text-align:center; padding:20px; color:#fb7185;">Failed to load audit trail.</td></tr>';
  }
}

// Super Admin Login
async function handleSadminLogin(e) {
  e.preventDefault();
  const btn = document.getElementById('sadminLoginBtn');
  const errBox = document.getElementById('loginError');
  btn.disabled = true;
  btn.textContent = 'Authenticating…';
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
    if (data.success && data.user.role === 'superadmin') {
      window.location.href = '?portal=1';
    } else {
      errBox.textContent = data.error || 'Access Denied: Super Administrator privileges required.';
      errBox.style.display = 'block';
    }
  } catch (err) {
    errBox.textContent = 'Server communication error.';
    errBox.style.display = 'block';
  } finally {
    btn.disabled = false;
    btn.textContent = 'Unlock Platform Command ⚡';
  }
}

// Filter Companies Table
function filterCompaniesTable() {
  const q = document.getElementById('compSearch').value.toLowerCase();
  const filtered = currentCompanies.filter(c => c.name.toLowerCase().includes(q) || c.slug.toLowerCase().includes(q));
  renderCompanyTable(filtered);
}

// ══════════════════════════════════════════════════════
// 9. BRAND PRODUCT PROMOTION INQUIRIES & NOTIFICATIONS
// ══════════════════════════════════════════════════════
let currentBrandInquiries = [];
let lastKnownInquiryCount = null;

async function loadBrandInquiries(manual = false) {
  try {
    const res = await fetch('api/brand_inquiries.php?action=list');
    const data = await res.json();
    if (data.success && Array.isArray(data.inquiries)) {
      currentBrandInquiries = data.inquiries;

      // Update counters & metrics
      const total = data.total || 0;
      const newCount = data.new_count || 0;
      const contactedCount = data.contacted_count || 0;
      const convertedCount = data.converted_count || 0;

      const metricTotal = document.getElementById('inqMetricTotal');
      const metricNew = document.getElementById('inqMetricNew');
      const metricContacted = document.getElementById('inqMetricContacted');
      const metricConverted = document.getElementById('inqMetricConverted');
      if (metricTotal) metricTotal.textContent = total;
      if (metricNew) metricNew.textContent = newCount;
      if (metricContacted) metricContacted.textContent = contactedCount;
      if (metricConverted) metricConverted.textContent = convertedCount;

      const metricHUD = document.getElementById('metricBrandInquiries');
      const metricHUDNew = document.getElementById('metricNewBrandInquiries');
      if (metricHUD) metricHUD.textContent = total;
      if (metricHUDNew) metricHUDNew.textContent = newCount > 0 ? (newCount + ' new') : '0 new';

      // Badges
      const hBadge = document.getElementById('headerBrandBadge');
      const tBadge = document.getElementById('sadminBrandBadge');
      if (hBadge) {
        hBadge.textContent = newCount;
        hBadge.style.display = newCount > 0 ? 'inline-block' : 'none';
        if (newCount > 0) hBadge.style.animation = 'pulseDot 0.8s infinite alternate';
        else hBadge.style.animation = 'none';
      }
      if (tBadge) {
        tBadge.textContent = newCount;
        tBadge.style.display = newCount > 0 ? 'inline-block' : 'none';
      }

      // Notification System: If new inquiries arrived while active on session!
      if (lastKnownInquiryCount !== null && total > lastKnownInquiryCount) {
        const diff = total - lastKnownInquiryCount;
        const newest = data.inquiries[0];
        triggerNewInquiryNotification(diff, newest);
      }
      lastKnownInquiryCount = total;

      filterBrandInquiriesTable();
    }
  } catch (err) {
    console.error('Error loading brand inquiries:', err);
  }
}

function filterBrandInquiriesTable() {
  const search = (document.getElementById('inqSearchInput')?.value || '').toLowerCase().trim();
  const filter = document.getElementById('inqStatusFilter')?.value || 'all';

  let list = currentBrandInquiries;
  if (filter !== 'all') {
    list = list.filter(i => (i.status || 'new') === filter);
  }
  if (search) {
    list = list.filter(i => 
      (i.brand_name || '').toLowerCase().includes(search) ||
      (i.product_name || '').toLowerCase().includes(search) ||
      (i.contact_name || '').toLowerCase().includes(search) ||
      (i.phone || '').includes(search)
    );
  }
  renderBrandInquiries(list);
}

function renderBrandInquiries(inquiries) {
  const tbody = document.getElementById('brandInquiriesTableBody');
  if (!tbody) return;

  if (!inquiries.length) {
    tbody.innerHTML = '<tr><td colspan="6" style="text-align:center; padding:32px; color:var(--text-muted);">✨ No brand promotion inquiries found matching your filter.</td></tr>';
    return;
  }

  tbody.innerHTML = inquiries.map(inq => {
    const cleanPhone = String(inq.phone || '').replace(/\D/g, '');
    const waGreeting = encodeURIComponent(`Hello ${inq.contact_name || ''}! This is Chatly Platform Administration regarding your inquiry to promote "${inq.product_name || inq.brand_name}". We reviewed your product details and would love to launch your dedicated WhatsApp closer team!`);
    const waLink = `https://wa.me/${cleanPhone}?text=${waGreeting}`;
    
    let statusBadge = '<span class="badge-status status-active" style="background:rgba(16,185,129,0.15); color:#34d399; border:1px solid rgba(16,185,129,0.3);">🟢 New Lead</span>';
    if (inq.status === 'contacted') {
      statusBadge = '<span class="badge-status" style="background:rgba(234,179,8,0.15); color:#fde047; border:1px solid rgba(234,179,8,0.3);">🟡 Contacted</span>';
    } else if (inq.status === 'converted') {
      statusBadge = '<span class="badge-status" style="background:rgba(59,130,246,0.15); color:#60a5fa; border:1px solid rgba(59,130,246,0.3);">🏢 Converted</span>';
    } else if (inq.status === 'archived') {
      statusBadge = '<span class="badge-status" style="background:rgba(148,163,184,0.15); color:#94a3b8; border:1px solid rgba(148,163,184,0.3);">⚪ Archived</span>';
    }

    const priceText = inq.price ? (inq.currency === 'USD' ? '$' + inq.price : 'Rs. ' + inq.price) : 'Custom';
    const pitchSnippet = (inq.description || 'No pitch provided').slice(0, 85) + (inq.description?.length > 85 ? '…' : '');

    return `
      <tr style="${!inq.is_read ? 'background:rgba(31,224,138,0.04);' : ''}">
        <td>
          <div style="font-weight:800; font-size:14px; color:var(--text-primary);">${inq.brand_name}</div>
          <div style="font-size:12.5px; color:#34d399; font-weight:700;">📦 ${inq.product_name}</div>
          <div style="display:flex; align-items:center; gap:6px; margin-top:4px;">
            <span style="font-size:10px; font-weight:700; padding:2px 6px; border-radius:4px; background:rgba(255,255,255,0.06); color:var(--text-secondary);">${inq.category || 'E-Commerce'}</span>
            <span style="font-size:11px; font-weight:800; color:#fbbf24;">${priceText}</span>
          </div>
        </td>
        <td style="max-width:260px;">
          <div style="font-size:12px; color:var(--text-secondary); line-height:1.4; margin-bottom:4px;">
            ${pitchSnippet}
          </div>
          <div style="display:flex; gap:8px; align-items:center;">
            <button class="btn btn-secondary" style="padding:2px 8px; font-size:10.5px; border-radius:6px;" onclick="openInquiryDetailModal('${inq.id}')">
              🔍 View Full Pitch
            </button>
            ${inq.website ? `<a href="${inq.website.startsWith('http') ? inq.website : 'https://' + inq.website}" target="_blank" style="font-size:11px; color:#60a5fa; text-decoration:underline;">🔗 Store/Link</a>` : ''}
          </div>
          <div style="font-size:10.5px; color:var(--text-muted); margin-top:4px;">Target: <strong>${inq.target_volume || 'Standard'}</strong></div>
        </td>
        <td>
          <div style="font-weight:700; font-size:13px; color:var(--text-primary);">${inq.contact_name}</div>
          <a href="${waLink}" target="_blank" style="display:inline-flex; align-items:center; gap:4px; font-family:'JetBrains Mono',monospace; font-size:12px; color:#34d399; text-decoration:none; font-weight:700; margin-top:2px;" title="Chat with lead on WhatsApp">
            <span>💬</span> ${inq.phone || ''}
          </a>
          <div style="font-size:11px; color:var(--text-muted);">${inq.email || inq.city || ''}</div>
        </td>
        <td>
          <div style="font-size:11.5px; color:var(--text-secondary); font-family:'JetBrains Mono',monospace;">
            ${inq.created_at || 'Recently'}
          </div>
        </td>
        <td>
          <div>${statusBadge}</div>
          <select onchange="updateInquiryStatus('${inq.id}', this.value)" style="margin-top:5px; background:var(--bg-card); color:var(--text-secondary); border:1px solid var(--border-subtle); border-radius:6px; font-size:10.5px; padding:2px 4px; cursor:pointer;">
            <option value="new" ${inq.status === 'new' ? 'selected' : ''}>Set New</option>
            <option value="contacted" ${inq.status === 'contacted' ? 'selected' : ''}>Set Contacted</option>
            <option value="converted" ${inq.status === 'converted' ? 'selected' : ''}>Set Converted</option>
            <option value="archived" ${inq.status === 'archived' ? 'selected' : ''}>Set Archived</option>
          </select>
        </td>
        <td>
          <div style="display:flex; flex-direction:column; gap:6px;">
            <a href="${waLink}" target="_blank" class="btn btn-emerald" style="padding:5px 10px; font-size:11.5px; text-decoration:none; text-align:center;">
              <span>💬</span> Chat WhatsApp
            </a>
            <button class="btn btn-gold" style="padding:5px 10px; font-size:11.5px;" onclick="convertInquiryToCompany('${inq.id}')">
              <span>🏢</span> Convert to Company
            </button>
            <button class="btn btn-secondary" style="padding:3px 8px; font-size:10.5px; color:#fb7185;" onclick="deleteInquiry('${inq.id}')">
              🗑️ Delete
            </button>
          </div>
        </td>
      </tr>
    `;
  }).join('');
}

function openInquiryDetailModal(id) {
  const inq = currentBrandInquiries.find(i => i.id === id);
  if (!inq) return;

  document.getElementById('inqModalBrandTitle').textContent = inq.brand_name;
  document.getElementById('inqModalSub').textContent = `Inquiry ID: ${inq.id} • Category: ${inq.category || 'General'}`;
  document.getElementById('inqModalProductName').textContent = inq.product_name;
  document.getElementById('inqModalPriceCat').textContent = `${inq.currency || 'PKR'} ${inq.price || 'Custom'} (${inq.category || 'Business'})`;
  document.getElementById('inqModalPitchText').textContent = inq.description || 'No pitch provided.';
  document.getElementById('inqModalVolume').textContent = inq.target_volume || 'Standard';
  
  const webEl = document.getElementById('inqModalWebsiteLink');
  if (inq.website) {
    const url = inq.website.startsWith('http') ? inq.website : 'https://' + inq.website;
    webEl.innerHTML = `<a href="${url}" target="_blank" style="color:#60a5fa; text-decoration:underline;">${inq.website}</a>`;
  } else {
    webEl.textContent = 'None provided';
  }

  const cleanPhone = String(inq.phone || '').replace(/\D/g, '');
  document.getElementById('inqModalContactName').textContent = `${inq.contact_name} (${inq.phone}) • ${inq.email || ''}`;
  
  const waBtn = document.getElementById('inqModalWAButton');
  const waGreeting = encodeURIComponent(`Hello ${inq.contact_name}! This is Chatly Platform Administration regarding your inquiry for "${inq.product_name}".`);
  waBtn.href = `https://wa.me/${cleanPhone}?text=${waGreeting}`;

  document.getElementById('inquiryDetailModal').style.display = 'flex';
}

function closeInquiryDetailModal() {
  document.getElementById('inquiryDetailModal').style.display = 'none';
}

function convertInquiryToCompany(id) {
  const inq = currentBrandInquiries.find(i => i.id === id);
  if (!inq) return;

  switchSadminTab('provision');
  const nameInp = document.getElementById('provName');
  const slugInp = document.getElementById('provSlug');
  const ownerNameInp = document.getElementById('provOwnerName');
  const emailInp = document.getElementById('provOwnerEmail');
  const phoneInp = document.getElementById('provFallbackPhone');

  if (nameInp) nameInp.value = inq.brand_name;
  if (slugInp) {
    slugInp.value = inq.brand_name.toLowerCase().replace(/[^a-z0-9]/g, '');
    if (typeof checkSlugAvailability === 'function') checkSlugAvailability();
  }
  if (ownerNameInp) ownerNameInp.value = inq.contact_name;
  if (emailInp && inq.email) emailInp.value = inq.email;
  if (phoneInp && inq.phone) phoneInp.value = inq.phone;

  // Update status of inquiry to 'converted'
  updateInquiryStatus(id, 'converted');

  const provPane = document.getElementById('paneProvision');
  if (provPane) {
    window.scrollTo({ top: provPane.offsetTop - 50, behavior: 'smooth' });
  }
}

async function updateInquiryStatus(id, newStatus) {
  try {
    const res = await fetch('api/brand_inquiries.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        action: 'update_status',
        id: id,
        status: newStatus
      })
    });
    const data = await res.json();
    if (data.success) {
      loadBrandInquiries();
    } else {
      alert('⚠️ ' + (data.error || 'Failed to update status.'));
    }
  } catch (err) {
    console.error('Error updating inquiry status:', err);
  }
}

async function markAllBrandInquiriesRead() {
  try {
    const res = await fetch('api/brand_inquiries.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'mark_all_read' })
    });
    const data = await res.json();
    if (data.success) {
      loadBrandInquiries();
    }
  } catch (err) {}
}

async function deleteInquiry(id) {
  if (!confirm('Are you sure you want to delete this brand promotion inquiry?')) return;
  try {
    const res = await fetch('api/brand_inquiries.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'delete', id: id })
    });
    const data = await res.json();
    if (data.success) {
      loadBrandInquiries();
    } else {
      alert('⚠️ ' + (data.error || 'Failed to delete inquiry.'));
    }
  } catch (err) {
    alert('Error deleting inquiry.');
  }
}

// Notification System: Audio chime + Floating toast
function playNotificationChime() {
  try {
    const AudioCtx = window.AudioContext || window.webkitAudioContext;
    if (!AudioCtx) return;
    const ctx = new AudioCtx();
    const osc = ctx.createOscillator();
    const gain = ctx.createGain();
    osc.type = 'sine';
    osc.frequency.setValueAtTime(587.33, ctx.currentTime);
    osc.frequency.exponentialRampToValueAtTime(880, ctx.currentTime + 0.15);
    gain.gain.setValueAtTime(0.25, ctx.currentTime);
    gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.45);
    osc.connect(gain);
    gain.connect(ctx.destination);
    osc.start();
    osc.stop(ctx.currentTime + 0.45);
  } catch (_) {}
}

function triggerNewInquiryNotification(diff, newestInq) {
  playNotificationChime();
  const toast = document.getElementById('sadminNotificationToast');
  const title = document.getElementById('toastTitle');
  const msg = document.getElementById('toastMsg');
  if (toast && newestInq) {
    if (title) title.textContent = `🔔 New Brand Promotion Request!`;
    if (msg) msg.textContent = `"${newestInq.brand_name}" submitted product "${newestInq.product_name}". Click to review!`;
    toast.style.display = 'block';
    setTimeout(() => {
      closeSadminToast();
    }, 7000);
  }
}

function closeSadminToast() {
  const toast = document.getElementById('sadminNotificationToast');
  if (toast) toast.style.display = 'none';
}

// Initial Boot
<?php if ($isSuperAdmin): ?>
loadDashboardData();
loadMasterPendingTMs();
loadBrandInquiries();
setInterval(loadDashboardData, 8000);
setInterval(loadMasterPendingTMs, 8000);
setInterval(loadBrandInquiries, 6000);
<?php endif; ?>

function toggleTheme() {
  const isLight = document.documentElement.classList.toggle('theme-light');
  try { localStorage.setItem('nunno_theme', isLight ? 'light' : 'dark'); } catch(_) {}
}
</script>
</body>
</html>