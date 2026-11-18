<?php
/**
 * OmniDesk 2026 — WhatsApp Web Style Landing Page & Multi-Role Gateway
 * 
 * 4 Complete Authentication Journeys:
 * 1. TM (Team Member / Sales Closer) — Authentic WhatsApp Web QR Code Scan
 * 2. TL (Team Leader) — Commission-Based Login & Quick Signup (Rs. 500 / TM)
 * 3. Company Admin — Enterprise Management Portal (/admin)
 * 4. Super Admin — Platform Owner Command Center (/sadmin)
 */
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 60 * 60 * 24 * 30,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

// If already logged in and not switching accounts, route directly to workspace
if (!empty($_SESSION['omnidesk_user']) && !isset($_GET['switch'])) {
    $role = $_SESSION['omnidesk_user']['role'] ?? '';
    if ($role === 'superadmin' && isset($_GET['to_sadmin'])) {
        header('Location: sadmin.php');
        exit;
    }
    if ($role === 'admin' && isset($_GET['to_admin'])) {
        header('Location: admin.php');
        exit;
    }
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
  <title>Chatly — WhatsApp Closing Platform &amp; Multi-Role Gateway</title>
  <link rel="icon" type="image/jpeg" href="assets/images/chatly-icon.jpg">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Segoe+UI:wght@300;400;500;600;700&family=Inter:wght@400;500;600;700;800;900&family=JetBrains+Mono:wght@500;700&display=swap" rel="stylesheet">
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
      --wa-green-header: #00a884;
      --wa-teal: #128c7e;
      --wa-bg: #111b21;
      --wa-card-bg: #222e35;
      --wa-card-border: rgba(255, 255, 255, 0.08);
      --wa-text-primary: #e9edef;
      --wa-text-secondary: #8696a0;
      --wa-text-muted: #667781;
      --wa-accent: #00a884;
      --wa-qr-border: #00a884;
      --tl-purple: #8b5cf6;
      --admin-amber: #f59e0b;
      --sadmin-gold: #eab308;
      --anim-overlay-bg: #111b21;
    }

    html.theme-light {
      --wa-green-header: #00a884;
      --wa-teal: #128c7e;
      --wa-bg: #eae6df;
      --wa-card-bg: #ffffff;
      --wa-card-border: rgba(0, 0, 0, 0.06);
      --wa-text-primary: #41525d;
      --wa-text-secondary: #3b4a54;
      --wa-text-muted: #8696a0;
      --anim-overlay-bg: #eae6df;
    }

    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      font-family: 'Segoe UI', 'Inter', -apple-system, sans-serif;
      background-color: var(--wa-bg);
      color: var(--wa-text-primary);
      min-height: 100vh;
      display: flex;
      flex-direction: column;
      position: relative;
      overflow-x: hidden;
      -webkit-font-smoothing: antialiased;
    }

    /* WhatsApp Web Iconic Top Header Banner */
    .wa-top-header {
      width: 100%;
      height: 222px;
      background-color: var(--wa-green-header);
      position: absolute;
      top: 0; left: 0; right: 0;
      z-index: 0;
      padding: 26px 40px;
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
    }
    .wa-brand-title {
      display: flex;
      align-items: center;
      gap: 14px;
      color: #ffffff;
      text-decoration: none;
    }
    .wa-logo-icon {
      width: 38px;
      height: 38px;
      border-radius: 50%;
      background: #ffffff;
      display: flex;
      align-items: center;
      justify-content: center;
      box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    }
    .wa-logo-icon svg { width: 24px; height: 24px; fill: #00a884; }
    .wa-brand-name {
      font-size: 15px;
      font-weight: 700;
      letter-spacing: 0.05em;
      text-transform: uppercase;
    }
    .wa-brand-tag {
      font-size: 10px;
      padding: 2px 7px;
      background: rgba(255,255,255,0.22);
      border-radius: 4px;
      font-weight: 700;
      margin-left: 6px;
    }

    /* Main Floating Container */
    .landing-wrapper {
      position: relative;
      z-index: 1;
      width: 100%;
      max-width: 1060px;
      margin: 80px auto 40px;
      padding: 0 16px;
    }

    /* Floating WhatsApp Web Card */
    .wa-main-card {
      background-color: var(--wa-card-bg);
      border-radius: 14px;
      box-shadow: 0 18px 50px rgba(0, 0, 0, 0.35);
      border: 1px solid var(--wa-card-border);
      overflow: hidden;
      display: flex;
      flex-direction: column;
      animation: cardFadeIn 0.3s ease-out;
    }
    @keyframes cardFadeIn {
      from { opacity: 0; transform: translateY(12px); }
      to { opacity: 1; transform: translateY(0); }
    }

    /* 4-Role Navigation Bar */
    .role-nav-bar {
      display: flex;
      background: rgba(0, 0, 0, 0.15);
      border-bottom: 1px solid var(--wa-card-border);
      overflow-x: auto;
    }
    html.theme-light .role-nav-bar {
      background: #f0f2f5;
    }
    .role-tab-btn {
      flex: 1;
      min-width: 180px;
      padding: 16px 14px;
      font-size: 13px;
      font-weight: 700;
      background: none;
      border: none;
      color: var(--wa-text-secondary);
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      transition: all 0.2s ease;
      border-bottom: 3px solid transparent;
    }
    .role-tab-btn:hover {
      color: var(--wa-text-primary);
      background: rgba(255, 255, 255, 0.03);
    }
    .role-tab-btn.active {
      color: var(--wa-accent);
      background: var(--wa-card-bg);
      border-bottom-color: var(--wa-accent);
    }
    .role-tab-btn.tab-tl.active {
      color: var(--tl-purple);
      border-bottom-color: var(--tl-purple);
    }
    .role-tab-btn.tab-admin.active {
      color: var(--admin-amber);
      border-bottom-color: var(--admin-amber);
    }
    .role-tab-btn.tab-sadmin.active {
      color: var(--sadmin-gold);
      border-bottom-color: var(--sadmin-gold);
    }

    /* Tab Panes */
    .tab-pane {
      display: none;
      padding: 44px 52px;
    }
    .tab-pane.active {
      display: block;
    }
    @media (max-width: 768px) {
      .tab-pane { padding: 24px 18px; }
      .landing-wrapper { margin-top: 60px; }
    }

    /* TAB 1: TM CLOSER QR CODE JOURNEY (WhatsApp Web Mirror) */
    .wa-journey-layout {
      display: grid;
      grid-template-columns: 1.35fr 0.95fr;
      gap: 48px;
      align-items: center;
    }
    @media (max-width: 860px) {
      .wa-journey-layout {
        grid-template-columns: 1fr;
        gap: 32px;
      }
    }

    .wa-journey-title {
      font-size: 26px;
      font-weight: 400;
      letter-spacing: -0.01em;
      color: var(--wa-text-primary);
      margin-bottom: 24px;
      line-height: 1.3;
    }
    .wa-steps-list {
      list-style: none;
      display: flex;
      flex-direction: column;
      gap: 18px;
      margin-bottom: 28px;
    }
    .wa-step-item {
      display: flex;
      align-items: flex-start;
      gap: 14px;
      font-size: 15px;
      line-height: 1.5;
      color: var(--wa-text-secondary);
    }
    .wa-step-item strong {
      color: var(--wa-text-primary);
    }
    .wa-step-num {
      font-size: 14px;
      font-weight: 700;
      color: var(--wa-text-primary);
      width: 24px;
      flex-shrink: 0;
    }
    .wa-icon-hint {
      display: inline-flex;
      align-items: center;
      vertical-align: middle;
      margin: 0 3px;
      font-size: 14px;
    }

    /* TL Banner on Landing Page */
    .tl-cta-banner {
      background: linear-gradient(135deg, rgba(139, 92, 246, 0.15), rgba(168, 85, 247, 0.08));
      border: 1.5px solid rgba(139, 92, 246, 0.35);
      border-radius: 12px;
      padding: 16px 20px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 14px;
      margin-top: 24px;
      cursor: pointer;
      transition: all 0.2s ease;
    }
    .tl-cta-banner:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 24px rgba(139, 92, 246, 0.25);
      border-color: rgba(139, 92, 246, 0.6);
    }
    .tl-cta-badge {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      font-size: 11px;
      font-weight: 800;
      text-transform: uppercase;
      color: #c4b5fd;
      background: rgba(139, 92, 246, 0.25);
      padding: 2px 8px;
      border-radius: 4px;
      margin-bottom: 4px;
    }
    .tl-cta-title {
      font-size: 14px;
      font-weight: 700;
      color: #fff;
    }
    html.theme-light .tl-cta-title { color: #5b21b6; }
    .tl-cta-sub {
      font-size: 12px;
      color: var(--wa-text-secondary);
    }
    .tl-cta-btn {
      padding: 8px 16px;
      border-radius: 8px;
      background: #8b5cf6;
      color: #ffffff;
      font-size: 12px;
      font-weight: 700;
      border: none;
      white-space: nowrap;
      flex-shrink: 0;
    }

    /* Right Column: QR Code Box */
    .qr-column {
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
    }
    .wa-qr-frame {
      background: #ffffff;
      padding: 18px;
      border-radius: 16px;
      box-shadow: 0 10px 30px rgba(0, 0, 0, 0.25);
      position: relative;
      display: flex;
      align-items: center;
      justify-content: center;
      border: 3px solid rgba(0, 168, 132, 0.3);
      width: 270px;
      height: 270px;
    }
    .wa-qr-img {
      width: 230px;
      height: 230px;
      object-fit: contain;
      border-radius: 8px;
      display: block;
    }
    .wa-qr-center-logo {
      position: absolute;
      width: 44px;
      height: 44px;
      border-radius: 50%;
      background: #ffffff;
      border: 2px solid #00a884;
      display: flex;
      align-items: center;
      justify-content: center;
      box-shadow: 0 4px 12px rgba(0,0,0,0.2);
    }
    .wa-qr-center-logo svg {
      width: 24px;
      height: 24px;
      fill: #00a884;
    }
    .qr-scan-line {
      position: absolute;
      width: calc(100% - 36px);
      height: 2px;
      background: linear-gradient(90deg, transparent, #00a884, transparent);
      box-shadow: 0 0 12px #00a884;
      animation: qrScanMove 2.6s ease-in-out infinite alternate;
      pointer-events: none;
    }
    @keyframes qrScanMove {
      0% { top: 22px; }
      100% { top: calc(100% - 22px); }
    }
    .qr-status-badge {
      margin-top: 16px;
      font-size: 12px;
      font-weight: 600;
      color: #00a884;
      display: flex;
      align-items: center;
      gap: 6px;
    }
    .pulse-dot {
      width: 8px;
      height: 8px;
      border-radius: 50%;
      background: #00a884;
      animation: pulseAnim 1.6s infinite;
    }
    @keyframes pulseAnim {
      0%, 100% { opacity: 1; transform: scale(1); }
      50% { opacity: 0.3; transform: scale(0.8); }
    }
    .qr-checkbox-row {
      margin-top: 14px;
      display: flex;
      align-items: center;
      gap: 8px;
      font-size: 13px;
      color: var(--wa-text-secondary);
      cursor: pointer;
    }
    .qr-quick-btn {
      margin-top: 14px;
      background: rgba(255, 255, 255, 0.05);
      border: 1px solid var(--wa-card-border);
      color: var(--wa-text-secondary);
      font-size: 12px;
      font-weight: 600;
      padding: 7px 16px;
      border-radius: 8px;
      cursor: pointer;
      transition: all 0.2s;
    }
    .qr-quick-btn:hover {
      background: rgba(0, 168, 132, 0.15);
      color: #00a884;
      border-color: #00a884;
    }

    /* AUTH FORMS (TL, ADMIN, SADMIN) */
    .form-container {
      max-width: 440px;
      margin: 0 auto;
    }
    .form-header {
      text-align: center;
      margin-bottom: 24px;
    }
    .form-badge-icon {
      width: 56px;
      height: 56px;
      border-radius: 16px;
      margin: 0 auto 12px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 26px;
    }
    .form-title {
      font-size: 20px;
      font-weight: 800;
      color: var(--wa-text-primary);
      margin-bottom: 4px;
    }
    .form-subtitle {
      font-size: 13px;
      color: var(--wa-text-secondary);
    }
    .input-group {
      margin-bottom: 16px;
      text-align: left;
    }
    .input-label {
      display: block;
      font-size: 12px;
      font-weight: 600;
      color: var(--wa-text-secondary);
      margin-bottom: 6px;
    }
    .input-field {
      width: 100%;
      padding: 12px 14px;
      border-radius: 10px;
      background: rgba(255, 255, 255, 0.04);
      border: 1px solid var(--wa-card-border);
      color: var(--wa-text-primary);
      font-size: 14px;
      font-family: inherit;
      transition: border-color 0.2s, box-shadow 0.2s;
    }
    html.theme-light .input-field {
      background: #f8fafc;
      border-color: #e2e8f0;
    }
    .input-field:focus {
      outline: none;
      border-color: var(--wa-accent);
      box-shadow: 0 0 10px rgba(0, 168, 132, 0.25);
    }
    .btn-action-primary {
      width: 100%;
      padding: 13px;
      border-radius: 10px;
      font-size: 14px;
      font-weight: 700;
      border: none;
      cursor: pointer;
      color: #ffffff;
      transition: transform 0.15s, box-shadow 0.15s;
      margin-top: 8px;
    }
    .btn-action-primary:hover {
      transform: translateY(-1px);
    }
    .btn-tl {
      background: linear-gradient(135deg, #8b5cf6, #6d28d9);
      box-shadow: 0 6px 20px rgba(139, 92, 246, 0.35);
    }
    .btn-admin {
      background: linear-gradient(135deg, #f59e0b, #d97706);
      box-shadow: 0 6px 20px rgba(245, 158, 11, 0.35);
    }
    .btn-sadmin {
      background: linear-gradient(135deg, #eab308, #ca8a04);
      color: #000;
      box-shadow: 0 6px 20px rgba(234, 179, 8, 0.35);
    }
    .error-box {
      padding: 10px 14px;
      border-radius: 8px;
      background: rgba(244, 63, 94, 0.15);
      border: 1px solid rgba(244, 63, 94, 0.3);
      color: #fb7185;
      font-size: 12px;
      margin-bottom: 14px;
      display: none;
    }

    /* Footer Navigation */
    .wa-card-footer {
      padding: 18px 40px;
      background: rgba(0, 0, 0, 0.08);
      border-top: 1px solid var(--wa-card-border);
      display: flex;
      align-items: center;
      justify-content: space-between;
      flex-wrap: wrap;
      gap: 12px;
      font-size: 12px;
      color: var(--wa-text-muted);
    }
    html.theme-light .wa-card-footer { background: #fbfbfa; }
    .footer-link {
      color: var(--wa-accent);
      text-decoration: none;
      font-weight: 600;
    }
    .footer-link:hover { text-decoration: underline; }

    /* 360 Spin & Zoom-Out Animation */
    .login-anim-overlay {
      position: fixed; inset: 0; background: var(--anim-overlay-bg);
      z-index: 9999999; display: flex; align-items: center; justify-content: center;
      opacity: 0; visibility: hidden; pointer-events: none;
      transition: opacity 0.25s ease, visibility 0.25s ease;
    }
    .login-anim-overlay.active { opacity: 1; visibility: visible; pointer-events: auto; }
    .anim-logo-box {
      width: 96px; height: 96px; border-radius: 24px;
      background: var(--wa-card-bg); border: 1.5px solid var(--wa-accent);
      display: flex; align-items: center; justify-content: center;
      box-shadow: 0 0 35px rgba(0, 168, 132, 0.5);
    }
    .anim-logo-box img { width: 68px; height: 68px; object-fit: cover; border-radius: 17px; }
    .login-anim-overlay.active .anim-logo-box {
      animation: nunnoSpinZoomOut 1.5s cubic-bezier(0.2, 0.85, 0.25, 1) forwards;
    }
    @keyframes nunnoSpinZoomOut {
      0% { opacity: 0; transform: scale(0.5) rotate(0deg); }
      22% { opacity: 1; transform: scale(1.08) rotate(90deg); }
      50% { opacity: 1; transform: scale(1) rotate(180deg); }
      75% { opacity: 1; transform: scale(1.05) rotate(270deg); }
      88% { opacity: 1; transform: scale(1) rotate(360deg); }
      100% { opacity: 0; transform: scale(0) rotate(360deg); }
    }
    .anim-brand-text { font-size: 20px; font-weight: 800; color: var(--wa-text-primary); margin-top: 20px; text-align: center; }
    .anim-subtext { font-size: 13px; color: var(--wa-text-secondary); margin-top: 4px; text-align: center; }
  </style>
</head>
<body>

<!-- Green Header Banner -->
<div class="wa-top-header">
  <a href="login.php" class="wa-brand-title" style="display:flex; align-items:center; gap:12px;">
    <img src="assets/images/chatly-logo.jpg" alt="Chatly" style="height:36px; width:auto; border-radius:8px; object-fit:contain; box-shadow:0 4px 14px rgba(0,0,0,0.25);">
    <span class="wa-brand-tag" style="background:rgba(255,255,255,0.22); color:#fff; font-weight:700;">WhatsApp Web 2026</span>
  </a>

  <div style="display:flex; align-items:center; gap:16px;">
    <button onclick="toggleTheme()" style="background:rgba(255,255,255,0.2); border:none; color:#fff; padding:6px 12px; border-radius:20px; font-size:12px; cursor:pointer; font-weight:600;">
      🌓 Theme
    </button>
  </div>
</div>

<!-- 360 Spin Animation Overlay -->
<div id="loginAnimOverlay" class="login-anim-overlay" aria-hidden="true">
  <div style="display:flex; flex-direction:column; align-items:center;">
    <div class="anim-logo-box" style="width:72px; height:72px; border-radius:20px; overflow:hidden;">
      <img src="assets/images/chatly-icon.jpg" alt="Chatly" style="width:100%; height:100%; object-fit:cover;">
    </div>
    <div class="anim-brand-text" id="animBrandText">Chatly Sales Desk</div>
    <div class="anim-subtext" id="animSubtext">Initializing isolated workspace…</div>
  </div>
</div>

<!-- Main Floating Container -->
<div class="landing-wrapper">
  <div class="wa-main-card">
    
    <!-- 4-Role Navigation Bar -->
    <div class="role-nav-bar">
      <button class="role-tab-btn active" id="tabBtnTM" onclick="switchRole('tm')">
        <span>📱</span> 1. Closer (TM) QR Login
      </button>
      <button class="role-tab-btn tab-tl" id="tabBtnTL" onclick="switchRole('tl')">
        <span>👑</span> 2. Continue as TL (Rs. 500/TM)
      </button>
      <button class="role-tab-btn tab-admin" id="tabBtnAdmin" onclick="switchRole('admin')">
        <span>🏢</span> 3. Company Admin (/admin)
      </button>
      <button class="role-tab-btn tab-sadmin" id="tabBtnSadmin" onclick="switchRole('sadmin')">
        <span>⚡</span> 4. Super Admin (/sadmin)
      </button>
    </div>

    <!-- TAB 1: TM CLOSER QR CODE JOURNEY (WhatsApp Web Mirror) -->
    <div class="tab-pane active" id="paneTM">
      
      <!-- Sub-Toggle: QR Pair vs Direct Password Login -->
      <div style="display:flex; gap:10px; margin-bottom:22px; max-width:540px; margin-left:auto; margin-right:auto;">
        <button type="button" class="role-tab-btn active" id="btnTMSubQR" onclick="toggleTMSubMode('qr')" style="flex:1; border-radius:10px; font-weight:700;">
          <span>📱</span> Scan WhatsApp QR Code
        </button>
        <button type="button" class="role-tab-btn" id="btnTMSubPW" onclick="toggleTMSubMode('password')" style="flex:1; border-radius:10px; font-weight:700;">
          <span>🔑</span> Continue with Username &amp; Password
        </button>
      </div>

      <!-- VIEW A: WhatsApp Web QR Pairing -->
      <div id="tmViewQR">
        <div class="wa-journey-layout">
          
          <!-- Left: Step-by-Step Instructions -->
          <div>
            <h1 class="wa-journey-title">To use Chatly on your computer:</h1>

            <ol class="wa-steps-list">
              <li class="wa-step-item">
                <span class="wa-step-num">1.</span>
                <div>Open <strong>WhatsApp</strong> on your mobile phone</div>
              </li>
              <li class="wa-step-item">
                <span class="wa-step-num">2.</span>
                <div>
                  Tap <strong>Menu <span class="wa-icon-hint">⋮</span></strong> on Android, or <strong>Settings <span class="wa-icon-hint">⚙️</span></strong> on iPhone
                </div>
              </li>
              <li class="wa-step-item">
                <span class="wa-step-num">3.</span>
                <div>
                  Tap <strong>Linked devices</strong> and then <strong>Link a device</strong>
                </div>
              </li>
              <li class="wa-step-item">
                <span class="wa-step-num">4.</span>
                <div>
                  Point your phone to this screen to capture the QR code
                </div>
              </li>
            </ol>

            <!-- Continue as TL Highlight Banner -->
            <div class="tl-cta-banner" onclick="switchRole('tl')">
              <div>
                <div class="tl-cta-badge">👑 Team Leader Opportunity</div>
                <div class="tl-cta-title">Or Continue as a Team Leader (TL)</div>
                <div class="tl-cta-sub">Earn Rs. 500 PKR commission per joined TM closer with an instant recruitment dashboard.</div>
              </div>
              <button class="tl-cta-btn">Access TL Portal &rarr;</button>
            </div>

            <div style="margin-top: 24px; font-size: 13px; color: var(--wa-text-secondary); display: flex; align-items: center; gap: 16px;">
              <a href="javascript:void(0)" onclick="refreshQRCode(true)" class="footer-link">🔄 Refresh QR code</a>
              <span>•</span>
              <a href="admin.php" class="footer-link">Company Admin Portal</a>
              <span>•</span>
              <a href="sadmin.php" class="footer-link">Super Admin</a>
            </div>
          </div>

          <!-- Right: WhatsApp Web QR Frame -->
          <div class="qr-column">
            <div class="wa-qr-frame">
              <div class="qr-scan-line"></div>
              <img id="landingQrImage" src="data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='230' height='230' viewBox='0 0 230 230'><rect width='230' height='230' fill='%23f1f5f9'/><text x='50%25' y='50%25' dominant-baseline='middle' text-anchor='middle' font-family='sans-serif' font-size='12' fill='%2364748b' font-weight='700'>⚡ Generating QR Code...</text></svg>" alt="WhatsApp QR Code" class="wa-qr-img">
              <div class="wa-qr-center-logo" style="width:44px; height:44px; border-radius:12px; background:#0c0a18; border:2px solid #00a884; overflow:hidden; padding:2px; display:flex; align-items:center; justify-content:center; box-shadow:0 4px 14px rgba(0,0,0,0.3);">
                <img src="assets/images/chatly-icon.jpg" alt="Chatly" style="width:100%; height:100%; object-fit:cover; border-radius:8px;">
              </div>
            </div>

            <div class="qr-status-badge" id="qrStatusBadge">
              <span class="pulse-dot"></span>
              <span id="qrStatusText">Pure WebSocket Bridge • Ready to Pair</span>
            </div>

            <label class="qr-checkbox-row">
              <input type="checkbox" id="keepSignedIn" checked>
              <span>Keep me signed in</span>
            </label>

            <!-- Pair / Test WhatsApp Phone Input -->
            <div style="margin-top: 14px; width: 100%; max-width: 270px;">
              <div style="font-size: 11px; color: var(--wa-text-muted); margin-bottom: 5px; text-align: left;">
                📱 Link WhatsApp Number:
              </div>
              <div style="display: flex; gap: 6px;">
                <input type="text" id="simPhoneInput" placeholder="+923046588806" value="+923046588806" class="input-field" style="padding: 8px 10px; font-size: 13px; font-family: monospace;">
                <button type="button" class="btn-action-primary" style="margin: 0; padding: 8px 12px; width: auto; font-size: 12px; background: #00a884; white-space: nowrap;" onclick="handlePhonePairSubmit()">
                  ⚡ Link
                </button>
              </div>
            </div>
          </div>

        </div>
      </div>

      <!-- VIEW B: Direct TM Username & Password Login -->
      <div id="tmViewPassword" style="display:none;">
        <div class="form-container">
          <div class="form-header">
            <div class="form-badge-icon" style="background:rgba(0, 168, 132, 0.18); color:#00a884;">🔑</div>
            <h2 class="form-title">Team Member (TM) Closer Login</h2>
            <p class="form-subtitle">Already registered &amp; approved? Sign in directly without scanning QR.</p>
          </div>

          <form id="formTMPasswordLogin" onsubmit="handleTMPasswordLogin(event)">
            <div class="input-group">
              <label class="input-label">Username (e.g. askari or your approved username)</label>
              <input type="text" id="tmDirectUsername" class="input-field" placeholder="askari" required>
            </div>
            <div class="input-group">
              <label class="input-label">Password</label>
              <input type="password" id="tmDirectPassword" class="input-field" placeholder="123456" required>
            </div>
            <div class="error-box" id="tmPasswordLoginError"></div>
            <button type="submit" id="tmDirectLoginBtn" class="btn-action-primary" style="background:linear-gradient(135deg, #00a884, #059669); box-shadow:0 6px 20px rgba(0, 168, 132, 0.35);">
              Sign In to Closer Workspace 🚀
            </button>
          </form>

          <div style="margin-top:20px; text-align:center; font-size:12px; color:var(--wa-text-secondary);">
            New to Chatly? <a href="javascript:void(0)" onclick="toggleTMSubMode('qr')" class="footer-link">Scan your WhatsApp QR to apply</a>
          </div>
        </div>
      </div>

    </div>

    <!-- TAB 2: TEAM LEADER (TL) COMMISSION PORTAL -->
    <div class="tab-pane" id="paneTL">
      <div class="form-container">
        <div class="form-header">
          <div class="form-badge-icon" style="background:rgba(139, 92, 246, 0.2); color:#c4b5fd;">👑</div>
          <h2 class="form-title">Team Leader (TL) Commission Portal</h2>
          <p class="form-subtitle">Earn <strong>Rs. 500 PKR</strong> per joined TM. Recruit closers & manage quotas.</p>
        </div>

        <div style="display:flex; gap:8px; margin-bottom:18px;">
          <button type="button" class="role-tab-btn tab-tl active" id="btnTLModeLogin" onclick="toggleTLMode('login')" style="flex:1; border-radius:8px;">TL Sign In</button>
          <button type="button" class="role-tab-btn tab-tl" id="btnTLModeReg" onclick="toggleTLMode('register')" style="flex:1; border-radius:8px;">Register New TL</button>
        </div>

        <!-- TL Login Form -->
        <form id="formTLLogin" onsubmit="handleTLLogin(event)">
          <div class="input-group">
            <label class="input-label">TL Username (e.g. ali or asima)</label>
            <input type="text" id="tlUsername" class="input-field" placeholder="ali" required>
          </div>
          <div class="input-group">
            <label class="input-label">Password</label>
            <input type="password" id="tlPassword" class="input-field" placeholder="ali123 or password" required>
          </div>
          <div class="error-box" id="tlLoginError"></div>
          <button type="submit" id="tlLoginBtn" class="btn-action-primary btn-tl">
            Access TL Commission Desk 👑
          </button>
        </form>

        <!-- TL Register Form -->
        <form id="formTLRegister" onsubmit="handleTLRegister(event)" style="display:none;">
          <div class="input-group">
            <label class="input-label">Full Name *</label>
            <input type="text" id="regTLName" class="input-field" placeholder="e.g. Bilal Ahmed" required>
          </div>
          <div class="input-group">
            <label class="input-label">Desired Username *</label>
            <input type="text" id="regTLUsername" class="input-field" placeholder="e.g. bilal_tl" required>
          </div>
          <div class="input-group">
            <label class="input-label">WhatsApp Phone *</label>
            <input type="text" id="regTLPhone" class="input-field" placeholder="+923001234567" required>
          </div>
          <div class="input-group">
            <label class="input-label">Password *</label>
            <input type="password" id="regTLPassword" class="input-field" placeholder="Min 6 characters" required>
          </div>
          <div class="error-box" id="tlRegError"></div>
          <button type="submit" id="tlRegBtn" class="btn-action-primary btn-tl">
            Register & Activate Commission Desk (Rs. 500/TM) 🚀
          </button>
        </form>

        <div style="margin-top:20px; text-align:center; font-size:12px; color:var(--wa-text-secondary);">
          Demo accounts: <span style="font-family:monospace; color:#c4b5fd;">ali / ali123</span> &bull; <span style="font-family:monospace; color:#c4b5fd;">asima / asima123</span>
        </div>
      </div>
    </div>

    <!-- TAB 3: COMPANY ADMIN PORTAL -->
    <div class="tab-pane" id="paneAdmin">
      <div class="form-container">
        <div class="form-header">
          <div class="form-badge-icon" style="background:rgba(245, 158, 11, 0.2); color:#fbbf24;">🏢</div>
          <h2 class="form-title">Company Admin Portal</h2>
          <p class="form-subtitle">Provision employees, adjust message limits & manage lead takeovers.</p>
        </div>

        <form id="formAdminLogin" onsubmit="handleAdminLogin(event)">
          <div class="input-group">
            <label class="input-label">Admin Username</label>
            <input type="text" id="adminUsername" class="input-field" placeholder="admin" required>
          </div>
          <div class="input-group">
            <label class="input-label">Admin Password</label>
            <input type="password" id="adminPassword" class="input-field" placeholder="admin123" required>
          </div>
          <div class="error-box" id="adminLoginError"></div>
          <button type="submit" id="adminLoginBtn" class="btn-action-primary btn-admin">
            Sign In to Company Admin (/admin) 🏢
          </button>
        </form>

        <div style="margin-top:20px; text-align:center; font-size:12px; color:var(--wa-text-secondary);">
          <a href="admin.php" class="footer-link">Go directly to full /admin page &rarr;</a>
        </div>
      </div>
    </div>

    <!-- TAB 4: SUPER ADMIN MASTER COMMAND -->
    <div class="tab-pane" id="paneSadmin">
      <div class="form-container">
        <div class="form-header">
          <div class="form-badge-icon" style="background:rgba(234, 179, 8, 0.2); color:#fde047;">⚡</div>
          <h2 class="form-title">Super Admin Command Center</h2>
          <p class="form-subtitle">Platform Owner Master Access — Multi-Company Telemetry & Stats.</p>
        </div>

        <form id="formSadminLogin" onsubmit="handleSadminLogin(event)">
          <div class="input-group">
            <label class="input-label">Super Admin Key</label>
            <input type="text" id="sadminUsername" class="input-field" placeholder="sadmin" required>
          </div>
          <div class="input-group">
            <label class="input-label">Master Password</label>
            <input type="password" id="sadminPassword" class="input-field" placeholder="sadmin123" required>
          </div>
          <div class="error-box" id="sadminLoginError"></div>
          <button type="submit" id="sadminLoginBtn" class="btn-action-primary btn-sadmin">
            Unlock Super Command (/sadmin) ⚡
          </button>
        </form>

        <div style="margin-top:20px; text-align:center; font-size:12px; color:var(--wa-text-secondary);">
          <a href="sadmin.php" class="footer-link">Go directly to full /sadmin page &rarr;</a>
        </div>
      </div>
    </div>

    <!-- WhatsApp Web Card Bottom Bar -->
    <div class="wa-card-footer">
      <div>
        <span>Chatly Multi-Tenant Closing Infrastructure</span>
        <span style="margin: 0 6px;">&bull;</span>
        <span style="color:#00a884; font-weight:700;">24/7 Pure WebSocket Engine</span>
      </div>
      <div style="display:flex; gap:16px;">
        <a href="login.php?switch=1" class="footer-link">Switch Account</a>
        <a href="admin.php" class="footer-link">Company Admin</a>
        <a href="sadmin.php" class="footer-link">Super Admin</a>
      </div>
    </div>

  </div>
</div>

<script>
// Role switcher
function switchRole(role) {
  const tabs = ['tm', 'tl', 'admin', 'sadmin'];
  tabs.forEach(r => {
    const btn = document.getElementById('tabBtn' + r.toUpperCase());
    const pane = document.getElementById('pane' + r.toUpperCase());
    if (btn) btn.classList.toggle('active', r === role);
    if (pane) pane.classList.toggle('active', r === role);
  });
}

// TL Mode Switcher (Login vs Register)
function toggleTLMode(mode) {
  const formLogin = document.getElementById('formTLLogin');
  const formReg = document.getElementById('formTLRegister');
  const btnLogin = document.getElementById('btnTLModeLogin');
  const btnReg = document.getElementById('btnTLModeReg');
  if (mode === 'register') {
    formLogin.style.display = 'none';
    formReg.style.display = 'block';
    btnReg.classList.add('active');
    btnLogin.classList.remove('active');
  } else {
    formLogin.style.display = 'block';
    formReg.style.display = 'none';
    btnLogin.classList.add('active');
    btnReg.classList.remove('active');
  }
}

// 360 Spin Animation Trigger
function triggerLoginAnimation(brandName, subtext, redirectUrl) {
  const overlay = document.getElementById('loginAnimOverlay');
  const txtBrand = document.getElementById('animBrandText');
  const txtSub = document.getElementById('animSubtext');
  if (txtBrand && brandName) txtBrand.textContent = brandName;
  if (txtSub && subtext) txtSub.textContent = subtext;
  overlay.classList.add('active');
  setTimeout(() => {
    window.location.href = redirectUrl;
  }, 1450);
}

// Live QR Code Poller & Auto-Pairing
let qrPollTimer = null;
let lastKnownState = '';

async function refreshQRCode(force = false) {
  const qrImg = document.getElementById('landingQrImage');
  const statusTxt = document.getElementById('qrStatusText');
  try {
    if (force) {
      statusTxt.textContent = '🔄 Generating fresh WhatsApp QR code...';
      try {
        await fetch('api/direct_sender.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ action: 'reset_whatsapp' })
        });
      } catch (_) {}
      lastKnownState = '';
    }
    const res = await fetch('api/direct_sender.php?action=get_sender_info');
    const data = await res.json();
    if (data.success) {
      if (data.qr && data.state === 'qr') {
        qrImg.src = data.qr;
        statusTxt.textContent = 'Pure WebSocket Bridge • Scan with WhatsApp';
      } else if (data.is_connected && data.connected_number) {
        statusTxt.textContent = `Connected: +${data.connected_number}! Verifying Account...`;
        if (lastKnownState !== 'connected') {
          lastKnownState = 'connected';
          clearInterval(qrPollTimer);
          handleConnectedPhone(data.connected_number);
        }
      } else if (data.state === 'idle' || data.state === 'disconnected') {
        fetch('api/direct_sender.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ action: 'connect_whatsapp' })
        });
        statusTxt.textContent = 'Initializing WhatsApp bridge…';
      } else {
        statusTxt.textContent = data.message || 'Connecting to WhatsApp bridge…';
      }
    }
  } catch (err) {
    statusTxt.textContent = 'Bridge reconnecting…';
  }
}

// Connected Phone Verification & Routing (TM Joining Cycle)
async function handleConnectedPhone(phone) {
  try {
    const res = await fetch('api/auth.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'check_phone', phone: phone })
    });
    const data = await res.json();
    if (data.exists && data.user) {
      if (data.user.status === 'pending') {
        const compName = data.user.company_name || data.user.company_id || 'Company';
        document.getElementById('successCompName').textContent = compName;
        document.getElementById('modalTMSuccess').style.display = 'flex';
      } else if (data.user.status === 'active') {
        const loginRes = await fetch('api/auth.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ action: 'qr_login', phone: phone })
        });
        const loginData = await loginRes.json();
        if (loginData.success) {
          triggerLoginAnimation(loginData.user.name, 'Sales Closer Desk Activated • Starting Pipeline', 'index.php');
        } else {
          pendingMatchedUser = data.user;
          document.getElementById('phoneExistsUser').textContent = '@' + data.user.username;
          document.getElementById('phoneExistsBtnUser').textContent = data.user.username;
          document.getElementById('modalPhoneExists').style.display = 'flex';
        }
      } else {
        alert('Your closer account is ' + data.user.status + '. Please contact your company administrator.');
      }
    } else {
      // Brand new TM closer: open registration modal with locked phone
      openTMRegisterModal(phone);
    }
  } catch (err) {
    openTMRegisterModal(phone);
  }
}

// Polling interval
qrPollTimer = setInterval(refreshQRCode, 3500);
refreshQRCode();

// Instant Closer Demo Login
async function instantTMTestLogin(phone) {
  try {
    const res = await fetch('api/auth.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        action: 'qr_login',
        phone: phone || '',
        demo: !phone
      })
    });
    const data = await res.json();
    if (data.success) {
      triggerLoginAnimation(data.user.name, 'Sales Closer Desk Activated • Starting Pipeline', 'index.php');
    } else {
      alert(data.error || 'Failed to authenticate closer.');
    }
  } catch (err) {
    alert('Network error connecting to closer authentication.');
  }
}

