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
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
  <title>Nunno Omni-Desk — WhatsApp CRM & Campaign Hub</title>
  <meta name="description" content="Premium multi-agent WhatsApp CRM and campaign studio for Nunno Cloud. Anti-theft lead locking, glassmorphism UI, real-time team inbox.">
  <link rel="icon" type="image/png" href="assets/images/nunno-logo.png">
  <link rel="stylesheet" href="assets/css/whatsapp.css?v=5.6">
  <script>
    window.NUNNO_TEMPLATES = <?= $templatesJson ?>;
    window.NUNNO_CONFIG = {
      connected_whatsapp_number: <?= json_encode($config['connected_whatsapp_number'] ?? '+923046588806') ?>,
      sender_engine: <?= json_encode($config['sender_engine'] ?? []) ?>
    };
  </script>
</head>
<body>

  <!-- ════════════════════════════════════════════════════════
       TOP NAVIGATION BAR
  ════════════════════════════════════════════════════════ -->
  <header class="topbar">
    <div class="brand-wrap">
      <img src="assets/images/nunno-logo.png" alt="Nunno Cloud" class="brand-logo-img">
      <div class="brand-info">
        <div class="brand-heading">
          <span>Nunno</span>
          <span class="brand-badge">Omni-Desk</span>
        </div>
        <span class="text-xs">Multi-Agent WhatsApp CRM</span>
      </div>
    </div>

    <!-- Main Navigation Tabs -->
    <nav class="nav-tab-bar">
      <button class="nav-tab-btn active tab-wa" id="tabInbox">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg>
        <span>Team Inbox</span>
        <span class="unread-badge" id="unreadGlobalBadge">3</span>
      </button>

      <button class="nav-tab-btn" id="tabCampaign">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
        <span>Campaign Studio</span>
      </button>

      <button class="nav-tab-btn employee-home-tab" id="tabEmployee" style="display:none;">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="3"/><path d="M8 8h8M8 12h8M8 16h4"/></svg>
        <span>My workspace</span>
      </button>
    </nav>

    <!-- Top Right Actions -->
    <div style="display:flex;align-items:center;gap:6px;">
      <button class="btn-header-perf" id="openScoreBtn" title="View My Performance Score & Closed Deals">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M12 20v-6M6 20V10M18 20V4"/></svg>
        <span>My Score</span>
      </button>

      <button class="btn-header-admin" id="openTeamModalBtn" style="display:none;" title="Admin Portal: Manage Employees">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
        <span>Employees</span>
        <span class="badge-role-admin" id="headerEmpCount">2</span>
      </button>

      <button type="button" class="btn-header-theme" id="themeToggleBtn" title="Switch to dark theme" aria-label="Switch to dark theme" aria-pressed="false">
        <svg class="theme-icon" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M20.2 15.2A8.5 8.5 0 0 1 8.8 3.8 8.5 8.5 0 1 0 20.2 15.2Z"/></svg>
        <span>Dark theme</span>
      </button>

      <button class="btn-header-logout" id="headerLogoutBtn" title="Logout">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
        <span>Logout</span>
      </button>
    </div>
  </header>

  <!-- ════════════════════════════════════════════════════════
       SUBBAR — LIVE STATUS & KPI WIDGETS
  ════════════════════════════════════════════════════════ -->
  <div class="subbar">
    <div class="status-indicator-group">
      <div class="connected-number-pill" id="connectedNumberPill" title="Click to view or change your connected WhatsApp number">
        <div class="pulse-circle online"></div>
        <span class="conn-label">Direct WA:</span>
        <span class="conn-number mono" id="connectedNumberText">+92 304 6588806</span>
      </div>
      <div class="kpi-widget-group">
        <button type="button" class="kpi-stat-pill active" data-filter="all" id="kpiFilterAll">
          <span class="kpi-dot dot-all"></span>
          <span class="kpi-label">All</span>
          <span class="kpi-count" id="kpiCountAll">8</span>
        </button>
        <button type="button" class="kpi-stat-pill" data-filter="fresh" id="kpiFilterFresh">
          <span class="kpi-dot dot-fresh"></span>
          <span class="kpi-label">Fresh</span>
          <span class="kpi-count" id="kpiCountFresh">2</span>
        </button>
        <button type="button" class="kpi-stat-pill" data-filter="ongoing" id="kpiFilterOngoing">
          <span class="kpi-dot dot-ongoing"></span>
          <span class="kpi-label">Ongoing</span>
          <span class="kpi-count" id="kpiCountOngoing">3</span>
        </button>
        <button type="button" class="kpi-stat-pill" data-filter="pending_verification" id="kpiFilterPending">
          <span class="kpi-dot dot-pending"></span>
          <span class="kpi-label">In Review</span>
          <span class="kpi-count" id="kpiCountPending">0</span>
        </button>
        <button type="button" class="kpi-stat-pill" data-filter="paid" id="kpiFilterPaid">
          <span class="kpi-dot dot-paid"></span>
          <span class="kpi-label">Paid</span>
          <span class="kpi-count" id="kpiCountPaid">2</span>
        </button>
        <button type="button" class="kpi-stat-pill" data-filter="useless" id="kpiFilterUseless">
          <span class="kpi-dot dot-useless"></span>
          <span class="kpi-label">Won't Pay</span>
          <span class="kpi-count" id="kpiCountUseless">1</span>
        </button>
      </div>
    </div>

    <div class="status-indicator-group">
      <button type="button" class="kpi-stat-pill" data-filter="mine" id="kpiFilterMine">
        <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
        <span class="kpi-label">My Leads</span>
        <span class="kpi-count" id="myLeadsCountBadge">2</span>
      </button>
      <div class="status-chip">Engine: <strong>Direct Device</strong></div>
      <div class="status-chip">Anti-Ban: <strong style="color:var(--cyan);">4-8s Jitter</strong></div>
    </div>
  </div>

  <!-- ════════════════════════════════════════════════════════
       VIEW 1: UNIFIED 3-PANE TEAM MESSENGER
  ════════════════════════════════════════════════════════ -->
  <main class="inbox-view-container" id="viewInbox">

    <!-- Pane 1: Conversations List -->
    <aside class="conversations-col">
      <div class="conv-header">
        <div class="conv-title-row">
          <span>All chats</span>
          <span class="panel-badge" id="convTotalBadge">8 Leads</span>
        </div>
        <input type="text" id="convSearchInput" class="conv-search-input" placeholder="Search by name or phone…">
        <div class="inbox-filter-bar">
          <button class="inbox-filter-btn active" data-filter="all">All</button>
          <button class="inbox-filter-btn filter-fresh" data-filter="fresh">🔴 Fresh</button>
          <button class="inbox-filter-btn filter-ongoing" data-filter="ongoing">🟡 Ongoing</button>
          <button class="inbox-filter-btn filter-pending" data-filter="pending_verification">⚪ Review</button>
          <button class="inbox-filter-btn filter-paid" data-filter="paid">🟢 Paid</button>
          <button class="inbox-filter-btn filter-useless" data-filter="useless">🔵 Won't Pay</button>
          <button class="inbox-filter-btn filter-mine" data-filter="mine">👤 Mine</button>
        </div>
      </div>
      <div class="conversations-scroll" id="conversationsScroll"></div>
    </aside>

    <!-- Pane 2: Active Chat Thread -->
    <section class="chat-thread-col" id="chatThreadCol" style="position:relative;">
      <div class="chat-transition-overlay" id="chatTransitionOverlay" aria-hidden="true"><span class="chat-transition-mark">N</span><span class="chat-transition-spinner"></span><span>Opening conversation</span></div>
      <div class="chat-header">
        <div class="chat-lead-info">
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
              <span class="mono text-xs" id="chatLeadPhone">+92 300 8472910</span>
              <span class="lead-closer-text" id="chatLeadCloser">🔒 Hamza Khan</span>
            </div>
          </div>
        </div>

        <div class="chat-header-actions">
          <button class="btn btn-claim btn-xs" id="chatClaimBtn" style="display:none;">✋ Take Lead</button>

          <button class="btn btn-paid btn-xs" id="markPaidHeaderBtn" title="Mark client as Paid">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
            <span>Mark Paid</span>
          </button>

          <button class="btn btn-emerald btn-xs" id="verifyPaidHeaderBtn" style="display:none;" title="Verify & approve payment">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
            <span>Verify Payment</span>
          </button>
          <button class="btn btn-rose btn-xs" id="rejectPaidHeaderBtn" style="display:none;" title="Reject fake transaction">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            <span>Reject Fake</span>
          </button>

          <button class="btn btn-warning btn-xs" id="cantHandleBtn" title="Can't handle — release lead">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M18.36 6.64a9 9 0 1 1-12.73 0"/><line x1="12" y1="2" x2="12" y2="12"/></svg>
            <span>Can't Handle</span>
          </button>

          <button class="btn btn-useless btn-xs" id="markUselessHeaderBtn" title="Mark as Won't Pay">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
            <span>Won't Pay</span>
          </button>

          <button class="btn btn-secondary btn-xs" id="simClientAudioBtn" title="Simulate incoming voice message">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"/><path d="M19 10v2a7 7 0 0 1-14 0v-2"/><line x1="12" y1="19" x2="12" y2="23"/><line x1="8" y1="23" x2="16" y2="23"/></svg>
            <span>+ Audio</span>
          </button>

          <div class="header-action-divider"></div>

          <button type="button" class="btn btn-crm-toggle" id="dossierToggleBtn" title="Open / Close Lead CRM Panel" aria-expanded="false">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
            <span>Lead CRM</span>
            <svg class="crm-toggle-chevron" width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 12 15 18 9"/></svg>
          </button>
        </div>
      </div>

      <!-- Other closer notice -->
      <div id="otherCloserNotice" class="other-closer-notice" style="display:none;">
        👑 <strong id="otherCloserName">Hamza Khan</strong> is handling this lead. (Admin Monitoring Mode)
      </div>

      <!-- Messages Thread -->
      <div class="chat-messages-area" id="chatMessagesArea"></div>

      <!-- Anti-Theft Shield -->
      <div id="leadLockedShield" class="lead-locked-shield" style="display:none;">
        <div class="lock-shield-card">
          <div class="lock-shield-icon">🔒</div>
          <h3 class="lock-shield-title">Lead In Progress & Protected</h3>
          <p class="lock-shield-desc">This client is currently being handled by <strong id="lockedCloserName">Hamza Khan</strong>.</p>
          <div class="lock-shield-badge">🛡️ Anti-Theft Lead Protection Active</div>
          <p class="lock-shield-sub">Only the assigned closer or the Administrator can view or reply.</p>
        </div>
      </div>

      <!-- Hidden file input for audio -->
      <input type="file" id="audioFileInput" accept="audio/*" style="display:none;">

      <!-- Message Composer -->
      <div class="chat-composer-bar" id="chatComposerBar">
        <div id="composerNormalView" class="composer-inner-wrap">
          <button type="button" class="btn-composer-icon" id="audioUploadBtn" title="Upload & Send Audio File">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/></svg>
          </button>

          <textarea id="chatInput" class="chat-input" rows="2" placeholder="Write a message…"></textarea>

          <button type="button" class="btn-composer-mic" id="voiceRecordBtn" title="Record voice note">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"/><path d="M19 10v2a7 7 0 0 1-14 0v-2"/><line x1="12" y1="19" x2="12" y2="23"/><line x1="8" y1="23" x2="16" y2="23"/></svg>
          </button>

          <button class="btn btn-wa" id="sendMsgBtn" title="Send Message">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
            <span>Send</span>
          </button>
        </div>

        <!-- Recording View -->
        <div id="composerRecordingView" class="composer-recording-wrap" style="display:none;">
          <div class="recording-indicator">
            <span class="recording-pulse-dot"></span>
            <span class="recording-label">Recording…</span>
            <span id="recordingTimer" class="recording-timer">0:00</span>
          </div>
          <div class="recording-soundwave">
            <span class="soundbar"></span><span class="soundbar"></span>
            <span class="soundbar"></span><span class="soundbar"></span>
            <span class="soundbar"></span><span class="soundbar"></span>
            <span class="soundbar"></span><span class="soundbar"></span>
          </div>
          <div class="recording-actions">
            <button type="button" class="btn btn-rose btn-sm" id="cancelRecordBtn">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
              <span>Discard</span>
            </button>
            <button type="button" class="btn btn-emerald btn-sm" id="sendRecordBtn">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
              <span>Send Audio</span>
            </button>
          </div>
        </div>
      </div>
    </section>

    <!-- Pane 3: Lead CRM Dossier -->
    <aside class="lead-dossier-col" id="leadDossierCol">
      <div class="dossier-drawer-header" id="dossierDrawerHeader">
        <div class="dossier-drawer-title">
          <div class="widget-icon-pill">⚡</div>
          <span>Lead CRM & Sales Control</span>
        </div>
        <button type="button" class="dossier-close-btn" id="closeDossierDrawerBtn" title="Close CRM Panel (Esc)">✕</button>
      </div>

      <!-- WIDGET 1: Status Matrix -->
      <div class="dossier-widget widget-status">
        <div class="widget-header">
          <span class="widget-badge-label">Lifecycle Status</span>
          <div id="dossierStatusPill" class="status-pill-clean ongoing">🟡 Ongoing</div>
        </div>
        <div class="status-matrix-grid">
          <button type="button" class="matrix-btn matrix-fresh" data-set-state="fresh" id="matrixBtnFresh">
            <span class="matrix-dot dot-fresh"></span><span>Fresh</span>
          </button>
          <button type="button" class="matrix-btn matrix-ongoing" data-set-state="ongoing" id="matrixBtnOngoing">
            <span class="matrix-dot dot-ongoing"></span><span>Ongoing</span>
          </button>
          <button type="button" class="matrix-btn matrix-pending" data-set-state="pending_verification" id="matrixBtnPending">
            <span class="matrix-dot dot-pending"></span><span>Review</span>
          </button>
          <button type="button" class="matrix-btn matrix-paid" data-set-state="paid" id="matrixBtnPaid">
            <span class="matrix-dot dot-paid"></span><span>Paid</span>
          </button>
          <button type="button" class="matrix-btn matrix-useless" data-set-state="useless" id="matrixBtnUseless">
            <span class="matrix-dot dot-useless"></span><span>Won't pay</span>
          </button>
        </div>
        <div class="deal-value-row">
          <div class="deal-plan-wrap">
            <span class="text-xs text-muted">Tier:</span>
            <span class="lead-plan-badge" id="dossierInterest">Nunno Pro</span>
          </div>
          <div class="deal-val-amount" id="dossierDealValue">PKR 5,000</div>
        </div>
      </div>

      <!-- WIDGET 2: Assigned Closer -->
      <div class="dossier-widget widget-agent">
        <div class="widget-header">
          <span class="widget-badge-label">Assigned Closer</span>
          <span class="agent-lock-badge" id="dossierLockBadge">🔒 Locked</span>
        </div>
        <div class="closer-profile-card">
          <div class="closer-avatar" id="dossierOwnerAvatar">👨‍💼</div>
          <div class="closer-info">
            <div class="closer-name" id="dossierOwner">Hamza Khan</div>
            <div class="closer-role" id="dossierOwnerRole">Team Member • EMP-101</div>
          </div>
        </div>
        <div style="display:flex;gap:6px;margin-top:8px;">
          <button type="button" class="btn btn-claim btn-sm" id="dossierClaimBtn" style="flex:1;display:none;">✋ Take Lead</button>
          <button type="button" class="btn btn-warning btn-xs" id="dossierReleaseBtn" style="flex:1;display:none;">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M18.36 6.64a9 9 0 1 1-12.73 0"/><line x1="12" y1="2" x2="12" y2="12"/></svg>
            <span>Can't Handle</span>
          </button>
        </div>
      </div>

      <!-- WIDGET 3: Quick Conversion Actions -->
      <div class="dossier-widget widget-actions">
        <span class="widget-badge-label">Quick Conversion Actions</span>
        <div style="display:flex;flex-direction:column;gap:6px;margin-top:6px;">
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
          <div id="dossierPendingNotice" style="display:none;background:rgba(107,114,128,0.06);border:1px solid rgba(107,114,128,0.2);border-radius:8px;padding:8px 10px;font-size:11.5px;color:#9ca3af;line-height:1.4;">
            ⚪ <strong>Under Verification:</strong> Payment submitted. Awaiting Admin confirmation.
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

      <!-- WIDGET 4: Private Notes -->
      <div class="dossier-widget widget-notes" style="flex:1;min-height:190px;">
        <div class="widget-header">
          <span class="widget-badge-label">Private Closer Notes</span>
          <span id="notesSavedIndicator" class="text-xs notes-saved">Auto-saved</span>
        </div>
        <div class="sales-quick-tags">
          <span class="sales-tag" data-append="Offered 30% discount">+ 30% Offer</span>
          <span class="sales-tag" data-append="Scalping Binance Futures">+ Scalper</span>
          <span class="sales-tag" data-append="EasyPaisa details shared">+ EasyPaisa</span>
          <span class="sales-tag" data-append="Wants free trial only">+ Wants Free</span>
          <span class="sales-tag" data-append="Refused to pay / Inactive">+ Won't Pay</span>
        </div>
        <textarea id="dossierNotes" class="dossier-notes-area" placeholder="Type sales intel here…"></textarea>
      </div>
    </aside>

    <!-- Drawer Backdrop -->
    <div class="dossier-backdrop" id="dossierBackdrop"></div>
  </main>

  <!-- ════════════════════════════════════════════════════════
       VIEW 2: CAMPAIGN STUDIO
  ════════════════════════════════════════════════════════ -->
  <div class="view-container" id="viewCampaign">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;flex-wrap:wrap;gap:10px;">
      <div>
        <h2 style="font-size:20px;font-weight:800;display:flex;align-items:center;gap:10px;">
          <span style="font-size:22px;">🚀</span>
          <span class="grad-text">WhatsApp Campaign Outreach Studio</span>
        </h2>
        <p class="text-xs text-muted" style="margin-top:4px;">Launch personalized human-cadence outreach campaigns with anti-ban jitter delays.</p>
      </div>
      <div id="campaignStatusBadge" class="panel-badge">⚪ Ready to Launch</div>
    </div>

    <div class="campaign-grid">
      <!-- COL 1: Audience -->
      <aside class="panel">
        <div class="panel-header">
          <div class="panel-title">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
            <span>Target Audience</span>
          </div>
          <span id="campaignAudienceBadge" class="panel-badge">0 Contacts</span>
        </div>

        <div class="form-group" style="margin:0;">
          <label class="form-label" for="campaignNameInput">Campaign Name</label>
          <input type="text" id="campaignNameInput" class="form-input" value="Nunno VIP Outreach" placeholder="e.g. October VIP Scalpers Pitch">
        </div>

        <div class="form-group" style="margin:0;">
          <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;">
            <label class="form-label" style="margin:0;">Drop Phone Numbers (.txt / .csv)</label>
            <button type="button" class="btn btn-ghost btn-xs" id="quickAddMyNumberBtn" style="color:var(--cyan);font-size:10px;padding:2px 7px;">⚡ Load My #</button>
          </div>
          <input type="file" id="campaignFileInput" accept=".txt,.csv" style="display:none;">
          <div class="campaign-drop-zone" id="campaignDropZone">
            <div class="drop-zone-icon">📥</div>
            <div class="drop-zone-title">Drop your .txt phone list here</div>
            <div class="drop-zone-sub">or <span class="drop-zone-browse">browse files</span></div>
            <div class="drop-zone-hint">Format: 03001234567, Ali, Lahore (1 per line)</div>
          </div>
        </div>

        <div>
          <button type="button" class="btn btn-ghost btn-xs" id="togglePasteAreaBtn" style="width:100%;justify-content:center;">
            ✏️ Or Paste Phone Numbers Manually
          </button>
          <textarea id="campaignPasteArea" class="form-textarea mono text-xs" style="display:none;min-height:80px;margin-top:6px;" placeholder="03001234567, Ali, Lahore&#10;03214567890, Bilal, Karachi"></textarea>
          <button type="button" class="btn btn-secondary btn-xs" id="applyPasteNumbersBtn" style="display:none;width:100%;margin-top:4px;justify-content:center;">Parse & Add Numbers</button>
        </div>

        <div id="campaignAudienceSummary" style="display:none;" class="campaign-audience-summary">
          <span>👥 <strong id="campaignLoadedCountText">0 Numbers</strong> loaded</span>
          <button type="button" class="btn btn-ghost btn-xs" id="clearAudienceBtn" style="color:var(--red);">Clear</button>
        </div>
        <div id="campaignAudienceList" class="campaign-audience-list" style="display:none;"></div>

        <div class="form-group" style="margin:0;">
          <label class="form-label">Assign Created Leads To</label>
          <select id="campaignAssignSelect" class="form-select form-select-sm">
            <option value="pool">🟢 Open Pool (Any closer can claim)</option>
            <option value="mine">🔒 Myself (Lock to my profile)</option>
          </select>
        </div>
      </aside>

      <!-- COL 2: Message Content -->
      <section class="panel">
        <div class="panel-header">
          <div class="panel-title">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
            <span>Message & Human Cadence</span>
          </div>
          <span class="panel-badge" style="color:var(--cyan);">Spintax Enabled</span>
        </div>

        <div class="campaign-delay-card">
          <div style="display:flex;justify-content:space-between;align-items:center;">
            <span style="font-weight:700;font-size:12px;color:var(--text-secondary);">⏱️ Anti-Ban Human Cadence Delay</span>
            <select id="campaignPresetSelect" class="form-select form-select-sm" style="width:auto;padding:3px 8px;font-size:11px;">
              <option value="fast" selected>⚡ Fast (3s – 6s)</option>
              <option value="recommended">🛡️ Safe (6s – 12s)</option>
              <option value="human">👤 Natural (12s – 25s)</option>
              <option value="custom">⚙️ Custom</option>
            </select>
          </div>
          <div style="display:flex;gap:10px;margin-top:8px;">
            <div style="flex:1;">
              <label class="text-xs text-muted" style="display:block;margin-bottom:2px;">Min Delay (s)</label>
              <input type="number" id="campaignDelayMin" class="form-input form-input-sm" value="3" min="1" max="180">
            </div>
            <div style="flex:1;">
              <label class="text-xs text-muted" style="display:block;margin-bottom:2px;">Max Delay (s)</label>
              <input type="number" id="campaignDelayMax" class="form-input form-input-sm" value="6" min="2" max="300">
            </div>
          </div>
        </div>

        <div class="campaign-dispatch-card">
          <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:6px;">
            <div style="font-weight:700;font-size:12px;color:var(--text-secondary);display:flex;align-items:center;gap:6px;">
              <span>⚡ Autonomous Background Outreach Engine</span>
              <span class="badge-role-admin" style="font-size:9.5px;padding:1px 6px;">100% Automated</span>
            </div>
            <span class="mono text-xs" style="color:var(--cyan);">Sender: +92 304 6588806</span>
          </div>
          <p class="text-xs text-muted" style="margin:5px 0 0;line-height:1.4;">
            Messages are dispatched automatically in the background with anti-ban jitter delays. No browser redirects or manual clicking required.
          </p>
        </div>

        <div class="form-group" style="margin:0;">
          <label class="form-label">Pitch Template</label>
          <select id="campaignPitchPreset" class="form-select form-select-sm">
            <option value="custom">✍️ Custom Message (Write Your Own)</option>
            <option value="pro_liquidation_urdu" selected>💎 Crypto Liquidation Alert (Roman Urdu)</option>
            <option value="starter_scalper">⚡ Nunno Starter (Rs. 1,700 Scalper)</option>
            <option value="flash_discount">🔥 Flash 48-Hour Discount</option>
            <option value="whale_institutional">🐋 Whale Institutional Alpha</option>
          </select>
        </div>

        <div class="form-group" style="margin:0;">
          <label class="form-label" style="display:flex;justify-content:space-between;">
            <span>Personalization Tags</span>
            <span class="text-xs text-muted">Click to insert</span>
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

        <div class="form-group" style="margin:0;flex:1;">
          <textarea id="campaignCustomMsg" class="form-textarea" style="min-height:150px;font-size:12.5px;" placeholder="Write your WhatsApp outreach message…">{Salam|AoA|Hello} {name|Bhai|Trader}!

