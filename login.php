<?php
/**
 * Nunno Omni-Desk — Sign In Page
 * Replicated with Signature 360 Spin & Zoom-Out Animation from Nunno Sales
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!empty($_SESSION['user'])) {
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
  <title>Sign in — Nunno Omni-Desk WhatsApp CRM</title>
  <link rel="icon" type="image/png" href="assets/images/nunno-logo.png">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <style>
    *, *::before, *::after {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
    }

    html, body {
      height: 100%;
      background-color: #06050a;
      color: #e2e8f0;
      font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
      -webkit-font-smoothing: antialiased;
      overflow-x: hidden;
    }

    /* Subtle Dark Backdrop Grid */
    .bg-grid {
      position: fixed;
      inset: 0;
      background-image: 
        linear-gradient(rgba(255, 255, 255, 0.015) 1px, transparent 1px),
        linear-gradient(90deg, rgba(255, 255, 255, 0.015) 1px, transparent 1px);
      background-size: 32px 32px;
      pointer-events: none;
      z-index: 0;
    }

    /* Center Container */
    .login-container {
      position: relative;
      z-index: 1;
      min-height: 100vh;
      min-height: 100dvh;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      padding: 24px 16px;
    }

    /* ===== NUNNO LOGO WITH PURPLE EDGE GLOW ===== */
    .brand-hero {
      text-align: center;
      margin-bottom: 24px;
    }

    .logo-frame {
      width: 76px;
      height: 76px;
      margin: 0 auto 16px;
      border-radius: 20px;
      background: #0d0c18;
      border: 1.5px solid rgba(139, 92, 246, 0.4);
      display: flex;
      align-items: center;
      justify-content: center;
      box-shadow: 
        0 0 24px rgba(139, 92, 246, 0.45),
        0 8px 30px rgba(0, 0, 0, 0.7);
      animation: purple-glow 3s ease-in-out infinite alternate;
    }

    @keyframes purple-glow {
      0% {
        box-shadow: 0 0 16px rgba(139, 92, 246, 0.35), 0 8px 24px rgba(0, 0, 0, 0.7);
        border-color: rgba(139, 92, 246, 0.35);
      }
      100% {
        box-shadow: 0 0 28px rgba(139, 92, 246, 0.6), 0 10px 30px rgba(0, 0, 0, 0.8);
        border-color: rgba(168, 85, 247, 0.55);
      }
    }

    .logo-img {
      width: 54px;
      height: 54px;
      object-fit: cover;
      border-radius: 13px;
    }

    .brand-title {
      font-size: 22px;
      font-weight: 800;
      color: #ffffff;
      letter-spacing: -0.02em;
      margin-bottom: 4px;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 7px;
    }

    .brand-badge {
      font-size: 10px;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.08em;
      padding: 3px 8px;
      border-radius: 9999px;
      background: rgba(99, 102, 241, 0.15);
      border: 1px solid rgba(99, 102, 241, 0.35);
      color: #a5b4fc;
    }

    .brand-subtitle {
      font-size: 13px;
      color: #71717a;
      font-weight: 400;
    }

    /* ===== LOGIN CARD ===== */
    .login-card {
      width: 100%;
      max-width: 380px;
      background: #0d0c18;
      border: 1px solid rgba(255, 255, 255, 0.08);
      border-radius: 18px;
      padding: 24px 20px;
      box-shadow: 0 20px 60px -10px rgba(0, 0, 0, 0.8);
    }

    .form-group {
      margin-bottom: 16px;
    }

    .form-label {
      display: block;
      font-size: 12px;
      font-weight: 500;
      color: #94a3b8;
      margin-bottom: 6px;
    }

    .input-wrapper {
      position: relative;
    }

    .input-icon {
      position: absolute;
      left: 14px;
      top: 50%;
      transform: translateY(-50%);
      color: #475569;
      pointer-events: none;
    }

    .form-input {
      width: 100%;
      background: #08070e;
      border: 1px solid rgba(255, 255, 255, 0.09);
      color: #ffffff;
      padding: 12px 14px 12px 42px;
      border-radius: 10px;
      font-size: 14px;
      font-family: inherit;
      -webkit-appearance: none;
      appearance: none;
      transition: border-color 0.15s, box-shadow 0.15s;
    }

    .form-input::placeholder {
      color: #3f3f46;
      font-size: 13px;
    }

    .form-input:focus {
      border-color: #6366f1;
      box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.2);
      outline: none;
    }

    .pwd-toggle {
      position: absolute;
      right: 12px;
      top: 50%;
      transform: translateY(-50%);
      background: none;
      border: none;
      color: #64748b;
      cursor: pointer;
      font-size: 11px;
      font-weight: 600;
      letter-spacing: 0.04em;
      padding: 6px 8px;
      border-radius: 6px;
    }

    .pwd-toggle:hover {
      color: #cbd5e1;
    }

    .submit-btn {
      width: 100%;
      padding: 13px;
      border-radius: 10px;
      background: #6366f1;
      color: #ffffff;
      font-size: 14px;
      font-weight: 600;
      border: none;
      cursor: pointer;
      transition: background 0.15s, transform 0.1s;
      box-shadow: 0 4px 16px rgba(99, 102, 241, 0.35);
      margin-top: 18px;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 6px;
    }

    .submit-btn:hover {
      background: #4f46e5;
    }

    .submit-btn:active {
      transform: scale(0.98);
    }

    .submit-btn:disabled {
      opacity: 0.65;
      cursor: not-allowed;
      transform: none;
    }

    /* Alerts */
    .auth-alert {
      display: none;
      padding: 10px 14px;
      border-radius: 8px;
      font-size: 13px;
      font-weight: 500;
      margin-bottom: 14px;
    }

    .auth-alert--error {
      background: rgba(239, 68, 68, 0.12);
      border: 1px solid rgba(239, 68, 68, 0.3);
      color: #fca5a5;
    }

    .auth-alert--success {
      background: rgba(34, 197, 94, 0.12);
      border: 1px solid rgba(34, 197, 94, 0.3);
      color: #86efac;
    }

    /* Quick Fill Bar */
    .quick-fill-bar {
      display: flex;
      gap: 6px;
      margin-top: 14px;
      padding-top: 14px;
      border-top: 1px solid rgba(255, 255, 255, 0.06);
    }

    .quick-fill-btn {
      flex: 1;
      background: rgba(255, 255, 255, 0.04);
      border: 1px solid rgba(255, 255, 255, 0.08);
      color: #94a3b8;
      font-size: 11px;
      font-weight: 600;
      padding: 7px 8px;
      border-radius: 8px;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 5px;
      transition: all 0.15s;
    }

    .quick-fill-btn:hover {
      background: rgba(99, 102, 241, 0.12);
      border-color: rgba(99, 102, 241, 0.35);
      color: #e2e8f0;
    }

    .card-footer {
      margin-top: 18px;
      text-align: center;
      font-size: 12px;
      color: #64748b;
      line-height: 1.5;
    }

    .footer-divider {
      width: 32px;
      height: 1px;
      background: rgba(255, 255, 255, 0.08);
      margin: 10px auto;
    }

    .spinner {
      display: inline-block;
      width: 14px;
      height: 14px;
      border: 2px solid rgba(255, 255, 255, 0.3);
      border-top-color: #ffffff;
      border-radius: 50%;
      animation: spin 0.65s linear infinite;
      margin-right: 4px;
    }

    @keyframes spin {
      to { transform: rotate(360deg); }
    }

    /* ===== NUNNO 360 ROTATE & ZOOM-OUT LOGIN ANIMATION ===== */
    .login-anim-overlay {
      position: fixed;
      inset: 0;
      background: #06050a;
      z-index: 9999999;
      display: flex;
      align-items: center;
      justify-content: center;
      opacity: 0;
      visibility: hidden;
      pointer-events: none;
      transition: opacity 0.25s ease, visibility 0.25s ease;
    }

    .login-anim-overlay.active {
      opacity: 1;
      visibility: visible;
      pointer-events: auto;
    }

    .anim-aura {
      position: absolute;
      width: 280px;
      height: 280px;
      border-radius: 50%;
      background: radial-gradient(circle, rgba(139, 92, 246, 0.45) 0%, rgba(139, 92, 246, 0) 70%);
      filter: blur(35px);
      pointer-events: none;
      opacity: 0;
      transform: scale(0.8);
      transition: opacity 0.35s ease, transform 0.35s ease;
    }

    .login-anim-overlay.active .anim-aura {
      opacity: 1;
      transform: scale(1.15);
    }

    .anim-scene {
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      text-align: center;
      position: relative;
      z-index: 2;
    }

    /* Logo Box with Signature Purple Edge Glow */
    .anim-logo-box {
      width: 96px;
      height: 96px;
      border-radius: 24px;
      background: #0d0c18;
      border: 2px solid rgba(168, 85, 247, 0.8);
      display: flex;
      align-items: center;
      justify-content: center;
      box-shadow: 
        0 0 35px rgba(139, 92, 246, 0.75),
        0 0 70px rgba(139, 92, 246, 0.4),
        0 14px 40px rgba(0, 0, 0, 0.9);
      will-change: transform, opacity, box-shadow;
    }

    .anim-logo-box img {
      width: 68px;
      height: 68px;
      object-fit: cover;
      border-radius: 17px;
      display: block;
    }

    /* The 360 Spin & Zoom-Out Keyframe */
    .login-anim-overlay.active .anim-logo-box {
      animation: nunnoSpinZoomOut 1.6s cubic-bezier(0.2, 0.85, 0.25, 1) forwards;
    }

    @keyframes nunnoSpinZoomOut {
      0% {
        opacity: 0;
        transform: scale(0.5) rotate(0deg);
        box-shadow: 0 0 20px rgba(139, 92, 246, 0.5);
      }
      22% {
        opacity: 1;
        transform: scale(1.08) rotate(90deg);
        box-shadow: 0 0 45px rgba(168, 85, 247, 0.9), 0 0 85px rgba(139, 92, 246, 0.5);
      }
      50% {
        opacity: 1;
        transform: scale(1) rotate(180deg);
        box-shadow: 0 0 55px rgba(168, 85, 247, 1), 0 0 95px rgba(139, 92, 246, 0.6);
      }
      75% {
        opacity: 1;
        transform: scale(1.05) rotate(270deg);
        box-shadow: 0 0 45px rgba(168, 85, 247, 0.9), 0 0 80px rgba(139, 92, 246, 0.5);
      }
      88% {
        opacity: 1;
        transform: scale(1) rotate(360deg);
        box-shadow: 0 0 35px rgba(168, 85, 247, 0.8);
      }
      100% {
        opacity: 0;
        transform: scale(0) rotate(360deg);
        box-shadow: 0 0 0px rgba(139, 92, 246, 0);
      }
    }

    /* Caption underneath */
    .anim-label {
      margin-top: 22px;
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 6px;
      will-change: transform, opacity;
    }

    .login-anim-overlay.active .anim-label {
      animation: nunnoLabelFade 1.6s cubic-bezier(0.2, 0.85, 0.25, 1) forwards;
    }

    @keyframes nunnoLabelFade {
      0% {
        opacity: 0;
        transform: translateY(12px) scale(0.92);
      }
      20% {
        opacity: 1;
        transform: translateY(0) scale(1);
      }
      82% {
        opacity: 1;
        transform: translateY(0) scale(1);
      }
      100% {
        opacity: 0;
        transform: translateY(8px) scale(0.85);
      }
    }

    .anim-brand-text {
      font-size: 20px;
      font-weight: 800;
      color: #ffffff;
      letter-spacing: -0.02em;
      display: flex;
      align-items: center;
      gap: 8px;
    }

    .anim-role-tag {
      font-size: 10px;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.08em;
      padding: 3px 8px;
      border-radius: 999px;
      background: rgba(139, 92, 246, 0.2);
      border: 1px solid rgba(168, 85, 247, 0.45);
      color: #c4b5fd;
    }

    .anim-subtext {
      font-size: 13px;
      font-weight: 500;
      color: #94a3b8;
      letter-spacing: 0.02em;
    }
  </style>