// TL Login
async function handleTLLogin(e) {
  e.preventDefault();
  const btn = document.getElementById('tlLoginBtn');
  const errBox = document.getElementById('tlLoginError');
  btn.disabled = true;
  btn.textContent = 'Authenticating TL…';
  errBox.style.display = 'none';

  try {
    const res = await fetch('api/auth.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        action: 'login',
        username: document.getElementById('tlUsername').value.trim(),
        password: document.getElementById('tlPassword').value.trim()
      })
    });
    const data = await res.json();
    if (data.success) {
      triggerLoginAnimation(data.user.name, 'Team Leader Commission Portal (Rs. 500/TM)', 'index.php?mode=tl');
    } else {
      errBox.textContent = data.error || 'Invalid TL credentials.';
      errBox.style.display = 'block';
    }
  } catch (err) {
    errBox.textContent = 'Network communication error.';
    errBox.style.display = 'block';
  } finally {
    btn.disabled = false;
    btn.textContent = 'Access TL Commission Desk 👑';
  }
}

// TL Register
async function handleTLRegister(e) {
  e.preventDefault();
  const btn = document.getElementById('tlRegBtn');
  const errBox = document.getElementById('tlRegError');
  btn.disabled = true;
  btn.textContent = 'Registering…';
  errBox.style.display = 'none';

  try {
    const res = await fetch('api/auth.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        action: 'tl_register',
        name: document.getElementById('regTLName').value.trim(),
        username: document.getElementById('regTLUsername').value.trim(),
        phone: document.getElementById('regTLPhone').value.trim(),
        password: document.getElementById('regTLPassword').value.trim()
      })
    });
    const data = await res.json();
    if (data.success) {
      triggerLoginAnimation(data.user.name, 'Team Leader Activated • Rs. 500 / TM Commission Ready', 'index.php?mode=tl');
    } else {
      errBox.textContent = data.error || 'Registration failed.';
      errBox.style.display = 'block';
    }
  } catch (err) {
    errBox.textContent = 'Network communication error.';
    errBox.style.display = 'block';
  } finally {
    btn.disabled = false;
    btn.textContent = 'Register & Activate Commission Desk (Rs. 500/TM) 🚀';
  }
}