Kya aap bar bar market ke sudden wick ya stop-loss hunt se liquid ho rahe hain? 📉❌

*Nunno AI Cloud* (`nunno.cloud`) ne Pakistan ke top traders ke liye *Liquidation Magnet System* launch kiya hai jo exact level pehle dikhata hai jahan whales target kar rahi hain.

🔥 *Special Community Offer:*
• Nunno Pro sirf *Rs. 5,000/month*
• EasyPaisa, JazzCash aur USDT accepted
• Coupon `NUNNO30` se *30% OFF*

Live Demo: https://nunno.cloud

_Reply STOP agar aap mazeed updates nahi chahte._</textarea>
        </div>
      </section>

      <!-- COL 3: Preview & Launch -->
      <aside class="panel">
        <div class="panel-header">
          <div class="panel-title">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><rect x="5" y="2" width="14" height="20" rx="2" ry="2"/><line x1="12" y1="18" x2="12.01" y2="18"/></svg>
            <span>Live Message Preview</span>
          </div>
          <span class="panel-badge">Preview</span>
        </div>

        <div class="campaign-preview-stage" aria-label="WhatsApp message preview">
          <div id="campaignBubblePreview" class="campaign-message-bubble"></div>
        </div>

        <div style="display:flex;flex-direction:column;gap:8px;">
          <button type="button" class="btn btn-wa btn-lg" id="startCampaignBtn" style="width:100%;justify-content:center;font-weight:700;">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polygon points="5 3 19 12 5 21 5 3"/></svg>
            <span>Start Outreach Campaign</span>
          </button>
          <div style="display:flex;gap:6px;">
            <button type="button" class="btn btn-secondary btn-sm" id="pauseCampaignBtn" style="flex:1;display:none;">⏸️ Pause</button>
            <button type="button" class="btn btn-rose btn-sm" id="stopCampaignBtn" style="flex:1;display:none;">⏹️ Stop</button>
          </div>
        </div>

        <div id="campaignProgressCard" class="campaign-progress-wrap" style="display:none;">
          <div style="display:flex;justify-content:space-between;font-size:11.5px;color:var(--text-secondary);">
            <span>Progress: <strong id="campaignProgressCount">0 / 0</strong></span>
            <span id="campaignProgressPct">0%</span>
          </div>
          <div class="campaign-progress-bar">
            <div id="campaignProgressFill" class="campaign-progress-fill"></div>
          </div>
          <div style="display:flex;justify-content:space-between;font-size:11px;color:var(--text-muted);">
            <span>Next in: <strong id="campaignCountdown" style="color:var(--cyan);">--</strong></span>
            <span>Speed: <span id="campaignCurrentPace">--</span></span>
          </div>
        </div>

        <div class="form-group" style="margin:0;">
          <label class="form-label" style="display:flex;justify-content:space-between;">
            <span>Live Dispatch Log</span>
            <span class="text-xs text-muted" id="campaignLogCount">0 events</span>
          </label>
          <div id="campaignTerminalLog" class="campaign-terminal-log">
            <div class="log-line log-info">System ready. Drop a .txt list or paste numbers to begin.</div>
          </div>
        </div>
      </aside>
    </div>
  </div>

  <main class="employee-workspace" id="viewEmployee" style="display:none;">
    <section class="employee-welcome">
      <div>
        <span class="workspace-eyebrow">YOUR SALES DESK</span>
        <h1>Good to see you, <span id="employeeWelcomeName">there</span></h1>
        <p>Your leads, follow-ups and progress, all in one place.</p>
      </div>
      <button type="button" class="btn btn-wa" id="employeeOpenInboxBtn">Open team inbox <span aria-hidden="true">↗</span></button>
    </section>
    <section class="employee-metrics" aria-label="Your performance">
      <article class="employee-metric"><span>My leads</span><strong id="employeeMetricLeads">0</strong><small>Assigned to you</small></article>
      <article class="employee-metric"><span>Needs follow-up</span><strong id="employeeMetricFollowups">0</strong><small>Open conversations</small></article>
      <article class="employee-metric"><span>Awaiting review</span><strong id="employeeMetricReview">0</strong><small>Payments submitted</small></article>
      <article class="employee-metric"><span>Verified sales</span><strong id="employeeMetricRevenue">PKR 0</strong><small id="employeeMetricDeals">0 closed deals</small></article>
    </section>
    <section class="employee-board">
      <div class="employee-board-heading"><div><span class="workspace-eyebrow">PIPELINE</span><h2>Your leads</h2></div><button class="employee-text-link" type="button" id="employeeViewScoreBtn">View my score ↗</button></div>
      <div class="employee-lead-list" id="employeeLeadList"></div>
    </section>
    <aside class="employee-portal-note"><span class="employee-note-mark">✳</span><p><strong>A good conversation starts with listening.</strong><br>Reply in the team inbox, save a private note, and update a lead’s status as it progresses.</p></aside>
  </main>

  <!-- ════════════════════════════════════════════════════════
       MODAL 1: AGENT SWITCHER
  ════════════════════════════════════════════════════════ -->
  <div class="modal-overlay" id="agentModal">
    <div class="modal-window">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;">
        <div style="font-size:16px;font-weight:800;color:#fff;">Switch Active Closer Identity</div>
        <button class="btn btn-ghost btn-xs" id="closeAgentModalBtn">✕</button>
      </div>
      <p class="text-xs text-muted" style="margin-bottom:16px;">Select your employee ID. Leads you claim lock exclusively to your ID — anti-theft protected.</p>
      <div style="display:flex;flex-direction:column;gap:8px;" id="agentListContainer"></div>
    </div>
  </div>

  <!-- ════════════════════════════════════════════════════════
       MODAL 2: CONNECT WHATSAPP NUMBER
  ════════════════════════════════════════════════════════ -->
  <div class="modal-overlay" id="connectWaModal">
    <div class="modal-window">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;">
        <div style="display:flex;align-items:center;gap:8px;">
          <span style="font-size:20px;">📱</span>
          <div style="font-size:16px;font-weight:800;color:#fff;">Connect Your WhatsApp Number</div>
        </div>
        <button class="btn btn-ghost btn-xs" id="closeConnectWaModalBtn">✕</button>
      </div>
      <p class="text-xs text-muted" style="margin-top:4px;margin-bottom:16px;">Sends messages directly from your WhatsApp with anti-ban human cadence. No Meta API required.</p>

      <div class="form-group">
        <label class="form-label">Your WhatsApp Phone Number</label>
        <input type="text" id="inputConnectedWaNumber" class="form-input mono" placeholder="e.g. 0300 1234567 or +923001234567">
        <span class="text-xs text-dim">Direct device session — 100% delivery, no template rejections.</span>
      </div>

      <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:14px;">
        <button class="btn btn-ghost btn-sm" id="cancelConnectWaBtn">Cancel</button>
        <button class="btn btn-wa btn-sm" id="saveConnectedWaBtn">Save & Set Number</button>
      </div>
    </div>
  </div>

  <!-- ════════════════════════════════════════════════════════
       AUTHENTICATION WALL / LOGIN
  ════════════════════════════════════════════════════════ -->
  <!-- ════════════════════════════════════════════════════════
       AUTHENTICATION WALL & LOGIN (NUNNO SALES DESIGN)
  ════════════════════════════════════════════════════════ -->
  <div class="auth-overlay" id="authOverlay">
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

    <main class="login-container" id="loginMainContainer">
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

        <form id="authForm" autocomplete="on" onsubmit="return false;">
          <div class="form-group" style="margin-bottom:16px;">
            <label class="form-label" for="authUsername">Work Username or Email</label>
            <div class="input-wrapper">
              <input 
                type="text" 
                id="authUsername" 
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

          <div class="form-group" style="margin-bottom:16px;">
            <label class="form-label" for="authPassword">Password</label>
            <div class="input-wrapper">
              <input 
                type="password" 
                id="authPassword" 
                class="form-input" 
                placeholder="••••••••••••" 
                required 
                autocomplete="current-password"
              >
              <svg class="input-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <rect x="3" y="11" width="18" height="11" rx="3"></rect>
                <path d="M7 11V7a5 5 0 0110 0v4"></path>
              </svg>
              <button type="button" class="pwd-toggle" id="authTogglePass">SHOW</button>
            </div>
          </div>

          <button type="submit" id="authSubmitBtn" class="submit-btn">
            Sign In to Omni-Desk
          </button>

          <div class="quick-fill-bar">
            <button type="button" class="quick-fill-btn" id="quickFillAdminBtn">👑 Admin</button>
            <button type="button" class="quick-fill-btn" id="quickFillEmpBtn">👨‍💼 Closer (Hamza)</button>
          </div>

        </form>

        <footer class="card-footer">
          <div>🔒 <strong>Authorized Personnel Only.</strong> No public registration.</div>
          <div class="footer-divider"></div>
          <div>Single-Number Multi-Closer Architecture with Anti-Theft Lead Locking</div>
        </footer>
      </div>
    </main>
  </div>

  <!-- ════════════════════════════════════════════════════════
       MODAL: ADMIN EMPLOYEE PROVISIONING
  ════════════════════════════════════════════════════════ -->
  <div class="modal-overlay" id="employeeModal">
    <div class="emp-modal-box">
      <div class="emp-modal-header">
        <div class="emp-modal-title-group">
          <div class="emp-modal-icon">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
          </div>
          <div>
            <div style="font-size:17px;font-weight:800;color:#fff;display:flex;align-items:center;gap:8px;">
              <span>Team & Employee Provisioning</span>
              <span class="badge-role-admin">👑 Admin Only</span>
            </div>
            <div class="text-xs text-muted">Provision authorized accounts for sales closers.</div>
          </div>
        </div>
        <button class="btn btn-ghost btn-xs" id="closeTeamModalBtn">✕</button>
      </div>

      <div class="emp-modal-body">
        <!-- Add Employee Form -->
        <div class="emp-panel">
          <h3 class="emp-panel-title">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="var(--cyan)" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            <span>Add New Employee</span>
          </h3>
          <p class="emp-panel-subtitle">Generate login credentials for your team member</p>

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
                <button type="button" class="btn btn-ghost btn-xs" id="genEmpPassBtn" style="color:var(--cyan);font-size:10px;">🎲 Generate</button>
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
              <label class="form-label">Employee WhatsApp (Optional)</label>
              <input type="text" id="newEmpPhone" class="form-input mono" placeholder="e.g. +923001234567">
            </div>
            <button type="submit" class="btn btn-wa btn-sm" id="submitNewEmpBtn" style="width:100%;justify-content:center;padding:10px;">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/></svg>
              <span>Provision Employee Account</span>
            </button>
          </form>
        </div>

        <!-- Team Roster -->
        <div class="emp-panel">
          <div style="display:flex;justify-content:space-between;align-items:center;">
            <div>
              <h3 class="emp-panel-title">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#a78bfa" stroke-width="2.2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                <span>Active Team Roster</span>
              </h3>
              <p class="emp-panel-subtitle">Provisioned Omni-Desk team members</p>
            </div>
            <button class="btn btn-ghost btn-xs" id="refreshTeamBtn">🔄 Refresh</button>
          </div>
          <div class="emp-roster-list" id="teamRosterList"></div>
        </div>
      </div>
    </div>
  </div>

  <!-- ════════════════════════════════════════════════════════
       MODAL: CONFIRM PAYMENT / MARK PAID
  ════════════════════════════════════════════════════════ -->
  <div class="modal-overlay" id="markPaidModal">
    <div class="modal-window" style="max-width:490px;">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:4px;">
        <div style="display:flex;align-items:center;gap:8px;">
          <span style="font-size:22px;">🛡️</span>
          <div style="font-size:16px;font-weight:800;color:#fff;">Record Sale & Submit for Verification</div>
        </div>
        <button class="btn btn-ghost btn-xs" id="closeMarkPaidModalBtn">✕</button>
      </div>
      <p class="text-xs text-muted" style="margin-bottom:16px;">Deal goes <strong style="color:#9ca3af;">⚪ Grey (In Review)</strong> first. Admin validates → turns <strong style="color:var(--emerald);">🟢 Green</strong> and credits your 10k target.</p>

      <form id="markPaidForm" onsubmit="return false;" style="display:flex;flex-direction:column;gap:12px;">
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
              <option value="Binance Pay (USDT)">🪙 Binance Pay</option>
              <option value="Bank Transfer">🏦 Bank Transfer</option>
              <option value="SadaPay / NayaPay">⚡ SadaPay / NayaPay</option>
            </select>
          </div>
          <div class="form-group" style="margin:0;">
            <label class="form-label">Customer Email (Optional)</label>
            <input type="email" id="paidCustomerEmail" class="form-input" placeholder="client@gmail.com">
          </div>
        </div>
        <div class="form-group" style="margin:0;">
          <label class="form-label">Transaction ID / Ref (Optional)</label>
          <input type="text" id="paidTrxId" class="form-input mono" placeholder="e.g. TID-984729104">
        </div>
        <div style="background:rgba(16,185,129,0.06);border:1px solid rgba(16,185,129,0.18);border-radius:8px;padding:8px 12px;display:flex;align-items:center;justify-content:space-between;font-size:11.5px;">
          <span style="color:var(--text-muted);">Attributed Closer:</span>
          <span style="font-weight:700;color:var(--emerald);" id="paidCloserName">Hamza Khan</span>
        </div>
        <div style="display:flex;gap:8px;justify-content:flex-end;">
          <button type="button" class="btn btn-ghost btn-sm" id="cancelMarkPaidBtn">Cancel</button>
          <button type="submit" class="btn btn-paid btn-sm" id="confirmMarkPaidBtn">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
            <span>Submit Sale for Verification</span>
          </button>
        </div>
      </form>
    </div>
  </div>

  <!-- ════════════════════════════════════════════════════════
       MODAL: CAN'T HANDLE / RELEASE LEAD
  ════════════════════════════════════════════════════════ -->
  <div class="modal-overlay" id="cantHandleModal">
    <div class="modal-window" style="max-width:440px;">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:4px;">
        <div style="display:flex;align-items:center;gap:8px;">
          <span style="font-size:20px;">🚫</span>
          <div style="font-size:16px;font-weight:800;color:#fff;">Can't Handle This Lead?</div>
        </div>
        <button class="btn btn-ghost btn-xs" id="closeCantHandleModalBtn">✕</button>
      </div>
      <p class="text-xs text-muted" style="margin-bottom:16px;">Releasing changes status back to <strong style="color:var(--red);">🔴 Fresh (Red)</strong> — any other closer can immediately pick it up.</p>

      <form id="cantHandleForm" onsubmit="return false;" style="display:flex;flex-direction:column;gap:12px;">
        <div class="form-group" style="margin:0;">
          <label class="form-label">Client</label>
          <div style="background:var(--bg-elevated);padding:8px 12px;border-radius:8px;font-size:12.5px;font-weight:600;color:#fff;border:1px solid var(--border-soft);" id="cantHandleClientName">Zeeshan Tariq</div>
        </div>
        <div class="form-group" style="margin:0;">
          <label class="form-label">Reason for Handover</label>
          <select id="cantHandleReasonSelect" class="form-select">
            <option value="Language barrier / Speaks regional language">🗣️ Language barrier (Pashto/Sindhi)</option>
            <option value="Price objection / Demanding higher discount">💰 Price objection / Higher discount</option>
            <option value="Unresponsive after pitch / Needs fresh angle">⏳ Unresponsive / Needs fresh angle</option>
            <option value="Demanding complex technical explanation">📈 Needs technical crypto explanation</option>
            <option value="Too busy right now / Handing off to team">⏱️ Too busy right now</option>
            <option value="Other objection">📝 Other reason</option>
          </select>
        </div>
        <div class="form-group" style="margin:0;">
          <label class="form-label">Handover Note for Next Closer (Optional)</label>
          <input type="text" id="cantHandleNote" class="form-input" placeholder="e.g. Offered 30% off, wants demo video">
        </div>
        <div style="display:flex;gap:8px;justify-content:flex-end;">
          <button type="button" class="btn btn-ghost btn-sm" id="cancelCantHandleBtn">Cancel</button>
          <button type="submit" class="btn btn-warning btn-sm" id="confirmCantHandleBtn">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M18.36 6.64a9 9 0 1 1-12.73 0"/><line x1="12" y1="2" x2="12" y2="12"/></svg>
            <span>Release to Fresh (Red) Pool</span>
          </button>
        </div>
      </form>
    </div>
  </div>

  <!-- ════════════════════════════════════════════════════════
       MODAL: PERFORMANCE PORTAL & LEADERBOARD
  ════════════════════════════════════════════════════════ -->
  <div class="modal-overlay" id="scoreModal">
    <div class="emp-modal-box" style="max-width:920px;">
      <div class="emp-modal-header">
        <div class="emp-modal-title-group">
          <div class="emp-modal-icon" style="background:rgba(234,179,8,0.1);border-color:rgba(234,179,8,0.25);color:#fbbf24;">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><circle cx="12" cy="8" r="7"/><polyline points="8.21 13.89 7 23 12 20 17 23 15.79 13.88"/></svg>
          </div>
          <div>
            <div style="font-size:17px;font-weight:800;color:#fff;display:flex;align-items:center;gap:8px;">
              <span id="scoreModalTitle">Performance & Score Center</span>
              <span class="badge-role-admin" id="scoreModalBadge">KPI Tracker</span>
            </div>
            <div class="text-xs text-muted">Real-time revenue metrics, deals won, and personal sales and commission metrics</div>
          </div>
        </div>
        <button class="btn btn-ghost btn-xs" id="closeScoreModalBtn">✕</button>
      </div>

      <!-- Tabs -->
      <div style="display:flex;gap:6px;padding:12px 24px;border-bottom:1px solid var(--border-subtle);background:rgba(0,0,0,0.15);overflow-x:auto;">
        <button type="button" class="nav-tab-btn active" id="tabMyScore" style="padding:6px 14px;font-size:12px;">👤 My Score & Deals</button>
        <button type="button" class="nav-tab-btn" id="tabVerifySales" style="padding:6px 14px;font-size:12px;">
          🛡️ Verify Transactions (<span id="pendingVerifCount">0</span>)
        </button>
      </div>

      <div class="emp-modal-body" style="grid-template-columns:1fr;padding:20px 24px;">

        <!-- TAB 1: My Score -->
        <div id="viewMyScoreTab">
          <div class="perf-hero-card">
            <div class="perf-hero-left">
              <div class="perf-hero-avatar" id="perfHeroAvatar">👤</div>
              <div>
                <div class="perf-hero-name" id="perfHeroName">Hamza Khan</div>
                <div class="perf-hero-role" id="perfHeroRole">Team Member &bull; EMP-101</div>
              </div>
            </div>
            <div class="perf-tier-badge" id="perfTierBadge">👤 Team Member</div>
          </div>

          <div class="salary-milestone-card">
            <div class="milestone-header">
              <div>
                <div class="milestone-title">🎯 Monthly Task Target: PKR 10,000 Sales</div>
                <div class="milestone-sub">Close Rs. 10,000 in verified sales to unlock your Rs. 10,000 Base Salary.</div>
              </div>
              <div class="milestone-status-badge" id="milestoneStatusBadge">
                <span id="targetBadgeIcon">⏳</span> <span id="targetBadgeText">In Progress</span>
              </div>
            </div>
            <div class="taskbar-container">
              <div class="taskbar-track">
                <div class="taskbar-fill" id="taskbarFill" style="width:0%;"></div>
              </div>
              <div class="taskbar-meta">
                <span class="mono" id="taskbarCurrent">Rs. 0</span>
                <span class="mono" id="taskbarPct">0% Completed</span>
                <span class="mono" id="taskbarGoal">Target: Rs. 10,000</span>
              </div>
            </div>
            <div class="milestone-feedback" id="milestoneFeedback">Close Rs. 10,000 more in verified sales to qualify for your base salary.</div>
          </div>

          <div class="score-cards-grid">
            <div class="score-card salary-card">
              <span class="score-card-label">Base Salary</span>
              <span class="score-card-value" id="myBaseSalary">Rs. 0</span>
              <span class="score-card-sub" id="myBaseSalarySub">Requires 10k Target</span>
            </div>
            <div class="score-card comm-card">
              <span class="score-card-label">Task Commissions</span>
              <span class="score-card-value" id="myCommissionsEarned">Rs. 0</span>
              <span class="score-card-sub">From post-target sales</span>
            </div>
            <div class="score-card payout-card">
              <span class="score-card-label">Total Take-Home</span>
              <span class="score-card-value" id="myTotalPayout">Rs. 0</span>
              <span class="score-card-sub">Base + Commissions</span>
            </div>
            <div class="score-card sales-card">
              <span class="score-card-label">Total Sales Closed</span>
              <span class="score-card-value" id="myTotalRevenue">Rs. 0</span>
              <span class="score-card-sub" id="myDealsWonSub">0 Verified Deals</span>
            </div>
          </div>

          <div class="comm-schedule-card">
            <div class="comm-schedule-title">💼 Post-Target Commission Schedule (Activates after 10k target)</div>
            <div class="comm-schedule-grid">
              <div class="comm-tier-box">
                <span class="comm-tier-plan">⚡ Starter Plan</span>
                <span class="comm-tier-sale">Sale: Rs. 1,700</span>
                <span class="comm-tier-earn">→ Rs. 700 Commission</span>
              </div>
              <div class="comm-tier-box">
                <span class="comm-tier-plan">💎 Pro Plan</span>
                <span class="comm-tier-sale">Sale: Rs. 5,000</span>
                <span class="comm-tier-earn">→ Rs. 2,000 Commission</span>
              </div>
              <div class="comm-tier-box">
                <span class="comm-tier-plan">🐋 Whale VIP</span>
                <span class="comm-tier-sale">Sale: Rs. 15,000</span>
                <span class="comm-tier-earn">→ Rs. 10,000 Commission</span>
              </div>
            </div>
          </div>

          <div class="perf-section-card">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
              <h4 style="margin:0;font-size:13.5px;color:#fff;font-weight:700;">Deals & Commission Ledger</h4>
              <span class="text-xs text-muted" id="myDealsCountLabel">0 Closed</span>
            </div>
            <div id="myDealsList" class="perf-deals-table"></div>
          </div>
        </div>

        <!-- TAB 3: Verify Transactions --><!-- TAB 3: Verify Transactions -->
        <div id="viewVerifySalesTab" style="display:none;">
          <div class="perf-hero-card" style="margin-bottom:16px;">
            <div class="perf-hero-left">
              <div class="perf-hero-avatar" style="background:rgba(30,41,59,0.8);border-color:var(--cyan);color:var(--cyan);">🛡️</div>
              <div>
                <div class="perf-hero-name">Anti-Fraud Transaction Audit Portal</div>
                <div class="perf-hero-role">Audit submitted payments before confirming deals & commissions</div>
              </div>
            </div>
            <div class="perf-tier-badge" style="background:rgba(0,212,170,0.06);border-color:rgba(0,212,170,0.2);color:var(--cyan);">
              <span id="pendingAuditHeaderCount">0 Pending</span>
            </div>
          </div>
          <div id="verifySalesList" style="display:flex;flex-direction:column;gap:12px;"></div>
        </div>

      </div>
    </div>
  </div>

  <!-- Toast Notification -->
  <div class="toast" id="toast">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--cyan)" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
    <span id="toastMsg">Ready</span>
  </div>

  <script src="assets/js/whatsapp-app.js?v=5.5"></script>
</body>
</html>
