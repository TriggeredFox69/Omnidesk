<?php
/**
 * Billion-Dollar Startup — Multi-Tenant QR Login & Onboarding Portal
 * 
 * Supports:
 * 1. Normal Closer (TM) Dynamic WhatsApp QR Code Scan Journey with Step-by-Step Guide
 * 2. Dedicated Team Leader (TL) Commission-Based Login Flow (Rs. 500 / TM)
 * 3. Quick Routing to Company Admin (/admin) and Super Admin (/sadmin)
 * 4. Signature 360 Spin & Zoom-Out Animation (Dark & Light Mode Adaptive)
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!empty($_SESSION['omnidesk_user'])) {
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
  <title>Sign in — Conversational Closing Platform</title>
  <link rel="icon" type="image/png" href="assets/images/nunno-logo.png">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&family=JetBrains+Mono:wght@500;700&display=swap" rel="stylesheet">
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
      --bg-dark: #06050a;
      --bg-card: #0d0c18;
      --bg-card-inner: rgba(255, 255, 255, 0.03);
      --border-card: rgba(139, 92, 246, 0.25);
      --border-glow: rgba(139, 92, 246, 0.5);
      --text-main: #f8fafc;
      --text-muted: #94a3b8;
      --accent-purple: #8b5cf6;
      --accent-emerald: #10b981;
      --accent-amber: #f59e0b;
      --accent-rose: #f43f5e;
      --anim-overlay-bg: #06050a;
      --anim-logo-bg: #0d0c18;
      --anim-text-color: #ffffff;
      --anim-subtext-color: #94a3b8;
    }

    html.theme-light {
      --bg-dark: #f8fafc;
      --bg-card: #ffffff;
      --bg-card-inner: #f1f5f9;
      --border-card: #e2e8f0;
      --border-glow: rgba(139, 92, 246, 0.35);
      --text-main: #0f172a;
      --text-muted: #64748b;
      --anim-overlay-bg: #f8fafc;
      --anim-logo-bg: #ffffff;
      --anim-text-color: #0f172a;
      --anim-subtext-color: #64748b;
    }

    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      font-family: 'Inter', -apple-system, sans-serif;
      background-color: var(--bg-dark);
      color: var(--text-main);
      min-height: 100vh;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      padding: 24px 16px;
      -webkit-font-smoothing: antialiased;
      overflow-x: hidden;
      position: relative;
    }

    .bg-grid {
      position: fixed; inset: 0;
      background-image: 
        linear-gradient(rgba(255, 255, 255, 0.015) 1px, transparent 1px),
        linear-gradient(90deg, rgba(255, 255, 255, 0.015) 1px, transparent 1px);
      background-size: 32px 32px;
      pointer-events: none;
      z-index: 0;
    }

    /* Container */
    .login-container {
      width: 100%;
      max-width: 860px;
      position: relative;
      z-index: 1;
    }

    /* Brand Header */
    .brand-hero {
      text-align: center;
      margin-bottom: 24px;
    }
    .logo-frame {
      width: 68px; height: 68px; margin: 0 auto 14px;
      border-radius: 18px; background: #0d0c18;
      border: 1.5px solid rgba(139, 92, 246, 0.4);
      display: flex; align-items: center; justify-content: center;
      box-shadow: 0 0 24px rgba(139, 92, 246, 0.45);
      animation: purple-glow 3s ease-in-out infinite alternate;
    }
    @keyframes purple-glow {
      0% { box-shadow: 0 0 16px rgba(139, 92, 246, 0.35); border-color: rgba(139, 92, 246, 0.35); }
      100% { box-shadow: 0 0 28px rgba(139, 92, 246, 0.6); border-color: rgba(168, 85, 247, 0.55); }
    }
    .logo-img { width: 48px; height: 48px; object-fit: cover; border-radius: 12px; }
    .brand-title {
      font-size: 22px; font-weight: 800; letter-spacing: -0.02em; margin-bottom: 4px;
      display: flex; align-items: center; justify-content: center; gap: 8px;
    }
    .brand-badge {
      font-size: 10px; font-weight: 700; text-transform: uppercase;
      padding: 3px 8px; border-radius: 6px;
      background: rgba(139, 92, 246, 0.15); border: 1px solid rgba(139, 92, 246, 0.35);
      color: #c4b5fd;
    }
    .brand-subtitle { font-size: 13px; color: var(--text-muted); }

    /* Login Stage Card (Wide 2-Column or Tabs) */
    .login-card {
      background: var(--bg-card);
      border: 1px solid var(--border-card);
      border-radius: 24px;
      box-shadow: 0 20px 60px rgba(0,0,0,0.5);
      overflow: hidden;
    }

    /* Tabs Header */
    .login-tabs {
      display: flex; border-bottom: 1px solid var(--border-card);
      background: rgba(255, 255, 255, 0.02);
    }
    .tab-btn {
      flex: 1; padding: 16px 20px; font-size: 14px; font-weight: 700;
      background: none; border: none; color: var(--text-muted);
      cursor: pointer; transition: all 0.2s ease;
      display: flex; align-items: center; justify-content: center; gap: 8px;
    }
    .tab-btn.active {
      color: #fff; background: var(--bg-card);
      border-bottom: 2px solid var(--accent-purple);
      box-shadow: inset 0 -2px 0 var(--accent-purple);
    }
    html.theme-light .tab-btn.active { color: #0f172a; }
    .tab-btn:hover:not(.active) { color: var(--text-main); background: rgba(255,255,255,0.04); }

    .tab-content { display: none; padding: 32px; }
    .tab-content.active { display: block; }

    /* QR Code Journey Layout */
    .qr-journey-grid {
      display: grid; grid-template-columns: 1.15fr 0.85fr; gap: 32px;
      align-items: center;
    }
    @media (max-width: 768px) {
      .qr-journey-grid { grid-template-columns: 1fr; gap: 24px; }
    }

    /* Step-by-Step Instructions */
    .steps-list { list-style: none; display: flex; flex-direction: column; gap: 18px; }
    .step-item { display: flex; gap: 14px; }
    .step-num {
      width: 32px; height: 32px; border-radius: 50%;
      background: rgba(139, 92, 246, 0.2); border: 1.5px solid var(--accent-purple);
      color: #c4b5fd; font-weight: 800; font-size: 13px;
      display: flex; align-items: center; justify-content: center; flex-shrink: 0;
    }
    .step-text { font-size: 14px; line-height: 1.5; }
    .step-text strong { color: var(--text-main); }
    .step-sub { font-size: 12px; color: var(--text-muted); margin-top: 2px; }

    /* QR Frame */
    .qr-display-box {
      background: #ffffff; padding: 18px; border-radius: 20px;
      box-shadow: 0 10px 30px rgba(0,0,0,0.4);
      display: flex; flex-direction: column; align-items: center; justify-content: center;
      position: relative; border: 2px solid rgba(139, 92, 246, 0.3);
    }
    .qr-img { width: 220px; height: 220px; object-fit: contain; border-radius: 8px; }
    .qr-scan-line {
      position: absolute; width: calc(100% - 36px); height: 2px;
      background: linear-gradient(90deg, transparent, #8b5cf6, transparent);
      box-shadow: 0 0 12px #8b5cf6;
      animation: scanAnim 2.8s ease-in-out infinite alternate;
      pointer-events: none;
    }
    @keyframes scanAnim {
      0% { top: 22px; }
      100% { top: calc(100% - 22px); }
    }
    .qr-status-pill {
      margin-top: 14px; padding: 6px 14px; border-radius: 20px;
      font-size: 11px; font-weight: 700;
      background: rgba(16, 185, 129, 0.15); color: #059669;
      display: flex; align-items: center; gap: 6px;
    }

    /* TL Portal Card Section */
    .tl-promo-badge {
      display: inline-flex; align-items: center; gap: 6px;
      padding: 6px 14px; border-radius: 20px; font-size: 12px; font-weight: 700;
      background: rgba(139, 92, 246, 0.15); border: 1px solid rgba(139, 92, 246, 0.35);
      color: #c4b5fd; margin-bottom: 14px;
    }
    .tl-form-card {
      max-width: 440px; margin: 0 auto;
    }

    /* Inputs & Buttons */
    .form-group { margin-bottom: 16px; }
    .form-label { display: block; font-size: 12px; font-weight: 600; color: var(--text-muted); margin-bottom: 6px; }
    .input-wrapper { position: relative; }
    .form-input {
      width: 100%; padding: 12px 14px 12px 38px; border-radius: 12px;
      background: rgba(255,255,255,0.04); border: 1px solid var(--border-card);
      color: var(--text-main); font-size: 14px; font-family: inherit;
    }
    .form-input:focus { outline: none; border-color: var(--accent-purple); box-shadow: 0 0 14px rgba(139, 92, 246, 0.3); }
    .input-icon { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: var(--text-muted); pointer-events: none; }
    .pwd-toggle {
      position: absolute; right: 12px; top: 50%; transform: translateY(-50%);
      background: none; border: none; color: var(--text-muted); font-size: 11px; font-weight: 700; cursor: pointer;
    }

    .btn-submit {
      width: 100%; padding: 13px; border-radius: 12px;
      background: linear-gradient(135deg, #8b5cf6, #6d28d9);
      color: #fff; font-size: 14px; font-weight: 700; border: none; cursor: pointer;
      box-shadow: 0 6px 20px rgba(139, 92, 246, 0.4); transition: transform 0.2s, box-shadow 0.2s;
    }
    .btn-submit:hover { transform: translateY(-1px); box-shadow: 0 8px 26px rgba(139, 92, 246, 0.6); }

    /* Footer & Portals Routing */
    .login-footer-routes {
      padding: 18px 32px; background: rgba(255, 255, 255, 0.02);
      border-top: 1px solid var(--border-card);
      display: flex; align-items: center; justify-content: space-between;
      flex-wrap: wrap; gap: 12px; font-size: 12px; color: var(--text-muted);
    }
    .route-link { color: var(--accent-purple); text-decoration: none; font-weight: 600; }
    .route-link:hover { text-decoration: underline; }

    /* 360 Spin Animation Overlay */
    .login-anim-overlay {
      position: fixed; inset: 0; background: var(--anim-overlay-bg);
      z-index: 9999999; display: flex; align-items: center; justify-content: center;
      opacity: 0; visibility: hidden; pointer-events: none;
      transition: opacity 0.25s ease, visibility 0.25s ease;
    }
    .login-anim-overlay.active { opacity: 1; visibility: visible; pointer-events: auto; }
    .anim-scene { display: flex; flex-direction: column; align-items: center; justify-content: center; }
    .anim-logo-box {
      width: 96px; height: 96px; border-radius: 24px;
      background: var(--anim-logo-bg); border: 1.5px solid rgba(139, 92, 246, 0.45);
      display: flex; align-items: center; justify-content: center;
      box-shadow: 0 0 30px rgba(139, 92, 246, 0.5);
    }
    .anim-logo-box img { width: 68px; height: 68px; object-fit: cover; border-radius: 17px; }
    .login-anim-overlay.active .anim-logo-box {
      animation: nunnoSpinZoomOut 1.6s cubic-bezier(0.2, 0.85, 0.25, 1) forwards;
    }
    @keyframes nunnoSpinZoomOut {
      0% { opacity: 0; transform: scale(0.5) rotate(0deg); }
      22% { opacity: 1; transform: scale(1.08) rotate(90deg); }
      50% { opacity: 1; transform: scale(1) rotate(180deg); }
      75% { opacity: 1; transform: scale(1.05) rotate(270deg); }
      88% { opacity: 1; transform: scale(1) rotate(360deg); }
      100% { opacity: 0; transform: scale(0) rotate(360deg); }
    }
    .anim-label { margin-top: 20px; text-align: center; }
    .anim-brand-text { font-size: 20px; font-weight: 800; color: var(--anim-text-color); }
    .anim-subtext { font-size: 13px; color: var(--anim-subtext-color); margin-top: 4px; }
  </style>
</head>
<body>

<div class="bg-grid"></div>

<!-- 360 Spin Animation Overlay -->
<div id="loginAnimOverlay" class="login-anim-overlay" aria-hidden="true">
  <div class="anim-scene">
    <div class="anim-logo-box">
      <img src="assets/images/nunno-logo.png" alt="Platform Logo">
    </div>
    <div class="anim-label">
      <div class="anim-brand-text" id="animBrandText">Nunno Conversational CRM</div>
      <div class="anim-subtext" id="animSubtext">Initializing isolated closer workspace…</div>
    </div>
  </div>
</div>

<main class="login-container">
  <!-- Brand Hero -->
  <header class="brand-hero">
    <div class="logo-frame">
      <img src="assets/images/nunno-logo.png" alt="Platform Logo" class="logo-img">
    </div>
    <h1 class="brand-title">
      Conversational Closing Platform
      <span class="brand-badge">Multi-Tenant 2026</span>
    </h1>
    <p class="brand-subtitle">High-Velocity WhatsApp Sales Pipeline & Automated Settlement</p>
  </header>

  <!-- Login Card with Tabs -->
  <div class="login-card">
    <div class="login-tabs">
      <button class="tab-btn active" id="tabBtnQR" onclick="switchTab('qr')">
        <span>📱</span> Scan QR Code (Closer)
      </button>
      <button class="tab-btn" id="tabBtnTL" onclick="switchTab('tl')">
        <span>👑</span> Continue as Team Leader (TL)
      </button>
      <button class="tab-btn" id="tabBtnDirect" onclick="switchTab('direct')">
        <span>🔑</span> Staff Sign In
      </button>
    </div>

    <!-- TAB 1: CLOSER QR JOURNEY -->
    <div class="tab-content active" id="tabQR">
      <div class="qr-journey-grid">
        <div>
          <div style="font-size: 18px; font-weight: 800; margin-bottom: 6px;">
            Link your Personal WhatsApp Device
          </div>
          <div style="font-size: 13px; color: var(--text-muted); margin-bottom: 22px;">
            Every sales closer runs their own dedicated WhatsApp device. One banned number will never impact other closers.
          </div>

          <ol class="steps-list">
            <li class="step-item">
              <div class="step-num">1</div>
              <div class="step-text">
                <strong>Open WhatsApp</strong> on your mobile phone
                <div class="step-sub">Android or iOS with active internet connection</div>
              </div>
            </li>
            <li class="step-item">
              <div class="step-num">2</div>
              <div class="step-text">
                Tap <strong>Menu (⋮)</strong> or <strong>Settings (⚙️)</strong>
                <div class="step-sub">Located at top right (Android) or bottom right (iPhone)</div>
              </div>
            </li>
            <li class="step-item">
              <div class="step-num">3</div>
              <div class="step-text">
                Select <strong>Linked Devices</strong> and tap <strong>Link a Device</strong>
                <div class="step-sub">Authorize with your fingerprint or face scan if prompted</div>
              </div>
            </li>
            <li class="step-item">
              <div class="step-num">4</div>
              <div class="step-text">
                <strong>Point your camera</strong> at this screen to capture the QR code
                <div class="step-sub">Your closer desk will load automatically within seconds</div>
              </div>
            </li>
          </ol>

          <div style="margin-top: 24px; display: flex; align-items: center; gap: 12px;">
            <button class="btn-submit" style="width: auto; padding: 10px 18px; font-size: 12px; background: rgba(255,255,255,0.06); border: 1px solid var(--border-card);" onclick="refreshQR()">
              🔄 Refresh QR Code
            </button>
            <span style="font-size: 12px; color: var(--text-muted);">
              Need account assistance? Contact your Team Lead.
            </span>
          </div>
        </div>

        <!-- Right QR Box -->
        <div style="display: flex; flex-direction: column; align-items: center;">
          <div class="qr-display-box" id="qrContainer">
            <div class="qr-scan-line"></div>
            <img id="qrImageElement" src="https://api.qrserver.com/v1/create-qr-code/?size=220x220&margin=2&data=OMNIDESK-PAIR-AUTH-DEMO-2026" alt="WhatsApp Pair QR Code" class="qr-img">
          </div>
          <div class="qr-status-pill">
            <span style="display:inline-block; width:8px; height:8px; border-radius:50%; background:#10b981; animation:pulse 1.5s infinite;"></span>
            Auto-refreshing &bull; Ready to Pair
          </div>
        </div>
      </div>
    </div>

    <!-- TAB 2: TEAM LEADER (TL) LOGIN FLOW -->
    <div class="tab-content" id="tabTL">
      <div class="tl-form-card">
        <div style="text-align: center; margin-bottom: 22px;">
          <div class="tl-promo-badge">
            💰 Commission Program: Rs. 500 per TM joining
          </div>
          <h2 style="font-size: 20px; font-weight: 800; margin-bottom: 6px;">Team Leader Command Portal</h2>
          <p style="font-size: 13px; color: var(--text-muted);">
            Provision Team Members under your leadership and track accrued commission balances.
          </p>
        </div>

        <form onsubmit="handleAuthSubmit(event, 'tl')">
          <div class="form-group">
            <label class="form-label">Team Lead Username or Email</label>
            <div class="input-wrapper">
              <input type="text" id="tlUsername" class="form-input" placeholder="e.g. ali or asima" required>
              <svg class="input-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
            </div>
          </div>

          <div class="form-group">
            <label class="form-label">Password</label>
            <div class="input-wrapper">
              <input type="password" id="tlPassword" class="form-input" placeholder="••••••••" required>
              <svg class="input-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="3"></rect><path d="M7 11V7a5 5 0 0110 0v4"></path></svg>
            </div>
          </div>

          <div id="tlErrorBox" style="display: none; padding: 10px; border-radius: 8px; background: rgba(244, 63, 94, 0.2); color: #fb7185; font-size: 13px; margin-bottom: 14px;"></div>

          <button type="submit" id="tlSubmitBtn" class="btn-submit" style="background: linear-gradient(135deg, #10b981, #059669); box-shadow: 0 6px 20px rgba(16, 185, 129, 0.4);">
            Sign in as Team Lead 👑
          </button>

          <!-- 1-Click Quick Fill for TLs -->
          <div style="display: flex; gap: 8px; margin-top: 14px; justify-content: center;">
            <button type="button" style="padding: 5px 10px; font-size: 11px; border-radius: 6px; background: rgba(255,255,255,0.06); border: 1px solid var(--border-card); color: var(--text-muted); cursor: pointer;" onclick="fillCredentials('ali', 'ali123', 'tl')">
              👑 Ali Raza (TL)
            </button>
            <button type="button" style="padding: 5px 10px; font-size: 11px; border-radius: 6px; background: rgba(255,255,255,0.06); border: 1px solid var(--border-card); color: var(--text-muted); cursor: pointer;" onclick="fillCredentials('asima', 'asima123', 'tl')">
              👑 Asima Noor (TL)
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- TAB 3: DIRECT STAFF SIGN IN -->
    <div class="tab-content" id="tabDirect">
      <div class="tl-form-card">
        <div style="text-align: center; margin-bottom: 20px;">
          <h2 style="font-size: 20px; font-weight: 800; margin-bottom: 4px;">Direct Closer / Staff Login</h2>
          <p style="font-size: 13px; color: var(--text-muted);">Already paired your device? Sign in with your assigned credentials.</p>
        </div>

        <form onsubmit="handleAuthSubmit(event, 'direct')">
          <div class="form-group">
            <label class="form-label">Username</label>
            <div class="input-wrapper">
              <input type="text" id="dirUsername" class="form-input" placeholder="e.g. hamza" required>
              <svg class="input-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
            </div>
          </div>

          <div class="form-group">
            <label class="form-label">Password</label>
            <div class="input-wrapper">
              <input type="password" id="dirPassword" class="form-input" placeholder="••••••••" required>
              <svg class="input-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="3"></rect><path d="M7 11V7a5 5 0 0110 0v4"></path></svg>
            </div>
          </div>

          <div id="dirErrorBox" style="display: none; padding: 10px; border-radius: 8px; background: rgba(244, 63, 94, 0.2); color: #fb7185; font-size: 13px; margin-bottom: 14px;"></div>

          <button type="submit" id="dirSubmitBtn" class="btn-submit">
            Enter Workspace 🚀
          </button>

          <!-- Quick Closer Fill -->
          <div style="display: flex; gap: 8px; margin-top: 14px; justify-content: center;">
            <button type="button" style="padding: 5px 10px; font-size: 11px; border-radius: 6px; background: rgba(255,255,255,0.06); border: 1px solid var(--border-card); color: var(--text-muted); cursor: pointer;" onclick="fillCredentials('hamza', 'Agent@123', 'direct')">
              👨‍💼 Hamza Khan (Closer)
            </button>
            <button type="button" style="padding: 5px 10px; font-size: 11px; border-radius: 6px; background: rgba(255,255,255,0.06); border: 1px solid var(--border-card); color: var(--text-muted); cursor: pointer;" onclick="fillCredentials('admin', 'Admin@Omnidesk2026!', 'direct')">
              ⚡ Admin
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Footer Routes -->
    <div class="login-footer-routes">
      <div>
        🏢 Company Manager? <a href="admin.php" class="route-link">Open Company Portal (/admin) →</a>
      </div>
      <div>
        ⚡ Platform Owner? <a href="sadmin.php" class="route-link">Super Admin Command (/sadmin) →</a>
      </div>
    </div>
  </div>
</main>

<script>
function switchTab(tabId) {
  document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
  document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));

  if (tabId === 'qr') {
    document.getElementById('tabBtnQR').classList.add('active');
    document.getElementById('tabQR').classList.add('active');
  } else if (tabId === 'tl') {
    document.getElementById('tabBtnTL').classList.add('active');
    document.getElementById('tabTL').classList.add('active');
  } else if (tabId === 'direct') {
    document.getElementById('tabBtnDirect').classList.add('active');
    document.getElementById('tabDirect').classList.add('active');
  }
}

function fillCredentials(user, pass, tab) {
  if (tab === 'tl') {
    document.getElementById('tlUsername').value = user;
    document.getElementById('tlPassword').value = pass;
  } else {
    document.getElementById('dirUsername').value = user;
    document.getElementById('dirPassword').value = pass;
  }
}

async function refreshQR() {
  try {
    const res = await fetch('api/direct_sender.php?action=get_sender_info');
    const data = await res.json();
    if (data.qr) {
      document.getElementById('qrImageElement').src = data.qr;
    } else {
      document.getElementById('qrImageElement').src = 'https://api.qrserver.com/v1/create-qr-code/?size=220x220&margin=2&data=OMNIDESK-PAIR-' + Date.now();
    }
  } catch (_) {
    document.getElementById('qrImageElement').src = 'https://api.qrserver.com/v1/create-qr-code/?size=220x220&margin=2&data=OMNIDESK-PAIR-' + Date.now();
  }
}

async function handleAuthSubmit(e, tab) {
  e.preventDefault();
  const isTL = (tab === 'tl');
  const user = isTL ? document.getElementById('tlUsername').value.trim() : document.getElementById('dirUsername').value.trim();
  const pass = isTL ? document.getElementById('tlPassword').value.trim() : document.getElementById('dirPassword').value.trim();
  const btn = isTL ? document.getElementById('tlSubmitBtn') : document.getElementById('dirSubmitBtn');
  const errBox = isTL ? document.getElementById('tlErrorBox') : document.getElementById('dirErrorBox');

  btn.disabled = true;
  btn.textContent = 'Verifying credentials…';
  errBox.style.display = 'none';

  try {
    const res = await fetch('api/auth.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'login', username: user, password: pass })
    });
    const data = await res.json();
    if (data.success && data.user) {
      localStorage.setItem('nunno_active_session', JSON.stringify(data.user));
      triggerLoginAnimation(data.user);
    } else {
      errBox.textContent = data.error || 'Invalid credentials.';
      errBox.style.display = 'block';
      btn.disabled = false;
      btn.textContent = isTL ? 'Sign in as Team Lead 👑' : 'Enter Workspace 🚀';
    }
  } catch (err) {
    errBox.textContent = 'Server communication error.';
    errBox.style.display = 'block';
    btn.disabled = false;
    btn.textContent = isTL ? 'Sign in as Team Lead 👑' : 'Enter Workspace 🚀';
  }
}

function triggerLoginAnimation(user) {
  const overlay = document.getElementById('loginAnimOverlay');
  const mainCard = document.querySelector('.login-container');
  const brandText = document.getElementById('animBrandText');
  const subtext = document.getElementById('animSubtext');

  if (user.role === 'lead') {
    brandText.textContent = 'Team Leader Command';
    subtext.textContent = `Welcome back, Lead ${user.name} (Rs. 500 / TM Active)`;
  } else if (user.role === 'admin') {
    brandText.textContent = 'Company Administration';
    subtext.textContent = `Welcome back, Administrator ${user.name}`;
  } else {
    brandText.textContent = 'Closer Sales Desk';
    subtext.textContent = `Welcome back, ${user.name}`;
  }

  if (mainCard) mainCard.style.display = 'none';
  overlay.classList.add('active');

  setTimeout(() => {
    window.location.replace('index.php');
  }, 1600);
}

// Check QR status periodically on page load
document.addEventListener('DOMContentLoaded', () => {
  refreshQR();
  setInterval(refreshQR, 25000);
});
</script>
</body>
</html>
