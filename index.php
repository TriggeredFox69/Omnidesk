<?php
/**
 * Nunno WhatsApp Omni-Desk & Multi-Agent Team CRM
 * Single-Number Multi-Closer Architecture with Anti-Theft Lead Locking
 */
$config = require __DIR__ . '/api/config.php';
$templatesJson = json_encode(array_column($config['default_templates'], null, 'id'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover, interactive-widget=resizes-content">
  <title>Nunno WhatsApp Omni-Desk — Multi-Agent CRM & Campaign Hub</title>
  <meta name="description" content="Next-generation multi-agent WhatsApp CRM and campaign desk for Nunno Cloud. Anti-theft lead locking, 3-pane team messenger, Pakistani network classifier, and human cadence outreach.">
  <link rel="icon" type="image/png" href="assets/images/nunno-logo.png">
  <link rel="stylesheet" href="assets/css/whatsapp.css?v=3.0">
  <script>
    window.NUNNO_TEMPLATES = <?= $templatesJson ?>;
  </script>
</head>
<body>

  <!-- Top Navigation Bar -->
  <header class="topbar">
    <div class="brand-wrap">
      <img src="assets/images/nunno-logo.png" alt="Nunno Cloud" class="brand-logo-img">
      <div class="brand-info">
        <div class="brand-heading">
          <span>Nunno</span>
          <span class="brand-badge">Omni-Desk</span>
        </div>
        <span class="text-xs text-muted">Multi-Agent WhatsApp CRM</span>
      </div>
    </div>

    <!-- Main Navigation Tabs -->
    <nav class="nav-tab-bar">
      <button class="nav-tab-btn active tab-wa" id="tabInbox">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg>
        <span>Team Inbox</span>
        <span class="unread-badge" id="unreadGlobalBadge" style="position:static;width:auto;height:auto;padding:1px 6px;font-size:9px;">3</span>
      </button>

      <button class="nav-tab-btn" id="tabCampaign">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
        <span>Campaign Studio</span>
      </button>

    </nav>

    <!-- Top Right Actions, Score, Admin Employees Trigger & Profile -->
    <div style="display:flex;align-items:center;gap:8px;">
      <!-- My Performance Score Trigger -->
      <button class="btn-header-perf" id="openScoreBtn" title="View My Performance Score & Closed Deals">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M12 20v-6M6 20V10M18 20V4"/></svg>
        <span>📈 My Score</span>
      </button>

      <!-- Admin Only: Organization Leaderboard Trigger -->
      <button class="btn-header-perf admin-leaderboard" id="openLeaderboardBtn" style="display:none;" title="Organization Leaderboard & All Users Scores">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M6 9H4.5a2.5 2.5 0 0 1 0-5H6"/><path d="M18 9h1.5a2.5 2.5 0 0 0 0-5H18"/><path d="M4 22h16"/><path d="M10 14.66V17c0 .55-.47.98-.97 1.21C7.85 18.75 7 20.24 7 22"/><path d="M14 14.66V17c0 .55.47.98.97 1.21C16.15 18.75 17 20.24 17 22"/><path d="M18 2H6v7a6 6 0 0 0 12 0V2Z"/></svg>
        <span>🏆 Leaderboard</span>
      </button>

      <!-- Admin Only: Employee Management Trigger -->
      <button class="btn-header-admin" id="openTeamModalBtn" style="display:none;" title="Admin Portal: Create & Manage Employees">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
        <span>👥 Employees</span>
        <span class="badge-role-admin" id="headerEmpCount">2</span>
      </button>

      <!-- Active Logged-in Profile Pill -->
      <div class="agent-profile-pill" id="agentPill" title="Logged in session profile">
        <div class="agent-avatar" id="topAgentAvatar">👑</div>
        <div class="agent-details">
          <span class="agent-name" id="topAgentName">System Administrator</span>
          <span class="badge-admin-crown" id="topAgentId">👑 Admin</span>
        </div>
      </div>

      <!-- Logout Button -->
      <button class="btn-header-logout" id="headerLogoutBtn" title="Lock & Log Out of Desk">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
        <span>Logout</span>
      </button>
    </div>
  </header>

  <!-- Sub-header Live Status & KPI Status Widgets -->
  <div class="subbar">
    <div class="status-indicator-group">
      <!-- Connected Direct Number Pill (Click to Set or View) -->
      <div class="connected-number-pill" id="connectedNumberPill" title="Click to view or set your connected WhatsApp number">
        <div class="pulse-circle online"></div>
        <span class="conn-label">Direct WA:</span>
        <span class="conn-number mono" id="connectedNumberText">Pending Setup (Click to Set)</span>
      </div>

      <!-- Quick KPI Status Widget Pills -->
      <div class="kpi-widget-group">
        <button type="button" class="kpi-stat-pill active" data-filter="all" id="kpiFilterAll">
          <span class="kpi-dot dot-all"></span>
          <span class="kpi-label">All</span>
          <span class="kpi-count" id="kpiCountAll">8</span>
        </button>
        <button type="button" class="kpi-stat-pill kpi-fresh" data-filter="fresh" id="kpiFilterFresh">
          <span class="kpi-dot dot-fresh"></span>
          <span class="kpi-label">🔴 Fresh</span>
          <span class="kpi-count" id="kpiCountFresh">2</span>
        </button>
        <button type="button" class="kpi-stat-pill kpi-ongoing" data-filter="ongoing" id="kpiFilterOngoing">
          <span class="kpi-dot dot-ongoing"></span>
          <span class="kpi-label">🟡 Ongoing</span>
          <span class="kpi-count" id="kpiCountOngoing">3</span>
        </button>
        <button type="button" class="kpi-stat-pill kpi-pending" data-filter="pending_verification" id="kpiFilterPending">
          <span class="kpi-dot dot-pending"></span>
          <span class="kpi-label">⚪ In Review</span>
          <span class="kpi-count" id="kpiCountPending">0</span>
        </button>
        <button type="button" class="kpi-stat-pill kpi-paid" data-filter="paid" id="kpiFilterPaid">
          <span class="kpi-dot dot-paid"></span>
          <span class="kpi-label">🟢 Paid</span>
          <span class="kpi-count" id="kpiCountPaid">2</span>
        </button>
        <button type="button" class="kpi-stat-pill kpi-useless" data-filter="useless" id="kpiFilterUseless">
          <span class="kpi-dot dot-useless"></span>
          <span class="kpi-label">🔵 Won't Pay</span>
          <span class="kpi-count" id="kpiCountUseless">1</span>
        </button>
      </div>
    </div>

    <div class="status-indicator-group">
      <button type="button" class="kpi-stat-pill kpi-mine" data-filter="mine" id="kpiFilterMine">
        <span class="kpi-label">👤 My Leads</span>
        <span class="kpi-count" id="myLeadsCountBadge">2</span>
      </button>
      <div class="status-chip" title="Direct WhatsApp session without Meta Cloud API">
        <span>Engine: <strong class="text-wa">Direct Device</strong></span>
      </div>
      <div class="status-chip">
        <span>Anti-Ban: <strong>4-8s Jitter</strong></span>
      </div>
    </div>
  </div>

  <!-- ====================================================
       VIEW 1: UNIFIED 3-PANE TEAM MESSENGER
       ==================================================== -->
  <main class="inbox-view-container" id="viewInbox">

    <!-- Pane 1: Conversations List -->
    <aside class="conversations-col">
      <div class="conv-header">
        <div class="conv-title-row">
          <span style="font-weight:800;font-size:14px;color:#ffffff;">Live Messages</span>
          <span class="panel-badge mono text-xs" id="convTotalBadge">8 Leads</span>
        </div>

        <input type="text" id="convSearchInput" class="conv-search-input" placeholder="Search by name or phone...">

        <!-- Message Filter Buttons -->
        <div class="inbox-filter-bar">
          <button class="inbox-filter-btn active" data-filter="all">All</button>
          <button class="inbox-filter-btn filter-fresh" data-filter="fresh">🔴 Fresh</button>
          <button class="inbox-filter-btn filter-ongoing" data-filter="ongoing">🟡 Ongoing</button>
          <button class="inbox-filter-btn filter-pending" data-filter="pending_verification">⚪ In Review</button>
          <button class="inbox-filter-btn filter-paid" data-filter="paid">🟢 Paid</button>
          <button class="inbox-filter-btn filter-useless" data-filter="useless">🔵 Won't Pay</button>
          <button class="inbox-filter-btn filter-mine" data-filter="mine">My Leads</button>
        </div>
      </div>

      <div class="conversations-scroll" id="conversationsScroll">
        <!-- Dynamically populated -->
      </div>
    </aside>

    <!-- Pane 2: Active Chat Thread -->
    <section class="chat-thread-col" id="chatThreadCol">
      <div class="chat-header">
        <div class="chat-lead-info">
          <!-- Mobile Return to Conversations Button -->
          <button type="button" class="chat-back-btn" id="chatBackBtn" title="Back to conversations">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="15 18 9 12 15 6"/></svg>
          </button>

          <div class="conv-avatar-wrap" style="width:38px;height:38px;">
            <span class="avatar-initials" id="chatLeadAvatar">ZT</span>
            <div class="lead-status-dot status-ongoing" id="chatLeadDot"></div>
          </div>
          <div>
            <div class="chat-lead-title">
              <span id="chatLeadName">Zeeshan Tariq</span>
              <span id="chatLeadStatusBadge" class="status-pill-clean ongoing">🟡 Ongoing</span>
            </div>
            <div class="chat-lead-meta">
              <span class="mono" id="chatLeadPhone">+92 300 8472910</span>
              <span class="lead-closer-text" id="chatLeadCloser">🔒 Hamza Khan</span>
            </div>
          </div>
        </div>

        <div class="chat-header-actions">
          <!-- Mobile / Tablet CRM Toggle Button -->
          <button type="button" class="btn btn-ghost btn-xs dossier-toggle-btn" id="dossierToggleBtn" title="View Lead CRM Details">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
            <span>CRM</span>
          </button>

          <button class="btn btn-paid btn-xs" id="markPaidHeaderBtn" title="Mark client as Paid (Closed Deal)">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
            <span>Mark Paid</span>
          </button>

          <!-- Admin Transaction Verification Buttons (Shown when deal is pending) -->
          <button class="btn btn-emerald btn-xs" id="verifyPaidHeaderBtn" style="display:none;" title="Verify & approve customer payment (Turn Green)">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
            <span>Verify Payment</span>
          </button>
          <button class="btn btn-rose btn-xs" id="rejectPaidHeaderBtn" style="display:none;" title="Reject fake transaction / revert lead">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            <span>Reject Fake</span>
          </button>

          <!-- Can't Handle / Release to Red Pool Button -->
          <button class="btn btn-warning btn-xs" id="cantHandleBtn" title="Can't handle this client? Release back to Fresh (Red) pool">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M18.36 6.64a9 9 0 1 1-12.73 0"/><line x1="12" y1="2" x2="12" y2="12"/></svg>
            <span>Can't Handle</span>
          </button>

          <!-- Simulate Incoming Client Voice Note (Receive Audio) -->
          <button class="btn btn-secondary btn-xs" id="simClientAudioBtn" title="Simulate incoming customer voice message (Receive Audio)">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"/><path d="M19 10v2a7 7 0 0 1-14 0v-2"/><line x1="12" y1="19" x2="12" y2="23"/><line x1="8" y1="23" x2="16" y2="23"/></svg>
            <span>+ Client Audio</span>
          </button>

          <button class="btn btn-useless btn-xs" id="markUselessHeaderBtn" title="Mark client as Useless / Won't Pay">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
            <span>Won't Pay</span>
          </button>

          <button class="btn btn-claim btn-xs" id="chatClaimBtn" style="display:none;" title="Claim this lead">
            ✋ Take Lead
          </button>

          <button class="btn btn-wa btn-xs btn-wa-native" id="launchNativeWaBtn" title="Launch official WhatsApp Web chat">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.888 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/></svg>
            <span>Open WhatsApp Web</span>
          </button>
        </div>
      </div>

      <!-- Messages Thread Area -->
      <div class="chat-messages-area" id="chatMessagesArea">
        <!-- Dynamically rendered authentic WhatsApp bubbles -->
      </div>

      <!-- Anti-Theft Lock Shield (Shown when ongoing lead is owned by another closer) -->
      <div id="leadLockedShield" class="lead-locked-shield" style="display:none;">
        <div class="lock-shield-card">
          <div class="lock-shield-icon">🔒</div>
          <h3 class="lock-shield-title">Lead In Progress & Protected</h3>
          <p class="lock-shield-desc">This client is currently being handled by <strong id="lockedCloserName">Hamza Khan</strong>.</p>
          <div class="lock-shield-badge">🛡️ Anti-Theft Lead Protection Active</div>
          <p class="lock-shield-sub">Only the assigned closer or the Administrator can view or reply to this conversation.</p>
        </div>
      </div>

      <!-- Slim Conflict Notice (Only shown if admin is inspecting another closer's chat) -->
      <div id="otherCloserNotice" class="other-closer-notice" style="display:none;">
        👑 <strong id="otherCloserName">Hamza Khan</strong> is handling this lead. (Admin Monitoring Mode)
      </div>

      <!-- Hidden file input for audio file upload -->
      <input type="file" id="audioFileInput" accept="audio/*" style="display:none;">

      <!-- Message Composer Bar (Spacious WhatsApp Desktop Experience with Voice Note & Audio) -->
      <div class="chat-composer-bar" id="chatComposerBar">
        <!-- Normal Typing & Audio Action Row -->
        <div id="composerNormalView" class="composer-inner-wrap">
          <button type="button" class="btn-composer-icon" id="audioUploadBtn" title="Upload & Send Audio File (.mp3, .ogg, .wav, .m4a)">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/></svg>
          </button>

          <textarea id="chatInput" class="chat-input" rows="2" placeholder="Type WhatsApp message... (Press Enter to send)"></textarea>

          <button type="button" class="btn-composer-mic" id="voiceRecordBtn" title="Record WhatsApp Voice Note (Click to record)">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"/><path d="M19 10v2a7 7 0 0 1-14 0v-2"/><line x1="12" y1="19" x2="12" y2="23"/><line x1="8" y1="23" x2="16" y2="23"/></svg>
          </button>

          <button class="btn btn-wa" id="sendMsgBtn" title="Send WhatsApp Message">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
            <span>Send</span>
          </button>
        </div>

        <!-- Live Voice Recording Mode -->
        <div id="composerRecordingView" class="composer-recording-wrap" style="display:none;">
          <div class="recording-indicator">
            <span class="recording-pulse-dot"></span>
            <span class="recording-label">Recording Voice Note...</span>
            <span id="recordingTimer" class="recording-timer">0:00</span>
          </div>

          <div class="recording-soundwave">
            <span class="soundbar"></span>
            <span class="soundbar"></span>
            <span class="soundbar"></span>
            <span class="soundbar"></span>
            <span class="soundbar"></span>
            <span class="soundbar"></span>
            <span class="soundbar"></span>
            <span class="soundbar"></span>
          </div>

          <div class="recording-actions">
            <button type="button" class="btn btn-rose btn-sm" id="cancelRecordBtn" title="Discard voice note">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
              <span>Discard</span>
            </button>
            <button type="button" class="btn btn-emerald btn-sm" id="sendRecordBtn" title="Send recorded audio message">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
              <span>Send Audio</span>
            </button>
          </div>
        </div>
      </div>
    </section>

    <!-- Pane 3: Clean Lead CRM Panel (SaaS Sales Intelligence Cockpit) -->
    <aside class="lead-dossier-col" id="leadDossierCol">
      <!-- Drawer Header (visible on mobile / tablet) -->
      <div class="dossier-drawer-header" id="dossierDrawerHeader">
        <div style="display:flex;align-items:center;gap:8px;">
          <div class="widget-icon-pill">⚡</div>
          <span style="font-weight:700;color:#ffffff;font-size:13px;">Lead CRM & Sales Control</span>
        </div>
        <button type="button" class="btn btn-ghost btn-xs" id="closeDossierDrawerBtn" title="Close CRM Panel">✕</button>
      </div>

      <!-- WIDGET 1: Status Matrix & Quick Switcher -->
      <div class="dossier-widget widget-status">
        <div class="widget-header">
          <span class="widget-badge-label">LIFECYCLE STATUS</span>
          <div id="dossierStatusPill" class="status-pill-clean ongoing">🟡 Ongoing</div>
        </div>

        <!-- 5-Button Segmented Status Matrix -->
        <div class="status-matrix-grid">
          <button type="button" class="matrix-btn matrix-fresh" data-set-state="fresh" id="matrixBtnFresh" title="Reset lead to Open Pool (Unclaimed)">
            <span class="matrix-dot dot-fresh"></span>
            <span>🔴 Fresh</span>
          </button>
          <button type="button" class="matrix-btn matrix-ongoing" data-set-state="ongoing" id="matrixBtnOngoing" title="Claim lead for ongoing discussion">
            <span class="matrix-dot dot-ongoing"></span>
            <span>🟡 Ongoing</span>
          </button>
          <button type="button" class="matrix-btn matrix-pending" data-set-state="pending_verification" id="matrixBtnPending" title="Deal submitted, undergoing Admin verification">
            <span class="matrix-dot dot-pending"></span>
            <span>⚪ In Review</span>
          </button>
          <button type="button" class="matrix-btn matrix-paid" data-set-state="paid" id="matrixBtnPaid" title="Client paid and subscription activated">
            <span class="matrix-dot dot-paid"></span>
            <span>🟢 Paid</span>
          </button>
          <button type="button" class="matrix-btn matrix-useless" data-set-state="useless" id="matrixBtnUseless" title="Client won't pay / useless / time-waster">
            <span class="matrix-dot dot-useless"></span>
            <span>🔵 Won't Pay</span>
          </button>
        </div>

        <!-- Plan & Expected Revenue Meter -->
        <div class="deal-value-row">
          <div class="deal-plan-wrap">
            <span class="text-xs text-muted">Tier:</span>
            <span class="lead-plan-badge" id="dossierInterest">Nunno Pro</span>
          </div>
          <div class="deal-val-amount" id="dossierDealValue">PKR 5,000</div>
        </div>
      </div>

      <!-- WIDGET 2: Assigned Closer & Anti-Theft Lock -->
      <div class="dossier-widget widget-agent">
        <div class="widget-header">
          <span class="widget-badge-label">ASSIGNED CLOSER</span>
          <span class="agent-lock-badge" id="dossierLockBadge">🔒 Locked</span>
        </div>

        <div class="closer-profile-card">
          <div class="closer-avatar" id="dossierOwnerAvatar">👨‍💼</div>
          <div class="closer-info">
            <div class="closer-name" id="dossierOwner">Hamza Khan</div>
            <div class="closer-role" id="dossierOwnerRole">Team Member • EMP-101</div>
          </div>
        </div>

        <!-- Claim / Can't Handle Actions -->
        <div style="display:flex;gap:6px;margin-top:8px;">
          <button type="button" class="btn btn-claim btn-sm" id="dossierClaimBtn" style="flex:1;display:none;">✋ Take Lead</button>
          <button type="button" class="btn btn-warning btn-xs" id="dossierReleaseBtn" style="flex:1;display:none;" title="Can't handle this client? Release back to Red pool">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M18.36 6.64a9 9 0 1 1-12.73 0"/><line x1="12" y1="2" x2="12" y2="12"/></svg>
            <span>Can't Handle (Release to Red)</span>
          </button>
        </div>
      </div>

      <!-- WIDGET 3: 1-Click Power Actions -->
      <div class="dossier-widget widget-actions">
        <span class="widget-badge-label">QUICK CONVERSION ACTIONS</span>
        <div style="display:flex;flex-direction:column;gap:6px;margin-top:6px;">
          <!-- Admin Verification Controls (shown when deal is pending verification) -->
          <div id="dossierAdminVerifActions" style="display:none;flex-direction:column;gap:6px;">
            <button type="button" class="btn btn-emerald btn-sm" id="dossierVerifyBtn">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
              <span>Verify Payment (Turn Green)</span>
            </button>
            <button type="button" class="btn btn-rose btn-sm" id="dossierRejectBtn">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
              <span>Reject Fake Sale</span>
            </button>
          </div>

          <!-- Closer Pending Notice (shown when lead is in review) -->
          <div id="dossierPendingNotice" style="display:none;background:#161d28;border:1px solid #2a3942;border-radius:8px;padding:8px 10px;font-size:11.5px;color:#cbd5e1;line-height:1.4;">
            ⚪ <strong>Under Verification:</strong> Payment details submitted. Awaiting Admin verification before 10k target & commission score are credited.
          </div>

          <button type="button" class="btn btn-paid btn-sm" id="dossierPaidBtn">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
            <span>Confirm Client Paid (Closed)</span>
          </button>
          <button type="button" class="btn btn-useless btn-sm" id="dossierUselessBtn">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
            <span>Mark as Useless (Won't Pay)</span>
          </button>
        </div>
      </div>

      <!-- WIDGET 4: Closer Private Sales Notes with Quick Tags -->
      <div class="dossier-widget widget-notes" style="flex:1;min-height:190px;">
        <div class="widget-header">
          <span class="widget-badge-label">PRIVATE CLOSER NOTES</span>
          <span id="notesSavedIndicator" class="text-xs notes-saved">Auto-saved</span>
        </div>

        <!-- Quick Sales Tags -->
        <div class="sales-quick-tags">
          <span class="sales-tag" data-append="Offered 30% discount">+ 30% Offer</span>
          <span class="sales-tag" data-append="Scalping Binance Futures">+ Scalper</span>
          <span class="sales-tag" data-append="EasyPaisa details shared">+ EasyPaisa</span>
          <span class="sales-tag" data-append="Wants free trial only">+ Wants Free</span>
          <span class="sales-tag" data-append="Refused to pay / Inactive">+ Won't Pay</span>
        </div>

        <textarea id="dossierNotes" class="form-textarea dossier-notes-area" placeholder="Type sales intel here..."></textarea>
      </div>
    </aside>

    <!-- Drawer Backdrop for Mobile & Tablet -->
    <div class="dossier-backdrop" id="dossierBackdrop"></div>
  </main>

  <!-- ====================================================
       VIEW 2: OUTREACH QUEUE & CAMPAIGN STUDIO
       ==================================================== -->
  <div class="view-container" id="viewCampaign">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:10px;">
      <div>
        <h2 style="font-size:17px;font-weight:800;color:#ffffff;display:flex;align-items:center;gap:8px;">
          <span>🚀</span>
          <span>WhatsApp Campaign Outreach Studio</span>
        </h2>
        <p class="text-xs text-muted" style="margin-top:2px;">Launch personalized human-cadence outreach campaigns with custom delay and anti-ban jitter intervals.</p>
      </div>
      <div id="campaignStatusBadge" class="panel-badge mono" style="font-size:11.5px;padding:4px 10px;color:#8696a0;background:#182229;border:1px solid #2a3942;">
        ⚪ Ready to Launch
      </div>
    </div>

    <div class="campaign-grid">
      <!-- COLUMN 1: Audience & .txt File Drop Zone -->
      <aside class="panel">
        <div class="panel-header">
          <div class="panel-title">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
            <span>Target Audience & Contacts</span>
          </div>
          <span id="campaignAudienceBadge" class="panel-badge mono">0 Contacts</span>
        </div>

        <!-- 1. Campaign Name -->
        <div class="form-group">
          <label class="form-label" for="campaignNameInput">Campaign Name</label>
          <input type="text" id="campaignNameInput" class="form-input" value="Nunno VIP Outreach" placeholder="e.g. October VIP Scalpers Pitch">
        </div>

        <!-- 2. Drop .txt File Area -->
        <div class="form-group">
          <label class="form-label">Drop Target Numbers (.txt / .csv)</label>
          <input type="file" id="campaignFileInput" accept=".txt,.csv" style="display:none;">
          <div class="campaign-drop-zone" id="campaignDropZone" title="Click or Drag & Drop .txt file here">
            <div class="drop-zone-icon">📥</div>
            <div class="drop-zone-title">Drop your .txt phone list here</div>
            <div class="drop-zone-sub">or <span class="drop-zone-browse">browse files</span> from your computer</div>
            <div class="drop-zone-hint">Format: 03001234567, Ali, Lahore (1 per line). Numbers are auto-sanitized.</div>
          </div>
        </div>

        <!-- 3. Toggle Paste Area -->
        <div>
          <button type="button" class="btn btn-ghost btn-xs" id="togglePasteAreaBtn" style="width:100%;justify-content:center;">
            <span>✏️ Or Paste Phone Numbers Manually</span>
          </button>
          <textarea id="campaignPasteArea" class="form-textarea mono text-xs" style="display:none;min-height:90px;margin-top:6px;" placeholder="03001234567, Ali, Lahore&#10;03214567890, Bilal, Karachi&#10;+923335123984, Usman, Islamabad"></textarea>
          <button type="button" class="btn btn-secondary btn-xs" id="applyPasteNumbersBtn" style="display:none;width:100%;margin-top:4px;">
            <span>Parse & Add Pasted Numbers</span>
          </button>
        </div>

        <!-- 4. Loaded Audience Preview -->
        <div id="campaignAudienceSummary" style="display:none;" class="campaign-audience-summary">
          <span>👥 <strong id="campaignLoadedCountText">0 Numbers</strong> loaded</span>
          <button type="button" class="btn btn-ghost btn-xs" id="clearAudienceBtn" style="color:#ef4444;">Clear</button>
        </div>

        <div id="campaignAudienceList" class="campaign-audience-list" style="display:none;">
          <!-- Dynamically populated contacts -->
        </div>

        <!-- 5. Closer Assignment -->
        <div class="form-group">
          <label class="form-label">Assign Created Leads To</label>
          <select id="campaignAssignSelect" class="form-select form-select-sm">
            <option value="pool">🟢 Open Pool (Available for any closer to claim)</option>
            <option value="mine">🔒 Myself (Lock directly to my profile)</option>
          </select>
        </div>
      </aside>

      <!-- COLUMN 2: Delay Configuration & Detailed Custom Message -->
      <section class="panel">
        <div class="panel-header">
          <div class="panel-title">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
            <span>Message Content & Human Cadence</span>
          </div>
          <span class="panel-badge" style="color:#00a884;">Spintax Enabled</span>
        </div>

        <!-- Delay Interval Configuration -->
        <div class="campaign-delay-card">
          <div style="display:flex;justify-content:space-between;align-items:center;">
            <div style="font-weight:700;font-size:12.5px;color:#e9edef;display:flex;align-items:center;gap:6px;">
              <span>⏱️ Anti-Ban Human Cadence Delay</span>
            </div>
            <select id="campaignPresetSelect" class="form-select form-select-sm" style="width:auto;padding:3px 8px;font-size:11px;">
              <option value="fast">⚡ Fast (3s - 6s)</option>
              <option value="recommended" selected>🛡️ Safe Recommended (6s - 12s)</option>
              <option value="human">👤 Natural Human (12s - 25s)</option>
              <option value="custom">⚙️ Custom Pace</option>
            </select>
          </div>
          <div style="display:flex;gap:10px;margin-top:4px;">
            <div style="flex:1;">
              <label class="text-xs text-muted" style="display:block;margin-bottom:2px;">Min Delay (seconds)</label>
              <input type="number" id="campaignDelayMin" class="form-input form-input-sm" value="6" min="1" max="180">
            </div>
            <div style="flex:1;">
              <label class="text-xs text-muted" style="display:block;margin-bottom:2px;">Max Delay (seconds)</label>
              <input type="number" id="campaignDelayMax" class="form-input form-input-sm" value="12" min="2" max="300">
            </div>
          </div>
        </div>

        <!-- Strategy Template Selector -->
        <div class="form-group">
          <label class="form-label">Pitch Template / Starting Draft</label>
          <select id="campaignPitchPreset" class="form-select form-select-sm">
            <option value="custom">✍️ Custom Message (Write Your Own)</option>
            <option value="pro_liquidation_urdu" selected>💎 Crypto Liquidation Alert (Roman Urdu)</option>
            <option value="starter_scalper">⚡ Nunno Starter (Rs. 1,700 Scalper Signals)</option>
            <option value="flash_discount">🔥 Flash 48-Hour Discount (EasyPaisa / JazzCash)</option>
            <option value="whale_institutional">🐋 Whale Institutional Alpha (VIP Futures)</option>
          </select>
        </div>

        <!-- Personalization Tags -->
        <div class="form-group">
          <label class="form-label" style="display:flex;justify-content:space-between;">
            <span>Personalization Tags (Click to insert)</span>
            <span class="text-xs text-muted">Auto-replaced per client</span>
          </label>
          <div class="tag-chips">
            <span class="tag-chip" data-insert="{name}">{name}</span>
            <span class="tag-chip" data-insert="{phone}">{phone}</span>
            <span class="tag-chip" data-insert="{city}">{city}</span>
            <span class="tag-chip" data-insert="{plan}">{plan}</span>
            <span class="tag-chip" data-insert="{price}">{price}</span>
            <span class="tag-chip" data-insert="{coupon}">{coupon}</span>
            <span class="tag-chip" data-insert="{link}">{link}</span>
          </div>
        </div>

        <!-- Custom Message Textarea -->
        <div class="form-group" style="flex:1;">
          <textarea id="campaignCustomMsg" class="form-textarea" style="min-height:160px;line-height:1.5;font-size:13px;" placeholder="Write your detailed WhatsApp outreach message here...">{Salam|AoA|Hello} {name|Bhai|Trader}!

Kya aap bar bar market ke sudden wick ya stop-loss hunt se liquid ho rahe hain? 📉❌

*Nunno AI Cloud* (`nunno.cloud`) ne Pakistan ke top traders ke liye *Liquidation Magnet System* launch kiya hai jo exact level pehle dikhata hai jahan whales target kar rahi hain.

🔥 *Special Community Offer:*
• Nunno Pro sirf *Rs. 5,000/month*
• EasyPaisa, JazzCash aur USDT accepted
• Coupon `NUNNO30` se *30% OFF*

Live Demo aur Signals dekhne ke liye visit karein: https://nunno.cloud

_Reply STOP agar aap mazeed updates nahi chahte._</textarea>
        </div>
      </section>

      <!-- COLUMN 3: Real-Time Preview, Launch Controls & Live Terminal -->
      <aside class="panel">
        <div class="panel-header">
          <div class="panel-title">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><rect x="5" y="2" width="14" height="20" rx="2" ry="2"/><line x1="12" y1="18" x2="12.01" y2="18"/></svg>
            <span>Live WhatsApp Bubble Preview</span>
          </div>
          <span class="panel-badge mono">Preview</span>
        </div>

        <!-- Live Bubble Simulator -->
        <div style="background:#0b141a;border-radius:12px;padding:14px;border:1px solid #222d34;">
          <div id="campaignBubblePreview" style="background:#005c4b;color:#e9edef;padding:10px 14px;border-radius:8px 8px 2px 8px;font-size:12px;line-height:1.45;white-space:pre-wrap;word-break:break-word;">
            <!-- Live Preview -->
          </div>
        </div>

        <!-- Campaign Launch Controls -->
        <div style="display:flex;flex-direction:column;gap:8px;margin-top:6px;">
          <button type="button" class="btn btn-wa btn-lg" id="startCampaignBtn" style="width:100%;justify-content:center;font-weight:700;">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polygon points="5 3 19 12 5 21 5 3"/></svg>
            <span>Start Outreach Campaign</span>
          </button>

          <div style="display:flex;gap:6px;">
            <button type="button" class="btn btn-secondary btn-sm" id="pauseCampaignBtn" style="flex:1;display:none;">
              <span>⏸️ Pause</span>
            </button>
            <button type="button" class="btn btn-rose btn-sm" id="stopCampaignBtn" style="flex:1;display:none;">
              <span>⏹️ Stop Campaign</span>
            </button>
          </div>
        </div>

        <!-- Live Progress Status -->
        <div id="campaignProgressCard" class="campaign-progress-wrap" style="display:none;margin-top:6px;">
          <div style="display:flex;justify-content:space-between;font-size:11.5px;color:#e9edef;">
            <span>Progress: <strong id="campaignProgressCount">0 / 0</strong></span>
            <span id="campaignProgressPct">0%</span>
          </div>
          <div class="campaign-progress-bar">
            <div id="campaignProgressFill" class="campaign-progress-fill"></div>
          </div>
          <div style="display:flex;justify-content:space-between;font-size:11px;color:#8696a0;">
            <span>Next in: <strong id="campaignCountdown" style="color:#00a884;">--</strong></span>
            <span>Speed: <span id="campaignCurrentPace">--</span></span>
          </div>
        </div>

        <!-- Live Terminal Log -->
        <div class="form-group" style="margin-top:6px;">
          <label class="form-label" style="display:flex;justify-content:space-between;">
            <span>Live Dispatch Log</span>
            <span class="text-xs text-muted" id="campaignLogCount">0 events</span>
          </label>
          <div id="campaignTerminalLog" class="campaign-terminal-log">
            <div class="log-line log-info">System ready. Drop a .txt list or paste numbers to begin outreach.</div>
          </div>
        </div>
      </aside>
    </div>
  </div>


  <!-- ====================================================
       MODAL 1: AGENT SWITCHER & IDENTITY MODAL
       ==================================================== -->
  <div class="modal-overlay" id="agentModal">
    <div class="modal-window">
      <div style="display:flex;justify-content:space-between;align-items:center;">
        <div style="font-size:16px;font-weight:800;color:#ffffff;">Switch Active Closer Identity</div>
        <button class="btn btn-ghost btn-xs" id="closeAgentModalBtn">✕</button>
      </div>
      <p class="text-xs text-muted">Select your employee ID. Once logged in, any lead you claim will be locked exclusively to your ID so other agents cannot conflict or steal it.</p>

      <div style="display:flex;flex-direction:column;gap:8px;" id="agentListContainer">
        <!-- Rendered by JS -->
      </div>
    </div>
  </div>


  <!-- ====================================================
       MODAL 3: CONNECT DIRECT WHATSAPP SENDER NUMBER
       ==================================================== -->
  <div class="modal-overlay" id="connectWaModal">
    <div class="modal-window">
      <div style="display:flex;justify-content:space-between;align-items:center;">
        <div style="display:flex;align-items:center;gap:8px;">
          <span style="font-size:18px;">📱</span>
          <div style="font-size:16px;font-weight:800;color:#ffffff;">Connect Your WhatsApp Number</div>
        </div>
        <button class="btn btn-ghost btn-xs" id="closeConnectWaModalBtn">✕</button>
      </div>
      <p class="text-xs text-muted" style="margin-top:6px;">This software sends cold outreach & CRM chats directly from your connected WhatsApp number with anti-ban human cadence (4-8s randomized delay). No Meta Cloud API or developer verification required.</p>

      <div class="form-group" style="margin-top:12px;">
        <label class="form-label">Enter Your WhatsApp Phone Number</label>
        <input type="text" id="inputConnectedWaNumber" class="form-input mono" placeholder="e.g. 0300 1234567 or +923001234567">
        <span class="text-xs text-dim">You can set your number now or tell me later and I will link it.</span>
      </div>

      <div style="background:var(--surface-2);border:1px solid var(--border);border-radius:var(--r-md);padding:10px 12px;font-size:11.5px;color:var(--text-muted);display:flex;flex-direction:column;gap:5px;margin-top:8px;">
        <div style="display:flex;align-items:center;gap:6px;color:#10b981;font-weight:700;">
          <span>✓</span> <span>Zero Meta API • Direct Session Dispatch</span>
        </div>
        <div>Messages dispatch legitimately through your session so your outbound delivery rate is 100% and immune to Meta API template rejections.</div>
      </div>

      <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:14px;">
        <button class="btn btn-ghost btn-sm" id="cancelConnectWaBtn">Cancel</button>
        <button class="btn btn-wa btn-sm" id="saveConnectedWaBtn">Save & Set Number</button>
      </div>
    </div>
  </div>

  <!-- ====================================================
       AUTHENTICATION WALL / LOGIN PORTAL
       ==================================================== -->
  <div class="auth-overlay" id="authOverlay">
    <div class="auth-card">
      <div class="auth-header">
        <div class="auth-logo-wrap">
          <img src="assets/images/nunno-logo.png" alt="Nunno Cloud">
        </div>
        <h2 class="auth-title">Nunno Omni-Desk</h2>
        <p class="auth-subtitle">Internal Outreach CRM & WhatsApp Hub</p>
        <div class="auth-lock-badge">
          <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
          <span>Authorized Personnel Only • No Public Signups</span>
        </div>
      </div>

      <div class="auth-alert" id="authAlert"></div>

      <form class="auth-form" id="authForm" onsubmit="return false;">
        <div class="auth-field">
          <label for="authUsername">Username or Email</label>
          <div class="auth-input-wrap">
            <svg class="input-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
            <input type="text" id="authUsername" placeholder="e.g. admin or employee" autocomplete="username" required autofocus>
          </div>
        </div>

        <div class="auth-field">
          <label for="authPassword">Password</label>
          <div class="auth-input-wrap">
            <svg class="input-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
            <input type="password" id="authPassword" placeholder="Enter your password" autocomplete="current-password" required>
            <button type="button" class="auth-toggle-pass" id="authTogglePass" title="Toggle Password Visibility">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
            </button>
          </div>
        </div>

        <button type="submit" class="auth-btn-submit" id="authSubmitBtn">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>
          <span>Sign In to Omni-Desk</span>
        </button>
      </form>

      <div class="auth-hint-box">
        <div style="font-weight:700;color:#fff;margin-bottom:4px;display:flex;align-items:center;gap:6px;">
          <span>🔑</span> <span>Master Admin Access</span>
        </div>
        <div>Default Login: <code>admin</code> &bull; Password: <code>Admin@Omnidesk2026!</code></div>
        <div style="margin-top:6px;color:var(--text-muted);font-size:10.5px;">
          ⚠️ <em>Public registration is permanently closed. Only logged-in Administrators can provision employee accounts from inside the desk.</em>
        </div>
      </div>
    </div>
  </div>

  <!-- ====================================================
       MODAL 4: ADMIN EMPLOYEE PROVISIONING & ROSTER
       ==================================================== -->
  <div class="modal-overlay" id="employeeModal">
    <div class="emp-modal-box">
      <div class="emp-modal-header">
        <div class="emp-modal-title-group">
          <div class="emp-modal-icon">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
          </div>
          <div>
            <div style="font-size:17px;font-weight:800;color:#ffffff;display:flex;align-items:center;gap:8px;">
              <span>Team & Employee Provisioning</span>
              <span class="badge-role-admin">👑 Admin Only</span>
            </div>
            <div class="text-xs text-muted">Provision authorized accounts for sales closers. Public signup is disabled.</div>
          </div>
        </div>
        <button class="btn btn-ghost btn-xs" id="closeTeamModalBtn">✕</button>
      </div>

      <div class="emp-modal-body">
        <!-- Panel 1: Provision New Employee Form -->
        <div class="emp-panel">
          <div>
            <h3 class="emp-panel-title">
              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="var(--c-wa)" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
              <span>Add New Employee</span>
            </h3>
            <p class="emp-panel-subtitle">Generate login credentials for your team member</p>
          </div>

          <div class="auth-alert" id="createEmpAlert"></div>

          <form id="createEmpForm" onsubmit="return false;" style="display:flex;flex-direction:column;gap:12px;">
            <div class="form-group" style="margin:0;">
              <label class="form-label">Full Name</label>
              <input type="text" id="newEmpName" class="form-input" placeholder="e.g. Usman Ali" required>
            </div>

            <div class="form-group" style="margin:0;">
              <label class="form-label">Username</label>
              <input type="text" id="newEmpUsername" class="form-input mono" placeholder="e.g. usman" required autocomplete="off">
            </div>

            <div class="form-group" style="margin:0;">
              <div style="display:flex;justify-content:space-between;align-items:center;">
                <label class="form-label">Initial Password</label>
                <button type="button" class="btn btn-ghost btn-xs" id="genEmpPassBtn" style="padding:2px 6px;font-size:10.5px;color:#38bdf8;">🎲 Generate Strong</button>
              </div>
              <input type="text" id="newEmpPassword" class="form-input mono" placeholder="Min 6 characters" required autocomplete="off">
            </div>

            <div class="form-group" style="margin:0;">
              <label class="form-label">Role & Designation</label>
              <select id="newEmpRole" class="form-select">
                <option value="Team Member">👤 Team Member</option>
                <option value="Team Lead">⚡ Team Lead</option>
                <option value="Admin">👑 Admin</option>
              </select>
            </div>

            <div class="form-group" style="margin:0;">
              <label class="form-label">Employee WhatsApp Number (Optional)</label>
              <input type="text" id="newEmpPhone" class="form-input mono" placeholder="e.g. +923001234567">
            </div>

            <button type="submit" class="btn btn-wa btn-sm" id="submitNewEmpBtn" style="margin-top:6px;width:100%;justify-content:center;padding:10px;">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/></svg>
              <span>Provision Employee Account</span>
            </button>
          </form>
        </div>

        <!-- Panel 2: Team Roster List -->
        <div class="emp-panel">
          <div style="display:flex;justify-content:space-between;align-items:center;">
            <div>
              <h3 class="emp-panel-title">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#a855f7" stroke-width="2.2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                <span>Active Team Roster</span>
              </h3>
              <p class="emp-panel-subtitle">Currently provisioned Omni-Desk team members</p>
            </div>
            <button class="btn btn-ghost btn-xs" id="refreshTeamBtn" title="Refresh Roster">🔄 Refresh</button>
          </div>

          <div class="emp-roster-list" id="teamRosterList">
            <!-- Rendered dynamically -->
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- ====================================================
       MODAL 5: CONFIRM PAYMENT & CLOSE DEAL (MINI-POPUP)
       ==================================================== -->
  <div class="modal-overlay" id="markPaidModal">
    <div class="modal-window" style="max-width:480px;">
      <div style="display:flex;justify-content:space-between;align-items:center;">
        <div style="display:flex;align-items:center;gap:8px;">
          <span style="font-size:20px;">🛡️</span>
          <div style="font-size:16px;font-weight:800;color:#ffffff;">Record Sale & Submit for Verification</div>
        </div>
        <button class="btn btn-ghost btn-xs" id="closeMarkPaidModalBtn">✕</button>
      </div>
      <p class="text-xs text-muted" style="margin-top:4px;">Deal will first be placed in <strong>⚪ Grey (In Review)</strong>. Once Admin validates the transaction in the Admin Portal, it turns <strong>🟢 Green</strong> and unlocks your 10k target & commission score.</p>

      <form id="markPaidForm" onsubmit="return false;" style="display:flex;flex-direction:column;gap:12px;margin-top:12px;">
        <div class="form-group" style="margin:0;">
          <label class="form-label">Customer Name</label>
          <input type="text" id="paidCustomerName" class="form-input" placeholder="Client Name" required>
        </div>

        <div class="form-group" style="margin:0;">
          <label class="form-label">Phone Number</label>
          <input type="text" id="paidCustomerPhone" class="form-input mono" disabled style="opacity:0.8;">
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
          <div class="form-group" style="margin:0;">
            <label class="form-label">Plan Purchased</label>
            <select id="paidPlanSelect" class="form-select">
              <option value="Nunno Pro" data-amount="5000">💎 Nunno Pro (Rs. 5,000)</option>
              <option value="Nunno Starter" data-amount="1700">⚡ Nunno Starter (Rs. 1,700)</option>
              <option value="Whale VIP" data-amount="15000">🐋 Whale VIP (Rs. 15,000)</option>
              <option value="Annual Pass" data-amount="35000">👑 Annual Pass (Rs. 35,000)</option>
              <option value="Custom Plan" data-amount="0">💼 Custom Deal</option>
            </select>
          </div>

          <div class="form-group" style="margin:0;">
            <label class="form-label">Amount (PKR)</label>
            <input type="number" id="paidAmountInput" class="form-input mono" value="5000" required>
          </div>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
          <div class="form-group" style="margin:0;">
            <label class="form-label">Payment Method</label>
            <select id="paidMethodSelect" class="form-select">
              <option value="EasyPaisa">💳 EasyPaisa</option>
              <option value="JazzCash">📱 JazzCash</option>
              <option value="Binance Pay (USDT)">🪙 Binance Pay (USDT)</option>
              <option value="Bank Transfer">🏦 Bank Transfer / Raast</option>
              <option value="SadaPay / NayaPay">⚡ SadaPay / NayaPay</option>
            </select>
          </div>

          <div class="form-group" style="margin:0;">
            <label class="form-label">Customer Email (Optional)</label>
            <input type="email" id="paidCustomerEmail" class="form-input" placeholder="client@gmail.com">
          </div>
        </div>

        <div class="form-group" style="margin:0;">
          <label class="form-label">Transaction ID / Trx Ref (Optional)</label>
          <input type="text" id="paidTrxId" class="form-input mono" placeholder="e.g. TID-984729104">
        </div>

        <div style="background:rgba(37,211,102,0.08);border:1px solid rgba(37,211,102,0.2);border-radius:8px;padding:8px 12px;display:flex;align-items:center;justify-content:space-between;font-size:11.5px;">
          <span style="color:var(--text-muted);">Attributed Closer:</span>
          <span style="font-weight:700;color:#10b981;" id="paidCloserName">Hamza Khan</span>
        </div>

        <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:6px;">
          <button type="button" class="btn btn-ghost btn-sm" id="cancelMarkPaidBtn">Cancel</button>
          <button type="submit" class="btn btn-paid btn-sm" id="confirmMarkPaidBtn">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
            <span>Submit Sale for Verification (Grey)</span>
          </button>
        </div>
      </form>
    </div>
  </div>

  <!-- ====================================================
       MODAL 6: CAN'T HANDLE / RELEASE LEAD BACK TO RED
       ==================================================== -->
  <div class="modal-overlay" id="cantHandleModal">
    <div class="modal-window" style="max-width:440px;">
      <div style="display:flex;justify-content:space-between;align-items:center;">
        <div style="display:flex;align-items:center;gap:8px;">
          <span style="font-size:20px;">🚫</span>
          <div style="font-size:16px;font-weight:800;color:#ffffff;">Can't Handle This Lead?</div>
        </div>
        <button class="btn btn-ghost btn-xs" id="closeCantHandleModalBtn">✕</button>
      </div>
      <p class="text-xs text-muted" style="margin-top:4px;">Releasing this lead changes its status back to <strong>🔴 Fresh (Red)</strong> so another sales closer can immediately pick it up.</p>

      <form id="cantHandleForm" onsubmit="return false;" style="display:flex;flex-direction:column;gap:12px;margin-top:12px;">
        <div class="form-group" style="margin:0;">
          <label class="form-label">Client</label>
          <div style="background:var(--surface-2);padding:8px 12px;border-radius:8px;font-size:12px;font-weight:600;color:#fff;" id="cantHandleClientName">Zeeshan Tariq</div>
        </div>

        <div class="form-group" style="margin:0;">
          <label class="form-label">Reason for Handover</label>
          <select id="cantHandleReasonSelect" class="form-select">
            <option value="Language barrier / Speaks regional language">🗣️ Language barrier (Speaks Pashto/Sindhi)</option>
            <option value="Price objection / Demanding higher discount">💰 Price objection / Wants higher discount</option>
            <option value="Unresponsive after pitch / Needs fresh angle">⏳ Unresponsive after pitch / Needs fresh angle</option>
            <option value="Demanding complex technical crypto explanation">📈 Demanding complex technical crypto scalping explanation</option>
            <option value="Too busy right now / Handing off to team">⏱️ Too busy right now / Handing off to team</option>
            <option value="Other objection">📝 Other reason</option>
          </select>
        </div>

        <div class="form-group" style="margin:0;">
          <label class="form-label">Handover Note for Next Closer (Optional)</label>
          <input type="text" id="cantHandleNote" class="form-input" placeholder="e.g. Offered 30% off, client asked for demo video">
        </div>

        <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:6px;">
          <button type="button" class="btn btn-ghost btn-sm" id="cancelCantHandleBtn">Cancel</button>
          <button type="submit" class="btn btn-warning btn-sm" id="confirmCantHandleBtn">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M18.36 6.64a9 9 0 1 1-12.73 0"/><line x1="12" y1="2" x2="12" y2="12"/></svg>
            <span>Release to Fresh (Red) Pool</span>
          </button>
        </div>
      </form>
    </div>
  </div>

  <!-- ====================================================
       MODAL 7: PERFORMANCE PORTAL & TEAM LEADERBOARD
       ==================================================== -->
  <div class="modal-overlay" id="scoreModal">
    <div class="emp-modal-box" style="max-width:920px;">
      <div class="emp-modal-header">
        <div class="emp-modal-title-group">
          <div class="emp-modal-icon" style="background:rgba(234,179,8,0.15);border-color:rgba(234,179,8,0.3);color:#fbbf24;">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><circle cx="12" cy="8" r="7"/><polyline points="8.21 13.89 7 23 12 20 17 23 15.79 13.88"/></svg>
          </div>
          <div>
            <div style="font-size:17px;font-weight:800;color:#ffffff;display:flex;align-items:center;gap:8px;">
              <span id="scoreModalTitle">Performance & Score Center</span>
              <span class="badge-role-admin" id="scoreModalBadge">KPI Tracker</span>
            </div>
            <div class="text-xs text-muted">Real-time revenue metrics, deals won, and team leaderboards</div>
          </div>
        </div>
        <button class="btn btn-ghost btn-xs" id="closeScoreModalBtn">✕</button>
      </div>

      <!-- Performance Tabs (My Score vs Team Leaderboard vs Verify Transactions) -->
      <div style="display:flex;gap:8px;padding:12px 24px;border-bottom:1px solid rgba(255,255,255,0.06);background:rgba(0,0,0,0.2);overflow-x:auto;">
        <button type="button" class="nav-tab-btn active" id="tabMyScore" style="padding:6px 14px;font-size:12px;">
          <span>👤 My Score & Deals</span>
        </button>
        <button type="button" class="nav-tab-btn" id="tabTeamScores" style="padding:6px 14px;font-size:12px;">
          <span>🏆 Team Leaderboard (All Users)</span>
        </button>
        <button type="button" class="nav-tab-btn" id="tabVerifySales" style="padding:6px 14px;font-size:12px;">
          <span>🛡️ Verify Transactions (<span id="pendingVerifCount">0</span>)</span>
        </button>
      </div>

      <div class="emp-modal-body" style="grid-template-columns:1fr;padding:20px 24px;">
        <!-- TAB 1: User Personal Performance Portal -->
        <div id="viewMyScoreTab">
          <!-- User Profile & Designation Card -->
          <div class="perf-hero-card">
            <div class="perf-hero-left">
              <div class="perf-hero-avatar" id="perfHeroAvatar">👤</div>
              <div>
                <div class="perf-hero-name" id="perfHeroName">Hamza Khan</div>
                <div class="perf-hero-role" id="perfHeroRole">Team Member &bull; EMP-101</div>
              </div>
            </div>
            <div class="perf-tier-badge" id="perfTierBadge">
              <span>👤 Team Member</span>
            </div>
          </div>

          <!-- Monthly Task & Salary Target Milestone (10k Sales -> 10k Salary) -->
          <div class="salary-milestone-card">
            <div class="milestone-header">
              <div class="milestone-title-group">
                <div class="milestone-title">🎯 Monthly Task Target: PKR 10,000 Sales</div>
                <div class="milestone-sub">Fulfill PKR 10,000 in customer sales (e.g. 6x Starter, 2x Pro, or 1x Whale) to unlock your PKR 10,000 Base Salary.</div>
              </div>
              <div class="milestone-status-badge" id="milestoneStatusBadge">
                <span id="targetBadgeIcon">⏳</span> <span id="targetBadgeText">Target In Progress</span>
              </div>
            </div>

            <!-- Visual Progress Taskbar -->
            <div class="taskbar-container">
              <div class="taskbar-track">
                <div class="taskbar-fill" id="taskbarFill" style="width: 0%;"></div>
              </div>
              <div class="taskbar-meta">
                <span class="mono" id="taskbarCurrent">Rs. 0</span>
                <span class="mono" id="taskbarPct">0% Completed</span>
                <span class="mono" id="taskbarGoal">Target: Rs. 10,000</span>
              </div>
            </div>

            <!-- Milestone Requirement Feedback Note -->
            <div class="milestone-feedback" id="milestoneFeedback">
              Close Rs. 10,000 more in customer sales to qualify for your Rs. 10,000 Base Salary.
            </div>
          </div>

          <!-- Earnings Breakdown Grid (Base Salary, Commissions, Take-Home, Sales Volume) -->
          <div class="score-cards-grid">
            <div class="score-card salary-card">
              <span class="score-card-label">BASE SALARY</span>
              <span class="score-card-value" id="myBaseSalary">Rs. 0</span>
              <span class="score-card-sub" id="myBaseSalarySub">Requires 10k Target</span>
            </div>
            <div class="score-card comm-card">
              <span class="score-card-label">TASK COMMISSIONS</span>
              <span class="score-card-value" id="myCommissionsEarned">Rs. 0</span>
              <span class="score-card-sub">From post-target sales</span>
            </div>
            <div class="score-card payout-card">
              <span class="score-card-label">TOTAL TAKE-HOME PAY</span>
              <span class="score-card-value" id="myTotalPayout">Rs. 0</span>
              <span class="score-card-sub">Base Salary + Commissions</span>
            </div>
            <div class="score-card sales-card">
              <span class="score-card-label">TOTAL SALES CLOSED</span>
              <span class="score-card-value" id="myTotalRevenue">Rs. 0</span>
              <span class="score-card-sub" id="myDealsWonSub">0 Verified Deals Won</span>
            </div>
          </div>

          <!-- Commission Rate Schedule Banner -->
          <div class="comm-schedule-card">
            <div class="comm-schedule-title">💼 Post-Target Task Commission Schedule (Active once 10k Target is Met)</div>
            <div class="comm-schedule-grid">
              <div class="comm-tier-box">
                <span class="comm-tier-plan">⚡ Starter Plan</span>
                <span class="comm-tier-sale mono">Sale: Rs. 1,700</span>
                <span class="comm-tier-earn mono">➔ Rs. 700 Commission</span>
              </div>
              <div class="comm-tier-box">
                <span class="comm-tier-plan">💎 Pro Plan</span>
                <span class="comm-tier-sale mono">Sale: Rs. 5,000</span>
                <span class="comm-tier-earn mono">➔ Rs. 2,000 Commission</span>
              </div>
              <div class="comm-tier-box">
                <span class="comm-tier-plan">🐋 Whale VIP</span>
                <span class="comm-tier-sale mono">Sale: Rs. 15,000</span>
                <span class="comm-tier-earn mono">➔ Rs. 10,000 Commission</span>
              </div>
            </div>
          </div>

          <!-- Closed Deals & Commission Audit Ledger -->
          <div class="perf-section-card">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
              <h4 style="margin:0;font-size:13.5px;color:#fff;font-weight:700;">Deals & Task Commission Ledger</h4>
              <span class="text-xs text-muted" id="myDealsCountLabel">0 Closed</span>
            </div>
            <div id="myDealsList" class="perf-deals-table">
              <!-- Dynamically populated -->
            </div>
          </div>
        </div>

        <!-- TAB 2: Admin Organization Leaderboard -->
        <div id="viewTeamScoresTab" style="display:none;">
          <!-- Organization KPI Summary -->
          <div class="org-summary-grid">
            <div class="org-stat-box">
              <span class="org-stat-label">TOTAL COMPANY SALES</span>
              <span class="org-stat-val" id="orgTotalRevenue">Rs. 0</span>
            </div>
            <div class="org-stat-box">
              <span class="org-stat-label">TOTAL CLOSED DEALS</span>
              <span class="org-stat-val" id="orgTotalDeals">0</span>
            </div>
            <div class="org-stat-box">
              <span class="org-stat-label">SALARIES QUALIFIED</span>
              <span class="org-stat-val" id="orgSalariesUnlocked">0 Qualified</span>
            </div>
            <div class="org-stat-box">
              <span class="org-stat-label">COMMISSIONS PAID</span>
              <span class="org-stat-val" id="orgCommissionsTotal">Rs. 0</span>
            </div>
          </div>

          <!-- Team Leaderboard Table -->
          <div class="perf-section-card" style="margin-top:16px;">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;">
              <h4 style="margin:0;font-size:14px;color:#fff;font-weight:700;display:flex;align-items:center;gap:6px;">
                <span>🏆 Team Performance, Target & Commission Leaderboard</span>
              </h4>
              <button type="button" class="btn btn-ghost btn-xs" id="exportLeaderboardBtn">📊 Export Payroll & Audit CSV</button>
            </div>
            <div id="teamLeaderboardTable">
              <!-- Dynamically populated -->
            </div>
          </div>
        </div>

        <!-- TAB 3: Admin Transaction Audit & Verification Portal (Anti-Fake Sale Shield) -->
        <div id="viewVerifySalesTab" style="display:none;">
          <div class="perf-hero-card" style="margin-bottom:16px;">
            <div class="perf-hero-left">
              <div class="perf-hero-avatar" style="background:#1e293b;border-color:#38bdf8;color:#38bdf8;">🛡️</div>
              <div>
                <div class="perf-hero-name">Anti-Fraud Transaction Audit Portal</div>
                <div class="perf-hero-role">Audit submitted payments before confirming deals, 10k target milestone credit, & task commissions</div>
              </div>
            </div>
            <div class="perf-tier-badge" style="background:#1e293b;border-color:#38bdf8;color:#38bdf8;">
              <span id="pendingAuditHeaderCount">0 Pending Deals</span>
            </div>
          </div>

          <div id="verifySalesList" style="display:flex;flex-direction:column;gap:12px;">
            <!-- Dynamically populated -->
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Toast Notification -->
  <div class="toast" id="toast">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--c-wa)" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
    <span id="toastMsg">Ready</span>
  </div>

  <script src="assets/js/whatsapp-app.js?v=3.0"></script>
</body>
</html>