// Company Admin Login
async function handleAdminLogin(e) {
  e.preventDefault();
  const btn = document.getElementById('adminLoginBtn');
  const errBox = document.getElementById('adminLoginError');
  btn.disabled = true;
  btn.textContent = 'Verifying Admin…';
  errBox.style.display = 'none';

  try {
    const res = await fetch('api/auth.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        action: 'login',
        username: document.getElementById('adminUsername').value.trim(),
        password: document.getElementById('adminPassword').value.trim()
      })
    });
    const data = await res.json();
    if (data.success && (data.user.role === 'admin' || data.user.role === 'superadmin')) {
      triggerLoginAnimation(data.user.name, 'Company Administrator Control Center', 'admin.php');
    } else {
      errBox.textContent = data.error || 'Access Denied: Company Admin privileges required.';
      errBox.style.display = 'block';
    }
  } catch (err) {
    errBox.textContent = 'Network communication error.';
    errBox.style.display = 'block';
  } finally {
    btn.disabled = false;
    btn.textContent = 'Sign In to Company Admin (/admin) 🏢';
  }
}

// Super Admin Login
async function handleSadminLogin(e) {
  e.preventDefault();
  const btn = document.getElementById('sadminLoginBtn');
  const errBox = document.getElementById('sadminLoginError');
  btn.disabled = true;
  btn.textContent = 'Authenticating Master Access…';
  errBox.style.display = 'none';

  try {
    const res = await fetch('api/auth.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        action: 'login',
        username: document.getElementById('sadminUsername').value.trim(),
        password: document.getElementById('sadminPassword').value.trim()
      })
    });
    const data = await res.json();
    if (data.success && data.user.role === 'superadmin') {
      triggerLoginAnimation('Platform Super Admin', 'Master Command Center • Multi-Tenant Telemetry', 'sadmin.php');
    } else {
      errBox.textContent = data.error || 'Access Denied: Super Administrator privileges required.';
      errBox.style.display = 'block';
    }
  } catch (err) {
    errBox.textContent = 'Network communication error.';
    errBox.style.display = 'block';
  } finally {
    btn.disabled = false;
    btn.textContent = 'Unlock Super Command (/sadmin) ⚡';
  }
}
</script>

