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

// If action=logout or switch=1 or logout=1 is passed, completely wipe session and redirect to clean root URL
if ((isset($_GET['action']) && $_GET['action'] === 'logout') || isset($_GET['logout']) || isset($_GET['switch'])) {
    $_SESSION = [];
    if (session_id()) {
        @session_unset();
        @session_destroy();
    }
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 86400, '/');
        if (!empty($params['domain'])) {
            setcookie(session_name(), '', time() - 86400, '/', $params['domain']);
        }
    }
    setcookie('nunno_local', '', time() - 86400, '/');
    @session_write_close();
    header('Location: /');
    exit;
} elseif (!empty($_SESSION['omnidesk_user'])) {
    $role = $_SESSION['omnidesk_user']['role'] ?? '';
    if ($role === 'superadmin' && isset($_GET['to_sadmin'])) {
        header('Location: /ss?portal=1');
        exit;
    }
    if ($role === 'admin' && isset($_GET['to_admin'])) {
        header('Location: /cc?portal=1');
        exit;
    }
    // Landing page remains viewable without forced redirect
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
  <title>Chatly — WhatsApp Closing Platform &amp; Multi-Role Gateway</title>
  <link rel="icon" type="image/png" href="assets/images/chatly-icon.png">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Segoe+UI:wght@300;400;500;600;700&family=Inter:wght@400;500;600;700;800;900&family=JetBrains+Mono:wght@500;700&display=swap" rel="stylesheet">
  <script>
    (function(){
      try {
        var q = window.location.search || '';
        if (q.indexOf('action=logout') !== -1 || q.indexOf('logout=1') !== -1 || q.indexOf('switch=1') !== -1 || sessionStorage.getItem('chatly_logged_out') === '1') {
          localStorage.removeItem('nunno_active_session');
          localStorage.removeItem('omnidesk_user');
          localStorage.removeItem('omnidesk_impersonated_from_admin');
          localStorage.removeItem('omnidesk_impersonated_user');
          localStorage.removeItem('omnidesk_impersonated_name');
          localStorage.removeItem('omnidesk_active_chat_phone');
          sessionStorage.clear();
        }
        // Always display clean URL in browser address bar
        if (window.history && window.history.replaceState) {
          if (window.location.pathname.endsWith('login.php') || q) {
            window.history.replaceState({}, document.title, '/');
          }
        }
        if (localStorage.getItem('nunno_theme') === 'light') {
          document.documentElement.classList.add('theme-light');
        }
      } catch(_) {}
    })();

    function handleGeneralLogout(e) {
      if (e) e.preventDefault();
      try {
        localStorage.removeItem('nunno_active_session');
        localStorage.removeItem('omnidesk_user');
        localStorage.removeItem('omnidesk_impersonated_from_admin');
        localStorage.removeItem('omnidesk_impersonated_user');
        localStorage.removeItem('omnidesk_impersonated_name');
        localStorage.removeItem('omnidesk_active_chat_phone');
        sessionStorage.clear();
        sessionStorage.setItem('chatly_logged_out', '1');
      } catch (_) {}
      window.location.href = '/login.php?action=logout';
    }
  </script>
  <style>
    :root {
      --wa-green-header: #0fa968;
      --wa-teal: #0fa968;
      --wa-bg: #050b09;
      --wa-card-bg: #0b1713;
      --wa-card-border: rgba(31, 224, 138, 0.14);
      --wa-text-primary: #effff7;
      --wa-text-secondary: #8db5a3;
      --wa-text-muted: #5c8070;
      --wa-accent: #1fe08a;
      --wa-qr-border: #1fe08a;
      --tl-purple: #2dd4bf;
      --admin-amber: #f59e0b;
      --sadmin-gold: #eab308;
      --anim-overlay-bg: radial-gradient(circle at 50% 40%, #0b2119 0%, #050b09 58%, #030706 100%);
      --anim-aura-bg: radial-gradient(circle, rgba(31, 224, 138, 0.42) 0%, rgba(31, 224, 138, 0.12) 45%, rgba(31, 224, 138, 0) 70%);
      --anim-text-color: #ffffff;
      --anim-subtext-color: #8db5a3;
      --anim-ring-track: rgba(31, 224, 138, 0.12);
      --anim-ring-arc: #1fe08a;
      --anim-tag-bg: rgba(31, 224, 138, 0.14);
      --anim-tag-border: 1px solid rgba(31, 224, 138, 0.4);
      --anim-glow-0: 0 0 22px rgba(31, 224, 138, 0.35);
      --anim-glow-50: 0 0 60px rgba(31, 224, 138, 0.8);
    }

    html.theme-light {
      --wa-green-header: #0fa968;
      --wa-teal: #0fa968;
      --wa-bg: #eaf7f0;
      --wa-card-bg: #ffffff;
      --wa-card-border: rgba(15, 169, 104, 0.16);
      --wa-text-primary: #07140f;
      --wa-text-secondary: #3f6354;
      --wa-text-muted: #7a9a8c;
      --wa-accent: #0fa968;
      --wa-qr-border: #0fa968;
      --tl-purple: #0d9488;
      --anim-overlay-bg: radial-gradient(circle at 50% 38%, #ffffff 0%, #effaf5 60%, #dff5ea 100%);
      --anim-aura-bg: radial-gradient(circle, rgba(31, 224, 138, 0.32) 0%, rgba(31, 224, 138, 0.1) 45%, rgba(31, 224, 138, 0) 70%);
      --anim-text-color: #07140f;
      --anim-subtext-color: #3f6354;
      --anim-ring-track: rgba(15, 169, 104, 0.14);
      --anim-ring-arc: #0fa968;
      --anim-tag-bg: rgba(15, 169, 104, 0.12);
      --anim-tag-border: 1px solid rgba(15, 169, 104, 0.35);
      --anim-glow-0: 0 6px 18px rgba(6, 40, 26, 0.12);
      --anim-glow-50: 0 18px 44px -4px rgba(31, 224, 138, 0.5);
    }
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      font-family: 'Segoe UI', 'Inter', -apple-system, sans-serif;
      background-color: var(--wa-bg);
      background-image: radial-gradient(ellipse 60% 40% at 8% -6%, rgba(31, 224, 138, 0.14), transparent 60%), radial-gradient(ellipse 50% 40% at 100% 0%, rgba(15, 169, 104, 0.1), transparent 60%);
      background-attachment: fixed;
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
      background: radial-gradient(ellipse 70% 120% at 15% -20%, rgba(31, 224, 138, 0.38), transparent 62%), linear-gradient(180deg, #08241a 0%, rgba(5, 11, 9, 0) 100%);
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
    .wa-logo-icon svg { width: 24px; height: 24px; fill: var(--wa-accent); }
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
      scrollbar-width: none;
      -ms-overflow-style: none;
    }
    .role-nav-bar::-webkit-scrollbar {
      display: none;
      width: 0;
      height: 0;
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
      background: linear-gradient(135deg, rgba(31, 224, 138, 0.15), rgba(45, 232, 154, 0.08));
      border: 1.5px solid rgba(31, 224, 138, 0.35);
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
      box-shadow: 0 8px 24px rgba(31, 224, 138, 0.25);
      border-color: rgba(31, 224, 138, 0.6);
    }
    .tl-cta-badge {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      font-size: 11px;
      font-weight: 800;
      text-transform: uppercase;
      color: #8ff5c8;
      background: rgba(31, 224, 138, 0.25);
      padding: 2px 8px;
      border-radius: 4px;
      margin-bottom: 4px;
    }
    .tl-cta-title {
      font-size: 14px;
      font-weight: 700;
      color: #fff;
    }
    html.theme-light .tl-cta-title { color: #065f3c; }
    html.theme-light .tl-cta-badge { color: #065f3c; background: rgba(15, 169, 104, 0.16); }
    .tl-cta-sub {
      font-size: 12px;
      color: var(--wa-text-secondary);
    }
    .tl-cta-btn {
      padding: 8px 16px;
      border-radius: 8px;
      background: #14c27a;
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
      border: 3px solid rgba(31, 224, 138, 0.3);
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
      border: 2px solid var(--wa-accent);
      display: flex;
      align-items: center;
      justify-content: center;
      box-shadow: 0 4px 12px rgba(0,0,0,0.2);
    }
    .wa-qr-center-logo svg {
      width: 24px;
      height: 24px;
      fill: var(--wa-accent);
    }
    .qr-scan-line {
      position: absolute;
      width: calc(100% - 36px);
      height: 2px;
      background: linear-gradient(90deg, transparent, var(--wa-accent), transparent);
      box-shadow: 0 0 12px var(--wa-accent);
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
      color: var(--wa-accent);
      display: flex;
      align-items: center;
      gap: 6px;
    }
    .pulse-dot {
      width: 8px;
      height: 8px;
      border-radius: 50%;
      background: var(--wa-accent);
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
      background: rgba(31, 224, 138, 0.15);
      color: var(--wa-accent);
      border-color: var(--wa-accent);
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
      box-shadow: 0 0 10px rgba(31, 224, 138, 0.25);
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
      background: linear-gradient(135deg, #14c27a, #0b8a57);
      box-shadow: 0 6px 20px rgba(31, 224, 138, 0.35);
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

    /* Chatly launch loader (icon pop -> progress ring -> send-off) */
    .login-anim-overlay {
      position: fixed; inset: 0; background: var(--anim-overlay-bg);
      z-index: 9999999; display: flex; align-items: center; justify-content: center;
      opacity: 0; visibility: hidden; pointer-events: none; overflow: hidden;
      transition: opacity 0.25s ease, visibility 0.25s ease;
    }
    .login-anim-overlay.active { opacity: 1; visibility: visible; pointer-events: auto; }
    .anim-aura { position: absolute; width: 360px; height: 360px; border-radius: 50%; background: var(--anim-aura-bg); filter: blur(36px); opacity: 0; transform: scale(0.7); pointer-events: none; }
    .login-anim-overlay.active .anim-aura { animation: chatlyAura 1.5s ease-out forwards; }
    @keyframes chatlyAura { 0% { opacity: 0; transform: scale(0.7); } 25% { opacity: 1; transform: scale(1.1); } 80% { opacity: 1; transform: scale(1.2); } 100% { opacity: 0; transform: scale(1.6); } }
    .anim-scene { display: flex; flex-direction: column; align-items: center; position: relative; z-index: 2; }
    .anim-logo-box { position: relative; width: 112px; height: 112px; display: flex; align-items: center; justify-content: center; will-change: transform, opacity; }
    .anim-logo-box::after { content: ''; position: absolute; width: 76px; height: 76px; border-radius: 24px; border: 2px solid var(--anim-ring-arc); opacity: 0; pointer-events: none; }
    .anim-ring { position: absolute; inset: 0; width: 100%; height: 100%; transform: rotate(-90deg); overflow: visible; }
    .anim-ring circle { fill: none; stroke-width: 3; }
    .anim-ring-track { stroke: var(--anim-ring-track); }
    .anim-ring-arc { stroke: var(--anim-ring-arc); stroke-linecap: round; stroke-dasharray: 339.3; stroke-dashoffset: 339.3; filter: drop-shadow(0 0 6px rgba(31, 224, 138, 0.5)); }
    .anim-logo-img { width: 76px; height: 76px; object-fit: contain; border-radius: 22px; display: block; box-shadow: var(--anim-glow-0); }
    .login-anim-overlay.active .anim-logo-box { animation: chatlyLaunch 1.5s cubic-bezier(0.2, 0.85, 0.25, 1) forwards; }
    .login-anim-overlay.active .anim-logo-box::after { animation: chatlyPulse 1.5s ease-out forwards; }
    .login-anim-overlay.active .anim-ring-arc { animation: chatlyRing 1.5s cubic-bezier(0.45, 0.05, 0.25, 1) forwards; }
    .login-anim-overlay.active .anim-logo-img { animation: chatlyGlow 1.5s ease-in-out forwards; }
    @keyframes chatlyLaunch {
      0% { opacity: 0; transform: scale(0.55); }
      16% { opacity: 1; transform: scale(1.08); }
      28% { opacity: 1; transform: scale(1); }
      72% { opacity: 1; transform: scale(1) translate(0, -3px); }
      82% { opacity: 1; transform: scale(0.94) translate(-6px, 4px) rotate(-4deg); }
      100% { opacity: 0; transform: scale(0.45) translate(70px, -120px) rotate(-14deg); }
    }
    @keyframes chatlyPulse { 0%, 12% { opacity: 0; transform: scale(1); } 20% { opacity: 0.85; transform: scale(1); } 60% { opacity: 0; transform: scale(1.9); } 100% { opacity: 0; transform: scale(1.9); } }
    @keyframes chatlyRing { 0%, 14% { stroke-dashoffset: 339.3; opacity: 1; } 78% { stroke-dashoffset: 0; opacity: 1; } 100% { stroke-dashoffset: 0; opacity: 0; } }
    @keyframes chatlyGlow { 0% { box-shadow: var(--anim-glow-0); } 45% { box-shadow: var(--anim-glow-50); } 100% { box-shadow: var(--anim-glow-0); } }
    .anim-label { margin-top: 18px; display: flex; flex-direction: column; align-items: center; gap: 7px; }
    .login-anim-overlay.active .anim-label { animation: chatlyLabel 1.5s cubic-bezier(0.2, 0.85, 0.25, 1) forwards; }
    @keyframes chatlyLabel { 0% { opacity: 0; transform: translateY(12px) scale(0.94); } 22% { opacity: 1; transform: translateY(0) scale(1); } 80% { opacity: 1; transform: translateY(0) scale(1); } 100% { opacity: 0; transform: translateY(-8px) scale(0.96); } }
    .anim-brand-text { font-size: 21px; font-weight: 800; color: var(--anim-text-color); letter-spacing: -0.03em; text-align: center; }
    .anim-subtext { font-size: 13.5px; font-weight: 500; color: var(--anim-subtext-color); text-align: center; }
    .anim-dots { display: inline-flex; align-items: center; gap: 5px; margin-top: 4px; padding: 7px 11px; border-radius: 14px 14px 14px 4px; background: var(--anim-tag-bg); border: var(--anim-tag-border); }
    .anim-dots i { width: 6px; height: 6px; border-radius: 50%; background: var(--anim-ring-arc); animation: chatlyDot 1s infinite ease-in-out both; }
    .anim-dots i:nth-child(1) { animation-delay: -0.32s; } .anim-dots i:nth-child(2) { animation-delay: -0.16s; }
    @keyframes chatlyDot { 0%, 80%, 100% { transform: translateY(0); opacity: 0.4; } 40% { transform: translateY(-5px); opacity: 1; } }
    @media (prefers-reduced-motion: reduce) {
      .login-anim-overlay.active .anim-logo-box, .login-anim-overlay.active .anim-logo-box::after, .login-anim-overlay.active .anim-aura, .login-anim-overlay.active .anim-logo-img { animation: none; opacity: 1; }
      .login-anim-overlay.active .anim-ring-arc { animation: none; stroke-dashoffset: 0; }
    }

    html.theme-light .wa-top-header {
      background: radial-gradient(ellipse 70% 120% at 15% -20%, rgba(31, 224, 138, 0.45), transparent 62%), linear-gradient(180deg, #0b3d2a 0%, #0a5a3a 100%);
    }

    /* Chatly logo + primary button contrast */
    .chatly-header-logo { height: 38px; width: auto; display: block; filter: drop-shadow(0 4px 14px rgba(31, 224, 138, 0.35)); }
    .btn-action-primary { color: #03130c; font-weight: 800; }
    .btn-admin { color: #1a1000; }
    .role-tab-btn.active { box-shadow: inset 0 -2px 18px -10px currentColor; }

    /* ═════════ CHATLY LANDING SECTIONS ═════════ */
    html { scroll-behavior: smooth; }
    .wa-top-header { height: 76px !important; padding: 16px 40px !important; align-items: center !important; z-index: 20 !important; position: fixed !important; backdrop-filter: blur(14px); -webkit-backdrop-filter: blur(14px); background: linear-gradient(180deg, rgba(8, 36, 26, 0.88), rgba(5, 11, 9, 0.72)) !important; border-bottom: 1px solid rgba(31, 224, 138, 0.16); }
    html.theme-light .wa-top-header { background: linear-gradient(180deg, rgba(11, 61, 42, 0.95), rgba(10, 90, 58, 0.92)) !important; }
    .lp-nav { display: flex; align-items: center; gap: 22px; }
    .lp-nav a { color: rgba(255, 255, 255, 0.78); text-decoration: none; font-size: 13px; font-weight: 600; transition: color 0.15s; }
    .lp-nav a:hover { color: #1fe08a; }
    .lp-nav .lp-nav-cta { padding: 7px 16px; border-radius: 999px; background: linear-gradient(135deg, #4cecab, #1fe08a 50%, #0fa968); color: #03130c; font-weight: 800; box-shadow: 0 4px 16px rgba(31, 224, 138, 0.35); }
    .lp-nav .lp-nav-cta:hover { color: #03130c; filter: brightness(1.07); }
    @media (max-width: 860px) { .lp-nav a:not(.lp-nav-cta) { display: none; } .wa-top-header { padding: 14px 16px !important; } .wa-brand-tag { display: none; } }

    .lp-section { position: relative; z-index: 1; width: 100%; max-width: 1180px; margin: 0 auto; padding: 0 20px; }
    .lp-eyebrow { display: inline-flex; align-items: center; gap: 8px; padding: 5px 13px; border-radius: 999px; background: rgba(31, 224, 138, 0.12); border: 1px solid rgba(31, 224, 138, 0.35); color: var(--wa-accent); font-size: 11.5px; font-weight: 800; letter-spacing: 0.08em; text-transform: uppercase; }
    .lp-eyebrow i { width: 7px; height: 7px; border-radius: 50%; background: var(--wa-accent); box-shadow: 0 0 10px var(--wa-accent); animation: pulseAnim 1.6s infinite; }
    .lp-h2 { font-size: clamp(26px, 3.6vw, 40px); font-weight: 900; letter-spacing: -0.03em; line-height: 1.12; margin: 14px 0 10px; color: var(--wa-text-primary); }
    .lp-lead { font-size: 16px; line-height: 1.6; color: var(--wa-text-secondary); max-width: 640px; }
    .lp-center { text-align: center; } .lp-center .lp-lead { margin: 0 auto; }
    .lp-grad { background: linear-gradient(135deg, #4cecab, #1fe08a 50%, #0fa968); -webkit-background-clip: text; background-clip: text; color: transparent; }
    html.theme-light .lp-grad { background: linear-gradient(135deg, #0fa968, #0b8a57); -webkit-background-clip: text; background-clip: text; }
    .lp-btn { display: inline-flex; align-items: center; justify-content: center; gap: 8px; padding: 14px 24px; border-radius: 14px; font-size: 15px; font-weight: 800; border: 0; cursor: pointer; text-decoration: none; transition: transform 0.15s, box-shadow 0.15s, filter 0.15s; }
    .lp-btn:hover { transform: translateY(-2px); }
    .lp-btn-primary { background: linear-gradient(135deg, #4cecab, #1fe08a 50%, #0fa968); color: #03130c; box-shadow: 0 10px 30px -6px rgba(31, 224, 138, 0.5); }
    .lp-btn-ghost { background: rgba(31, 224, 138, 0.08); color: var(--wa-text-primary); border: 1px solid rgba(31, 224, 138, 0.3); }
    .lp-btn-ghost:hover { background: rgba(31, 224, 138, 0.16); }

    /* Hero */
    .lp-hero { padding-top: 138px; padding-bottom: 36px; display: grid; grid-template-columns: 1.08fr 0.92fr; gap: 48px; align-items: center; }
    .lp-hero h1 { font-size: clamp(34px, 5.4vw, 64px); font-weight: 900; letter-spacing: -0.04em; line-height: 1.04; margin: 18px 0 18px; color: var(--wa-text-primary); }
    .lp-hero .lp-lead { font-size: 17.5px; max-width: 560px; }
    .lp-cta-row { display: flex; flex-wrap: wrap; gap: 12px; margin-top: 28px; }
    .lp-trust { display: flex; flex-wrap: wrap; gap: 8px 20px; margin-top: 26px; font-size: 13px; color: var(--wa-text-secondary); font-weight: 600; }
    .lp-trust span::before { content: '\2713'; color: var(--wa-accent); font-weight: 900; margin-right: 6px; }
    @media (max-width: 920px) { .lp-hero { grid-template-columns: 1fr; padding-top: 112px; gap: 36px; } }

    
    /* Hero Login Card & QR Desk */
    .hero-login-card {
      position: relative;
      width: min(100%, 390px);
      margin: 0 auto;
      border-radius: 24px;
      background: rgba(11, 23, 19, 0.92);
      border: 1px solid rgba(31, 224, 138, 0.3);
      box-shadow: 0 30px 70px -20px rgba(0, 0, 0, 0.7), 0 0 50px -15px rgba(31, 224, 138, 0.35);
      backdrop-filter: blur(18px);
      -webkit-backdrop-filter: blur(18px);
      padding: 24px 22px;
      display: flex;
      flex-direction: column;
      gap: 10px;
    }
    html.theme-light .hero-login-card {
      background: rgba(255, 255, 255, 0.96);
      border-color: rgba(16, 185, 129, 0.28);
      box-shadow: 0 20px 50px -15px rgba(0, 0, 0, 0.12), 0 0 30px -10px rgba(16, 185, 129, 0.2);
    }
    .hero-qr-header { text-align: center; margin-bottom: 8px; }
    .hero-qr-badge {
      display: inline-block;
      font-size: 10px;
      font-weight: 800;
      text-transform: uppercase;
      letter-spacing: 0.06em;
      padding: 3px 10px;
      border-radius: 999px;
      background: rgba(31, 224, 138, 0.12);
      color: var(--wa-accent);
      border: 1px solid rgba(31, 224, 138, 0.3);
      margin-bottom: 5px;
    }
    .hero-qr-title {
      font-size: 17px;
      font-weight: 850;
      color: var(--wa-text-primary);
      margin: 0;
      letter-spacing: -0.02em;
    }
    .hero-login-card .wa-qr-frame {
      margin: 0 auto;
      width: 220px;
      height: 220px;
      padding: 8px;
      border-radius: 18px;
      background: #ffffff;
      border: 1px solid rgba(31, 224, 138, 0.35);
      box-shadow: 0 8px 24px rgba(0, 0, 0, 0.15);
      position: relative;
      display: flex;
      align-items: center;
      justify-content: center;
    }
    .hero-login-card .wa-qr-img { width: 100%; height: 100%; object-fit: contain; border-radius: 12px; }
    .btn-continue-pwd {
      width: 100%;
      padding: 10px 14px;
      border-radius: 12px;
      background: rgba(31, 224, 138, 0.08);
      border: 1px solid rgba(31, 224, 138, 0.28);
      color: var(--wa-text-primary);
      font-size: 12.5px;
      font-weight: 750;
      cursor: pointer;
      transition: all 0.2s ease;
    }
    .btn-continue-pwd:hover {
      background: rgba(31, 224, 138, 0.18);
      border-color: var(--wa-accent);
      color: var(--wa-accent);
    }
    .user-pass-role-switch {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 8px;
      margin-bottom: 14px;
    }
    .user-pass-role-switch .role-tab-btn {
      background: rgba(255, 255, 255, 0.04);
      border: 1px solid var(--wa-card-border);
      color: var(--wa-text-secondary);
      border-radius: 10px;
      font-size: 12px;
      padding: 9px 6px;
      font-weight: 800;
      text-align: center;
      cursor: pointer;
      transition: all 0.18s ease;
    }
    .user-pass-role-switch .role-tab-btn.active {
      background: rgba(31, 224, 138, 0.16);
      border-color: var(--wa-accent);
      color: var(--wa-accent);
      box-shadow: 0 0 16px -4px var(--wa-accent);
    }

    /* Hero visual: animated chat */
    .lp-visual { position: relative; display: flex; justify-content: center; padding: 18px 0; }
    .lp-visual::before { content: ''; position: absolute; width: 380px; height: 380px; border-radius: 50%; background: radial-gradient(circle, rgba(31, 224, 138, 0.35), rgba(31, 224, 138, 0) 68%); filter: blur(30px); top: 50%; left: 50%; transform: translate(-50%, -50%); animation: chatlyFloat 6s ease-in-out infinite; }
    .lp-chat { position: relative; width: min(100%, 390px); border-radius: 26px; background: rgba(11, 23, 19, 0.86); border: 1px solid rgba(31, 224, 138, 0.28); box-shadow: 0 30px 70px -20px rgba(0, 0, 0, 0.7), 0 0 0 1px rgba(31, 224, 138, 0.06), 0 0 60px -18px rgba(31, 224, 138, 0.45); backdrop-filter: blur(16px); overflow: hidden; }
    html.theme-light .lp-chat { background: rgba(255, 255, 255, 0.92); }
    .lp-chat-head { display: flex; align-items: center; gap: 11px; padding: 14px 16px; border-bottom: 1px solid var(--wa-card-border); }
    .lp-chat-head img { width: 38px; height: 38px; border-radius: 11px; }
    .lp-chat-head b { display: block; font-size: 14px; color: var(--wa-text-primary); }
    .lp-chat-head small { font-size: 11.5px; color: var(--wa-accent); font-weight: 700; }
    .lp-chat-body { padding: 18px 14px 20px; min-height: 330px; display: flex; flex-direction: column; gap: 10px; }
    .cm-b { max-width: 82%; padding: 10px 14px; font-size: 13.5px; line-height: 1.4; font-weight: 600; opacity: 0; transform: translateY(12px) scale(0.94); }
    .cm-in { align-self: flex-start; border-radius: 16px 16px 16px 4px; background: rgba(31, 224, 138, 0.1); border: 1px solid rgba(31, 224, 138, 0.16); color: var(--wa-text-primary); }
    .cm-out { align-self: flex-end; border-radius: 16px 16px 4px 16px; background: linear-gradient(135deg, #2de89a, #0fa968); color: #03130c; box-shadow: 0 6px 18px -4px rgba(31, 224, 138, 0.45); }
    .cm-deal { align-self: center; margin-top: 6px; padding: 9px 18px; border-radius: 999px; background: #03130c; color: #1fe08a; border: 1px solid rgba(31, 224, 138, 0.6); font-size: 12.5px; font-weight: 900; letter-spacing: 0.06em; box-shadow: 0 0 26px -4px rgba(31, 224, 138, 0.7); opacity: 0; transform: scale(0.8); }
    html.theme-light .cm-deal { background: #0b3d2a; }
    .cm-b:nth-child(1) { animation: cm1 11s infinite; } .cm-b:nth-child(2) { animation: cm2 11s infinite; }
    .cm-b:nth-child(3) { animation: cm3 11s infinite; } .cm-b:nth-child(4) { animation: cm4 11s infinite; }
    .cm-deal { animation: cm5 11s infinite; }
    @keyframes cm1 { 0%, 4% { opacity: 0; transform: translateY(12px) scale(0.94); } 8%, 92% { opacity: 1; transform: none; } 97%, 100% { opacity: 0; transform: translateY(-6px) scale(0.97); } }
    @keyframes cm2 { 0%, 16% { opacity: 0; transform: translateY(12px) scale(0.94); } 20%, 92% { opacity: 1; transform: none; } 97%, 100% { opacity: 0; transform: translateY(-6px) scale(0.97); } }
    @keyframes cm3 { 0%, 30% { opacity: 0; transform: translateY(12px) scale(0.94); } 34%, 92% { opacity: 1; transform: none; } 97%, 100% { opacity: 0; transform: translateY(-6px) scale(0.97); } }
    @keyframes cm4 { 0%, 44% { opacity: 0; transform: translateY(12px) scale(0.94); } 48%, 92% { opacity: 1; transform: none; } 97%, 100% { opacity: 0; transform: translateY(-6px) scale(0.97); } }
    @keyframes cm5 { 0%, 58% { opacity: 0; transform: scale(0.7); } 63% { opacity: 1; transform: scale(1.12); } 67%, 92% { opacity: 1; transform: scale(1); } 97%, 100% { opacity: 0; transform: scale(0.9); } }
    .lp-chip { position: absolute; padding: 9px 14px; border-radius: 14px; font-size: 12.5px; font-weight: 800; background: rgba(11, 23, 19, 0.92); color: var(--wa-text-primary); border: 1px solid rgba(31, 224, 138, 0.3); box-shadow: 0 14px 30px -10px rgba(0, 0, 0, 0.6); backdrop-filter: blur(10px); white-space: nowrap; }
    html.theme-light .lp-chip { background: rgba(255, 255, 255, 0.95); }
    .lp-chip b { color: var(--wa-accent); }
    .lp-chip.a { top: -10px; left: -26px; animation: chatlyFloat 5s ease-in-out infinite; }
    .lp-chip.b { bottom: 34px; right: -6px; animation: chatlyFloat 6s ease-in-out -2s infinite; }
    @media (max-width: 480px) { .lp-chip.a { left: 0; top: -14px; } .lp-chip.b { right: 0; } }

    /* Stats */
    .lp-stats { margin-top: 18px; padding: 26px; border-radius: 24px; background: linear-gradient(135deg, rgba(31, 224, 138, 0.1), rgba(31, 224, 138, 0.03)); border: 1px solid rgba(31, 224, 138, 0.22); display: grid; grid-template-columns: repeat(5, 1fr); gap: 8px; box-shadow: 0 20px 60px -24px rgba(0, 0, 0, 0.5); }
    .lp-stat { text-align: center; padding: 8px 6px; position: relative; }
    .lp-stat + .lp-stat::before { content: ''; position: absolute; left: 0; top: 18%; bottom: 18%; width: 1px; background: rgba(31, 224, 138, 0.2); }
    .lp-stat b { display: block; white-space: nowrap; font-size: clamp(22px, 2.7vw, 34px); font-weight: 900; letter-spacing: -0.03em; color: var(--wa-text-primary); font-variant-numeric: tabular-nums; }
    .lp-stat b em { font-style: normal; color: var(--wa-accent); }
    .lp-stat > span { display: block; margin-top: 4px; font-size: 12.5px; font-weight: 600; color: var(--wa-text-secondary); }
    @media (max-width: 860px) { .lp-stats { grid-template-columns: repeat(2, 1fr); gap: 18px 8px; } .lp-stat + .lp-stat::before { display: none; } .lp-stat:last-child { grid-column: 1 / -1; } }

    /* Generic block spacing */
    .lp-block { padding-top: 84px; }
    .lp-grid3 { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; margin-top: 38px; }
    @media (max-width: 920px) { .lp-grid3 { grid-template-columns: repeat(2, 1fr); } }
    @media (max-width: 580px) { .lp-grid3 { grid-template-columns: 1fr; } }
    .lp-card { padding: 24px; border-radius: 20px; background: var(--wa-card-bg); border: 1px solid var(--wa-card-border); transition: transform 0.2s, border-color 0.2s, box-shadow 0.2s; position: relative; overflow: hidden; }
    .lp-card:hover { transform: translateY(-4px); border-color: rgba(31, 224, 138, 0.45); box-shadow: 0 18px 44px -18px rgba(31, 224, 138, 0.35); }
    .lp-ico { width: 46px; height: 46px; border-radius: 14px; display: flex; align-items: center; justify-content: center; font-size: 22px; background: linear-gradient(135deg, rgba(31, 224, 138, 0.22), rgba(31, 224, 138, 0.06)); border: 1px solid rgba(31, 224, 138, 0.3); margin-bottom: 14px; }
    .lp-card h3 { font-size: 17px; font-weight: 800; margin-bottom: 6px; color: var(--wa-text-primary); letter-spacing: -0.01em; }
    .lp-card p { font-size: 14px; line-height: 1.55; color: var(--wa-text-secondary); }

    /* How it works */
    .lp-steps { display: grid; grid-template-columns: repeat(3, 1fr); gap: 18px; margin-top: 40px; counter-reset: st; position: relative; }
    @media (max-width: 820px) { .lp-steps { grid-template-columns: 1fr; } }
    .lp-step { padding: 26px 24px; border-radius: 20px; background: var(--wa-card-bg); border: 1px solid var(--wa-card-border); position: relative; }
    .lp-step::before { counter-increment: st; content: counter(st); display: flex; align-items: center; justify-content: center; width: 40px; height: 40px; border-radius: 50%; font-size: 17px; font-weight: 900; color: #03130c; background: linear-gradient(135deg, #4cecab, #0fa968); box-shadow: 0 6px 18px -4px rgba(31, 224, 138, 0.55); margin-bottom: 16px; }
    .lp-step h3 { font-size: 18px; font-weight: 800; margin-bottom: 6px; color: var(--wa-text-primary); }
    .lp-step p { font-size: 14px; line-height: 1.55; color: var(--wa-text-secondary); }

    /* Brands vs closers */
    .lp-split { display: grid; grid-template-columns: 1fr 1fr; gap: 18px; margin-top: 38px; }
    @media (max-width: 820px) { .lp-split { grid-template-columns: 1fr; } }
    .lp-panel { padding: 32px 30px; border-radius: 24px; border: 1px solid var(--wa-card-border); background: var(--wa-card-bg); position: relative; overflow: hidden; }
    .lp-panel.brand { background: linear-gradient(145deg, rgba(31, 224, 138, 0.16), rgba(31, 224, 138, 0.04) 60%), var(--wa-card-bg); border-color: rgba(31, 224, 138, 0.35); }
    .lp-panel h3 { font-size: 24px; font-weight: 900; letter-spacing: -0.02em; margin: 12px 0 14px; color: var(--wa-text-primary); }
    .lp-panel ul { list-style: none; display: flex; flex-direction: column; gap: 11px; margin-bottom: 24px; }
    .lp-panel li { display: flex; gap: 10px; font-size: 14.5px; line-height: 1.45; color: var(--wa-text-secondary); }
    .lp-panel li::before { content: '\2713'; flex: none; width: 22px; height: 22px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 900; color: #03130c; background: var(--wa-accent); margin-top: 1px; }

    /* Brands strip */
    .lp-brands { display: flex; flex-wrap: wrap; justify-content: center; gap: 14px; margin-top: 30px; }
    .lp-brand-tile { display: flex; align-items: center; gap: 12px; padding: 12px 20px 12px 12px; border-radius: 16px; background: var(--wa-card-bg); border: 1px solid var(--wa-card-border); font-weight: 800; font-size: 15px; color: var(--wa-text-primary); }
    .lp-brand-tile img { width: 38px; height: 38px; border-radius: 11px; display: block; }
    .lp-brand-tile.more { color: var(--wa-text-secondary); border-style: dashed; padding: 12px 22px; }

    /* Final CTA */
    .lp-final { margin-top: 90px; padding: 54px 28px; border-radius: 30px; text-align: center; position: relative; overflow: hidden; background: radial-gradient(ellipse 70% 120% at 50% 0%, rgba(31, 224, 138, 0.28), transparent 65%), linear-gradient(180deg, #0a1f17, #060f0b); border: 1px solid rgba(31, 224, 138, 0.35); box-shadow: 0 30px 80px -30px rgba(31, 224, 138, 0.35); }
    .lp-final h2 { font-size: clamp(26px, 4vw, 44px); font-weight: 900; letter-spacing: -0.03em; color: #effff7; margin-bottom: 10px; }
    .lp-final p { color: #8db5a3; font-size: 16px; margin-bottom: 26px; }
    .lp-final .lp-cta-row { justify-content: center; margin-top: 0; }
    .lp-final .lp-btn-ghost { color: #effff7; }
    .lp-foot { margin: 56px auto 36px; text-align: center; font-size: 12.5px; color: var(--wa-text-muted); }
    .lp-foot img { height: 26px; margin: 0 auto 10px; display: block; opacity: 0.9; }
    html.theme-light .lp-foot img.dark-logo { display: none; }

    /* Access card section (Top Primary Workspace) */
    #access { position: relative; z-index: 1; padding-top: 104px; padding-bottom: 48px; }
    .lp-access-head { text-align: center; padding-top: 0; margin-bottom: 24px; }
    #access .landing-wrapper { margin-top: 0; margin-bottom: 0; }
    #overview.lp-hero { padding-top: 64px; padding-bottom: 44px; border-top: 1px solid var(--wa-card-border); }
    @media (prefers-reduced-motion: reduce) { .cm-b, .cm-deal { animation: none !important; opacity: 1 !important; transform: none !important; } .lp-visual::before, .lp-chip { animation: none; } }

    /* ═════════ MOBILE: ACCESS CARD ═════════ */
    @media (max-width: 640px) {
      #access { padding-top: 92px; padding-bottom: 28px; }
      #overview.lp-hero { padding-top: 48px; }
      .landing-wrapper { padding: 0 10px; }
      .wa-main-card { border-radius: 20px; }
      .role-nav-bar { display: grid; grid-template-columns: 1fr 1fr; overflow: visible; }
      .role-nav-bar .role-tab-btn { min-width: 0; padding: 12px 6px 11px; font-size: 11.5px; line-height: 1.25; flex-direction: column; gap: 4px; text-align: center; border-bottom: 2px solid transparent; }
      .role-nav-bar .role-tab-btn > span:first-child { font-size: 18px; line-height: 1; }
      .role-nav-bar .role-tab-btn:nth-child(odd) { border-right: 1px solid var(--wa-card-border); }
      .role-nav-bar .role-tab-btn:nth-child(-n+2) { border-bottom: 1px solid var(--wa-card-border); }
      .role-nav-bar .role-tab-btn.active { border-bottom: 2px solid currentColor !important; }
      .tab-pane { padding: 20px 14px 22px; }

      .tm-subtoggle { display: grid !important; grid-template-columns: 1fr 1fr; gap: 8px !important; margin-bottom: 20px !important; }
      .tm-subtoggle .role-tab-btn { min-width: 0; padding: 10px 6px; font-size: 12px; line-height: 1.25; flex-direction: column; gap: 3px; text-align: center; border-radius: 12px !important; border: 1px solid var(--wa-card-border); background: rgba(31, 224, 138, 0.04); }
      .tm-subtoggle .role-tab-btn.active { border-color: var(--wa-accent); background: rgba(31, 224, 138, 0.12); box-shadow: 0 0 18px -6px var(--wa-accent); }

      .wa-journey-layout { gap: 24px; }
      .qr-column { order: -1; width: 100%; }
      .wa-qr-frame { width: min(256px, 100%); height: auto; aspect-ratio: 1 / 1; padding: 12px; border-radius: 20px; }
      .wa-qr-img { width: 100%; height: 100%; }
      .qr-column > div:not(.wa-qr-frame) { max-width: 340px !important; width: 100%; }
      .qr-status-badge { margin-top: 12px; text-align: center; justify-content: center; }
      .qr-checkbox-row { margin-top: 10px; }

      .wa-journey-title { font-size: 19px; font-weight: 800; margin-bottom: 14px; text-align: center; }
      .wa-steps-list { gap: 10px; margin-bottom: 20px; padding: 14px; border-radius: 16px; background: rgba(31, 224, 138, 0.05); border: 1px solid var(--wa-card-border); }
      .wa-step-item { font-size: 13.5px; gap: 12px; align-items: center; line-height: 1.4; }
      .wa-step-num { flex: none; width: 26px; height: 26px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 12px; background: rgba(31, 224, 138, 0.16); color: var(--wa-accent); border: 1px solid rgba(31, 224, 138, 0.35); }

      .tl-cta-banner { flex-direction: column; align-items: stretch; gap: 12px; padding: 16px; margin-top: 4px; }
      .tl-cta-btn { width: 100%; padding: 12px; font-size: 13px; }
      .qr-links { flex-wrap: wrap; justify-content: center; gap: 8px !important; margin-top: 16px !important; }
      .qr-links > span { display: none; }
      .qr-links a { padding: 8px 13px; border-radius: 999px; background: rgba(31, 224, 138, 0.1); border: 1px solid rgba(31, 224, 138, 0.28); font-size: 12.5px; text-decoration: none; }

      .form-container { max-width: 100%; }
      .form-title { font-size: 18px; }
      .wa-card-footer { padding: 16px 14px; flex-direction: column; align-items: center; text-align: center; gap: 10px; }
      .wa-card-footer > div { display: flex; flex-wrap: wrap; justify-content: center; gap: 6px 14px; }
      .lp-access-head { padding-top: 0; margin-bottom: 16px; }
    }  </style>
</head>
<body>

<!-- Green Header Banner -->
<div class="wa-top-header">
  <a href="/" onclick="window.scrollTo({top:0,behavior:'smooth'}); return false;" class="wa-brand-title" style="display:flex; align-items:center; gap:12px; text-decoration:none;" title="Chatly Homepage">
    <img src="assets/images/chatly-logo.png" alt="Chatly" class="chatly-header-logo">
  </a>

  <div style="display:flex; align-items:center; gap:18px;">
    <nav class="lp-nav">
      <?php if (!empty($_SESSION['omnidesk_user'])): 
        $sessUser = $_SESSION['omnidesk_user'];
        $role = $sessUser['role'] ?? '';
        if ($role === 'superadmin') {
            $destUrl = '/ss?portal=1';
            $btnText = '⚡ Master Command Center';
        } elseif ($role === 'admin') {
            $destUrl = '/cc?portal=1&company_id=' . urlencode($sessUser['company_id'] ?? '');
            $btnText = '🏢 Company Admin Portal';
        } else {
            $destUrl = 'index.php';
            $btnText = '⚡ Open Workspace (' . htmlspecialchars($sessUser['name'] ?? 'Closer') . ')';
        }
      ?>
        <a href="<?php echo htmlspecialchars($destUrl); ?>" class="lp-nav-cta" style="background:rgba(31,224,138,0.22); border:1px solid var(--wa-accent); color:var(--wa-accent); font-weight:800;"><?php echo $btnText; ?> &rarr;</a>
        <a href="/login.php?action=logout" onclick="handleGeneralLogout(event)" style="color:#ef4444; font-size:12.5px; font-weight:700;">Logout</a>
      <?php else: ?>
        <a href="#top" class="lp-nav-cta" onclick="focusHeroQR(); return false;">⚡ Workspace Login</a>
      <?php endif; ?>
      <a href="#offer">What we offer</a>
      <a href="#how">How it works</a>
      <a href="#promote" onclick="openPromoteProductModal(); return false;">For brands</a>
    </nav>
    <button onclick="toggleTheme()" style="background:rgba(255,255,255,0.2); border:none; color:#fff; padding:6px 12px; border-radius:20px; font-size:12px; cursor:pointer; font-weight:600;">
      🌓 Theme
    </button>
  </div>
</div>

<!-- Chatly launch loader -->
<div id="loginAnimOverlay" class="login-anim-overlay" aria-hidden="true">
  <div class="anim-aura"></div>
  <div class="anim-scene">
    <div class="anim-logo-box">
      <svg class="anim-ring" viewBox="0 0 120 120" aria-hidden="true"><circle class="anim-ring-track" cx="60" cy="60" r="54"/><circle class="anim-ring-arc" cx="60" cy="60" r="54"/></svg>
      <img src="assets/images/chatly-icon.png" alt="Chatly" class="anim-logo-img">
    </div>
    <div class="anim-label">
      <div class="anim-brand-text" id="animBrandText">Chatly Sales Desk</div>
      <div class="anim-subtext" id="animSubtext">Initializing isolated workspace…</div>
      <div class="anim-dots" aria-hidden="true"><i></i><i></i><i></i></div>
    </div>
  </div>
</div>
<!-- ═══════════ LANDING: HERO & LIVE WORKSPACE DESK ═══════════ -->
<section class="lp-section lp-hero" id="top">
  <!-- Left Column: Headline & Platform Pitch (Image 1) -->
  <div>
    <span class="lp-eyebrow"><i></i> WhatsApp sales closing platform</span>
    <h1>Turn WhatsApp chats into <span class="lp-grad">paying customers.</span></h1>
    <p class="lp-lead">Chatly puts your product in front of a trained team of closers who sell for you on WhatsApp. You pay for promotion &mdash; we bring real conversations, real orders and real revenue.</p>
    <div class="lp-cta-row">
      <a href="#promote" class="lp-btn lp-btn-primary" onclick="openPromoteProductModal(); return false;">Promote my product &rarr;</a>
      <a href="javascript:void(0)" class="lp-btn lp-btn-ghost" onclick="focusHeroQR(); return false;">Start closing as TM</a>
    </div>
    <div class="lp-trust">
      <span>Pay for promotion</span>
      <span>Trained closers</span>
      <span>Live deal tracking</span>
    </div>
  </div>

  <!-- Right Column: Live WhatsApp QR Scanner & TM / TL Login Switcher -->
  <div class="hero-login-card" id="heroLoginCard">
    
    <!-- State 1: QR Scanner (Default View) -->
    <div id="heroQrView">
      <div class="hero-qr-header">
        <span class="hero-qr-badge">⚡ 24/7 Live Closing Desk</span>
        <h3 class="hero-qr-title">Scan QR with WhatsApp</h3>
        <p style="font-size:12px; color:var(--wa-text-secondary); margin:4px 0 0;">Link your closer WhatsApp in seconds to start working</p>
      </div>

      <!-- Live WebSocket QR Frame -->
      <div class="wa-qr-frame">
        <div class="qr-scan-line"></div>
        <img id="landingQrImage" src="data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='230' height='230' viewBox='0 0 230 230'><rect width='230' height='230' fill='%23f1f5f9'/><text x='50%25' y='50%25' dominant-baseline='middle' text-anchor='middle' font-family='sans-serif' font-size='12' fill='%2364748b' font-weight='700'>⚡ Generating QR Code...</text></svg>" alt="WhatsApp QR Code" class="wa-qr-img">
        <div class="wa-qr-center-logo" style="width:40px; height:40px; border-radius:10px; background:#07140f; border:2px solid var(--wa-accent); overflow:hidden; padding:2px; display:flex; align-items:center; justify-content:center; box-shadow:0 4px 14px rgba(0,0,0,0.3);">
          <img src="assets/images/chatly-icon.png" alt="Chatly" style="width:100%; height:100%; object-fit:contain; border-radius:6px;">
        </div>
      </div>

      <span id="qrStatusText" style="display:none;"></span>
      <input type="hidden" id="simPhoneInput" value="">

      <div style="display:flex; align-items:center; justify-content:space-between; margin-top:8px; padding:0 4px;">
        <label class="qr-checkbox-row" style="margin:0;">
          <input type="checkbox" id="keepSignedIn" checked>
          <span style="font-size:12px;">Keep me signed in</span>
        </label>
        <a href="javascript:void(0)" onclick="refreshQRCode(true)" class="footer-link" style="font-size:11.5px;">🔄 Refresh</a>
      </div>

      <!-- Connected WhatsApp Closer Card -->
      <div id="connectedPhoneActionsBox" style="display:none; width:100%; margin-top:12px; background:rgba(31,224,138,0.08); border:1px solid rgba(31,224,138,0.3); border-radius:14px; padding:14px; text-align:center; transition:all 0.25s ease;">
        <div id="connectedCardBadge" style="font-size:10px; text-transform:uppercase; letter-spacing:0.6px; color:var(--wa-accent); font-weight:800; margin-bottom:3px;">
          📱 Connected WhatsApp Closer
        </div>
        <div id="connectedCloserName" style="font-size:15px; font-weight:800; color:var(--wa-text-primary); margin-bottom:2px;">
          Closer Account
        </div>
        <div id="connectedCloserCompany" style="display:none; font-size:11px; font-weight:700; margin-bottom:4px;"></div>
        <div id="connectedCloserPhone" style="font-size:11.5px; color:var(--wa-text-secondary); margin-bottom:8px;"></div>
        <div id="connectedCloserNote" style="font-size:11.5px; line-height:1.45; color:var(--wa-text-secondary); margin-bottom:12px;"></div>
        
        <div id="connectedActionsRow" style="display:flex; gap:8px; justify-content:center;">
          <button type="button" id="btnEnterConnectedCloser" class="btn-action-primary" style="margin:0; padding:9px 14px; font-size:12px; flex:1.4; white-space:nowrap;">
            Enter Desk →
          </button>
          <button type="button" id="btnDisconnectWhatsApp" class="btn-action-secondary" style="margin:0; padding:9px 12px; font-size:12px; background:rgba(239,68,68,0.12); color:#ef4444; border:1px solid rgba(239,68,68,0.28); flex:1; white-space:nowrap;" onclick="disconnectWhatsAppBridge()" title="Disconnect WhatsApp session and show fresh QR code">
            🔄 Disconnect
          </button>
        </div>
      </div>

      <!-- Option below QR: Continue with Username & Password -->
      <div style="margin-top:14px; text-align:center;">
        <button type="button" class="btn-continue-pwd" onclick="toggleHeroLoginMode('password')">
          <span>🔑 Continue with Username &amp; Password &rarr;</span>
        </button>
      </div>
    </div>

    <!-- State 2: Username & Password Login with TM / TL Switch -->
    <div id="heroPasswordView" style="display:none;">
      <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:12px;">
        <button type="button" class="btn-back-qr" onclick="toggleHeroLoginMode('qr')" style="background:none; border:none; color:var(--wa-accent); font-size:12px; font-weight:750; cursor:pointer; display:inline-flex; align-items:center; gap:4px; padding:0;">
          <span>&larr; Back to QR Scanner</span>
        </button>
        <span style="font-size:11px; color:var(--wa-text-muted); font-weight:600;">Credential Login</span>
      </div>

      <!-- Switch between TM (Team Member) and TL (Team Leader) -->
      <div class="user-pass-role-switch">
        <button type="button" class="role-tab-btn active" id="btnSwitchTM" onclick="switchPasswordRole('tm')">
          <span>📱 TM (Team Member)</span>
        </button>
        <button type="button" class="role-tab-btn" id="btnSwitchTL" onclick="switchPasswordRole('tl')">
          <span>👑 TL (Team Leader)</span>
        </button>
      </div>

      <!-- TM Password Form -->
      <div id="heroTMPasswordPane">
        <form id="formTMPasswordLogin" onsubmit="handleTMPasswordLogin(event)">
          <div class="input-group" style="margin-bottom:12px;">
            <label class="input-label" style="font-size:11.5px;">Closer (TM) Username</label>
            <input type="text" id="tmDirectUsername" class="input-field" placeholder="e.g. askari" required style="padding:9px 12px; font-size:13px;">
          </div>
          <div class="input-group" style="margin-bottom:12px;">
            <label class="input-label" style="font-size:11.5px;">Password</label>
            <input type="password" id="tmDirectPassword" class="input-field" placeholder="Password" required style="padding:9px 12px; font-size:13px;">
          </div>
          <div class="error-box" id="tmPasswordLoginError"></div>
          <button type="submit" id="tmDirectLoginBtn" class="btn-action-primary" style="margin-top:6px; padding:10px; font-size:13px; font-weight:800; background:linear-gradient(135deg, #2de89a, #0fa968); box-shadow:0 6px 18px rgba(31, 224, 138, 0.35);">
            Sign In as Closer (TM) 🚀
          </button>
        </form>
      </div>

      <!-- TL Password Form -->
      <div id="heroTLPasswordPane" style="display:none;">
        <form id="formTLLogin" onsubmit="handleTLLogin(event)">
          <div class="input-group" style="margin-bottom:12px;">
            <label class="input-label" style="font-size:11.5px;">Team Lead (TL) Username</label>
            <input type="text" id="tlUsername" class="input-field" placeholder="e.g. ali or asima" required style="padding:9px 12px; font-size:13px;">
          </div>
          <div class="input-group" style="margin-bottom:12px;">
            <label class="input-label" style="font-size:11.5px;">Password</label>
            <input type="password" id="tlPassword" class="input-field" placeholder="ali123 or password" required style="padding:9px 12px; font-size:13px;">
          </div>
          <div class="error-box" id="tlLoginError"></div>
          <button type="submit" id="tlLoginBtn" class="btn-action-primary btn-tl" style="margin-top:6px; padding:10px; font-size:13px; font-weight:800; background:linear-gradient(135deg, #f59e0b, #d97706); box-shadow:0 6px 18px rgba(245, 158, 11, 0.35);">
            Access TL Commission Desk 👑
          </button>
        </form>
      </div>
    </div>

  </div>
</section>

<!-- ═══════════ LANDING: STATS (Image 2) ═══════════ -->
<section class="lp-section" id="stats">
  <div class="lp-stats">
    <div class="lp-stat"><b><span data-count="48500" data-suffix="+">0</span></b><span>Conversations handled</span></div>
    <div class="lp-stat"><b><span data-count="3200" data-suffix="+">0</span></b><span>Deals closed</span></div>
    <div class="lp-stat"><b><span data-count="24.8" data-decimals="1" data-prefix="Rs. " data-suffix="M+">0</span></b><span>Revenue generated for brands</span></div>
    <div class="lp-stat"><b><span data-count="420" data-suffix="+">0</span></b><span>Active closers</span></div>
    <div class="lp-stat"><b><span data-count="85" data-suffix="+">0</span></b><span>Brands promoted</span></div>
  </div>
</section>

<!-- ═══════════ LANDING: WHAT WE OFFER ═══════════ -->
<section class="lp-section lp-block lp-center" id="offer">
  <span class="lp-eyebrow">What we offer</span>
  <h2 class="lp-h2">Everything you need to <span class="lp-grad">sell on WhatsApp</span></h2>
  <p class="lp-lead">One platform for the whole journey &mdash; from the first message to the paid order.</p>
  <div class="lp-grid3" style="text-align:left;">
    <div class="lp-card"><div class="lp-ico">&#129309;</div><h3>Dedicated closers</h3><p>A trained team of closers chats with your buyers and turns interest into confirmed orders.</p></div>
    <div class="lp-card"><div class="lp-ico">&#128172;</div><h3>Shared team inbox</h3><p>Every conversation in one live inbox, with anti-theft lead locking so customers never get double-messaged.</p></div>
    <div class="lp-card"><div class="lp-ico">&#128640;</div><h3>Smart campaigns</h3><p>Reach your audience with campaign message variations, live previews and automatic sending from a linked account.</p></div>
    <div class="lp-card"><div class="lp-ico">&#128202;</div><h3>Live deal pipeline</h3><p>Watch every lead move from fresh to ongoing to paid, with conversion and performance stats in real time.</p></div>
    <div class="lp-card"><div class="lp-ico">&#128176;</div><h3>Commissions &amp; payouts</h3><p>Closers earn on every deal and team leaders earn Rs. 500 per recruited closer, with a built-in earnings portal.</p></div>
    <div class="lp-card"><div class="lp-ico">&#127970;</div><h3>Your own workspace</h3><p>Each brand gets an isolated company workspace and admin portal to manage people, limits and approvals.</p></div>
  </div>
</section>

<!-- ═══════════ LANDING: HOW IT WORKS ═══════════ -->
<section class="lp-section lp-block lp-center" id="how">
  <span class="lp-eyebrow">How it works</span>
  <h2 class="lp-h2">From product to <span class="lp-grad">paid order</span> in three steps</h2>
  <p class="lp-lead">No ad guesswork. You pay for promotion, closers do the selling.</p>
  <div class="lp-steps" style="text-align:left;">
    <div class="lp-step"><h3>List your product</h3><p>Set up your company workspace and tell us what you sell. Approve the closers who will represent your brand.</p></div>
    <div class="lp-step"><h3>Closers start chatting</h3><p>Closers link WhatsApp in seconds, promote your product and handle every conversation in the shared inbox.</p></div>
    <div class="lp-step"><h3>You get customers</h3><p>Track deals in real time. You pay for promotion and keep the customers and revenue that come in.</p></div>
  </div>
</section>

<!-- ═══════════ LANDING: FOR BRANDS / FOR CLOSERS ═══════════ -->
<section class="lp-section lp-block lp-center" id="brands">
  <span class="lp-eyebrow">Two ways to win</span>
  <h2 class="lp-h2">Built for <span class="lp-grad">brands</span> and <span class="lp-grad">closers</span></h2>
  <div class="lp-split" style="text-align:left;">
    <div class="lp-panel brand">
      <span class="lp-eyebrow">For brands</span>
      <h3>Pay for promotion. Get real customers.</h3>
      <ul>
        <li>A trained closer team promotes and sells your product on WhatsApp</li>
        <li>Your own company workspace with a full admin portal</li>
        <li>Live pipeline, conversions and closer performance</li>
        <li>Approve, pause or remove closers any time</li>
      </ul>
      <a href="#promote" class="lp-btn lp-btn-primary" onclick="openPromoteProductModal(); return false;">Promote my product &rarr;</a>
    </div>
    <div class="lp-panel">
      <span class="lp-eyebrow">For closers</span>
      <h3>Turn your WhatsApp into income.</h3>
      <ul>
        <li>Link your WhatsApp by scanning a QR code &mdash; it takes seconds</li>
        <li>Pick a brand, chat with buyers and close deals</li>
        <li>Earn commission on every deal you close</li>
        <li>Team leaders earn Rs. 500 for each closer they recruit</li>
      </ul>
      <a href="#top" class="lp-btn lp-btn-ghost" onclick="scrollToAccess('tm'); return false;">Start as a closer</a>
    </div>
  </div>

  <div style="margin-top:64px;">
    <span class="lp-eyebrow">Brands on Chatly</span>
    <div class="lp-brands">
      <div class="lp-brand-tile"><img src="assets/images/nunno-logo.svg" alt="">Nunno</div>
      <div class="lp-brand-tile"><img src="assets/images/apex-logo.svg" alt="">Apex</div>
      <div class="lp-brand-tile"><img src="assets/images/paktrade-logo.svg" alt="">PakTrade</div>
      <div class="lp-brand-tile more" style="cursor:pointer;" onclick="openPromoteProductModal();" title="Promote your product on Chatly">+ your brand</div>
    </div>
  </div>
</section>

<!-- ═══════════ LANDING: FINAL CTA + FOOTER ═══════════ -->
<section class="lp-section">
  <div class="lp-final">
    <h2>Ready to sell more on WhatsApp?</h2>
    <p>Put your product in front of real buyers &mdash; and let our closers do the selling.</p>
    <div class="lp-cta-row">
      <a href="#promote" class="lp-btn lp-btn-primary" onclick="openPromoteProductModal(); return false;">Start promoting today &rarr;</a>
      <a href="#top" class="lp-btn lp-btn-ghost" onclick="scrollToAccess('tm'); return false;">Join as a closer</a>
    </div>
  </div>
  <div class="lp-foot">
    <img src="assets/images/chatly-logo.png" alt="Chatly" class="dark-logo">
    &copy; Chatly &middot; WhatsApp sales closing platform
  </div>
</section>

<script>
// Landing helpers: jump to the access card on a given role tab, and animate the stat counters.

// Hero Login Card State Switchers
function toggleHeroLoginMode(mode) {
  const qrView = document.getElementById('heroQrView');
  const pwdView = document.getElementById('heroPasswordView');
  if (mode === 'password') {
    if (qrView) qrView.style.display = 'none';
    if (pwdView) pwdView.style.display = 'block';
  } else {
    if (qrView) qrView.style.display = 'block';
    if (pwdView) pwdView.style.display = 'none';
  }
}

function switchPasswordRole(role) {
  const btnTM = document.getElementById('btnSwitchTM');
  const btnTL = document.getElementById('btnSwitchTL');
  const paneTM = document.getElementById('heroTMPasswordPane');
  const paneTL = document.getElementById('heroTLPasswordPane');
  if (role === 'tl') {
    if (btnTL) btnTL.classList.add('active');
    if (btnTM) btnTM.classList.remove('active');
    if (paneTL) paneTL.style.display = 'block';
    if (paneTM) paneTM.style.display = 'none';
  } else {
    if (btnTM) btnTM.classList.add('active');
    if (btnTL) btnTL.classList.remove('active');
    if (paneTM) paneTM.style.display = 'block';
    if (paneTL) paneTL.style.display = 'none';
  }
}

function focusHeroQR() {
  toggleHeroLoginMode('qr');
  window.scrollTo({ top: 0, behavior: 'smooth' });
}

function scrollToAccess(role) {
  if (role === 'admin') {
    window.location.href = 'cc.php';
    return;
  }
  if (role === 'tl') {
    toggleHeroLoginMode('password');
    switchPasswordRole('tl');
    window.scrollTo({ top: 0, behavior: 'smooth' });
    return;
  }
  focusHeroQR();
}

function _origScrollToAccess(role) {
  try { if (role && typeof switchRole === 'function') switchRole(role); } catch (_) {}
  const el = document.querySelector('#access .landing-wrapper') || document.getElementById('access');
  if (el) el.scrollIntoView({ behavior: 'smooth', block: 'start' });
}
(function () {
  const nodes = document.querySelectorAll('[data-count]');
  if (!nodes.length) return;
  const fmt = (n, d) => n.toLocaleString('en-US', { minimumFractionDigits: d, maximumFractionDigits: d });
  const render = (el, v) => {
    const d = +(el.dataset.decimals || 0);
    el.textContent = (el.dataset.prefix || '') + fmt(v, d) + '';
    if (el.dataset.suffix) { const s = document.createElement('em'); s.textContent = el.dataset.suffix; el.appendChild(s); }
  };
  const run = el => {
    const target = parseFloat(el.dataset.count), t0 = performance.now(), dur = 1700;
    const tick = now => {
      const p = Math.min(1, (now - t0) / dur), e = 1 - Math.pow(1 - p, 3);
      render(el, target * e);
      if (p < 1) requestAnimationFrame(tick);
    };
    requestAnimationFrame(tick);
  };
  const reduce = window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches;
  if (reduce || !('IntersectionObserver' in window)) { nodes.forEach(el => render(el, parseFloat(el.dataset.count))); return; }
  const io = new IntersectionObserver(es => es.forEach(en => { if (en.isIntersecting) { run(en.target); io.unobserve(en.target); } }), { threshold: 0.4 });
  nodes.forEach(n => io.observe(n));
})();
</script>


<script>
// Role switcher
function switchRole(role) {
  const roleMap = {
    tm: { btn: 'tabBtnTM', pane: 'paneTM' },
    tl: { btn: 'tabBtnTL', pane: 'paneTL' },
    admin: { btn: 'tabBtnAdmin', pane: 'paneAdmin' },
    sadmin: { btn: 'tabBtnSadmin', pane: 'paneSadmin' }
  };
  Object.keys(roleMap).forEach(r => {
    const btn = document.getElementById(roleMap[r].btn);
    const pane = document.getElementById(roleMap[r].pane);
    if (btn) btn.classList.toggle('active', r === role);
    if (pane) pane.classList.toggle('active', r === role);
  });
}

// TL Mode Switcher (TL is login only)
function toggleTLMode(mode) {
  const formLogin = document.getElementById('formTLLogin');
  if (formLogin) formLogin.style.display = 'block';
}

// 360 Spin Animation Trigger (Optimized for instant, snappy portal transitions)
function triggerLoginAnimation(brandName, subtext, redirectUrl) {
  const overlay = document.getElementById('loginAnimOverlay');
  const txtBrand = document.getElementById('animBrandText');
  const txtSub = document.getElementById('animSubtext');
  if (txtBrand && brandName) txtBrand.textContent = brandName;
  if (txtSub && subtext) txtSub.textContent = subtext;
  if (overlay) overlay.classList.add('active');
  setTimeout(() => {
    window.location.href = redirectUrl;
  }, 380);
}

// Live QR Code Poller & Auto-Pairing
let qrPollTimer = null;
let lastKnownState = '';

async function refreshQRCode(force = false) {
  const qrImg = document.getElementById('landingQrImage');
  const statusTxt = document.getElementById('qrStatusText');
  const scanLine = document.querySelector('.qr-scan-line');
  const box = document.getElementById('connectedPhoneActionsBox');
  try {
    if (force) {
      if (statusTxt) statusTxt.textContent = '🔄 Generating fresh WhatsApp QR code...';
      if (box) box.style.display = 'none';
      if (scanLine) scanLine.style.display = 'block';
      try {
        await fetch('api/direct_sender.php?action=reset_whatsapp', {
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
      if (data.qr && (!data.is_connected || data.state === 'qr')) {
        if (qrImg) {
          qrImg.src = data.qr;
          qrImg.style.display = 'block';
        }
        if (box) box.style.display = 'none';
        if (scanLine) scanLine.style.display = 'block';
        if (statusTxt) statusTxt.textContent = 'Pure WebSocket Bridge • Scan with WhatsApp';
        lastKnownState = 'qr';
      } else if (data.is_connected && data.connected_number) {
        lastKnownState = 'connected';
        showConnectedCloserBox(data.connected_number);
      } else if (data.state === 'idle' || data.state === 'disconnected' || (!data.qr && !data.is_connected)) {
        if (box) box.style.display = 'none';
        if (scanLine) scanLine.style.display = 'block';
        fetch('api/direct_sender.php?action=connect_whatsapp', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ action: 'connect_whatsapp' })
        });
        if (statusTxt) statusTxt.textContent = 'Initializing WhatsApp bridge…';
      } else {
        if (statusTxt) statusTxt.textContent = data.message || 'Connecting to WhatsApp bridge…';
      }
    }
  } catch (err) {
    if (statusTxt) statusTxt.textContent = 'Bridge reconnecting…';
  }
}

let autoModalTriggeredForPhone = null;

async function showConnectedCloserBox(phone) {
  const statusTxt = document.getElementById('qrStatusText');
  const scanLine = document.querySelector('.qr-scan-line');
  if (scanLine) scanLine.style.display = 'none';
  if (statusTxt) statusTxt.innerHTML = '🟢 WhatsApp Paired: <strong>+' + phone + '</strong>';

  const box = document.getElementById('connectedPhoneActionsBox');
  const badgeEl = document.getElementById('connectedCardBadge');
  const nameEl = document.getElementById('connectedCloserName');
  const compEl = document.getElementById('connectedCloserCompany');
  const phoneEl = document.getElementById('connectedCloserPhone');
  const noteEl = document.getElementById('connectedCloserNote');
  const enterBtn = document.getElementById('btnEnterConnectedCloser');
  const discBtn = document.getElementById('btnDisconnectWhatsApp');

  if (!box) return;
  box.style.display = 'block';
  if (phoneEl) phoneEl.textContent = '+' + phone;

  try {
    const res = await fetch('api/auth.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'check_phone', phone: phone })
    });
    const data = await res.json();

    if (data.exists && data.user) {
      const u = data.user;
      const compName = u.company_name || u.company_id || 'Company';
      const isTL = (u.role === 'lead' || (u.designation && u.designation.toLowerCase().includes('lead')));

      if (u.status === 'pending') {
        // CASE 1: Applicant is pending manual approval by company admin
        if (badgeEl) {
          badgeEl.textContent = isTL ? '⏳ TEAM LEADER APPLICATION PENDING APPROVAL' : '⏳ APPLICATION PENDING APPROVAL';
          badgeEl.style.color = '#fbbf24';
        }
        if (nameEl) nameEl.textContent = (u.name || (isTL ? 'Team Leader Applicant' : 'Closer Applicant')) + ' (@' + (u.username || 'applicant') + ')';
        if (compEl) {
          compEl.style.display = 'block';
          compEl.style.color = '#fbbf24';
          compEl.textContent = '🏢 Applied to: ' + compName + (isTL ? ' • Role: Team Leader (TL)' : ' • Role: Sales Closer (TM)');
        }
        if (noteEl) {
          if (isTL) {
            noteEl.innerHTML = 'Your application to join <strong>' + compName + '</strong> as <strong>Team Leader (TL)</strong> is awaiting manual review in <strong>/cc</strong>.<br>You can start recruiting TMs and earning commission bounties once approved.';
          } else {
            noteEl.innerHTML = 'Your application to join <strong>' + compName + '</strong> is awaiting manual review by company administration in <strong>/cc</strong>.<br>You will be able to enter once approved.';
          }
        }
        if (enterBtn) {
          enterBtn.style.display = 'inline-block';
          enterBtn.style.background = 'linear-gradient(135deg, #f59e0b, #d97706)';
          enterBtn.textContent = '🔄 Check Status';
          enterBtn.onclick = function() {
            showConnectedCloserBox(phone);
          };
        }
      } else if (u.status === 'active') {
        // CASE 2: Approved active closer or team lead
        if (badgeEl) {
          badgeEl.textContent = isTL ? '🚀 VERIFIED TEAM LEADER (TL)' : '🟢 VERIFIED SALES CLOSER';
          badgeEl.style.color = isTL ? '#c084fc' : 'var(--wa-accent)';
        }
        if (nameEl) nameEl.textContent = (u.name || (isTL ? 'Team Leader' : 'Closer')) + ' (@' + (u.username || 'user') + ')';
        if (compEl) {
          compEl.style.display = 'block';
          compEl.style.color = isTL ? '#c084fc' : 'var(--wa-accent)';
          compEl.textContent = '🏢 ' + compName + ' • Ready';
        }
        if (noteEl) {
          if (isTL) {
            noteEl.innerHTML = 'Active Team Leader session linked to <strong>' + compName + '</strong>. Click below to enter your workspace to outreach and recruit TMs.';
          } else {
            noteEl.innerHTML = 'Active sales session linked to <strong>' + compName + '</strong>. Click below to enter your closing desk.';
          }
        }
        if (enterBtn) {
          enterBtn.style.display = 'inline-block';
          enterBtn.style.background = isTL ? 'linear-gradient(135deg, #a855f7, #7c3aed)' : 'linear-gradient(135deg, #2de89a, #0fa968)';
          enterBtn.textContent = isTL ? 'Enter Team Lead Portal →' : 'Enter Sales Desk →';
          enterBtn.onclick = function() {
            loginAsConnectedCloser(phone);
          };
        }
      } else {
        // CASE 3: Suspended / Inactive
        if (badgeEl) {
          badgeEl.textContent = '🚫 ACCOUNT INACTIVE';
          badgeEl.style.color = '#ef4444';
        }
        if (nameEl) nameEl.textContent = (u.name || 'Account') + ' (@' + (u.username || '') + ')';
        if (compEl) {
          compEl.style.display = 'block';
          compEl.style.color = '#ef4444';
          compEl.textContent = '🏢 ' + compName;
        }
        if (noteEl) {
          noteEl.textContent = 'Your closer account is ' + u.status + '. Please contact your company administrator.';
        }
        if (enterBtn) {
          enterBtn.style.display = 'none';
        }
      }
    } else {
      // CASE 4: Brand new WhatsApp number (NOT registered in Chatly!)
      if (badgeEl) {
        badgeEl.textContent = '✨ NEW CLOSER APPLICANT (NOT REGISTERED)';
        badgeEl.style.color = '#38bdf8';
      }
      if (nameEl) nameEl.textContent = 'Unregistered Closer Applicant';
      if (compEl) {
        compEl.style.display = 'block';
        compEl.style.color = 'var(--wa-text-muted)';
        compEl.textContent = '🏢 Please select your company';
      }
      if (noteEl) {
        noteEl.innerHTML = 'WhatsApp number verified! Complete your closer application below to choose your company and submit for admin approval.';
      }
      if (enterBtn) {
        enterBtn.style.display = 'inline-block';
        enterBtn.style.background = 'linear-gradient(135deg, #38bdf8, #0284c7)';
        enterBtn.textContent = '🚀 Complete Closer Application →';
        enterBtn.onclick = function() {
          openTMRegisterModal(phone);
        };
      }

      // Automatically open the registration modal once so the TM gets the onboarding wizard immediately
      if (autoModalTriggeredForPhone !== phone) {
        autoModalTriggeredForPhone = phone;
        setTimeout(() => {
          openTMRegisterModal(phone);
        }, 350);
      }
    }
  } catch (err) {
    if (nameEl) nameEl.textContent = 'Connected Closer (+' + phone + ')';
  }

  if (discBtn) {
    discBtn.onclick = function() {
      disconnectWhatsAppBridge();
    };
  }
}

async function loginAsConnectedCloser(phone) {
  try {
    sessionStorage.removeItem('chatly_logged_out');
    const res = await fetch('api/auth.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'qr_login', phone: phone })
    });
    const data = await res.json();
    if (data.success && data.user) {
      triggerLoginAnimation(data.user.name, 'Sales Closer Desk Activated • Starting Pipeline', 'index.php');
    } else {
      if (data.status === 'pending' || data.requires_registration) {
        showConnectedCloserBox(phone);
      }
      alert(data.error || 'Failed to activate session with connected number.');
    }
  } catch (err) {
    alert('Login error: ' + err.message);
  }
}

async function disconnectWhatsAppBridge() {
  const statusTxt = document.getElementById('qrStatusText');
  if (statusTxt) statusTxt.textContent = '🔄 Disconnecting WhatsApp & generating fresh QR code...';
  const box = document.getElementById('connectedPhoneActionsBox');
  if (box) box.style.display = 'none';
  const scanLine = document.querySelector('.qr-scan-line');
  if (scanLine) scanLine.style.display = 'block';
  lastKnownState = '';
  autoModalTriggeredForPhone = null;
  await refreshQRCode(true);
}

// Connected Phone Verification & Routing (TM Joining Cycle)
async function handleConnectedPhone(phone) {
  await showConnectedCloserBox(phone);
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
      triggerLoginAnimation(data.user.name, 'Sales Closer Desk Activated • Starting Pipeline', data.redirect || 'index.php');
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
      try { localStorage.setItem('omnidesk_user', JSON.stringify(data.user)); } catch(_) {}
      const targetCompany = data.user.company_id ? `/cc?portal=1&company_id=${encodeURIComponent(data.user.company_id)}` : '/cc?portal=1';
      triggerLoginAnimation(data.user.name, 'Company Administrator Control Center — ' + (data.user.company_name || data.user.name), targetCompany);
    } else {
      errBox.textContent = data.error || 'Access Denied: Company Admin privileges required.';
      errBox.style.display = 'block';
    }
  } catch (err) {
    errBox.textContent = 'Network communication error.';
    errBox.style.display = 'block';
  } finally {
    btn.disabled = false;
    btn.textContent = 'Sign In to Company Portal 🏢';
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
      triggerLoginAnimation('Platform Super Admin', 'Master Command Center • Multi-Tenant Telemetry', '/ss?portal=1');
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
      This WhatsApp number is already linked to closer account <strong id="phoneExistsUser" style="color:var(--wa-accent);">@user</strong>.<br>Wanna login with your password instead?
    </p>
    <div style="display:flex; gap:10px;">
      <button type="button" class="btn-action-primary" style="background:linear-gradient(135deg, #2de89a, #0fa968); margin:0;" onclick="switchTMToLogin()">
        🔑 Yes, Log In as @<span id="phoneExistsBtnUser">user</span>
      </button>
      <button type="button" class="btn-action-primary" style="background:rgba(255,255,255,0.08); color:var(--wa-text-primary); margin:0; width:auto; padding:12px 18px;" onclick="closePhoneExistsModal()">
        Cancel
      </button>
    </div>
  </div>
</div>

<!-- MODAL: New TM / TL Closer Registration Wizard -->
<div id="modalTMRegister" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.8); z-index:999999; align-items:center; justify-content:center; backdrop-filter:blur(8px); overflow-y:auto; padding:20px 0;">
  <div style="background:var(--wa-card-bg); border:1px solid rgba(31, 224, 138,0.4); border-radius:20px; padding:30px; max-width:540px; width:92%; box-shadow:0 25px 60px rgba(0,0,0,0.6); position:relative;">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
      <div>
        <div style="font-size:11px; font-weight:800; text-transform:uppercase; color:var(--wa-accent); letter-spacing:0.05em;">Step-by-Step Onboarding</div>
        <h3 id="regModalTitle" style="font-size:20px; font-weight:800; color:var(--wa-text-primary);">🚀 Account Setup &amp; Role</h3>
      </div>
      <button type="button" onclick="closeTMRegisterModal()" style="background:none; border:none; color:var(--wa-text-muted); font-size:24px; cursor:pointer;">&times;</button>
    </div>

    <div style="background:rgba(31, 224, 138,0.1); border:1px solid rgba(31, 224, 138,0.25); border-radius:10px; padding:10px 14px; margin-bottom:16px; font-size:12px; color:var(--wa-accent); display:flex; align-items:center; gap:8px;">
      <span>📱</span>
      <span>WhatsApp Number: <strong id="regDisplayPhone">+92 300 1234567</strong></span>
      <span style="margin-left:auto; background:var(--wa-accent); color:#03130c; font-size:10px; padding:2px 6px; border-radius:4px; font-weight:700;">VERIFIED</span>
    </div>

    <form id="formTMRegisterSubmit" onsubmit="handleTMRegisterSubmit(event)">
      <!-- Role Selector Toggle: Sales Closer (TM) vs Team Leader (TL) -->
      <div style="margin-bottom:14px;">
        <label class="input-label" style="margin-bottom:6px; font-weight:700;">Choose Your Role *</label>
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px;">
          <div id="roleCardTM" onclick="selectOnboardingRole('member')" style="background:rgba(31,224,138,0.12); border:2px solid var(--wa-accent); border-radius:12px; padding:12px; cursor:pointer; transition:all 0.2s ease;">
            <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:4px;">
              <span style="font-weight:800; font-size:13px; color:var(--wa-text-primary);">🎯 Sales Closer (TM)</span>
              <span id="roleRadioTM" style="color:var(--wa-accent); font-size:14px; font-weight:900;">●</span>
            </div>
            <div style="font-size:11px; color:var(--wa-text-muted); line-height:1.4;">
              Live WhatsApp sales desk. Pitch products and close customer deals.
            </div>
          </div>
          <div id="roleCardTL" onclick="selectOnboardingRole('lead')" style="background:rgba(255,255,255,0.03); border:1px solid rgba(255,255,255,0.12); border-radius:12px; padding:12px; cursor:pointer; transition:all 0.2s ease;">
            <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:4px;">
              <span style="font-weight:800; font-size:13px; color:var(--wa-text-primary);">🚀 Team Leader (TL)</span>
              <span id="roleRadioTL" style="color:var(--wa-text-muted); font-size:14px; font-weight:900;">○</span>
            </div>
            <div style="font-size:11px; color:var(--wa-text-muted); line-height:1.4;">
              Cold outreach on external platforms to recruit TMs. Direct commission per TM!
            </div>
          </div>
        </div>
        <input type="hidden" id="regOnboardingRole" value="member">
      </div>

      <!-- TL Specific Explainer Banner (Shows only when TL is selected) -->
      <div id="tlRoleBanner" style="display:none; background:rgba(147, 51, 234, 0.12); border:1px solid rgba(147, 51, 234, 0.35); border-radius:10px; padding:10px 14px; margin-bottom:14px; font-size:11.5px; color:#d8b4fe; line-height:1.5;">
        💼 <strong>Team Leader (TL) Note:</strong> There is no fixed salary. Your role is cold outreach on other platforms to invite TMs to Chatly and sell company products. You earn direct company commission bounty for each approved TM you recruit! Outbound &amp; inbound message sync is enabled on your desk.
      </div>

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
        <label class="input-label" id="regCompanyLabel">Select Company * (Where you are applying as Closer)</label>
        <select id="regTMCompany" class="input-field" required style="cursor:pointer; font-weight:600;" onchange="updateSelectedCompanyPreview()">
          <option value="COMP-001">Nunno Official — Scalper Alpha &amp; SaaS</option>
          <option value="COMP-002">Apex Crypto Signals — Institutional Trading</option>
          <option value="COMP-003">Pak Trade Academy — Mentorship &amp; Signals</option>
        </select>
      </div>

      <!-- Live Company Brand & Logo Preview Card -->
      <div id="companyBrandPreviewCard" style="background: rgba(255,255,255,0.04); border: 1px solid rgba(31, 224, 138,0.3); border-radius: 12px; padding: 10px 14px; margin-bottom: 14px; display: flex; align-items: center; gap: 12px; transition: all 0.2s ease;">
        <img id="previewCompLogo" src="assets/images/nunno-logo.png" alt="Company Logo" style="width: 42px; height: 42px; border-radius: 10px; object-fit: contain; background: #07140f; border: 1px solid rgba(255,255,255,0.15); padding: 3px; box-shadow: 0 4px 12px rgba(0,0,0,0.3); flex-shrink: 0;">
        <div style="flex: 1; min-width: 0;">
          <div style="display: flex; align-items: center; gap: 6px; margin-bottom: 2px;">
            <strong id="previewCompName" style="color: var(--wa-text-primary); font-size: 13.5px; font-weight: 800;">Nunno Official</strong>
            <span id="previewCompBadge" style="font-size: 10px; font-weight: 700; padding: 2px 6px; border-radius: 4px; background: rgba(31, 224, 138, 0.2); color: #4cecab; border: 1px solid rgba(31, 224, 138, 0.4);">Enterprise Billion</span>
          </div>
          <div id="previewCompTagline" style="font-size: 11px; color: var(--wa-text-muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">Scalper Alpha &amp; SaaS</div>
        </div>
      </div>

      <!-- Optional TL Referral Attribution (Only for TMs) -->
      <div class="input-group" id="groupTMReferredByTL" style="margin-bottom:12px;">
        <label class="input-label">Referred by Team Leader (TL) Username (Optional)</label>
        <input type="text" id="regTMReferredByTL" class="input-field" placeholder="e.g. ali or leave blank if none" style="font-size:13px;">
        <div style="font-size:10.5px; color:var(--wa-text-muted); margin-top:3px;">If a Chatly Team Leader recruited you, enter their username so they receive commission bounty.</div>
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
            <option value="male">Male</option>
            <option value="female">Female</option>
          </select>
        </div>
      </div>

      <div class="input-group">
        <label class="input-label">Profile Picture (PFP Avatar URL - Optional)</label>
        <input type="text" id="regTMPfp" class="input-field" placeholder="https://... (or leave default avatar)">
      </div>

      <div class="error-box" id="tmRegModalError"></div>

      <button type="submit" id="btnSubmitTMReg" class="btn-action-primary" style="background:linear-gradient(135deg, #2de89a, #0fa968); margin-top:8px;">
        Submit Closer Application for Company Approval ⏳
      </button>
      
      <div id="regFooterNotice" style="font-size:11px; color:var(--wa-text-muted); text-align:center; margin-top:10px;">
        ℹ️ Once submitted, company administration reviews and approves your account with a custom daily message quota.
      </div>
    </form>
  </div>
</div>

<!-- MODAL: TM / TL Application Submitted Confirmation -->
<div id="modalTMSuccess" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.8); z-index:999999; align-items:center; justify-content:center; backdrop-filter:blur(8px);">
  <div style="background:var(--wa-card-bg); border:1px solid rgba(31, 224, 138,0.4); border-radius:20px; padding:32px; max-width:460px; width:90%; box-shadow:0 25px 60px rgba(0,0,0,0.6); text-align:center;">
    <div style="display:flex; justify-content:center; margin-bottom:12px;">
      <img id="successCompLogo" src="assets/images/nunno-logo.png" alt="Company Logo" style="width:64px; height:64px; border-radius:16px; object-fit:contain; background:#07140f; border:1px solid rgba(255,255,255,0.18); padding:5px; box-shadow:0 8px 24px rgba(0,0,0,0.4);">
    </div>
    <h3 id="successModalTitle" style="font-size:20px; font-weight:800; color:var(--wa-text-primary); margin-bottom:8px;">Application Received!</h3>
    <p id="successModalBody" style="font-size:13px; color:var(--wa-text-secondary); line-height:1.6; margin-bottom:20px;">
      Your application has been dispatched to <strong id="successCompName" style="color:var(--wa-accent);">Company</strong> administration.<br><br>
      Once approved by the company, you can log in directly anytime using your <strong>Username &amp; Password</strong> without needing to scan the QR code again!
    </p>
    <button type="button" class="btn-action-primary" style="background:linear-gradient(135deg, #2de89a, #0fa968); margin:0;" onclick="closeTMSuccessModal()">
      Understood, Continue to Login &rarr;
    </button>
  </div>
</div>

<!-- MODAL: Promote My Product Form (Brand Inquiry) -->
<div id="modalPromoteProduct" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.85); z-index:999999; align-items:center; justify-content:center; backdrop-filter:blur(10px); overflow-y:auto; padding:24px 0;">
  <div style="background:var(--wa-card-bg); border:1px solid rgba(31, 224, 138, 0.4); border-radius:22px; padding:32px; max-width:560px; width:92%; box-shadow:0 25px 70px rgba(0,0,0,0.7); position:relative; margin:auto;">
    
    <!-- Modal Close Button -->
    <button type="button" onclick="closePromoteProductModal()" style="position:absolute; top:20px; right:20px; background:none; border:none; color:var(--wa-text-muted); font-size:26px; cursor:pointer; line-height:1; transition:color 0.15s ease;" onmouseover="this.style.color='#fff'" onmouseout="this.style.color='var(--wa-text-muted)'">&times;</button>

    <!-- Header -->
    <div style="margin-bottom:20px; padding-right:30px;">
      <div style="display:inline-flex; align-items:center; gap:6px; font-size:11px; font-weight:800; text-transform:uppercase; color:var(--wa-accent); letter-spacing:0.05em; background:rgba(31,224,138,0.12); border:1px solid rgba(31,224,138,0.3); padding:3px 8px; border-radius:6px; margin-bottom:8px;">
        <span>🚀</span> Sell with Chatly Closers
      </div>
      <h3 style="font-size:22px; font-weight:800; color:var(--wa-text-primary); margin:0 0 6px 0; letter-spacing:-0.02em;">
        Promote Your Product on Chatly
      </h3>
      <p style="font-size:12.5px; color:var(--wa-text-secondary); margin:0; line-height:1.5;">
        Tell us about your product or service. Our network of trained WhatsApp sales closers will pitch, qualify leads, and close deals for your brand.
      </p>
    </div>

    <!-- Form -->
    <form id="formPromoteProductSubmit" onsubmit="handlePromoteProductSubmit(event)">
      
      <!-- Section 1: Product & Business -->
      <div style="font-size:11.5px; font-weight:800; text-transform:uppercase; color:var(--wa-accent); letter-spacing:0.04em; margin-bottom:8px; border-bottom:1px solid rgba(255,255,255,0.08); padding-bottom:4px;">
        1. Product &amp; Brand Details
      </div>

      <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:12px;">
        <div class="input-group" style="margin:0;">
          <label class="input-label">Brand / Business Name *</label>
          <input type="text" id="inqBrandName" class="input-field" placeholder="e.g. Aura Threads / Alpha Signals" required style="font-size:13px;">
        </div>
        <div class="input-group" style="margin:0;">
          <label class="input-label">Product or Service Name *</label>
          <input type="text" id="inqProductName" class="input-field" placeholder="e.g. Premium Leather Bags" required style="font-size:13px;">
        </div>
      </div>

      <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:12px;">
        <div class="input-group" style="margin:0;">
          <label class="input-label">Industry / Category *</label>
          <select id="inqCategory" class="input-field" style="cursor:pointer; font-size:12.5px;">
            <option value="E-Commerce & Retail">E-Commerce &amp; Retail</option>
            <option value="Trading, Crypto & Forex">Trading, Crypto &amp; Forex</option>
            <option value="Online Courses & Coaching">Online Courses &amp; Coaching</option>
            <option value="Real Estate & Property">Real Estate &amp; Property</option>
            <option value="Health, Beauty & Fashion">Health, Beauty &amp; Fashion</option>
            <option value="SaaS & Digital Services">SaaS &amp; Digital Services</option>
            <option value="Other Business">Other Business</option>
          </select>
        </div>
        <div class="input-group" style="margin:0;">
          <label class="input-label">Product Selling Price (PKR / USD) *</label>
          <input type="text" id="inqPrice" class="input-field" placeholder="e.g. Rs. 4,500 or $50" required style="font-size:13px;">
        </div>
      </div>

      <div class="input-group" style="margin-bottom:12px;">
        <label class="input-label">What does your product do? / Main Pitch *</label>
        <textarea id="inqDescription" class="input-field" rows="3" placeholder="Briefly describe what your product is, its key benefits, and why customers buy it..." required style="resize:vertical; font-family:inherit; font-size:12.5px; line-height:1.5;"></textarea>
      </div>

      <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:16px;">
        <div class="input-group" style="margin:0;">
          <label class="input-label">Website, Store, or Instagram URL</label>
          <input type="text" id="inqWebsite" class="input-field" placeholder="https://... (Optional)" style="font-size:12.5px;">
        </div>
        <div class="input-group" style="margin:0;">
          <label class="input-label">Target Daily Orders / Closers</label>
          <select id="inqTargetVolume" class="input-field" style="cursor:pointer; font-size:12.5px;">
            <option value="1-5 orders / day (1 Closer)">1-5 orders / day (1 Closer)</option>
            <option value="5-20 orders / day (2-3 Closers)" selected>5-20 orders / day (2-3 Closers)</option>
            <option value="20-50+ orders / day (Dedicated Team)">20-50+ orders / day (Dedicated Team)</option>
            <option value="Enterprise Scale (100+ orders / day)">Enterprise Scale (100+ orders / day)</option>
          </select>
        </div>
      </div>

      <!-- Section 2: Contact Info -->
      <div style="font-size:11.5px; font-weight:800; text-transform:uppercase; color:var(--wa-accent); letter-spacing:0.04em; margin-bottom:8px; border-bottom:1px solid rgba(255,255,255,0.08); padding-bottom:4px;">
        2. Business Contact &amp; WhatsApp
      </div>

      <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:12px;">
        <div class="input-group" style="margin:0;">
          <label class="input-label">Contact Person Name *</label>
          <input type="text" id="inqContactName" class="input-field" placeholder="e.g. Askar Ali" required style="font-size:13px;">
        </div>
        <div class="input-group" style="margin:0;">
          <label class="input-label">WhatsApp Number *</label>
          <input type="text" id="inqPhone" class="input-field" placeholder="+92 300 1234567" required style="font-size:13px;">
        </div>
      </div>

      <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:14px;">
        <div class="input-group" style="margin:0;">
          <label class="input-label">Email Address (Optional)</label>
          <input type="email" id="inqEmail" class="input-field" placeholder="owner@brand.com" style="font-size:12.5px;">
        </div>
        <div class="input-group" style="margin:0;">
          <label class="input-label">City / Country</label>
          <input type="text" id="inqCity" class="input-field" placeholder="e.g. Lahore, Pakistan" value="Pakistan" style="font-size:12.5px;">
        </div>
      </div>

      <div class="error-box" id="promoteFormError" style="display:none; margin-bottom:12px;"></div>

      <button type="submit" id="btnSubmitPromoteProduct" class="btn-action-primary" style="background:linear-gradient(135deg, #4cecab, #1fe08a 50%, #0fa968); color:#03130c; font-weight:800; font-size:15px; margin-top:4px; box-shadow:0 10px 30px -6px rgba(31, 224, 138, 0.5);">
        🚀 Submit Product for Review &amp; Promotion
      </button>

      <div style="font-size:11px; color:var(--wa-text-muted); text-align:center; margin-top:10px;">
        🔒 Your details are secure. Our administration will contact you to review your product and assign dedicated WhatsApp closers.
      </div>
    </form>
  </div>
</div>

<!-- MODAL: Product Promotion Submitted Confirmation -->
<div id="modalPromoteSuccess" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.85); z-index:999999; align-items:center; justify-content:center; backdrop-filter:blur(10px);">
  <div style="background:var(--wa-card-bg); border:1px solid rgba(31, 224, 138, 0.4); border-radius:24px; padding:36px; max-width:480px; width:90%; box-shadow:0 25px 70px rgba(0,0,0,0.7); text-align:center;">
    <div style="width:68px; height:68px; border-radius:50%; background:rgba(31, 224, 138, 0.15); border:2px solid var(--wa-accent); display:flex; align-items:center; justify-content:center; font-size:32px; margin:0 auto 16px;">
      🎉
    </div>
    <div style="font-size:11px; font-weight:800; text-transform:uppercase; color:var(--wa-accent); letter-spacing:0.06em; margin-bottom:6px;">Application Received</div>
    <h3 style="font-size:22px; font-weight:800; color:var(--wa-text-primary); margin-bottom:10px;">
      Our Company Will Be In Touch Soon!
    </h3>
    <p style="font-size:13.5px; color:var(--wa-text-secondary); line-height:1.6; margin-bottom:24px;">
      Thank you for submitting your product! Our team has received your details for <strong id="successInquiryBrand" style="color:var(--wa-accent);">your brand</strong>.<br><br>
      Our company administration will review your product and be in touch with you shortly on WhatsApp at <strong id="successInquiryPhone" style="color:#fff;">+92 300 1234567</strong> to discuss closer onboarding and launching your campaigns.
    </p>
    <button type="button" class="btn-action-primary" style="background:linear-gradient(135deg, #2de89a, #0fa968); color:#03130c; font-weight:800; margin:0;" onclick="closePromoteSuccessModal()">
      Awesome, Return to Homepage &rarr;
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
  const cleanPhone = phone ? (phone.startsWith('+') ? phone : ('+' + phone.replace(/\D/g, ''))) : '+92 300 1234567';
  document.getElementById('regDisplayPhone').textContent = cleanPhone;
  document.getElementById('modalTMRegister').style.display = 'flex';
  selectOnboardingRole('member');
  const refInp = document.getElementById('regTMReferredByTL');
  if (refInp) refInp.value = '';
  document.getElementById('regTMUsername').focus();
  loadCompaniesForRegister();
}

function selectOnboardingRole(role) {
  const isTL = (role === 'lead');
  const hiddenRole = document.getElementById('regOnboardingRole');
  if (hiddenRole) hiddenRole.value = isTL ? 'lead' : 'member';

  const cardTM = document.getElementById('roleCardTM');
  const cardTL = document.getElementById('roleCardTL');
  const radioTM = document.getElementById('roleRadioTM');
  const radioTL = document.getElementById('roleRadioTL');
  const bannerTL = document.getElementById('tlRoleBanner');
  const refGroup = document.getElementById('groupTMReferredByTL');
  const modalTitle = document.getElementById('regModalTitle');
  const compLabel = document.getElementById('regCompanyLabel');
  const btn = document.getElementById('btnSubmitTMReg');
  const footerNotice = document.getElementById('regFooterNotice');

  if (isTL) {
    if (cardTL) {
      cardTL.style.background = 'rgba(147, 51, 234, 0.18)';
      cardTL.style.border = '2px solid #a855f7';
    }
    if (radioTL) {
      radioTL.style.color = '#c084fc';
      radioTL.textContent = '●';
    }
    if (cardTM) {
      cardTM.style.background = 'rgba(255,255,255,0.03)';
      cardTM.style.border = '1px solid rgba(255,255,255,0.12)';
    }
    if (radioTM) {
      radioTM.style.color = 'var(--wa-text-muted)';
      radioTM.textContent = '○';
    }
    if (bannerTL) bannerTL.style.display = 'block';
    if (refGroup) refGroup.style.display = 'none';
    if (compLabel) compLabel.textContent = 'Select Company * (Where you are applying as Team Leader)';
    if (modalTitle) modalTitle.textContent = '🚀 New Team Leader (TL) Setup';
    if (btn) btn.textContent = 'Submit Team Leader (TL) Application for Approval ⏳';
    if (footerNotice) footerNotice.textContent = 'ℹ️ Once approved, you can cold outreach prospects & earn commission bounty for each verified TM brought to this company!';
  } else {
    if (cardTM) {
      cardTM.style.background = 'rgba(31,224,138,0.12)';
      cardTM.style.border = '2px solid var(--wa-accent)';
    }
    if (radioTM) {
      radioTM.style.color = 'var(--wa-accent)';
      radioTM.textContent = '●';
    }
    if (cardTL) {
      cardTL.style.background = 'rgba(255,255,255,0.03)';
      cardTL.style.border = '1px solid rgba(255,255,255,0.12)';
    }
    if (radioTL) {
      radioTL.style.color = 'var(--wa-text-muted)';
      radioTL.textContent = '○';
    }
    if (bannerTL) bannerTL.style.display = 'none';
    if (refGroup) refGroup.style.display = 'block';
    if (compLabel) compLabel.textContent = 'Select Company * (Where you are applying as Closer)';
    if (modalTitle) modalTitle.textContent = '🚀 New Closer WhatsApp Setup';
    if (btn) btn.textContent = 'Submit Closer Application for Company Approval ⏳';
    if (footerNotice) footerNotice.textContent = 'ℹ️ Once submitted, company administration reviews and approves your account with a custom daily message quota.';
  }
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
    logo: 'assets/images/nunno-logo.png',
    plan: 'Enterprise Billion',
    badge: 'Scalper Alpha & SaaS'
  };
  const logoEl = document.getElementById('previewCompLogo');
  const nameEl = document.getElementById('previewCompName');
  const badgeEl = document.getElementById('previewCompBadge');
  const taglineEl = document.getElementById('previewCompTagline');
  if (logoEl) logoEl.src = comp.logo || 'assets/images/nunno-logo.png';
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

  const role = document.getElementById('regOnboardingRole')?.value || 'member';
  const isTL = (role === 'lead');
  const phone = document.getElementById('regDisplayPhone').textContent.trim();
  const username = document.getElementById('regTMUsername').value.trim();
  const password = document.getElementById('regTMPassword').value.trim();
  const company_id = document.getElementById('regTMCompany').value;
  const fullName = document.getElementById('regTMFullName').value.trim();
  const city = document.getElementById('regTMCity').value.trim();
  const gender = document.getElementById('regTMGender').value;
  const pfp = document.getElementById('regTMPfp').value.trim();
  const referred_by_tl = isTL ? '' : (document.getElementById('regTMReferredByTL')?.value.trim() || '');

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
        pfp,
        role,
        referred_by_tl
      })
    });
    const data = await res.json();
    if (data.success) {
      closeTMRegisterModal();
      const compSelect = document.getElementById('regTMCompany');
      const compName = compSelect.options[compSelect.selectedIndex]?.text || 'Company';
      const successTitle = document.getElementById('successModalTitle');
      const successBody = document.getElementById('successModalBody');
      if (successTitle) {
        successTitle.textContent = isTL ? 'Team Leader Application Received!' : 'Closer Application Received!';
      }
      if (successBody) {
        successBody.innerHTML = `Your application to join <strong style="color:var(--wa-accent);">${compName}</strong> as <strong>${isTL ? 'Team Leader (TL)' : 'Sales Closer (TM)'}</strong> has been dispatched for company review.<br><br>Once approved by company administration in <strong>/cc</strong>, you can log in directly anytime using your <strong>Username &amp; Password</strong> without needing to scan the QR code again!`;
      }
      const compMatch = loadedCompaniesCache.find(c => c.id === company_id);
      if (compMatch && compMatch.logo) {
        document.getElementById('successCompLogo').src = compMatch.logo;
      }
      document.getElementById('modalTMSuccess').style.display = 'flex';
      document.getElementById('tmDirectUsername').value = username;

      // Update card on page to Pending state immediately!
      const cleanP = phone.replace(/\D/g, '');
      if (cleanP) {
        showConnectedCloserBox(cleanP);
      }
    } else {
      errBox.textContent = data.error || 'Registration failed.';
      errBox.style.display = 'block';
    }
  } catch (err) {
    errBox.textContent = 'Network communication error.';
    errBox.style.display = 'block';
  } finally {
    btn.disabled = false;
    btn.textContent = isTL ? 'Submit Team Leader (TL) Application for Approval ⏳' : 'Submit Closer Application for Company Approval ⏳';
  }
}

// Brand Product Promotion Modal Handlers
function openPromoteProductModal() {
  const m = document.getElementById('modalPromoteProduct');
  if (m) {
    m.style.display = 'flex';
    const firstInput = document.getElementById('inqBrandName');
    if (firstInput) setTimeout(() => firstInput.focus(), 100);
  }
}

function closePromoteProductModal() {
  const m = document.getElementById('modalPromoteProduct');
  if (m) m.style.display = 'none';
}

function closePromoteSuccessModal() {
  const m = document.getElementById('modalPromoteSuccess');
  if (m) m.style.display = 'none';
  if (window.location.hash === '#promote') {
    history.replaceState(null, document.title, window.location.pathname);
  }
}

async function handlePromoteProductSubmit(e) {
  e.preventDefault();
  const btn = document.getElementById('btnSubmitPromoteProduct');
  const errBox = document.getElementById('promoteFormError');
  btn.disabled = true;
  btn.textContent = 'Submitting Product Details…';
  if (errBox) errBox.style.display = 'none';

  const brand_name = document.getElementById('inqBrandName').value.trim();
  const product_name = document.getElementById('inqProductName').value.trim();
  const category = document.getElementById('inqCategory').value;
  const price = document.getElementById('inqPrice').value.trim();
  const description = document.getElementById('inqDescription').value.trim();
  const website = document.getElementById('inqWebsite').value.trim();
  const target_volume = document.getElementById('inqTargetVolume').value;
  const contact_name = document.getElementById('inqContactName').value.trim();
  const phone = document.getElementById('inqPhone').value.trim();
  const email = document.getElementById('inqEmail').value.trim();
  const city = document.getElementById('inqCity').value.trim();

  try {
    const res = await fetch('api/brand_inquiries.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        action: 'submit',
        brand_name,
        product_name,
        category,
        price,
        description,
        website,
        target_volume,
        contact_name,
        phone,
        email,
        city
      })
    });
    const data = await res.json();
    if (data.success) {
      closePromoteProductModal();
      document.getElementById('formPromoteProductSubmit').reset();
      
      const bEl = document.getElementById('successInquiryBrand');
      const pEl = document.getElementById('successInquiryPhone');
      if (bEl) bEl.textContent = brand_name;
      if (pEl) pEl.textContent = phone;

      const sModal = document.getElementById('modalPromoteSuccess');
      if (sModal) sModal.style.display = 'flex';
    } else {
      if (errBox) {
        errBox.textContent = data.error || 'Failed to submit inquiry.';
        errBox.style.display = 'block';
      }
    }
  } catch (err) {
    if (errBox) {
      errBox.textContent = 'Network communication error. Please try again.';
      errBox.style.display = 'block';
    }
  } finally {
    btn.disabled = false;
    btn.textContent = '🚀 Submit Product for Review & Promotion';
  }
}

// Auto open if #promote hash is present
window.addEventListener('DOMContentLoaded', () => {
  if (window.location.hash === '#promote') {
    openPromoteProductModal();
  }
});
window.addEventListener('hashchange', () => {
  if (window.location.hash === '#promote') {
    openPromoteProductModal();
  }
});

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
      triggerLoginAnimation(data.user.name, 'Sales Closer Desk Activated • Starting Pipeline', data.redirect || 'index.php');
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
