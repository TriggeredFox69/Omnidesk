<?php
/**
 * Chatly — Super Admin Command Center (/ss)
 * Platform owner console: live overview, companies, Auto Data limits, live chat terminal,
 * number intelligence, fallback numbers, closer approvals, Nunno settlement, brand leads,
 * logins and audit trail. Served through ss.php / ss/index.php.
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

// Complete logout action: thoroughly purge session, wipe cookies, and redirect to homepage (/)
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
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
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <base href="/">
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="robots" content="noindex, nofollow">
  <title>Chatly Super Admin</title>
  <link rel="icon" type="image/png" href="assets/images/chatly-icon.png">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500;700&display=swap" rel="stylesheet">
  <script>
    (function () {
      try { if (localStorage.getItem('nunno_theme') === 'light') document.documentElement.classList.add('theme-light'); } catch (_) {}
    })();
    function toggleTheme() {
      var isLight = document.documentElement.classList.toggle('theme-light');
      try { localStorage.setItem('nunno_theme', isLight ? 'light' : 'dark'); } catch (_) {}
    }
    function handleSadminLogout(e) {
      if (e) e.preventDefault();
      try {
        localStorage.removeItem('omnidesk_user');
        localStorage.removeItem('nunno_active_session');
        localStorage.removeItem('omnidesk_impersonated_from_admin');
        localStorage.removeItem('omnidesk_impersonated_user');
        localStorage.removeItem('omnidesk_impersonated_name');
        localStorage.removeItem('omnidesk_active_chat_phone');
        sessionStorage.clear();
        sessionStorage.setItem('chatly_logged_out', '1');
      } catch (_) {}
      window.location.href = '/ss?action=logout';
    }
  </script>
  <style>
    :root {
      --bg: #04080a;
      --panel: #0a1416;
      --panel-2: #0d1a1d;
      --panel-3: #112327;
      --line: rgba(120, 255, 200, 0.08);
      --line-2: rgba(120, 255, 200, 0.16);
      --grid: rgba(120, 255, 200, 0.025);
      --text: #e4f2ec;
      --text-2: #94aea5;
      --text-3: #5f7a70;
      --green: #1fe08a;
      --amber: #ffb547;
      --red: #ff5c7a;
      --cyan: #4cc9f0;
      --violet: #a78bfa;
      --gold: #eab308;
      --track: rgba(255, 255, 255, 0.07);
      --shadow: 0 10px 30px rgba(0, 0, 0, 0.35);
      --side-bg: rgba(5, 12, 14, 0.88);
      --r: 14px;
      --side: 244px;
      color-scheme: dark;
    }
    html.theme-light {
      --bg: #eef5f2;
      --panel: #ffffff;
      --panel-2: #f5faf8;
      --panel-3: #e9f2ee;
      --line: #dfebe5;
      --line-2: #c9ddd4;
      --grid: rgba(10, 80, 50, 0.035);
      --text: #0b1a14;
      --text-2: #46645a;
      --text-3: #7d978d;
      --green: #0c9f61;
      --amber: #b86e00;
      --red: #e11d48;
      --cyan: #0284c7;
      --violet: #7c3aed;
      --gold: #a87c00;
      --track: rgba(0, 0, 0, 0.07);
      --shadow: 0 6px 20px rgba(20, 60, 40, 0.08);
      --side-bg: rgba(255, 255, 255, 0.92);
      color-scheme: light;
    }
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    [hidden] { display: none !important; }
    html { -webkit-text-size-adjust: 100%; }
    body {
      font-family: 'Inter', -apple-system, 'Segoe UI', sans-serif;
      background-color: var(--bg);
      background-image:
        radial-gradient(circle at 18% -10%, rgba(31, 224, 138, 0.09), transparent 42%),
        radial-gradient(circle at 100% 100%, rgba(76, 201, 240, 0.05), transparent 40%),
        linear-gradient(var(--grid) 1px, transparent 1px),
        linear-gradient(90deg, var(--grid) 1px, transparent 1px);
      background-size: auto, auto, 36px 36px, 36px 36px;
      background-attachment: fixed;
      color: var(--text);
      min-height: 100vh;
      font-size: 14px;
      -webkit-font-smoothing: antialiased;
    }
    a { color: inherit; }
    button { font-family: inherit; }
    .mono { font-family: 'JetBrains Mono', ui-monospace, Consolas, monospace; }
    .c-green { color: var(--green); } .c-amber { color: var(--amber); } .c-red { color: var(--red); }
    .c-cyan { color: var(--cyan); } .c-gold { color: var(--gold); } .c-dim { color: var(--text-3); }
    .ic { width: 18px; height: 18px; fill: none; stroke: currentColor; stroke-width: 1.8; stroke-linecap: round; stroke-linejoin: round; flex: 0 0 auto; }

    .chatly-logo { height: 28px; width: auto; display: block; }
    .chatly-logo--light { display: none; }
    html.theme-light .chatly-logo--dark { display: none; }
    html.theme-light .chatly-logo--light { display: block; }

    /* Buttons */
    .btn {
      display: inline-flex; align-items: center; justify-content: center; gap: 7px;
      padding: 8px 14px; border-radius: 9px; font-size: 12.5px; font-weight: 700;
      border: 1px solid var(--line-2); background: var(--panel-2); color: var(--text);
      cursor: pointer; text-decoration: none; white-space: nowrap; transition: background .15s, border-color .15s, transform .15s;
    }
    .btn:hover { border-color: var(--green); }
    .btn:disabled { opacity: .55; cursor: wait; }
    .btn-gold { background: linear-gradient(135deg, #f5c518, #ca8a04); color: #1a1200; border-color: transparent; box-shadow: 0 4px 16px rgba(234, 179, 8, 0.25); }
    .btn-gold:hover { border-color: transparent; transform: translateY(-1px); }
    .btn-green { background: rgba(31, 224, 138, 0.12); color: var(--green); border-color: rgba(31, 224, 138, 0.35); }
    .btn-danger { background: rgba(255, 92, 122, 0.1); color: var(--red); border-color: rgba(255, 92, 122, 0.3); }
    .btn-danger:hover { border-color: var(--red); }
    .btn-ghost { background: transparent; }
    .btn-sm { padding: 6px 11px; font-size: 12px; }
    .btn-xs { padding: 4px 9px; font-size: 11.5px; border-radius: 7px; }
    .icon-btn { width: 36px; height: 36px; padding: 0; border-radius: 9px; position: relative; }
    .icon-btn .dot-badge { position: absolute; top: -5px; right: -5px; min-width: 18px; height: 18px; padding: 0 5px; border-radius: 99px; background: var(--red); color: #fff; font: 700 10px 'JetBrains Mono', monospace; display: inline-flex; align-items: center; justify-content: center; }

    /* Shell */
    .shell { display: grid; grid-template-columns: var(--side) minmax(0, 1fr); min-height: 100vh; }
    .side {
      position: sticky; top: 0; height: 100vh; display: flex; flex-direction: column;
      border-right: 1px solid var(--line-2); background: var(--side-bg);
      -webkit-backdrop-filter: blur(14px); backdrop-filter: blur(14px); z-index: 40; min-width: 0;
    }
    .side-brand { display: flex; align-items: center; gap: 10px; padding: 18px 18px 14px; border-bottom: 1px solid var(--line); }
    .sa-chip { font: 700 9.5px 'JetBrains Mono', monospace; letter-spacing: .14em; color: var(--gold); border: 1px solid rgba(234, 179, 8, 0.4); background: rgba(234, 179, 8, 0.1); padding: 3px 6px; border-radius: 5px; white-space: nowrap; }
    .side-nav { flex: 1; overflow-y: auto; padding: 6px 10px 12px; }
    .nav-g { font-size: 10px; letter-spacing: .16em; text-transform: uppercase; color: var(--text-3); font-weight: 700; padding: 14px 10px 6px; }
    .nav-i {
      display: flex; align-items: center; gap: 10px; width: 100%; padding: 9px 10px; margin: 1px 0;
      border-radius: 9px; border: 1px solid transparent; background: none; color: var(--text-2);
      font-size: 13px; font-weight: 600; cursor: pointer; text-align: left; position: relative;
    }
    .nav-i:hover { color: var(--text); background: rgba(127, 127, 127, 0.06); }
    .nav-i.active { color: var(--text); background: linear-gradient(90deg, rgba(31, 224, 138, 0.15), rgba(31, 224, 138, 0.02)); border-color: rgba(31, 224, 138, 0.25); }
    .nav-i.active::before { content: ''; position: absolute; left: -10px; top: 8px; bottom: 8px; width: 3px; border-radius: 3px; background: var(--green); box-shadow: 0 0 10px var(--green); }
    .nav-i.active .ic { color: var(--green); }
    .nav-b { margin-left: auto; font: 700 10.5px 'JetBrains Mono', monospace; padding: 1px 7px; border-radius: 99px; background: var(--red); color: #fff; }
    .nav-b.green { background: var(--green); color: #02130b; }
    .nav-live { margin-left: auto; width: 7px; height: 7px; border-radius: 50%; background: var(--green); box-shadow: 0 0 8px var(--green); animation: pulse 1.6s infinite; }
    .side-foot { padding: 12px; border-top: 1px solid var(--line); display: flex; align-items: center; gap: 6px; }
    .who { flex: 1; min-width: 0; }
    .who b { display: block; font-size: 12.5px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .who small { font-size: 11px; color: var(--text-3); }
    .m-only { display: none !important; }

    .main { min-width: 0; width: 100%; max-width: 1640px; padding: 22px 28px 56px; }
    .topbar { display: flex; align-items: flex-end; justify-content: space-between; gap: 14px; flex-wrap: wrap; margin-bottom: 18px; }
    .topbar h1 { font-size: 22px; font-weight: 800; letter-spacing: -.02em; display: flex; align-items: center; gap: 10px; }
    .topbar h1 .prompt { font: 500 15px 'JetBrains Mono', monospace; color: var(--green); }
    .topbar p { font-size: 12.5px; color: var(--text-2); margin-top: 4px; }
    .top-actions { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
    .live-pill {
      display: inline-flex; align-items: center; gap: 8px; padding: 7px 12px; border-radius: 99px;
      border: 1px solid rgba(31, 224, 138, 0.3); background: rgba(31, 224, 138, 0.07);
      font: 700 11px 'JetBrains Mono', monospace; letter-spacing: .08em; color: var(--green); white-space: nowrap;
    }
    .live-pill .pulse { width: 8px; height: 8px; border-radius: 50%; background: var(--green); animation: ring 1.8s infinite; }
    .live-pill time { color: var(--text-2); font-weight: 500; letter-spacing: 0; }
    .live-pill.bad { color: var(--red); border-color: rgba(255, 92, 122, 0.35); background: rgba(255, 92, 122, 0.08); }
    .live-pill.bad .pulse { background: var(--red); animation: none; }
    .live-pill.blip .pulse { animation: blip .6s ease-out; }
    .banner { padding: 11px 14px; border-radius: 10px; font-size: 13px; margin-bottom: 14px; border: 1px solid rgba(255, 92, 122, 0.35); background: rgba(255, 92, 122, 0.08); color: var(--red); }
    .banner a { font-weight: 700; }
    .banner.warn { border-color: rgba(255, 181, 71, 0.35); background: rgba(255, 181, 71, 0.08); color: var(--amber); }

    @keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: .35; } }
    @keyframes ring { 0% { box-shadow: 0 0 0 0 rgba(31, 224, 138, 0.55); } 70% { box-shadow: 0 0 0 8px rgba(31, 224, 138, 0); } 100% { box-shadow: 0 0 0 0 rgba(31, 224, 138, 0); } }
    @keyframes blip { 0% { transform: scale(1.9); box-shadow: 0 0 14px var(--green); } 100% { transform: scale(1); } }
    @keyframes fadeUp { from { opacity: 0; transform: translateY(6px); } to { opacity: 1; transform: none; } }

    .sec { animation: fadeUp .2s ease-out; }
    .grid { display: grid; gap: 16px; margin-bottom: 16px; }
    .grid > * { min-width: 0; }
    .g-kpi { grid-template-columns: repeat(4, minmax(0, 1fr)); }
    .g-2 { grid-template-columns: minmax(0, 1.45fr) minmax(0, 1fr); }
    .g-2e { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .g-3 { grid-template-columns: repeat(3, minmax(0, 1fr)); }

    /* Panels */
    .panel { background: var(--panel); border: 1px solid var(--line-2); border-radius: var(--r); box-shadow: var(--shadow); min-width: 0; position: relative; margin-bottom: 16px; }
    .grid > .panel { margin-bottom: 0; }
    .panel::before { content: ''; position: absolute; top: -1px; left: -1px; width: 16px; height: 16px; border-top: 2px solid var(--green); border-left: 2px solid var(--green); border-top-left-radius: var(--r); opacity: .75; pointer-events: none; }
    .panel-h { display: flex; align-items: center; justify-content: space-between; gap: 10px 14px; flex-wrap: wrap; padding: 14px 16px; border-bottom: 1px solid var(--line); }
    .panel-t { font-size: 12px; font-weight: 800; letter-spacing: .08em; text-transform: uppercase; display: flex; align-items: center; gap: 8px; }
    .panel-t::before { content: ''; width: 6px; height: 6px; background: var(--green); border-radius: 2px; box-shadow: 0 0 8px var(--green); flex: 0 0 auto; }
    .panel-d { font-size: 12px; color: var(--text-2); margin-top: 4px; line-height: 1.45; }
    .panel-b { padding: 16px; }
    .panel-b.flush { padding: 0; }
    .tools { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
    .empty-note { color: var(--text-3); font-size: 12.5px; padding: 18px 4px; text-align: center; }

    /* KPI cards */
    .kpi { background: var(--panel); border: 1px solid var(--line-2); border-radius: var(--r); padding: 14px 14px 10px; position: relative; overflow: hidden; display: flex; flex-direction: column; gap: 4px; --tone: var(--green); }
    .kpi::after { content: ''; position: absolute; right: -40px; top: -40px; width: 120px; height: 120px; border-radius: 50%; background: radial-gradient(circle, var(--tone), transparent 70%); opacity: .09; pointer-events: none; }
    .kpi[data-act] { cursor: pointer; transition: border-color .15s, transform .15s; }
    .kpi[data-act]:hover { border-color: var(--tone); transform: translateY(-1px); }
    .kpi[data-tone="amber"] { --tone: var(--amber); } .kpi[data-tone="cyan"] { --tone: var(--cyan); }
    .kpi[data-tone="violet"] { --tone: var(--violet); } .kpi[data-tone="gold"] { --tone: var(--gold); }
    .kpi-h { display: flex; justify-content: space-between; align-items: center; gap: 8px; font-size: 10.5px; text-transform: uppercase; letter-spacing: .08em; color: var(--text-3); font-weight: 700; }
    .kpi-h .ic { width: 16px; height: 16px; color: var(--tone); }
    .kpi-v { font-family: 'JetBrains Mono', monospace; font-size: 25px; font-weight: 700; color: var(--tone); letter-spacing: -.02em; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; line-height: 1.25; }
    .kpi-v.tick { animation: tick .8s ease-out; }
    @keyframes tick { 0% { text-shadow: 0 0 14px var(--tone); filter: brightness(1.6); } 100% { text-shadow: none; filter: none; } }
    .kpi-s { font-size: 11.5px; color: var(--text-2); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .kpi-spark { height: 30px; margin-top: 4px; }
    .kpi-spark svg { width: 100%; height: 100%; display: block; overflow: visible; }

    /* Bars, donuts, stats */
    .bar { height: 8px; background: var(--track); border-radius: 99px; overflow: hidden; }
    .bar i { display: block; height: 100%; width: 0; border-radius: inherit; background: var(--green); transition: width .6s ease; }
    .bar.warn i { background: var(--amber); }
    .bar.over i { background: var(--red); }
    .bar.unl i { width: 100% !important; background: repeating-linear-gradient(45deg, rgba(31, 224, 138, 0.32) 0 6px, rgba(31, 224, 138, 0.1) 6px 12px); }
    .hb { margin-bottom: 11px; }
    .hb:last-child { margin-bottom: 0; }
    .hb-top { display: flex; justify-content: space-between; gap: 10px; font-size: 12px; margin-bottom: 5px; }
    .hb-l { color: var(--text-2); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; min-width: 0; }
    .hb-v { font-family: 'JetBrains Mono', monospace; font-weight: 700; white-space: nowrap; }
    .viz { display: flex; align-items: center; gap: 18px; flex-wrap: wrap; }
    .donut { position: relative; width: 132px; height: 132px; flex: 0 0 auto; }
    .donut svg { width: 100%; height: 100%; transform: rotate(0deg); }
    .donut circle { transition: stroke-dasharray .6s ease; }
    .donut-c { position: absolute; inset: 0; display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center; padding: 18px; }
    .donut-c b { font: 700 16px 'JetBrains Mono', monospace; max-width: 100%; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .donut-c small { font-size: 10px; color: var(--text-3); text-transform: uppercase; letter-spacing: .08em; }
    .legend { list-style: none; flex: 1; min-width: 150px; display: grid; gap: 7px; }
    .legend li { display: flex; align-items: center; gap: 8px; font-size: 12px; color: var(--text-2); }
    .legend i { width: 9px; height: 9px; border-radius: 3px; flex: 0 0 auto; }
    .legend b { margin-left: auto; font-family: 'JetBrains Mono', monospace; color: var(--text); white-space: nowrap; }
    .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); gap: 10px; margin-top: 14px; }
    .stats > div { background: var(--panel-2); border: 1px solid var(--line); border-radius: 10px; padding: 9px 11px; min-width: 0; }
    .stats dt { font-size: 10.5px; text-transform: uppercase; letter-spacing: .07em; color: var(--text-3); font-weight: 700; }
    .stats dd { font: 700 14px 'JetBrains Mono', monospace; margin-top: 3px; overflow-wrap: anywhere; }
    .stats dd small { display: block; font: 500 11px 'Inter', sans-serif; color: var(--text-2); margin-top: 2px; }
    .mini-kpis { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 10px; }
    .mini-kpis > div { background: var(--panel-2); border: 1px solid var(--line); border-radius: 10px; padding: 11px 12px; min-width: 0; }
    .mini-kpis span { display: block; font-size: 10.5px; text-transform: uppercase; letter-spacing: .07em; color: var(--text-3); font-weight: 700; }
    .mini-kpis b { display: block; font: 700 19px 'JetBrains Mono', monospace; margin-top: 4px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .mini-kpis small { display: block; font-size: 11px; color: var(--text-2); margin-top: 2px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .mini-kpis .hl { border-color: rgba(31, 224, 138, 0.45); background: linear-gradient(180deg, rgba(31, 224, 138, 0.12), rgba(31, 224, 138, 0.02)); }

    .feed { list-style: none; }
    .feed li { display: grid; grid-template-columns: auto minmax(0, 1fr); gap: 4px 10px; padding: 9px 0; border-bottom: 1px dashed var(--line); font-size: 12.5px; }
    .feed li:last-child { border-bottom: 0; }
    .feed .when { font: 500 11px 'JetBrains Mono', monospace; color: var(--text-3); padding-top: 2px; white-space: nowrap; }
    .feed .what { overflow-wrap: anywhere; }
    .feed small { display: block; color: var(--text-3); font-size: 11px; margin-top: 2px; }
    .feed .acts { grid-column: 1 / -1; display: flex; gap: 6px; flex-wrap: wrap; }

    /* Tags */
    .tag { display: inline-flex; align-items: center; gap: 5px; padding: 2px 8px; border-radius: 99px; font-size: 11px; font-weight: 700; border: 1px solid var(--line-2); color: var(--text-2); white-space: nowrap; }
    .tag.green { color: var(--green); border-color: rgba(31, 224, 138, 0.35); background: rgba(31, 224, 138, 0.08); }
    .tag.amber { color: var(--amber); border-color: rgba(255, 181, 71, 0.35); background: rgba(255, 181, 71, 0.08); }
    .tag.red { color: var(--red); border-color: rgba(255, 92, 122, 0.35); background: rgba(255, 92, 122, 0.08); }
    .tag.cyan { color: var(--cyan); border-color: rgba(76, 201, 240, 0.35); background: rgba(76, 201, 240, 0.08); }
    .tag.gold { color: var(--gold); border-color: rgba(234, 179, 8, 0.35); background: rgba(234, 179, 8, 0.08); }
    .tag.violet { color: var(--violet); border-color: rgba(167, 139, 250, 0.35); background: rgba(167, 139, 250, 0.08); }
    .tag.gray { color: var(--text-2); background: rgba(127, 127, 127, 0.08); }

    /* Forms */
    .fc { width: 100%; min-width: 0; padding: 9px 11px; border-radius: 9px; background: var(--panel-2); border: 1px solid var(--line-2); color: var(--text); font: 13px 'Inter', sans-serif; }
    .fc:focus { outline: none; border-color: var(--green); box-shadow: 0 0 0 3px rgba(31, 224, 138, 0.15); }
    .fc-sm { padding: 7px 10px; font-size: 12.5px; width: auto; }
    .fc-xs { padding: 3px 6px; font-size: 11px; width: auto; border-radius: 6px; margin-top: 5px; }
    .search { width: 240px; max-width: 100%; }
    .fl { display: block; font-size: 11.5px; color: var(--text-2); font-weight: 600; margin-bottom: 5px; }
    .fg { margin-bottom: 12px; min-width: 0; }
    .frow { display: grid; grid-template-columns: 1fr 1fr; gap: 0 12px; }
    .hint { font-size: 11px; color: var(--text-3); margin-top: 4px; }
    .err-box { padding: 10px 12px; border-radius: 9px; background: rgba(255, 92, 122, 0.1); border: 1px solid rgba(255, 92, 122, 0.3); color: var(--red); font-size: 12.5px; margin-bottom: 12px; }
    .seg { display: inline-flex; padding: 3px; border: 1px solid var(--line-2); border-radius: 10px; background: var(--panel-2); }
    .seg button { border: 0; background: none; color: var(--text-2); font-size: 12.5px; font-weight: 700; padding: 6px 14px; border-radius: 7px; cursor: pointer; }
    .seg button.on { background: var(--green); color: #02130b; }

    /* Tables (cards on phones) */
    .tbl-wrap { overflow-x: auto; -webkit-overflow-scrolling: touch; }
    table.rt { width: 100%; border-collapse: collapse; font-size: 13px; }
    .rt th { text-align: left; font-size: 10.5px; text-transform: uppercase; letter-spacing: .07em; color: var(--text-3); font-weight: 700; padding: 10px 14px; border-bottom: 1px solid var(--line-2); white-space: nowrap; background: var(--panel-2); }
    .rt td { padding: 11px 14px; border-bottom: 1px solid var(--line); vertical-align: middle; }
    .rt tbody tr:hover td { background: rgba(127, 127, 127, 0.04); }
    .rt tr.unread td { background: rgba(31, 224, 138, 0.04); }
    .rt small { display: block; color: var(--text-3); font-size: 11.5px; margin-top: 2px; }
    .rt td.empty { text-align: center; color: var(--text-3); padding: 28px 14px; }
    .rt td.acts .cv { display: flex; gap: 6px; flex-wrap: wrap; align-items: center; }
    .rt .clamp { display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; color: var(--text-2); font-size: 12px; max-width: 300px; }
    .co { display: flex; align-items: center; gap: 11px; min-width: 0; }
    .co > div:last-child { min-width: 0; }
    .av { width: 34px; height: 34px; border-radius: 9px; flex: 0 0 auto; display: flex; align-items: center; justify-content: center; font: 700 14px 'JetBrains Mono', monospace; color: #02130b; background: linear-gradient(135deg, var(--green), #0f9f62); }
    .av img { width: 100%; height: 100%; object-fit: cover; border-radius: inherit; }
    .lnk { color: var(--cyan); text-decoration: underline; text-underline-offset: 2px; font-size: 12px; }
    .row-acts { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; margin-top: 5px; }
    .more { text-align: center; padding: 12px; }

    /* Live terminal */
    .term {
      --t-bg: #020604; --t-green: #39ff88; --t-amber: #ffb000; --t-dim: #4c7c63; --t-cyan: #5ce1e6; --t-red: #ff5f6d;
      background: var(--t-bg); border: 1px solid rgba(57, 255, 136, 0.28); border-radius: 12px; overflow: hidden; position: relative;
      box-shadow: inset 0 0 60px rgba(57, 255, 136, 0.05), 0 0 30px rgba(57, 255, 136, 0.06), var(--shadow);
      font-family: 'JetBrains Mono', ui-monospace, Consolas, monospace; color: var(--t-green);
    }
    .term::after { content: ''; position: absolute; inset: 0; pointer-events: none; z-index: 1; background: repeating-linear-gradient(0deg, rgba(0, 0, 0, 0.22) 0 1px, transparent 1px 3px); opacity: .55; }
    .term-bar { display: flex; align-items: center; gap: 10px; padding: 9px 12px; background: linear-gradient(#0b1610, #07100b); border-bottom: 1px solid rgba(57, 255, 136, 0.18); }
    .dots { display: flex; gap: 6px; flex: 0 0 auto; }
    .dots i { width: 11px; height: 11px; border-radius: 50%; background: #ff5f57; }
    .dots i:nth-child(2) { background: #febc2e; } .dots i:nth-child(3) { background: #28c840; }
    .term-title { font-size: 11.5px; color: var(--t-dim); flex: 1; min-width: 0; text-align: center; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .term-state { font-size: 11px; font-weight: 700; letter-spacing: .1em; white-space: nowrap; }
    .term-state.live { color: var(--t-green); } .term-state.paused { color: var(--t-amber); } .term-state.err { color: var(--t-red); }
    .term-ctrl { display: flex; gap: 6px; flex-wrap: wrap; align-items: center; padding: 8px 12px; border-bottom: 1px solid rgba(57, 255, 136, 0.12); background: #040a07; position: relative; z-index: 2; }
    .term-ctrl .lbl { font-size: 11px; color: var(--t-dim); }
    .term-ctrl select, .term-ctrl input { background: #06100b; border: 1px solid rgba(57, 255, 136, 0.25); color: var(--t-green); font: 12px 'JetBrains Mono', monospace; padding: 6px 8px; border-radius: 6px; min-width: 0; max-width: 100%; }
    .term-ctrl input { flex: 1 1 140px; }
    .term-ctrl select:focus, .term-ctrl input:focus { outline: none; border-color: var(--t-green); }
    .t-btn { background: transparent; border: 1px solid rgba(57, 255, 136, 0.35); color: var(--t-green); font: 700 11px 'JetBrains Mono', monospace; padding: 6px 10px; border-radius: 6px; cursor: pointer; text-transform: uppercase; letter-spacing: .06em; white-space: nowrap; }
    .t-btn:hover { background: rgba(57, 255, 136, 0.1); }
    .t-btn.on { background: var(--t-amber); color: #000; border-color: var(--t-amber); }
    .term-screen { height: calc(100vh - 300px); min-height: 340px; overflow-y: auto; overflow-x: hidden; padding: 12px 14px 16px; font-size: 12.5px; line-height: 1.7; position: relative; scrollbar-width: thin; scrollbar-color: #1d3b2a transparent; }
    .term-screen.mini { height: 318px; min-height: 0; font-size: 11.5px; }
    .tl { white-space: pre-wrap; overflow-wrap: anywhere; text-shadow: 0 0 6px rgba(57, 255, 136, 0.35); }
    .tl .tt { color: var(--t-dim); }
    .tl .tc { color: var(--t-cyan); text-shadow: 0 0 6px rgba(92, 225, 230, 0.35); }
    .tl .tg { color: var(--t-dim); }
    .tl .ta { color: var(--t-green); font-weight: 700; }
    .tl .tld { color: var(--t-amber); font-weight: 700; text-shadow: 0 0 6px rgba(255, 176, 0, 0.4); }
    .tl .tp { color: #9fc9b2; }
    .tl.agent .tm { color: #c3ffd9; }
    .tl.lead .tm { color: #ffe0a3; text-shadow: 0 0 6px rgba(255, 176, 0, 0.25); }
    .tl .ts { color: var(--t-dim); font-size: .92em; }
    .tl .ts.s-paid { color: var(--t-green); } .tl .ts.s-pending_verification, .tl .ts.s-review { color: var(--t-amber); }
    .tl.sys { color: var(--t-dim); text-shadow: none; }
    .tl.sys.err { color: var(--t-red); }
    .tl.new { animation: typein .45s steps(28, end); }
    @keyframes typein { from { clip-path: inset(0 100% 0 0); } to { clip-path: inset(0 0 0 0); } }
    .t-prompt { margin-top: 2px; white-space: nowrap; overflow: hidden; }
    .t-ps1 { color: var(--t-cyan); }
    .cursor { display: inline-block; width: .62em; height: 1.15em; vertical-align: -3px; background: var(--t-green); box-shadow: 0 0 8px var(--t-green); animation: blink 1s steps(1) infinite; }
    .term.paused .cursor { background: var(--t-amber); box-shadow: 0 0 8px var(--t-amber); animation: none; }
    @keyframes blink { 50% { opacity: 0; } }
    .term-foot { display: flex; gap: 6px 16px; flex-wrap: wrap; padding: 7px 12px; border-top: 1px solid rgba(57, 255, 136, 0.15); font-size: 11px; color: var(--t-dim); background: #040a07; position: relative; z-index: 2; }
    .term-foot b { color: var(--t-green); font-weight: 700; }
    .t-jump { position: absolute; right: 18px; bottom: 46px; z-index: 3; background: var(--t-amber); color: #000; border: 0; font: 700 11.5px 'JetBrains Mono', monospace; padding: 7px 12px; border-radius: 99px; cursor: pointer; box-shadow: 0 6px 20px rgba(0, 0, 0, 0.5); }

    /* Auto Data */
    .ad-row { display: grid; grid-template-columns: minmax(170px, 1.05fr) minmax(150px, 1fr) minmax(150px, 1fr) minmax(270px, 1.25fr); gap: 14px 18px; align-items: center; padding: 14px 16px; border-bottom: 1px solid var(--line); }
    .ad-row:last-child { border-bottom: 0; }
    .ad-row.suspended { opacity: .7; }
    .ad-row { transition: background-color .4s; }
    .ad-row.ad-flash { background: rgba(234, 179, 8, .14); }
    .ad-meter-top { display: flex; justify-content: space-between; gap: 8px; font-size: 11.5px; color: var(--text-2); margin-bottom: 6px; }
    .ad-meter-top > span:first-child { text-transform: uppercase; letter-spacing: .07em; font-size: 10.5px; font-weight: 700; color: var(--text-3); }
    .ad-num { font-family: 'JetBrains Mono', monospace; white-space: nowrap; }
    .ad-num b { color: var(--text); }
    .ad-num em { font-style: normal; color: var(--text-3); margin-left: 4px; }
    .ad-form { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1fr) auto; gap: 8px; align-items: end; }
    .ad-form label { font-size: 10.5px; text-transform: uppercase; letter-spacing: .07em; color: var(--text-3); font-weight: 700; display: flex; flex-direction: column; gap: 4px; min-width: 0; }
    .ad-form input { width: 100%; min-width: 0; padding: 7px 9px; border-radius: 8px; background: var(--panel-2); border: 1px solid var(--line-2); color: var(--text); font: 600 13px 'JetBrains Mono', monospace; }
    .ad-form input:focus { outline: none; border-color: var(--green); }

    /* Ultimate campaign (red) */
    .nav-uc .ic { color: var(--red); }
    .nav-uc.active { background: linear-gradient(90deg, rgba(255, 70, 90, 0.18), rgba(255, 70, 90, 0.02)); border-color: rgba(255, 70, 90, 0.35); }
    .nav-uc.active::before { background: var(--red); box-shadow: 0 0 10px var(--red); }
    .nav-uc.active .ic { color: var(--red); }
    .nav-live.red { background: var(--red); box-shadow: 0 0 8px var(--red); }
    .uc { --uc: #ff3b55; --uc-2: #c8102e; --uc-soft: rgba(255, 59, 85, 0.1); --uc-line: rgba(255, 59, 85, 0.3); }
    .uc .fc:focus { border-color: var(--uc); box-shadow: 0 0 0 3px rgba(255, 59, 85, 0.16); }
    .uc-hero { display: flex; justify-content: space-between; align-items: flex-start; gap: 14px; flex-wrap: wrap; padding: 18px 20px; border-radius: var(--r); margin-bottom: 14px;
      border: 1px solid var(--uc-line); background: radial-gradient(circle at 0% 0%, rgba(255, 59, 85, 0.22), transparent 55%), linear-gradient(135deg, rgba(200, 16, 46, 0.16), rgba(255, 59, 85, 0.03)); }
    .uc-kicker { font: 700 10.5px 'JetBrains Mono', monospace; letter-spacing: .14em; text-transform: uppercase; color: var(--uc); }
    .uc-hero h2 { font-size: 24px; font-weight: 800; letter-spacing: -.02em; margin-top: 4px; }
    .uc-hero p { font-size: 12.5px; color: var(--text-2); margin-top: 6px; max-width: 720px; line-height: 1.5; }
    .uc-state { display: inline-flex; align-items: center; gap: 8px; padding: 8px 14px; border-radius: 99px; border: 1px solid var(--line-2); background: var(--panel-2); font: 700 12px 'JetBrains Mono', monospace; letter-spacing: .06em; text-transform: uppercase; color: var(--text-2); white-space: nowrap; }
    .uc-state i { width: 9px; height: 9px; border-radius: 50%; background: var(--text-3); }
    .uc-state.running { color: var(--uc); border-color: var(--uc-line); background: var(--uc-soft); }
    .uc-state.running i { background: var(--uc); box-shadow: 0 0 0 0 rgba(255, 59, 85, .6); animation: ucRing 1.6s infinite; }
    .uc-state.paused { color: var(--amber); border-color: rgba(255, 181, 71, .35); } .uc-state.paused i { background: var(--amber); }
    .uc-state.completed { color: var(--green); } .uc-state.completed i { background: var(--green); }
    @keyframes ucRing { 0% { box-shadow: 0 0 0 0 rgba(255, 59, 85, .6); } 70% { box-shadow: 0 0 0 9px rgba(255, 59, 85, 0); } 100% { box-shadow: 0 0 0 0 rgba(255, 59, 85, 0); } }
    .uc-kpis { margin-bottom: 14px; }
    .uc-kpis .hl { border-color: var(--uc-line); background: linear-gradient(180deg, rgba(255, 59, 85, 0.14), rgba(255, 59, 85, 0.02)); }
    .uc-kpis .hl b { color: var(--uc); }
    .uc-grid { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1.3fr) minmax(0, 1fr); gap: 16px; margin-bottom: 16px; align-items: start; }
    .uc-card { background: var(--panel); border: 1px solid var(--line-2); border-radius: 16px; padding: 18px; box-shadow: var(--shadow); min-width: 0; }
    .uc-card-h { display: flex; align-items: center; gap: 10px; padding-bottom: 14px; margin-bottom: 14px; border-bottom: 1px solid var(--line); }
    .uc-card-h b { font-size: 15px; font-weight: 800; }
    .uc-step { width: 28px; height: 28px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font: 800 13px 'Inter', sans-serif; color: #fff; background: linear-gradient(135deg, var(--uc), var(--uc-2)); box-shadow: 0 4px 14px rgba(255, 59, 85, 0.35); flex: 0 0 auto; }
    .uc-chip { margin-left: auto; padding: 4px 10px; border-radius: 99px; font-size: 11.5px; font-weight: 800; color: #fff; background: linear-gradient(135deg, var(--uc), var(--uc-2)); white-space: nowrap; }
    .uc-chip.ghost { color: var(--uc); background: var(--uc-soft); border: 1px solid var(--uc-line); font-weight: 700; }
    .uc-auto { display: flex; align-items: center; gap: 12px; width: 100%; margin: 14px 0 12px; padding: 12px 14px; border-radius: 13px; cursor: pointer; text-align: left; color: var(--text);
      border: 1.5px solid var(--line-2); background: var(--panel-2); transition: border-color .2s, background .2s; }
    .uc-auto-ic { width: 34px; height: 34px; border-radius: 10px; display: inline-flex; align-items: center; justify-content: center; background: var(--track); color: var(--text-2); flex: 0 0 auto; }
    .uc-auto-t { flex: 1; min-width: 0; } .uc-auto-t b { display: block; font-size: 14px; } .uc-auto-t small { display: block; font-size: 11.5px; color: var(--text-3); margin-top: 2px; }
    .uc-switch { width: 44px; height: 24px; border-radius: 99px; background: var(--track); position: relative; flex: 0 0 auto; transition: background .2s; }
    .uc-switch i { position: absolute; top: 3px; left: 3px; width: 18px; height: 18px; border-radius: 50%; background: #fff; transition: left .2s; box-shadow: 0 1px 4px rgba(0, 0, 0, .3); }
    .uc-auto[aria-pressed="true"] { border-color: var(--uc); background: var(--uc-soft); }
    .uc-auto[aria-pressed="true"] .uc-auto-ic { background: linear-gradient(135deg, var(--uc), var(--uc-2)); color: #fff; }
    .uc-auto[aria-pressed="true"] .uc-auto-t small { color: var(--uc); font-weight: 600; }
    .uc-auto[aria-pressed="true"] .uc-switch { background: var(--uc); } .uc-auto[aria-pressed="true"] .uc-switch i { left: 23px; }
    .uc-box { border: 1px solid var(--line); background: var(--panel-2); border-radius: 13px; padding: 14px; }
    .uc-count { display: flex; align-items: center; gap: 10px; } .uc-count .fc { font: 700 20px 'JetBrains Mono', monospace; padding: 10px 12px; } .uc-count span { color: var(--text-3); font-size: 12.5px; }
    .uc-quick { display: flex; flex-wrap: wrap; gap: 6px; margin: 10px 0; }
    .uc-quick button, .uc-tags button { border: 1px solid var(--line-2); background: var(--panel); color: var(--text); font: 700 12px 'Inter', sans-serif; padding: 6px 12px; border-radius: 99px; cursor: pointer; }
    .uc-quick button:hover, .uc-tags button:hover { border-color: var(--uc); color: var(--uc); }
    .uc-left { padding: 9px 12px; border-radius: 10px; border: 1px solid var(--line); background: var(--panel); font-size: 12.5px; color: var(--text-2); }
    .uc-left b { color: var(--uc); font-family: 'JetBrains Mono', monospace; }
    .uc-file-row { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; margin-top: 10px; }
    .uc-types { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 8px; margin-bottom: 12px; }
    .uc-types button { padding: 10px 6px; border-radius: 11px; border: 1.5px solid var(--line-2); background: var(--panel-2); color: var(--text); font: 700 12.5px 'Inter', sans-serif; cursor: pointer; }
    .uc-types button.on { border-color: var(--uc); background: var(--uc-soft); color: var(--uc); }
    .uc-media { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; margin-bottom: 12px; }
    .uc-msg-top { display: flex; justify-content: space-between; align-items: center; gap: 8px; margin-bottom: 6px; flex-wrap: wrap; }
    .uc-tags { display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 8px; }
    .uc-tags button { font-family: 'JetBrains Mono', monospace; color: var(--uc); background: var(--uc-soft); border-color: var(--uc-line); }
    #ucMsg { resize: vertical; line-height: 1.55; min-height: 150px; }
    .uc-gap { margin-top: 14px; padding-top: 14px; border-top: 1px solid var(--line); }
    .uc-gap-top { display: flex; justify-content: space-between; align-items: center; gap: 8px; font-size: 13px; }
    .uc-gap-tag { padding: 4px 11px; border-radius: 99px; font-size: 11.5px; font-weight: 800; color: #fff; background: var(--gapc, #f97316); white-space: nowrap; }
    .uc-gap-row { display: flex; align-items: center; gap: 12px; margin: 12px 0 6px; }
    .uc-gap-row input[type=range] { flex: 1; min-width: 0; accent-color: var(--gapc, #f97316); }
    .uc-gap-row .fc { width: 72px; text-align: center; font: 700 18px 'JetBrains Mono', monospace; padding: 7px; border: 2px solid var(--gapc, #f97316); color: var(--gapc, #f97316); }
    .uc-gap-scale { display: flex; justify-content: space-between; font-size: 11px; color: var(--text-3); margin-bottom: 6px; }
    .uc-gap-scale .r { color: #e11d48; font-weight: 700; } .uc-gap-scale .g { color: var(--green); font-weight: 700; }
    .uc-preview { min-height: 150px; border-radius: 13px; padding: 14px; border: 1px solid var(--line); background: linear-gradient(180deg, rgba(255, 59, 85, 0.06), transparent), var(--panel-2); }
    .uc-bubble { margin-left: auto; max-width: 92%; width: fit-content; padding: 10px 12px; border-radius: 12px 12px 3px 12px; background: #fde2e6; color: #2a0a10; font-size: 13px; line-height: 1.5; white-space: pre-wrap; overflow-wrap: anywhere; box-shadow: 0 2px 8px rgba(0, 0, 0, .12); }
    .uc-checks { list-style: none; margin: 12px 0; padding: 12px 14px; border-radius: 12px; border: 1px solid var(--line); background: var(--panel-2); display: grid; gap: 7px; font-size: 12.5px; }
    .uc-checks li { display: flex; gap: 8px; align-items: flex-start; } .uc-checks li::before { content: '•'; font-weight: 800; color: var(--amber); } .uc-checks li.ok::before { content: '✓'; color: var(--green); }
    .uc-checks li.bad { color: var(--amber); }
    .uc-start { width: 100%; padding: 14px; border: 0; border-radius: 13px; font: 800 15px 'Inter', sans-serif; color: #fff; cursor: pointer; background: linear-gradient(135deg, var(--uc), var(--uc-2)); box-shadow: 0 8px 24px rgba(255, 59, 85, 0.35); transition: transform .15s, opacity .15s; }
    .uc-start:hover { transform: translateY(-1px); } .uc-start:disabled { opacity: .45; cursor: not-allowed; transform: none; box-shadow: none; }
    .uc-run { margin-top: 12px; }
    .uc-prog-top { display: flex; justify-content: space-between; gap: 8px; font-size: 12px; color: var(--text-2); margin-bottom: 6px; } .uc-prog-top b { font: 700 13px 'JetBrains Mono', monospace; color: var(--text); }
    .uc-bar i { background: linear-gradient(90deg, var(--uc-2), var(--uc)); }
    .uc-run-btns { display: flex; gap: 8px; margin-top: 10px; flex-wrap: wrap; } .uc-run-btns .btn { flex: 1; }
    .uc-log-h { display: flex; justify-content: space-between; align-items: center; margin: 16px 0 6px; font-size: 13px; }
    .uc-log { height: 230px; overflow-y: auto; border-radius: 12px; padding: 10px 12px; background: #0b0204; border: 1px solid rgba(255, 59, 85, .25); font: 11.5px/1.6 'JetBrains Mono', monospace; color: #ffd6dc; }
    .uc-log-l { white-space: pre-wrap; overflow-wrap: anywhere; } .uc-log-l .t { color: #a06a73; } .uc-log-l.success { color: #8ff0b6; } .uc-log-l.warn { color: #ffcf7a; } .uc-log-l.error { color: #ff7d8f; } .uc-log-l.dim { color: #a06a73; }
    .uc-panel::before { border-color: var(--uc); }
    .uc-panel .panel-t::before { background: var(--uc); box-shadow: 0 0 8px var(--uc); }
    .uc-panel input[type=checkbox] { width: 17px; height: 17px; accent-color: var(--uc); cursor: pointer; }
    .uc-panel tr.off td { opacity: .55; }
    .uc-lane { font: 600 12px 'JetBrains Mono', monospace; white-space: nowrap; }
    @media (max-width: 1250px) { .uc-grid { grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); } .uc-grid > .uc-card:last-child { grid-column: 1 / -1; } }
    @media (max-width: 760px) { .uc-grid { grid-template-columns: minmax(0, 1fr); } .uc-hero h2 { font-size: 20px; } .uc-types { grid-template-columns: 1fr; } }
    .ad-form input.dirty { border-color: var(--amber); }
    .ad-msg { grid-column: 1 / -1; font-size: 11.5px; }
    .ad-msg:empty { display: none; }
    .ad-msg.ok { color: var(--green); } .ad-msg.err { color: var(--red); }

    /* Number intelligence */
    .nx-chips { display: flex; flex-wrap: wrap; gap: 7px; margin-bottom: 12px; }
    .nx-chip { display: inline-flex; align-items: center; gap: 7px; padding: 7px 12px; border-radius: 99px; border: 1px solid var(--line-2); background: var(--panel-2); color: var(--text-2); font-size: 12px; font-weight: 700; cursor: pointer; }
    .nx-chip:hover { border-color: var(--green); color: var(--text); }
    .nx-chip b { font: 700 11px 'JetBrains Mono', monospace; padding: 1px 7px; border-radius: 99px; background: rgba(127, 127, 127, 0.15); color: var(--text); }
    .nx-chip.active { background: rgba(31, 224, 138, 0.14); border-color: var(--green); color: var(--text); }
    .nx-chip.active b { background: var(--green); color: #02130b; }
    .nx-filters { display: grid; grid-template-columns: 2fr repeat(6, minmax(0, 1fr)); gap: 8px; }
    .nx-filters .fc { padding: 8px 10px; font-size: 12.5px; }
    .nx-bar { display: flex; align-items: center; justify-content: space-between; gap: 10px; flex-wrap: wrap; margin: 14px 0 0; padding: 10px 14px; border-radius: 10px; background: rgba(31, 224, 138, 0.06); border: 1px solid rgba(31, 224, 138, 0.2); }
    .nx-stats { display: flex; gap: 6px 18px; flex-wrap: wrap; font-size: 12.5px; color: var(--text-2); }
    .nx-stats strong { color: var(--text); font-family: 'JetBrains Mono', monospace; }

    /* Settlement explainer */
    .explain { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; font-size: 12px; line-height: 1.6; color: var(--text-2); background: var(--panel-2); border: 1px solid var(--line); border-radius: 10px; padding: 12px 14px; margin-top: 14px; }
    .explain strong { color: var(--text); }

    /* Emails */
    .em-grid { display: grid; grid-template-columns: minmax(0, 1.45fr) minmax(0, 1fr); gap: 16px; }
    .em-grid > * { min-width: 0; }
    textarea.fc { resize: vertical; line-height: 1.55; min-height: 120px; }
    .em-list { max-height: 330px; overflow-y: auto; border: 1px solid var(--line-2); border-radius: 10px; background: var(--panel-2); margin-top: 8px; }
    .em-row { display: flex; align-items: center; gap: 10px; padding: 9px 12px; border-bottom: 1px solid var(--line); cursor: pointer; font-size: 12.5px; }
    .em-row:last-child { border-bottom: 0; }
    .em-row:hover { background: rgba(127, 127, 127, 0.05); }
    .em-row input { width: 16px; height: 16px; accent-color: var(--green); flex: 0 0 auto; }
    .em-row-main { flex: 1; min-width: 0; }
    .em-row-main b { display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .em-row-main small { display: block; color: var(--text-3); font-size: 11.5px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .em-actions { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; margin-top: 4px; }
    .em-result { margin-top: 16px; display: grid; gap: 12px; }
    .em-result .banner { margin-bottom: 0; }
    .em-check { display: flex; align-items: center; gap: 8px; font-size: 12.5px; color: var(--text-2); cursor: pointer; }
    .em-check input { width: 16px; height: 16px; accent-color: var(--green); }
    .em-frame { width: 100%; height: 62vh; min-height: 380px; border: 1px solid var(--line-2); border-radius: 10px; background: #f1f5f4; display: block; }
    @media (max-width: 1100px) { .em-grid { grid-template-columns: minmax(0, 1fr); } }

    /* Modals */
    .modal { position: fixed; inset: 0; z-index: 200; display: none; align-items: center; justify-content: center; padding: 16px; background: rgba(0, 0, 0, 0.7); -webkit-backdrop-filter: blur(6px); backdrop-filter: blur(6px); }
    .modal.open { display: flex; }
    .modal-card { width: 100%; max-width: 600px; max-height: calc(100vh - 32px); overflow: auto; background: var(--panel); border: 1px solid var(--line-2); border-radius: 16px; box-shadow: 0 30px 80px rgba(0, 0, 0, 0.6); animation: fadeUp .18s ease-out; }
    .modal-card.wide { max-width: 760px; }
    .modal-h { display: flex; justify-content: space-between; align-items: flex-start; gap: 12px; padding: 16px 18px; border-bottom: 1px solid var(--line); }
    .modal-h h3 { font-size: 16px; font-weight: 800; overflow-wrap: anywhere; }
    .modal-h p { font-size: 12px; color: var(--text-2); margin-top: 3px; overflow-wrap: anywhere; }
    .modal-x { background: none; border: 0; color: var(--text-3); font-size: 24px; line-height: 1; cursor: pointer; padding: 0 4px; }
    .modal-b { padding: 18px; }
    .modal-f { display: flex; justify-content: flex-end; gap: 8px; padding: 14px 18px; border-top: 1px solid var(--line); flex-wrap: wrap; }
    .pitch { font-size: 13px; line-height: 1.6; background: var(--panel-2); padding: 10px 12px; border-radius: 8px; border: 1px solid var(--line); white-space: pre-wrap; overflow-wrap: anywhere; }
    .kv { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px; }
    .kv span { display: block; font-size: 10.5px; text-transform: uppercase; letter-spacing: .07em; color: var(--text-3); font-weight: 700; margin-bottom: 3px; }
    .kv b, .kv div { font-size: 13px; overflow-wrap: anywhere; }
    .logo-prev { width: 44px; height: 44px; border-radius: 10px; border: 1px dashed var(--line-2); display: flex; align-items: center; justify-content: center; overflow: hidden; background: var(--panel-2); color: var(--text-3); font-size: 11px; }
    .logo-prev img { width: 100%; height: 100%; object-fit: contain; }

    /* Toasts */
    .toasts { position: fixed; top: 16px; right: 16px; z-index: 300; display: flex; flex-direction: column; gap: 8px; width: min(380px, calc(100vw - 32px)); pointer-events: none; }
    .toast { pointer-events: auto; cursor: pointer; background: var(--panel-3); border: 1px solid var(--line-2); border-left: 3px solid var(--green); border-radius: 10px; padding: 11px 14px; font-size: 12.5px; box-shadow: 0 14px 40px rgba(0, 0, 0, 0.5); animation: fadeUp .2s ease-out; transition: opacity .35s, transform .35s; overflow-wrap: anywhere; }
    .toast b { display: block; margin-bottom: 2px; font-size: 13px; }
    .toast.err { border-left-color: var(--red); }
    .toast.info { border-left-color: var(--cyan); }
    .toast.out { opacity: 0; transform: translateX(20px); }

    /* Login gate */
    .gate { min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 24px 16px; }
    .gate-card { width: 100%; max-width: 430px; }
    .gate .term-screen { height: auto; min-height: 0; padding: 20px 20px 22px; font-size: 13px; overflow: visible; }
    .gate h2 { font: 700 15px 'JetBrains Mono', monospace; color: #39ff88; margin: 6px 0 2px; }
    .gate-sub { color: #4c7c63; font-size: 12px; margin-bottom: 18px; }
    .gate label { display: block; color: #4c7c63; font-size: 11.5px; margin-bottom: 5px; }
    .gate input { width: 100%; background: #06100b; border: 1px solid rgba(57, 255, 136, 0.3); color: #c3ffd9; font: 15px 'JetBrains Mono', monospace; padding: 10px 12px; border-radius: 8px; margin-bottom: 14px; }
    .gate input:focus { outline: none; border-color: #39ff88; box-shadow: 0 0 0 3px rgba(57, 255, 136, 0.15); }
    .gate .btn-gold { width: 100%; padding: 12px; font-size: 14px; }
    .gate-session { border: 1px solid rgba(255, 176, 0, 0.35); background: rgba(255, 176, 0, 0.06); border-radius: 10px; padding: 12px; margin-bottom: 18px; color: #ffe0a3; font-size: 12.5px; }
    .gate-session .row { display: flex; gap: 8px; margin-top: 10px; flex-wrap: wrap; }
    .gate-session .btn { flex: 1; }
    .gate-err { padding: 9px 11px; border-radius: 8px; background: rgba(255, 95, 109, 0.12); border: 1px solid rgba(255, 95, 109, 0.35); color: #ff8a95; font-size: 12.5px; margin-bottom: 14px; }
    .gate-back { display: block; text-align: center; margin-top: 16px; font-size: 12px; color: var(--text-2); text-decoration: none; }

    /* Responsive */
    @media (max-width: 1280px) {
      .g-kpi { grid-template-columns: repeat(2, minmax(0, 1fr)); }
      .nx-filters { grid-template-columns: repeat(3, minmax(0, 1fr)); }
      .nx-filters .fc:first-child { grid-column: 1 / -1; }
      .ad-row { grid-template-columns: repeat(2, minmax(0, 1fr)); }
      .ad-co, .ad-form { grid-column: 1 / -1; }
    }
    @media (max-width: 1100px) {
      .g-2, .g-2e, .g-3 { grid-template-columns: minmax(0, 1fr); }
      .mini-kpis { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    }
    @media (max-width: 900px) {
      .shell { display: block; }
      .side { position: sticky; top: 0; height: auto; border-right: 0; border-bottom: 1px solid var(--line-2); }
      .side-brand { padding: 10px 16px; border-bottom: 0; }
      .side-nav { display: flex; overflow-x: auto; overflow-y: hidden; gap: 6px; padding: 0 16px 10px; scrollbar-width: none; }
      .side-nav::-webkit-scrollbar { display: none; }
      .nav-g { display: none; }
      .nav-i { width: auto; flex: 0 0 auto; white-space: nowrap; padding: 7px 11px; font-size: 12.5px; margin: 0; border-color: var(--line); }
      .nav-i.active::before { display: none; }
      .nav-b, .nav-live { margin-left: 4px; }
      .side-foot { display: none; }
      .m-only { display: inline-flex !important; }
      .main { padding: 16px 16px 48px; }
      .topbar h1 { font-size: 19px; }
      .term-screen { height: 62vh; min-height: 300px; font-size: 11.5px; }
      .explain { grid-template-columns: 1fr; }
    }
    @media (max-width: 760px) {
      .rt thead { display: none; }
      .rt, .rt tbody, .rt tr, .rt td { display: block; width: 100%; }
      .rt tbody tr { border: 1px solid var(--line); border-radius: 12px; margin: 0 0 10px; padding: 4px 12px; background: var(--panel-2); }
      .rt tbody tr:hover td { background: none; }
      .rt td { display: flex; justify-content: space-between; align-items: flex-start; gap: 12px; padding: 8px 0; border-bottom: 1px dashed var(--line); }
      .rt td:last-child { border-bottom: 0; }
      .rt td::before { content: attr(data-l); color: var(--text-3); font-size: 10.5px; text-transform: uppercase; letter-spacing: .06em; font-weight: 700; flex: 0 0 auto; padding-top: 2px; }
      .rt td .cv { min-width: 0; text-align: right; display: flex; flex-direction: column; align-items: flex-end; overflow-wrap: anywhere; }
      .rt td.acts { flex-direction: column; align-items: stretch; }
      .rt td.acts .cv { justify-content: flex-start; flex-direction: row; text-align: left; }
      .rt td.empty { display: block; text-align: center; border: 0; }
      .rt td.empty::before { content: none; }
      .rt .clamp { max-width: none; text-align: right; }
      .rt .co { flex-direction: row-reverse; text-align: right; }
      .tbl-wrap { overflow: visible; padding: 10px; }
      .panel-b.flush .tbl-wrap { padding: 10px; }
    }
    @media (max-width: 560px) {
      .g-kpi { gap: 10px; }
      .kpi { padding: 12px 12px 8px; }
      .kpi-v { font-size: 19px; }
      .kpi-s { font-size: 11px; white-space: normal; }
      .frow, .kv { grid-template-columns: 1fr; }
      .nx-filters { grid-template-columns: 1fr 1fr; }
      .ad-row { grid-template-columns: 1fr; }
      .ad-form { grid-template-columns: 1fr 1fr; }
      .ad-form .btn { grid-column: 1 / -1; }
      .search { width: 100%; }
      .tools { width: 100%; }
      .tools > .fc, .tools > select { flex: 1 1 140px; }
      .fc, .ad-form input, .term-ctrl input, .term-ctrl select { font-size: 16px; }
      .mini-kpis b { font-size: 16px; }
      .live-pill time { display: none; }
      .donut { width: 116px; height: 116px; }
    }
  </style>
</head>
<body>

<?php if (!$isSuperAdmin): ?>
  <!-- SUPER ADMIN LOGIN GATE -->
  <div class="gate">
    <div class="gate-card">
      <div class="term">
        <div class="term-bar">
          <div class="dots"><i></i><i></i><i></i></div>
          <div class="term-title">chatly — /ss — secure login</div>
          <span class="term-state live">●</span>
        </div>
        <div class="term-screen">
          <div class="tl sys">[sys] Chatly super admin console</div>
          <div class="tl sys">[sys] authentication required</div>
          <h2>&gt; Super Admin Command</h2>
          <div class="gate-sub">Platform owner access only.</div>

          <?php if ($isLoggedInSuperAdmin): ?>
            <div class="gate-session">
              Active super admin session: <strong><?php echo htmlspecialchars($currentUser['name'] ?? $currentUser['username']); ?></strong>
              <div class="row">
                <a href="/ss?portal=1" class="btn btn-gold btn-sm">Enter command center &rarr;</a>
                <a href="/ss?action=logout" onclick="handleSadminLogout(event)" class="btn btn-danger btn-sm">Sign out</a>
              </div>
            </div>
          <?php endif; ?>

          <form id="sadminLoginForm" onsubmit="handleSadminLogin(event)" autocomplete="on">
            <label for="sUsername">username</label>
            <input type="text" id="sUsername" placeholder="username" autocomplete="username" required autofocus>
            <label for="sPassword">password</label>
            <input type="password" id="sPassword" placeholder="••••••••" autocomplete="current-password" required>
            <div id="loginError" class="gate-err" style="display:none;"></div>
            <button type="submit" id="sadminLoginBtn" class="btn btn-gold">Unlock command center</button>
          </form>
          <div class="t-prompt" style="margin-top:16px;"><span class="t-ps1">guest@chatly:~$</span> <span class="cursor"></span></div>
        </div>
      </div>
      <a href="/" class="gate-back">&larr; Back to Chatly homepage</a>
    </div>
  </div>

<script>
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
    if (data.success && data.user && data.user.role === 'superadmin') {
      window.location.href = '/ss?portal=1';
    } else {
      errBox.textContent = data.error || 'Access denied: super administrator privileges required.';
      errBox.style.display = 'block';
    }
  } catch (err) {
    errBox.textContent = 'Server communication error.';
    errBox.style.display = 'block';
  } finally {
    btn.disabled = false;
    btn.textContent = 'Unlock command center';
  }
}
</script>

<?php else: ?>
  <!-- SUPER ADMIN COMMAND CENTER -->
  <div class="shell">
    <aside class="side">
      <div class="side-brand">
        <a href="/" title="Chatly homepage"><img src="assets/images/chatly-logo.png" alt="Chatly" class="chatly-logo chatly-logo--dark"><img src="assets/images/chatly-logo-light.png" alt="Chatly" class="chatly-logo chatly-logo--light"></a>
        <span class="sa-chip">SUPER ADMIN</span>
        <a href="/ss?action=logout" onclick="handleSadminLogout(event)" class="btn btn-danger icon-btn m-only" style="margin-left:auto;" title="Log out" aria-label="Log out"><svg class="ic" viewBox="0 0 24 24"><path d="M15 4h3a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2h-3M10 16l-4-4 4-4M6 12h10"/></svg></a>
      </div>
      <nav class="side-nav" aria-label="Sections">
        <div class="nav-g">Monitor</div>
        <button class="nav-i" data-act="nav" data-sec="overview"><svg class="ic" viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></svg><span>Overview</span></button>
        <button class="nav-i" data-act="nav" data-sec="chats"><svg class="ic" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="16" rx="2"/><path d="M7 9l3 3-3 3M13 15h4"/></svg><span>Live chats</span><span class="nav-live" id="navChatsLive"></span></button>
        <div class="nav-g">Manage</div>
        <button class="nav-i nav-uc" data-act="nav" data-sec="ultimate"><svg class="ic" viewBox="0 0 24 24"><path d="M3 11l15-6v14L3 13z"/><path d="M7 13.5V18a2 2 0 0 0 4 0v-3"/><path d="M21 9v4"/></svg><span>Ultimate campaign</span><span class="nav-live red" id="navUcLive" hidden></span></button>
        <button class="nav-i" data-act="nav" data-sec="companies"><svg class="ic" viewBox="0 0 24 24"><path d="M4 21V5a1 1 0 0 1 1-1h9a1 1 0 0 1 1 1v16M15 9h4a1 1 0 0 1 1 1v11M3 21h18M8 8h3M8 12h3M8 16h3"/></svg><span>Companies</span></button>
        <button class="nav-i" data-act="nav" data-sec="autodata"><svg class="ic" viewBox="0 0 24 24"><ellipse cx="12" cy="5" rx="8" ry="3"/><path d="M4 5v6c0 1.7 3.6 3 8 3s8-1.3 8-3V5M4 11v6c0 1.7 3.6 3 8 3s8-1.3 8-3v-6"/></svg><span>Auto Data limits</span></button>
        <button class="nav-i" data-act="nav" data-sec="approvals"><svg class="ic" viewBox="0 0 24 24"><circle cx="9" cy="8" r="4"/><path d="M2 21c0-3.9 3.1-7 7-7s7 3.1 7 7M16 11l2 2 4-4"/></svg><span>Closer approvals</span><span class="nav-b" id="navPending" hidden>0</span></button>
        <button class="nav-i" data-act="nav" data-sec="settlement"><svg class="ic" viewBox="0 0 24 24"><rect x="3" y="6" width="18" height="14" rx="2"/><path d="M3 10h18M16 15h2M7 6V4h10v2"/></svg><span>Nunno settlement</span></button>
        <button class="nav-i" data-act="nav" data-sec="invoice"><svg class="ic" viewBox="0 0 24 24"><path d="M6 2h9l5 5v15H6z"/><path d="M14 2v6h6M9 13h6M9 17h6"/></svg><span>Nunno invoice</span></button>
        <button class="nav-i" data-act="nav" data-sec="emails"><svg class="ic" viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 7l9 6 9-6"/></svg><span>Emails</span></button>
        <div class="nav-g">Data</div>
        <button class="nav-i" data-act="nav" data-sec="numbers"><svg class="ic" viewBox="0 0 24 24"><circle cx="12" cy="12" r="8"/><circle cx="12" cy="12" r="3"/><path d="M12 2v3M12 19v3M2 12h3M19 12h3"/></svg><span>Number intelligence</span></button>
        <button class="nav-i" data-act="nav" data-sec="pool"><svg class="ic" viewBox="0 0 24 24"><path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2z"/></svg><span>Fallback numbers</span></button>
        <button class="nav-i" data-act="nav" data-sec="brand"><svg class="ic" viewBox="0 0 24 24"><path d="M3 11v2a1 1 0 0 0 1 1h3l6 4V6L7 10H4a1 1 0 0 0-1 1zM17 8a5 5 0 0 1 0 8"/></svg><span>Brand leads</span><span class="nav-b green" id="navBrand" hidden>0</span></button>
        <div class="nav-g">Security</div>
        <button class="nav-i" data-act="nav" data-sec="security"><svg class="ic" viewBox="0 0 24 24"><path d="M12 3l8 3v6c0 4.5-3.4 8.2-8 9-4.6-.8-8-4.5-8-9V6l8-3z"/><path d="M9 12l2 2 4-4"/></svg><span>Logins &amp; audit</span></button>
      </nav>
      <div class="side-foot">
        <div class="who"><b><?php echo htmlspecialchars($currentUser['name'] ?? ($currentUser['username'] ?? 'Super Admin')); ?></b><small>super admin</small></div>
        <a href="/ss" class="btn btn-ghost icon-btn" title="Switch account" aria-label="Switch account"><svg class="ic" viewBox="0 0 24 24"><path d="M7 7h11l-3-3M17 17H6l3 3"/></svg></a>
        <a href="/ss?action=logout" onclick="handleSadminLogout(event)" class="btn btn-danger icon-btn" title="Log out" aria-label="Log out"><svg class="ic" viewBox="0 0 24 24"><path d="M15 4h3a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2h-3M10 16l-4-4 4-4M6 12h10"/></svg></a>
      </div>
    </aside>

    <main class="main">
      <div id="sessionBanner" class="banner" hidden>Your super admin session has ended. <a href="/ss">Sign in again</a> to keep the dashboard live.</div>

      <header class="topbar">
        <div>
          <h1><span class="prompt">&gt;_</span><span id="secTitle">Overview</span></h1>
          <p id="secSub">Live platform pulse across every company</p>
        </div>
        <div class="top-actions">
          <span class="live-pill" id="livePill" title="Auto-refreshing"><span class="pulse"></span><span id="liveLabel">LIVE</span><time id="liveTime">--:--:--</time></span>
          <button class="btn icon-btn" data-act="nav" data-sec="brand" title="Brand leads" aria-label="Brand leads"><svg class="ic" viewBox="0 0 24 24"><path d="M6 9a6 6 0 1 1 12 0c0 6 2 7 2 7H4s2-1 2-7M10 20a2 2 0 0 0 4 0"/></svg><span class="dot-badge" id="bellBadge" hidden>0</span></button>
          <button class="btn icon-btn" data-act="refresh" title="Refresh everything now" aria-label="Refresh"><svg class="ic" viewBox="0 0 24 24"><path d="M20 11a8 8 0 1 0-2.3 5.7M20 4v7h-7"/></svg></button>
          <button class="btn icon-btn" onclick="toggleTheme()" title="Light / dark" aria-label="Toggle theme"><svg class="ic" viewBox="0 0 24 24"><circle cx="12" cy="12" r="8"/><path d="M12 4a8 8 0 0 1 0 16z" fill="currentColor"/></svg></button>
          <button class="btn btn-gold" data-act="provision"><svg class="ic" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg>New company</button>
        </div>
      </header>

      <!-- ============ OVERVIEW ============ -->
      <section class="sec" data-sec="overview">
        <div class="grid g-kpi">
          <div class="kpi" id="kCompanies" data-tone="cyan" data-act="nav" data-sec="companies"><div class="kpi-h"><span>Companies</span><svg class="ic" viewBox="0 0 24 24"><path d="M4 21V5a1 1 0 0 1 1-1h9a1 1 0 0 1 1 1v16M15 9h4a1 1 0 0 1 1 1v11M3 21h18"/></svg></div><div class="kpi-v">—</div><div class="kpi-s">loading…</div><div class="kpi-spark"></div></div>
          <div class="kpi" id="kSessions" data-tone="green"><div class="kpi-h"><span>Live WhatsApp sessions</span><svg class="ic" viewBox="0 0 24 24"><path d="M5 12.5l4 4L19 7"/></svg></div><div class="kpi-v">—</div><div class="kpi-s">active closers with a linked phone</div><div class="kpi-spark"></div></div>
          <div class="kpi" id="kMsgs" data-tone="cyan" data-act="nav" data-sec="chats"><div class="kpi-h"><span>Messages today</span><svg class="ic" viewBox="0 0 24 24"><path d="M4 5h16v11H8l-4 4z"/></svg></div><div class="kpi-v">—</div><div class="kpi-s">sent by all closers</div><div class="kpi-spark"></div></div>
          <div class="kpi" id="kPending" data-tone="amber" data-act="nav" data-sec="approvals"><div class="kpi-h"><span>Pending approvals</span><svg class="ic" viewBox="0 0 24 24"><circle cx="12" cy="12" r="8"/><path d="M12 8v4l3 2"/></svg></div><div class="kpi-v">—</div><div class="kpi-s">closer applicants</div><div class="kpi-spark"></div></div>
          <div class="kpi" id="kAuto" data-tone="gold" data-act="nav" data-sec="autodata"><div class="kpi-h"><span>Auto Data today</span><svg class="ic" viewBox="0 0 24 24"><ellipse cx="12" cy="6" rx="7" ry="3"/><path d="M5 6v12c0 1.7 3.1 3 7 3s7-1.3 7-3V6"/></svg></div><div class="kpi-v">—</div><div class="kpi-s">pool numbers used</div><div class="kpi-spark"></div></div>
          <div class="kpi" id="kRevenue" data-tone="green" data-act="nav" data-sec="settlement"><div class="kpi-h"><span>Nunno sales revenue</span><svg class="ic" viewBox="0 0 24 24"><path d="M4 17l5-5 4 4 7-8M14 8h6v6"/></svg></div><div class="kpi-v">—</div><div class="kpi-s">verified sales</div><div class="kpi-spark"></div></div>
          <div class="kpi" id="kEarned" data-tone="violet" data-act="nav" data-sec="settlement"><div class="kpi-h"><span>Earned by closers</span><svg class="ic" viewBox="0 0 24 24"><rect x="3" y="6" width="18" height="14" rx="2"/><path d="M3 10h18"/></svg></div><div class="kpi-v">—</div><div class="kpi-s">Nunno pays</div><div class="kpi-spark"></div></div>
          <div class="kpi" id="kBrand" data-tone="violet" data-act="nav" data-sec="brand"><div class="kpi-h"><span>Brand leads</span><svg class="ic" viewBox="0 0 24 24"><path d="M3 11v2a1 1 0 0 0 1 1h3l6 4V6L7 10H4a1 1 0 0 0-1 1z"/></svg></div><div class="kpi-v">—</div><div class="kpi-s">promotion inquiries</div><div class="kpi-spark"></div></div>
        </div>

        <div class="grid g-2">
          <div class="term" id="ovTerm">
            <div class="term-bar">
              <div class="dots"><i></i><i></i><i></i></div>
              <div class="term-title">root@chatly:~/live$ tail -f chats.log</div>
              <button class="t-btn" data-act="nav" data-sec="chats">open ›</button>
            </div>
            <div class="term-screen mini" id="ovTermScreen"><div id="ovTermOut"></div><div class="t-prompt"><span class="t-ps1">root@chatly:~/live$</span> <span class="cursor"></span></div></div>
          </div>
          <div class="panel">
            <div class="panel-h"><div><div class="panel-t">Messages today by company</div><div class="panel-d">Closer sends since midnight</div></div></div>
            <div class="panel-b" id="ovMsgBars"><div class="empty-note">Loading…</div></div>
          </div>
        </div>

        <div class="grid g-3">
          <div class="panel">
            <div class="panel-h"><div class="panel-t">Workforce &amp; platform</div></div>
            <div class="panel-b" id="ovPlatform"><div class="empty-note">Loading…</div></div>
          </div>
          <div class="panel">
            <div class="panel-h"><div class="panel-t">Nunno revenue split</div></div>
            <div class="panel-b" id="ovSettle"><div class="empty-note">Loading…</div></div>
          </div>
          <div class="panel">
            <div class="panel-h"><div><div class="panel-t">Auto Data today</div><div class="panel-d">Pool numbers used per company</div></div><button class="btn btn-ghost btn-xs" data-act="nav" data-sec="autodata">Limits ›</button></div>
            <div class="panel-b" id="ovAuto"><div class="empty-note">Loading…</div></div>
          </div>
        </div>

        <div class="grid g-2e">
          <div class="panel">
            <div class="panel-h"><div class="panel-t">Closers waiting for approval</div><button class="btn btn-ghost btn-xs" data-act="nav" data-sec="approvals">All ›</button></div>
            <div class="panel-b" id="ovPending"><div class="empty-note">Loading…</div></div>
          </div>
          <div class="panel">
            <div class="panel-h"><div class="panel-t">Recent admin activity</div><button class="btn btn-ghost btn-xs" data-act="nav" data-sec="security">Audit ›</button></div>
            <div class="panel-b" id="ovAudit"><div class="empty-note">Loading…</div></div>
          </div>
        </div>
      </section>

      <!-- ============ LIVE CHATS (TERMINAL) ============ -->
      <section class="sec" data-sec="chats" hidden>
        <div class="term" id="term">
          <div class="term-bar">
            <div class="dots"><i></i><i></i><i></i></div>
            <div class="term-title">root@chatly: ~/live — tail -f chats.log (all companies)</div>
            <span class="term-state live" id="tState">● LIVE</span>
          </div>
          <div class="term-ctrl">
            <span class="lbl">company</span>
            <select id="termCo" aria-label="Filter by company"><option value="">all companies</option></select>
            <input id="termGrep" type="search" placeholder="grep: name, number, text…" aria-label="Search lines">
            <button class="t-btn" id="tPauseBtn" data-act="t-pause">❚❚ pause</button>
            <button class="t-btn" data-act="t-clear">clear</button>
          </div>
          <div class="term-screen" id="termScreen" tabindex="0" aria-live="off">
            <div id="termOut"></div>
            <div class="t-prompt"><span class="t-ps1">root@chatly:~/live$</span> <span id="tPromptCmd">tail -f chats.log</span> <span class="cursor"></span></div>
          </div>
          <button class="t-jump" id="termJump" data-act="t-jump" hidden>↓ live</button>
          <div class="term-foot">
            <span>buffer <b id="tCount">0</b> lines</span>
            <span>companies <b id="tCos">0</b></span>
            <span>poll <b>5s</b></span>
            <span>last poll <b id="tLast">—</b></span>
            <span><span style="color:#39ff88;">■</span> closer &nbsp;<span style="color:#ffb000;">■</span> lead</span>
          </div>
        </div>
      </section>

      <!-- ============ COMPANIES ============ -->
      <section class="sec" data-sec="companies" hidden>
        <div class="panel">
          <div class="panel-h">
            <div><div class="panel-t">All companies</div><div class="panel-d" id="compCount">Open any company admin in one click.</div></div>
            <div class="tools">
              <input type="search" id="compSearch" class="fc fc-sm search" placeholder="Search name, slug, owner…">
              <select id="compStatus" class="fc fc-sm"><option value="">All statuses</option><option value="active">Active</option><option value="suspended">Suspended</option></select>
              <button class="btn btn-gold btn-sm" data-act="provision">+ Add company</button>
            </div>
          </div>
          <div class="panel-b flush">
            <div class="tbl-wrap">
              <table class="rt">
                <thead><tr><th>Company</th><th>Plan</th><th>Workforce</th><th>Msgs today</th><th>Auto Data</th><th>Fallback phone</th><th>Status</th><th>Actions</th></tr></thead>
                <tbody id="companyTableBody"><tr><td class="empty" colspan="8">Loading companies…</td></tr></tbody>
              </table>
            </div>
          </div>
        </div>
      </section>

      <!-- ============ ULTIMATE CAMPAIGN ============ -->
      <section class="sec uc" data-sec="ultimate" hidden>
        <div class="uc-hero">
          <div>
            <div class="uc-kicker">Super admin · every WhatsApp on Chatly</div>
            <h2>Ultimate campaign</h2>
            <p>One campaign sent from every connected closer WhatsApp in every company. Replies land in the sending closer's own inbox. WhatsApps already sending their company's campaign are skipped.</p>
          </div>
          <div class="uc-state" id="ucState"><i></i><span>Idle</span></div>
        </div>
        <div class="mini-kpis uc-kpis">
          <div class="hl"><span>Connected WhatsApps</span><b id="ucKConn">—</b><small id="ucKConnSub">across all companies</small></div>
          <div><span>Selected to send</span><b id="ucKSel">—</b><small>ticked below</small></div>
          <div><span>Companies</span><b id="ucKCo">—</b><small>with a connected WhatsApp</small></div>
          <div><span>Auto Data left</span><b id="ucKPool">—</b><small>fresh numbers for Ultimate</small></div>
        </div>

        <div class="uc-grid">
          <!-- 1. Numbers -->
          <div class="uc-card">
            <div class="uc-card-h"><span class="uc-step">1</span><b>Numbers</b><span class="uc-chip" id="ucNumChip">0</span></div>
            <label class="fl" for="ucName">Campaign name</label>
            <input type="text" id="ucName" class="fc" maxlength="120" placeholder="Ultimate campaign">
            <button type="button" class="uc-auto" id="ucAutoBtn" aria-pressed="true">
              <span class="uc-auto-ic"><svg class="ic" viewBox="0 0 24 24"><ellipse cx="12" cy="5" rx="8" ry="3"/><path d="M4 5v6c0 1.7 3.6 3 8 3s8-1.3 8-3V5M4 11v6c0 1.7 3.6 3 8 3s8-1.3 8-3v-6"/></svg></span>
              <span class="uc-auto-t"><b>Auto Data</b><small id="ucAutoSub">On: numbers come from the Chatly number pool</small></span>
              <span class="uc-switch"><i></i></span>
            </button>
            <div id="ucAutoBox" class="uc-box">
              <label class="fl" for="ucCount">How many messages to send?</label>
              <div class="uc-count"><input type="number" id="ucCount" class="fc" min="1" max="50000" step="1" value="100" inputmode="numeric"><span>messages</span></div>
              <div class="uc-quick">
                <button type="button" data-uc-count="100">100</button><button type="button" data-uc-count="500">500</button><button type="button" data-uc-count="1000">1,000</button><button type="button" data-uc-count="5000">5,000</button><button type="button" data-uc-count="10000">10,000</button>
              </div>
              <div class="uc-left" id="ucPoolLeft">Loading the pool…</div>
              <p class="hint">Fresh numbers no company has a chat with yet, split equally between the ticked WhatsApps. Numbers not on WhatsApp are skipped.</p>
            </div>
            <div id="ucManualBox" class="uc-box" hidden>
              <label class="fl" for="ucNumbers">Paste numbers (one per line, or separated by commas)</label>
              <textarea id="ucNumbers" class="fc" rows="7" placeholder="03001234567&#10;923211234567"></textarea>
              <div class="uc-file-row">
                <label class="btn btn-sm" for="ucNumFile">Upload .txt / .csv</label>
                <input type="file" id="ucNumFile" accept=".txt,.csv,text/plain,text/csv" hidden>
                <span class="hint" id="ucNumCount" style="margin:0">0 valid numbers</span>
              </div>
            </div>
          </div>

          <!-- 2. Message -->
          <div class="uc-card">
            <div class="uc-card-h"><span class="uc-step">2</span><b>Message</b><span class="uc-chip ghost">Personalized per contact</span></div>
            <label class="fl">Message type</label>
            <div class="uc-types" id="ucTypes">
              <button type="button" class="on" data-uc-type="text">Text only</button>
              <button type="button" data-uc-type="text_image">Text + image</button>
              <button type="button" data-uc-type="audio">Voice note</button>
            </div>
            <div class="uc-media" id="ucMediaBox" hidden>
              <label class="btn btn-sm" for="ucMediaFile" id="ucMediaLbl">Choose image</label>
              <input type="file" id="ucMediaFile" hidden>
              <span class="hint" id="ucMediaName" style="margin:0">No file chosen</span>
            </div>
            <div class="uc-msg-top"><label class="fl" for="ucMsg" style="margin:0">Your message</label><span class="hint" style="margin:0">Click a tag to insert it</span></div>
            <div class="uc-tags"><button type="button" data-uc-tag="{name}">{name}</button><button type="button" data-uc-tag="{phone}">{phone}</button><button type="button" data-uc-tag="{city}">{city}</button><button type="button" data-uc-tag="{link}">{link}</button></div>
            <textarea id="ucMsg" class="fc" rows="8" maxlength="4000" placeholder="Write your message...&#10;&#10;Tip: {name} becomes each person's name, and {Salam|AoA|Hello} picks one greeting at random so every message is a little different."></textarea>
            <div class="uc-gap">
              <div class="uc-gap-top"><b>Gap between messages</b><span class="uc-gap-tag" id="ucGapTag">Moderate</span></div>
              <div class="uc-gap-row">
                <input type="range" id="ucGapRange" min="1" max="60" step="1" value="10" aria-label="Gap between messages in seconds">
                <input type="number" id="ucGap" class="fc" min="1" max="60" step="1" value="10" inputmode="numeric" aria-label="Gap in seconds"><span class="hint" style="margin:0">sec</span>
              </div>
              <div class="uc-gap-scale"><span class="r">1s risky</span><span>10s</span><span class="g">30s+ safe</span><span>60s</span></div>
              <div class="hint" id="ucGapText">Each WhatsApp sends one message every 10 seconds.</div>
            </div>
          </div>

          <!-- 3. Check & start -->
          <div class="uc-card">
            <div class="uc-card-h"><span class="uc-step">3</span><b>Check &amp; start</b><span class="uc-chip ghost">Preview</span></div>
            <div class="uc-preview"><div class="uc-bubble" id="ucPreview">Type a message to preview it here.</div></div>
            <ul class="uc-checks" id="ucChecks"></ul>
            <button type="button" class="uc-start" id="ucStartBtn" data-act="uc-start">▶ Start ultimate campaign</button>
            <div class="uc-run" id="ucRunBox" hidden>
              <div class="uc-prog"><div class="uc-prog-top"><b id="ucProgText">0 / 0</b><span id="ucProgSub">sent · failed</span></div><div class="bar uc-bar"><i id="ucProgBar"></i></div></div>
              <div class="uc-run-btns">
                <button type="button" class="btn btn-sm" id="ucPauseBtn" data-act="uc-pause">Pause</button>
                <button type="button" class="btn btn-danger btn-sm" id="ucStopBtn" data-act="uc-stop">■ Stop now</button>
                <button type="button" class="btn btn-ghost btn-sm" id="ucClearBtn" data-act="uc-clear" hidden>Clear</button>
              </div>
            </div>
            <div class="uc-log-h"><b>Activity log</b><span class="hint" style="margin:0" id="ucLogCount">0 events</span></div>
            <div class="uc-log" id="ucLog"><div class="uc-log-l dim">Ready. Pick the WhatsApps below, write the message and press Start.</div></div>
          </div>
        </div>

        <div class="panel uc-panel">
          <div class="panel-h">
            <div><div class="panel-t">Every WhatsApp on Chatly</div><div class="panel-d" id="ucSendCount">Loading…</div></div>
            <div class="tools">
              <input type="search" id="ucSearch" class="fc fc-sm search" placeholder="Search closer, company, number…">
              <select id="ucCoFilter" class="fc fc-sm"><option value="">All companies</option></select>
              <button class="btn btn-sm" data-act="uc-all">Tick all ready</button>
              <button class="btn btn-sm" data-act="uc-none">Untick all</button>
              <button class="btn btn-sm" data-act="uc-reload">Refresh</button>
            </div>
          </div>
          <div class="panel-b flush">
            <div class="tbl-wrap">
              <table class="rt">
                <thead><tr><th style="width:40px"></th><th>Closer</th><th>Company</th><th>WhatsApp</th><th>Status</th><th>Today</th><th>In this campaign</th></tr></thead>
                <tbody id="ucSendBody"><tr><td class="empty" colspan="7">Loading…</td></tr></tbody>
              </table>
            </div>
          </div>
        </div>
      </section>

      <!-- ============ AUTO DATA LIMITS ============ -->
      <section class="sec" data-sec="autodata" hidden>
        <div class="panel">
          <div class="panel-h">
            <div><div class="panel-t">Auto Data pool</div><div class="panel-d">Auto Data campaigns pull numbers from the central pool. Limit each company to what it pays for. 0 or empty means unlimited.</div></div>
            <button class="btn btn-sm" data-act="ad-refresh">Refresh usage</button>
          </div>
          <div class="panel-b">
            <div id="adBanner" class="banner warn" hidden></div>
            <div class="mini-kpis">
              <div class="hl"><span>Numbers in pool</span><b id="adPool">—</b><small>central pool size</small></div>
              <div><span>Used today</span><b id="adToday">—</b><small>all companies</small></div>
              <div><span>Used all-time</span><b id="adTotal">—</b><small>all companies</small></div>
              <div><span>Companies limited</span><b id="adLimited">—</b><small>have a daily or total cap</small></div>
            </div>
          </div>
        </div>
        <div class="panel">
          <div class="panel-h"><div><div class="panel-t">Limits per company</div><div class="panel-d">Today resets at 12 AM PKT. Bars turn amber at 80% and red at the limit.</div></div></div>
          <div class="panel-b flush" id="adList"><div class="empty-note">Loading usage…</div></div>
        </div>
      </section>

      <!-- ============ CLOSER APPROVALS ============ -->
      <section class="sec" data-sec="approvals" hidden>
        <div class="panel">
          <div class="panel-h">
            <div><div class="panel-t">Pending closer approvals</div><div class="panel-d">Every closer applicant on the platform. As super admin you can approve or decline for any company.</div></div>
            <button class="btn btn-sm" data-act="reload-pending">Refresh</button>
          </div>
          <div class="panel-b flush">
            <div class="tbl-wrap">
              <table class="rt">
                <thead><tr><th>Applicant</th><th>Company</th><th>Username &amp; email</th><th>WhatsApp phone</th><th>City &amp; gender</th><th>Applied</th><th>Actions</th></tr></thead>
                <tbody id="masterPendingTableBody"><tr><td class="empty" colspan="7">Loading applicants…</td></tr></tbody>
              </table>
            </div>
          </div>
        </div>
      </section>

      <!-- ============ NUNNO SETTLEMENT ============ -->
      <section class="sec" data-sec="settlement" hidden>
        <div class="panel">
          <div class="panel-h">
            <div><div class="panel-t">Nunno commission settlement</div><div class="panel-d">Confirmed paid sales, what Nunno owes its closers, and what Nunno keeps.</div></div>
            <div class="tools">
              <button class="btn btn-sm" data-act="reload-settlement">Refresh</button>
              <button class="btn btn-gold btn-sm" data-act="open-sale">+ Record confirmed sale</button>
            </div>
          </div>
          <div class="panel-b">
            <div class="mini-kpis">
              <div><span>Confirmed sales revenue</span><b class="c-green" id="nunnoConfirmedRev">Rs. 0</b><small id="nunnoConfirmedRevSub">0 verified sales</small></div>
              <div class="hl"><span>Earned by closers (Nunno pays)</span><b class="c-green" id="nunnoPayableToChatly">Rs. 0</b><small>First Rs. 10,000 per closer at 100% + plan commission</small></div>
              <div><span>Nunno keeps</span><b class="c-gold" id="nunnoRetainedProfit">Rs. 0</b><small>Sales minus what closers earned</small></div>
              <div><span>Nunno closers</span><b class="c-cyan" id="nunnoActiveTMs">0</b><small id="nunnoPayoutsSub">Payouts: Rs. 0 paid · Rs. 0 pending</small></div>
            </div>
            <div class="explain">
              <div><strong>1. First Rs. 10,000 of sales = 100% to the closer.</strong><br>A closer who sells Rs. 10,000 earns Rs. 10,000. The sale that crosses the line pays only up to Rs. 10,000 (6 × 1,700 = 10,200 → 10,000; one Rs. 15,000 plan → 10,000). Team Leaders earn Rs. 500 per recruited TM whose Nunno x Chatly plan is verified.</div>
              <div><strong>2. After that, commission per plan.</strong><br>Rs. 1,700 plan &rarr; <strong>Rs. 700</strong> · Rs. 5,000 plan &rarr; <strong>Rs. 2,000</strong> · Rs. 15,000 plan &rarr; <strong>Rs. 10,000</strong>. Only admin-verified sales count. The same numbers drive each closer's wallet and withdrawals.</div>
            </div>
          </div>
        </div>
        <div class="panel">
          <div class="panel-h"><div class="panel-t">Closers: performance &amp; payable</div></div>
          <div class="panel-b flush">
            <div class="tbl-wrap">
              <table class="rt">
                <thead><tr><th>Closer</th><th>Verified sales</th><th>Sales revenue</th><th>First Rs. 10,000 (100%)</th><th>Earned by closer</th><th>Nunno keeps</th><th>Payouts</th><th>Actions</th></tr></thead>
                <tbody id="tableNunnoSettlementBody"><tr><td class="empty" colspan="8">Loading settlement…</td></tr></tbody>
              </table>
            </div>
          </div>
        </div>
      </section>

      <!-- ============ NUNNO INVOICE ============ -->
      <section class="sec" data-sec="invoice" hidden>
        <div class="panel">
          <div class="panel-h">
            <div><div class="panel-t">Nunno invoice</div><div class="panel-d">All sales by Chatly's TMs and TLs are paid on Nunno. Nunno owes Chatly the closers' payouts: TMs' first Rs. 10,000 then plan commission, and Rs. 500 per TM a TL activated.</div></div>
            <div class="tools">
              <button class="btn btn-sm" data-act="reload-invoice">Refresh</button>
              <button class="btn btn-gold btn-sm" data-act="print-invoice">Print / PDF</button>
            </div>
          </div>
          <div class="panel-b">
            <div class="mini-kpis">
              <div><span>Total revenue collected</span><b class="c-cyan" id="invRevenue">Rs. 0</b><small id="invRevenueSub">verified sales</small></div>
              <div class="hl"><span>Payable by Nunno to Chatly</span><b class="c-green" id="invPayable">Rs. 0</b><small id="invPayableSub">TM payouts + TL commissions</small></div>
            </div>
          </div>
        </div>
        <div class="panel">
          <div class="panel-h"><div class="panel-t">Lines</div><div class="panel-d" id="invGenerated"></div></div>
          <div class="panel-b flush">
            <div class="tbl-wrap">
              <table class="rt">
                <thead><tr><th>Closer</th><th>Role</th><th>Verified sales</th><th>Revenue collected</th><th>Payable</th></tr></thead>
                <tbody id="invLines"><tr><td class="empty" colspan="5">Loading invoice…</td></tr></tbody>
              </table>
            </div>
          </div>
        </div>
      </section>

      <!-- ============ EMAILS ============ -->
      <section class="sec" data-sec="emails" hidden>
        <div class="panel">
          <div class="panel-h">
            <div><div class="panel-t">Email companies</div><div class="panel-d">Goes from the Chatly Gmail sender to each company's owner email and its admin accounts. Write plain text; links become clickable. Personalise with {first_name}, {name} and {company}.</div></div>
            <div class="tools">
              <select id="emPreset" class="fc fc-sm" aria-label="Template">
                <option value="custom">Template: blank</option>
                <option value="welcome">Welcome to Chatly</option>
                <option value="billing">Plan / billing reminder</option>
                <option value="feature">New feature announcement</option>
                <option value="maintenance">Maintenance notice</option>
              </select>
            </div>
          </div>
          <div class="panel-b">
            <div class="em-grid">
              <div>
                <div class="fg"><label class="fl" for="emSubject">Subject</label><input id="emSubject" class="fc" maxlength="200" placeholder="e.g. New on Chatly: faster campaigns"></div>
                <div class="fg"><label class="fl" for="emMessage">Message</label><textarea id="emMessage" class="fc" rows="13" maxlength="20000" placeholder="Hello {first_name},&#10;&#10;Write your message here. Lines starting with - become a list."></textarea><div class="hint">Plain text only (no HTML). A blank line starts a new paragraph. The email gets the Chatly header, an "Open your admin portal" button and a footer.</div></div>
                <div class="em-actions">
                  <button class="btn btn-sm" data-act="em-preview">Preview</button>
                  <button class="btn btn-gold btn-sm" id="emSendBtn" data-act="em-send">Send email</button>
                  <span class="hint" id="emSendHint"></span>
                </div>
              </div>
              <div>
                <label class="fl">Recipients</label>
                <div class="seg" role="tablist">
                  <button class="on" data-act="em-aud" data-aud="all" role="tab">All companies</button>
                  <button data-act="em-aud" data-aud="pick" role="tab">Selected companies</button>
                </div>
                <div id="emPickTools" hidden>
                  <div class="em-actions" style="margin-top:10px;">
                    <input type="search" id="emCoSearch" class="fc fc-sm search" placeholder="Search name, owner, email…">
                    <button class="btn btn-ghost btn-xs" data-act="em-all">Select shown</button>
                    <button class="btn btn-ghost btn-xs" data-act="em-none">Clear</button>
                  </div>
                  <div class="em-list" id="emCoList"></div>
                </div>
                <div class="hint" id="emCoCount" style="margin-top:8px;"></div>
              </div>
            </div>
            <div class="em-result" id="emResult" hidden></div>
          </div>
        </div>
        <div class="grid g-2">
          <div class="panel">
            <div class="panel-h">
              <div><div class="panel-t">Send log</div><div class="panel-d">Everything sent from the Chatly sender: composer emails, test emails and automatic emails.</div></div>
              <button class="btn btn-sm" data-act="em-logs">Refresh</button>
            </div>
            <div class="panel-b flush">
              <div class="tbl-wrap">
                <table class="rt">
                  <thead><tr><th>Time</th><th>To</th><th>Subject</th><th>Type</th><th>Status</th></tr></thead>
                  <tbody id="emLogBody"><tr><td class="empty" colspan="5">Open this section to load the log…</td></tr></tbody>
                </table>
              </div>
            </div>
          </div>
          <div class="panel">
            <div class="panel-h"><div><div class="panel-t">Sender settings</div><div class="panel-d">Gmail account that sends Chatly emails.</div></div></div>
            <div class="panel-b">
              <div class="mini-kpis" style="grid-template-columns: repeat(2, minmax(0, 1fr)); margin-bottom: 14px;">
                <div><span>Sender</span><b id="emSetSender" style="font-size:13px;">—</b><small id="emSetTransport">loading…</small></div>
                <div><span>Sent in 24 h</span><b id="emSetUsage">—</b><small>of the daily limit</small></div>
              </div>
              <div class="fg"><label class="fl" for="emSetName">Sender name</label><input id="emSetName" class="fc" maxlength="60" placeholder="Chatly"></div>
              <div class="fg">
                <label class="fl" for="emSetPass">Gmail App Password</label>
                <input id="emSetPass" class="fc" type="password" autocomplete="new-password" spellcheck="false" placeholder="xxxx xxxx xxxx xxxx">
                <div class="hint" id="emSetPassState">—</div>
                <div class="hint">Sign in to Google as the sender account &gt; Google Account &gt; Security &gt; 2-Step Verification (turn on) &gt; App passwords &gt; create one named "Chatly" and paste the 16 letters here. It is stored on the server only and never shown again.</div>
              </div>
              <div class="frow">
                <div class="fg"><label class="fl" for="emSetCap">Daily limit</label><input id="emSetCap" class="fc" type="number" min="1" max="2000" step="10"><div class="hint">Gmail allows about 500 a day.</div></div>
                <div class="fg"><label class="fl">&nbsp;</label><label class="em-check"><input type="checkbox" id="emSetEnabled"> Sending on</label></div>
              </div>
              <div class="em-actions">
                <button class="btn btn-green btn-sm" data-act="em-save">Save settings</button>
                <button class="btn btn-danger btn-xs" data-act="em-clear-pass">Remove password</button>
              </div>
              <div class="fg" style="margin-top:14px;">
                <label class="fl" for="emTestTo">Send a test email</label>
                <div class="em-actions" style="margin-top:0;">
                  <input id="emTestTo" class="fc fc-sm" type="email" placeholder="your email" style="flex:1 1 180px;">
                  <button class="btn btn-sm" id="emTestBtn" data-act="em-test">Send test email to me</button>
                </div>
                <div class="hint">Leave empty to use the email on your super admin account.</div>
              </div>
            </div>
          </div>
        </div>
      </section>

      <!-- ============ NUMBER INTELLIGENCE ============ -->
      <section class="sec" data-sec="numbers" hidden>
        <div class="panel">
          <div class="panel-h">
            <div><div class="panel-t">Every number, every company</div><div class="panel-d">Slice by outcome, replies, delivery, closer, company and recency. Export exactly the list you need straight into a new campaign.</div></div>
            <button class="btn btn-sm" data-act="nx-refresh">Refresh</button>
          </div>
          <div class="panel-b">
            <div class="nx-chips" id="nxChips"></div>
            <div class="nx-filters">
              <input id="nxSearch" class="fc" type="search" placeholder="Search number, name, city, plan…">
              <select id="nxCompany" class="fc"><option value="">All companies</option></select>
              <select id="nxOwner" class="fc"><option value="">All closers</option></select>
              <select id="nxReplied" class="fc">
                <option value="">Replies: any</option>
                <option value="yes">Replied at least once</option>
                <option value="no">Never replied</option>
                <option value="3">Replied 3+ times</option>
                <option value="10">Replied 10+ times</option>
              </select>
              <select id="nxActive" class="fc">
                <option value="">Last activity: any</option>
                <option value="1">Last 24 hours</option>
                <option value="7">Last 7 days</option>
                <option value="30">Last 30 days</option>
                <option value="-30">Quiet for 30+ days</option>
                <option value="-90">Quiet for 90+ days</option>
              </select>
              <select id="nxDelivery" class="fc">
                <option value="">Delivery: any</option>
                <option value="delivered">Delivered / read</option>
                <option value="failed">Had a failed message</option>
                <option value="unsent">Never messaged</option>
              </select>
              <select id="nxSort" class="fc">
                <option value="la">Sort: latest activity</option>
                <option value="r">Sort: most replies</option>
                <option value="amt">Sort: highest sale</option>
                <option value="lr">Sort: latest reply</option>
                <option value="old">Sort: oldest activity</option>
              </select>
            </div>
            <div class="nx-bar">
              <div class="nx-stats" id="nxStats">Open this section to load numbers…</div>
              <div class="tools">
                <button class="btn btn-sm" data-act="nx-copy">Copy numbers</button>
                <button class="btn btn-sm" data-act="nx-txt">Numbers .txt</button>
                <button class="btn btn-green btn-sm" data-act="nx-csv">Full .csv</button>
                <button class="btn btn-ghost btn-sm" data-act="nx-reset">Reset</button>
              </div>
            </div>
          </div>
          <div class="panel-b flush">
            <div class="tbl-wrap">
              <table class="rt" id="nxTable">
                <thead><tr><th>Number</th><th>Name</th><th>Company</th><th>Status</th><th>Replies / sent</th><th>Delivery</th><th>Closer</th><th>Last activity</th><th>Sale</th></tr></thead>
                <tbody id="nxBody"><tr><td class="empty" colspan="9">Open this section to load numbers…</td></tr></tbody>
              </table>
            </div>
            <div class="more"><button class="btn btn-sm" id="nxMore" data-act="nx-more" hidden>Show more</button></div>
          </div>
        </div>
      </section>

      <!-- ============ FALLBACK NUMBERS ============ -->
      <section class="sec" data-sec="pool" hidden>
        <div class="panel">
          <div class="panel-h">
            <div><div class="panel-t">Company fallback numbers</div><div class="panel-d">If a closer cannot arrange their own phone, they use one of these company numbers until their device is ready.</div></div>
            <div class="tools">
              <button class="btn btn-sm" data-act="reload-pool">Refresh</button>
              <button class="btn btn-gold btn-sm" data-act="open-number">+ Add fallback number</button>
            </div>
          </div>
          <div class="panel-b flush">
            <div class="tbl-wrap">
              <table class="rt">
                <thead><tr><th>Profile</th><th>Phone</th><th>Label</th><th>Daily capacity</th><th>Assigned to</th><th>Status</th><th>Actions</th></tr></thead>
                <tbody id="numberPoolTableBody"><tr><td class="empty" colspan="7">Loading number profiles…</td></tr></tbody>
              </table>
            </div>
          </div>
        </div>
      </section>

      <!-- ============ BRAND LEADS ============ -->
      <section class="sec" data-sec="brand" hidden>
        <div class="panel">
          <div class="panel-h">
            <div><div class="panel-t">Brand promotion inquiries</div><div class="panel-d">Brands that submitted "Promote my product" on the homepage. Chat on WhatsApp and convert them to a company in one click.</div></div>
            <div class="tools">
              <button class="btn btn-sm" data-act="reload-brand">Refresh</button>
              <button class="btn btn-sm" data-act="brand-read">Mark all read</button>
            </div>
          </div>
          <div class="panel-b">
            <div class="mini-kpis">
              <div><span>Total</span><b id="inqMetricTotal">—</b><small>lifetime inquiries</small></div>
              <div class="hl"><span>New</span><b class="c-green" id="inqMetricNew">—</b><small>awaiting first outreach</small></div>
              <div><span>Contacted</span><b class="c-amber" id="inqMetricContacted">—</b><small>in discussion</small></div>
              <div><span>Converted</span><b class="c-gold" id="inqMetricConverted">—</b><small>live company on Chatly</small></div>
            </div>
          </div>
        </div>
        <div class="panel">
          <div class="panel-h">
            <div class="panel-t">Inquiries</div>
            <div class="tools">
              <input type="search" id="inqSearchInput" class="fc fc-sm search" placeholder="Search brand, product, phone…">
              <select id="inqStatusFilter" class="fc fc-sm">
                <option value="all">All statuses</option>
                <option value="new" selected>New</option>
                <option value="contacted">Contacted</option>
                <option value="converted">Converted</option>
                <option value="archived">Archived</option>
              </select>
            </div>
          </div>
          <div class="panel-b flush">
            <div class="tbl-wrap">
              <table class="rt">
                <thead><tr><th>Brand &amp; product</th><th>Pitch</th><th>Contact</th><th>Submitted</th><th>Status</th><th>Actions</th></tr></thead>
                <tbody id="brandInquiriesTableBody"><tr><td class="empty" colspan="6">Loading brand inquiries…</td></tr></tbody>
              </table>
            </div>
          </div>
        </div>
      </section>

      <!-- ============ LOGINS & AUDIT ============ -->
      <section class="sec" data-sec="security" hidden>
        <div class="panel">
          <div class="panel-h">
            <div class="seg" role="tablist">
              <button class="on" data-act="sec-tab" data-tab="logins" role="tab">Logins</button>
              <button data-act="sec-tab" data-tab="audit" role="tab">Audit log</button>
            </div>
            <div class="tools">
              <input type="search" id="secSearch" class="fc fc-sm search" placeholder="Filter…">
              <button class="btn btn-sm" data-act="reload-security">Refresh</button>
            </div>
          </div>
          <div class="panel-b flush" id="secLogins">
            <div class="tbl-wrap">
              <table class="rt">
                <thead><tr><th>User</th><th>Role</th><th>Company</th><th>IP &amp; location</th><th>Device</th><th>Signed in</th></tr></thead>
                <tbody id="loginsTableBody"><tr><td class="empty" colspan="6">Loading logins…</td></tr></tbody>
              </table>
            </div>
          </div>
          <div class="panel-b flush" id="secAudit" hidden>
            <div class="tbl-wrap">
              <table class="rt">
                <thead><tr><th>Time</th><th>Company</th><th>Actor</th><th>Action</th><th>Details</th></tr></thead>
                <tbody id="auditTableBody"><tr><td class="empty" colspan="5">Loading audit trail…</td></tr></tbody>
              </table>
            </div>
          </div>
        </div>
      </section>
    </main>
  </div>

  <!-- PROVISION COMPANY MODAL -->
  <div class="modal" id="provisionModal" role="dialog" aria-modal="true" aria-labelledby="provTitle">
    <div class="modal-card wide">
      <div class="modal-h"><div><h3 id="provTitle">Provision a new company</h3><p>Creates an isolated company workspace with its own admin account, closer limits and products.</p></div><button class="modal-x" data-act="close-modal" aria-label="Close">&times;</button></div>
      <form id="inlineProvisionForm" onsubmit="handleProvisionCompany(event)">
        <div class="modal-b">
          <div class="frow">
            <div class="fg"><label class="fl" for="compName">Company legal name *</label><input type="text" id="compName" class="fc" placeholder="e.g. Apex Sales Agency" required></div>
            <div class="fg"><label class="fl" for="compSlug">Company slug (URL) *</label><input type="text" id="compSlug" class="fc" placeholder="e.g. apex" required></div>
          </div>
          <div class="frow">
            <div class="fg"><label class="fl" for="compOwnerName">Owner full name *</label><input type="text" id="compOwnerName" class="fc" placeholder="e.g. Tariq Mehmood" required></div>
            <div class="fg"><label class="fl" for="compOwnerEmail">Owner email *</label><input type="email" id="compOwnerEmail" class="fc" placeholder="owner@company.com" required></div>
          </div>
          <div class="frow">
            <div class="fg"><label class="fl" for="compAdminUser">Admin username *</label><input type="text" id="compAdminUser" class="fc" placeholder="e.g. apex_admin" required autocomplete="off"></div>
            <div class="fg"><label class="fl" for="compAdminPass">Admin password *</label><input type="text" id="compAdminPass" class="fc" placeholder="Min 6 characters" required autocomplete="off"></div>
          </div>
          <div class="frow">
            <div class="fg"><label class="fl" for="compPlan">Subscription tier</label>
              <select id="compPlan" class="fc">
                <option value="Starter (Rs. 15,000/mo)">Starter (Rs. 15,000 / mo)</option>
                <option value="Growth (Rs. 35,000/mo)" selected>Growth (Rs. 35,000 / mo)</option>
                <option value="Enterprise Pro (Rs. 75,000/mo)">Enterprise Pro (Rs. 75,000 / mo)</option>
                <option value="Billion Custom (Rs. 150,000/mo)">Billion Custom (Rs. 150,000 / mo)</option>
              </select>
            </div>
            <div class="fg"><label class="fl" for="compDefaultLimit">Default message limit / closer</label><input type="number" id="compDefaultLimit" class="fc" value="200" min="10" max="5000"></div>
          </div>
          <div class="frow">
            <div class="fg"><label class="fl" for="compMaxEmployees">Max allowed closers</label><input type="number" id="compMaxEmployees" class="fc" value="50" min="1" max="1000"></div>
            <div class="fg"><label class="fl" for="compFallbackPhone">Company fallback phone</label><input type="text" id="compFallbackPhone" class="fc" placeholder="+923001234567"></div>
          </div>
          <div class="frow">
            <div class="fg"><label class="fl" for="compLogo">Company logo (image URL or path)</label><input type="text" id="compLogo" class="fc" placeholder="assets/images/logo.png or https://…" oninput="previewCompanyLogo(this.value)"></div>
            <div class="fg"><label class="fl" for="compLogoFile">Or upload a logo</label>
              <div style="display:flex; gap:10px; align-items:center;">
                <input type="file" id="compLogoFile" class="fc" accept="image/*" onchange="handleLogoFileUpload(event)" style="padding:6px 8px; font-size:12px;">
                <div class="logo-prev" id="compLogoPreviewBox"><span id="compLogoPreviewText">logo</span><img id="compLogoPreviewImg" alt="" hidden></div>
              </div>
            </div>
          </div>
          <div id="provisionError" class="err-box" style="display:none;"></div>
        </div>
        <div class="modal-f">
          <button type="button" class="btn" data-act="close-modal">Cancel</button>
          <button type="submit" id="btnProvisionSubmit" class="btn btn-gold">Provision company</button>
        </div>
      </form>
    </div>
  </div>

  <!-- COMPANY DEEP STATS MODAL -->
  <div class="modal" id="deepStatsModal" role="dialog" aria-modal="true" aria-labelledby="statsModalTitle">
    <div class="modal-card wide">
      <div class="modal-h"><div><h3 id="statsModalTitle">Company statistics</h3><p id="statsModalSub">Live lead breakdown</p></div><button class="modal-x" data-act="close-modal" aria-label="Close">&times;</button></div>
      <div class="modal-b">
        <div class="mini-kpis" style="margin-bottom:16px;">
          <div><span>Total leads</span><b id="stTotalLeads">—</b><small id="stOngoing">&nbsp;</small></div>
          <div><span>Paid (closed)</span><b class="c-green" id="stPaidLeads">—</b><small id="stWontPay">&nbsp;</small></div>
          <div><span>Conversion</span><b class="c-gold" id="stConversion">—</b><small>paid / total</small></div>
          <div><span>Messages today</span><b class="c-cyan" id="stMsgs">—</b><small id="stTeam">&nbsp;</small></div>
        </div>
        <div class="panel-t" style="margin-bottom:10px;">Closers &amp; daily message quotas</div>
        <div class="tbl-wrap" style="max-height:300px; overflow:auto;">
          <table class="rt">
            <thead><tr><th>Closer</th><th>Role</th><th>Sent today</th><th>Daily limit</th><th>Status</th></tr></thead>
            <tbody id="stClosersTbody"><tr><td class="empty" colspan="5">Loading closers…</td></tr></tbody>
          </table>
        </div>
      </div>
      <div class="modal-f"><button class="btn" data-act="close-modal">Close</button></div>
    </div>
  </div>

  <!-- ADD FALLBACK NUMBER MODAL -->
  <div class="modal" id="addNumberModal" role="dialog" aria-modal="true" aria-labelledby="numTitle">
    <div class="modal-card">
      <div class="modal-h"><div><h3 id="numTitle">Add a company fallback number</h3></div><button class="modal-x" data-act="close-modal" aria-label="Close">&times;</button></div>
      <form id="addNumberForm" onsubmit="handleAddNumber(event)">
        <div class="modal-b">
          <div class="fg"><label class="fl" for="numPhone">Phone number *</label><input type="text" id="numPhone" class="fc" placeholder="+923001234567" required></div>
          <div class="fg"><label class="fl" for="numLabel">Label / identifier *</label><input type="text" id="numLabel" class="fc" placeholder="e.g. Ufone trunk line 1" required></div>
          <div class="fg"><label class="fl" for="numCap">Daily message capacity</label><input type="number" id="numCap" class="fc" value="500" min="50" max="5000"></div>
        </div>
        <div class="modal-f">
          <button type="button" class="btn" data-act="close-modal">Cancel</button>
          <button type="submit" class="btn btn-gold">Add to number pool</button>
        </div>
      </form>
    </div>
  </div>

  <!-- RECORD NUNNO SALE MODAL -->
  <div class="modal" id="recordNunnoSaleModal" role="dialog" aria-modal="true" aria-labelledby="saleTitle">
    <div class="modal-card">
      <div class="modal-h"><div><h3 id="saleTitle">Record a confirmed plan sale (Nunno)</h3><p>Counts toward the closer's earnings and wallet.</p></div><button class="modal-x" data-act="close-modal" aria-label="Close">&times;</button></div>
      <form onsubmit="submitRecordNunnoSale(event)">
        <div class="modal-b">
          <div class="fg"><label class="fl" for="recordNunnoSaleTMSelect">Nunno closer</label><select id="recordNunnoSaleTMSelect" class="fc" required></select></div>
          <div class="fg"><label class="fl" for="recordNunnoSalePriceSelect">Plan sold</label>
            <select id="recordNunnoSalePriceSelect" class="fc">
              <option value="1700">Rs. 1,700 plan (Rs. 700 commission after the first 10k)</option>
              <option value="5000">Rs. 5,000 plan (Rs. 2,000 commission after the first 10k)</option>
              <option value="15000">Rs. 15,000 plan (Rs. 10,000 commission after the first 10k)</option>
            </select>
          </div>
          <div class="fg"><label class="fl" for="recordNunnoSalePlansInput">How many of this plan</label>
            <input type="number" id="recordNunnoSalePlansInput" class="fc" value="1" min="1" max="50" required>
            <div class="hint">Use this only for sales that did not go through the Chatly inbox. Inbox sales count automatically once an admin verifies them.</div>
          </div>
        </div>
        <div class="modal-f">
          <button type="button" class="btn" data-act="close-modal">Cancel</button>
          <button type="submit" class="btn btn-gold">Save confirmed sale</button>
        </div>
      </form>
    </div>
  </div>

  <!-- BRAND INQUIRY DETAIL MODAL -->
  <div class="modal" id="inquiryDetailModal" role="dialog" aria-modal="true" aria-labelledby="inqModalBrandTitle">
    <div class="modal-card">
      <div class="modal-h"><div><h3 id="inqModalBrandTitle">Brand product details</h3><p id="inqModalSub">Full pitch</p></div><button class="modal-x" data-act="close-modal" aria-label="Close">&times;</button></div>
      <div class="modal-b">
        <div class="kv">
          <div><span>Product</span><b id="inqModalProductName">-</b></div>
          <div><span>Price &amp; category</span><b class="c-green" id="inqModalPriceCat">-</b></div>
        </div>
        <div style="margin-bottom:12px;"><div class="kv" style="margin-bottom:4px;"><div><span>Description &amp; pitch</span></div></div><div class="pitch" id="inqModalPitchText">-</div></div>
        <div class="kv">
          <div><span>Target daily orders</span><b id="inqModalVolume">-</b></div>
          <div><span>Website / social link</span><div id="inqModalWebsiteLink">-</div></div>
        </div>
        <div style="font-size:12px; color:var(--text-2);">Contact: <strong id="inqModalContactName" style="color:var(--text);">-</strong></div>
      </div>
      <div class="modal-f">
        <a id="inqModalWAButton" href="#" target="_blank" rel="noopener noreferrer" class="btn btn-green">Chat on WhatsApp</a>
        <button type="button" class="btn" data-act="close-modal">Close</button>
      </div>
    </div>
  </div>

  <!-- EMAIL PREVIEW MODAL -->
  <div class="modal" id="emPreviewModal" role="dialog" aria-modal="true" aria-labelledby="emPreviewTitle">
    <div class="modal-card wide">
      <div class="modal-h"><div><h3 id="emPreviewTitle">Email preview</h3><p id="emPreviewSubject">Subject</p></div><button class="modal-x" data-act="close-modal" aria-label="Close">&times;</button></div>
      <div class="modal-b"><iframe id="emPreviewFrame" class="em-frame" sandbox="" title="Email preview" referrerpolicy="no-referrer"></iframe><div class="hint" style="margin-top:8px;">Sample recipient shown; {first_name}, {name} and {company} are filled in for each company.</div></div>
      <div class="modal-f"><button class="btn" data-act="close-modal">Close</button></div>
    </div>
  </div>

  <div class="toasts" id="toasts" aria-live="polite"></div>

<script>
'use strict';
/* =========================================================
   Helpers
   ========================================================= */
const $ = (s, r) => (r || document).querySelector(s);
const $$ = (s, r) => Array.from((r || document).querySelectorAll(s));
function esc(v) {
  return String(v ?? '').replace(/[&<>"']/g, ch => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ch]));
}
const n0 = v => { const x = Number(v); return Number.isFinite(x) ? x : 0; };
const fmt = v => n0(v).toLocaleString();
const rs = v => 'Rs. ' + fmt(v);
const pad2 = n => String(n).padStart(2, '0');
const hms = d => pad2(d.getHours()) + ':' + pad2(d.getMinutes()) + ':' + pad2(d.getSeconds());
function safeUrl(u) {
  u = String(u || '').trim();
  if (!u) return '';
  if (!/^https?:\/\//i.test(u)) u = 'https://' + u;
  try { const x = new URL(u); return (x.protocol === 'http:' || x.protocol === 'https:') ? x.href : ''; } catch (_) { return ''; }
}
function safeImg(u) {
  u = String(u || '').trim();
  return (/^https?:\/\//i.test(u) || /^data:image\//i.test(u) || /^(assets|uploads|data)\//i.test(u)) ? u : '';
}
function td(label, html, cls) {
  return `<td data-l="${esc(label)}"${cls ? ` class="${cls}"` : ''}><div class="cv">${html}</div></td>`;
}
const emptyRow = (cols, text, color) => `<tr><td class="empty" colspan="${cols}"${color ? ` style="color:${color}"` : ''}>${esc(text)}</td></tr>`;
function guarded(fn) {
  let busy = false;
  return async function (...args) {
    if (busy) return;
    busy = true;
    try { return await fn.apply(this, args); } finally { busy = false; }
  };
}

let sessionExpired = false;
async function api(url, opts) {
  const res = await fetch(url, Object.assign({ credentials: 'same-origin' }, opts || {}));
  let data = null;
  try { data = await res.json(); } catch (_) { throw new Error('Unreadable server reply (HTTP ' + res.status + ')'); }
  if (res.status === 403 && !sessionExpired) {
    sessionExpired = true;
    const b = $('#sessionBanner');
    if (b) b.hidden = false;
  }
  return data || {};
}
function postJSON(url, body) {
  return api(url, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) });
}

function toast(msg, opts) {
  opts = opts || {};
  const host = $('#toasts');
  if (!host) return;
  const el = document.createElement('div');
  el.className = 'toast ' + (opts.kind || 'ok');
  el.innerHTML = (opts.title ? `<b>${esc(opts.title)}</b>` : '') + `<span>${esc(msg)}</span>`;
  el.addEventListener('click', () => { if (opts.onClick) opts.onClick(); el.remove(); });
  host.appendChild(el);
  const ms = opts.ms || 4200;
  setTimeout(() => el.classList.add('out'), ms);
  setTimeout(() => el.remove(), ms + 400);
}

/* =========================================================
   State, live indicator, history for sparklines
   ========================================================= */
const S = {
  section: 'overview',
  companies: [], telemetry: null,
  pending: [], pendingLoaded: false,
  settlement: null,
  inquiries: [], inqLoaded: false, inqStats: { total: 0, new: 0, contacted: 0, converted: 0 }, lastInquiryTotal: null,
  usage: null, usageMsg: '', poolTotal: null,
  logins: [], audit: [], profiles: [],
  secTab: 'logins'
};

function setLive(ok) {
  const pill = $('#livePill');
  if (!pill) return;
  if (ok) {
    pill.classList.remove('bad', 'blip');
    void pill.offsetWidth;
    pill.classList.add('blip');
    $('#liveLabel').textContent = 'LIVE';
    $('#liveTime').textContent = hms(new Date());
  } else {
    pill.classList.add('bad');
    $('#liveLabel').textContent = 'OFFLINE';
  }
}

const HIST_KEY = 'chatly_ss_hist_v1';
const hist = { msgs: [], sess: [], pend: [] };
try {
  const h = JSON.parse(localStorage.getItem(HIST_KEY) || 'null');
  if (h && typeof h === 'object') {
    Object.keys(hist).forEach(k => {
      if (Array.isArray(h[k])) hist[k] = h[k].filter(p => Array.isArray(p) && p.length === 2 && Number.isFinite(p[0]) && Number.isFinite(p[1]));
    });
  }
} catch (_) {}
function pushHist(k, v) {
  const now = Date.now(), arr = hist[k];
  arr.push([now, n0(v)]);
  const cutoff = now - 2 * 3600 * 1000;
  while (arr.length && (arr[0][0] < cutoff || arr.length > 360)) arr.shift();
}
function saveHist() { try { localStorage.setItem(HIST_KEY, JSON.stringify(hist)); } catch (_) {} }

/* =========================================================
   Tiny SVG charts
   ========================================================= */
let svgUid = 0;
function spark(points, color) {
  const W = 120, H = 30;
  if (!points || points.length < 2) {
    return `<svg viewBox="0 0 ${W} ${H}" preserveAspectRatio="none" aria-hidden="true"><line x1="0" y1="${H - 2}" x2="${W}" y2="${H - 2}" style="stroke:${color}" stroke-opacity=".35" stroke-dasharray="3 4" vector-effect="non-scaling-stroke"/></svg>`;
  }
  const t0 = points[0][0], t1 = points[points.length - 1][0];
  let lo = Infinity, hi = -Infinity;
  points.forEach(p => { lo = Math.min(lo, p[1]); hi = Math.max(hi, p[1]); });
  if (hi === lo) { lo -= 1; hi += 1; }
  const x = t => ((t - t0) / Math.max(1, t1 - t0)) * W;
  const y = v => H - 2 - ((v - lo) / (hi - lo)) * (H - 5);
  const pts = points.map(p => x(p[0]).toFixed(1) + ',' + y(p[1]).toFixed(1)).join(' ');
  const id = 'sg' + (++svgUid);
  return `<svg viewBox="0 0 ${W} ${H}" preserveAspectRatio="none" aria-hidden="true"><defs><linearGradient id="${id}" x1="0" y1="0" x2="0" y2="1"><stop offset="0" style="stop-color:${color};stop-opacity:.35"/><stop offset="1" style="stop-color:${color};stop-opacity:0"/></linearGradient></defs><polygon points="0,${H} ${pts} ${W},${H}" fill="url(#${id})"/><polyline points="${pts}" fill="none" style="stroke:${color}" stroke-width="1.7" stroke-linejoin="round" vector-effect="non-scaling-stroke"/></svg>`;
}
function donut(segs, centerTop, centerSub) {
  const total = segs.reduce((s, g) => s + Math.max(0, n0(g.v)), 0);
  let off = 25, rings = '';
  if (total > 0) {
    segs.forEach(g => {
      const v = Math.max(0, n0(g.v));
      if (!v) return;
      const len = v / total * 100;
      rings += `<circle r="15.9155" cx="21" cy="21" fill="none" stroke-width="5" style="stroke:${g.c}" stroke-dasharray="${len.toFixed(3)} ${(100 - len).toFixed(3)}" stroke-dashoffset="${off.toFixed(3)}"/>`;
      off -= len;
    });
  }
  return `<div class="viz"><div class="donut"><svg viewBox="0 0 42 42" aria-hidden="true"><circle r="15.9155" cx="21" cy="21" fill="none" stroke-width="5" style="stroke:var(--track)"/>${rings}</svg><div class="donut-c"><b>${esc(centerTop)}</b><small>${esc(centerSub)}</small></div></div>` +
    `<ul class="legend">${segs.map(g => `<li><i style="background:${g.c}"></i>${esc(g.label)}<b>${esc(g.text != null ? g.text : fmt(g.v))}</b></li>`).join('')}</ul></div>`;
}
function hbars(items, color) {
  const max = Math.max(1, ...items.map(i => n0(i.v)));
  return items.map(i => `<div class="hb"><div class="hb-top"><span class="hb-l">${esc(i.label)}</span><span class="hb-v">${esc(i.text != null ? i.text : fmt(i.v))}</span></div><div class="bar${i.cls ? ' ' + i.cls : ''}"><i style="width:${(n0(i.v) / max * 100).toFixed(1)}%;${i.cls ? '' : 'background:' + color}"></i></div></div>`).join('');
}
function setKpi(id, val, sub, sparkHtml) {
  const el = document.getElementById(id);
  if (!el) return;
  const v = el.querySelector('.kpi-v');
  if (v.textContent !== val) {
    const first = v.textContent === '—';
    v.textContent = val;
    if (!first) { v.classList.remove('tick'); void v.offsetWidth; v.classList.add('tick'); }
  }
  if (sub !== undefined) el.querySelector('.kpi-s').textContent = sub;
  if (sparkHtml !== undefined) el.querySelector('.kpi-spark').innerHTML = sparkHtml;
}
const coName = id => { const c = S.companies.find(x => x.id === id); return c ? c.name : (id || ''); };

/* =========================================================
   Sections / navigation
   ========================================================= */
const SECTIONS = {
  overview:   ['Overview', 'Live platform pulse across every company'],
  chats:      ['Live chats', 'Every WhatsApp message across all companies, as it happens'],
  ultimate:   ['Ultimate campaign', 'One campaign from every connected WhatsApp on Chatly'],
  companies:  ['Companies', 'Open any company admin, check stats, suspend or delete'],
  autodata:   ['Auto Data limits', 'How many pool numbers each company may use, per day and in total'],
  approvals:  ['Closer approvals', 'Closer applicants waiting for approval on any company'],
  settlement: ['Nunno settlement', 'Verified sales, what closers earned and what Nunno keeps'],
  invoice:    ['Nunno invoice', 'Revenue Nunno collected through Chatly, and what Nunno owes Chatly'],
  emails:     ['Emails', 'Email company owners from the Chatly Gmail sender, see every send, manage the sender'],
  numbers:    ['Number intelligence', 'Every number in every company, sliced and exported'],
  pool:       ['Fallback numbers', 'Company numbers lent to closers until their own phone is ready'],
  brand:      ['Brand leads', 'Brands that asked to promote their product on Chatly'],
  security:   ['Logins & audit', 'Who signed in, and every admin action on the platform']
};
function showSection(name) {
  if (!SECTIONS[name]) name = 'overview';
  S.section = name;
  $$('.sec').forEach(s => { s.hidden = s.dataset.sec !== name; });
  $$('.nav-i').forEach(b => b.classList.toggle('active', b.dataset.sec === name));
  $('#secTitle').textContent = SECTIONS[name][0];
  $('#secSub').textContent = SECTIONS[name][1];
  // <base href="/"> would turn a bare "#hash" into "/#hash", so keep the full path.
  const url = location.pathname + location.search + '#' + name;
  if (location.hash !== '#' + name) { try { history.replaceState(null, '', url); } catch (_) {} }
  const act = $('.nav-i.active');
  if (act && window.innerWidth <= 900 && act.scrollIntoView) act.scrollIntoView({ inline: 'center', block: 'nearest' });
  window.scrollTo(0, 0);
  onShow(name);
}
function onShow(name) {
  if (name === 'chats') { renderTerm(); T.stick = true; T.unseen = 0; updateJump(); scrollTermBottom(); pollChat(); }
  if (name === 'overview') { renderOverview(); pollChat(); }
  if (name === 'companies') renderCompanies();
  if (name === 'ultimate') ucOpen();
  if (name === 'autodata') { renderAutoData(); loadUsage(); }
  if (name === 'approvals') loadMasterPendingTMs();
  if (name === 'settlement') loadNunnoSettlement();
  if (name === 'invoice') loadNunnoInvoice();
  if (name === 'emails') emOpen();
  if (name === 'numbers') nxLoad(false);
  if (name === 'pool') loadNumberPool();
  if (name === 'brand') loadBrandInquiries();
  if (name === 'security') { loadLoginsData(); loadAuditLogs(); }
}

/* =========================================================
   Core telemetry + companies
   ========================================================= */
const refreshCore = guarded(async function () {
  try {
    const [tel, comp] = await Promise.all([
      api('api/companies.php?action=get_telemetry'),
      api('api/companies.php?action=list')
    ]);
    if (tel.success && tel.telemetry) {
      S.telemetry = tel.telemetry;
      pushHist('msgs', S.telemetry.total_messages_today);
      pushHist('sess', S.telemetry.active_sessions);
      if (S.telemetry.pending_tms !== undefined) {
        pushHist('pend', S.telemetry.pending_tms);
        if (!S.pendingLoaded) setPendingBadge(n0(S.telemetry.pending_tms));
      }
      saveHist();
    }
    if (comp.success && Array.isArray(comp.companies)) S.companies = comp.companies;
    if (tel.success || comp.success) setLive(true); else setLive(false);
    fillTermCompanies();
    renderOverview();
    renderCompanies();
    renderAutoData();
    if (S.section === 'emails') emCompaniesChanged();
  } catch (err) {
    setLive(false);
  }
});

function renderOverview() {
  const t = S.telemetry;
  if (t) {
    const sus = S.companies.filter(c => c.status === 'suspended').length;
    setKpi('kCompanies', fmt(t.total_companies), `${fmt(t.active_companies)} active · ${fmt(sus)} suspended`);
    setKpi('kSessions', fmt(t.active_sessions), 'active closers with a linked phone', spark(hist.sess, 'var(--green)'));
    setKpi('kMsgs', fmt(t.total_messages_today), 'sent today by all closers', spark(hist.msgs, 'var(--cyan)'));
  }
  const pend = S.pendingLoaded ? S.pending.length : (t && t.pending_tms !== undefined ? n0(t.pending_tms) : null);
  if (pend !== null) setKpi('kPending', fmt(pend), pend ? 'closers waiting for approval' : 'queue is clear', spark(hist.pend, 'var(--amber)'));
  if (S.usage) {
    let today = 0, total = 0;
    Object.values(S.usage).forEach(u => { today += n0(u && u.today); total += n0(u && u.total); });
    setKpi('kAuto', fmt(today), `${fmt(total)} all-time` + (S.poolTotal != null ? ` · pool ${fmt(S.poolTotal)}` : ''));
  } else if (S.usageMsg) {
    setKpi('kAuto', '—', S.usageMsg);
  }
  const st = S.settlement && S.settlement.summary;
  if (st) {
    setKpi('kRevenue', rs(st.total_confirmed_revenue), `${fmt(st.total_plans_sold)} verified sales`);
    setKpi('kEarned', rs(st.total_payable_by_nunno), `Nunno keeps ${rs(st.total_nunno_profit)}`);
  }
  if (S.inqLoaded) setKpi('kBrand', fmt(S.inqStats.total), `${fmt(S.inqStats.new)} new · ${fmt(S.inqStats.contacted)} contacted`);

  if (S.section !== 'overview') return;
  // Messages by company
  const mb = $('#ovMsgBars');
  if (mb && S.companies.length) {
    const top = S.companies.slice().sort((a, b) => n0(b.messages_today) - n0(a.messages_today)).slice(0, 8);
    mb.innerHTML = top.some(c => n0(c.messages_today) > 0)
      ? hbars(top.map(c => ({ label: c.name, v: c.messages_today })), 'linear-gradient(90deg, var(--cyan), var(--green))')
      : '<div class="empty-note">No messages sent yet today.</div>';
  }
  // Workforce & platform
  const pl = $('#ovPlatform');
  if (pl && t) {
    const tl = n0(t.total_tls), tm = n0(t.total_tms), tot = n0(t.total_employees), other = Math.max(0, tot - tl - tm);
    let leadsHtml;
    if (nxRows.length) {
      const ongoing = nxRows.filter(r => r.s === 'ongoing').length, paid = nxRows.filter(r => r.s === 'paid').length;
      leadsHtml = `${fmt(nxRows.length)}<small>${fmt(ongoing)} ongoing · ${fmt(paid)} paid</small>`;
    } else {
      leadsHtml = nxLoading ? '<small>counting…</small>' : '<button class="btn btn-ghost btn-xs" data-act="nx-kpi">Count leads</button>';
    }
    pl.innerHTML = donut([
      { label: 'Closers', v: tm, c: 'var(--green)' },
      { label: 'Team leads', v: tl, c: 'var(--cyan)' },
      { label: 'Admins & other', v: other, c: 'var(--violet)' }
    ], fmt(tot), 'workforce') +
      `<dl class="stats"><div><dt>Plan MRR (est.)</dt><dd>${t.platform_mrr != null ? esc(rs(t.platform_mrr)) : '—'}</dd></div>` +
      `<div><dt>Leads tracked</dt><dd>${leadsHtml}</dd></div>` +
      `<div><dt>Server time</dt><dd>${esc(t.server_time || '—')}</dd></div></dl>`;
  }
  // Settlement split
  const se = $('#ovSettle');
  if (se && st) {
    se.innerHTML = donut([
      { label: 'Earned by closers', v: st.total_payable_by_nunno, c: 'var(--violet)', text: rs(st.total_payable_by_nunno) },
      { label: 'Nunno keeps', v: st.total_nunno_profit, c: 'var(--gold)', text: rs(st.total_nunno_profit) }
    ], rs(st.total_confirmed_revenue), 'sales') +
      `<dl class="stats"><div><dt>Paid out</dt><dd>${esc(rs(st.total_paid_out))}</dd></div><div><dt>Pending payouts</dt><dd>${esc(rs(st.total_pending_payouts))}</dd></div><div><dt>Closers</dt><dd>${fmt(st.total_tms)}</dd></div></dl>`;
  }
  // Auto Data today
  const oa = $('#ovAuto');
  if (oa) {
    if (S.usage && S.companies.length) {
      const items = S.companies.map(c => ({ c, u: usageFor(c.id) })).sort((a, b) => b.u.today - a.u.today).slice(0, 8);
      oa.innerHTML = items.some(i => i.u.today > 0)
        ? hbars(items.map(({ c, u }) => {
            const pct = u.daily_limit > 0 ? u.today / u.daily_limit * 100 : 0;
            return { label: c.name, v: u.today, text: fmt(u.today) + (u.daily_limit > 0 ? ' / ' + fmt(u.daily_limit) : ''), cls: pct >= 100 ? 'over' : pct >= 80 ? 'warn' : '' };
          }), 'var(--gold)')
        : '<div class="empty-note">No Auto Data numbers used yet today.</div>';
    } else if (S.usageMsg) {
      oa.innerHTML = `<div class="empty-note">${esc(S.usageMsg)}</div>`;
    }
  }
  renderOvPending();
  renderOvAudit();
}

function renderOvPending() {
  const el = $('#ovPending');
  if (!el || !S.pendingLoaded) return;
  if (!S.pending.length) { el.innerHTML = '<div class="empty-note">No closers waiting. All applications are handled.</div>'; return; }
  el.innerHTML = '<ul class="feed">' + S.pending.slice(0, 5).map(a => `<li>
      <span class="when">${esc(a.applied_at || a.created_at || '')}</span>
      <span class="what"><b>${esc(a.name)}</b> <span class="c-dim">@${esc(a.username)}</span><small>${esc(coName(a.company_id))} · ${esc(a.phone || 'phone not linked')}</small></span>
      <span class="acts"><button class="btn btn-green btn-xs" data-act="approve" data-id="${esc(a.id)}" data-name="${esc(a.name)}">Approve</button><button class="btn btn-danger btn-xs" data-act="reject" data-id="${esc(a.id)}" data-name="${esc(a.name)}">Decline</button></span>
    </li>`).join('') + '</ul>' + (S.pending.length > 5 ? `<div class="empty-note">+${fmt(S.pending.length - 5)} more</div>` : '');
}
function renderOvAudit() {
  const el = $('#ovAudit');
  if (!el || !S.auditLoaded) return;
  if (!S.audit.length) { el.innerHTML = '<div class="empty-note">No admin actions logged yet.</div>'; return; }
  el.innerHTML = '<ul class="feed">' + S.audit.slice(0, 7).map(l => `<li>
      <span class="when">${esc(String(l.timestamp || '').slice(5, 16))}</span>
      <span class="what"><span class="tag gold">${esc(l.action)}</span> ${esc(l.details)}<small>${esc(l.actor_name || 'System')} · ${esc(l.company_id ? coName(l.company_id) : 'GLOBAL')}</small></span>
    </li>`).join('') + '</ul>';
}

/* =========================================================
   Companies
   ========================================================= */
function renderCompanies() {
  const tbody = $('#companyTableBody');
  if (!tbody || S.section !== 'companies') return;
  const q = ($('#compSearch').value || '').trim().toLowerCase();
  const st = $('#compStatus').value;
  const list = S.companies.filter(c => {
    if (st === 'suspended' && c.status !== 'suspended') return false;
    if (st === 'active' && c.status === 'suspended') return false;
    if (!q) return true;
    return [c.name, c.slug, c.id, c.owner_name].some(v => String(v || '').toLowerCase().includes(q));
  });
  $('#compCount').textContent = `${fmt(list.length)} of ${fmt(S.companies.length)} companies. Open any company admin in one click.`;
  if (!S.companies.length) { tbody.innerHTML = emptyRow(8, 'No companies provisioned yet.'); return; }
  if (!list.length) { tbody.innerHTML = emptyRow(8, 'No company matches this search.'); return; }
  tbody.innerHTML = list.map(c => {
    const sus = c.status === 'suspended';
    const u = usageFor(c.id);
    const name = String(c.name || c.id || '');
    const id = esc(c.id), nm = esc(name);
    return `<tr>
      ${td('Company', `<div class="co"><div class="av">${esc(name.charAt(0).toUpperCase() || '?')}</div><div><b>${nm}</b><small>/c/${esc(c.slug)} · ${id}${c.owner_name ? ' · ' + esc(c.owner_name) : ''}</small></div></div>`)}
      ${td('Plan', `<span class="tag gold">${esc(c.plan || 'Growth')}</span>`)}
      ${td('Workforce', `<b>${fmt(c.employee_count)}</b><small>${fmt(c.tl_count)} TLs · ${fmt(c.tm_count)} closers</small>`)}
      ${td('Msgs today', `<b class="c-green mono">${fmt(c.messages_today)}</b>`)}
      ${td('Auto Data', (S.usage ? `<span class="mono">${fmt(u.today)}${u.daily_limit ? ' / ' + fmt(u.daily_limit) : ''}</span><small>${fmt(u.total)} total${u.total_limit ? ' of ' + fmt(u.total_limit) : ''}</small>` : '<small>—</small>')
        + `<button class="btn btn-ghost btn-xs" style="margin-top:4px" data-act="ad-limits" data-id="${id}" title="Set this company's daily and total Auto Data limits">${u.daily_limit || u.total_limit ? 'Edit limits' : 'Set limits'}</button>`)}
      ${td('Fallback phone', `<span class="mono">${esc(c.fallback_phone || '—')}</span>`)}
      ${td('Status', `<span class="tag ${sus ? 'red' : 'green'}">${sus ? 'Suspended' : 'Active'}</span>`)}
      ${td('Actions', `<button class="btn btn-gold btn-xs" data-act="impersonate" data-id="${id}" title="Log in as this company's admin">Open admin</button>
        <button class="btn btn-xs" data-act="stats" data-id="${id}">Stats</button>
        <button class="btn btn-xs" data-act="toggle" data-id="${id}" data-name="${nm}" data-sus="${sus ? '1' : '0'}">${sus ? 'Activate' : 'Suspend'}</button>
        ${c.id !== 'COMP-001' ? `<button class="btn btn-danger btn-xs" data-act="delete-company" data-id="${id}" data-name="${nm}">Delete</button>` : ''}`, 'acts')}
    </tr>`;
  }).join('');
}

async function impersonateCompanyAdmin(companyId) {
  try {
    const data = await postJSON('api/companies.php', { action: 'impersonate', company_id: companyId });
    if (data.success && data.redirect) window.location.href = data.redirect;
    else toast(data.error || 'Failed to enter company admin.', { kind: 'err' });
  } catch (err) {
    toast('Error opening company admin.', { kind: 'err' });
  }
}

async function viewCompanyStats(companyId) {
  const name = coName(companyId);
  $('#statsModalTitle').textContent = 'Statistics — ' + name;
  $('#statsModalSub').textContent = 'Company ID: ' + companyId;
  ['stTotalLeads', 'stPaidLeads', 'stConversion', 'stMsgs'].forEach(id => { $('#' + id).textContent = '…'; });
  $('#stClosersTbody').innerHTML = emptyRow(5, 'Loading closers…');
  openModal('deepStatsModal');
  try {
    const data = await api('api/companies.php?action=get_company_deep_stats&company_id=' + encodeURIComponent(companyId));
    if (!data.success || !data.stats) throw new Error(data.error || 'No stats returned.');
    const s = data.stats;
    $('#stTotalLeads').textContent = fmt(s.total_leads);
    $('#stOngoing').textContent = fmt(s.ongoing_leads) + ' ongoing';
    $('#stPaidLeads').textContent = fmt(s.paid_leads);
    $('#stWontPay').textContent = fmt(s.wont_pay_leads) + " won't pay";
    $('#stConversion').textContent = n0(s.conversion_rate) + '%';
    $('#stMsgs').textContent = fmt(s.messages_today);
    $('#stTeam').textContent = `${fmt(s.tls_count)} TLs · ${fmt(s.tms_count)} closers`;
    const closers = Array.isArray(s.closers) ? s.closers : [];
    $('#stClosersTbody').innerHTML = closers.length ? closers.map(cl => `<tr>
        ${td('Closer', `<b>${esc(cl.name)}</b><small>@${esc(cl.username)}</small>`)}
        ${td('Role', esc(cl.role === 'lead' ? 'Team lead' : cl.role === 'admin' ? 'Admin' : 'Closer'))}
        ${td('Sent today', `<b class="c-green mono">${fmt(cl.messages_today)}</b>`)}
        ${td('Daily limit', `<span class="mono">${n0(cl.message_limit) === -1 ? '∞' : fmt(cl.message_limit)}</span>`)}
        ${td('Status', `<span class="tag ${cl.status === 'active' ? 'green' : 'red'}">${esc(cl.status)}</span>`)}
      </tr>`).join('') : emptyRow(5, 'No closers in this company yet.');
  } catch (err) {
    $('#stClosersTbody').innerHTML = emptyRow(5, 'Could not load stats: ' + err.message, 'var(--red)');
  }
}

async function toggleCompanyStatus(companyId, name, isSuspended) {
  if (!isSuspended && !confirm(`Suspend "${name}"?`)) return;
  try {
    const data = await postJSON('api/companies.php', { action: 'toggle_status', company_id: companyId });
    if (data.success) { toast(`${name}: ${isSuspended ? 'activated' : 'suspended'}.`); refreshCore(); }
    else toast(data.error || 'Could not change company status.', { kind: 'err' });
  } catch (err) {
    toast('Error toggling company status.', { kind: 'err' });
  }
}

async function deleteCompany(companyId, name) {
  if (!confirm(`Permanently delete "${name}" (${companyId}) and its isolated tenant records? This action cannot be undone.`)) return;
  try {
    const data = await postJSON('api/companies.php', { action: 'delete_company', company_id: companyId });
    if (data.success) { toast(data.message || 'Company deleted.'); refreshCore(); }
    else toast(data.error || 'Failed to delete company.', { kind: 'err' });
  } catch (err) {
    toast('Error deleting company.', { kind: 'err' });
  }
}

/* Provision */
function openProvisionModal() {
  $('#provisionError').style.display = 'none';
  openModal('provisionModal');
  setTimeout(() => { const f = $('#compName'); if (f) f.focus(); }, 50);
}
function previewCompanyLogo(url) {
  const img = $('#compLogoPreviewImg'), txt = $('#compLogoPreviewText');
  if (!img || !txt) return;
  const u = safeImg(url);
  if (u) {
    img.onerror = () => { img.hidden = true; txt.hidden = false; };
    img.src = u;
    img.hidden = false;
    txt.hidden = true;
  } else {
    img.hidden = true;
    img.removeAttribute('src');
    txt.hidden = false;
  }
}
function handleLogoFileUpload(e) {
  const file = e.target.files && e.target.files[0];
  if (!file) return;
  const reader = new FileReader();
  reader.onload = evt => {
    const dataUrl = evt.target.result;
    const input = $('#compLogo');
    if (input) input.value = dataUrl;
    previewCompanyLogo(dataUrl);
  };
  reader.readAsDataURL(file);
}
async function handleProvisionCompany(e) {
  e.preventDefault();
  const btn = $('#btnProvisionSubmit'), errBox = $('#provisionError');
  btn.disabled = true;
  btn.textContent = 'Provisioning…';
  errBox.style.display = 'none';
  try {
    const data = await postJSON('api/companies.php', {
      action: 'create',
      name: $('#compName').value.trim(),
      slug: $('#compSlug').value.trim(),
      owner_name: $('#compOwnerName').value.trim(),
      owner_email: $('#compOwnerEmail').value.trim(),
      admin_username: $('#compAdminUser').value.trim(),
      admin_password: $('#compAdminPass').value.trim(),
      plan: $('#compPlan').value,
      logo: ($('#compLogo').value || '').trim(),
      fallback_phone: ($('#compFallbackPhone').value || '').trim(),
      default_message_limit: parseInt($('#compDefaultLimit').value, 10) || 200,
      max_employees: parseInt($('#compMaxEmployees').value, 10) || 50
    });
    if (data.success) {
      toast(data.message || 'Company provisioned.', { title: 'Company created' });
      $('#inlineProvisionForm').reset();
      previewCompanyLogo('');
      closeModal('provisionModal');
      showSection('companies');
      refreshCore();
    } else {
      errBox.textContent = data.error || 'Failed to provision company.';
      errBox.style.display = 'block';
    }
  } catch (err) {
    errBox.textContent = 'Server communication error.';
    errBox.style.display = 'block';
  } finally {
    btn.disabled = false;
    btn.textContent = 'Provision company';
  }
}

/* =========================================================
   Auto Data limits
   ========================================================= */
function usageFor(id) {
  const u = (S.usage && S.usage[id]) || {};
  return { today: n0(u.today), total: n0(u.total), daily_limit: n0(u.daily_limit), total_limit: n0(u.total_limit) };
}
const loadUsage = guarded(async function () {
  try {
    const d = await api('api/companies.php?action=auto_data_usage');
    if (d && d.success) {
      S.usage = (d.usage && typeof d.usage === 'object' && !Array.isArray(d.usage)) ? d.usage : {};
      S.poolTotal = d.pool_total != null ? d.pool_total : null;
      S.usageMsg = '';
    } else {
      S.usageMsg = (d && (d.message || d.error)) || 'Auto Data usage is not available right now.';
    }
  } catch (err) {
    S.usageMsg = 'Could not load Auto Data usage: ' + err.message;
  }
  renderAutoData();
  renderCompanies();
  renderOverview();
});

function adRow(id) {
  const host = $('#adList');
  if (!host) return null;
  return Array.from(host.children).find(el => el.dataset && el.dataset.cid === id) || null;
}
function adRowHTML(c) {
  const id = esc(c.id), name = String(c.name || c.id || '');
  return `<div class="ad-row${c.status === 'suspended' ? ' suspended' : ''}" data-cid="${id}">
    <div class="ad-co"><div class="co"><div class="av">${esc(name.charAt(0).toUpperCase() || '?')}</div><div><b>${esc(name)}</b><small style="display:block; color:var(--text-3); font-size:11.5px;">${id}${c.status === 'suspended' ? ' · suspended' : ''}</small></div></div></div>
    <div class="ad-meter" data-k="today"><div class="ad-meter-top"><span>Today</span><span class="ad-num"></span></div><div class="bar"><i></i></div></div>
    <div class="ad-meter" data-k="total"><div class="ad-meter-top"><span>Total</span><span class="ad-num"></span></div><div class="bar"><i></i></div></div>
    <div class="ad-form">
      <label>Daily limit<input type="number" min="0" step="1" inputmode="numeric" data-f="daily" placeholder="Unlimited"></label>
      <label>Total limit<input type="number" min="0" step="1" inputmode="numeric" data-f="total" placeholder="Unlimited"></label>
      <button class="btn btn-gold btn-sm" data-act="ad-save" data-id="${id}">Save</button>
      <div class="ad-msg"></div>
    </div>
  </div>`;
}
function adMeter(el, used, limit) {
  const num = el.querySelector('.ad-num'), bar = el.querySelector('.bar'), fill = bar.firstElementChild;
  if (limit > 0) {
    const pct = used / limit * 100;
    num.innerHTML = `<b>${fmt(used)}</b> / ${fmt(limit)}<em>${Math.round(pct)}%</em>`;
    bar.className = 'bar' + (pct >= 100 ? ' over' : pct >= 80 ? ' warn' : '');
    fill.style.width = Math.min(100, pct).toFixed(1) + '%';
  } else {
    num.innerHTML = `<b>${fmt(used)}</b> · no limit`;
    bar.className = 'bar unl';
    fill.style.width = '100%';
  }
}
function adUpdateRow(c) {
  const row = adRow(c.id);
  if (!row) return;
  const u = usageFor(c.id);
  adMeter(row.querySelector('[data-k="today"]'), u.today, u.daily_limit);
  adMeter(row.querySelector('[data-k="total"]'), u.total, u.total_limit);
  [['daily', u.daily_limit], ['total', u.total_limit]].forEach(([f, v]) => {
    const inp = row.querySelector(`[data-f="${f}"]`);
    if (inp && !inp.classList.contains('dirty') && document.activeElement !== inp) inp.value = v > 0 ? String(v) : '';
  });
}
function renderAutoData() {
  const host = $('#adList'), banner = $('#adBanner');
  if (!host) return;
  if (banner) {
    banner.hidden = !S.usageMsg;
    banner.textContent = S.usageMsg ? (S.usage ? S.usageMsg + ' Showing the last values that loaded.' : S.usageMsg) : '';
  }
  if (!S.usage) {
    host.dataset.sig = '';
    host.innerHTML = `<div class="empty-note">${S.usageMsg ? 'Limits can be edited once usage loads.' : 'Loading usage…'}</div>`;
    return;
  }
  let today = 0, total = 0, limited = 0;
  S.companies.forEach(c => { const u = usageFor(c.id); if (u.daily_limit > 0 || u.total_limit > 0) limited++; });
  Object.values(S.usage).forEach(u => { today += n0(u && u.today); total += n0(u && u.total); });
  $('#adPool').textContent = S.poolTotal != null ? fmt(S.poolTotal) : '—';
  $('#adToday').textContent = fmt(today);
  $('#adTotal').textContent = fmt(total);
  $('#adLimited').textContent = `${fmt(limited)} / ${fmt(S.companies.length)}`;
  if (!S.companies.length) { host.innerHTML = '<div class="empty-note">No companies yet.</div>'; host.dataset.sig = ''; return; }
  const sig = S.companies.map(c => c.id + ':' + c.name + ':' + c.status).join('|');
  if (host.dataset.sig !== sig) {
    host.innerHTML = S.companies.map(adRowHTML).join('');
    host.dataset.sig = sig;
  }
  S.companies.forEach(adUpdateRow);
}
function setAdMsg(row, text, kind) {
  const m = row.querySelector('.ad-msg');
  if (!m) return;
  m.className = 'ad-msg ' + (kind || '');
  m.textContent = text;
  if (kind === 'ok') setTimeout(() => { if (m.textContent === text) m.textContent = ''; }, 3500);
}
async function saveAutoDataLimits(companyId, btn) {
  const row = adRow(companyId);
  if (!row) return;
  const di = row.querySelector('[data-f="daily"]'), ti = row.querySelector('[data-f="total"]');
  const parse = v => { v = String(v).trim(); if (v === '') return 0; return /^\d+$/.test(v) ? parseInt(v, 10) : NaN; };
  const daily = parse(di.value), total = parse(ti.value);
  if (Number.isNaN(daily) || Number.isNaN(total)) { setAdMsg(row, 'Limits must be whole numbers (0 or empty = unlimited).', 'err'); return; }
  btn.disabled = true;
  btn.textContent = 'Saving…';
  try {
    const d = await postJSON('api/companies.php?action=set_auto_data_limits', { company_id: companyId, daily_limit: daily, total_limit: total });
    if (d.success) {
      S.usage = S.usage || {};
      S.usage[companyId] = Object.assign({}, S.usage[companyId] || {}, { daily_limit: daily, total_limit: total });
      di.classList.remove('dirty');
      ti.classList.remove('dirty');
      di.value = daily ? String(daily) : '';
      ti.value = total ? String(total) : '';
      const c = S.companies.find(x => x.id === companyId);
      if (c) adUpdateRow(c);
      setAdMsg(row, `Saved: daily ${daily ? fmt(daily) : 'unlimited'}, total ${total ? fmt(total) : 'unlimited'}.`, 'ok');
      renderOverview();
    } else {
      setAdMsg(row, d.error || d.message || 'Could not save limits.', 'err');
    }
  } catch (err) {
    setAdMsg(row, 'Network error: ' + err.message, 'err');
  } finally {
    btn.disabled = false;
    btn.textContent = 'Save';
  }
}

/* =========================================================
   Live chat terminal
   ========================================================= */
const T = { lines: [], seen: new Set(), paused: false, stick: true, unseen: 0, co: '', grep: '', loaded: false, lastPollAt: 0, err: '' };
const T_MAX = 800;
const chatKey = m => [m.company_id, m.lead_phone, m.time, m.text].map(v => String(v ?? '')).join('␟');
function tPass(L) {
  if (L.kind !== 'msg') return true;
  if (T.co && L.m.company_id !== T.co) return false;
  if (T.grep) {
    const m = L.m;
    const hay = [m.company_name, m.closer_name, m.lead_name, m.lead_phone, m.text].map(v => String(v ?? '')).join(' ').toLowerCase();
    if (!hay.includes(T.grep)) return false;
  }
  return true;
}
function fmtPhone(p) {
  p = String(p || '').trim();
  if (!p) return 'unknown';
  return /^\d{8,}$/.test(p) ? '+' + p : p;
}
function lineHTML(L, animate) {
  const cls = 'tl' + (animate ? ' new' : '');
  if (L.kind === 'sys') return `<div class="${cls} sys${L.err ? ' err' : ''}"><span class="tt">[${hms(L.at)}]</span> sys: ${esc(L.text)}</div>`;
  const m = L.m, agent = m.sender === 'agent';
  const phone = fmtPhone(m.lead_phone);
  const name = String(m.lead_name || '').trim();
  const sameAsPhone = name.replace(/\D/g, '') !== '' && name.replace(/\D/g, '') === String(m.lead_phone || '').replace(/\D/g, '');
  const leadLbl = name && !sameAsPhone ? `${phone} (${name})` : phone;
  const closer = String(m.closer_name || 'Closer');
  const text = String(m.text ?? '').trim() || '‹media›';
  const who = agent
    ? `<span class="ta">${esc(closer)} (closer)</span> <span class="tg">→</span> <span class="tp">${esc(leadLbl)}</span>`
    : `<span class="tld">${esc(leadLbl)}</span> <span class="tg">→</span> <span class="tp">${esc(closer)}</span>`;
  const stateKey = String(m.state || '').replace(/[^a-z_]/gi, '');
  const state = stateKey ? ` <span class="ts s-${stateKey}">[${esc(m.state)}]</span>` : '';
  return `<div class="${cls} ${agent ? 'agent' : 'lead'}"><span class="tt">[${esc(m.time || '--:--')}]</span> <span class="tc">${esc(m.company_name || m.company_id)}</span> <span class="tg">›</span> ${who}<span class="tg">:</span> <span class="tm">${esc(text)}</span>${state}</div>`;
}
function scrollTermBottom() { const s = $('#termScreen'); if (s) s.scrollTop = s.scrollHeight; }
function updateJump() {
  const j = $('#termJump');
  if (!j) return;
  j.hidden = T.stick;
  j.textContent = T.unseen > 0 ? `↓ ${T.unseen} new line${T.unseen === 1 ? '' : 's'}` : '↓ back to live';
}
function termStatus() {
  const st = $('#tState');
  if (st) {
    if (T.paused) { st.className = 'term-state paused'; st.textContent = '❚❚ PAUSED'; }
    else if (T.err) { st.className = 'term-state err'; st.textContent = '✖ ERROR'; }
    else { st.className = 'term-state live'; st.textContent = '● LIVE'; }
  }
  const msgs = T.lines.filter(L => L.kind === 'msg');
  const cnt = $('#tCount'); if (cnt) cnt.textContent = fmt(msgs.length);
  const cos = $('#tCos'); if (cos) cos.textContent = fmt(new Set(msgs.map(L => L.m.company_id)).size);
  const last = $('#tLast'); if (last) last.textContent = T.lastPollAt ? hms(new Date(T.lastPollAt)) : '—';
  const term = $('#term'); if (term) term.classList.toggle('paused', T.paused);
  const pb = $('#tPauseBtn'); if (pb) { pb.textContent = T.paused ? '▶ resume' : '❚❚ pause'; pb.classList.toggle('on', T.paused); }
  const cmd = $('#tPromptCmd'); if (cmd) cmd.textContent = T.paused ? '# paused — press resume' : 'tail -f chats.log' + (T.co ? ' | grep ' + coName(T.co) : '') + (T.grep ? ' | grep "' + T.grep + '"' : '');
  const nl = $('#navChatsLive'); if (nl) nl.style.background = T.paused ? 'var(--amber)' : (T.err ? 'var(--red)' : '');
}
function renderTerm() {
  const out = $('#termOut');
  if (!out) return;
  out.innerHTML = T.lines.filter(tPass).map(L => lineHTML(L, false)).join('');
  if (T.stick) scrollTermBottom();
  termStatus();
}
function renderMiniTerm(newCount) {
  const out = $('#ovTermOut'), scr = $('#ovTermScreen');
  if (!out) return;
  const last = T.lines.slice(-16);
  const animFrom = last.length - Math.min(newCount || 0, last.length);
  out.innerHTML = last.map((L, i) => lineHTML(L, i >= animFrom && newCount > 0)).join('');
  if (scr) scr.scrollTop = scr.scrollHeight;
}
function addLines(newLines) {
  if (!newLines.length) return;
  T.lines.push(...newLines);
  if (T.lines.length > T_MAX) T.lines.splice(0, T.lines.length - T_MAX);
  if (T.seen.size > 6000) T.seen = new Set(T.lines.filter(L => L.kind === 'msg').map(L => L.key));
  const out = $('#termOut');
  if (out) {
    const vis = newLines.filter(tPass);
    if (vis.length) {
      out.insertAdjacentHTML('beforeend', vis.map(L => lineHTML(L, true)).join(''));
      while (out.children.length > T_MAX) out.removeChild(out.firstChild);
      if (T.stick) scrollTermBottom();
      else { T.unseen += vis.length; updateJump(); }
    }
  }
  renderMiniTerm(newLines.length);
  termStatus();
}
const sysLine = (text, err) => addLines([{ kind: 'sys', text, err: !!err, at: new Date() }]);
const pollChat = guarded(async function () {
  if (T.paused) return;
  try {
    const d = await api('api/companies.php?action=get_live_chat_stream');
    if (!d.success || !Array.isArray(d.stream)) throw new Error(d.error || 'stream unavailable');
    const fresh = [];
    // The stream comes newest first: walk it oldest first so lines append in order.
    for (const m of d.stream.slice().reverse()) {
      if (!m || typeof m !== 'object') continue;
      const key = chatKey(m);
      if (T.seen.has(key)) continue;
      T.seen.add(key);
      fresh.push({ kind: 'msg', m, key, at: new Date() });
    }
    T.lastPollAt = Date.now();
    if (T.err) { T.err = ''; sysLine('stream reconnected'); }
    if (!T.loaded) { T.loaded = true; sysLine(`connected to live chat stream · ${fresh.length} recent message${fresh.length === 1 ? '' : 's'} buffered`); }
    addLines(fresh);
  } catch (err) {
    T.lastPollAt = Date.now();
    if (T.err !== err.message) sysLine('stream error: ' + err.message, true);
    T.err = err.message;
  }
  termStatus();
});
function toggleTermPause() {
  T.paused = !T.paused;
  sysLine(T.paused ? 'stream paused by operator' : 'stream resumed');
  termStatus();
  if (!T.paused) pollChat();
}
function onTermScroll() {
  const s = $('#termScreen');
  if (!s) return;
  const atBottom = s.scrollHeight - s.scrollTop - s.clientHeight < 30;
  T.stick = atBottom;
  if (atBottom) T.unseen = 0;
  updateJump();
}
function fillTermCompanies() {
  const sel = $('#termCo');
  if (!sel) return;
  const sig = S.companies.map(c => c.id + ':' + c.name).join('|');
  if (sel.dataset.sig === sig) return;
  sel.dataset.sig = sig;
  const cur = sel.value;
  sel.innerHTML = '<option value="">all companies</option>' + S.companies.map(c => `<option value="${esc(c.id)}">${esc(c.name)}</option>`).join('');
  sel.value = cur;
  if (sel.value !== cur) sel.value = '';
}

/* =========================================================
   Closer approvals
   ========================================================= */
function setPendingBadge(count) {
  const b = $('#navPending');
  if (b) { b.textContent = String(count); b.hidden = !(count > 0); }
}
const loadMasterPendingTMs = guarded(async function () {
  const tbody = $('#masterPendingTableBody');
  try {
    const data = await api('api/auth.php?action=list_pending_tms');
    if (!data.success || !Array.isArray(data.pending)) throw new Error(data.error || 'Could not load applicants.');
    S.pending = data.pending;
    S.pendingLoaded = true;
    setPendingBadge(S.pending.length);
    renderOverview();
    if (!tbody) return;
    if (!S.pending.length) { tbody.innerHTML = emptyRow(7, 'No pending applicants on the platform. Every closer registration has been handled.'); return; }
    tbody.innerHTML = S.pending.map(app => {
      const pfp = safeImg(app.pfp);
      const id = esc(app.id), nm = esc(app.name);
      return `<tr>
        ${td('Applicant', `<div class="co"><div class="av">${pfp ? `<img src="${esc(pfp)}" alt="">` : esc(String(app.name || '?').charAt(0).toUpperCase())}</div><div><b>${nm}</b><small>Closer applicant</small></div></div>`)}
        ${td('Company', `<span class="tag gold">${esc(coName(app.company_id) || 'COMP-001')}</span>`)}
        ${td('Username & email', `<b class="mono c-green">@${esc(app.username)}</b><small>${esc(app.email || 'No email')}</small>`)}
        ${td('WhatsApp phone', `<span class="mono">${app.phone ? esc(app.phone) : '<em class="c-dim">Not linked</em>'}</span>`)}
        ${td('City & gender', `${esc(app.city || 'Pakistan')}<small>${esc(app.gender || 'male')} closer</small>`)}
        ${td('Applied', `<span class="mono">${esc(app.applied_at || app.created_at || 'Just now')}</span>`)}
        ${td('Actions', `<button class="btn btn-green btn-xs" data-act="approve" data-id="${id}" data-name="${nm}">Approve</button><button class="btn btn-danger btn-xs" data-act="reject" data-id="${id}" data-name="${nm}">Decline</button>`, 'acts')}
      </tr>`;
    }).join('');
  } catch (err) {
    if (tbody) tbody.innerHTML = emptyRow(7, 'Failed to load pending applicants: ' + err.message, 'var(--red)');
  }
});
async function masterApproveTM(userId, name) {
  const limit = prompt(`Enter daily message quota for "${name}":`, '150');
  if (!limit) return;
  try {
    const data = await postJSON('api/auth.php', { action: 'approve_tm', user_id: userId, message_limit: parseInt(limit, 10) || 150 });
    if (data.success) { toast(`"${name}" approved and activated.`, { title: 'Closer approved' }); loadMasterPendingTMs(); refreshCore(); }
    else toast(data.error || 'Failed to approve closer.', { kind: 'err' });
  } catch (err) {
    toast('Server error while approving.', { kind: 'err' });
  }
}
async function masterRejectTM(userId, name) {
  if (!confirm(`Are you sure you want to decline the application for "${name}"?`)) return;
  try {
    const data = await postJSON('api/auth.php', { action: 'reject_tm', user_id: userId });
    if (data.success) { toast('Applicant removed.'); loadMasterPendingTMs(); }
    else toast(data.error || 'Failed to decline applicant.', { kind: 'err' });
  } catch (err) {
    toast('Server error while declining.', { kind: 'err' });
  }
}

/* =========================================================
   Nunno settlement
   ========================================================= */
// Nunno invoice: revenue collected through Chatly and what Nunno owes Chatly (no Nunno margin).
const loadNunnoInvoice = guarded(async function () {
  try {
    const d = await api('api/companies.php?action=get_nunno_invoice');
    if (!d.success || !d.invoice) throw new Error(d.error || 'Invoice unavailable');
    const inv = d.invoice;
    $('#invRevenue').textContent = rs(inv.total_revenue_collected);
    $('#invRevenueSub').textContent = `${fmt(inv.tm_count)} TMs · ${fmt(inv.tl_count)} TLs`;
    $('#invPayable').textContent = rs(inv.total_payable_to_chatly);
    $('#invPayableSub').textContent = `TM payouts ${rs(inv.tm_payable)} + TL commissions ${rs(inv.tl_payable)} (${fmt(inv.tl_activations)} TMs activated)`;
    $('#invGenerated').textContent = 'Generated ' + new Date(d.generated_at || Date.now()).toLocaleString();
    const lines = Array.isArray(inv.lines) ? inv.lines : [];
    $('#invLines').innerHTML = lines.length ? lines.map(l => `<tr>
        <td data-l="Closer">${esc(l.name)}</td><td data-l="Role">${esc(l.role)}</td><td data-l="Verified sales">${fmt(l.deals)}</td>
        <td data-l="Revenue collected">${rs(l.revenue)}</td><td data-l="Payable"><b>${rs(l.payable)}</b></td></tr>`).join('')
      + `<tr><td colspan="3"><b>Total</b></td><td><b>${rs(inv.total_revenue_collected)}</b></td><td><b>${rs(inv.total_payable_to_chatly)}</b></td></tr>`
      : '<tr><td class="empty" colspan="5">No verified sales yet.</td></tr>';
  } catch (e) {
    $('#invLines').innerHTML = `<tr><td class="empty" colspan="5">${esc(e.message)}</td></tr>`;
  }
});

let nunnoSettlementData = null;
const loadNunnoSettlement = guarded(async function () {
  try {
    const data = await api('api/companies.php?action=get_nunno_settlement');
    if (!data.success) return;
    nunnoSettlementData = data;
    S.settlement = data;
    renderNunnoSettlement(data);
    renderOverview();
  } catch (err) {
    const tb = $('#tableNunnoSettlementBody');
    if (tb && !nunnoSettlementData) tb.innerHTML = emptyRow(8, 'Failed to load settlement: ' + err.message, 'var(--red)');
  }
});
function renderNunnoSettlement(data) {
  const s = data.summary || {};
  const closers = Array.isArray(data.closers) ? data.closers : [];
  $('#nunnoConfirmedRev').textContent = rs(s.total_confirmed_revenue);
  $('#nunnoConfirmedRevSub').textContent = `${fmt(s.total_plans_sold)} verified sales`;
  $('#nunnoPayableToChatly').textContent = rs(s.total_payable_by_nunno);
  $('#nunnoRetainedProfit').textContent = rs(s.total_nunno_profit);
  $('#nunnoActiveTMs').textContent = fmt(s.total_tms || closers.length);
  $('#nunnoPayoutsSub').textContent = `Payouts: ${rs(s.total_paid_out)} paid · ${rs(s.total_pending_payouts)} pending`;
  const tbody = $('#tableNunnoSettlementBody');
  if (!tbody) return;
  if (!closers.length) { tbody.innerHTML = emptyRow(8, 'No closers currently assigned to Nunno.'); return; }
  tbody.innerHTML = closers.map(c => {
    const prog = n0(c.full_pay_progress);
    const pct = Math.min(100, Math.round(prog / 100));
    const progCell = prog >= 10000
      ? '<span class="tag green">Done · on plan commission</span>'
      : `<span class="mono c-gold">${esc(rs(prog))} / Rs. 10,000</span><div class="bar" style="width:140px; max-width:100%; margin-top:5px;"><i style="width:${pct}%; background:linear-gradient(90deg, var(--amber), var(--green));"></i></div>`;
    return `<tr>
      ${td('Closer', `<div class="co"><div class="av">${esc(String(c.name || 'C').charAt(0).toUpperCase())}</div><div><b>${esc(c.name || 'Closer')}</b><small>@${esc(c.username || '')}${c.phone ? ' · ' + esc(c.phone) : ''}</small></div></div>`)}
      ${td('Verified sales', `<b class="mono">${fmt(c.deals)}</b>${c.in_review_count ? `<small>+${fmt(c.in_review_count)} in review</small>` : ''}`)}
      ${td('Sales revenue', `<b class="mono c-green">${esc(rs(c.gross_revenue))}</b>`)}
      ${td('First Rs. 10,000', progCell)}
      ${td('Earned by closer', `<b class="mono c-green">${esc(rs(c.amount_payable_by_nunno))}</b>`)}
      ${td('Nunno keeps', `<b class="mono c-gold">${esc(rs(c.nunno_retained_profit))}</b>`)}
      ${td('Payouts', `<span class="c-green">Paid ${esc(rs(c.paid_out))}</span><small class="c-amber">Pending ${esc(rs(c.pending_withdrawals))}</small><small>Available ${esc(rs(c.available))}</small>`)}
      ${td('Actions', `<button class="btn btn-green btn-xs" data-act="quick-sale" data-id="${esc(c.id)}" data-name="${esc(c.name || '')}">+ Sale</button>`, 'acts')}
    </tr>`;
  }).join('');
}
async function quickAddNunnoSale(userId, name) {
  const pick = prompt(`Record a verified sale for ${name}.\nWhich plan? Type 1700, 5000 or 15000:`, '1700');
  if (pick === null) return;
  const price = parseInt(pick.replace(/[^0-9]/g, ''), 10);
  if (![1700, 5000, 15000].includes(price)) { alert('Please type 1700, 5000 or 15000.'); return; }
  try {
    const d = await postJSON('api/companies.php?action=record_nunno_sale', { user_id: userId, plans: 1, plan_price: price });
    if (d.success) { toast(d.message || 'Sale recorded.'); loadNunnoSettlement(); }
    else toast(d.error || 'Failed to record sale.', { kind: 'err' });
  } catch (e) {
    toast('Error recording sale.', { kind: 'err' });
  }
}
function openRecordNunnoSaleModal() {
  const select = $('#recordNunnoSaleTMSelect');
  if (select && nunnoSettlementData && Array.isArray(nunnoSettlementData.closers)) {
    select.innerHTML = nunnoSettlementData.closers.map(c => `<option value="${esc(c.id)}">${esc(c.name)} (@${esc(c.username || '')}) · ${fmt(c.deals)} sales</option>`).join('');
  }
  openModal('recordNunnoSaleModal');
}
async function submitRecordNunnoSale(e) {
  if (e) e.preventDefault();
  const select = $('#recordNunnoSaleTMSelect');
  const userId = select ? select.value : '';
  const plans = parseInt($('#recordNunnoSalePlansInput').value, 10) || 1;
  if (!userId) { alert('Please select a closer.'); return; }
  try {
    const d = await postJSON('api/companies.php?action=record_nunno_sale', { user_id: userId, plans: plans, plan_price: parseInt($('#recordNunnoSalePriceSelect').value, 10) || 1700 });
    if (d.success) { closeModal('recordNunnoSaleModal'); toast(d.message || 'Sale recorded.'); loadNunnoSettlement(); }
    else toast(d.error || 'Failed to record sale.', { kind: 'err' });
  } catch (err) {
    toast('Error recording sale.', { kind: 'err' });
  }
}

/* =========================================================
   Number intelligence
   ========================================================= */
let nxRows = [];
let nxCompanies = {};
let nxSegment = 'all';
let nxLimit = 300;
let nxFiltered = [];
let nxLoadedAt = 0;
let nxLoading = false;
const NX_STATE = { fresh: ['Fresh', 'red'], ongoing: ['Lead (ongoing)', 'amber'], pending_verification: ['In review', 'gray'], paid: ['Paid', 'green'], useless: ["Won't pay", 'cyan'] };
const NX_SEGMENTS = [
  ['all', 'All numbers', () => true],
  ['fresh', 'Fresh', r => r.s === 'fresh'],
  ['ongoing', 'Leads', r => r.s === 'ongoing'],
  ['pending_verification', 'In review', r => r.s === 'pending_verification'],
  ['paid', 'Paid', r => r.s === 'paid'],
  ['useless', "Won't pay", r => r.s === 'useless'],
  ['dead', 'Dead (undeliverable)', r => r.dead === 1],
  ['replied', 'Replied', r => r.r > 0],
  ['silent', 'Never replied', r => r.r === 0 && r.sn > 0],
  ['hot', 'Replied, not paid', r => r.r > 0 && r.s !== 'paid' && r.s !== 'useless'],
  ['hot48', 'Replied in 48h, not paid', r => r.r > 0 && r.s !== 'paid' && r.lr && (Date.now() / 1000 - r.lr) < 172800]
];
async function nxLoad(force) {
  if (nxLoading) return;
  if (!force && nxRows.length && Date.now() - nxLoadedAt < 60000) { nxRender(); return; }
  const body = $('#nxBody');
  if (body) body.innerHTML = emptyRow(9, 'Loading every number…');
  nxLoading = true;
  renderOverview();
  try {
    const data = await api('api/companies.php?action=sadmin_numbers', { credentials: 'same-origin' });
    if (!data.success) throw new Error(data.error || 'Could not load numbers.');
    nxRows = Array.isArray(data.rows) ? data.rows : [];
    nxCompanies = {};
    (data.companies || []).forEach(c => { nxCompanies[c.id] = c.name; });
    nxLoadedAt = Date.now();
    const compSel = $('#nxCompany');
    if (compSel) {
      const cur = compSel.value;
      compSel.innerHTML = '<option value="">All companies</option>' + (data.companies || []).map(c => `<option value="${esc(c.id)}">${esc(c.name)}</option>`).join('');
      compSel.value = cur;
    }
    const owners = {};
    nxRows.forEach(r => { if (r.o) owners[r.o] = r.on || r.o; });
    const ownSel = $('#nxOwner');
    if (ownSel) {
      const cur = ownSel.value;
      ownSel.innerHTML = '<option value="">All closers</option><option value="__none">Unassigned</option>' +
        Object.entries(owners).sort((a, b) => String(a[1]).localeCompare(String(b[1]))).map(([id, n]) => `<option value="${esc(id)}">${esc(n)}</option>`).join('');
      ownSel.value = cur;
    }
    nxRender();
  } catch (err) {
    if (body) body.innerHTML = emptyRow(9, err.message, 'var(--red)');
  } finally {
    nxLoading = false;
    renderOverview();
  }
}
function nxBaseFilter(r) {
  const comp = $('#nxCompany').value || '';
  const owner = $('#nxOwner').value || '';
  const replied = $('#nxReplied').value || '';
  const active = parseInt($('#nxActive').value || '0', 10);
  const delivery = $('#nxDelivery').value || '';
  const q = ($('#nxSearch').value || '').trim().toLowerCase();
  const now = Date.now() / 1000;
  if (comp && r.c !== comp) return false;
  if (owner === '__none' && r.o) return false;
  if (owner && owner !== '__none' && r.o !== owner) return false;
  if (replied === 'yes' && r.r < 1) return false;
  if (replied === 'no' && r.r > 0) return false;
  if (/^\d+$/.test(replied) && r.r < parseInt(replied, 10)) return false;
  if (active > 0 && (!r.la || now - r.la > active * 86400)) return false;
  if (active < 0 && r.la && now - r.la < -active * 86400) return false;
  if (delivery === 'delivered' && r.d < 1) return false;
  if (delivery === 'failed' && r.f < 1) return false;
  if (delivery === 'unsent' && r.sn > 0) return false;
  if (q) {
    const hay = (r.p + ' ' + r.n + ' ' + r.ci + ' ' + r.pl + ' ' + r.it + ' ' + r.on).toLowerCase();
    if (!hay.includes(q.replace(/^\+/, ''))) return false;
  }
  return true;
}
function nxRender() {
  const base = nxRows.filter(nxBaseFilter);
  const chips = $('#nxChips');
  if (chips) chips.innerHTML = NX_SEGMENTS.map(([key, label, fn]) =>
    `<button type="button" class="nx-chip ${key === nxSegment ? 'active' : ''}" data-act="nx-seg" data-seg="${esc(key)}">${esc(label)} <b>${base.filter(fn).length.toLocaleString()}</b></button>`).join('');
  const seg = NX_SEGMENTS.find(s => s[0] === nxSegment) || NX_SEGMENTS[0];
  nxFiltered = base.filter(seg[2]);
  const sort = $('#nxSort').value || 'la';
  const cmp = {
    la: (a, b) => (b.la || 0) - (a.la || 0),
    r: (a, b) => b.r - a.r || (b.la || 0) - (a.la || 0),
    amt: (a, b) => b.amt - a.amt,
    lr: (a, b) => (b.lr || 0) - (a.lr || 0),
    old: (a, b) => (a.la || 0) - (b.la || 0)
  }[sort] || ((a, b) => (b.la || 0) - (a.la || 0));
  nxFiltered.sort(cmp);
  const replied = nxFiltered.filter(r => r.r > 0).length;
  const paid = nxFiltered.filter(r => r.s === 'paid');
  const revenue = paid.reduce((s, r) => s + (r.amt || 0), 0);
  const stats = $('#nxStats');
  if (stats) stats.innerHTML = `
    <span><strong>${nxFiltered.length.toLocaleString()}</strong> numbers</span>
    <span>Reply rate <strong>${nxFiltered.length ? Math.round(replied / nxFiltered.length * 100) : 0}%</strong></span>
    <span>Paid <strong>${paid.length.toLocaleString()}</strong></span>
    <span>Revenue <strong>Rs. ${revenue.toLocaleString()}</strong></span>
    <span>Dead <strong>${nxFiltered.filter(r => r.dead).length.toLocaleString()}</strong></span>`;
  nxLimit = 300;
  nxPaint();
}
function nxAgo(ts) {
  if (!ts) return '<span class="c-dim">—</span>';
  const s = Date.now() / 1000 - ts;
  if (s < 3600) return Math.max(1, Math.round(s / 60)) + 'm ago';
  if (s < 86400) return Math.round(s / 3600) + 'h ago';
  if (s < 86400 * 60) return Math.round(s / 86400) + 'd ago';
  return esc(new Date(ts * 1000).toLocaleDateString());
}
function nxPaint() {
  const body = $('#nxBody');
  if (!body) return;
  if (!nxFiltered.length) {
    body.innerHTML = emptyRow(9, nxRows.length ? 'No numbers match these filters.' : 'No numbers loaded.');
  } else {
    body.innerHTML = nxFiltered.slice(0, nxLimit).map(r => {
      const delivery = r.dead ? '<span class="tag gray">Undeliverable</span>'
        : r.f ? `<span class="tag red">${fmt(r.f)} failed</span>`
        : r.d ? '<span class="tag green">Delivered</span>'
        : r.sn ? '<span class="tag gray">Sent</span>' : '<span class="c-dim">Not messaged</span>';
      const st = NX_STATE[r.s] || [r.s, 'gray'];
      return `<tr>
        ${td('Number', `<b class="mono c-cyan">+${esc(r.p)}</b>`)}
        ${td('Name', `${esc(r.n && !String(r.n).startsWith('+') ? r.n : '')}${r.ci ? `<small>${esc(r.ci)}</small>` : ''}`)}
        ${td('Company', esc(nxCompanies[r.c] || r.c))}
        ${td('Status', `<span class="tag ${st[1]}">${esc(st[0])}</span>`)}
        ${td('Replies / sent', `<span class="mono">${fmt(r.r)} / ${fmt(r.sn)}</span>`)}
        ${td('Delivery', delivery)}
        ${td('Closer', esc(r.on || '—'))}
        ${td('Last activity', `${nxAgo(r.la)}${r.lr ? `<small>reply ${nxAgo(r.lr)}</small>` : ''}`)}
        ${td('Sale', `<span class="mono">${r.amt ? 'Rs. ' + fmt(r.amt) : ''}</span>${r.pl ? `<small>${esc(r.pl)}</small>` : ''}`)}
      </tr>`;
    }).join('');
  }
  const more = $('#nxMore');
  if (more) {
    const left = nxFiltered.length - nxLimit;
    more.hidden = !(left > 0);
    more.textContent = `Show ${Math.min(300, left).toLocaleString()} more (${left.toLocaleString()} hidden)`;
  }
}
function nxShowMore() { nxLimit += 300; nxPaint(); }
function nxSetSegment(key) { nxSegment = key; nxRender(); }
function nxReset() {
  ['nxSearch', 'nxCompany', 'nxOwner', 'nxReplied', 'nxActive', 'nxDelivery'].forEach(id => { const el = document.getElementById(id); if (el) el.value = ''; });
  $('#nxSort').value = 'la';
  nxSegment = 'all';
  nxRender();
}
function nxCopy() {
  const text = nxFiltered.map(r => r.p).join('\n');
  if (!text) { toast('No numbers to copy.', { kind: 'info' }); return; }
  (navigator.clipboard ? navigator.clipboard.writeText(text) : Promise.reject())
    .then(() => toast(`Copied ${nxFiltered.length.toLocaleString()} numbers.`))
    .catch(() => { prompt('Copy these numbers:', text); });
}
function nxDownload(kind) {
  if (!nxFiltered.length) { toast('No numbers to export.', { kind: 'info' }); return; }
  const seg = (NX_SEGMENTS.find(s => s[0] === nxSegment) || NX_SEGMENTS[0])[0];
  let content, type;
  if (kind === 'txt') {
    content = nxFiltered.map(r => r.p).join('\n');
    type = 'text/plain';
  } else {
    const q = v => `"${String(v ?? '').replace(/"/g, '""')}"`;
    const iso = ts => ts ? new Date(ts * 1000).toISOString() : '';
    content = 'Number,Name,Company,Status,Replies,Messages Sent,Delivered,Failed,Dead,Closer,City,Last Activity,Last Reply,Sale Amount,Plan\n' +
      nxFiltered.map(r => [r.p, r.n, nxCompanies[r.c] || r.c, (NX_STATE[r.s] || [r.s])[0], r.r, r.sn, r.d, r.f, r.dead ? 'yes' : '', r.on, r.ci, iso(r.la), iso(r.lr), r.amt || '', r.pl].map(q).join(',')).join('\n');
    type = 'text/csv';
  }
  const blob = new Blob([content], { type: type + ';charset=utf-8' });
  const a = document.createElement('a');
  a.href = URL.createObjectURL(blob);
  a.download = `chatly-numbers-${seg}-${new Date().toISOString().slice(0, 10)}.${kind}`;
  document.body.appendChild(a);
  a.click();
  setTimeout(() => { URL.revokeObjectURL(a.href); a.remove(); }, 500);
}

/* =========================================================
   Fallback number pool
   ========================================================= */
const loadNumberPool = guarded(async function () {
  const tbody = $('#numberPoolTableBody');
  try {
    const data = await api('api/companies.php?action=list_number_profiles');
    if (!data.success || !Array.isArray(data.profiles)) throw new Error(data.error || 'Could not load the pool.');
    S.profiles = data.profiles;
    if (!data.profiles.length) { tbody.innerHTML = emptyRow(7, 'No fallback numbers in the pool yet.'); return; }
    tbody.innerHTML = data.profiles.map(p => {
      const inUse = p.status === 'in_use';
      return `<tr>
        ${td('Profile', `<span class="mono">${esc(p.id)}</span>`)}
        ${td('Phone', `<b class="mono">${esc(p.display || p.phone)}</b>`)}
        ${td('Label', esc(p.label))}
        ${td('Daily capacity', `<span class="mono">${fmt(p.daily_capacity)}</span> msgs/day`)}
        ${td('Assigned to', p.assigned_to_user_name ? `${esc(p.assigned_to_user_name)}<small>${esc(p.assigned_to_user_id)}</small>` : '<em class="c-dim">Available in pool</em>')}
        ${td('Status', `<span class="tag ${inUse ? 'amber' : 'green'}">${esc(p.status)}</span>`)}
        ${td('Actions', p.assigned_to_user_id ? `<button class="btn btn-xs" data-act="release" data-id="${esc(p.id)}">Release</button>` : '<span class="c-dim">Ready</span>', 'acts')}
      </tr>`;
    }).join('');
  } catch (err) {
    if (tbody) tbody.innerHTML = emptyRow(7, 'Failed to load pool: ' + err.message, 'var(--red)');
  }
});
async function handleAddNumber(e) {
  e.preventDefault();
  try {
    const data = await postJSON('api/companies.php', {
      action: 'create_number_profile',
      phone: $('#numPhone').value.trim(),
      label: $('#numLabel').value.trim(),
      daily_capacity: parseInt($('#numCap').value, 10) || 500
    });
    if (data.success) {
      closeModal('addNumberModal');
      $('#addNumberForm').reset();
      toast(data.message || 'Number added to the pool.');
      loadNumberPool();
    } else {
      toast(data.error || 'Failed to add number.', { kind: 'err' });
    }
  } catch (err) {
    toast('Error adding number.', { kind: 'err' });
  }
}
async function releaseNumber(profileId) {
  try {
    const data = await postJSON('api/companies.php', { action: 'release_number_profile', profile_id: profileId });
    if (data.success) { toast(data.message || 'Number released.'); loadNumberPool(); }
    else toast(data.error || 'Could not release number.', { kind: 'err' });
  } catch (err) {
    toast('Error releasing number.', { kind: 'err' });
  }
}

/* =========================================================
   Logins & audit
   ========================================================= */
const loadLoginsData = guarded(async function () {
  const tbody = $('#loginsTableBody');
  try {
    const data = await api('api/auth.php?action=list_logins');
    if (!data.success || !Array.isArray(data.logins)) throw new Error(data.error || 'Could not load logins.');
    S.logins = data.logins;
    renderLogins();
  } catch (err) {
    if (tbody) tbody.innerHTML = emptyRow(6, 'Failed to load login records: ' + err.message, 'var(--red)');
  }
});
const ROLE_TAG = { superadmin: 'gold', admin: 'amber', lead: 'cyan', member: 'green' };
function renderLogins() {
  const tbody = $('#loginsTableBody');
  if (!tbody) return;
  const q = ($('#secSearch').value || '').trim().toLowerCase();
  const list = S.logins.filter(l => !q || [l.name, l.username, l.role, l.company_name, l.company_id, l.ip, l.city, l.device].some(v => String(v || '').toLowerCase().includes(q)));
  if (!list.length) { tbody.innerHTML = emptyRow(6, S.logins.length ? 'No login matches this filter.' : 'No logins recorded yet.'); return; }
  tbody.innerHTML = list.slice(0, 500).map(l => `<tr>
    ${td('User', `<b>${esc(l.name)}</b><small class="mono">@${esc(l.username)}</small>`)}
    ${td('Role', `<span class="tag ${ROLE_TAG[l.role] || 'gray'}">${esc(l.role)}</span>`)}
    ${td('Company', esc(l.company_name || coName(l.company_id)))}
    ${td('IP & location', `<span class="mono">${esc(l.ip)}</span><small>${esc(l.city || '')}</small>`)}
    ${td('Device', `<span class="c-dim">${esc(l.device || '')}</span>`)}
    ${td('Signed in', `<span class="mono">${esc(l.timestamp)}</span>`)}
  </tr>`).join('') + (list.length > 500 ? emptyRow(6, `Showing the latest 500 of ${fmt(list.length)}. Use the filter to narrow down.`) : '');
}
const loadAuditLogs = guarded(async function () {
  const tbody = $('#auditTableBody');
  try {
    const data = await api('api/companies.php?action=get_audit_logs');
    if (!data.success || !Array.isArray(data.logs)) throw new Error(data.error || 'Could not load audit trail.');
    S.audit = data.logs;
    S.auditLoaded = true;
    renderAudit();
    renderOvAudit();
  } catch (err) {
    if (tbody) tbody.innerHTML = emptyRow(5, 'Failed to load audit trail: ' + err.message, 'var(--red)');
  }
});
function renderAudit() {
  const tbody = $('#auditTableBody');
  if (!tbody) return;
  const q = ($('#secSearch').value || '').trim().toLowerCase();
  const list = S.audit.filter(l => !q || [l.timestamp, l.company_id, coName(l.company_id), l.actor_name, l.action, l.details].some(v => String(v || '').toLowerCase().includes(q)));
  if (!list.length) { tbody.innerHTML = emptyRow(5, S.audit.length ? 'No event matches this filter.' : 'No audit events logged yet.'); return; }
  tbody.innerHTML = list.map(l => `<tr>
    ${td('Time', `<span class="mono c-dim">${esc(l.timestamp)}</span>`)}
    ${td('Company', `<b>${esc(l.company_id ? coName(l.company_id) : 'GLOBAL')}</b>${l.company_id ? `<small class="mono">${esc(l.company_id)}</small>` : ''}`)}
    ${td('Actor', esc(l.actor_name || 'System'))}
    ${td('Action', `<span class="tag gold">${esc(l.action)}</span>`)}
    ${td('Details', esc(l.details))}
  </tr>`).join('');
}
function setSecTab(tab) {
  S.secTab = tab === 'audit' ? 'audit' : 'logins';
  $$('[data-act="sec-tab"]').forEach(b => b.classList.toggle('on', b.dataset.tab === S.secTab));
  $('#secLogins').hidden = S.secTab !== 'logins';
  $('#secAudit').hidden = S.secTab !== 'audit';
}

/* =========================================================
   Brand inquiries
   ========================================================= */
let currentBrandInquiries = [];
const loadBrandInquiries = guarded(async function () {
  try {
    const data = await api('api/brand_inquiries.php?action=list');
    if (!data.success || !Array.isArray(data.inquiries)) return;
    currentBrandInquiries = data.inquiries;
    const total = n0(data.total), newCount = n0(data.new_count);
    S.inqStats = { total, new: newCount, contacted: n0(data.contacted_count), converted: n0(data.converted_count) };
    S.inqLoaded = true;
    $('#inqMetricTotal').textContent = fmt(total);
    $('#inqMetricNew').textContent = fmt(newCount);
    $('#inqMetricContacted').textContent = fmt(S.inqStats.contacted);
    $('#inqMetricConverted').textContent = fmt(S.inqStats.converted);
    ['#navBrand', '#bellBadge'].forEach(sel => { const b = $(sel); if (b) { b.textContent = String(newCount); b.hidden = !(newCount > 0); } });
    if (S.lastInquiryTotal !== null && total > S.lastInquiryTotal) triggerNewInquiryNotification(data.inquiries[0]);
    S.lastInquiryTotal = total;
    renderOverview();
    filterBrandInquiriesTable();
  } catch (err) {
    console.error('Error loading brand inquiries:', err);
  }
});
function filterBrandInquiriesTable() {
  const tbody = $('#brandInquiriesTableBody');
  if (!tbody) return;
  // Don't rebuild the table under an open status dropdown.
  if (document.activeElement && tbody.contains(document.activeElement)) return;
  const search = ($('#inqSearchInput').value || '').toLowerCase().trim();
  const filter = $('#inqStatusFilter').value || 'all';
  let list = currentBrandInquiries;
  if (filter !== 'all') list = list.filter(i => (i.status || 'new') === filter);
  if (search) {
    list = list.filter(i => [i.brand_name, i.product_name, i.contact_name].some(v => String(v || '').toLowerCase().includes(search)) || String(i.phone || '').includes(search));
  }
  renderBrandInquiries(list);
}
const INQ_TAG = { new: ['New', 'green'], contacted: ['Contacted', 'amber'], converted: ['Converted', 'cyan'], archived: ['Archived', 'gray'] };
function waLinkFor(inq, short) {
  const phone = String(inq.phone || '').replace(/\D/g, '');
  if (!phone) return '';
  const text = short
    ? `Hello ${inq.contact_name || ''}! This is Chatly Platform Administration regarding your inquiry for "${inq.product_name || ''}".`
    : `Hello ${inq.contact_name || ''}! This is Chatly Platform Administration regarding your inquiry to promote "${inq.product_name || inq.brand_name || ''}". We reviewed your product details and would love to launch your dedicated WhatsApp closer team!`;
  return `https://wa.me/${phone}?text=${encodeURIComponent(text)}`;
}
function renderBrandInquiries(inquiries) {
  const tbody = $('#brandInquiriesTableBody');
  if (!tbody) return;
  if (!inquiries.length) { tbody.innerHTML = emptyRow(6, 'No brand inquiries match this filter.'); return; }
  tbody.innerHTML = inquiries.map(inq => {
    const id = esc(inq.id);
    const wa = waLinkFor(inq, false);
    const web = safeUrl(inq.website);
    const st = INQ_TAG[inq.status || 'new'] || INQ_TAG.new;
    const price = inq.price ? (inq.currency === 'USD' ? '$' + inq.price : 'Rs. ' + inq.price) : 'Custom';
    const desc = String(inq.description || 'No pitch provided');
    const snippet = desc.slice(0, 85) + (desc.length > 85 ? '…' : '');
    return `<tr class="${inq.is_read ? '' : 'unread'}">
      ${td('Brand & product', `<b>${esc(inq.brand_name)}</b><small class="c-green">${esc(inq.product_name)}</small><small>${esc(inq.category || 'E-Commerce')} · ${esc(price)}</small>`)}
      ${td('Pitch', `<span class="clamp">${esc(snippet)}</span><div class="row-acts"><button class="btn btn-xs" data-act="inq-view" data-id="${id}">View pitch</button>${web ? `<a class="lnk" href="${esc(web)}" target="_blank" rel="noopener noreferrer">Store link</a>` : ''}</div><small>Target: ${esc(inq.target_volume || 'Standard')}</small>`)}
      ${td('Contact', `<b>${esc(inq.contact_name)}</b>${wa ? `<a class="lnk mono" href="${esc(wa)}" target="_blank" rel="noopener noreferrer">${esc(inq.phone)}</a>` : `<span class="mono">${esc(inq.phone || '')}</span>`}<small>${esc(inq.email || inq.city || '')}</small>`)}
      ${td('Submitted', `<span class="mono">${esc(inq.created_at || 'Recently')}</span>`)}
      ${td('Status', `<span class="tag ${st[1]}">${esc(st[0])}</span><select class="fc fc-xs" data-change="inq-status" data-id="${id}" aria-label="Change status">${['new', 'contacted', 'converted', 'archived'].map(s => `<option value="${s}"${inq.status === s ? ' selected' : ''}>Set ${s}</option>`).join('')}</select>`)}
      ${td('Actions', `${wa ? `<a class="btn btn-green btn-xs" href="${esc(wa)}" target="_blank" rel="noopener noreferrer">WhatsApp</a>` : ''}<button class="btn btn-gold btn-xs" data-act="inq-convert" data-id="${id}">Convert to company</button><button class="btn btn-danger btn-xs" data-act="inq-delete" data-id="${id}">Delete</button>`, 'acts')}
    </tr>`;
  }).join('');
}
function openInquiryDetailModal(id) {
  const inq = currentBrandInquiries.find(i => String(i.id) === String(id));
  if (!inq) return;
  $('#inqModalBrandTitle').textContent = inq.brand_name || 'Brand';
  $('#inqModalSub').textContent = `Inquiry ID: ${inq.id} • Category: ${inq.category || 'General'}`;
  $('#inqModalProductName').textContent = inq.product_name || '-';
  $('#inqModalPriceCat').textContent = `${inq.currency || 'PKR'} ${inq.price || 'Custom'} (${inq.category || 'Business'})`;
  $('#inqModalPitchText').textContent = inq.description || 'No pitch provided.';
  $('#inqModalVolume').textContent = inq.target_volume || 'Standard';
  const webEl = $('#inqModalWebsiteLink');
  const web = safeUrl(inq.website);
  if (web) webEl.innerHTML = `<a class="lnk" href="${esc(web)}" target="_blank" rel="noopener noreferrer">${esc(inq.website)}</a>`;
  else webEl.textContent = 'None provided';
  $('#inqModalContactName').textContent = `${inq.contact_name || ''} (${inq.phone || ''}) • ${inq.email || ''}`;
  const waBtn = $('#inqModalWAButton');
  const wa = waLinkFor(inq, true);
  waBtn.hidden = !wa;
  if (wa) waBtn.href = wa;
  openModal('inquiryDetailModal');
}
function convertInquiryToCompany(id) {
  const inq = currentBrandInquiries.find(i => String(i.id) === String(id));
  if (!inq) return;
  openProvisionModal();
  $('#compName').value = inq.brand_name || '';
  $('#compSlug').value = String(inq.brand_name || '').toLowerCase().replace(/[^a-z0-9]/g, '');
  $('#compOwnerName').value = inq.contact_name || '';
  if (inq.email) $('#compOwnerEmail').value = inq.email;
  if (inq.phone) $('#compFallbackPhone').value = inq.phone;
  updateInquiryStatus(id, 'converted');
}
async function updateInquiryStatus(id, newStatus) {
  try {
    const data = await postJSON('api/brand_inquiries.php', { action: 'update_status', id: id, status: newStatus });
    if (data.success) { if (document.activeElement) document.activeElement.blur(); loadBrandInquiries(); }
    else toast(data.error || 'Failed to update status.', { kind: 'err' });
  } catch (err) {
    console.error('Error updating inquiry status:', err);
  }
}
async function markAllBrandInquiriesRead() {
  try {
    const data = await postJSON('api/brand_inquiries.php', { action: 'mark_all_read' });
    if (data.success) loadBrandInquiries();
  } catch (err) {}
}
async function deleteInquiry(id) {
  if (!confirm('Are you sure you want to delete this brand promotion inquiry?')) return;
  try {
    const data = await postJSON('api/brand_inquiries.php', { action: 'delete', id: id });
    if (data.success) loadBrandInquiries();
    else toast(data.error || 'Failed to delete inquiry.', { kind: 'err' });
  } catch (err) {
    toast('Error deleting inquiry.', { kind: 'err' });
  }
}
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
function triggerNewInquiryNotification(newestInq) {
  playNotificationChime();
  if (!newestInq) return;
  toast(`"${newestInq.brand_name || 'A brand'}" submitted product "${newestInq.product_name || ''}". Click to review.`, {
    title: 'New brand promotion request', kind: 'info', ms: 7000, onClick: () => showSection('brand')
  });
}

/* =========================================================
   Emails (Chatly Gmail sender -> company owners / admins)
   ========================================================= */
const EM = { aud: 'all', picked: new Set(), preset: 'custom', settings: null, myEmail: '', loaded: false, coSig: '' };
const EM_PRESETS = {
  welcome: ['Welcome to Chatly, {company}',
    'Hello {first_name},\n\nWelcome to Chatly! Your company workspace for {company} is ready.\n\n- Sign in to your admin portal: https://chatly.pk/cc\n- Add your products and prices\n- Approve or invite your sales closers (TMs) and team leaders (TLs)\n- Each closer links their own WhatsApp and starts replying to leads\n\nIf you need anything, just reply to this email.\n\nTeam Chatly'],
  billing: ['Your Chatly plan: renewal reminder for {company}',
    'Hello {first_name},\n\nThis is a friendly reminder that the Chatly plan for {company} is due for renewal.\n\nTo keep your closers sending and your Auto Data running without a break, please complete the payment before the due date. Reply to this email if you need the invoice or the payment details.\n\nThank you for growing with Chatly.\n\nTeam Chatly'],
  feature: ['New on Chatly: (feature name)',
    'Hello {first_name},\n\nWe just released something new for {company}:\n\n- What is new: (describe the feature in one line)\n- Why it helps: (the benefit for your sales team)\n- Where to find it: https://chatly.pk/cc\n\nAs always, reply to this email with feedback or questions.\n\nTeam Chatly'],
  maintenance: ['Scheduled maintenance: Chatly',
    'Hello {first_name},\n\nChatly will have a short maintenance window:\n\n- When: (day, date, time PKT)\n- Expected duration: about 15 minutes\n- Impact: WhatsApp sending may pause briefly. Chats, leads and balances are safe.\n\nNo action is needed from you. Thank you for your patience.\n\nTeam Chatly']
};
const EM_KINDS = {
  test: 'test', auto_company_welcome: 'welcome (auto)', auto_withdrawal_paid: 'payout sent (auto)',
  auto_withdrawal_requested: 'payout request (auto)', auto_approval_tm: 'TM approved (auto)', auto_approval_tl: 'TL approved (auto)'
};
function emKindLabel(k) {
  k = String(k || '');
  if (EM_KINDS[k]) return EM_KINDS[k];
  if (k.indexOf('composer_') === 0) return (k.slice(9).replace(/_/g, ' ') || 'custom');
  return k || 'email';
}
function emKindTone(k) {
  k = String(k || '');
  if (k.indexOf('auto_') === 0) return 'cyan';
  return k === 'test' ? 'gray' : 'violet';
}
function emWhen(t) {
  const d = new Date(t);
  return isNaN(d.getTime()) ? String(t || '') : d.toLocaleString();
}
function emOpen() {
  emRenderCompanies();
  if (!EM.loaded) { EM.loaded = true; loadEmailSettings(); }
  loadEmailLogs();
}
function emApplyPreset(key) {
  const subj = $('#emSubject'), msg = $('#emMessage');
  if (key === 'custom' || !EM_PRESETS[key]) { EM.preset = 'custom'; return; }
  if ((subj.value.trim() || msg.value.trim()) && !confirm('Replace the current subject and message with this template?')) {
    $('#emPreset').value = EM.preset;
    return;
  }
  EM.preset = key;
  subj.value = EM_PRESETS[key][0];
  msg.value = EM_PRESETS[key][1];
}
function emSetAud(aud) {
  EM.aud = aud === 'pick' ? 'pick' : 'all';
  $$('[data-act="em-aud"]').forEach(b => b.classList.toggle('on', b.dataset.aud === EM.aud));
  emRenderCompanies();
}
function emTargets() {
  const all = S.companies || [];
  return EM.aud === 'all' ? all : all.filter(c => EM.picked.has(c.id));
}
function emVisibleCompanies() {
  const q = ($('#emCoSearch').value || '').trim().toLowerCase();
  return (S.companies || []).filter(c => !q || [c.name, c.slug, c.owner_name, c.owner_email, c.id].some(v => String(v || '').toLowerCase().includes(q)));
}
function emRenderCount() {
  const all = S.companies || [];
  const n = emTargets().length;
  $('#emCoCount').textContent = EM.aud === 'pick'
    ? fmt(n) + ' of ' + fmt(all.length) + ' companies selected. Each gets one copy per owner / admin email.'
    : 'All ' + fmt(all.length) + ' companies: every owner email and company admin account gets one copy.';
  $('#emSendHint').textContent = n ? 'to ' + fmt(n) + (n === 1 ? ' company' : ' companies') : '';
}
function emRenderCompanies() {
  const list = $('#emCoList');
  if (!list) return;
  const pick = EM.aud === 'pick';
  $('#emPickTools').hidden = !pick;
  EM.coSig = (S.companies || []).map(c => c.id + ':' + (c.owner_email || '') + ':' + (c.status || '')).join('|');
  if (pick) {
    const rows = emVisibleCompanies();
    list.innerHTML = rows.length ? rows.map(c => {
      const sus = (c.status || 'active') !== 'active';
      return `<label class="em-row"><input type="checkbox" data-em-co="${esc(c.id)}"${EM.picked.has(c.id) ? ' checked' : ''}>` +
        `<span class="em-row-main"><b>${esc(c.name || c.id)}</b><small>${esc(c.owner_email || 'no owner email')}${c.owner_name ? ' · ' + esc(c.owner_name) : ''}</small></span>` +
        (sus ? '<span class="tag red">suspended</span>' : '') + '</label>';
    }).join('') : `<div class="empty-note">${(S.companies || []).length ? 'No companies match.' : 'Loading companies…'}</div>`;
  }
  emRenderCount();
}
function emCompaniesChanged() {
  const sig = (S.companies || []).map(c => c.id + ':' + (c.owner_email || '') + ':' + (c.status || '')).join('|');
  if (sig !== EM.coSig) emRenderCompanies();
}
function emPickShown(on) {
  emVisibleCompanies().forEach(c => { if (on) EM.picked.add(c.id); else EM.picked.delete(c.id); });
  if (!on) EM.picked.clear();
  emRenderCompanies();
}
const emPreview = guarded(async function () {
  const subject = $('#emSubject').value.trim(), message = $('#emMessage').value.trim();
  if (!subject && !message) { toast('Write a subject and a message first.', { kind: 'err' }); return; }
  try {
    const d = await postJSON('api/email_center.php', { action: 'preview', profile: 'chatly', subject: subject, message: message });
    if (!d.success) { toast(d.error || 'Preview failed.', { kind: 'err' }); return; }
    $('#emPreviewSubject').textContent = 'Subject: ' + (d.subject || '');
    $('#emPreviewFrame').srcdoc = d.html || '';
    openModal('emPreviewModal');
  } catch (err) {
    toast(err.message, { kind: 'err' });
  }
});
function emShowResult(r, total) {
  const box = $('#emResult');
  const failed = r.results.filter(x => !x.ok);
  box.hidden = false;
  box.innerHTML =
    (r.error ? `<div class="banner">${esc(r.error)}</div>` : '') +
    (r.note ? `<div class="banner warn">${esc(r.note)}</div>` : '') +
    `<div class="mini-kpis">
      <div class="hl"><span>Sent</span><b class="c-green">${fmt(r.sent)}</b><small>${r.transport === 'mail' ? 'via PHP mail() fallback' : 'via Gmail SMTP'}</small></div>
      <div><span>Failed</span><b class="${r.failed ? 'c-red' : ''}">${fmt(r.failed)}</b><small>${r.failed ? 'reasons below' : 'none'}</small></div>
      <div><span>Companies</span><b>${fmt(r.done)} / ${fmt(total)}</b><small>${r.done < total ? 'stopped early' : 'all processed'}</small></div>
      <div><span>Sender</span><b style="font-size:13px;">${esc(EM.settings ? EM.settings.sender_email : 'Chatly')}</b><small>profile chatly</small></div>
    </div>` +
    (failed.length ? `<ul class="feed">${failed.map(x => `<li><span class="when"><span class="tag red">failed</span></span><span class="what">${esc(x.name || x.to)}<small>${esc(x.to)} · ${esc(x.error || 'not sent')}</small></span></li>`).join('')}</ul>` : '');
}
const emSend = guarded(async function () {
  const subject = $('#emSubject').value.trim(), message = $('#emMessage').value.trim();
  if (!subject || !message) { toast('Write a subject and a message.', { kind: 'err' }); return; }
  const targets = emTargets();
  if (!targets.length) { toast('Choose at least one company.', { kind: 'err' }); return; }
  const from = EM.settings ? EM.settings.sender_email : 'the Chatly sender';
  if (!confirm('Send "' + subject + '" to ' + targets.length + (targets.length === 1 ? ' company' : ' companies') + ' from ' + from + '?')) return;
  const ids = targets.map(c => c.id);
  const agg = { sent: 0, failed: 0, results: [], note: null, transport: null, error: null, done: 0 };
  const btn = $('#emSendBtn');
  const box = $('#emResult');
  btn.disabled = true;
  box.hidden = false;
  try {
    for (let i = 0; i < ids.length; i += 10) {
      const chunk = ids.slice(i, i + 10);
      box.innerHTML = `<div class="empty-note">Sending… ${fmt(i)} of ${fmt(ids.length)} companies done</div>`;
      let d;
      try {
        d = await postJSON('api/email_center.php', { action: 'send_chatly', audience: 'pick', company_ids: chunk, subject: subject, message: message, preset: $('#emPreset').value });
      } catch (err) {
        agg.error = err.message;
        break;
      }
      if (!d.success) { agg.error = d.error || 'Sending failed.'; break; }
      agg.sent += n0(d.sent);
      agg.failed += n0(d.failed);
      (d.results || []).forEach(x => agg.results.push(x));
      if (d.note) agg.note = d.note;
      if (d.transport) agg.transport = d.transport;
      agg.done += chunk.length;
    }
  } finally {
    btn.disabled = false;
  }
  emShowResult(agg, ids.length);
  toast(fmt(agg.sent) + ' sent, ' + fmt(agg.failed) + ' failed.', { title: 'Emails', kind: agg.error || agg.failed ? 'err' : 'ok' });
  loadEmailLogs();
  loadEmailSettings();
});
async function loadEmailLogs() {
  const tb = $('#emLogBody');
  if (!tb) return;
  try {
    const d = await api('api/email_center.php?action=logs&profile=chatly&limit=100');
    if (!d.success) { tb.innerHTML = emptyRow(5, d.error || 'Could not load the send log.', 'var(--red)'); return; }
    const logs = Array.isArray(d.logs) ? d.logs : [];
    tb.innerHTML = logs.length ? logs.map(l => `<tr>
      ${td('Time', `<span class="mono">${esc(emWhen(l.time))}</span>`)}
      ${td('To', `<b>${esc(l.name || l.to)}</b><small>${esc(l.to)}</small>`)}
      ${td('Subject', `<span class="clamp">${esc(l.subject)}</span>`)}
      ${td('Type', `<span class="tag ${emKindTone(l.kind)}">${esc(emKindLabel(l.kind))}</span>`)}
      ${td('Status', l.ok
        ? `<span class="tag green">sent</span><small>${l.transport === 'mail' ? 'PHP mail()' : 'Gmail'}</small>`
        : `<span class="tag red">failed</span><small>${esc(l.error || '')}</small>`)}
    </tr>`).join('') : emptyRow(5, 'Nothing sent from the Chatly sender yet.');
  } catch (err) {
    tb.innerHTML = emptyRow(5, err.message, 'var(--red)');
  }
}
function renderEmailSettings() {
  const s = EM.settings;
  if (!s) return;
  $('#emSetSender').textContent = s.sender_email || '—';
  const tr = $('#emSetTransport');
  tr.textContent = s.app_password_set ? (s.enabled ? 'Gmail SMTP · on' : 'Gmail SMTP · turned off') : 'No App Password: mail() fallback';
  tr.className = s.app_password_set && s.enabled ? 'c-green' : 'c-amber';
  $('#emSetUsage').textContent = fmt(s.sent_last_24h) + ' / ' + fmt(s.daily_cap);
  $('#emSetName').value = s.sender_name || '';
  $('#emSetCap').value = s.daily_cap || 450;
  $('#emSetEnabled').checked = !!s.enabled;
  const pw = $('#emSetPass');
  pw.value = '';
  pw.placeholder = s.app_password_set ? s.app_password_mask + '  (saved; type a new one to replace)' : 'xxxx xxxx xxxx xxxx';
  $('#emSetPassState').textContent = s.app_password_set
    ? 'An App Password is saved' + (s.updated_at ? ' (last change ' + emWhen(s.updated_at) + ')' : '') + '.'
    : 'No App Password saved yet: emails fall back to PHP mail() and will likely land in spam.';
  $('#emTestTo').placeholder = EM.myEmail || 'your email';
}
async function loadEmailSettings() {
  try {
    const d = await api('api/email_center.php?action=settings&profile=chatly');
    if (!d.success) { $('#emSetTransport').textContent = d.error || 'Could not load sender settings.'; return; }
    EM.settings = d.settings;
    EM.myEmail = d.my_email || '';
    renderEmailSettings();
  } catch (err) {
    $('#emSetTransport').textContent = err.message;
  }
}
const emSaveSettings = guarded(async function () {
  const body = {
    action: 'save_settings', profile: 'chatly',
    sender_name: $('#emSetName').value.trim(),
    daily_cap: n0($('#emSetCap').value) || 450,
    enabled: $('#emSetEnabled').checked
  };
  const pw = $('#emSetPass').value.trim();
  if (pw) body.app_password = pw;
  try {
    const d = await postJSON('api/email_center.php', body);
    if (!d.success) { toast(d.error || 'Could not save sender settings.', { kind: 'err' }); return; }
    EM.settings = d.settings;
    renderEmailSettings();
    toast(d.message || 'Saved.', { title: 'Sender settings' });
  } catch (err) {
    toast(err.message, { kind: 'err' });
  }
});
const emClearPass = guarded(async function () {
  if (!confirm('Remove the saved Gmail App Password? Emails will fall back to PHP mail() until a new one is saved.')) return;
  try {
    const d = await postJSON('api/email_center.php', { action: 'save_settings', profile: 'chatly', clear_password: true });
    if (!d.success) { toast(d.error || 'Could not remove the password.', { kind: 'err' }); return; }
    EM.settings = d.settings;
    renderEmailSettings();
    toast('App Password removed.', { title: 'Sender settings' });
  } catch (err) {
    toast(err.message, { kind: 'err' });
  }
});
const emTest = guarded(async function () {
  const btn = $('#emTestBtn');
  btn.disabled = true;
  try {
    const d = await postJSON('api/email_center.php', { action: 'test', profile: 'chatly', to: $('#emTestTo').value.trim() });
    if (d.success) toast((d.message || 'Test email sent.') + (d.note ? ' ' + d.note : ''), { title: 'Test email', ms: 8000, kind: d.note ? 'info' : 'ok' });
    else toast(d.error || 'Test email failed.', { title: 'Test email failed', kind: 'err', ms: 10000 });
  } catch (err) {
    toast(err.message, { kind: 'err' });
  } finally {
    btn.disabled = false;
    loadEmailLogs();
    loadEmailSettings();
  }
});

/* =========================================================
   Modals
   ========================================================= */
function openModal(id) { const m = document.getElementById(id); if (m) m.classList.add('open'); }
function closeModal(id) { const m = document.getElementById(id); if (m) m.classList.remove('open'); }

/* =========================================================
   Events (delegated: no handlers built from API data)
   ========================================================= */
document.addEventListener('click', e => {
  const modal = e.target.classList && e.target.classList.contains('modal') ? e.target : null;
  if (modal) { modal.classList.remove('open'); return; }
  const el = e.target.closest('[data-act]');
  if (!el) return;
  const a = el.dataset.act, id = el.dataset.id || '', name = el.dataset.name || '';
  switch (a) {
    case 'nav': showSection(el.dataset.sec); break;
    case 'refresh': refreshAll(); break;
    case 'provision': openProvisionModal(); break;
    case 'close-modal': { const m = el.closest('.modal'); if (m) m.classList.remove('open'); break; }
    case 'impersonate': impersonateCompanyAdmin(id); break;
    case 'stats': viewCompanyStats(id); break;
    case 'toggle': toggleCompanyStatus(id, name, el.dataset.sus === '1'); break;
    case 'delete-company': deleteCompany(id, name); break;
    case 'ad-save': saveAutoDataLimits(id, el); break;
    case 'ad-limits': {
      // Jump to this company's row in Auto Data limits and put the cursor in "Daily limit".
      showSection('autodata');
      const row = adRow(id);
      if (row) {
        row.scrollIntoView({ block: 'center' });
        row.classList.add('ad-flash');
        setTimeout(() => row.classList.remove('ad-flash'), 1600);
        const inp = row.querySelector('[data-f="daily"]');
        if (inp) inp.focus({ preventScroll: true });
      }
      break;
    }
    case 'ad-refresh': loadUsage(); break;
    case 'approve': masterApproveTM(id, name); break;
    case 'reject': masterRejectTM(id, name); break;
    case 'reload-pending': loadMasterPendingTMs(); break;
    case 'reload-settlement': loadNunnoSettlement(); break;
    case 'reload-invoice': loadNunnoInvoice(); break;
    case 'print-invoice': window.print(); break;
    case 'open-sale': openRecordNunnoSaleModal(); break;
    case 'quick-sale': quickAddNunnoSale(id, name); break;
    case 'nx-refresh': nxLoad(true); break;
    case 'nx-kpi': nxLoad(true); break;
    case 'nx-seg': nxSetSegment(el.dataset.seg); break;
    case 'nx-copy': nxCopy(); break;
    case 'nx-txt': nxDownload('txt'); break;
    case 'nx-csv': nxDownload('csv'); break;
    case 'nx-reset': nxReset(); break;
    case 'nx-more': nxShowMore(); break;
    case 'reload-pool': loadNumberPool(); break;
    case 'open-number': openModal('addNumberModal'); break;
    case 'release': releaseNumber(id); break;
    case 'reload-brand': loadBrandInquiries(); break;
    case 'brand-read': markAllBrandInquiriesRead(); break;
    case 'inq-view': openInquiryDetailModal(id); break;
    case 'inq-convert': convertInquiryToCompany(id); break;
    case 'inq-delete': deleteInquiry(id); break;
    case 'sec-tab': setSecTab(el.dataset.tab); break;
    case 'reload-security': loadLoginsData(); loadAuditLogs(); break;
    case 't-pause': toggleTermPause(); break;
    case 't-clear': T.lines = []; $('#termOut').innerHTML = ''; T.unseen = 0; sysLine('screen cleared'); break;
    case 't-jump': T.stick = true; T.unseen = 0; scrollTermBottom(); updateJump(); break;
    case 'em-aud': emSetAud(el.dataset.aud); break;
    case 'em-all': emPickShown(true); break;
    case 'em-none': emPickShown(false); break;
    case 'em-preview': emPreview(); break;
    case 'em-send': emSend(); break;
    case 'em-logs': loadEmailLogs(); break;
    case 'em-save': emSaveSettings(); break;
    case 'em-clear-pass': emClearPass(); break;
    case 'em-test': emTest(); break;
    case 'uc-start': ucStart(); break;
    case 'uc-pause': ucControl(UC.status === 'paused' ? 'resume' : 'pause'); break;
    case 'uc-stop': ucStop(); break;
    case 'uc-clear': ucControl('clear'); break;
    case 'uc-all': ucPickAll(true); break;
    case 'uc-none': ucPickAll(false); break;
    case 'uc-reload': ucLoadSenders(); ucLoadPool(); break;
  }
});
document.addEventListener('change', e => {
  const el = e.target.closest('[data-change]');
  if (el && el.dataset.change === 'inq-status') updateInquiryStatus(el.dataset.id, el.value);
});
document.addEventListener('keydown', e => {
  if (e.key === 'Escape') $$('.modal.open').forEach(m => m.classList.remove('open'));
  const kpi = e.target.closest && e.target.closest('.kpi[data-act]');
  if (kpi && (e.key === 'Enter' || e.key === ' ')) { e.preventDefault(); showSection(kpi.dataset.sec); }
});
$$('.kpi[data-act]').forEach(k => { k.tabIndex = 0; k.setAttribute('role', 'button'); });
$('#compSearch').addEventListener('input', renderCompanies);
$('#compStatus').addEventListener('change', renderCompanies);
$('#inqSearchInput').addEventListener('input', filterBrandInquiriesTable);
$('#inqStatusFilter').addEventListener('change', filterBrandInquiriesTable);
$('#secSearch').addEventListener('input', () => { renderLogins(); renderAudit(); });
['nxSearch'].forEach(id => $('#' + id).addEventListener('input', nxRender));
['nxCompany', 'nxOwner', 'nxReplied', 'nxActive', 'nxDelivery', 'nxSort'].forEach(id => $('#' + id).addEventListener('change', nxRender));
$('#termScreen').addEventListener('scroll', onTermScroll, { passive: true });
$('#termCo').addEventListener('change', e => { T.co = e.target.value; T.unseen = 0; T.stick = true; renderTerm(); updateJump(); });
$('#termGrep').addEventListener('input', e => { T.grep = e.target.value.trim().toLowerCase(); T.stick = true; renderTerm(); updateJump(); });
$('#emPreset').addEventListener('change', e => emApplyPreset(e.target.value));
$('#emCoSearch').addEventListener('input', emRenderCompanies);
$('#emCoList').addEventListener('change', e => {
  const cb = e.target.closest('input[data-em-co]');
  if (!cb) return;
  if (cb.checked) EM.picked.add(cb.dataset.emCo); else EM.picked.delete(cb.dataset.emCo);
  emRenderCount();
});
$('#adList').addEventListener('input', e => { if (e.target.matches('input[data-f]')) e.target.classList.add('dirty'); });
$('#adList').addEventListener('keydown', e => {
  if (e.key !== 'Enter' || !e.target.matches('input[data-f]')) return;
  const row = e.target.closest('.ad-row');
  const btn = row && row.querySelector('[data-act="ad-save"]');
  if (btn) btn.click();
});

/* =========================================================
   Ultimate campaign (every connected WhatsApp on Chatly)
   ========================================================= */
const UC_API = 'api/ultimate_campaign.php';
const UC = {
  senders: [], picked: new Set(), edited: false, loaded: false,
  auto: true, type: 'text', media: null, pool: null,
  status: 'idle', last: null, starting: false
};
const ucReady = s => s.connected && !s.stopped && !s.plan_locked && !s.limit_reached && !s.busy_in;
function ucPhones(text) {
  const out = new Set();
  String(text || '').split(/[\s,;|]+/).forEach(t => {
    let d = t.replace(/\D/g, '');
    if (d.startsWith('0092')) d = d.slice(2);
    else if (d.startsWith('03') && d.length === 11) d = '92' + d.slice(1);
    else if (d.startsWith('3') && d.length === 10) d = '92' + d;
    if (d.length >= 10 && d.length <= 15) out.add(d);
  });
  return [...out];
}
function ucGapColor(s) {
  if (s <= 3) return ['#b91c1c', 'Very risky'];
  if (s < 10) return ['#ef4444', 'Risky'];
  if (s < 20) return ['#f97316', 'Moderate'];
  if (s < 30) return ['#84cc16', 'Good'];
  return ['#16a34a', 'Safe'];
}
function ucSetGap(v, from) {
  const s = Math.min(60, Math.max(1, parseInt(v, 10) || 10));
  if (from !== 'range') $('#ucGapRange').value = s;
  if (from !== 'box') $('#ucGap').value = s;
  const [c, label] = ucGapColor(s);
  const box = $('#ucGap').closest('.uc-gap');
  box.style.setProperty('--gapc', c);
  $('#ucGapTag').textContent = label;
  $('#ucGapText').textContent = `Each WhatsApp sends one message every ${s} second${s === 1 ? '' : 's'}.` + (s < 10 ? ' Risky: numbers get restricted much faster.' : s >= 30 ? ' Safest for the numbers.' : '');
  ucRenderChecks();
}
function ucGapValue() { return Math.min(60, Math.max(1, parseInt($('#ucGap').value, 10) || 10)); }
function ucSetAuto(on) {
  UC.auto = !!on;
  $('#ucAutoBtn').setAttribute('aria-pressed', UC.auto ? 'true' : 'false');
  $('#ucAutoSub').textContent = UC.auto ? 'On: numbers come from the Chatly number pool' : 'Off: paste or upload your own numbers';
  $('#ucAutoBox').hidden = !UC.auto;
  $('#ucManualBox').hidden = UC.auto;
  ucRenderChecks();
}
function ucSetType(t) {
  UC.type = t;
  UC.media = null;
  $$('#ucTypes [data-uc-type]').forEach(b => b.classList.toggle('on', b.dataset.ucType === t));
  $('#ucMediaBox').hidden = t === 'text';
  const f = $('#ucMediaFile');
  f.value = '';
  f.accept = t === 'audio' ? 'audio/*,.ogg,.opus,.mp3,.m4a' : 'image/*';
  $('#ucMediaLbl').textContent = t === 'audio' ? 'Choose voice note' : 'Choose image';
  $('#ucMediaName').textContent = 'No file chosen';
  ucRenderChecks();
}
function ucCount() {
  return UC.auto ? Math.max(0, parseInt($('#ucCount').value, 10) || 0) : ucPhones($('#ucNumbers').value).length;
}
function ucRenderPreview() {
  const msg = $('#ucMsg').value.trim();
  const p = $('#ucPreview');
  if (!msg) { p.textContent = UC.type === 'audio' ? '🎙️ Voice note' + (UC.media ? ': ' + UC.media.filename : '') : 'Type a message to preview it here.'; return; }
  const filled = msg
    .replace(/\{([^{}]+)\}/g, (m, inner) => {
      const head = inner.split('|')[0].trim().toLowerCase();
      if (head === 'name') return inner.includes('|') ? inner.split('|')[1].trim() : 'Ali';
      if (head === 'phone') return '+92 300 1234567';
      if (head === 'city') return 'Lahore';
      if (head === 'link') return 'https://chatly.pk';
      if (head === 'company') return 'Chatly';
      return inner.includes('|') ? inner.split('|')[0].trim() : inner;
    });
  p.textContent = (UC.type === 'text_image' ? '🖼️ ' + (UC.media ? UC.media.filename : 'image') + '\n' : UC.type === 'audio' ? '🎙️ voice note\n' : '') + filled;
}
function ucRenderChecks() {
  const sel = UC.senders.filter(s => UC.picked.has(s.id) && s.connected);
  const n = ucCount();
  const items = [];
  items.push(n > 0 ? ['ok', UC.auto ? `Auto Data: ${fmt(n)} numbers from the pool` : `${fmt(n)} numbers added`] : ['bad', UC.auto ? 'Enter how many messages to send' : 'Add the numbers (step 1)']);
  const msg = $('#ucMsg').value.trim();
  if (UC.type === 'audio') items.push(UC.media ? ['ok', 'Voice note ready'] : ['bad', 'Upload the voice note (step 2)']);
  else {
    items.push(msg ? ['ok', 'Message written'] : ['bad', 'Write the message (step 2)']);
    if (UC.type === 'text_image') items.push(UC.media ? ['ok', 'Image ready'] : ['bad', 'Upload the image (step 2)']);
  }
  const cos = new Set(sel.map(s => s.company_id));
  items.push(sel.length ? ['ok', `${fmt(sel.length)} WhatsApp${sel.length === 1 ? '' : 's'} from ${fmt(cos.size)} compan${cos.size === 1 ? 'y' : 'ies'}`] : ['bad', 'Tick at least one connected WhatsApp below']);
  if (sel.length && n) items.push(['ok', `About ${fmt(Math.ceil(n / sel.length))} messages each, one every ${ucGapValue()} s`]);
  $('#ucChecks').innerHTML = items.map(([k, t]) => `<li class="${k}">${esc(t)}</li>`).join('');
  $('#ucNumChip').textContent = UC.auto ? `${fmt(n)} Auto` : fmt(n);
  $('#ucKSel').textContent = fmt(sel.length);
  const running = UC.status === 'running' || UC.status === 'paused';
  const ready = items.every(i => i[0] === 'ok');
  const btn = $('#ucStartBtn');
  btn.hidden = running;
  btn.disabled = !ready || UC.starting;
  btn.textContent = UC.starting ? 'Starting…' : '▶ Start ultimate campaign';
  ucRenderPreview();
}
function ucStatusTag(s) {
  if (s.busy_in === 'ultimate') return '<span class="tag red">In this campaign</span>';
  if (!s.connected) return `<span class="tag gray">${esc(s.state === 'not linked' ? 'Not linked' : 'Offline')}</span>`;
  if (s.stopped) return '<span class="tag amber">Stopped by admin</span>';
  if (s.plan_locked) return '<span class="tag amber">Plan not verified</span>';
  if (s.busy_in) return `<span class="tag cyan" title="Already sending this company's campaign">Busy: ${esc(s.busy_in)}</span>`;
  if (s.limit_reached) return '<span class="tag amber">Daily limit reached</span>';
  return '<span class="tag green">Ready</span>';
}
function ucRenderSenders() {
  const body = $('#ucSendBody');
  const q = ($('#ucSearch').value || '').trim().toLowerCase();
  const co = $('#ucCoFilter').value;
  const lanes = new Map(((UC.last && UC.last.lanes) || []).map(l => [String(l.id), l]));
  const list = UC.senders.filter(s => (!co || s.company_id === co) &&
    (!q || [s.name, s.username, s.company_name, s.number].join(' ').toLowerCase().includes(q)));
  const conn = UC.senders.filter(s => s.connected);
  $('#ucKConn').textContent = fmt(conn.length);
  $('#ucKConnSub').textContent = `of ${fmt(UC.senders.length)} closers`;
  $('#ucKCo').textContent = fmt(new Set(conn.map(s => s.company_id)).size);
  $('#ucSendCount').textContent = `${fmt(conn.length)} connected of ${fmt(UC.senders.length)} active closers. Ready ones are ticked; untick any number you want to protect.`;
  if (!UC.senders.length) { body.innerHTML = emptyRow(7, UC.loaded ? 'No active closers on Chatly yet.' : 'Loading…'); return; }
  if (!list.length) { body.innerHTML = emptyRow(7, 'No WhatsApp matches this search.'); return; }
  body.innerHTML = list.map(s => {
    const can = s.connected && !s.busy_in;
    const lane = lanes.get(s.id);
    const laneTxt = lane ? `<span class="uc-lane">${fmt(lane.sent)} / ${fmt(lane.assigned)}</span><small>${esc(lane.status || '')}${lane.failed ? ' · ' + fmt(lane.failed) + ' failed' : ''}</small>` : '<small>—</small>';
    const lim = s.limit > 0 ? ` / ${fmt(s.limit)}` : '';
    return `<tr class="${can ? '' : 'off'}">
      ${td('', `<input type="checkbox" data-uc-pick="${esc(s.id)}" ${UC.picked.has(s.id) ? 'checked' : ''} ${can ? '' : 'disabled'} aria-label="Send from ${esc(s.name)}">`)}
      ${td('Closer', `<b>${esc(s.name)}</b><small>@${esc(s.username)} · ${esc(s.role)}</small>`)}
      ${td('Company', esc(s.company_name))}
      ${td('WhatsApp', `<span class="mono">${s.number ? '+' + esc(s.number) : '—'}</span>`)}
      ${td('Status', ucStatusTag(s))}
      ${td('Today', `<span class="mono">${fmt(s.sent_today)}${lim}</span>`)}
      ${td('In this campaign', laneTxt)}
    </tr>`;
  }).join('');
}
function ucFillCompanies() {
  const sel = $('#ucCoFilter'), cur = sel.value;
  const cos = new Map();
  UC.senders.forEach(s => cos.set(s.company_id, s.company_name));
  sel.innerHTML = '<option value="">All companies</option>' + [...cos].sort((a, b) => a[1].localeCompare(b[1]))
    .map(([id, nm]) => `<option value="${esc(id)}">${esc(nm)}</option>`).join('');
  if (cos.has(cur)) sel.value = cur;
}
const ucLoadSenders = guarded(async function () {
  try {
    const d = await api(UC_API + '?action=senders');
    if (!d.success) { $('#ucSendCount').textContent = d.message || 'Could not load the WhatsApps.'; return; }
    UC.senders = Array.isArray(d.senders) ? d.senders : [];
    UC.loaded = true;
    const byId = new Map(UC.senders.map(s => [s.id, s]));
    if (!UC.edited) UC.picked = new Set(UC.senders.filter(ucReady).map(s => s.id));
    else UC.picked.forEach(id => { const s = byId.get(id); if (!s || !s.connected) UC.picked.delete(id); });
    ucFillCompanies();
    ucRenderSenders();
    ucRenderChecks();
  } catch (err) {
    $('#ucSendCount').textContent = 'Could not load the WhatsApps: ' + err.message;
  }
});
const ucLoadPool = guarded(async function () {
  try {
    const d = await api(UC_API + '?action=auto_status');
    UC.pool = d;
    const el = $('#ucPoolLeft');
    if (d.success) {
      el.innerHTML = `<b>${fmt(d.allowed_now != null ? d.allowed_now : d.remaining)}</b> fresh numbers left for the Ultimate campaign`;
      $('#ucKPool').textContent = fmt(d.remaining);
    } else {
      el.textContent = d.message || 'Auto Data is not available right now.';
      $('#ucKPool').textContent = '—';
    }
  } catch (err) { $('#ucPoolLeft').textContent = 'Could not load the pool: ' + err.message; }
});
function ucApplyStatus(d) {
  if (!d || !d.success) return;
  UC.last = d;
  const st = d.status || 'idle';
  const was = UC.status;
  UC.status = (d.companyId && d.companyId !== 'CHATLY-HQ') ? 'idle' : st;
  const running = UC.status === 'running' || UC.status === 'paused';
  const pill = $('#ucState');
  pill.className = 'uc-state ' + UC.status;
  pill.querySelector('span').textContent = { running: 'Sending', paused: 'Paused', stopped: 'Stopped', completed: 'Completed', idle: 'Idle' }[UC.status] || UC.status;
  $('#navUcLive').hidden = UC.status !== 'running';
  const total = n0(d.totalContacts), sent = n0(d.totalSent), failed = n0(d.totalFailed), done = n0(d.currentIndex);
  const hasRun = total > 0 && UC.status !== 'idle';
  $('#ucRunBox').hidden = !hasRun;
  if (hasRun) {
    $('#ucProgText').textContent = `${fmt(done)} / ${fmt(total)}`;
    $('#ucProgSub').textContent = `${fmt(sent)} sent · ${fmt(failed)} failed · ${fmt((d.lanes || []).length)} WhatsApps`;
    $('#ucProgBar').style.width = (total ? Math.min(100, done / total * 100) : 0).toFixed(1) + '%';
    $('#ucPauseBtn').hidden = !running;
    $('#ucPauseBtn').textContent = UC.status === 'paused' ? '▶ Resume' : 'Pause';
    $('#ucStopBtn').hidden = !running;
    $('#ucClearBtn').hidden = running;
  }
  const logs = Array.isArray(d.logs) ? d.logs : [];
  const box = $('#ucLog');
  const stick = box.scrollTop + box.clientHeight >= box.scrollHeight - 30;
  if (logs.length) {
    box.innerHTML = logs.map(l => `<div class="uc-log-l ${esc(l.type || '')}"><span class="t">${esc(l.time || '')}</span> ${esc(l.message || '')}</div>`).join('');
    if (stick || was !== UC.status) box.scrollTop = box.scrollHeight;
  }
  $('#ucLogCount').textContent = `${fmt(logs.length)} events`;
  if (was !== UC.status && (was === 'running') !== (UC.status === 'running')) ucLoadSenders();
  if (S.section === 'ultimate') ucRenderSenders();
  ucRenderChecks();
}
const ucPoll = guarded(async function () {
  try { ucApplyStatus(await api(UC_API + '?action=status')); } catch (_) {}
});
function ucOpen() {
  ucPoll();
  ucLoadSenders();
  ucLoadPool();
  ucRenderChecks();
}
function ucPickAll(on) {
  UC.edited = true;
  if (on) UC.senders.filter(ucReady).forEach(s => UC.picked.add(s.id));
  else UC.picked.clear();
  ucRenderSenders();
  ucRenderChecks();
}
async function ucStart() {
  const sel = UC.senders.filter(s => UC.picked.has(s.id) && s.connected);
  const n = ucCount();
  if (!sel.length || !n) { ucRenderChecks(); return; }
  const cos = new Set(sel.map(s => s.company_id)).size;
  if (!confirm(`Start the Ultimate campaign?\n\n${fmt(n)} messages from ${fmt(sel.length)} closer WhatsApp${sel.length === 1 ? '' : 's'} in ${fmt(cos)} compan${cos === 1 ? 'y' : 'ies'}, one message every ${ucGapValue()} s per WhatsApp.\n\nReplies go to each closer's own company inbox.`)) return;
  UC.starting = true;
  ucRenderChecks();
  try {
    const body = {
      action: 'start', name: $('#ucName').value.trim() || 'Ultimate campaign',
      message: $('#ucMsg').value, variation: UC.type, media: UC.media,
      delay: ucGapValue(), auto_data: UC.auto, auto_count: UC.auto ? n : 0,
      audience: UC.auto ? [] : ucPhones($('#ucNumbers').value).map(p => ({ phone: p })),
      sender_ids: sel.map(s => s.id)
    };
    const d = await postJSON(UC_API + '?action=start', body);
    if (d.success) {
      toast(d.auto_data_note ? 'Started. ' + d.auto_data_note : `Started: ${fmt(d.totalContacts || n)} numbers across ${fmt((d.lanes || []).length)} WhatsApps.`, { title: 'Ultimate campaign' });
      ucApplyStatus(d);
      ucLoadPool();
      ucLoadSenders();
    } else {
      toast(d.message || 'Could not start the campaign.', { kind: 'err', ms: 7000 });
    }
  } catch (err) {
    toast('Could not start: ' + err.message, { kind: 'err' });
  } finally {
    UC.starting = false;
    ucRenderChecks();
  }
}
async function ucControl(op) {
  try {
    const d = await postJSON(UC_API + '?action=' + op, { action: op });
    if (d && d.success !== false) { ucApplyStatus(d.status ? d : await api(UC_API + '?action=status')); if (op === 'clear') { $('#ucLog').innerHTML = '<div class="uc-log-l dim">Cleared. Ready for the next campaign.</div>'; ucLoadSenders(); } }
    else toast((d && d.message) || 'The bridge did not answer.', { kind: 'err' });
  } catch (err) { toast(err.message, { kind: 'err' }); }
}
function ucStop() {
  // Instant on screen; the bridge stops the lanes at once (persisting happens after it answers).
  const pill = $('#ucState');
  pill.className = 'uc-state stopped';
  pill.querySelector('span').textContent = 'Stopping…';
  $('#ucStopBtn').disabled = true;
  ucControl('stop').finally(() => { $('#ucStopBtn').disabled = false; });
}
function ucReadFile(file, kind) {
  return new Promise((resolve, reject) => {
    if (!file) return resolve(null);
    if (file.size > 10 * 1024 * 1024) return reject(new Error('The file is bigger than 10 MB.'));
    const ok = kind === 'audio' ? /^audio\//.test(file.type) || /\.(ogg|opus|mp3|m4a|wav)$/i.test(file.name) : /^image\//.test(file.type);
    if (!ok) return reject(new Error(kind === 'audio' ? 'Pick an audio file.' : 'Pick an image file.'));
    const r = new FileReader();
    r.onload = () => {
      const s = String(r.result || ''), i = s.indexOf(',');
      resolve({ mimetype: file.type || (kind === 'audio' ? 'audio/ogg' : 'image/jpeg'), data: i >= 0 ? s.slice(i + 1) : s, filename: file.name });
    };
    r.onerror = () => reject(new Error('Could not read the file.'));
    r.readAsDataURL(file);
  });
}
$('#ucAutoBtn').addEventListener('click', () => ucSetAuto(!UC.auto));
$('#ucTypes').addEventListener('click', e => { const b = e.target.closest('[data-uc-type]'); if (b) ucSetType(b.dataset.ucType); });
$('#ucAutoBox').addEventListener('click', e => { const b = e.target.closest('[data-uc-count]'); if (b) { $('#ucCount').value = b.dataset.ucCount; ucRenderChecks(); } });
$('#ucCount').addEventListener('input', ucRenderChecks);
$('#ucNumbers').addEventListener('input', () => { $('#ucNumCount').textContent = `${fmt(ucPhones($('#ucNumbers').value).length)} valid numbers`; ucRenderChecks(); });
$('#ucNumFile').addEventListener('change', e => {
  const f = e.target.files && e.target.files[0];
  if (!f) return;
  const r = new FileReader();
  r.onload = () => {
    const nums = ucPhones(r.result);
    const cur = $('#ucNumbers').value.trim();
    $('#ucNumbers').value = (cur ? cur + '\n' : '') + nums.join('\n');
    $('#ucNumbers').dispatchEvent(new Event('input'));
    toast(`${fmt(nums.length)} numbers added from ${f.name}.`);
    e.target.value = '';
  };
  r.readAsText(f);
});
$('#ucMediaFile').addEventListener('change', async e => {
  const f = e.target.files && e.target.files[0];
  try {
    UC.media = await ucReadFile(f, UC.type === 'audio' ? 'audio' : 'image');
    $('#ucMediaName').textContent = UC.media ? `${UC.media.filename} (${Math.round(f.size / 1024)} KB)` : 'No file chosen';
  } catch (err) {
    UC.media = null; e.target.value = '';
    $('#ucMediaName').textContent = 'No file chosen';
    toast(err.message, { kind: 'err' });
  }
  ucRenderChecks();
});
$('#ucMsg').addEventListener('input', ucRenderChecks);
$('#ucGapRange').addEventListener('input', e => ucSetGap(e.target.value, 'range'));
$('#ucGap').addEventListener('input', e => ucSetGap(e.target.value, 'box'));
$('#ucGap').addEventListener('change', e => ucSetGap(e.target.value));
document.querySelector('.uc-tags').addEventListener('click', e => {
  const b = e.target.closest('[data-uc-tag]');
  if (!b) return;
  const ta = $('#ucMsg'), tag = b.dataset.ucTag;
  const s = ta.selectionStart ?? ta.value.length, en = ta.selectionEnd ?? ta.value.length;
  ta.value = ta.value.slice(0, s) + tag + ta.value.slice(en);
  ta.focus();
  ta.selectionStart = ta.selectionEnd = s + tag.length;
  ucRenderChecks();
});
$('#ucSendBody').addEventListener('change', e => {
  const cb = e.target.closest('input[data-uc-pick]');
  if (!cb) return;
  UC.edited = true;
  if (cb.checked) UC.picked.add(cb.dataset.ucPick); else UC.picked.delete(cb.dataset.ucPick);
  ucRenderChecks();
});
$('#ucSearch').addEventListener('input', ucRenderSenders);
$('#ucCoFilter').addEventListener('change', ucRenderSenders);
ucSetGap(10);

/* =========================================================
   Boot + polling
   ========================================================= */
function refreshAll() {
  refreshCore();
  loadMasterPendingTMs();
  loadBrandInquiries();
  loadNunnoSettlement();
  loadUsage();
  loadAuditLogs();
  pollChat();
  if (S.section === 'numbers') nxLoad(true);
  if (S.section === 'pool') loadNumberPool();
  if (S.section === 'security') loadLoginsData();
}
function every(ms, fn, when) {
  setInterval(() => {
    if (document.hidden) return;
    if (when && !when()) return;
    fn();
  }, ms);
}

sysLine('booting live chat monitor…');
showSection((location.hash || '').replace('#', ''));
refreshAll();

every(10000, refreshCore);
every(15000, loadMasterPendingTMs);
every(8000, loadBrandInquiries);
every(20000, loadNunnoSettlement);
// Chat feed: every 5 s while it is on screen, every 30 s otherwise (keeps the buffer warm).
every(5000, pollChat, () => !T.paused && (S.section === 'chats' || S.section === 'overview' || Date.now() - T.lastPollAt > 30000));
every(30000, loadUsage, () => ['overview', 'autodata', 'companies'].includes(S.section));
every(30000, loadAuditLogs, () => S.section === 'overview' || S.section === 'security');
every(30000, loadLoginsData, () => S.section === 'security');
// Ultimate campaign: live status every 3 s while open (or sending), the WhatsApp list every 15 s.
ucPoll();
every(3000, ucPoll, () => S.section === 'ultimate');
every(20000, ucPoll, () => S.section !== 'ultimate' && UC.status === 'running');
every(15000, ucLoadSenders, () => S.section === 'ultimate');
document.addEventListener('visibilitychange', () => { if (!document.hidden) { refreshCore(); pollChat(); } });
window.addEventListener('hashchange', () => { const h = (location.hash || '').replace('#', ''); if (h && h !== S.section && SECTIONS[h]) showSection(h); });
</script>
<?php endif; ?>
</body>
</html>