<!-- MODAL: Phone Already Registered Check -->
<div id="modalPhoneExists" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.75); z-index:999999; align-items:center; justify-content:center; backdrop-filter:blur(6px);">
  <div style="background:var(--wa-card-bg); border:1px solid var(--wa-card-border); border-radius:18px; padding:28px; max-width:440px; width:90%; box-shadow:0 20px 40px rgba(0,0,0,0.5); text-align:center;">
    <div style="font-size:44px; margin-bottom:12px;">⚠️</div>
    <h3 style="font-size:18px; font-weight:800; color:var(--wa-text-primary); margin-bottom:8px;">Phone Number Already in Use</h3>
    <p style="font-size:13px; color:var(--wa-text-secondary); line-height:1.5; margin-bottom:20px;" id="phoneExistsMsg">
      This WhatsApp number is already linked to closer account <strong id="phoneExistsUser" style="color:#00a884;">@user</strong>.<br>Wanna login with your password instead?
    </p>
    <div style="display:flex; gap:10px;">
      <button type="button" class="btn-action-primary" style="background:linear-gradient(135deg, #00a884, #059669); margin:0;" onclick="switchTMToLogin()">
        🔑 Yes, Log In as @<span id="phoneExistsBtnUser">user</span>
      </button>
      <button type="button" class="btn-action-primary" style="background:rgba(255,255,255,0.08); color:var(--wa-text-primary); margin:0; width:auto; padding:12px 18px;" onclick="closePhoneExistsModal()">
        Cancel
      </button>
    </div>
  </div>