</head>
<body>

  <div class="bg-grid"></div>

  <!-- FULLSCREEN NUNNO 360 ROTATE & ZOOM-OUT OVERLAY -->
  <div id="loginAnimOverlay" class="login-anim-overlay" aria-hidden="true" role="status" aria-live="polite">
    <div class="anim-aura"></div>
    <div class="anim-scene">
      <div class="anim-logo-box">
        <img src="assets/images/nunno-logo.png" alt="Nunno" class="anim-logo-img">
      </div>
      <div class="anim-label">
        <div class="anim-brand-text">
          <span>Nunno</span>
          <span class="anim-role-tag" id="animRoleTag">Omni-Desk</span>
        </div>
        <div class="anim-subtext" id="animSubtext">Opening workspace…</div>
      </div>
    </div>
  </div>

  <main class="login-container">

    <!-- HERO NUNNO LOGO WITH PURPLE EDGE GLOW -->
    <header class="brand-hero">
      <div class="logo-frame" id="logoHeroFrame">
        <img src="assets/images/nunno-logo.png" alt="Nunno Omni-Desk" class="logo-img">
      </div>
      <h1 class="brand-title">
        Nunno
        <span class="brand-badge">Omni-Desk</span>
      </h1>
      <p class="brand-subtitle">Internal WhatsApp CRM & Outreach Hub</p>
    </header>

    <!-- LOGIN CARD -->
    <div class="login-card">

      <div id="authAlert" class="auth-alert" role="alert"></div>

      <form id="authForm" autocomplete="on">
        <div class="form-group">
          <label class="form-label" for="workEmail">Work Username or Email</label>
          <div class="input-wrapper">
            <input 
              type="text" 
              id="workEmail" 
              class="form-input" 
              placeholder="admin or closer" 
              required 
              autofocus 
              autocomplete="username"
            >
            <svg class="input-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>
            </svg>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label" for="workPassword">Password</label>
          <div class="input-wrapper">
            <input 
              type="password" 
              id="workPassword" 
              class="form-input" 
              placeholder="••••••••••••" 
              required 
              autocomplete="current-password"
            >
            <svg class="input-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <rect x="3" y="11" width="18" height="11" rx="3"></rect>
              <path d="M7 11V7a5 5 0 0110 0v4"></path>
            </svg>
            <button type="button" class="pwd-toggle" id="pwdToggleBtn">SHOW</button>
          </div>
        </div>

        <button type="submit" id="submitBtn" class="submit-btn">
          Sign In to Omni-Desk
        </button>

        <!-- Quick 1-Click Fillers -->
        <div class="quick-fill-bar">
          <button type="button" class="quick-fill-btn" id="fillAdminBtn">
            👑 Admin
          </button>
          <button type="button" class="quick-fill-btn" id="fillCloserBtn">
            👨‍💼 Closer (Hamza)
          </button>
        </div>
      </form>

      <footer class="card-footer">
        <div>🔒 <strong>Authorized Personnel Only.</strong> No public registration.</div>
        <div class="footer-divider"></div>
        <div>Single-Number Multi-Closer Architecture with Anti-Theft Lead Locking</div>
      </footer>

    </div>

  </main>

  <script>
    // Password Toggle
    const pwdInput = document.getElementById('workPassword');
    const pwdToggle = document.getElementById('pwdToggleBtn');
    const emailInput = document.getElementById('workEmail');

    pwdToggle.addEventListener('click', () => {
      const isPwd = pwdInput.type === 'password';
      pwdInput.type = isPwd ? 'text' : 'password';
      pwdToggle.textContent = isPwd ? 'HIDE' : 'SHOW';
    });

    // 1-Click Quick Fillers
    document.getElementById('fillAdminBtn')?.addEventListener('click', () => {
      emailInput.value = 'admin';
      pwdInput.value = 'Admin@Omnidesk2026!';
    });

    document.getElementById('fillCloserBtn')?.addEventListener('click', () => {
      emailInput.value = 'hamza';
      pwdInput.value = 'Agent@123';
    });

    // Clean, direct submission with full Nunno 360 Spin & Zoom-Out login animation
    const form = document.getElementById('authForm');
    const submitBtn = document.getElementById('submitBtn');
    const alertBox = document.getElementById('authAlert');

    form.addEventListener('submit', async (e) => {
      e.preventDefault();

      const username = emailInput.value.trim();
      const password = pwdInput.value;

      if (!username || !password) {
        showFeedback('Please enter both your username and password.', true);
        return;
      }

      submitBtn.disabled = true;
      submitBtn.innerHTML = '<span class="spinner"></span> Signing in…';
      alertBox.style.display = 'none';

      try {
        const response = await fetch('api/auth.php?action=login', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ username, password })
        });

        const data = await response.json();

        if (data.success) {
          localStorage.setItem('nunno_active_session', JSON.stringify(data.user));
          triggerLoginAnimation(data);
        } else {
          showFeedback(data.error || 'Invalid credentials.', true);
          submitBtn.disabled = false;
          submitBtn.textContent = 'Sign In to Omni-Desk';
        }
      } catch (err) {
        // Offline / local fallback
        if (username.toLowerCase() === 'admin' && password === 'Admin@Omnidesk2026!') {
          const offlineAdmin = {
            id: 'USR-ADMIN-01',
            name: 'System Administrator',
            username: 'admin',
            email: 'admin@nunno.cloud',
            role: 'admin',
            designation: 'Master Administrator'
          };
          localStorage.setItem('nunno_active_session', JSON.stringify(offlineAdmin));
          triggerLoginAnimation({ user: offlineAdmin });
          return;
        }

        if (username.toLowerCase() === 'hamza' && password === 'Agent@123') {
          const offlineCloser = {
            id: 'EMP-101',
            name: 'Hamza Khan',
            username: 'hamza',
            email: 'hamza@nunno.cloud',
            role: 'member',
            designation: 'Team Member'
          };
          localStorage.setItem('nunno_active_session', JSON.stringify(offlineCloser));
          triggerLoginAnimation({ user: offlineCloser });
          return;
        }

        showFeedback('Connection error or invalid credentials.', true);
        submitBtn.disabled = false;
        submitBtn.textContent = 'Sign In to Omni-Desk';
      }
    });

    function showFeedback(msg, isError) {
      alertBox.textContent = msg;
      alertBox.className = 'auth-alert ' + (isError ? 'auth-alert--error' : 'auth-alert--success');
      alertBox.style.display = 'block';
    }

    function triggerLoginAnimation(data) {
      const overlay = document.getElementById('loginAnimOverlay');
      const loginContainer = document.querySelector('.login-container');
      const roleTag = document.getElementById('animRoleTag');
      const subtext = document.getElementById('animSubtext');

      const userName = (data.user && data.user.name) ? data.user.name : '';
      const isAdmin = (data.user && data.user.role === 'admin');

      if (isAdmin) {
        roleTag.textContent = 'Admin Suite';
        subtext.textContent = userName ? `Welcome back, ${userName}` : 'Opening Executive Desk…';
      } else {
        roleTag.textContent = 'Closer Portal';
        subtext.textContent = userName ? `Welcome back, ${userName}` : 'Opening Closer Workspace…';
      }

      // 1. Instantly hide the login card and header so the login page CAN NEVER flash or glitch
      if (loginContainer) {
        loginContainer.style.display = 'none';
      }

      // 2. Activate the 360 spin & zoom-out animation overlay
      overlay.classList.add('active');

      // 3. At 1.6s when the 360 rotation & zoom-out completes, replace location immediately
      setTimeout(() => {
        window.location.replace('index.php');
      }, 1600);
    }
  </script>
</body>
</html>