</div>

<!-- MODAL: New TM Closer Registration Wizard -->
<div id="modalTMRegister" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.8); z-index:999999; align-items:center; justify-content:center; backdrop-filter:blur(8px); overflow-y:auto; padding:20px 0;">
  <div style="background:var(--wa-card-bg); border:1px solid rgba(0,168,132,0.4); border-radius:20px; padding:30px; max-width:520px; width:92%; box-shadow:0 25px 60px rgba(0,0,0,0.6); position:relative;">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:18px;">
      <div>
        <div style="font-size:11px; font-weight:800; text-transform:uppercase; color:#00a884; letter-spacing:0.05em;">Step-by-Step Onboarding</div>
        <h3 style="font-size:20px; font-weight:800; color:var(--wa-text-primary);">🚀 New Closer WhatsApp Setup</h3>
      </div>
      <button type="button" onclick="closeTMRegisterModal()" style="background:none; border:none; color:var(--wa-text-muted); font-size:24px; cursor:pointer;">&times;</button>
    </div>

    <div style="background:rgba(0,168,132,0.1); border:1px solid rgba(0,168,132,0.25); border-radius:10px; padding:10px 14px; margin-bottom:18px; font-size:12px; color:#00a884; display:flex; align-items:center; gap:8px;">
      <span>📱</span>
      <span>WhatsApp Number: <strong id="regDisplayPhone">+92 300 1234567</strong></span>
      <span style="margin-left:auto; background:#00a884; color:#fff; font-size:10px; padding:2px 6px; border-radius:4px; font-weight:700;">VERIFIED</span>
    </div>

    <form id="formTMRegisterSubmit" onsubmit="handleTMRegisterSubmit(event)">
      <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:12px;">
        <div class="input-group" style="margin:0;">
          <label class="input-label">Set Desired Username *</label>
          <input type="text" id="regTMUsername" class="input-field" placeholder="e.g. ali_closer" required style="font-size:13px;">
        </div>
        <div class="input-group" style="margin:0;">
          <label class="input-label">Set Password *</label>
          <input type="password" id="regTMPassword" class="input-field" placeholder="Min 6 chars" required style="font-size:13px;">
        </div>
      </div>

      <div class="input-group" style="margin-bottom: 6px;">
        <label class="input-label">Select Company * (Where you are applying as Closer)</label>
        <select id="regTMCompany" class="input-field" required style="cursor:pointer; font-weight:600;" onchange="updateSelectedCompanyPreview()">
          <option value="COMP-001">Nunno Official — Scalper Alpha &amp; SaaS</option>
          <option value="COMP-002">Apex Crypto Signals — Institutional Trading</option>
          <option value="COMP-003">Pak Trade Academy — Mentorship &amp; Signals</option>
        </select>
      </div>

      <!-- Live Company Brand & Logo Preview Card -->
      <div id="companyBrandPreviewCard" style="background: rgba(255,255,255,0.04); border: 1px solid rgba(0,168,132,0.3); border-radius: 12px; padding: 10px 14px; margin-bottom: 14px; display: flex; align-items: center; gap: 12px; transition: all 0.2s ease;">
        <img id="previewCompLogo" src="assets/images/nunno-logo.svg" alt="Company Logo" style="width: 42px; height: 42px; border-radius: 10px; object-fit: contain; background: #0c0a18; border: 1px solid rgba(255,255,255,0.15); padding: 3px; box-shadow: 0 4px 12px rgba(0,0,0,0.3); flex-shrink: 0;">
        <div style="flex: 1; min-width: 0;">
          <div style="display: flex; align-items: center; gap: 6px; margin-bottom: 2px;">
            <strong id="previewCompName" style="color: var(--wa-text-primary); font-size: 13.5px; font-weight: 800;">Nunno Official</strong>
            <span id="previewCompBadge" style="font-size: 10px; font-weight: 700; padding: 2px 6px; border-radius: 4px; background: rgba(139, 92, 246, 0.2); color: #a78bfa; border: 1px solid rgba(139, 92, 246, 0.4);">Enterprise Billion</span>
          </div>
          <div id="previewCompTagline" style="font-size: 11px; color: var(--wa-text-muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">Scalper Alpha &amp; SaaS</div>
        </div>
      </div>

      <div class="input-group">
        <label class="input-label">Full Name *</label>
        <input type="text" id="regTMFullName" class="input-field" placeholder="e.g. Ali Ahmed" required>
      </div>

      <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:12px;">
        <div class="input-group" style="margin:0;">
          <label class="input-label">City *</label>
          <input type="text" id="regTMCity" class="input-field" placeholder="e.g. Lahore / Karachi" required>
        </div>
        <div class="input-group" style="margin:0;">
          <label class="input-label">Gender</label>
          <select id="regTMGender" class="input-field">
            <option value="male">Male Closer</option>
            <option value="female">Female Closer</option>
          </select>
        </div>
      </div>

      <div class="input-group">
        <label class="input-label">Profile Picture (PFP Avatar URL - Optional)</label>
        <input type="text" id="regTMPfp" class="input-field" placeholder="https://... (or leave default avatar)">
      </div>

      <div class="error-box" id="tmRegModalError"></div>

      <button type="submit" id="btnSubmitTMReg" class="btn-action-primary" style="background:linear-gradient(135deg, #00a884, #059669); margin-top:8px;">
        Submit Closer Application for Company Approval ⏳
      </button>
      
      <div style="font-size:11px; color:var(--wa-text-muted); text-align:center; margin-top:10px;">
        ℹ️ Once submitted, company administration reviews and approves your account with a custom daily message quota.
      </div>
    </form>
  </div>
</div>

<!-- MODAL: TM Application Submitted Confirmation -->
<div id="modalTMSuccess" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.8); z-index:999999; align-items:center; justify-content:center; backdrop-filter:blur(8px);">
  <div style="background:var(--wa-card-bg); border:1px solid rgba(0,168,132,0.4); border-radius:20px; padding:32px; max-width:460px; width:90%; box-shadow:0 25px 60px rgba(0,0,0,0.6); text-align:center;">
    <div style="display:flex; justify-content:center; margin-bottom:12px;">
      <img id="successCompLogo" src="assets/images/nunno-logo.svg" alt="Company Logo" style="width:64px; height:64px; border-radius:16px; object-fit:contain; background:#0c0a18; border:1px solid rgba(255,255,255,0.18); padding:5px; box-shadow:0 8px 24px rgba(0,0,0,0.4);">
    </div>
    <h3 style="font-size:20px; font-weight:800; color:var(--wa-text-primary); margin-bottom:8px;">Closer Application Received!</h3>
    <p style="font-size:13px; color:var(--wa-text-secondary); line-height:1.6; margin-bottom:20px;">
      Your application has been dispatched to <strong id="successCompName" style="color:#00a884;">Company</strong> administration.<br><br>
      Once approved by the company, you can log in directly anytime using your <strong>Username &amp; Password</strong> without needing to scan the QR code again!
    </p>
    <button type="button" class="btn-action-primary" style="background:linear-gradient(135deg, #00a884, #059669); margin:0;" onclick="closeTMSuccessModal()">
      Understood, Continue to Login &rarr;
    </button>
  </div>
</div>

<script>
// TM Sub-Mode Switcher (QR vs Password)
function toggleTMSubMode(mode) {
  const vQR = document.getElementById('tmViewQR');
  const vPW = document.getElementById('tmViewPassword');
  const bQR = document.getElementById('btnTMSubQR');
  const bPW = document.getElementById('btnTMSubPW');
  if (mode === 'password') {
    vQR.style.display = 'none';
    vPW.style.display = 'block';
    bPW.classList.add('active');
    bQR.classList.remove('active');
  } else {
    vQR.style.display = 'block';
    vPW.style.display = 'none';
    bQR.classList.add('active');
    bPW.classList.remove('active');
  }
}

// Global matched user for phone duplicate check
let pendingMatchedUser = null;

// Handle phone pairing / linking submit
async function handlePhonePairSubmit() {
  const phoneInput = document.getElementById('simPhoneInput');
  const phone = (phoneInput.value || '').trim();
  if (!phone) {
    alert('Please enter a WhatsApp phone number.');
    return;
  }

  try {
    const res = await fetch('api/auth.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'check_phone', phone: phone })
    });
    const data = await res.json();
    if (data.exists && data.user) {
      // Case A: Phone already in DB!
      pendingMatchedUser = data.user;
      document.getElementById('phoneExistsUser').textContent = '@' + data.user.username;
      document.getElementById('phoneExistsBtnUser').textContent = data.user.username;
      document.getElementById('modalPhoneExists').style.display = 'flex';
    } else {
      // Case B: Phone is brand new -> open registration wizard!
      openTMRegisterModal(phone);
    }
  } catch (err) {
    // If local offline fallback, still allow registration
    openTMRegisterModal(phone);
  }
}

function switchTMToLogin() {
  closePhoneExistsModal();
  toggleTMSubMode('password');
  if (pendingMatchedUser) {
    document.getElementById('tmDirectUsername').value = pendingMatchedUser.username;
    document.getElementById('tmDirectPassword').focus();
  }
}

function closePhoneExistsModal() {
  document.getElementById('modalPhoneExists').style.display = 'none';
}

function openTMRegisterModal(phone) {
  document.getElementById('regDisplayPhone').textContent = phone;
  document.getElementById('modalTMRegister').style.display = 'flex';
  document.getElementById('regTMUsername').focus();
  loadCompaniesForRegister();
}

function closeTMRegisterModal() {
  document.getElementById('modalTMRegister').style.display = 'none';
}

function closeTMSuccessModal() {
  document.getElementById('modalTMSuccess').style.display = 'none';
  toggleTMSubMode('password');
}

let loadedCompaniesCache = [];

async function loadCompaniesForRegister() {
  try {
    const res = await fetch('api/companies.php?action=list');
    const data = await res.json();
    if (data.success && Array.isArray(data.companies)) {
      loadedCompaniesCache = data.companies;
      const sel = document.getElementById('regTMCompany');
      sel.innerHTML = '';
      data.companies.forEach(c => {
        const opt = document.createElement('option');
        opt.value = c.id;
        opt.textContent = `${c.name} (${c.plan || 'Enterprise'})`;
        sel.appendChild(opt);
      });
      updateSelectedCompanyPreview();
    }
  } catch (_) {}
}

function updateSelectedCompanyPreview() {
  const sel = document.getElementById('regTMCompany');
  if (!sel) return;
  const compId = sel.value;
  const comp = (loadedCompaniesCache && loadedCompaniesCache.find(c => c.id === compId)) || {
    id: compId || 'COMP-001',
    name: 'Nunno Official',
    logo: 'assets/images/nunno-logo.svg',
    plan: 'Enterprise Billion',
    badge: 'Scalper Alpha & SaaS'
  };
  const logoEl = document.getElementById('previewCompLogo');
  const nameEl = document.getElementById('previewCompName');
  const badgeEl = document.getElementById('previewCompBadge');
  const taglineEl = document.getElementById('previewCompTagline');
  if (logoEl) logoEl.src = comp.logo || 'assets/images/nunno-logo.svg';
  if (nameEl) nameEl.textContent = comp.name;
  if (badgeEl) badgeEl.textContent = comp.plan || 'Verified Company';
  if (taglineEl) taglineEl.textContent = comp.badge || (comp.plan + ' • Verified Closing Workspace');
}

async function handleTMRegisterSubmit(e) {
  e.preventDefault();
  const btn = document.getElementById('btnSubmitTMReg');
  const errBox = document.getElementById('tmRegModalError');
  btn.disabled = true;
  btn.textContent = 'Submitting Application…';
  errBox.style.display = 'none';

  const phone = document.getElementById('regDisplayPhone').textContent.trim();
  const username = document.getElementById('regTMUsername').value.trim();
  const password = document.getElementById('regTMPassword').value.trim();
  const company_id = document.getElementById('regTMCompany').value;
  const fullName = document.getElementById('regTMFullName').value.trim();
  const city = document.getElementById('regTMCity').value.trim();
  const gender = document.getElementById('regTMGender').value;
  const pfp = document.getElementById('regTMPfp').value.trim();

  try {
    const res = await fetch('api/auth.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        action: 'tm_register',
        username,
        password,
        name: fullName,
        phone,
        company_id,
        city,
        gender,
        pfp
      })
    });
    const data = await res.json();
    if (data.success) {
      closeTMRegisterModal();
      const compSelect = document.getElementById('regTMCompany');
      const compName = compSelect.options[compSelect.selectedIndex]?.text || 'Company';
      document.getElementById('successCompName').textContent = compName;
      const compMatch = loadedCompaniesCache.find(c => c.id === company_id);
      if (compMatch && compMatch.logo) {
        document.getElementById('successCompLogo').src = compMatch.logo;
      }
      document.getElementById('modalTMSuccess').style.display = 'flex';
      document.getElementById('tmDirectUsername').value = username;
    } else {
      errBox.textContent = data.error || 'Registration failed.';
      errBox.style.display = 'block';
    }
  } catch (err) {
    errBox.textContent = 'Network communication error.';
    errBox.style.display = 'block';
  } finally {
    btn.disabled = false;
    btn.textContent = 'Submit Closer Application for Company Approval ⏳';
  }
}

// Handle TM Direct Password Login
async function handleTMPasswordLogin(e) {
  e.preventDefault();
  const btn = document.getElementById('tmDirectLoginBtn');
  const errBox = document.getElementById('tmPasswordLoginError');
  btn.disabled = true;
  btn.textContent = 'Authenticating Closer…';
  errBox.style.display = 'none';

  try {
    const res = await fetch('api/auth.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        action: 'login',
        username: document.getElementById('tmDirectUsername').value.trim(),
        password: document.getElementById('tmDirectPassword').value.trim()
      })
    });
    const data = await res.json();
    if (data.success) {
      triggerLoginAnimation(data.user.name, 'Sales Closer Desk Activated • Starting Pipeline', 'index.php');
    } else {
      errBox.textContent = data.error || 'Invalid closer credentials.';
      errBox.style.display = 'block';
    }
  } catch (err) {
    errBox.textContent = 'Network communication error.';
    errBox.style.display = 'block';
  } finally {
    btn.disabled = false;
    btn.textContent = 'Sign In to Closer Workspace 🚀';
  }
}

// Theme Toggle
function toggleTheme() {
  const isLight = document.documentElement.classList.toggle('theme-light');
  try { localStorage.setItem('nunno_theme', isLight ? 'light' : 'dark'); } catch(_) {}
}
</script>
</body>
</html>