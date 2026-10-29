/**
 * Nunno WhatsApp Omni-Desk & Team CRM Engine
 * Multi-Agent Ownership, Anti-Theft Lead Locking, Unified 3-Pane Messenger & Campaign Queue
 */

document.addEventListener('DOMContentLoaded', () => {

  // Safe HTML Escaping Helper
  function escapeHtml(text) {
    if (text === null || text === undefined) return '';
    return String(text)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  // Default Team Agents (Strict Roles: Admin, Team Lead, Team Member)
  const defaultAgents = [
    { id: 'EMP-101', name: 'Hamza Khan', role: 'Team Member', avatar: '👨‍💼', color: '#6366f1' },
    { id: 'EMP-102', name: 'Bilal Tariq', role: 'Team Lead', avatar: '🧑‍💻', color: '#06b6d4' },
    { id: 'EMP-103', name: 'Ayesha Noor', role: 'Team Member', avatar: '👩‍💼', color: '#ec4899' },
    { id: 'EMP-104', name: 'Zain Ahmed', role: 'Team Member', avatar: '👨‍🦱', color: '#10b981' }
  ];

  // Pre-loaded Pakistani Traders with Traffic-Light Statuses
  // RED = Fresh (Unclaimed)
  // YELLOW = Ongoing (Claimed by an agent)
  // GREEN = Closed (Client has paid!)
  const initialLeads = [
    {
      id: 'lead_self',
      name: 'My WhatsApp (Self Test)',
      phone: '923318036576',
      display: '+92 331 8036576',
      city: 'Rawalpindi / Islamabad',
      network: 'Ufone',
      ownerId: 'USR-ADMIN-01',
      leadState: 'ongoing',
      status: 'replied',
      unreadCount: 0,
      interest: 'Live Delivery Verification',
      lastActive: 'Just now',
      notes: 'Connected phone self-testing chat. Messages, voice notes, and docs sent here deliver straight to your linked phone!',
      messages: [
        { sender: 'lead', text: '⚡ This is your personal linked WhatsApp chat (+92 331 8036576). Send any message, voice memo, or document to see it arrive directly on your phone!', time: '12:00 PM' }
      ]
    }
  ];

  // Helper: Single Source of Truth for Traffic-Light Status
  // RED = Fresh (Unclaimed)
  // YELLOW = Ongoing (Claimed by an agent)
  // GREY = Pending Verification (Submitted as paid, awaiting Admin audit)
  // GREEN = Closed (Client has paid & Admin verified!)
  // BLUE = Useless (Dealt with, won't pay / dead lead)
  function getLeadState(lead) {
    if (!lead) return 'fresh';
    if (lead.leadState === 'pending_verification' || lead.status === 'pending_verification' || lead.leadState === 'pending' || lead.status === 'pending') return 'pending_verification';
    if (lead.leadState === 'paid' || lead.status === 'paid') return 'paid';
    if (lead.leadState === 'useless' || lead.status === 'useless') return 'useless';
    if (lead.ownerId !== null) return 'ongoing';
    return 'fresh';
  }

  // App Master State
  const state = {
    currentTab: 'inbox', // 'inbox', 'campaign', 'directory'
    currentUser: null,   // Authenticated Admin or Employee
    employees: [],       // Employee roster
    agents: defaultAgents,
    currentAgent: defaultAgents[0], // EMP-101 Hamza Khan
    leads: window.NUNNO_OFFLINE_PREVIEW ? [...initialLeads] : [],
    activeLeadId: window.NUNNO_OFFLINE_PREVIEW ? 'lead_self' : null,
    inboxFilter: 'all', // 'all', 'unread', 'replied', 'sent', 'mine', 'unclaimed'
    searchQuery: '',

    // Linked-device sender; connection state always comes from the server.
    connectedWaNumber: '',
    senderConfigured: false,

    // Campaign Studio queue state
    campaignQueue: [...initialLeads],
    campaignIndex: 0,
    delaySeconds: 3,
    blacklist: []
  };

  // DOM Elements
  const els = {
    // Topbar Tabs
    tabInbox: document.getElementById('tabInbox'),
    tabCampaign: document.getElementById('tabCampaign'),
    tabEmployee: document.getElementById('tabEmployee'),
    viewInbox: document.getElementById('viewInbox'),
    viewCampaign: document.getElementById('viewCampaign'),
    viewEmployee: document.getElementById('viewEmployee'),
    employeeLeadList: document.getElementById('employeeLeadList'),

    // Topbar Badges & Agent Pill
    agentPill: document.getElementById('agentPill'),
    topAgentAvatar: document.getElementById('topAgentAvatar'),
    topAgentName: document.getElementById('topAgentName'),
    topAgentId: document.getElementById('topAgentId'),
    unreadGlobalBadge: document.getElementById('unreadGlobalBadge'),
    myLeadsCountBadge: document.getElementById('myLeadsCountBadge'),
    openTeamModalBtn: document.getElementById('openTeamModalBtn'),
    themeToggleBtn: document.getElementById('themeToggleBtn'),
    headerLogoutBtn: document.getElementById('headerLogoutBtn'),
    headerEmpCount: document.getElementById('headerEmpCount'),

    // Authentication Elements
    authOverlay: document.getElementById('authOverlay'),
    authForm: document.getElementById('authForm'),
    authUsername: document.getElementById('authUsername'),
    authPassword: document.getElementById('authPassword'),
    authTogglePass: document.getElementById('authTogglePass'),
    authAlert: document.getElementById('authAlert'),
    authSubmitBtn: document.getElementById('authSubmitBtn'),

    // Employee Provisioning & Roster Modal Elements
    employeeModal: document.getElementById('employeeModal'),
    closeTeamModalBtn: document.getElementById('closeTeamModalBtn'),
    createEmpForm: document.getElementById('createEmpForm'),
    newEmpName: document.getElementById('newEmpName'),
    newEmpUsername: document.getElementById('newEmpUsername'),
    newEmpPassword: document.getElementById('newEmpPassword'),
    newEmpRole: document.getElementById('newEmpRole'),
    newEmpPhone: document.getElementById('newEmpPhone'),
    genEmpPassBtn: document.getElementById('genEmpPassBtn'),
    submitNewEmpBtn: document.getElementById('submitNewEmpBtn'),
    createEmpAlert: document.getElementById('createEmpAlert'),
    teamRosterList: document.getElementById('teamRosterList'),
    refreshTeamBtn: document.getElementById('refreshTeamBtn'),

    // Connected WhatsApp Direct Number Pill & Modal
    connectedNumberPill: document.getElementById('connectedNumberPill'),
    connectedNumberText: document.getElementById('connectedNumberText'),
    campaignConnectionStatus: document.getElementById('campaignConnectionStatus'),
    connectWaModal: document.getElementById('connectWaModal'),
    closeConnectWaModalBtn: document.getElementById('closeConnectWaModalBtn'),
    cancelConnectWaBtn: document.getElementById('cancelConnectWaBtn'),
    saveConnectedWaBtn: document.getElementById('saveConnectedWaBtn'),
    reconnectWaBtn: document.getElementById('reconnectWaBtn'),
    waConnectedBox: document.getElementById('waConnectedBox'),
    waModalConnectedNum: document.getElementById('waModalConnectedNum'),
    waConnectionStatus: document.getElementById('waConnectionStatus'),
    waQrImage: document.getElementById('waQrImage'),
    waPendingCampaign: document.getElementById('waPendingCampaign'),

    // Modal
    agentModal: document.getElementById('agentModal'),
    closeAgentModalBtn: document.getElementById('closeAgentModalBtn'),
    agentListContainer: document.getElementById('agentListContainer'),

    // Inbox Controls
    convSearchInput: document.getElementById('convSearchInput'),
    inboxFilterBtns: document.querySelectorAll('.inbox-filter-btn'),
    conversationsScroll: document.getElementById('conversationsScroll'),

    // Subbar KPI Widgets
    kpiFilterBtns: document.querySelectorAll('.kpi-stat-pill'),
    kpiCountAll: document.getElementById('kpiCountAll'),
    kpiCountFresh: document.getElementById('kpiCountFresh'),
    kpiCountOngoing: document.getElementById('kpiCountOngoing'),
    kpiCountPaid: document.getElementById('kpiCountPaid'),
    kpiCountPending: document.getElementById('kpiCountPending'),
    kpiFilterPending: document.getElementById('kpiFilterPending'),
    kpiCountUseless: document.getElementById('kpiCountUseless'),
    convTotalBadge: document.getElementById('convTotalBadge'),

    // Active Chat Thread
    chatBackBtn: document.getElementById('chatBackBtn'),
    chatLeadAvatar: document.getElementById('chatLeadAvatar'),
    chatLeadDot: document.getElementById('chatLeadDot'),
    chatLeadName: document.getElementById('chatLeadName'),
    chatLeadPhone: document.getElementById('chatLeadPhone'),
    chatLeadStatusBadge: document.getElementById('chatLeadStatusBadge'),
    chatLeadCloser: document.getElementById('chatLeadCloser'),
    chatClaimBtn: document.getElementById('chatClaimBtn'),
    markPaidHeaderBtn: document.getElementById('markPaidHeaderBtn'),
    verifyPaidHeaderBtn: document.getElementById('verifyPaidHeaderBtn'),
    rejectPaidHeaderBtn: document.getElementById('rejectPaidHeaderBtn'),
    markUselessHeaderBtn: document.getElementById('markUselessHeaderBtn'),
    otherCloserNotice: document.getElementById('otherCloserNotice'),
    otherCloserName: document.getElementById('otherCloserName'),
    chatMessagesArea: document.getElementById('chatMessagesArea'),
    chatInput: document.getElementById('chatInput'),
    sendMsgBtn: document.getElementById('sendMsgBtn'),
    simClientAudioBtn: document.getElementById('simClientAudioBtn'),

    // Audio & Voice Message Controls
    voiceRecordBtn: document.getElementById('voiceRecordBtn'),
    audioUploadBtn: document.getElementById('audioUploadBtn'),
    audioFileInput: document.getElementById('audioFileInput'),
    composerNormalView: document.getElementById('composerNormalView'),
    composerRecordingView: document.getElementById('composerRecordingView'),
    recordingTimer: document.getElementById('recordingTimer'),
    cancelRecordBtn: document.getElementById('cancelRecordBtn'),
    sendRecordBtn: document.getElementById('sendRecordBtn'),

    // Macro Chips
    macroChips: document.querySelectorAll('.quick-macro-chip'),

    // Dossier Panel (SaaS Sales Intelligence Cockpit)
    dossierToggleBtn: document.getElementById('dossierToggleBtn'),
    dossierDrawerHeader: document.getElementById('dossierDrawerHeader'),
    closeDossierDrawerBtn: document.getElementById('closeDossierDrawerBtn'),
    leadDossierCol: document.getElementById('leadDossierCol'),
    dossierBackdrop: document.getElementById('dossierBackdrop'),
    dossierInterest: document.getElementById('dossierInterest'),
    dossierStatusPill: document.getElementById('dossierStatusPill'),
    dossierDealValue: document.getElementById('dossierDealValue'),
    dossierOwnerAvatar: document.getElementById('dossierOwnerAvatar'),
    dossierOwner: document.getElementById('dossierOwner'),
    dossierOwnerRole: document.getElementById('dossierOwnerRole'),
    dossierLockBadge: document.getElementById('dossierLockBadge'),
    dossierPaidBtn: document.getElementById('dossierPaidBtn'),
    dossierUselessBtn: document.getElementById('dossierUselessBtn'),
    dossierClaimBtn: document.getElementById('dossierClaimBtn'),
    dossierReleaseBtn: document.getElementById('dossierReleaseBtn'),
    dossierNotes: document.getElementById('dossierNotes'),
    notesSavedIndicator: document.getElementById('notesSavedIndicator'),
    matrixBtns: document.querySelectorAll('.matrix-btn'),
    salesTags: document.querySelectorAll('.sales-tag'),

    // Performance Score & Leaderboard Triggers
    openScoreBtn: document.getElementById('openScoreBtn'),

    // Can't Handle & Lead Lock Shield Elements
    cantHandleBtn: document.getElementById('cantHandleBtn'),
    leadLockedShield: document.getElementById('leadLockedShield'),
    lockedCloserName: document.getElementById('lockedCloserName'),

    // Modal 5: Mark Paid Mini-Popup
    markPaidModal: document.getElementById('markPaidModal'),
    closeMarkPaidModalBtn: document.getElementById('closeMarkPaidModalBtn'),
    cancelMarkPaidBtn: document.getElementById('cancelMarkPaidBtn'),
    markPaidForm: document.getElementById('markPaidForm'),
    paidCustomerName: document.getElementById('paidCustomerName'),
    paidCustomerPhone: document.getElementById('paidCustomerPhone'),
    paidPlanSelect: document.getElementById('paidPlanSelect'),
    paidAmountInput: document.getElementById('paidAmountInput'),
    paidMethodSelect: document.getElementById('paidMethodSelect'),
    paidCustomerEmail: document.getElementById('paidCustomerEmail'),
    paidTrxId: document.getElementById('paidTrxId'),
    paidCloserName: document.getElementById('paidCloserName'),
    confirmMarkPaidBtn: document.getElementById('confirmMarkPaidBtn'),

    // Modal 6: Can't Handle Handover Modal
    cantHandleModal: document.getElementById('cantHandleModal'),
    closeCantHandleModalBtn: document.getElementById('closeCantHandleModalBtn'),
    cancelCantHandleBtn: document.getElementById('cancelCantHandleBtn'),
    cantHandleForm: document.getElementById('cantHandleForm'),
    cantHandleClientName: document.getElementById('cantHandleClientName'),
    cantHandleReasonSelect: document.getElementById('cantHandleReasonSelect'),
    cantHandleNote: document.getElementById('cantHandleNote'),
    confirmCantHandleBtn: document.getElementById('confirmCantHandleBtn'),

    // Modal 7: Performance Portal & Leaderboard
    scoreModal: document.getElementById('scoreModal'),
    closeScoreModalBtn: document.getElementById('closeScoreModalBtn'),
    tabMyScore: document.getElementById('tabMyScore'),
    tabVerifySales: document.getElementById('tabVerifySales'),
    viewMyScoreTab: document.getElementById('viewMyScoreTab'),
    viewVerifySalesTab: document.getElementById('viewVerifySalesTab'),
    pendingVerifCount: document.getElementById('pendingVerifCount'),
    pendingAuditHeaderCount: document.getElementById('pendingAuditHeaderCount'),
    verifySalesList: document.getElementById('verifySalesList'),
    dossierAdminVerifActions: document.getElementById('dossierAdminVerifActions'),
    dossierVerifyBtn: document.getElementById('dossierVerifyBtn'),
    dossierRejectBtn: document.getElementById('dossierRejectBtn'),
    dossierPendingNotice: document.getElementById('dossierPendingNotice'),
    matrixBtnPending: document.getElementById('matrixBtnPending'),
    perfHeroAvatar: document.getElementById('perfHeroAvatar'),
    perfHeroName: document.getElementById('perfHeroName'),
    perfHeroRole: document.getElementById('perfHeroRole'),
    perfTierBadge: document.getElementById('perfTierBadge'),
    taskbarFill: document.getElementById('taskbarFill'),
    taskbarCurrent: document.getElementById('taskbarCurrent'),
    taskbarPct: document.getElementById('taskbarPct'),
    taskbarGoal: document.getElementById('taskbarGoal'),
    milestoneStatusBadge: document.getElementById('milestoneStatusBadge'),
    targetBadgeIcon: document.getElementById('targetBadgeIcon'),
    targetBadgeText: document.getElementById('targetBadgeText'),
    milestoneFeedback: document.getElementById('milestoneFeedback'),
    myBaseSalary: document.getElementById('myBaseSalary'),
    myBaseSalarySub: document.getElementById('myBaseSalarySub'),
    myCommissionsEarned: document.getElementById('myCommissionsEarned'),
    myTotalPayout: document.getElementById('myTotalPayout'),
    myTotalRevenue: document.getElementById('myTotalRevenue'),
    myDealsWonSub: document.getElementById('myDealsWonSub'),
    myDealsCountLabel: document.getElementById('myDealsCountLabel'),
    myDealsList: document.getElementById('myDealsList'),
    orgTotalRevenue: document.getElementById('orgTotalRevenue'),
    orgTotalDeals: document.getElementById('orgTotalDeals'),
    orgSalariesUnlocked: document.getElementById('orgSalariesUnlocked'),
    orgCommissionsTotal: document.getElementById('orgCommissionsTotal'),

    // Campaign Studio DOM Elements
    campaignNameInput: document.getElementById('campaignNameInput'),
    campaignFileInput: document.getElementById('campaignFileInput'),
    campaignDropZone: document.getElementById('campaignDropZone'),
    quickAddMyNumberBtn: document.getElementById('quickAddMyNumberBtn'),
    togglePasteAreaBtn: document.getElementById('togglePasteAreaBtn'),
    campaignPasteArea: document.getElementById('campaignPasteArea'),
    applyPasteNumbersBtn: document.getElementById('applyPasteNumbersBtn'),
    campaignAudienceBadge: document.getElementById('campaignAudienceBadge'),
    campaignAudienceSummary: document.getElementById('campaignAudienceSummary'),
    campaignLoadedCountText: document.getElementById('campaignLoadedCountText'),
    clearAudienceBtn: document.getElementById('clearAudienceBtn'),
    campaignAudienceList: document.getElementById('campaignAudienceList'),
    campaignAssignSelect: document.getElementById('campaignAssignSelect'),
    campaignDelayMin: document.getElementById('campaignDelayMin'),
    campaignDelayMax: document.getElementById('campaignDelayMax'),
    campaignPresetSelect: document.getElementById('campaignPresetSelect'),
    campaignAutoOpenWa: document.getElementById('campaignAutoOpenWa'),
    campaignWaTargetType: document.getElementById('campaignWaTargetType'),
    campaignPitchPreset: document.getElementById('campaignPitchPreset'),
    campaignCustomMsg: document.getElementById('campaignCustomMsg'),
    campaignBubblePreview: document.getElementById('campaignBubblePreview'),
    startCampaignBtn: document.getElementById('startCampaignBtn'),
    pauseCampaignBtn: document.getElementById('pauseCampaignBtn'),
    stopCampaignBtn: document.getElementById('stopCampaignBtn'),
    campaignProgressCard: document.getElementById('campaignProgressCard'),
    campaignProgressCount: document.getElementById('campaignProgressCount'),
    campaignProgressPct: document.getElementById('campaignProgressPct'),
    campaignProgressFill: document.getElementById('campaignProgressFill'),
    campaignCountdown: document.getElementById('campaignCountdown'),
    campaignCurrentPace: document.getElementById('campaignCurrentPace'),
    campaignTerminalLog: document.getElementById('campaignTerminalLog'),
    campaignLogCount: document.getElementById('campaignLogCount'),
    campaignStatusBadge: document.getElementById('campaignStatusBadge'),
    campaignTagChips: document.querySelectorAll('#viewCampaign .tag-chip'),

    // Toast
    toast: document.getElementById('toast'),
    toastMsg: document.getElementById('toastMsg')
  };

  function setTheme(theme) {
    const isDark = theme === 'dark';
    document.body.classList.toggle('theme-dark', isDark);
    localStorage.setItem('nunno_theme', isDark ? 'dark' : 'light');
    if (!els.themeToggleBtn) return;
    const label = isDark ? 'Light theme' : 'Dark theme';
    const icon = isDark
      ? '<circle cx="12" cy="12" r="4"/><path d="M12 2v2m0 16v2M4.93 4.93l1.42 1.42m11.3 11.3 1.42 1.42M2 12h2m16 0h2M4.93 19.07l1.42-1.42m11.3-11.3 1.42-1.42"/>'
      : '<path d="M20.2 15.2A8.5 8.5 0 0 1 8.8 3.8 8.5 8.5 0 1 0 20.2 15.2Z"/>';
    els.themeToggleBtn.setAttribute('aria-label', `Switch to ${isDark ? 'light' : 'dark'} theme`);
    els.themeToggleBtn.setAttribute('title', `Switch to ${isDark ? 'light' : 'dark'} theme`);
    els.themeToggleBtn.setAttribute('aria-pressed', String(isDark));
    els.themeToggleBtn.innerHTML = `<svg class="theme-icon" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">${icon}</svg><span>${label}</span>`;
  }

  setTheme(localStorage.getItem('nunno_theme') === 'dark' ? 'dark' : 'light');
  els.themeToggleBtn?.addEventListener('click', () => {
    setTheme(document.body.classList.contains('theme-dark') ? 'light' : 'dark');
  });

  // Toast Function
  function previewLabel(text) {
    return window.NUNNO_OFFLINE_PREVIEW ? '[OFFLINE TEST] ' + text.replaceAll('WhatsApp accepted', 'Simulation processed').replaceAll('WhatsApp delivered', 'Simulation completed') : text;
  }

  if (window.NUNNO_OFFLINE_PREVIEW || window.NUNNO_LOCAL_LIVE) {
    const banner = document.createElement('div');
    banner.id = 'localTestBanner';
    banner.setAttribute('role', 'status');
    banner.style.cssText = 'position:fixed;bottom:0;left:0;right:0;z-index:99999;pointer-events:none;padding:10px;text-align:center;background:#fff3cd;color:#5c4100;font:600 13px sans-serif;';
    banner.textContent = window.NUNNO_OFFLINE_PREVIEW
      ? 'OFFLINE TEST — simulated messages only. Nothing is sent to WhatsApp. Test data resets when this page closes.'
      : 'LOCAL WORKSPACE — your linked device and saved message history stay on this computer.';
    document.body.appendChild(banner);
  }

  function showToast(msg, duration = 3000) {
    msg = previewLabel(msg);
    if (!els.toast) return;
    els.toastMsg.textContent = msg;
    els.toast.classList.add('show');
    setTimeout(() => els.toast.classList.remove('show'), duration);
  }

  // Helper: Find Lead by ID
  function getLead(id) {
    return state.leads.find(l => l.id === id);
  }

  // Helper: Find Agent by ID
  function getAgent(id) {
    return state.agents.find(a => a.id === id);
  }

  function canAccessCampaignStudio() {
    const role = String(state.currentUser?.role || state.currentAgent?.role || '').trim().toLowerCase();
    return role === 'admin' || role === 'master admin';
  }

  // Update Topbar Agent Display
  function renderAgentProfile() {
    const user = state.currentUser;
    if (els.tabCampaign) els.tabCampaign.style.display = canAccessCampaignStudio() ? 'inline-flex' : 'none';
    if (user) {
      const isAdmin = user.role === 'admin';
      if (els.topAgentAvatar) els.topAgentAvatar.textContent = isAdmin ? '👑' : '👨‍💼';
      if (els.topAgentName) els.topAgentName.textContent = user.name;
      if (els.topAgentId) {
        els.topAgentId.innerHTML = isAdmin 
          ? '<span class="badge-admin-crown">👑 Admin</span>' 
          : `<span class="badge-employee-pill">💼 ${escapeHtml(user.designation || 'Closer')}</span>`;
      }
      if (els.openScoreBtn) {
        els.openScoreBtn.innerHTML = isAdmin
          ? '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M12 20v-6M6 20V10M18 20V4"/></svg><span>📈 My Score</span>'
          : `<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><circle cx="12" cy="7" r="4"/><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/></svg><span>💼 Closer Portal (${escapeHtml((user.name || 'Closer').split(' ')[0])})</span>`;
      }
      if (els.openTeamModalBtn) {
        els.openTeamModalBtn.style.display = isAdmin ? 'inline-flex' : 'none';
      }
      if (els.tabEmployee) els.tabEmployee.style.display = isAdmin ? 'none' : 'inline-flex';
    } else {
      if (els.openScoreBtn) {
        els.openScoreBtn.innerHTML = '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M12 20v-6M6 20V10M18 20V4"/></svg><span>My Score</span>';
      }
      if (els.topAgentAvatar) els.topAgentAvatar.textContent = state.currentAgent.avatar;
      if (els.topAgentName) els.topAgentName.textContent = state.currentAgent.name;
      if (els.topAgentId) els.topAgentId.textContent = `${state.currentAgent.id} • ${state.currentAgent.role}`;
      if (els.openTeamModalBtn) els.openTeamModalBtn.style.display = 'none';
      if (els.tabEmployee) els.tabEmployee.style.display = 'none';
    }

    // Update global counters
    const unreadCount = state.leads.filter(l => l.status === 'unread').length;
    if (els.unreadGlobalBadge) els.unreadGlobalBadge.textContent = unreadCount;

    const activeId = user ? user.id : state.currentAgent.id;
    const myLeads = state.leads.filter(l => l.ownerId === activeId).length;
    if (els.myLeadsCountBadge) els.myLeadsCountBadge.textContent = myLeads;
  }

  // Update Connected Direct WhatsApp Number in Subbar
  function renderConnectedNumber() {
    if (!els.connectedNumberText) return;
    if (state.senderConfigured) {
      els.connectedNumberText.textContent = state.connectedWaNumber || 'Business API configured';
      els.connectedNumberText.style.color = 'var(--c-wa)';
    } else {
      els.connectedNumberText.textContent = 'Sender not connected (Click to set up)';
      els.connectedNumberText.style.color = '#94a3b8';
    }
  }

  let waPollTimer = null;
  let waModalGeneration = 0;
  let pendingCampaignStart = false;

  function renderSenderConnection(result) {
    const wasConnected = state.senderConfigured;
    state.senderConfigured = result.is_configured === true;
    state.connectedWaNumber = result.connected_number || '';
    renderConnectedNumber();
    if (els.campaignConnectionStatus) {
      els.campaignConnectionStatus.textContent = window.NUNNO_OFFLINE_PREVIEW
        ? '🧪 Offline simulation — no real sends'
        : state.senderConfigured
          ? `✓ WhatsApp connected${state.connectedWaNumber ? ' · +' + state.connectedWaNumber : ''} — ready to send`
          : 'WhatsApp not connected · Click to link';
    }
    if (!wasConnected && state.senderConfigured && !window.NUNNO_OFFLINE_PREVIEW) {
      showToast('✓ WhatsApp connected. Your campaign is ready; click Start your campaign to send.', 6000);
    }
    const labels = {
      idle: 'Ready to link. Click Connect WhatsApp.',
      starting: 'Starting WhatsApp… the QR code will appear here.',
      qr: 'Scan this code with WhatsApp on your phone.',
      authenticated: 'Phone linked. Waiting for WhatsApp to finish syncing…',
      ready: 'WhatsApp connected' + (state.connectedWaNumber ? ': +' + state.connectedWaNumber : '')
    };
    if (els.waConnectionStatus) els.waConnectionStatus.textContent = result.message || labels[result.state] || (state.senderConfigured ? 'WhatsApp connected.' : 'WhatsApp is not connected.');
    if (els.waQrImage) {
      const validQr = typeof result.qr === 'string' && result.qr.startsWith('data:image/png;base64,');
      els.waQrImage.hidden = !validQr;
      if (validQr) els.waQrImage.src = result.qr;
      else els.waQrImage.removeAttribute('src');
    }
    if (els.waConnectedBox) {
      els.waConnectedBox.style.display = state.senderConfigured ? 'block' : 'none';
    }
    if (els.waModalConnectedNum) {
      els.waModalConnectedNum.textContent = state.connectedWaNumber ? '+' + state.connectedWaNumber : (result.connected_number ? '+' + result.connected_number : 'Connected');
    }
    if (els.saveConnectedWaBtn) {
      els.saveConnectedWaBtn.disabled = ['starting', 'qr', 'authenticated', 'ready'].includes(result.state);
      els.saveConnectedWaBtn.textContent = state.senderConfigured ? 'Connected' : 'Connect WhatsApp';
      els.saveConnectedWaBtn.style.display = state.senderConfigured ? 'none' : 'inline-block';
    }
  }

  async function refreshSenderConnection() {
    const response = await fetch('api/direct_sender.php?action=get_sender_info', { cache: 'no-store' });
    const result = await response.json();
    renderSenderConnection(result);
    if (!response.ok || !result.success) throw new Error(result.message || 'Could not check WhatsApp connection.');
    return state.senderConfigured;
  }

  async function pollWhatsAppConnection(generation) {
    if (generation !== waModalGeneration || !els.connectWaModal?.classList.contains('open')) return;
    try {
      await refreshSenderConnection();
      if (generation !== waModalGeneration) return;
      if (state.senderConfigured && pendingCampaignStart) {
        closeConnectWaModal();
        startCampaign();
        return;
      }
    } catch (_) { /* The status panel displays service errors and allows a retry. */ }
    if (generation === waModalGeneration && els.connectWaModal?.classList.contains('open')) {
      waPollTimer = setTimeout(() => pollWhatsAppConnection(generation), 2000);
    }
  }

  function openConnectWaModal() {
    if (els.connectWaModal) els.connectWaModal.classList.add('open');
    if (els.waPendingCampaign) els.waPendingCampaign.hidden = !pendingCampaignStart;
    clearTimeout(waPollTimer);
    pollWhatsAppConnection(++waModalGeneration);
  }

  function closeConnectWaModal() {
    pendingCampaignStart = false;
    waModalGeneration++;
    clearTimeout(waPollTimer);
    if (els.connectWaModal) els.connectWaModal.classList.remove('open');
  }

  async function saveConnectedWaNumber() {
    if (els.saveConnectedWaBtn) els.saveConnectedWaBtn.disabled = true;
    try {
      const response = await fetch('api/direct_sender.php', {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'connect_whatsapp' })
      });
      const result = await response.json();
      renderSenderConnection(result);
      if (!response.ok || !result.success) throw new Error(result.message || 'Could not start WhatsApp linking.');
    } catch (error) {
      if (els.waConnectionStatus) els.waConnectionStatus.textContent = describeSendError(error);
      if (els.saveConnectedWaBtn) els.saveConnectedWaBtn.disabled = false;
    }
  }

  async function reconnectWhatsApp() {
    if (els.reconnectWaBtn) els.reconnectWaBtn.disabled = true;
    if (els.waConnectionStatus) els.waConnectionStatus.textContent = 'Unlinking previous WhatsApp session and generating new QR code…';
    if (els.waConnectedBox) els.waConnectedBox.style.display = 'none';
    if (els.waQrImage) { els.waQrImage.hidden = true; els.waQrImage.removeAttribute('src'); }
    try {
      const response = await fetch('api/direct_sender.php', {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'reconnect_whatsapp' })
      });
      const result = await response.json();
      state.senderConfigured = false;
      state.connectedWaNumber = '';
      renderConnectedNumber();
      renderSenderConnection(result);
      showToast('🔄 Scan the QR code with WhatsApp on your phone to link.');
    } catch (error) {
      if (els.waConnectionStatus) els.waConnectionStatus.textContent = describeSendError(error);
    } finally {
      if (els.reconnectWaBtn) els.reconnectWaBtn.disabled = false;
    }
  }

  // Full-Screen Image Lightbox Viewer
  function openImageLightbox(src, filename) {
    const modal = document.getElementById('imageLightboxModal');
    const img = document.getElementById('lightboxImageEl');
    const dlBtn = document.getElementById('lightboxDownloadBtn');
    if (!modal || !img) return;
    img.src = src;
    if (dlBtn) {
      dlBtn.href = src;
      dlBtn.download = filename || 'whatsapp-image.jpg';
    }
    modal.style.display = 'flex';
  }

  function closeImageLightbox() {
    const modal = document.getElementById('imageLightboxModal');
    const img = document.getElementById('lightboxImageEl');
    if (modal) modal.style.display = 'none';
    if (img) img.removeAttribute('src');
  }

  document.getElementById('closeImageLightboxBtn')?.addEventListener('click', closeImageLightbox);
  document.getElementById('imageLightboxModal')?.addEventListener('click', (e) => {
    if (e.target === e.currentTarget) closeImageLightbox();
  });
  window.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') closeImageLightbox();
  });

  // ==========================================
  // ==========================================
  // LEAD LOCKING, OWNERSHIP & PAYMENT ENGINE
  // ==========================================

  // Centralized Server Sync (No Scattered Data across employees on cPanel)
  function syncLeadToServer(lead, extraData = {}) {
    if (!lead) return;
    const payload = {
      ...((window.NUNNO_LOCAL_LIVE || window.NUNNO_OFFLINE_PREVIEW) ? lead : {}),
      action: 'update_lead',
      id: lead.id,
      leadState: lead.leadState,
      status: lead.status,
      ownerId: lead.ownerId,
      ownerName: lead.ownerName,
      verificationStatus: lead.verificationStatus,
      paidDetails: lead.paidDetails,
      notes: lead.notes,
      unreadCount: lead.unreadCount,
      lastActive: lead.lastActive,
      ...extraData
    };
    fetch('api/leads_sync.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    }).catch(() => {});
  }

  // Fetch Centralized Leads on cPanel
  function fetchServerLeads() {
    if (window.NUNNO_LOCAL_LIVE) return;
    fetch('api/leads_sync.php?action=get_leads')
      .then(res => res.json())
      .then(data => {
        if (data && data.success && Array.isArray(data.leads) && data.leads.length > 0) {
          state.leads = data.leads;
          if (!state.activeLeadId || !state.leads.some(l => l.id === state.activeLeadId)) {
            state.activeLeadId = state.leads[0].id;
          }
          renderConversationsList();
          renderActiveChat();
          renderAgentProfile();
        }
      })
      .catch(() => {});
  }

  // Claim Lead -> Transitions to YELLOW (Ongoing)
  function claimLead(leadId) {
    const lead = getLead(leadId);
    if (!lead) return;

    const myId = state.currentUser ? state.currentUser.id : state.currentAgent.id;
    const myName = state.currentUser ? state.currentUser.name : state.currentAgent.name;
    const isAdmin = state.currentUser && state.currentUser.role === 'admin';

    if (lead.ownerId && lead.ownerId !== myId && !isAdmin) {
      const owner = getAgent(lead.ownerId);
      showToast(`⚠️ This lead is already locked by ${owner ? owner.name : (lead.ownerName || lead.ownerId)}!`);
      return;
    }

    lead.ownerId = myId;
    lead.ownerName = myName;
    lead.leadState = 'ongoing'; // YELLOW: Ongoing

    showToast(`🟡 Lead Claimed! You are now chatting with ${lead.name} (Ongoing).`);
    renderConversationsList();
    renderActiveChat();
    renderAgentProfile();
    syncLeadToServer(lead, { ownerName: myName });
  }

  // Release Lead -> Transitions back to RED (Fresh / Unclaimed)
  function releaseLead(leadId) {
    const lead = getLead(leadId);
    if (!lead) return;

    lead.ownerId = null;
    lead.ownerName = null;
    lead.leadState = 'fresh'; // RED: Fresh

    showToast(`🔴 Lead released back to Open Pool (Fresh).`);
    renderConversationsList();
    renderActiveChat();
    renderAgentProfile();
    syncLeadToServer(lead, { ownerName: null });
  }

  // Mark Lead as Paid / Closed -> Transitions to GREEN (Closed / Paid)
  function markLeadPaid(leadId) {
    const lead = getLead(leadId);
    if (!lead) return;

    lead.leadState = 'paid';
    lead.status = 'paid';
    if (!lead.ownerId) {
      lead.ownerId = state.currentAgent.id;
    }

    lead.messages.push({
      sender: 'agent',
      agentName: state.currentAgent.name,
      text: '🎉 PAYMENT CONFIRMED: Client has successfully paid and activated Nunno Cloud! Account verified (CLOSED/PAID).',
      time: new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
    });

    showToast(`🟢 Client PAID! Lead ${lead.name} is now CLOSED (Green).`);
    renderConversationsList();
    renderActiveChat();
    renderAgentProfile();
    syncLeadToServer(lead, { newMessage: lead.messages[lead.messages.length - 1] });
  }

  // Mark Lead as Useless / Won't Pay -> Transitions to BLUE (Useless)
  function markLeadUseless(leadId) {
    const lead = getLead(leadId);
    if (!lead) return;

    lead.leadState = 'useless';
    lead.status = 'useless';
    if (!lead.ownerId) {
      lead.ownerId = state.currentAgent.id;
    }

    lead.messages.push({
      sender: 'agent',
      agentName: state.currentAgent.name,
      text: '🚫 CLIENT MARKED USELESS: Handled by agent, but client will not pay / uninterested / dead lead.',
      time: new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
    });

    showToast(`🔵 Lead marked USELESS (Won't Pay).`);
    renderConversationsList();
    renderActiveChat();
    renderAgentProfile();
    syncLeadToServer(lead, { newMessage: lead.messages[lead.messages.length - 1] });
  }

  // Universal Lifecycle State Switcher
  function setLeadState(leadId, targetState) {
    const lead = getLead(leadId);
    if (!lead) return;

    if (targetState === 'fresh') {
      releaseLead(leadId);
    } else if (targetState === 'ongoing') {
      claimLead(leadId);
    } else if (targetState === 'paid') {
      markLeadPaid(leadId);
    } else if (targetState === 'useless') {
      markLeadUseless(leadId);
    }
  }

  // Reopen Lead (Toggle from Paid/Useless back to Ongoing)
  function reopenLead(leadId) {
    const lead = getLead(leadId);
    if (!lead) return;

    lead.leadState = 'ongoing';
    lead.status = 'replied';

    showToast(`🟡 Lead re-opened to Ongoing.`);
    renderConversationsList();
    renderActiveChat();
    renderAgentProfile();
    syncLeadToServer(lead);
  }

  // ==========================================
  // INBOX & CONVERSATIONS LIST
  // ==========================================

  function renderConversationsList() {
    if (!els.conversationsScroll) return;

    // Keep the inbox ordered by the latest message, including updates on an
    // existing chat, so active conversations rise immediately after sync.
    state.leads.sort((a, b) => {
      const latest = lead => Number(lead.lastMessageAt || lead.messages?.[lead.messages.length - 1]?.timestamp || 0);
      return latest(b) - latest(a);
    });

    // Filter leads
    let filtered = state.leads.filter(l => {
      // Search match
      if (state.searchQuery) {
        const q = state.searchQuery.toLowerCase();
        const matches = l.name.toLowerCase().includes(q) ||
                        l.phone.includes(q);
        if (!matches) return false;
      }

      const lState = getLeadState(l);

      // Traffic-light filter tabs
      if (state.inboxFilter === 'all') return true;
      if (state.inboxFilter === 'fresh') return lState === 'fresh';
      if (state.inboxFilter === 'ongoing') return lState === 'ongoing';
      if (state.inboxFilter === 'pending_verification' || state.inboxFilter === 'pending') return lState === 'pending_verification';
      if (state.inboxFilter === 'paid') return lState === 'paid';
      if (state.inboxFilter === 'useless') return lState === 'useless';
      if (state.inboxFilter === 'mine') {
        const myId = state.currentUser ? state.currentUser.id : state.currentAgent.id;
        return l.ownerId === myId || l.paidDetails?.closedById === myId;
      }
      return true;
    });

    // Update Subbar KPI widgets and badges
    const countAll = state.leads.length;
    const countFresh = state.leads.filter(l => getLeadState(l) === 'fresh').length;
    const countOngoing = state.leads.filter(l => getLeadState(l) === 'ongoing').length;
    const countPending = state.leads.filter(l => getLeadState(l) === 'pending_verification').length;
    const countPaid = state.leads.filter(l => getLeadState(l) === 'paid').length;
    const countUseless = state.leads.filter(l => getLeadState(l) === 'useless').length;
    const currentUserId = state.currentUser ? state.currentUser.id : state.currentAgent.id;
    const countMine = state.leads.filter(l => l.ownerId === currentUserId || l.paidDetails?.closedById === currentUserId).length;

    if (els.kpiCountAll) els.kpiCountAll.textContent = countAll;
    if (els.kpiCountFresh) els.kpiCountFresh.textContent = countFresh;
    if (els.kpiCountOngoing) els.kpiCountOngoing.textContent = countOngoing;
    if (els.kpiCountPending) els.kpiCountPending.textContent = countPending;
    if (els.kpiCountPaid) els.kpiCountPaid.textContent = countPaid;
    if (els.kpiCountUseless) els.kpiCountUseless.textContent = countUseless;
    if (els.myLeadsCountBadge) els.myLeadsCountBadge.textContent = countMine;
    if (els.pendingVerifCount) els.pendingVerifCount.textContent = countPending;
    if (els.pendingAuditHeaderCount) els.pendingAuditHeaderCount.textContent = `${countPending} Pending Deals`;
    if (els.convTotalBadge) els.convTotalBadge.textContent = `${filtered.length} Leads`;

    // Sync active class on filter buttons & KPI pills
    if (els.inboxFilterBtns) {
      els.inboxFilterBtns.forEach(btn => {
        btn.classList.toggle('active', btn.getAttribute('data-filter') === state.inboxFilter);
      });
    }
    if (els.kpiFilterBtns) {
      els.kpiFilterBtns.forEach(pill => {
        pill.classList.toggle('active', pill.getAttribute('data-filter') === state.inboxFilter);
      });
    }

    els.conversationsScroll.innerHTML = '';

    if (!filtered.length) {
      els.conversationsScroll.innerHTML = `
        <div style="padding:40px 20px;text-align:center;color:var(--text-dim);">
          <div style="font-size:24px;margin-bottom:8px;">📭</div>
          <p class="text-sm">No conversations in this section.</p>
        </div>
      `;
      return;
    }

    filtered.forEach(lead => {
      const isSelected = lead.id === state.activeLeadId;
      const lastMsg = lead.messages.length ? lead.messages[lead.messages.length - 1] : { text: 'No messages', time: '' };
      const owner = lead.ownerId ? getAgent(lead.ownerId) : null;
      const leadState = getLeadState(lead);

      // Clean initials for avatar
      const initials = lead.name.split(' ').map(n => n[0]).join('').substring(0, 2).toUpperCase();

      // Clean 5-State Badge
      let statusBadgeHtml = '';
      if (leadState === 'fresh') {
        statusBadgeHtml = `<span class="status-pill-clean fresh">🔴 Fresh</span>`;
      } else if (leadState === 'ongoing') {
        const ownerName = owner ? owner.name.split(' ')[0] : 'Claimed';
        statusBadgeHtml = `<span class="status-pill-clean ongoing">🟡 Ongoing (${ownerName})</span>`;
      } else if (leadState === 'pending_verification') {
        const closerName = lead.paidDetails?.closedBy ? lead.paidDetails.closedBy.split(' ')[0] : (owner ? owner.name.split(' ')[0] : 'In Review');
        statusBadgeHtml = `<span class="status-pill-clean pending">⚪ In Review (${closerName})</span>`;
      } else if (leadState === 'paid') {
        statusBadgeHtml = `<span class="status-pill-clean paid">🟢 Paid</span>`;
      } else if (leadState === 'useless') {
        statusBadgeHtml = `<span class="status-pill-clean useless">🔵 Won't Pay</span>`;
      }

      const rawText = lastMsg.text || '';
      const cleanPreview = rawText.replace(/[\r\n\t]+/g, ' ').replace(/\s{2,}/g, ' ').trim();

      const previewHtml = lastMsg.type === 'audio'
        ? `<span class="conv-preview-text is-audio"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"/><path d="M19 10v2a7 7 0 0 1-14 0v-2"/></svg> Voice note (${lastMsg.duration || '0:20'})</span>`
        : `<span class="conv-preview-text" title="${escapeHtml(cleanPreview)}">${escapeHtml(cleanPreview)}</span>`;

      const item = document.createElement('div');
      item.className = `conv-item state-${leadState} ${isSelected ? 'active' : ''}`;
      item.onclick = () => selectLead(lead.id);

      item.innerHTML = `
        <div class="conv-avatar-wrap">
          <span class="avatar-initials">${initials}</span>
          <div class="lead-status-dot status-${leadState}" title="${leadState === 'fresh' ? '🔴 Fresh (No one claimed)' : leadState === 'ongoing' ? '🟡 Ongoing (In Progress)' : leadState === 'pending_verification' ? '⚪ In Review (Awaiting Admin Verification)' : leadState === 'paid' ? '🟢 Closed (Client Paid)' : '🔵 Won\'t Pay'}"></div>
          ${lead.unreadCount > 0 ? `<div class="unread-badge">${lead.unreadCount}</div>` : ''}
        </div>
        <div class="conv-body">
          <div class="conv-top-row">
            <span class="conv-contact-name">${lead.name}</span>
            <span class="conv-timestamp">${lastMsg.time || lead.lastActive}</span>
          </div>
          <div class="conv-preview-row">
            ${previewHtml}
          </div>
          <div class="conv-bottom-row">
            <span class="mono text-xs text-muted">${lead.display}</span>
            ${statusBadgeHtml}
          </div>
        </div>
      `;
      els.conversationsScroll.appendChild(item);
    });
  }

  // Select Lead
  function selectLead(leadId) {
    state.activeLeadId = leadId;
    const lead = getLead(leadId);
    if (lead && lead.unreadCount > 0) {
      lead.unreadCount = 0;
    }
    // On mobile screens, activate full-screen chat thread and collapse header chrome
    if (els.viewInbox) {
      els.viewInbox.classList.add('mobile-chat-open');
    }
    document.body.classList.add('mobile-in-chat');

    // On drawer screens, close the drawer when a new lead is selected so chat is visible
    if (window.innerWidth <= 1180) {
      toggleDossierDrawer(false);
    }

    const transition = document.getElementById('chatTransitionOverlay');
    if (transition) {
      transition.classList.add('active');
      transition.setAttribute('aria-hidden', 'false');
      window.clearTimeout(selectLead.transitionTimer);
      selectLead.transitionTimer = window.setTimeout(() => {
        transition.classList.remove('active');
        transition.setAttribute('aria-hidden', 'true');
      }, 420);
    }

    renderConversationsList();
    renderActiveChat();
    renderAgentProfile();
  }

  // ==========================================
  // ACTIVE CHAT & LEAD DOSSIER
  // ==========================================

  function renderActiveChat() {
    const lead = getLead(state.activeLeadId);
    if (!lead) {
      if (els.chatLeadName) els.chatLeadName.textContent = 'Select a conversation';
      if (els.chatLeadPhone) els.chatLeadPhone.textContent = '';
      if (els.chatMessagesArea) els.chatMessagesArea.textContent = 'Connect WhatsApp to sync recent conversations, or start a campaign.';
      return;
    }
    const presenceEl = document.getElementById('chatPresence');
    if (presenceEl && presenceEl.dataset.phone !== lead.phone) {
      presenceEl.dataset.phone = lead.phone;
      presenceEl.textContent = 'Checking online / last seen…'; presenceEl.className = 'chat-presence';
    }

    const leadState = getLeadState(lead);
    const owner = lead.ownerId ? getAgent(lead.ownerId) : null;
    const initials = lead.name.split(' ').map(n => n[0]).join('').substring(0, 2).toUpperCase();

    // Header Info
    if (els.chatLeadAvatar) els.chatLeadAvatar.textContent = initials;
    if (els.chatLeadDot) els.chatLeadDot.className = `lead-status-dot status-${leadState}`;
    if (els.chatLeadName) els.chatLeadName.textContent = lead.name;
    if (els.chatLeadPhone) els.chatLeadPhone.textContent = lead.display;

    if (els.chatLeadStatusBadge) {
      if (leadState === 'fresh') {
        els.chatLeadStatusBadge.className = 'status-pill-clean fresh';
        els.chatLeadStatusBadge.innerHTML = '🔴 Fresh Lead';
      } else if (leadState === 'ongoing') {
        els.chatLeadStatusBadge.className = 'status-pill-clean ongoing';
        els.chatLeadStatusBadge.innerHTML = '🟡 Ongoing';
      } else if (leadState === 'pending_verification') {
        els.chatLeadStatusBadge.className = 'status-pill-clean pending';
        els.chatLeadStatusBadge.innerHTML = '⚪ In Review (Pending Verification)';
      } else if (leadState === 'paid') {
        els.chatLeadStatusBadge.className = 'status-pill-clean paid';
        els.chatLeadStatusBadge.innerHTML = '🟢 Closed (Paid)';
      } else if (leadState === 'useless') {
        els.chatLeadStatusBadge.className = 'status-pill-clean useless';
        els.chatLeadStatusBadge.innerHTML = "🔵 Won't Pay";
      }
    }

    const currentUserId = state.currentUser ? state.currentUser.id : state.currentAgent.id;
    const currentRawRole = (state.currentUser && state.currentUser.designation) || (state.currentUser && state.currentUser.role) || state.currentAgent.role || 'Team Member';
    const currentRole = normalizeRole(currentRawRole);
    const isAdmin = currentRole === 'Admin';
    const isOwner = lead.ownerId === currentUserId;
    const isLockedOngoing = leadState === 'ongoing' && lead.ownerId && !isOwner;
    const closerDisplayName = owner ? owner.name : (lead.ownerName || lead.ownerId);

    if (els.chatLeadCloser) {
      if (leadState === 'fresh') {
        els.chatLeadCloser.style.color = '#ff6688';
        els.chatLeadCloser.textContent = 'Open Pool (Unclaimed)';
      } else if (leadState === 'ongoing') {
        els.chatLeadCloser.style.color = '#fbbf24';
        els.chatLeadCloser.textContent = isOwner ? `🔒 You (${closerDisplayName})` : `🔒 ${closerDisplayName}`;
      } else if (leadState === 'pending_verification') {
        els.chatLeadCloser.style.color = '#cbd5e1';
        const displayCloser = lead.paidDetails?.closedBy || closerDisplayName || 'Closer';
        els.chatLeadCloser.textContent = `⚪ In Review (${displayCloser})`;
      } else if (leadState === 'paid') {
        els.chatLeadCloser.style.color = '#34d399';
        const displayCloser = lead.paidDetails?.closedBy || closerDisplayName || 'Closer';
        els.chatLeadCloser.textContent = `✓ Paid (${displayCloser})`;
      } else if (leadState === 'useless') {
        els.chatLeadCloser.style.color = '#38bdf8';
        els.chatLeadCloser.textContent = `🚫 Won't Pay / Dead`;
      }
    }

    // Header Claim Button
    if (els.chatClaimBtn) {
      if (leadState === 'fresh') {
        els.chatClaimBtn.style.display = 'inline-flex';
        els.chatClaimBtn.onclick = () => claimLead(lead.id);
      } else {
        els.chatClaimBtn.style.display = 'none';
      }
    }

    // Header Can't Handle Button (Only visible to the assigned closer or admin when ongoing)
    if (els.cantHandleBtn) {
      if (leadState === 'ongoing' && (isOwner || isAdmin)) {
        els.cantHandleBtn.style.display = 'inline-flex';
        els.cantHandleBtn.onclick = () => openCantHandleModal(lead);
      } else {
        els.cantHandleBtn.style.display = 'none';
      }
    }

    // Header Won't Pay Button
    if (els.markUselessHeaderBtn) {
      if (isLockedOngoing && !isAdmin) {
        els.markUselessHeaderBtn.style.display = 'none';
      } else if (leadState === 'useless') {
        els.markUselessHeaderBtn.style.display = 'inline-flex';
        els.markUselessHeaderBtn.className = 'btn btn-ghost btn-xs';
        els.markUselessHeaderBtn.style.color = '#38bdf8';
        els.markUselessHeaderBtn.style.borderColor = 'rgba(56, 189, 248, 0.4)';
        els.markUselessHeaderBtn.innerHTML = `🔵 Won't Pay ✓`;
        els.markUselessHeaderBtn.onclick = () => reopenLead(lead.id);
      } else {
        els.markUselessHeaderBtn.style.display = 'inline-flex';
        els.markUselessHeaderBtn.className = 'btn btn-useless btn-xs';
        els.markUselessHeaderBtn.style.color = '#ffffff';
        els.markUselessHeaderBtn.style.borderColor = 'transparent';
        els.markUselessHeaderBtn.innerHTML = `Won't Pay`;
        els.markUselessHeaderBtn.onclick = () => markLeadUseless(lead.id);
      }
    }

    // Header Verification & Mark Paid Buttons
    if (leadState === 'pending_verification') {
      if (isAdmin) {
        if (els.verifyPaidHeaderBtn) {
          els.verifyPaidHeaderBtn.style.display = 'inline-flex';
          els.verifyPaidHeaderBtn.onclick = () => verifyPaymentDeal(lead.id);
        }
        if (els.rejectPaidHeaderBtn) {
          els.rejectPaidHeaderBtn.style.display = 'inline-flex';
          els.rejectPaidHeaderBtn.onclick = () => rejectFakeDeal(lead.id);
        }
        if (els.markPaidHeaderBtn) els.markPaidHeaderBtn.style.display = 'none';
      } else {
        if (els.verifyPaidHeaderBtn) els.verifyPaidHeaderBtn.style.display = 'none';
        if (els.rejectPaidHeaderBtn) els.rejectPaidHeaderBtn.style.display = 'none';
        if (els.markPaidHeaderBtn) {
          els.markPaidHeaderBtn.style.display = 'inline-flex';
          els.markPaidHeaderBtn.className = 'btn btn-ghost btn-xs';
          els.markPaidHeaderBtn.style.color = '#cbd5e1';
          els.markPaidHeaderBtn.style.borderColor = 'rgba(148, 163, 184, 0.4)';
          els.markPaidHeaderBtn.innerHTML = `⚪ In Review`;
          els.markPaidHeaderBtn.onclick = () => openMarkPaidModal(lead);
        }
      }
    } else {
      if (els.verifyPaidHeaderBtn) els.verifyPaidHeaderBtn.style.display = 'none';
      if (els.rejectPaidHeaderBtn) els.rejectPaidHeaderBtn.style.display = 'none';
      if (els.markPaidHeaderBtn) {
        if (isLockedOngoing && !isAdmin) {
          els.markPaidHeaderBtn.style.display = 'none';
        } else if (leadState === 'paid') {
          els.markPaidHeaderBtn.style.display = 'inline-flex';
          els.markPaidHeaderBtn.className = 'btn btn-ghost btn-xs';
          els.markPaidHeaderBtn.style.color = '#10b981';
          els.markPaidHeaderBtn.style.borderColor = 'rgba(16, 185, 129, 0.4)';
          els.markPaidHeaderBtn.innerHTML = `🟢 Paid ✓`;
          els.markPaidHeaderBtn.onclick = () => openMarkPaidModal(lead);
        } else {
          els.markPaidHeaderBtn.style.display = 'inline-flex';
          els.markPaidHeaderBtn.className = 'btn btn-paid btn-xs';
          els.markPaidHeaderBtn.style.color = '#ffffff';
          els.markPaidHeaderBtn.style.borderColor = 'transparent';
          els.markPaidHeaderBtn.innerHTML = `Mark Paid`;
          els.markPaidHeaderBtn.onclick = () => openMarkPaidModal(lead);
        }
      }
    }

    // Quick Macro Bar & Message Composer Bar
    const quickBar = document.querySelector('.quick-reply-bar');
    const compBar = document.querySelector('.chat-composer-bar');

    // ANTI-THEFT LEAD LOCKING ENFORCEMENT
    if (isLockedOngoing && !isAdmin) {
      // UNAUTHORIZED CLOSER: Show Anti-Theft Security Shield
      if (els.leadLockedShield) {
        els.leadLockedShield.style.display = 'flex';
        if (els.lockedCloserName) els.lockedCloserName.textContent = closerDisplayName;
      }
      if (els.chatMessagesArea) els.chatMessagesArea.style.display = 'none';
      if (quickBar) quickBar.style.display = 'none';
      if (compBar) compBar.style.display = 'none';
      if (els.otherCloserNotice) els.otherCloserNotice.style.display = 'none';
    } else {
      // AUTHORIZED: Closer, Admin, or Fresh/Paid/Useless Lead
      if (els.leadLockedShield) els.leadLockedShield.style.display = 'none';
      if (els.chatMessagesArea) els.chatMessagesArea.style.display = 'flex';
      if (quickBar) quickBar.style.display = 'flex';
      if (compBar) compBar.style.display = 'flex';

      // Admin Monitoring Notice
      if (els.otherCloserNotice) {
        if (isLockedOngoing && isAdmin) {
          els.otherCloserNotice.style.display = 'block';
          if (els.otherCloserName) els.otherCloserName.textContent = closerDisplayName;
        } else {
          els.otherCloserNotice.style.display = 'none';
        }
      }

      if (els.chatInput) {
        els.chatInput.disabled = false;
        els.chatInput.placeholder = 'Write a message…';
      }
      if (els.sendMsgBtn) els.sendMsgBtn.disabled = false;
    }

    // Render Messages Thread
    if (els.chatMessagesArea && (!isLockedOngoing || isAdmin)) {
      els.chatMessagesArea.innerHTML = `
        <div class="msg-date-divider">TODAY • ENCRYPTED WHATSAPP THREAD</div>
      `;

      lead.messages.forEach((msg, idx) => {
        const bubble = document.createElement('div');
        const isInbound = msg.sender === 'lead';
        bubble.className = `msg-bubble ${isInbound ? 'msg-inbound' : 'msg-outbound'}`;
        const tickState = msg.deliveryStatus;
        const deliveryMarkup = isInbound ? '' : window.NUNNO_OFFLINE_PREVIEW ? '<span>🧪 simulated</span>'
          : tickState === 'read' ? '<span title="Read" style="color:#53bdeb;font-size:12px;">✓✓</span>'
          : tickState === 'delivered' ? '<span title="Delivered; not confirmed read" style="color:#8696a0;font-size:12px;">✓✓</span>'
          : tickState === 'sent' ? '<span title="Sent; not delivered yet" style="color:#8696a0;font-size:12px;">✓</span>'
          : ['sending','accepted'].includes(tickState) ? '<span title="Submitting; waiting for server acknowledgement" style="color:#8696a0;">◷</span>'
          : tickState === 'failed' ? '<span title="Sending failed" style="color:#ef4444;">!</span>'
          : '<span title="No confirmed delivery status" style="color:#8696a0;font-size:10px;">unconfirmed</span>';

        if (msg.type === 'audio') {
          bubble.classList.add('msg-audio-bubble');
          const msgId = msg.id || (`msg_audio_${idx}_${(msg.time || '').replace(/[^a-zA-Z0-9]/g, '')}`);
          msg.id = msgId;
          const durSec = msg.durationSeconds || parseDurationToSeconds(msg.duration || '0:20');
          const durDisplay = msg.duration || formatSeconds(durSec);
          const isCurrentlyPlaying = activeAudio.msgId === msgId;

          bubble.innerHTML = `
            ${!isInbound ? `<div class="msg-sender-tag">${escapeHtml(msg.agentName || 'Agent')}</div>` : `<div class="msg-sender-tag" style="color:#53bdeb;">🎙️ Client Voice Note</div>`}
            <div class="voice-player" id="vp_${msgId}" data-msg-id="${msgId}">
              <button type="button" class="vn-play-btn" data-vn-id="${msgId}" title="Play/Pause Voice Note">
                <svg class="vn-icon-play" width="16" height="16" viewBox="0 0 24 24" fill="currentColor" style="${isCurrentlyPlaying ? 'display:none;' : ''}"><polygon points="6 4 20 12 6 20 6 4"/></svg>
                <svg class="vn-icon-pause" width="16" height="16" viewBox="0 0 24 24" fill="currentColor" style="${isCurrentlyPlaying ? '' : 'display:none;'}"><rect x="6" y="4" width="4" height="16"/><rect x="14" y="4" width="4" height="16"/></svg>
              </button>

              <div class="vn-body">
                <div class="vn-waveform-track" data-track-id="${msgId}" title="Click to seek">
                  <div class="vn-waveform-bars" id="vnbars_${msgId}">
                    ${generateWaveformBarsHtml(durSec)}
                  </div>
                </div>
                <div class="vn-info-row">
                  <span class="vn-timer" id="vntimer_${msgId}">${durDisplay}</span>
                  <button type="button" class="vn-speed-btn" data-speed-id="${msgId}" title="Toggle playback speed (1x / 1.5x / 2x)">${activeAudio.currentRate || 1}x</button>
                </div>
              </div>

              <div class="vn-mic-badge-wrap">
                <div class="vn-avatar">${isInbound ? '👤' : (state.currentAgent?.avatar || '🎧')}</div>
                <div class="vn-mic-badge">🎤</div>
              </div>
            </div>
            <div class="msg-meta-row">
              <span>${escapeHtml(msg.time)}</span>
              ${deliveryMarkup}
            </div>
          `;
        } else {
          const isDoc = msg.type === 'document';
          const isImg = msg.type === 'image';
          const isVideo = msg.type === 'video';
          const hasCustomCaption = msg.text && msg.text !== 'Image' && msg.text !== 'Voice note' && msg.text !== 'Document' && msg.text !== msg.filename;

          bubble.innerHTML = `
            ${!isInbound ? `<div class="msg-sender-tag">${escapeHtml(msg.agentName || 'Agent')}</div>` : ''}
            ${isImg ? (msg.imageUrl ? `
              <div class="chat-img-card" data-img-src="${escapeHtml(msg.imageUrl)}" data-img-name="${escapeHtml(msg.filename || 'image.jpg')}" style="position:relative;display:inline-block;cursor:pointer;border-radius:10px;overflow:hidden;background:rgba(0,0,0,0.12);max-width:320px;">
                <img src="${escapeHtml(msg.imageUrl)}" alt="Chat image" style="max-width:320px;max-height:360px;width:auto;height:auto;border-radius:10px;display:block;" onload="const p=this.closest('.chat-messages-area');if(p){p.scrollTop=p.scrollHeight;}">
                <div class="chat-img-overlay" style="position:absolute;inset:0;background:rgba(0,0,0,0.3);opacity:0;transition:opacity 0.2s;display:flex;align-items:center;justify-content:center;color:#fff;font-size:11.5px;font-weight:700;">
                  <span style="background:rgba(0,0,0,0.7);padding:4px 12px;border-radius:20px;box-shadow:0 2px 6px rgba(0,0,0,0.4);">🔍 Click to enlarge</span>
                </div>
              </div>` : `<div>${escapeHtml(msg.mediaError || 'Loading image…')}</div>`) : ''}
            ${isVideo ? (msg.videoUrl ? `<video src="${escapeHtml(msg.videoUrl)}" controls preload="metadata" style="max-width:280px;max-height:300px;border-radius:10px;display:block;"></video>` : `<div>${escapeHtml(msg.mediaError || 'Loading video…')}</div>`) : ''}
            ${isDoc ? `
              <div class="msg-doc-card" style="display:flex;align-items:center;gap:10px;background:rgba(255,255,255,0.07);border:1px solid rgba(255,255,255,0.12);padding:10px 12px;border-radius:10px;margin-bottom:6px;">
                <span style="font-size:24px;">📄</span>
                <div style="flex:1;min-width:0;">
                  <div style="font-weight:600;font-size:13px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">${escapeHtml(msg.filename || msg.text || 'Document')}</div>
                  <div style="font-size:11px;opacity:0.75;">Document Attachment</div>
                </div>
                ${msg.docUrl ? `<a href="${escapeHtml(msg.docUrl)}" download="${escapeHtml(msg.filename || 'document')}" class="btn btn-xs" style="padding:4px 8px;background:rgba(255,255,255,0.15);color:#fff;border-radius:6px;text-decoration:none;">⬇</a>` : ''}
              </div>` : ''}
            ${hasCustomCaption || (!isDoc && !isImg && !isVideo) ? `<div>${formatText(msg.text)}</div>` : ''}
            <div class="msg-meta-row">
              <span>${escapeHtml(msg.time)}</span>
              ${deliveryMarkup}
            </div>
          `;
        }

        els.chatMessagesArea.appendChild(bubble);
      });

      // Attach voice note interaction handlers
      els.chatMessagesArea.querySelectorAll('.vn-play-btn').forEach(btn => {
        btn.addEventListener('click', (e) => {
          e.stopPropagation();
          const id = btn.getAttribute('data-vn-id');
          if (id) playVoiceNote(id);
        });
      });
      els.chatMessagesArea.querySelectorAll('.vn-speed-btn').forEach(btn => {
        btn.addEventListener('click', (e) => {
          e.stopPropagation();
          const id = btn.getAttribute('data-speed-id');
          if (id) cyclePlaybackSpeed(id);
        });
      });
      els.chatMessagesArea.querySelectorAll('.vn-waveform-track').forEach(track => {
        track.addEventListener('click', (e) => {
          e.stopPropagation();
          const id = track.getAttribute('data-track-id');
          if (id) seekAudio(id, e);
      els.chatMessagesArea.querySelectorAll('.chat-img-card').forEach(card => {
        card.addEventListener('mouseenter', () => {
          const overlay = card.querySelector('.chat-img-overlay');
          if (overlay) overlay.style.opacity = '1';
        });
        card.addEventListener('mouseleave', () => {
          const overlay = card.querySelector('.chat-img-overlay');
          if (overlay) overlay.style.opacity = '0';
        });
        card.addEventListener('click', (e) => {
          e.stopPropagation();
          const src = card.getAttribute('data-img-src');
          const name = card.getAttribute('data-img-name');
          if (src) openImageLightbox(src, name);
        });
      });

      els.chatMessagesArea.scrollTop = els.chatMessagesArea.scrollHeight;
      requestAnimationFrame(() => {
        if (els.chatMessagesArea) els.chatMessagesArea.scrollTop = els.chatMessagesArea.scrollHeight;
      });
    }

    // ==============================
    // RIGHT CRM DOSSIER WIDGETS
    // ==============================

    // WIDGET 1: Status Matrix & Deal Value
    if (els.dossierInterest) els.dossierInterest.textContent = lead.interest || 'Nunno Pro';

    if (els.dossierStatusPill) {
      if (leadState === 'fresh') els.dossierStatusPill.innerHTML = `<span class="status-pill-clean fresh">🔴 FRESH</span>`;
      else if (leadState === 'ongoing') els.dossierStatusPill.innerHTML = `<span class="status-pill-clean ongoing">🟡 ONGOING</span>`;
      else if (leadState === 'pending_verification') els.dossierStatusPill.innerHTML = `<span class="status-pill-clean pending">⚪ IN REVIEW</span>`;
      else if (leadState === 'paid') els.dossierStatusPill.innerHTML = `<span class="status-pill-clean paid">🟢 PAID</span>`;
      else if (leadState === 'useless') els.dossierStatusPill.innerHTML = `<span class="status-pill-clean useless">🔵 WON'T PAY</span>`;
    }

    if (els.matrixBtns) {
      els.matrixBtns.forEach(btn => {
        btn.classList.toggle('active', btn.getAttribute('data-set-state') === leadState);
      });
    }

    if (els.dossierDealValue) {
      if (lead.interest && lead.interest.includes('Starter')) {
        els.dossierDealValue.textContent = 'PKR 1,700';
      } else if (lead.interest && lead.interest.includes('Whale')) {
        els.dossierDealValue.textContent = 'PKR 15,000';
      } else {
        els.dossierDealValue.textContent = 'PKR 5,000';
      }
    }

    // WIDGET 2: Assigned Closer & Anti-Theft Lock
    if (els.dossierOwnerAvatar) els.dossierOwnerAvatar.textContent = owner ? owner.avatar : '👤';
    if (els.dossierOwner) els.dossierOwner.textContent = owner ? owner.name : 'Open Pool (Unclaimed)';
    if (els.dossierOwnerRole) els.dossierOwnerRole.textContent = owner ? `${owner.role} • ${owner.id}` : 'Available for any closer to take';

    if (els.dossierLockBadge) {
      if (!lead.ownerId) {
        els.dossierLockBadge.className = 'agent-lock-badge open';
        els.dossierLockBadge.textContent = '🟢 Open Pool';
      } else if (lead.ownerId === state.currentAgent.id) {
        els.dossierLockBadge.className = 'agent-lock-badge mine';
        els.dossierLockBadge.textContent = '🔒 Locked to You';
      } else {
        els.dossierLockBadge.className = 'agent-lock-badge locked';
        els.dossierLockBadge.textContent = `🔒 Locked (${owner ? owner.name.split(' ')[0] : lead.ownerId})`;
      }
    }

    if (els.dossierClaimBtn) {
      if (leadState === 'fresh') {
        els.dossierClaimBtn.style.display = 'block';
        els.dossierClaimBtn.onclick = () => claimLead(lead.id);
      } else {
        els.dossierClaimBtn.style.display = 'none';
      }
    }

    if (els.dossierReleaseBtn) {
      if (leadState === 'ongoing' && (lead.ownerId === currentUserId || isAdmin)) {
        els.dossierReleaseBtn.style.display = 'block';
        els.dossierReleaseBtn.onclick = () => openCantHandleModal(lead);
      } else {
        els.dossierReleaseBtn.style.display = 'none';
      }
    }

    // WIDGET 3: Power Conversion Buttons & Admin Audit Controls
    if (leadState === 'pending_verification') {
      if (isAdmin) {
        if (els.dossierAdminVerifActions) els.dossierAdminVerifActions.style.display = 'flex';
        if (els.dossierVerifyBtn) els.dossierVerifyBtn.onclick = () => verifyPaymentDeal(lead.id);
        if (els.dossierRejectBtn) els.dossierRejectBtn.onclick = () => rejectFakeDeal(lead.id);
        if (els.dossierPendingNotice) els.dossierPendingNotice.style.display = 'none';
        if (els.dossierPaidBtn) els.dossierPaidBtn.style.display = 'none';
      } else {
        if (els.dossierAdminVerifActions) els.dossierAdminVerifActions.style.display = 'none';
        if (els.dossierPendingNotice) els.dossierPendingNotice.style.display = 'block';
        if (els.dossierPaidBtn) els.dossierPaidBtn.style.display = 'none';
      }
    } else {
      if (els.dossierAdminVerifActions) els.dossierAdminVerifActions.style.display = 'none';
      if (els.dossierPendingNotice) els.dossierPendingNotice.style.display = 'none';
      if (els.dossierPaidBtn) {
        els.dossierPaidBtn.style.display = 'block';
        if (leadState === 'paid') {
          els.dossierPaidBtn.className = 'btn btn-ghost btn-sm';
          els.dossierPaidBtn.innerHTML = 'Re-open Lead to Ongoing';
          els.dossierPaidBtn.onclick = () => reopenLead(lead.id);
        } else {
          els.dossierPaidBtn.className = 'btn btn-paid btn-sm';
          els.dossierPaidBtn.innerHTML = '✅ Record Sale (Submit Verification)';
          els.dossierPaidBtn.onclick = () => openMarkPaidModal(lead);
        }
      }
    }

    if (els.dossierUselessBtn) {
      if (leadState === 'useless') {
        els.dossierUselessBtn.className = 'btn btn-ghost btn-sm';
        els.dossierUselessBtn.innerHTML = 'Re-open Lead to Ongoing';
        els.dossierUselessBtn.onclick = () => reopenLead(lead.id);
      } else {
        els.dossierUselessBtn.className = 'btn btn-useless btn-sm';
        els.dossierUselessBtn.innerHTML = "🚫 Mark as Useless (Won't Pay)";
        els.dossierUselessBtn.onclick = () => markLeadUseless(lead.id);
      }
    }

    // WIDGET 4: Closer Notes
    if (els.dossierNotes) {
      els.dossierNotes.value = lead.notes || '';
    }
  }

  // Format WhatsApp markdown
  function formatText(str) {
    if (!str) return '';
    return str
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/\*(.*?)\*/g, '<strong>$1</strong>')
      .replace(/_(.*?)_/g, '<em>$1</em>')
      .replace(/\n/g, '<br>');
  }

  // Send Message in Active Chat
  function describeSendError(error) {
    const message = error && error.message ? error.message : 'Unknown network error';
    if (message === 'Failed to fetch' || message.includes('NetworkError')) {
      if (window.location.protocol === 'file:') {
        return 'This page is opened as a local file, so it cannot reach the PHP sender. Run the app through PHP (index.php) on localhost or your HTTPS host.';
      }
      return 'Could not reach api/direct_sender.php. Open the app through its PHP/HTTPS host and confirm that the PHP API endpoint is online.';
    }
    return message;
  }

  async function sendMessage() {
    if (els.sendMsgBtn && els.sendMsgBtn.disabled) return;
    const lead = getLead(state.activeLeadId);
    if (!lead) return;

    const myId = state.currentUser ? state.currentUser.id : state.currentAgent.id;
    const myName = state.currentUser ? state.currentUser.name : state.currentAgent.name;
    const isAdmin = state.currentUser && state.currentUser.role === 'admin';

    // Check lock
    if (lead.ownerId && lead.ownerId !== myId && !isAdmin) {
      showToast('⚠️ You cannot send messages to a lead owned by another agent!');
      return;
    }

    const text = els.chatInput.value.trim();
    if (!text) return;

    if (els.sendMsgBtn) els.sendMsgBtn.disabled = true;
    let sendResult;
    try {
      const response = await fetch('api/direct_sender.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'send_direct', phone: lead.phone, message: text, request_id: crypto.randomUUID() })
      });
      sendResult = await response.json();
      if (!response.ok || !sendResult.success) throw new Error(sendResult.message || `WhatsApp API returned HTTP ${response.status}`);
    } catch (error) {
      showToast(`❌ WhatsApp message was not sent: ${describeSendError(error)}`);
      return;
    } finally {
      if (els.sendMsgBtn) els.sendMsgBtn.disabled = false;
    }

    // If unclaimed, automatically claim when agent responds
    if (!lead.ownerId) {
      lead.ownerId = myId;
      lead.ownerName = myName;
      lead.leadState = 'ongoing';
      showToast(`🎯 You responded first! Lead automatically locked to you.`);
    }

    const now = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });

    const crmMessage = {
      id: `msg_${Date.now()}`,
      waMessageId: sendResult.message_id,
      deliveryStatus: 'accepted',
      sender: 'agent',
      agentName: myName,
      text: text,
      time: now
    };
    if (!lead.messages.some(m => m.waMessageId === crmMessage.waMessageId)) lead.messages.push(crmMessage);

    lead.status = 'replied';
    lead.lastActive = 'Just now';

    els.chatInput.value = '';
    renderConversationsList();
    renderActiveChat();
    syncLeadToServer(lead, { newMessage: crmMessage });
    watchCampaignDelivery({
      id: crmMessage.id,
      phone: lead.phone,
      display: lead.display || lead.phone,
      name: lead.name,
      messageId: sendResult.message_id,
      isCampaign: false
    }, 0);
    showToast('WhatsApp accepted the message; waiting for delivery confirmation.');
  }

  // Quick Macro Chips
  els.macroChips.forEach(chip => {
    chip.addEventListener('click', () => {
      const macro = chip.getAttribute('data-macro');
      if (els.chatInput) {
        els.chatInput.value = macro;
        els.chatInput.focus();
      }
    });
  });

  // Event Listeners for Chat
  if (els.sendMsgBtn) els.sendMsgBtn.addEventListener('click', sendMessage);
  if (els.chatInput) {
    els.chatInput.addEventListener('keydown', (e) => {
      if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        sendMessage();
      }
    });
  }

  // Search Filter Input
  if (els.convSearchInput) {
    els.convSearchInput.addEventListener('input', (e) => {
      state.searchQuery = e.target.value;
      renderConversationsList();
    });
  }

  // Inbox Filter Buttons
  els.inboxFilterBtns.forEach(btn => {
    btn.addEventListener('click', () => {
      state.inboxFilter = btn.getAttribute('data-filter');
      renderConversationsList();
    });
  });

  // KPI Header Stat Pills
  if (els.kpiFilterBtns) {
    els.kpiFilterBtns.forEach(pill => {
      pill.addEventListener('click', () => {
        state.inboxFilter = pill.getAttribute('data-filter');
        renderConversationsList();
      });
    });
  }

  // 4-Button Segmented Status Matrix Buttons in CRM Dossier
  if (els.matrixBtns) {
    els.matrixBtns.forEach(btn => {
      btn.addEventListener('click', () => {
        const targetState = btn.getAttribute('data-set-state');
        if (targetState && state.activeLeadId) {
          setLeadState(state.activeLeadId, targetState);
        }
      });
    });
  }

  // Quick Sales Tags in CRM Dossier
  if (els.salesTags) {
    els.salesTags.forEach(tag => {
      tag.addEventListener('click', () => {
        const appendText = tag.getAttribute('data-append');
        const lead = getLead(state.activeLeadId);
        if (lead && appendText && els.dossierNotes) {
          const current = els.dossierNotes.value.trim();
          els.dossierNotes.value = current ? `${current}\n• ${appendText}` : `• ${appendText}`;
          lead.notes = els.dossierNotes.value;
          if (els.notesSavedIndicator) {
            els.notesSavedIndicator.textContent = 'Tag added & saved ✓';
            setTimeout(() => { if (els.notesSavedIndicator) els.notesSavedIndicator.textContent = 'Auto-saved'; }, 1500);
          }
        }
      });
    });
  }

  // Save Notes on Lead Dossier
  if (els.dossierNotes) {
    els.dossierNotes.addEventListener('input', () => {
      const lead = getLead(state.activeLeadId);
      if (lead) {
        lead.notes = els.dossierNotes.value;
        if (els.notesSavedIndicator) {
          els.notesSavedIndicator.textContent = 'Saving...';
          clearTimeout(window._notesTimer);
          window._notesTimer = setTimeout(() => {
            if (els.notesSavedIndicator) els.notesSavedIndicator.textContent = 'Auto-saved ✓';
            syncLeadToServer(lead);
          }, 400);
        }
      }
    });
  }

  // ==========================================
  // EMPLOYEE SWITCHER MODAL
  // ==========================================

  function renderEmployeeModal() {
    if (!els.agentListContainer) return;
    els.agentListContainer.innerHTML = '';

    state.agents.forEach(agent => {
      const isCurrent = agent.id === state.currentAgent.id;
      const claimedLeads = state.leads.filter(l => l.ownerId === agent.id).length;

      const card = document.createElement('div');
      card.className = `employee-select-card ${isCurrent ? 'active' : ''}`;
      card.onclick = () => {
        state.currentAgent = agent;
        renderAgentProfile();
        renderConversationsList();
        renderActiveChat();
        closeAgentModal();
        showToast(`Switched active profile to ${agent.name} (${agent.id})`);
      };

      card.innerHTML = `
        <div style="display:flex;align-items:center;gap:12px;">
          <div class="agent-avatar" style="width:36px;height:36px;font-size:18px;">${agent.avatar}</div>
          <div>
            <div style="font-weight:700;color:#ffffff;font-size:13px;">${agent.name} ${isCurrent ? '<span class="brand-badge" style="font-size:9px;">Active</span>' : ''}</div>
            <div class="text-xs text-dim">${agent.role} • <span class="mono" style="color:var(--c-cyan);">${agent.id}</span></div>
          </div>
        </div>
        <div style="text-align:right;">
          <span class="mono" style="font-weight:800;color:var(--c-indigo);font-size:14px;">${claimedLeads}</span>
          <div class="text-xs text-dim">Claimed Leads</div>
        </div>
      `;
      els.agentListContainer.appendChild(card);
    });
  }

  function openAgentModal() {
    renderEmployeeModal();
    if (els.agentModal) els.agentModal.classList.add('open');
  }

  function closeAgentModal() {
    if (els.agentModal) els.agentModal.classList.remove('open');
  }

  if (els.agentPill) els.agentPill.addEventListener('click', openAgentModal);
  if (els.closeAgentModalBtn) els.closeAgentModalBtn.addEventListener('click', closeAgentModal);

  // ==========================================
  // PAKISTANI NUMBER SANITIZER & NETWORK CLASSIFIER
  // ==========================================

  // Normalize Pakistani Number
  function cleanNumber(raw) {
    if (!raw) return null;
    let clean = String(raw).replace(/\D+/g, '');
    if (!clean) return null;
    if (clean.startsWith('0092')) clean = clean.substring(2);
    else if (clean.startsWith('03') && clean.length === 11) clean = '92' + clean.substring(1);
    else if (clean.startsWith('3') && clean.length === 10) clean = '92' + clean;
    return (clean.length === 12 && clean.startsWith('923')) ? clean : null;
  }

  function getNetwork(clean) {
    if (!clean) return 'Other';
    const p = '0' + clean.substring(2, 5);
    const jazz = ['0300','0301','0302','0303','0304','0305','0306','0307','0308','0309','0320','0321','0322','0323','0324','0325','0326','0327','0328','0329'];
    const telenor = ['0340','0341','0342','0343','0344','0345','0346','0347','0348','0349'];
    const zong = ['0310','0311','0312','0313','0314','0315','0316','0317','0318'];
    const ufone = ['0330','0331','0332','0333','0334','0335','0336','0337'];
    if (jazz.includes(p)) return 'Jazz';
    if (telenor.includes(p)) return 'Telenor';
    if (zong.includes(p)) return 'Zong';
    if (ufone.includes(p)) return 'Ufone';
    return 'Other';
  }

  // ==========================================
  // CAMPAIGN STUDIO OUTREACH ENGINE (.TXT DROP, CADENCE, SPINTAX & DISPATCH)
  // ==========================================

  const campaignState = {
    name: 'OmniDesk campaign',
    audience: [],
    currentIndex: 0,
    isRunning: false,
    isPaused: false,
    minDelay: 3,
    maxDelay: 6,
    timeoutId: null,
    countdownInterval: null,
    remainingSeconds: 0,
    totalSent: 0,
    logEvents: 0
  };

  const campaignPitchTemplates = {
    custom: '',
    pro_liquidation_urdu: `{Salam|AoA|Hello} {name|Bhai|Trader}!\n\nKya aap bar bar market ke sudden wick ya stop-loss hunt se liquid ho rahe hain? 📉❌\n\n*Nunno AI Cloud* (\`nunno.cloud\`) ne Pakistan ke top traders ke liye *Liquidation Magnet System* launch kiya hai jo exact level pehle dikhata hai jahan whales target kar rahi hain.\n\n🔥 *Special Community Offer:*\n• Nunno Pro sirf *Rs. 5,000/month*\n• EasyPaisa, JazzCash aur USDT accepted\n• Coupon \`NUNNO30\` se *30% OFF*\n\nLive Demo aur Signals dekhne ke liye visit karein: https://nunno.cloud\n\n_Reply STOP agar aap mazeed updates nahi chahte._`,
    starter_scalper: `Salam {name|Bhai}! 👋\n\nAgar aap Binance ya Bybit pe 15m/5m scalping karte hain to Nunno Starter Signals sirf *Rs. 1,700* mein available hain.\n\n✅ 90%+ Accuracy Scalp setups\n✅ Instant JazzCash / EasyPaisa activation\n\nKya aapko demo charts dekhne hain? Reply YES karein!`,
    flash_discount: `⚡ *FLASH 48-HOUR TRADER PASS* ⚡\n\nAssalam o Alaikum {name}!\n\nNunno Pro Membership pe aglay 48 ghanton ke liye flat *Rs. 2,000 discount* active hai.\n\nNormal Price: Rs. 5,000\n*Flash Price: Rs. 3,000 only*\n\nPayment EasyPaisa / JazzCash se transfer karke receipt yahan share karein, 5 minute mein instant access mil jayega.`,
    whale_institutional: `Greetings {name},\n\nWe noticed high-volume positioning in Pakistani crypto traders' circles. Nunno AI Cloud offers real-time order-book liquidity heatmaps and institutional whale tracking.\n\nAccess our enterprise portal at https://nunno.cloud with code VIP2026.\n\nBest regards,\nNunno Cloud Desk`
  };

  function logCampaignTerminal(msg, type = 'info') {
    msg = previewLabel(msg);
    if (!els.campaignTerminalLog) return;
    campaignState.logEvents++;
    if (els.campaignLogCount) {
      els.campaignLogCount.textContent = `${campaignState.logEvents} events`;
    }
    const time = new Date().toLocaleTimeString();
    const line = document.createElement('div');
    line.className = `log-line log-${type}`;
    line.innerHTML = `<span style="color:#8696a0;font-size:10.5px;margin-right:4px;">[${time}]</span> ${msg}`;
    els.campaignTerminalLog.appendChild(line);
    els.campaignTerminalLog.scrollTop = els.campaignTerminalLog.scrollHeight;
  }

  function resolveSpintax(text) {
    if (!text) return '';
    return text.replace(/\{([^{}]+)\}/g, (match, contents) => {
      const lower = contents.trim().toLowerCase();
      if (['name', 'phone', 'city', 'plan', 'price', 'coupon', 'link'].includes(lower)) {
        return match;
      }
      if (contents.includes('|')) {
        const parts = contents.split('|');
        return parts[Math.floor(Math.random() * parts.length)].trim();
      }
      return contents;
    });
  }

  function personalizeMessage(template, contact) {
    if (!template) return '';
    let msg = resolveSpintax(template);

    msg = msg.replace(/\{name(?:\|([^{}]+))?\}/gi, (match, fallback) => {
      if (contact && contact.name && contact.name !== 'Trader') return contact.name;
      if (fallback) {
        const fbParts = fallback.split('|');
        return fbParts[Math.floor(Math.random() * fbParts.length)].trim();
      }
      return 'Trader';
    });

    const contactName = (contact && contact.name) ? contact.name : 'Trader';
    const contactPhone = (contact && contact.display) ? contact.display : ((contact && contact.phone) ? contact.phone : '+92 300 1234567');
    const contactCity = (contact && contact.city) ? contact.city : 'Pakistan';

    msg = msg
      .replace(/\{name\}/gi, contactName)
      .replace(/\{phone\}/gi, contactPhone)
      .replace(/\{city\}/gi, contactCity)
      .replace(/\{plan\}/gi, 'Nunno Pro')
      .replace(/\{price\}/gi, 'Rs. 5,000')
      .replace(/\{coupon\}/gi, 'NUNNO30')
      .replace(/\{link\}/gi, 'https://nunno.cloud');

    return msg;
  }

  function updateCampaignPreview() {
    if (!els.campaignBubblePreview) return;
    const rawTemplate = els.campaignCustomMsg ? els.campaignCustomMsg.value : '';
    if (!rawTemplate.trim()) {
      els.campaignBubblePreview.innerHTML = '<span class="campaign-preview-placeholder">Type a message or choose a template to preview it here.</span>';
      return;
    }

    const sampleContact = campaignState.audience.length > 0
      ? campaignState.audience[0]
      : { name: 'Sample contact', phone: '923000000000', display: '+92 300 0000000', city: 'Lahore' };

    const personalized = personalizeMessage(rawTemplate, sampleContact);

    let html = escapeHtml(personalized);
    html = html.replace(/\*([^*]+)\*/g, '<strong>$1</strong>');
    html = html.replace(/_([^_]+)_/g, '<em>$1</em>');
    html = html.replace(/`([^`]+)`/g, '<code class="campaign-inline-code">$1</code>');
    html = html.replace(/\n/g, '<br>');

    const timeStr = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    html += `<div class="campaign-preview-meta">
      <span>${timeStr}</span>
      <span class="campaign-preview-checks">✓✓</span>
    </div>`;

    els.campaignBubblePreview.innerHTML = html;
  }

  function parseContactsFromText(text) {
    if (!text) return 0;
    const lines = text.split(/[\r\n]+/);
    let added = 0;
    lines.forEach((line, idx) => {
      line = line.trim();
      if (!line) return;
      const parts = line.split(/[,\t;|]+/);
      const rawPhone = parts[0] ? parts[0].trim() : '';
      const rawName = parts[1] ? parts[1].trim() : 'Trader';
      const rawCity = parts[2] ? parts[2].trim() : 'Pakistan';

      const clean = cleanNumber(rawPhone);
      if (!clean) return;

      if (campaignState.audience.some(c => c.phone === clean)) return;

      campaignState.audience.push({
        id: `c_lead_${Date.now()}_${idx}_${Math.random().toString(36).substr(2, 4)}`,
        name: rawName,
        phone: clean,
        display: `+92 ${clean.substring(2, 5)} ${clean.substring(5)}`,
        city: rawCity,
        network: getNetwork(clean),
        status: 'pending',
        sentAt: null
      });
      added++;
    });

    renderCampaignAudienceUI();
    if (added > 0) {
      logCampaignTerminal(`✅ Parsed & queued ${added} valid target numbers into campaign. Total audience: ${campaignState.audience.length}.`, 'success');
    }
    return added;
  }

  function renderCampaignAudienceUI() {
    const count = campaignState.audience.length;
    if (els.campaignAudienceBadge) {
      els.campaignAudienceBadge.textContent = `${count} Contacts`;
    }
    if (els.campaignLoadedCountText) {
      els.campaignLoadedCountText.textContent = `${count} Numbers`;
    }

    if (count > 0) {
      if (els.campaignAudienceSummary) els.campaignAudienceSummary.style.display = 'flex';
      if (els.campaignAudienceList) els.campaignAudienceList.style.display = 'flex';
    } else {
      if (els.campaignAudienceSummary) els.campaignAudienceSummary.style.display = 'none';
      if (els.campaignAudienceList) els.campaignAudienceList.style.display = 'none';
    }

    if (els.campaignAudienceList) {
      els.campaignAudienceList.innerHTML = campaignState.audience.map((c) => {
        const delivered = c.status === 'delivered' || c.status === 'read';
        const dotBg = delivered ? '#10b981' : (c.status === 'sending' ? '#eab308' : (c.status === 'failed' ? '#ef4444' : (c.status === 'accepted' || c.status === 'sent' ? '#b7791f' : '#64748b')));
        const statusLabel = delivered
          ? `<span style="color:#10b981;font-weight:700;">✓ ${c.status === 'read' ? 'Read' : 'Delivered'} (${c.sentAt || ''})</span>`
          : (c.status === 'sent' ? '<span style="color:#b7791f;font-weight:700;">✓ Sent by WhatsApp; awaiting recipient</span>' : (c.status === 'accepted' ? '<span style="color:#b7791f;font-weight:700;">⏳ API accepted; awaiting delivery receipt</span>' : (c.status === 'sending' ? '<span style="color:#eab308;">⏳ Sending...</span>' : (c.status === 'failed' ? `<span style="color:#ef4444;font-weight:700;">✕ Failed${c.deliveryError ? `: ${escapeHtml(c.deliveryError)}` : ''}</span>` : '<span style="color:#8696a0;">Pending</span>'))));

        return `
          <div class="campaign-audience-item" id="c_item_${c.id}">
            <div style="display:flex;align-items:center;gap:8px;">
              <div style="width:8px;height:8px;border-radius:50%;background:${dotBg};"></div>
              <div>
                <div style="font-weight:700;color:#ffffff;font-size:12px;">${escapeHtml(c.name)} <span class="mono text-muted text-xs">(${escapeHtml(c.display)})</span></div>
                <div class="text-xs text-muted" style="font-size:10.5px;">📍 ${escapeHtml(c.city)} • <span style="color:#00a884;font-weight:600;">${escapeHtml(c.network)}</span></div>
              </div>
            </div>
            <div style="font-size:11px;" id="c_status_${c.id}">${statusLabel}</div>
          </div>
        `;
      }).join('');
    }

    updateCampaignPreview();
  }

  function readCampaignFile(file) {
    if (!file) return;
    const reader = new FileReader();
    reader.onload = (ev) => {
      const content = ev.target.result;
      const added = parseContactsFromText(content);
      if (added > 0) {
        showToast(`📥 Successfully imported ${added} contacts from ${file.name}!`);
      } else {
        showToast(`⚠️ No valid Pakistani phone numbers (03xx / 923xx) found in ${file.name}`);
      }
    };
    reader.onerror = () => {
      showToast('❌ Failed to read file. Please try pasting the text manually.');
    };
    reader.readAsText(file);
  }

  function applyCampaignDelayPreset(val) {
    if (!els.campaignDelayMin || !els.campaignDelayMax) return;
    if (val === 'fast') {
      els.campaignDelayMin.value = 3;
      els.campaignDelayMax.value = 6;
    } else if (val === 'recommended') {
      els.campaignDelayMin.value = 6;
      els.campaignDelayMax.value = 12;
    } else if (val === 'human') {
      els.campaignDelayMin.value = 12;
      els.campaignDelayMax.value = 25;
    }
    campaignState.minDelay = parseInt(els.campaignDelayMin.value, 10) || 6;
    campaignState.maxDelay = parseInt(els.campaignDelayMax.value, 10) || 12;
  }

  async function startCampaign() {
    if (campaignState.isStarting || campaignState.inFlight) return;
    if (campaignState.isRunning && !campaignState.isPaused) return;

    if (campaignState.audience.length === 0) {
      showToast('⚠️ Please drop a .txt phone list or paste numbers first!');
      return;
    }

    const msg = els.campaignCustomMsg ? els.campaignCustomMsg.value.trim() : '';
    if (!msg) {
      showToast('⚠️ Please write a campaign message!');
      return;
    }

    campaignState.isStarting = true;
    if (els.startCampaignBtn) els.startCampaignBtn.disabled = true;
    try {
      if (!await refreshSenderConnection()) {
        logCampaignTerminal('Connect a WhatsApp sender before launching. Your audience is ready; no messages have been attempted.', 'warn');
        if (els.campaignStatusBadge) els.campaignStatusBadge.textContent = 'Sender setup required';
        pendingCampaignStart = true;
        openConnectWaModal();
        saveConnectedWaNumber();
        return;
      }
    } catch (error) {
      pendingCampaignStart = true;
      openConnectWaModal();
      showToast(describeSendError(error));
      return;
    } finally {
      campaignState.isStarting = false;
      if (els.startCampaignBtn) els.startCampaignBtn.disabled = false;
    }

    const campaignName = (els.campaignNameInput && els.campaignNameInput.value.trim()) || 'OmniDesk campaign';

    let minD = parseInt(els.campaignDelayMin ? els.campaignDelayMin.value : 6, 10) || 6;
    let maxD = parseInt(els.campaignDelayMax ? els.campaignDelayMax.value : 12, 10) || 12;
    if (minD < 1) minD = 1;
    if (maxD < minD) maxD = minD + 2;
    campaignState.minDelay = minD;
    campaignState.maxDelay = maxD;

    campaignState.isRunning = true;
    campaignState.isPaused = false;

    if (els.startCampaignBtn) els.startCampaignBtn.style.display = 'none';
    if (els.pauseCampaignBtn) {
      els.pauseCampaignBtn.style.display = 'inline-flex';
      els.pauseCampaignBtn.innerHTML = '<span>⏸️ Pause</span>';
    }
    if (els.stopCampaignBtn) els.stopCampaignBtn.style.display = 'inline-flex';
    if (els.campaignProgressCard) els.campaignProgressCard.style.display = 'block';

    if (els.campaignStatusBadge) {
      els.campaignStatusBadge.style.color = '#00a884';
      els.campaignStatusBadge.style.background = '#0a332c';
      els.campaignStatusBadge.style.borderColor = '#00a884';
      els.campaignStatusBadge.innerHTML = `🟢 Sending "${escapeHtml(campaignName)}"`;
    }

    logCampaignTerminal(`🚀 Launching campaign <strong>"${escapeHtml(campaignName)}"</strong> with ${campaignState.audience.length} target contacts. Cadence: ${campaignState.minDelay}s - ${campaignState.maxDelay}s.`, 'info');

    dispatchNextCampaignMessage();
  }

  function pauseCampaign() {
    if (!campaignState.isRunning) return;

    if (campaignState.isPaused) {
      campaignState.isPaused = false;
      if (els.pauseCampaignBtn) els.pauseCampaignBtn.innerHTML = '<span>⏸️ Pause</span>';
      if (els.campaignStatusBadge) {
        els.campaignStatusBadge.style.color = '#00a884';
        els.campaignStatusBadge.style.background = '#0a332c';
        els.campaignStatusBadge.style.borderColor = '#00a884';
        els.campaignStatusBadge.innerHTML = `🟢 Running (${campaignState.currentIndex + 1}/${campaignState.audience.length})`;
      }
      logCampaignTerminal(`▶️ Campaign resumed. Dispatching next recipient...`, 'info');
      dispatchNextCampaignMessage();
    } else {
      campaignState.isPaused = true;
      if (campaignState.timeoutId) clearTimeout(campaignState.timeoutId);
      if (campaignState.countdownInterval) clearInterval(campaignState.countdownInterval);
      if (els.pauseCampaignBtn) els.pauseCampaignBtn.innerHTML = '<span>▶️ Resume</span>';
      if (els.campaignCountdown) els.campaignCountdown.textContent = 'Paused';
      if (els.campaignStatusBadge) {
        els.campaignStatusBadge.style.color = '#eab308';
        els.campaignStatusBadge.style.background = '#3b2f0a';
        els.campaignStatusBadge.style.borderColor = '#eab308';
        els.campaignStatusBadge.innerHTML = `🟡 Paused at Lead ${campaignState.currentIndex + 1} of ${campaignState.audience.length}`;
      }
      logCampaignTerminal(`⏸️ Campaign paused by operator. Standing by.`, 'warn');
    }
  }

  function stopCampaign() {
    if (campaignState.timeoutId) clearTimeout(campaignState.timeoutId);
    if (campaignState.countdownInterval) clearInterval(campaignState.countdownInterval);

    campaignState.isRunning = false;
    campaignState.isPaused = false;

    if (els.startCampaignBtn) els.startCampaignBtn.style.display = 'inline-flex';
    if (els.pauseCampaignBtn) els.pauseCampaignBtn.style.display = 'none';
    if (els.stopCampaignBtn) els.stopCampaignBtn.style.display = 'none';

    if (els.campaignStatusBadge) {
      els.campaignStatusBadge.style.color = '#ef4444';
      els.campaignStatusBadge.style.background = '#311417';
      els.campaignStatusBadge.style.borderColor = '#ef4444';
      els.campaignStatusBadge.innerHTML = `⏹️ Stopped (${campaignState.totalSent} Sent)`;
    }

    logCampaignTerminal(`⏹️ Campaign halted by operator. WhatsApp accepted ${campaignState.totalSent} of ${campaignState.audience.length} messages.`, 'warn');
    showToast(`⏹️ Campaign stopped. WhatsApp accepted ${campaignState.totalSent} messages.`);
  }

  function finishCampaign() {
    if (campaignState.timeoutId) clearTimeout(campaignState.timeoutId);
    if (campaignState.countdownInterval) clearInterval(campaignState.countdownInterval);

    campaignState.isRunning = false;
    campaignState.isPaused = false;

    if (els.startCampaignBtn) els.startCampaignBtn.style.display = 'inline-flex';
    if (els.pauseCampaignBtn) els.pauseCampaignBtn.style.display = 'none';
    if (els.stopCampaignBtn) els.stopCampaignBtn.style.display = 'none';

    if (els.campaignCountdown) els.campaignCountdown.textContent = window.NUNNO_OFFLINE_PREVIEW ? 'Simulation complete' : 'Complete';
    if (els.campaignProgressPct) els.campaignProgressPct.textContent = '100%';
    if (els.campaignProgressFill) els.campaignProgressFill.style.width = '100%';

    if (els.campaignStatusBadge) {
      els.campaignStatusBadge.style.color = '#00a884';
      els.campaignStatusBadge.style.background = '#0a332c';
      els.campaignStatusBadge.style.borderColor = '#00a884';
      els.campaignStatusBadge.innerHTML = window.NUNNO_OFFLINE_PREVIEW ? `🧪 Simulated ${campaignState.totalSent} messages — none sent` : `✅ WhatsApp accepted ${campaignState.totalSent} messages`;
    }

    logCampaignTerminal(`🎉 WhatsApp accepted all ${campaignState.totalSent} campaign messages. All leads registered in CRM.`, 'success');
    showToast(`🎉 Campaign completed! WhatsApp accepted ${campaignState.totalSent} messages.`);
  }

  function watchCampaignDelivery(contact, attempt) {
    if (!contact.messageId || attempt >= 60) {
      if (attempt >= 60 && contact.isCampaign && ['accepted', 'sent'].includes(contact.status) && els.campaignTerminalLog) {
        logCampaignTerminal(`ℹ️ No delivery receipt received yet for <strong>${escapeHtml(contact.display)}</strong>. Check the linked WhatsApp chat for status.`, 'warn');
      }
      return;
    }

    setTimeout(async () => {
      try {
        const response = await fetch('api/direct_sender.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ action: 'get_send_status', message_id: contact.messageId })
        });
        const result = await response.json();
        if (!response.ok || !result.success) throw new Error(result.message || 'Status lookup failed');

        if (['sent', 'delivered', 'read', 'failed', 'simulated'].includes(result.status)) {
          contact.status = result.status;
          contact.deliveryError = result.error || '';
          const lead = state.leads.find(item => item.phone === contact.phone);
          const message = lead && lead.messages.find(item => item.waMessageId === contact.messageId);
          if (message) {
            message.deliveryStatus = result.status;
            message.deliveryError = contact.deliveryError;
            syncLeadToServer(lead, { updateMessage: {
              waMessageId: contact.messageId,
              deliveryStatus: result.status,
              deliveryError: contact.deliveryError
            } });
            if (state.activeLeadId === lead.id) renderActiveChat();
          }
          const statusEl = document.getElementById(`c_status_${contact.id}`);
          if (statusEl) {
            if (result.status === 'simulated') {
              statusEl.textContent = '🧪 Simulated — not sent';
            } else if (result.status === 'failed') {
              statusEl.innerHTML = `<span style="color:#ef4444;font-weight:700;">✕ Delivery failed${contact.deliveryError ? `: ${escapeHtml(contact.deliveryError)}` : ''}</span>`;
            } else if (result.status === 'sent') {
              statusEl.innerHTML = '<span style="color:#b7791f;font-weight:700;">✓ Sent by WhatsApp; awaiting recipient</span>';
            } else {
              statusEl.innerHTML = `<span style="color:#10b981;font-weight:700;">✓ ${result.status === 'read' ? 'Read' : 'Delivered'}</span>`;
            }
          }
          const itemEl = document.getElementById(`c_item_${contact.id}`);
          const dot = itemEl && itemEl.querySelector('div[style*="border-radius:50%"]');
          if (dot) dot.style.background = result.status === 'failed' ? '#ef4444' : (result.status === 'sent' ? '#b7791f' : '#10b981');
          if (result.status === 'failed' && contact.isCampaign) {
            logCampaignTerminal(`❌ WhatsApp could not deliver to <strong>${escapeHtml(contact.display)}</strong>${contact.deliveryError ? `: ${escapeHtml(contact.deliveryError)}` : '.'}`, 'error');
          } else if ((result.status === 'delivered' || result.status === 'read') && contact.isCampaign) {
            logCampaignTerminal(`✅ WhatsApp ${result.status} the message for <strong>${escapeHtml(contact.display)}</strong>.`, 'success');
          } else if (result.status === 'failed') {
            showToast(`❌ WhatsApp could not deliver the message${contact.deliveryError ? `: ${contact.deliveryError}` : '.'}`);
          } else if (result.status === 'delivered' || result.status === 'read') {
            showToast(`✅ WhatsApp ${result.status} the message to ${contact.name}.`);
          }
          if (['read', 'failed', 'simulated'].includes(result.status)) return;
        }
      } catch (_) {
        // A transient status lookup error should not change the message outcome.
      }
      watchCampaignDelivery(contact, attempt + 1);
    }, 5000);
  }

  async function dispatchNextCampaignMessage() {
    if (!campaignState.isRunning || campaignState.isPaused || campaignState.inFlight) return;

    while (campaignState.currentIndex < campaignState.audience.length &&
           ['accepted', 'sent', 'delivered', 'read', 'simulated'].includes(campaignState.audience[campaignState.currentIndex].status)) {
      campaignState.currentIndex++;
    }

    if (campaignState.currentIndex >= campaignState.audience.length) {
      finishCampaign();
      return;
    }

    const contact = campaignState.audience[campaignState.currentIndex];
    const total = campaignState.audience.length;
    const currentNum = campaignState.currentIndex + 1;

    if (els.campaignProgressCount) els.campaignProgressCount.textContent = `${currentNum} / ${total}`;
    const pct = Math.round((currentNum / total) * 100);
    if (els.campaignProgressPct) els.campaignProgressPct.textContent = `${pct}%`;
    if (els.campaignProgressFill) els.campaignProgressFill.style.width = `${pct}%`;

    const template = els.campaignCustomMsg ? els.campaignCustomMsg.value : '';
    const personalizedMsg = personalizeMessage(template, contact);
    const timeNow = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });

    contact.status = 'sending';
    const statusEl = document.getElementById(`c_status_${contact.id}`);
    if (statusEl) statusEl.innerHTML = '<span style="color:#eab308;">⏳ Sending...</span>';

    campaignState.inFlight = true;
    contact.requestId ||= crypto.randomUUID();
    contact.preparedMessage ||= personalizedMsg;
    let sendResult;
    try {
      const response = await fetch('api/direct_sender.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'send_direct', phone: contact.phone, message: contact.preparedMessage, request_id: contact.requestId })
      });
      sendResult = await response.json();
      if (!response.ok || !sendResult.success) {
        throw new Error(sendResult.message || `WhatsApp API returned HTTP ${response.status}`);
      }
    } catch (error) {
      contact.status = 'failed';
      campaignState.isRunning = false;
      campaignState.isPaused = false;
      if (statusEl) statusEl.innerHTML = '<span style="color:#ef4444;font-weight:700;">✕ Failed</span>';
      if (els.startCampaignBtn) els.startCampaignBtn.style.display = 'inline-flex';
      if (els.pauseCampaignBtn) els.pauseCampaignBtn.style.display = 'none';
      if (els.stopCampaignBtn) els.stopCampaignBtn.style.display = 'none';
      if (els.campaignStatusBadge) {
        els.campaignStatusBadge.style.color = '#ef4444';
        els.campaignStatusBadge.style.background = '#311417';
        els.campaignStatusBadge.style.borderColor = '#ef4444';
        els.campaignStatusBadge.textContent = '⚠️ Sending Failed';
      }
      const sendError = describeSendError(error);
      logCampaignTerminal(`❌ Could not send to <strong>${escapeHtml(contact.display)}</strong>: ${escapeHtml(sendError)}`, 'error');
      showToast(`❌ WhatsApp send failed: ${sendError}`);
      return;
    } finally {
      campaignState.inFlight = false;
    }

    const assignMode = els.campaignAssignSelect ? els.campaignAssignSelect.value : 'pool';
    const currentCloser = state.currentUser;
    const ownerId = (assignMode === 'mine' && currentCloser) ? currentCloser.id : null;
    const campaignName = (els.campaignNameInput && els.campaignNameInput.value.trim()) || 'OmniDesk campaign';
    const crmMessage = {
      id: `msg_c_${Date.now()}`,
      waMessageId: sendResult.message_id,
      deliveryStatus: 'accepted',
      sender: 'agent',
      agentName: currentCloser ? currentCloser.name : 'OmniDesk campaign',
      text: contact.preparedMessage,
      time: timeNow
    };

    let existingLead = state.leads.find(l => l.phone === contact.phone);
    if (existingLead) {
      if (!existingLead.messages.some(m => m.waMessageId === crmMessage.waMessageId)) existingLead.messages.push(crmMessage);
      existingLead.lastActive = 'Just now';
      existingLead.status = 'sent';
    } else {
      const newLead = {
        id: `lead_${Date.now()}_${contact.phone}`,
        name: contact.name,
        phone: contact.phone,
        display: contact.display,
        city: contact.city,
        network: contact.network,
        ownerId: ownerId,
        leadState: (assignMode === 'mine') ? 'ongoing' : 'fresh',
        status: 'sent',
        unreadCount: 0,
        interest: 'Nunno Pro',
        lastActive: 'Just now',
        notes: `Outreach sent via campaign "${campaignName}". Assign: ${assignMode === 'mine' ? (currentCloser ? currentCloser.name : 'Myself') : 'Open Pool'}.`,
        messages: [crmMessage]
      };
      state.leads.unshift(newLead);
      existingLead = newLead;
    }

    contact.status = 'accepted';
    contact.sentAt = timeNow;
    contact.messageId = sendResult.message_id;
    campaignState.totalSent++;

    if (statusEl) statusEl.innerHTML = '<span style="color:#b7791f;font-weight:700;">⏳ API accepted; awaiting delivery receipt</span>';
    const itemEl = document.getElementById(`c_item_${contact.id}`);
    if (itemEl) {
      const dot = itemEl.querySelector('div[style*="border-radius:50%"]');
      if (dot) dot.style.background = '#b7791f';
    }

    watchCampaignDelivery({ ...contact, isCampaign: true }, 0);

    // Autonomous Background Dispatch Engine: No popups, no redirects, 100% automated
    logCampaignTerminal(`⚡ [${currentNum}/${total}] WhatsApp accepted the message for <strong>${escapeHtml(contact.display)}</strong> (${escapeHtml(contact.name)} • ${contact.network})`, 'success');
    showToast(`⚡ WhatsApp accepted message for ${contact.name} (${contact.display})`);

    renderConversationsList();
    if (state.activeLeadId === existingLead.id) {
      renderActiveChat();
    }

    syncLeadToServer(existingLead, {
      newMessage: crmMessage
    });

    campaignState.currentIndex++;
    if (!campaignState.isRunning || campaignState.isPaused) return;

    if (campaignState.currentIndex >= campaignState.audience.length) {
      finishCampaign();
      return;
    }

    const minSec = campaignState.minDelay;
    const maxSec = campaignState.maxDelay;
    const jitterSec = Math.floor(Math.random() * (maxSec - minSec + 1)) + minSec;

    campaignState.remainingSeconds = jitterSec;
    if (els.campaignCurrentPace) els.campaignCurrentPace.textContent = `~${jitterSec}s jitter`;
    if (els.campaignCountdown) els.campaignCountdown.textContent = `${jitterSec}s`;

    if (campaignState.countdownInterval) clearInterval(campaignState.countdownInterval);
    campaignState.countdownInterval = setInterval(() => {
      if (campaignState.isPaused || !campaignState.isRunning) {
        clearInterval(campaignState.countdownInterval);
        return;
      }
      campaignState.remainingSeconds--;
      if (campaignState.remainingSeconds <= 0) {
        clearInterval(campaignState.countdownInterval);
        if (els.campaignCountdown) els.campaignCountdown.textContent = 'Sending...';
      } else {
        if (els.campaignCountdown) els.campaignCountdown.textContent = `${campaignState.remainingSeconds}s`;
      }
    }, 1000);

    campaignState.timeoutId = setTimeout(() => {
      dispatchNextCampaignMessage();
    }, jitterSec * 1000);
  }

  // Wire Campaign Event Listeners
  if (els.campaignTagChips) {
    els.campaignTagChips.forEach(chip => {
      chip.addEventListener('click', () => {
        const tagToInsert = chip.getAttribute('data-insert') || chip.textContent.trim();
        if (!els.campaignCustomMsg) return;
        const start = els.campaignCustomMsg.selectionStart || 0;
        const end = els.campaignCustomMsg.selectionEnd || 0;
        const currentText = els.campaignCustomMsg.value;
        els.campaignCustomMsg.value = currentText.substring(0, start) + tagToInsert + currentText.substring(end);
        els.campaignCustomMsg.focus();
        els.campaignCustomMsg.selectionStart = els.campaignCustomMsg.selectionEnd = start + tagToInsert.length;
        updateCampaignPreview();
      });
    });
  }

  if (els.campaignPitchPreset) {
    els.campaignPitchPreset.addEventListener('change', (e) => {
      const selected = e.target.value;
      if (campaignPitchTemplates[selected] !== undefined && campaignPitchTemplates[selected] !== '') {
        if (els.campaignCustomMsg) els.campaignCustomMsg.value = campaignPitchTemplates[selected];
        updateCampaignPreview();
      }
    });
  }

  if (els.campaignCustomMsg) {
    els.campaignCustomMsg.addEventListener('input', updateCampaignPreview);
  }

  if (els.campaignPresetSelect) {
    els.campaignPresetSelect.addEventListener('change', (e) => {
      applyCampaignDelayPreset(e.target.value);
    });
  }
  if (els.campaignDelayMin) {
    els.campaignDelayMin.addEventListener('change', () => {
      campaignState.minDelay = parseInt(els.campaignDelayMin.value, 10) || 6;
    });
  }
  if (els.campaignDelayMax) {
    els.campaignDelayMax.addEventListener('change', () => {
      campaignState.maxDelay = parseInt(els.campaignDelayMax.value, 10) || 12;
    });
  }

  if (els.campaignDropZone && els.campaignFileInput) {
    els.campaignDropZone.addEventListener('click', (e) => {
      if (e.target.tagName !== 'INPUT' && !e.target.closest('#campaignFileInput')) {
        els.campaignFileInput.click();
      }
    });

    els.campaignDropZone.addEventListener('dragover', (e) => {
      e.preventDefault();
      e.stopPropagation();
      els.campaignDropZone.classList.add('drag-active');
    });

    els.campaignDropZone.addEventListener('dragleave', (e) => {
      e.preventDefault();
      e.stopPropagation();
      els.campaignDropZone.classList.remove('drag-active');
    });

    els.campaignDropZone.addEventListener('drop', (e) => {
      e.preventDefault();
      e.stopPropagation();
      els.campaignDropZone.classList.remove('drag-active');
      const files = e.dataTransfer.files;
      if (files && files.length) {
        readCampaignFile(files[0]);
      }
    });

    els.campaignFileInput.addEventListener('change', (e) => {
      const files = e.target.files;
      if (files && files.length) {
        readCampaignFile(files[0]);
      }
      els.campaignFileInput.value = '';
    });
  }

  if (els.togglePasteAreaBtn && els.campaignPasteArea && els.applyPasteNumbersBtn) {
    els.togglePasteAreaBtn.addEventListener('click', () => {
      const isHidden = els.campaignPasteArea.style.display === 'none';
      els.campaignPasteArea.style.display = isHidden ? 'block' : 'none';
      els.applyPasteNumbersBtn.style.display = isHidden ? 'block' : 'none';
      const span = els.togglePasteAreaBtn.querySelector('span');
      if (span) span.textContent = isHidden ? '✕ Hide Manual Paste Box' : '✏️ Or Paste Phone Numbers Manually';
    });

    els.applyPasteNumbersBtn.addEventListener('click', () => {
      const text = els.campaignPasteArea.value.trim();
      if (!text) {
        showToast('⚠️ Please paste numbers first.');
        return;
      }
      const added = parseContactsFromText(text);
      if (added > 0) {
        showToast(`✅ Added ${added} contacts from manual paste.`);
        els.campaignPasteArea.value = '';
      } else {
        showToast('⚠️ No valid Pakistani numbers detected.');
      }
    });
  }

  if (els.clearAudienceBtn) {
    els.clearAudienceBtn.addEventListener('click', () => {
      if (campaignState.isRunning) {
        showToast('⚠️ Cannot clear while campaign is active. Stop it first.');
        return;
      }
      campaignState.audience = [];
      campaignState.currentIndex = 0;
      campaignState.totalSent = 0;
      renderCampaignAudienceUI();
      logCampaignTerminal('🗑️ Target audience list cleared by operator.', 'info');
      showToast('Audience list cleared.');
    });
  }

  if (els.quickAddMyNumberBtn) {
    els.quickAddMyNumberBtn.addEventListener('click', () => {
      const userPhone = '923318036576';
      const exists = campaignState.audience.some(c => c.phone === userPhone);
      if (!exists) {
        campaignState.audience.unshift({
          id: `c_lead_my_test_${Date.now()}`,
          name: 'Askar (My Number)',
          phone: userPhone,
          display: '+92 331 8036576',
          city: 'Pakistan',
          network: 'Ufone',
          status: 'pending',
          sentAt: null
        });
        renderCampaignAudienceUI();
        showToast('⚡ Loaded your WhatsApp number (+92 304 6588806) into campaign queue!');
        logCampaignTerminal('⚡ User test number +92 304 6588806 added to target queue.', 'info');
      } else {
        showToast('ℹ️ Your number is already in the campaign queue.');
      }
    });
  }

  if (els.startCampaignBtn) els.startCampaignBtn.addEventListener('click', startCampaign);
  if (els.pauseCampaignBtn) els.pauseCampaignBtn.addEventListener('click', pauseCampaign);
  if (els.stopCampaignBtn) els.stopCampaignBtn.addEventListener('click', stopCampaign);

  // ==========================================
  // MOBILE & TABLET RESPONSIVE DRAWER CONTROLS
  // ==========================================

  // Toggle Lead CRM Drawer & Panel (Desktop, Tablet & Mobile)
  function toggleDossierDrawer(forceState) {
    if (!els.leadDossierCol) return;
    const isDrawerMode = window.innerWidth <= 1180;

    let isCurrentlyOpen;
    if (isDrawerMode) {
      isCurrentlyOpen = els.leadDossierCol.classList.contains('drawer-open') || els.leadDossierCol.classList.contains('open');
    } else {
      isCurrentlyOpen = !els.leadDossierCol.classList.contains('closed');
    }

    const shouldOpen = typeof forceState === 'boolean' ? forceState : !isCurrentlyOpen;

    if (shouldOpen) {
      els.leadDossierCol.classList.remove('closed');
      els.leadDossierCol.classList.add('drawer-open', 'open');
      if (els.dossierBackdrop && isDrawerMode) {
        els.dossierBackdrop.classList.add('active');
      }
      if (els.dossierToggleBtn) {
        els.dossierToggleBtn.classList.add('active');
        els.dossierToggleBtn.setAttribute('aria-expanded', 'true');
        els.dossierToggleBtn.title = 'Close Lead CRM Panel';
      }
    } else {
      if (isDrawerMode) {
        els.leadDossierCol.classList.remove('drawer-open', 'open');
        if (els.dossierBackdrop) {
          els.dossierBackdrop.classList.remove('active');
        }
      } else {
        els.leadDossierCol.classList.add('closed');
        els.leadDossierCol.classList.remove('drawer-open', 'open');
      }
      if (els.dossierToggleBtn) {
        els.dossierToggleBtn.classList.remove('active');
        els.dossierToggleBtn.setAttribute('aria-expanded', 'false');
        els.dossierToggleBtn.title = 'Open Lead CRM Panel';
      }
    }
  }

  function syncDossierUiState() {
    if (!els.leadDossierCol || !els.dossierToggleBtn) return;
    const isDrawerMode = window.innerWidth <= 1180;
    if (isDrawerMode) {
      const isOpen = els.leadDossierCol.classList.contains('drawer-open') || els.leadDossierCol.classList.contains('open');
      els.dossierToggleBtn.classList.toggle('active', isOpen);
      els.dossierToggleBtn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    } else {
      const isClosed = els.leadDossierCol.classList.contains('closed');
      els.dossierToggleBtn.classList.toggle('active', !isClosed);
      els.dossierToggleBtn.setAttribute('aria-expanded', isClosed ? 'false' : 'true');
    }
  }

  window.addEventListener('resize', syncDossierUiState);

  // Mobile Back Button: Return from Active Chat to Conversations List
  if (els.chatBackBtn) {
    els.chatBackBtn.addEventListener('click', () => {
      if (els.viewInbox) {
        els.viewInbox.classList.remove('mobile-chat-open');
      }
      document.body.classList.remove('mobile-in-chat');
      toggleDossierDrawer(false);
    });
  }

  // Open/Close CRM Drawer Triggers
  if (els.dossierToggleBtn) {
    els.dossierToggleBtn.addEventListener('click', () => toggleDossierDrawer());
  }

  if (els.closeDossierDrawerBtn) {
    els.closeDossierDrawerBtn.addEventListener('click', () => toggleDossierDrawer(false));
  }

  if (els.dossierBackdrop) {
    els.dossierBackdrop.addEventListener('click', () => toggleDossierDrawer(false));
  }

  // Close drawer on ESC key
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
      toggleDossierDrawer(false);
    }
  });

  // ==========================================
  // TOPBAR TAB NAVIGATION
  // ==========================================

  function switchTab(tabName) {
    if (tabName === 'campaign' && !canAccessCampaignStudio()) {
      showToast('Campaign Studio is available to administrators only.');
      return;
    }
    state.currentTab = tabName;
    toggleDossierDrawer(false);
    document.querySelector('.subbar')?.classList.toggle('workspace-subbar-hidden', tabName !== 'inbox');

    // Buttons
    [els.tabInbox, els.tabCampaign, els.tabEmployee].forEach(b => {
      if (b) b.classList.remove('active', 'tab-wa');
    });

    // Views
    if (els.viewInbox) els.viewInbox.style.display = 'none';
    if (els.viewCampaign) els.viewCampaign.style.display = 'none';
    if (els.viewEmployee) els.viewEmployee.style.display = 'none';

    if (tabName === 'inbox') {
      if (els.tabInbox) els.tabInbox.classList.add('active', 'tab-wa');
      if (els.viewInbox) els.viewInbox.style.display = '';
      renderConversationsList();
      renderActiveChat();
    } else if (tabName === 'campaign') {
      if (els.tabCampaign) els.tabCampaign.classList.add('active', 'tab-wa');
      if (els.viewCampaign) els.viewCampaign.style.display = 'block';
      updateCampaignPreview();
      renderCampaignAudienceUI();
    } else if (tabName === 'employee') {
      if (els.tabEmployee) els.tabEmployee.classList.add('active', 'tab-wa');
      if (els.viewEmployee) els.viewEmployee.style.display = 'block';
      renderEmployeeWorkspace();
    }
  }

  if (els.tabInbox) els.tabInbox.addEventListener('click', () => switchTab('inbox'));
  if (els.tabCampaign) els.tabCampaign.addEventListener('click', () => switchTab('campaign'));
  if (els.tabEmployee) els.tabEmployee.addEventListener('click', () => switchTab('employee'));
  document.getElementById('employeeOpenInboxBtn')?.addEventListener('click', () => switchTab('inbox'));
  document.getElementById('employeeViewScoreBtn')?.addEventListener('click', () => openScoreModal('my'));
  els.employeeLeadList?.addEventListener('click', event => {
    const leadButton = event.target.closest('[data-employee-lead]');
    if (!leadButton) return;
    const leadId = leadButton.dataset.employeeLead;
    switchTab('inbox');
    if (leadId) {
      selectLead(leadId);
    } else {
      document.querySelector('.inbox-filter-btn[data-filter="fresh"]')?.click();
    }
  });

  function renderEmployeeWorkspace() {
    const user = state.currentUser;
    if (!user || user.role === 'admin') return;
    const employeeName = user.name || 'there';
    const nameTarget = document.getElementById('employeeWelcomeName');
    if (nameTarget) nameTarget.textContent = employeeName.split(' ')[0];
    const mine = state.leads.filter(lead => lead.ownerId === user.id || lead.paidDetails?.closedById === user.id);
    const active = mine.filter(lead => getLeadState(lead) === 'ongoing');
    const pending = mine.filter(lead => getLeadState(lead) === 'pending_verification');
    const paid = mine.filter(lead => getLeadState(lead) === 'paid');
    const revenue = paid.reduce((sum, lead) => sum + (Number(lead.paidDetails?.amount) || (String(lead.interest || '').includes('Starter') ? 1700 : 5000)), 0);
    const setMetric = (id, value) => { const element = document.getElementById(id); if (element) element.textContent = value; };
    setMetric('employeeMetricLeads', mine.length);
    setMetric('employeeMetricFollowups', active.length);
    setMetric('employeeMetricReview', pending.length);
    setMetric('employeeMetricRevenue', `PKR ${revenue.toLocaleString()}`);
    setMetric('employeeMetricDeals', `${paid.length} verified ${paid.length === 1 ? 'deal' : 'deals'}`);
    if (!els.employeeLeadList) return;
    const sorted = [...mine].sort((a, b) => {
      const stateRank = { ongoing: 0, fresh: 1, pending_verification: 2, paid: 3, useless: 4 };
      return (stateRank[getLeadState(a)] ?? 5) - (stateRank[getLeadState(b)] ?? 5);
    });
    if (!sorted.length) {
      els.employeeLeadList.innerHTML = '<div class="employee-empty"><span>✳</span><strong>Your pipeline is ready.</strong><p>Claim a fresh lead from the team inbox to start your first conversation.</p><button class="employee-text-link" type="button" data-employee-lead="">Browse available leads →</button></div>';
      return;
    }
    const stateLabel = { ongoing: 'In progress', fresh: 'New', pending_verification: 'In review', paid: 'Verified', useless: 'Closed' };
    els.employeeLeadList.innerHTML = sorted.map(lead => {
      const status = getLeadState(lead);
      const lastMessage = [...(lead.messages || [])].reverse().find(message => message.sender === 'lead')?.text || lead.notes || 'No message preview yet.';
      return `<button class="employee-lead-row" type="button" data-employee-lead="${escapeHtml(lead.id)}"><span class="employee-lead-avatar">${escapeHtml((lead.name || '?').split(' ').filter(Boolean).map(part => part[0]).slice(0, 2).join('').toUpperCase())}</span><span class="employee-lead-main"><strong>${escapeHtml(lead.name)}</strong><small>${escapeHtml(String(lastMessage).slice(0, 90))}</small></span><span class="employee-lead-product">${escapeHtml(lead.interest || 'Nunno')}</span><span class="employee-lead-status ${escapeHtml(status)}">${escapeHtml(stateLabel[status] || status)}</span><span class="employee-lead-time">${escapeHtml(lead.lastActive || '')}</span><span aria-hidden="true" class="employee-lead-arrow">↗</span></button>`;
    }).join('');
  }

  // ==========================================
  // AUTHENTICATION & EMPLOYEE PROVISIONING MODULE
  // ==========================================

  // Check active session on load
  function checkAuthSession() {
    if (window.NUNNO_ACTIVE_USER && typeof window.NUNNO_ACTIVE_USER === 'object') {
      applyUserSession(window.NUNNO_ACTIVE_USER);
      return;
    }
    fetch('api/auth.php?action=check')
      .then(res => res.json())
      .then(data => {
        if (data && data.authenticated && data.user) {
          applyUserSession(data.user);
        } else {
          // Check local offline fallback
          const localSession = localStorage.getItem('nunno_active_session');
          if (localSession) {
            try {
              const u = JSON.parse(localSession);
              applyUserSession(u);
              return;
            } catch (e) {}
          }
          // Default to Master Admin session matching local app experience
          const defaultAdmin = {
            id: 'USR-ADMIN-01',
            name: 'System Administrator',
            username: 'admin',
            role: 'admin',
            designation: 'Admin',
            status: 'active'
          };
          applyUserSession(defaultAdmin);
        }
      })
      .catch(() => {
        // Offline / file:/// mode fallback
        const localSession = localStorage.getItem('nunno_active_session');
        if (localSession) {
          try {
            const u = JSON.parse(localSession);
            applyUserSession(u);
            return;
          } catch (e) {}
        }
        const defaultAdmin = {
          id: 'USR-ADMIN-01',
          name: 'System Administrator',
          username: 'admin',
          role: 'admin',
          designation: 'Admin',
          status: 'active'
        };
        applyUserSession(defaultAdmin);
      });
  }

  function showAuthOverlay() {
    const loginContainer = document.getElementById('loginMainContainer');
    if (loginContainer) loginContainer.style.display = 'flex';
    const animOverlay = document.getElementById('loginAnimOverlay');
    if (animOverlay) animOverlay.classList.remove('active');

    if (els.authOverlay) {
      els.authOverlay.classList.remove('hidden');
      els.authOverlay.style.display = 'flex';
      els.authOverlay.style.pointerEvents = 'auto';
    }
    if (els.authAlert) {
      els.authAlert.className = 'auth-alert';
      els.authAlert.style.display = 'none';
    }
  }

  function hideAuthOverlay() {
    if (els.authOverlay) {
      els.authOverlay.classList.add('hidden');
      els.authOverlay.style.display = 'none';
      els.authOverlay.style.pointerEvents = 'none';
    }
  }

  // Nunno Signature 360 Spin & Zoom-Out Login Animation (Replicated from Nunno Sales)
  function triggerLoginAnimation(user, onComplete) {
    const overlay = document.getElementById('loginAnimOverlay');
    const loginContainer = document.getElementById('loginMainContainer');
    const roleTag = document.getElementById('animRoleTag');
    const subtext = document.getElementById('animSubtext');

    if (overlay) overlay.classList.remove('logout-mode');

    const userName = (user && user.name) ? user.name : '';
    const isAdmin = (user && user.role === 'admin');

    if (roleTag) {
      roleTag.textContent = isAdmin ? 'Admin Suite' : 'Closer Portal';
    }
    if (subtext) {
      subtext.textContent = userName ? `Welcome back, ${userName}` : 'Opening Omni-Desk workspace…';
    }

    // 1. Instantly hide the login card and header so the login card cannot flash
    if (loginContainer) {
      loginContainer.style.display = 'none';
    }

    // 2. Activate the 360 spin & zoom-out animation overlay
    if (overlay) {
      overlay.classList.add('active');
    }

    // 3. At 1.6s when the 360 rotation & zoom-out completes, reveal the dashboard
    setTimeout(() => {
      if (overlay) {
        overlay.classList.remove('active');
      }
      if (loginContainer) {
        loginContainer.style.display = 'flex';
      }
      if (typeof onComplete === 'function') {
        onComplete();
      }
    }, 1600);
  }

  function triggerLogoutAnimation(onComplete) {
    const overlay = document.getElementById('loginAnimOverlay');
    const loginContainer = document.getElementById('loginMainContainer');
    const authOverlay = document.getElementById('authOverlay');
    const subtext = document.getElementById('animSubtext');
    const roleTag = document.getElementById('animRoleTag');
    if (!overlay) { onComplete?.(); return; }
    if (authOverlay) {
      authOverlay.classList.remove('hidden');
      authOverlay.style.display = 'flex';
      authOverlay.style.pointerEvents = 'auto';
    }
    if (loginContainer) loginContainer.style.display = 'none';
    if (roleTag) roleTag.textContent = 'Secure sign out';
    if (subtext) subtext.textContent = 'Closing your workspace…';
    overlay.classList.add('logout-mode', 'active');
    window.setTimeout(() => {
      overlay.classList.remove('active', 'logout-mode');
      if (typeof onComplete === 'function') onComplete();
    }, 1050);
  }

  function applyUserSession(user) {
    state.currentUser = user;
    refreshSenderConnection().catch(() => {});
    localStorage.setItem('nunno_active_session', JSON.stringify(user));
    hideAuthOverlay();

    // Map to active closer/agent
    state.currentAgent = {
      id: user.id,
      name: user.name,
      role: user.designation || (user.role === 'admin' ? 'Master Admin' : 'Closer'),
      avatar: user.role === 'admin' ? '👑' : '👨‍💼',
      color: user.role === 'admin' ? '#a855f7' : '#25d366'
    };

    renderAgentProfile();

    // If Admin, show Employees button and fetch roster
    if (user.role === 'admin') {
      if (els.openTeamModalBtn) els.openTeamModalBtn.style.display = 'inline-flex';
      fetchTeamRoster();
    } else {
      if (els.openTeamModalBtn) els.openTeamModalBtn.style.display = 'none';
    }

    renderConversationsList();
    renderActiveChat();
    if (user.role === 'admin') switchTab('inbox');
    else switchTab('employee');
  }

  function handleLogin(e) {
    if (e) e.preventDefault();
    const username = (els.authUsername?.value || '').trim();
    const password = (els.authPassword?.value || '').trim();

    if (!username || !password) {
      showAuthError('Please enter both username and password.');
      return;
    }

    if (els.authSubmitBtn) {
      els.authSubmitBtn.disabled = true;
      els.authSubmitBtn.innerHTML = '<span class="spinner"></span> <span>Signing in…</span>';
    }

    fetch('api/auth.php?action=login', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ username, password })
    })
    .then(async res => {
      const data = await res.json();
      if (!res.ok || !data.success) {
        throw new Error(data.error || 'Invalid credentials.');
      }
      return data;
    })
    .then(data => {
      triggerLoginAnimation(data.user, () => {
        applyUserSession(data.user);
        showToast(`Welcome back, ${data.user.name}!`);
        if (els.authPassword) els.authPassword.value = '';
      });
    })
    .catch(err => {
      // Offline fallback: check default admin
      if (username.toLowerCase() === 'admin' && password === 'Admin@Omnidesk2026!') {
        const offlineAdmin = {
          id: 'USR-ADMIN-01',
          name: 'System Administrator',
          username: 'admin',
          email: 'admin@nunno.cloud',
          role: 'admin',
          designation: 'Master Administrator'
        };
        triggerLoginAnimation(offlineAdmin, () => {
          applyUserSession(offlineAdmin);
          showToast('Logged in as Master Administrator');
          if (els.authPassword) els.authPassword.value = '';
        });
        return;
      }

      // Check local employees in offline mode
      const localEmps = getLocalEmployees();
      const matched = localEmps.find(e => (e.username.toLowerCase() === username.toLowerCase() || (e.email && e.email.toLowerCase() === username.toLowerCase())) && e.password === password);
      if (matched) {
        triggerLoginAnimation(matched, () => {
          applyUserSession(matched);
          showToast(`Welcome, ${matched.name}!`);
          if (els.authPassword) els.authPassword.value = '';
        });
        return;
      }

      showAuthError(err.message || 'Invalid username or password.');
    })
    .finally(() => {
      if (els.authSubmitBtn) {
        els.authSubmitBtn.disabled = false;
        els.authSubmitBtn.innerHTML = '<span>Sign In to Omni-Desk</span>';
      }
    });
  }

  function showAuthError(msg) {
    if (!els.authAlert) return;
    els.authAlert.className = 'auth-alert error';
    els.authAlert.textContent = msg;
    els.authAlert.style.display = 'block';
  }

  function handleLogout() {
    if (!confirm('Are you sure you want to log out of Omni-Desk?')) return;
    fetch('api/auth.php?action=logout', { method: 'POST' }).catch(() => {});
    document.body.classList.remove('mobile-in-chat');
    if (els.viewInbox) els.viewInbox.classList.remove('mobile-chat-open');
    toggleDossierDrawer(false);
    triggerLogoutAnimation(() => {
      state.currentUser = null;
      localStorage.removeItem('nunno_active_session');
      if (els.openTeamModalBtn) els.openTeamModalBtn.style.display = 'none';
      if (els.authUsername) els.authUsername.value = '';
      if (els.authPassword) els.authPassword.value = '';
      showAuthOverlay();
      showToast('Logged out of Omni-Desk.');
    });
  }

  function togglePasswordVisibility() {
    if (!els.authPassword) return;
    const isPass = els.authPassword.type === 'password';
    els.authPassword.type = isPass ? 'text' : 'password';
    if (els.authTogglePass) {
      els.authTogglePass.innerHTML = isPass 
        ? '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>'
        : '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>';
    }
  }

  // --- EMPLOYEE PROVISIONING & MANAGEMENT (ADMIN ONLY) ---

  function openTeamModal() {
    if (!state.currentUser || state.currentUser.role !== 'admin') {
      showToast('⚠️ Access Denied: Only Administrator can access Employee Provisioning.');
      return;
    }
    if (els.employeeModal) els.employeeModal.classList.add('open');
    if (els.createEmpAlert) els.createEmpAlert.style.display = 'none';
    fetchTeamRoster();
  }

  function closeTeamModal() {
    if (els.employeeModal) els.employeeModal.classList.remove('open');
  }

  function generateRandomPassword() {
    const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!@#$%';
    let pass = 'Omni@';
    for (let i = 0; i < 6; i++) {
      pass += chars.charAt(Math.floor(Math.random() * chars.length));
    }
    pass += '!';
    if (els.newEmpPassword) els.newEmpPassword.value = pass;
  }

  function handleCreateEmployee(e) {
    if (e) e.preventDefault();
    if (!state.currentUser || state.currentUser.role !== 'admin') {
      showToast('⚠️ Only Administrator can provision employee accounts.');
      return;
    }

    const name = (els.newEmpName?.value || '').trim();
    const username = (els.newEmpUsername?.value || '').trim();
    const password = (els.newEmpPassword?.value || '').trim();
    const designation = (els.newEmpRole?.value || 'Team Member');
    const role = designation === 'Admin' ? 'admin' : (designation === 'Team Lead' ? 'lead' : 'member');
    const phone = (els.newEmpPhone?.value || '').trim();

    if (!name || !username || !password) {
      showCreateEmpAlert('Please fill in Full Name, Username, and Password.', 'error');
      return;
    }

    if (password.length < 6) {
      showCreateEmpAlert('Password must be at least 6 characters.', 'error');
      return;
    }

    if (els.submitNewEmpBtn) {
      els.submitNewEmpBtn.disabled = true;
      els.submitNewEmpBtn.textContent = 'Provisioning...';
    }

    fetch('api/auth.php?action=create_employee', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ name, username, password, designation, role, phone })
    })
    .then(async res => {
      const data = await res.json();
      if (!res.ok || !data.success) {
        throw new Error(data.error || 'Failed to create employee.');
      }
      return data;
    })
    .then(data => {
      showCreateEmpAlert(`✅ Employee account created successfully for ${name} (${username}).`, 'success');
      showToast(`Employee ${name} provisioned!`);
      if (els.newEmpName) els.newEmpName.value = '';
      if (els.newEmpUsername) els.newEmpUsername.value = '';
      if (els.newEmpPassword) els.newEmpPassword.value = '';
      if (els.newEmpPhone) els.newEmpPhone.value = '';
      fetchTeamRoster();
    })
    .catch(err => {
      // Offline fallback: save to localStorage
      const localEmps = getLocalEmployees();
      if (localEmps.some(e => e.username.toLowerCase() === username.toLowerCase())) {
        showCreateEmpAlert(`Username "${username}" is already taken.`, 'error');
        return;
      }
      const newEmp = {
        id: 'EMP-' + (100 + localEmps.length + 1),
        name,
        username,
        email: username + '@nunno.cloud',
        password,
        role,
        designation,
        phone,
        status: 'active',
        created_at: new Date().toLocaleString()
      };
      localEmps.push(newEmp);
      saveLocalEmployees(localEmps);
      showCreateEmpAlert(`✅ Employee account created for ${name} (Stored locally).`, 'success');
      showToast(`Employee ${name} provisioned!`);
      if (els.newEmpName) els.newEmpName.value = '';
      if (els.newEmpUsername) els.newEmpUsername.value = '';
      if (els.newEmpPassword) els.newEmpPassword.value = '';
      if (els.newEmpPhone) els.newEmpPhone.value = '';
      renderRosterList(localEmps);
    })
    .finally(() => {
      if (els.submitNewEmpBtn) {
        els.submitNewEmpBtn.disabled = false;
        els.submitNewEmpBtn.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/></svg><span>Provision Employee Account</span>';
      }
    });
  }

  function showCreateEmpAlert(msg, type) {
    if (!els.createEmpAlert) return;
    els.createEmpAlert.className = `auth-alert ${type}`;
    els.createEmpAlert.textContent = msg;
    els.createEmpAlert.style.display = 'block';
  }

  function fetchTeamRoster() {
    fetch('api/auth.php?action=list_employees')
      .then(res => res.json())
      .then(data => {
        if (data && data.success && Array.isArray(data.employees)) {
          state.employees = data.employees;
          renderRosterList(data.employees);
          if (els.headerEmpCount) els.headerEmpCount.textContent = data.employees.length;
        }
      })
      .catch(() => {
        const localEmps = getLocalEmployees();
        renderRosterList(localEmps);
        if (els.headerEmpCount) els.headerEmpCount.textContent = localEmps.length;
      });
  }

  function renderRosterList(employees) {
    if (!els.teamRosterList) return;
    if (!employees || employees.length === 0) {
      els.teamRosterList.innerHTML = '<div class="text-xs text-muted" style="text-align:center;padding:24px;">No employees registered yet. Add your first closer above!</div>';
      return;
    }

    els.teamRosterList.innerHTML = employees.map(emp => {
      const isAdmin = emp.role === 'admin';
      const isSuspended = emp.status === 'suspended';
      const initials = (emp.name || 'Emp').split(' ').map(n => n[0]).join('').substring(0, 2).toUpperCase();

      return `
        <article class="emp-roster-card ${isSuspended ? 'suspended' : ''}">
          <div class="emp-card-user">
            <div class="emp-avatar ${isAdmin ? 'admin-av' : ''}">${initials}</div>
            <div class="emp-card-identity">
              <div class="emp-card-name">${escapeHtml(emp.name)} ${isAdmin ? '<span class="badge-role-admin" style="font-size:9px;">Admin</span>' : ''}</div>
              <div class="emp-card-sub">
                <span class="emp-card-username">${escapeHtml(emp.username)}</span>
                <span class="emp-card-role">${escapeHtml(emp.designation || emp.role)}</span>
              </div>
            </div>
          </div>
          <div class="emp-actions-group">
            <span class="emp-status-badge ${isSuspended ? 'suspended' : 'active'}">
              <span class="emp-status-indicator" aria-hidden="true"></span>${isSuspended ? 'Suspended' : 'Active'}
            </span>
            ${!isAdmin ? `
              <button type="button" class="btn-emp-action" onclick="window.OmnideskToggleStatus('${emp.id}')" title="${isSuspended ? 'Activate Employee' : 'Suspend Employee'}">
                ${isSuspended ? 'Activate' : 'Suspend'}
              </button>
              <button type="button" class="btn-emp-action delete" onclick="window.OmnideskDeleteEmp('${emp.id}', '${escapeHtml(emp.name)}')" title="Remove employee" aria-label="Remove ${escapeHtml(emp.name)}">
                Remove
              </button>
            ` : ''}
          </div>
        </article>
      `;
    }).join('');
  }

  // Global window hooks for inline onclicks in roster
  window.OmnideskToggleStatus = function(empId) {
    if (!confirm('Toggle status for this employee?')) return;
    fetch('api/auth.php?action=toggle_status', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ id: empId })
    })
    .then(res => res.json())
    .then(data => {
      if (data && data.success) {
        showToast(data.message || 'Status updated.');
        fetchTeamRoster();
      } else {
        alert(data.error || 'Failed to update status.');
      }
    })
    .catch(() => {
      const emps = getLocalEmployees();
      const target = emps.find(e => e.id === empId);
      if (target) {
        target.status = target.status === 'active' ? 'suspended' : 'active';
        saveLocalEmployees(emps);
        renderRosterList(emps);
        showToast(`Employee status set to ${target.status}.`);
      }
    });
  };

  window.OmnideskDeleteEmp = function(empId, empName) {
    if (!confirm(`Are you sure you want to permanently delete employee "${empName}"?`)) return;
    fetch('api/auth.php?action=delete_employee', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ id: empId })
    })
    .then(res => res.json())
    .then(data => {
      if (data && data.success) {
        showToast('Employee removed.');
        fetchTeamRoster();
      } else {
        alert(data.error || 'Failed to delete employee.');
      }
    })
    .catch(() => {
      let emps = getLocalEmployees();
      emps = emps.filter(e => e.id !== empId);
      saveLocalEmployees(emps);
      renderRosterList(emps);
      showToast('Employee removed (local).');
    });
  };

  function getLocalEmployees() {
    const raw = localStorage.getItem('nunno_local_employees');
    if (raw) {
      try { return JSON.parse(raw); } catch (e) {}
    }
    return [
      { id: 'USR-ADMIN-01', name: 'System Administrator', username: 'admin', email: 'admin@nunno.cloud', password: 'Admin@Omnidesk2026!', role: 'admin', designation: 'Admin', status: 'active' },
      { id: 'EMP-101', name: 'Hamza Khan', username: 'hamza', email: 'hamza@nunno.cloud', password: 'Agent@123', role: 'member', designation: 'Team Member', status: 'active' }
    ];
  }

  function saveLocalEmployees(list) {
    localStorage.setItem('nunno_local_employees', JSON.stringify(list));
  }

  // Connect WhatsApp Number Modal Event Listeners
  if (els.connectedNumberPill) els.connectedNumberPill.addEventListener('click', openConnectWaModal);
  if (els.campaignConnectionStatus) els.campaignConnectionStatus.addEventListener('click', openConnectWaModal);
  if (els.closeConnectWaModalBtn) els.closeConnectWaModalBtn.addEventListener('click', closeConnectWaModal);
  if (els.cancelConnectWaBtn) els.cancelConnectWaBtn.addEventListener('click', closeConnectWaModal);
  if (els.saveConnectedWaBtn) els.saveConnectedWaBtn.addEventListener('click', saveConnectedWaNumber);
  if (els.reconnectWaBtn) els.reconnectWaBtn.addEventListener('click', reconnectWhatsApp);

  // Authentication & Employee Modal Event Listeners
  if (els.authForm) els.authForm.addEventListener('submit', handleLogin);
  if (els.authSubmitBtn) els.authSubmitBtn.addEventListener('click', handleLogin);
  if (els.authTogglePass) els.authTogglePass.addEventListener('click', togglePasswordVisibility);

  document.getElementById('quickFillAdminBtn')?.addEventListener('click', () => {
    if (els.authUsername) els.authUsername.value = 'admin';
    if (els.authPassword) els.authPassword.value = 'Admin@Omnidesk2026!';
    if (els.authAlert) els.authAlert.style.display = 'none';
  });
  document.getElementById('quickFillEmpBtn')?.addEventListener('click', () => {
    if (els.authUsername) els.authUsername.value = 'hamza';
    if (els.authPassword) els.authPassword.value = 'Agent@123';
    if (els.authAlert) els.authAlert.style.display = 'none';
  });

  if (els.headerLogoutBtn) els.headerLogoutBtn.addEventListener('click', handleLogout);
  if (els.openTeamModalBtn) els.openTeamModalBtn.addEventListener('click', openTeamModal);
  if (els.closeTeamModalBtn) els.closeTeamModalBtn.addEventListener('click', closeTeamModal);
  if (els.genEmpPassBtn) els.genEmpPassBtn.addEventListener('click', generateRandomPassword);
  if (els.createEmpForm) els.createEmpForm.addEventListener('submit', handleCreateEmployee);
  if (els.submitNewEmpBtn) els.submitNewEmpBtn.addEventListener('click', handleCreateEmployee);
  if (els.refreshTeamBtn) els.refreshTeamBtn.addEventListener('click', fetchTeamRoster);

  // ==========================================
  // MODAL 5: MARK PAID (DEAL WON POPUP)
  // ==========================================

  function openMarkPaidModal(lead) {
    if (!lead) return;
    state.activeLeadId = lead.id;

    if (els.paidCustomerName) els.paidCustomerName.value = lead.name || '';
    if (els.paidCustomerPhone) els.paidCustomerPhone.value = lead.display || lead.phone || '';
    if (els.paidCustomerEmail) els.paidCustomerEmail.value = lead.email || '';
    if (els.paidTrxId) els.paidTrxId.value = (lead.paidDetails && lead.paidDetails.trxId) ? lead.paidDetails.trxId : '';

    const closerName = state.currentUser ? state.currentUser.name : state.currentAgent.name;
    if (els.paidCloserName) els.paidCloserName.textContent = closerName;

    // Default plan & amount based on lead interest
    let defPlan = 'Pro';
    let defAmount = 5000;
    if (lead.paidDetails && lead.paidDetails.plan) {
      defPlan = lead.paidDetails.plan;
      defAmount = lead.paidDetails.amount || 5000;
    } else if (lead.interest && lead.interest.includes('Starter')) {
      defPlan = 'Starter';
      defAmount = 1700;
    } else if (lead.interest && lead.interest.includes('Whale')) {
      defPlan = 'Whale VIP';
      defAmount = 15000;
    }

    if (els.paidPlanSelect) els.paidPlanSelect.value = defPlan;
    if (els.paidAmountInput) els.paidAmountInput.value = defAmount;
    if (els.paidMethodSelect) {
      els.paidMethodSelect.value = (lead.paidDetails && lead.paidDetails.method) ? lead.paidDetails.method : 'EasyPaisa';
    }

    if (els.markPaidModal) els.markPaidModal.classList.add('open');
  }

  function closeMarkPaidModal() {
    if (els.markPaidModal) els.markPaidModal.classList.remove('open');
  }

  if (els.paidPlanSelect) {
    els.paidPlanSelect.addEventListener('change', () => {
      const p = els.paidPlanSelect.value;
      if (p === 'Pro') els.paidAmountInput.value = 5000;
      else if (p === 'Starter') els.paidAmountInput.value = 1700;
      else if (p === 'Whale VIP') els.paidAmountInput.value = 15000;
      else if (p === 'Enterprise 3-Month') els.paidAmountInput.value = 12000;
    });
  }

  function handleMarkPaidSubmit(e) {
    if (e) e.preventDefault();
    const lead = getLead(state.activeLeadId);
    if (!lead) return;

    const name = (els.paidCustomerName?.value || '').trim() || lead.name;
    const plan = (els.paidPlanSelect?.value || 'Pro');
    const amount = parseInt(els.paidAmountInput?.value, 10) || 5000;
    const method = (els.paidMethodSelect?.value || 'EasyPaisa');
    const email = (els.paidCustomerEmail?.value || '').trim();
    const trxId = (els.paidTrxId?.value || '').trim();

    const closerName = state.currentUser ? state.currentUser.name : state.currentAgent.name;
    const closerId = state.currentUser ? state.currentUser.id : state.currentAgent.id;

    lead.name = name;
    lead.email = email;
    lead.leadState = 'pending_verification';
    lead.status = 'pending_verification';
    lead.verificationStatus = 'pending';
    if (!lead.ownerId) {
      lead.ownerId = closerId;
      lead.ownerName = closerName;
    }

    lead.paidDetails = {
      plan: plan,
      amount: amount,
      method: method,
      email: email,
      trxId: trxId,
      closedBy: closerName,
      closedById: closerId,
      closedAt: new Date().toISOString()
    };

    const now = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    lead.messages.push({
      sender: 'agent',
      agentName: closerName,
      text: `⚪ PAYMENT SUBMITTED (PENDING VERIFICATION)\n• Plan: ${plan} (Rs. ${amount.toLocaleString()})\n• Method: ${method}${trxId ? '\n• Trx Ref: ' + trxId : ''}${email ? '\n• Account Email: ' + email : ''}\n• Closer: ${closerName}\n⏳ Awaiting Admin verification in the Admin Portal before 10k target & commission score are credited.`,
      time: now
    });

    closeMarkPaidModal();
    showToast(`⚪ Sale submitted! Status set to Grey (Pending Admin Verification). 🛡️`);
    renderConversationsList();
    renderActiveChat();
    renderAgentProfile();

    syncLeadToServer(lead, {
      leadState: 'pending_verification',
      status: 'pending_verification',
      verificationStatus: 'pending',
      name: lead.name,
      email: lead.email,
      paidDetails: lead.paidDetails,
      newMessage: lead.messages[lead.messages.length - 1]
    });
  }

  // Admin Anti-Fraud Action: Verify & Approve Deal (Turns Green & Credits Score)
  function verifyPaymentDeal(leadId) {
    const user = state.currentUser;
    const currentRawRole = (user && user.designation) || (user && user.role) || state.currentAgent.role || 'Team Member';
    const currentRole = normalizeRole(currentRawRole);
    if (currentRole !== 'Admin') {
      showToast('❌ Only Administrators can verify and approve payments.');
      return;
    }

    const lead = getLead(leadId);
    if (!lead) return;

    const adminName = user ? user.name : 'System Administrator';
    const now = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    const plan = lead.paidDetails?.plan || lead.interest || 'Nunno Pro';
    const amount = lead.paidDetails?.amount ? Number(lead.paidDetails.amount) : 5000;
    const closerName = lead.paidDetails?.closedBy || lead.ownerName || 'Closer';

    lead.leadState = 'paid';
    lead.status = 'paid';
    lead.verificationStatus = 'verified';
    lead.verifiedBy = adminName;
    lead.verifiedAt = new Date().toISOString();

    lead.messages.push({
      sender: 'agent',
      agentName: 'Admin Audit Verification',
      text: `🟢 PAYMENT VERIFIED & APPROVED (DEAL WON)!\n• Verified By: ${adminName}\n• Closer Credited: ${closerName}\n• Plan: ${plan} (Rs. ${amount.toLocaleString()})\n• Transaction officially verified on banking portal. Account activated! 🚀`,
      time: now
    });

    showToast(`🟢 Payment Verified! Deal won and turned Green (Rs. ${amount.toLocaleString()}). 🚀`);
    renderConversationsList();
    renderActiveChat();
    renderAgentProfile();
    if (els.scoreModal && els.scoreModal.classList.contains('open')) {
      renderScoreData(state.activeScoreTab || 'verify');
    }

    syncLeadToServer(lead, {
      leadState: 'paid',
      status: 'paid',
      verificationStatus: 'verified',
      verifiedBy: adminName,
      verifiedAt: lead.verifiedAt,
      newMessage: lead.messages[lead.messages.length - 1]
    });
  }

  // Admin Anti-Fraud Action: Reject Fake Deal (Reverts Lead & Awards Zero Score)
  function rejectFakeDeal(leadId) {
    const user = state.currentUser;
    const currentRawRole = (user && user.designation) || (user && user.role) || state.currentAgent.role || 'Team Member';
    const currentRole = normalizeRole(currentRawRole);
    if (currentRole !== 'Admin') {
      showToast('❌ Only Administrators can reject fake transactions.');
      return;
    }

    const lead = getLead(leadId);
    if (!lead) return;

    const defaultReason = 'Fake Transaction / Payment Not Received on EasyPaisa or Bank';
    const reason = prompt('Reason for rejecting this deal (Fake sale / Invalid Ref / No deposit):', defaultReason);
    if (reason === null) return; // User cancelled prompt

    const adminName = user ? user.name : 'System Administrator';
    const now = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    const prevCloser = lead.paidDetails?.closedBy || lead.ownerName || 'Closer';

    // Revert lead back to ongoing or fresh
    lead.leadState = lead.ownerId ? 'ongoing' : 'fresh';
    lead.status = lead.ownerId ? 'replied' : 'unread';
    lead.verificationStatus = 'rejected';
    lead.rejectionReason = reason || defaultReason;
    lead.rejectedBy = adminName;
    lead.rejectedAt = new Date().toISOString();
    lead.paidDetails = null;

    lead.messages.push({
      sender: 'agent',
      agentName: 'Security & Anti-Fraud Audit',
      text: `⚠️ PAYMENT REJECTED (FAKE SALE SHIELD):\n• Rejected By Admin: ${adminName}\n• Reason: ${reason || defaultReason}\n• Submitter Closer: ${prevCloser}\nDeal has been rejected and reverted. Zero sales credit or commission awarded.`,
      time: now
    });

    showToast(`❌ Fake deal rejected! Lead reverted to ${lead.leadState}. Zero score awarded.`);
    renderConversationsList();
    renderActiveChat();
    renderAgentProfile();
    if (els.scoreModal && els.scoreModal.classList.contains('open')) {
      renderScoreData(state.activeScoreTab || 'verify');
    }

    syncLeadToServer(lead, {
      leadState: lead.leadState,
      status: lead.status,
      verificationStatus: 'rejected',
      rejectionReason: lead.rejectionReason,
      paidDetails: null,
      newMessage: lead.messages[lead.messages.length - 1]
    });
  }

  // ==========================================
  // MODAL 6: CAN'T HANDLE (RELEASE LEAD TO RED POOL)
  // ==========================================

  function openCantHandleModal(lead) {
    if (!lead) return;
    state.activeLeadId = lead.id;

    if (els.cantHandleClientName) {
      els.cantHandleClientName.textContent = `${lead.name} (${lead.display || lead.phone})`;
    }
    if (els.cantHandleNote) {
      els.cantHandleNote.value = '';
    }
    if (els.cantHandleModal) {
      els.cantHandleModal.classList.add('open');
    }
  }

  function closeCantHandleModal() {
    if (els.cantHandleModal) els.cantHandleModal.classList.remove('open');
  }

  function handleCantHandleSubmit(e) {
    if (e) e.preventDefault();
    const lead = getLead(state.activeLeadId);
    if (!lead) return;

    const reason = els.cantHandleReasonSelect?.value || 'Requested handover to team';
    const note = (els.cantHandleNote?.value || '').trim();
    const closerName = state.currentUser ? state.currentUser.name : state.currentAgent.name;

    const now = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    lead.messages.push({
      sender: 'agent',
      agentName: 'System Handover',
      text: `⚠️ HANDOVER TO OPEN POOL: Closer ${closerName} released this lead back to Fresh (Red) pool.\n• Reason: ${reason}${note ? '\n• Handover Note: ' + note : ''}`,
      time: now
    });

    // Append to lead notes
    const handoverLog = `[Handover ${new Date().toLocaleDateString()} by ${closerName}]: ${reason}${note ? ' - ' + note : ''}`;
    lead.notes = lead.notes ? `${lead.notes}\n${handoverLog}` : handoverLog;

    // Reset status back to Fresh (Red) and release claim
    lead.ownerId = null;
    lead.ownerName = null;
    lead.leadState = 'fresh';
    lead.status = 'unread';
    lead.unreadCount = (lead.unreadCount || 0) + 1;

    closeCantHandleModal();
    showToast(`🔴 Lead released back to Fresh (Red) pool for team closers!`);

    // On mobile screens return to list so closer can pick another lead
    document.body.classList.remove('mobile-in-chat');
    if (els.viewInbox) {
      els.viewInbox.classList.remove('mobile-chat-open');
    }

    renderConversationsList();
    renderActiveChat();
    renderAgentProfile();

    syncLeadToServer(lead, {
      action: 'release_lead',
      ownerName: null,
      notes: lead.notes,
      handoverReason: reason,
      handoverNote: note,
      newMessage: lead.messages[lead.messages.length - 1]
    });
  }

  // ==========================================
  // MODAL 7: PERFORMANCE SCOREBOARD & TEAM LEADERBOARD
  // ==========================================

  function openScoreModal(initialTab = 'my') {
    renderScoreData(initialTab);
    if (els.scoreModal) els.scoreModal.classList.add('open');
  }

  function closeScoreModal() {
    if (els.scoreModal) els.scoreModal.classList.remove('open');
  }

  // Helper: Strictly normalize designations/roles to the 3 allowed types: Admin, Team Lead, Team Member
  function normalizeRole(roleStr = '') {
    const lower = String(roleStr).toLowerCase();
    if (lower.includes('admin')) return 'Admin';
    if (lower.includes('lead')) return 'Team Lead';
    return 'Team Member';
  }

  // Helper: Post-target task commission calculator
  // On Rs. 1,700 sale -> Rs. 700 commission
  // On Rs. 5,000 sale -> Rs. 2,000 commission
  // On Rs. 15,000 sale -> Rs. 10,000 commission
  function getDealCommission(amt, planName = '') {
    const num = Number(amt) || 0;
    if (num >= 15000 || (planName && planName.toLowerCase().includes('whale'))) return 10000;
    if (num >= 5000 || (planName && planName.toLowerCase().includes('pro'))) return 2000;
    if (num >= 1700 || (planName && planName.toLowerCase().includes('starter'))) return 700;
    return 0;
  }

  // Core Calculator: Computes 10k target eligibility, Base Salary, and Post-Target Commissions
  function calculateCloserPerformance(closerId) {
    // Only VERIFIED deals count towards company revenue, 10k target, and commissions
    const paidLeads = state.leads.filter(l => 
      (l.leadState === 'paid' || l.status === 'paid') && 
      (l.paidDetails?.closedById === closerId || l.ownerId === closerId)
    );

    // Pending deals undergoing Admin verification
    const pendingLeads = state.leads.filter(l => 
      (l.leadState === 'pending_verification' || l.status === 'pending_verification') && 
      (l.paidDetails?.closedById === closerId || l.ownerId === closerId)
    );

    const TARGET = 10000;
    const BASE_SALARY_AMOUNT = 10000;

    let runningSales = 0;
    let commissionsEarned = 0;

    const dealLedger = paidLeads.map(l => {
      const amt = l.paidDetails?.amount ? Number(l.paidDetails.amount) : (l.interest && l.interest.includes('Starter') ? 1700 : (l.interest && l.interest.includes('Whale') ? 15000 : 5000));
      const plan = l.paidDetails?.plan || l.interest || 'Nunno Pro';
      const method = l.paidDetails?.method || 'EasyPaisa';
      const email = l.paidDetails?.email || l.email || '';
      const closedAt = l.paidDetails?.closedAt ? new Date(l.paidDetails.closedAt).toLocaleDateString() : l.lastActive;

      const salesBeforeDeal = runningSales;
      runningSales += amt;

      let dealCommission = 0;
      let dealTag = '';

      if (salesBeforeDeal >= TARGET) {
        // Post-target sale: awards task commission
        dealCommission = getDealCommission(amt, plan);
        commissionsEarned += dealCommission;
        dealTag = `💰 Commission: +Rs. ${dealCommission.toLocaleString()}`;
      } else if (runningSales >= TARGET) {
        // Milestone deal that passed the 10,000 sales target
        dealTag = `🎯 10k Target Met (Unlocked Rs. 10k Salary!)`;
      } else {
        const pct = Math.round((runningSales / TARGET) * 100);
        dealTag = `🎯 Funded 10k Target (${pct}%)`;
      }

      return {
        id: l.id,
        name: l.name,
        display: l.display || l.phone,
        email,
        plan,
        amt,
        method,
        closedAt,
        dealCommission,
        dealTag,
        isPending: false
      };
    });

    // Show pending deals in closer's ledger
    pendingLeads.forEach(l => {
      const amt = l.paidDetails?.amount ? Number(l.paidDetails.amount) : 5000;
      const plan = l.paidDetails?.plan || l.interest || 'Nunno Pro';
      const method = l.paidDetails?.method || 'EasyPaisa';
      const email = l.paidDetails?.email || l.email || '';
      const closedAt = l.paidDetails?.closedAt ? new Date(l.paidDetails.closedAt).toLocaleDateString() : l.lastActive;

      dealLedger.unshift({
        id: l.id,
        name: l.name,
        display: l.display || l.phone,
        email,
        plan,
        amt,
        method,
        closedAt,
        dealCommission: 0,
        dealTag: `⚪ In Review (Awaiting Admin Audit)`,
        isPending: true
      });
    });

    const totalSales = runningSales;
    const isTargetAchieved = totalSales >= TARGET;
    const baseSalary = isTargetAchieved ? BASE_SALARY_AMOUNT : 0;
    const totalPayout = baseSalary + commissionsEarned;

    return {
      paidLeads,
      pendingLeads,
      dealLedger,
      totalSales,
      isTargetAchieved,
      baseSalary,
      commissionsEarned,
      totalPayout
    };
  }

  function renderScoreData(activeTab = 'my') {
    state.activeScoreTab = activeTab;
    const user = state.currentUser;
    const currentId = user ? user.id : state.currentAgent.id;
    const currentName = user ? user.name : state.currentAgent.name;
    const currentRawRole = (user && user.designation) || (user && user.role) || state.currentAgent.role || 'Team Member';
    const currentRole = normalizeRole(currentRawRole);
    const isAdmin = currentRole === 'Admin';

    if (!isAdmin && activeTab === 'verify') {
      activeTab = 'my';
    }
    state.activeScoreTab = activeTab;

    const scoreTitle = document.getElementById('scoreModalTitle');
    const scoreBadge = document.getElementById('scoreModalBadge');
    if (scoreTitle) {
      scoreTitle.textContent = isAdmin ? 'Executive Performance & Score Center' : 'Employee Closer Portal & Financial Ledger';
    }
    if (scoreBadge) {
      scoreBadge.textContent = isAdmin ? '👑 Admin Suite' : '💼 Employee Portal';
    }

    // Count pending verification deals
    const allPendingDeals = state.leads.filter(l => getLeadState(l) === 'pending_verification');
    if (els.pendingVerifCount) els.pendingVerifCount.textContent = allPendingDeals.length;
    if (els.pendingAuditHeaderCount) els.pendingAuditHeaderCount.textContent = `${allPendingDeals.length} Pending Deals`;

    // Toggle tab display
    if (els.tabMyScore) els.tabMyScore.classList.toggle('active', activeTab === 'my');
    if (els.tabVerifySales) {
      els.tabVerifySales.style.display = isAdmin ? 'inline-flex' : 'none';
      els.tabVerifySales.classList.toggle('active', activeTab === 'verify');
    }

    if (els.viewMyScoreTab) els.viewMyScoreTab.style.display = activeTab === 'my' ? 'block' : 'none';
    if (els.viewVerifySalesTab) els.viewVerifySalesTab.style.display = (activeTab === 'verify' && isAdmin) ? 'block' : 'none';

    // --- TAB 1: User Personal Performance Portal ---
    const myPerf = calculateCloserPerformance(currentId);
    const myRevenue = myPerf.totalSales;
    const myDealsWon = myPerf.paidLeads.length;

    if (els.perfHeroAvatar) els.perfHeroAvatar.textContent = isAdmin ? '👑' : (currentRole === 'Team Lead' ? '⚡' : '👤');
    if (els.perfHeroName) els.perfHeroName.textContent = currentName;
    if (els.perfHeroRole) els.perfHeroRole.textContent = `${currentRole} • ${currentId}`;
    if (els.perfTierBadge) {
      els.perfTierBadge.innerHTML = `<span>${currentRole === 'Admin' ? '👑 Admin' : (currentRole === 'Team Lead' ? '⚡ Team Lead' : '👤 Team Member')}</span>`;
    }

    // Visual Progress Taskbar
    const pct = Math.min(100, Math.round((myRevenue / 10000) * 100));
    if (els.taskbarFill) {
      els.taskbarFill.style.width = `${pct}%`;
      els.taskbarFill.classList.toggle('target-met', myPerf.isTargetAchieved);
    }
    if (els.taskbarCurrent) els.taskbarCurrent.textContent = `Rs. ${myRevenue.toLocaleString()}`;
    if (els.taskbarPct) els.taskbarPct.textContent = `${pct}% Completed`;
    if (els.taskbarGoal) els.taskbarGoal.textContent = `Target: Rs. 10,000`;

    // Milestone Badge
    if (els.milestoneStatusBadge) {
      if (myPerf.isTargetAchieved) {
        els.milestoneStatusBadge.className = 'milestone-status-badge unlocked';
        if (els.targetBadgeIcon) els.targetBadgeIcon.textContent = '✅';
        if (els.targetBadgeText) els.targetBadgeText.textContent = '10k Target Achieved (Unlocked)';
      } else {
        els.milestoneStatusBadge.className = 'milestone-status-badge';
        if (els.targetBadgeIcon) els.targetBadgeIcon.textContent = '⏳';
        if (els.targetBadgeText) els.targetBadgeText.textContent = `Target In Progress (${pct}%)`;
      }
    }

    // Milestone Feedback Note
    if (els.milestoneFeedback) {
      if (myPerf.isTargetAchieved) {
        els.milestoneFeedback.className = 'milestone-feedback unlocked';
        els.milestoneFeedback.innerHTML = `🎉 <strong>Milestone Achieved!</strong> You have successfully passed the PKR 10,000 monthly sales target. Your <strong>Rs. 10,000 Base Salary</strong> is fully unlocked, and task commissions are actively paid on every additional sale!`;
      } else {
        els.milestoneFeedback.className = 'milestone-feedback';
        const remaining = 10000 - myRevenue;
        els.milestoneFeedback.innerHTML = `Close <strong>Rs. ${remaining.toLocaleString()}</strong> more in customer sales to pass your milestone and unlock your <strong>Rs. 10,000 Base Salary</strong>.`;
      }
    }

    // Financial Breakdown Cards
    if (els.myBaseSalary) els.myBaseSalary.textContent = `Rs. ${myPerf.baseSalary.toLocaleString()}`;
    if (els.myBaseSalarySub) els.myBaseSalarySub.textContent = myPerf.isTargetAchieved ? '✅ Target Passed (Qualified)' : 'Requires 10k Target';
    if (els.myCommissionsEarned) els.myCommissionsEarned.textContent = `Rs. ${myPerf.commissionsEarned.toLocaleString()}`;
    if (els.myTotalPayout) els.myTotalPayout.textContent = `Rs. ${myPerf.totalPayout.toLocaleString()}`;
    if (els.myTotalRevenue) els.myTotalRevenue.textContent = `Rs. ${myRevenue.toLocaleString()}`;
    if (els.myDealsWonSub) els.myDealsWonSub.textContent = `${myDealsWon} Verified Deals Won`;
    if (els.myDealsCountLabel) els.myDealsCountLabel.textContent = `${myDealsWon} Deals Won`;

    // Render Deals & Commission Audit Ledger
    if (els.myDealsList) {
      if (myPerf.dealLedger.length === 0) {
        els.myDealsList.innerHTML = `
          <div style="text-align:center;padding:24px;color:var(--text-dim);font-size:12px;">
            No closed deals yet. Claim fresh leads from the Red pool and close your first subscription to begin funding your 10k sales target!
          </div>
        `;
      } else {
        els.myDealsList.innerHTML = myPerf.dealLedger.map(deal => {
          return `
            <div class="perf-deal-row" style="${deal.isPending ? 'border-left:3px solid #94a3b8;opacity:0.9;' : ''}">
              <div class="perf-deal-client">
                <div class="perf-deal-name">${escapeHtml(deal.name)}</div>
                <div class="perf-deal-sub mono">${escapeHtml(deal.display)}${deal.email ? ' &bull; ' + escapeHtml(deal.email) : ''}</div>
              </div>
              <div class="perf-deal-plan">
                <span class="badge-role-admin" style="font-size:10px;background:#161d28;border-color:#2a3942;color:#38bdf8;">${escapeHtml(deal.plan)}</span>
                <span class="text-xs text-muted" style="margin-left:4px;">${escapeHtml(deal.method)}</span>
              </div>
              <div style="text-align:right;">
                <div class="perf-deal-amount mono">Rs. ${deal.amt.toLocaleString()}</div>
                <div class="text-xs" style="font-weight:600;color:${deal.isPending ? '#cbd5e1' : (deal.dealCommission > 0 ? '#10b981' : '#fbbf24')};">${deal.dealTag}</div>
              </div>
              <div class="perf-deal-date text-xs text-muted">${deal.closedAt}</div>
            </div>
          `;
        }).join('');
      }
    }

    // --- TAB 2: Admin Organization Leaderboard ---
    const allPaidLeads = state.leads.filter(l => l.leadState === 'paid' || l.status === 'paid');
    const orgTotalRevenue = allPaidLeads.reduce((sum, l) => {
      const amt = l.paidDetails?.amount ? Number(l.paidDetails.amount) : (l.interest && l.interest.includes('Starter') ? 1700 : 5000);
      return sum + amt;
    }, 0);
    const orgTotalDeals = allPaidLeads.length;

    // Collect all registered employees & default agents
    const closersMap = new Map();

    defaultAgents.forEach(a => {
      closersMap.set(a.id, {
        id: a.id,
        name: a.name,
        role: normalizeRole(a.role),
        avatar: a.avatar || '👤'
      });
    });

    state.employees.forEach(e => {
      closersMap.set(e.id, {
        id: e.id,
        name: e.name,
        role: normalizeRole(e.designation || e.role),
        avatar: normalizeRole(e.designation || e.role) === 'Admin' ? '👑' : (normalizeRole(e.designation || e.role) === 'Team Lead' ? '⚡' : '👤')
      });
    });

    if (user && !closersMap.has(user.id)) {
      closersMap.set(user.id, {
        id: user.id,
        name: user.name,
        role: normalizeRole(user.designation || user.role),
        avatar: isAdmin ? '👑' : '👤'
      });
    }

    // Compute metrics for all team members
    let orgSalariesUnlocked = 0;
    let orgCommissionsTotal = 0;

    const rankedClosers = Array.from(closersMap.values()).map(c => {
      const perf = calculateCloserPerformance(c.id);
      if (perf.isTargetAchieved) orgSalariesUnlocked++;
      orgCommissionsTotal += perf.commissionsEarned;

      return {
        id: c.id,
        name: c.name,
        role: c.role,
        avatar: c.avatar,
        dealsWon: perf.paidLeads.length,
        revenue: perf.totalSales,
        isTargetAchieved: perf.isTargetAchieved,
        baseSalary: perf.baseSalary,
        commissions: perf.commissionsEarned,
        totalPayout: perf.totalPayout
      };
    }).sort((a, b) => {
      if (b.totalPayout !== a.totalPayout) return b.totalPayout - a.totalPayout;
      return b.revenue - a.revenue;
    });

    if (els.orgTotalRevenue) els.orgTotalRevenue.textContent = `Rs. ${orgTotalRevenue.toLocaleString()}`;
    if (els.orgTotalDeals) els.orgTotalDeals.textContent = orgTotalDeals;
    if (els.orgSalariesUnlocked) els.orgSalariesUnlocked.textContent = `${orgSalariesUnlocked} / ${rankedClosers.length} Qualified`;
    if (els.orgCommissionsTotal) els.orgCommissionsTotal.textContent = `Rs. ${orgCommissionsTotal.toLocaleString()}`;

    // Render Leaderboard Rows with 10k Target, Salary, and Commission
    if (els.teamLeaderboardTable) {
      els.teamLeaderboardTable.innerHTML = `
        <div class="leaderboard-table-header" style="display:grid;grid-template-columns:40px 1.4fr 110px 80px 100px 90px 90px 110px;gap:8px;padding:10px 14px;background:#161d28;border-radius:8px;font-size:11px;font-weight:700;color:#94a3b8;text-transform:uppercase;margin-bottom:8px;">
          <span>Rank</span>
          <span>Team Member & Role</span>
          <span style="text-align:center;">10k Target</span>
          <span style="text-align:center;">Deals</span>
          <span style="text-align:right;">Total Sales</span>
          <span style="text-align:right;">Salary</span>
          <span style="text-align:right;">Commission</span>
          <span style="text-align:right;">Take-Home Pay</span>
        </div>
        ${rankedClosers.map((c, idx) => {
          const isCurrentUser = c.id === currentId;
          const targetPct = Math.min(100, Math.round((c.revenue / 10000) * 100));
          let rankBadge = `#${idx + 1}`;
          if (idx === 0) rankBadge = '🥇';
          else if (idx === 1) rankBadge = '🥈';
          else if (idx === 2) rankBadge = '🥉';

          return `
            <div class="leaderboard-row ${isCurrentUser ? 'current-user' : ''}" style="display:grid;grid-template-columns:40px 1.4fr 110px 80px 100px 90px 90px 110px;gap:8px;align-items:center;padding:11px 14px;background:#11161f;border:1px solid #1f2836;border-radius:8px;margin-bottom:6px;font-size:12px;">
              <div class="leaderboard-rank mono" style="font-weight:800;">${rankBadge}</div>
              <div style="display:flex;align-items:center;gap:8px;overflow:hidden;">
                <div style="width:28px;height:28px;border-radius:50%;background:#1e293b;display:flex;align-items:center;justify-content:center;font-size:14px;flex-shrink:0;">${c.avatar || '👤'}</div>
                <div style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                  <div style="font-weight:700;color:#fff;">
                    ${escapeHtml(c.name)}
                    ${isCurrentUser ? '<span style="font-size:9px;background:#062b1e;color:#34d399;border:1px solid #047857;padding:1px 5px;border-radius:4px;margin-left:4px;">You</span>' : ''}
                  </div>
                  <div style="font-size:10.5px;color:#8696a0;">${escapeHtml(c.role)} &bull; <span class="mono">${escapeHtml(c.id)}</span></div>
                </div>
              </div>
              <div style="text-align:center;">
                <span style="display:inline-block;padding:2px 8px;border-radius:12px;font-size:10px;font-weight:700;background:${c.isTargetAchieved ? '#062b1e' : '#241c09'};color:${c.isTargetAchieved ? '#34d399' : '#fbbf24'};border:1px solid ${c.isTargetAchieved ? '#047857' : '#78350f'};">
                  ${c.isTargetAchieved ? '✅ Unlocked' : `⏳ ${targetPct}%`}
                </span>
              </div>
              <div class="mono" style="text-align:center;font-weight:700;color:#e2e8f0;">${c.dealsWon}</div>
              <div class="mono" style="text-align:right;font-weight:700;color:#38bdf8;">Rs. ${c.revenue.toLocaleString()}</div>
              <div class="mono" style="text-align:right;color:${c.baseSalary > 0 ? '#38bdf8' : '#64748b'};font-weight:600;">Rs. ${c.baseSalary.toLocaleString()}</div>
              <div class="mono" style="text-align:right;color:${c.commissions > 0 ? '#a855f7' : '#64748b'};font-weight:600;">Rs. ${c.commissions.toLocaleString()}</div>
              <div class="mono" style="text-align:right;font-weight:900;color:#10b981;font-size:13px;">Rs. ${c.totalPayout.toLocaleString()}</div>
            </div>
          `;
        }).join('')}
      `;
    }

    // --- TAB 3: Admin Transaction Audit & Verification Portal ---
    if (els.verifySalesList && activeTab === 'verify') {
      if (allPendingDeals.length === 0) {
        els.verifySalesList.innerHTML = `
          <div style="text-align:center;padding:40px 20px;background:var(--surface-0);border:1px solid var(--border-subtle);border-radius:12px;">
            <div style="font-size:36px;margin-bottom:10px;">🛡️✅</div>
            <h4 style="margin:0 0 6px 0;font-size:16px;color:#ffffff;font-weight:700;">Zero Pending Verifications</h4>
            <p class="text-xs text-muted" style="max-width:440px;margin:0 auto;">All customer subscription payments have been audited and verified. Incoming sales will appear here in Grey for Admin verification before green status and commission score unlock.</p>
          </div>
        `;
      } else {
        els.verifySalesList.innerHTML = allPendingDeals.map(lead => {
          const p = lead.paidDetails || {};
          const plan = p.plan || lead.interest || 'Nunno Pro';
          const amt = p.amount ? Number(p.amount) : 5000;
          const method = p.method || 'EasyPaisa';
          const trxId = p.trxId || 'N/A';
          const closer = p.closedBy || lead.ownerName || 'Closer';
          const email = p.email || lead.email || 'None';
          const submittedAt = p.closedAt ? new Date(p.closedAt).toLocaleString() : lead.lastActive;
          const initials = lead.name.split(' ').map(n => n[0]).join('').substring(0, 2).toUpperCase();

          return `
            <div class="verify-deal-card">
              <div class="verify-deal-top">
                <div class="verify-deal-customer">
                  <div class="verify-deal-avatar">${initials}</div>
                  <div>
                    <div class="verify-deal-name">${escapeHtml(lead.name)}</div>
                    <div class="verify-deal-sub mono">${escapeHtml(lead.display || lead.phone)} &bull; ${escapeHtml(lead.city || 'Pakistan')}</div>
                  </div>
                </div>
                <div class="verify-deal-amount-wrap">
                  <div class="verify-deal-amount">PKR ${amt.toLocaleString()}</div>
                  <div class="verify-deal-plan">${escapeHtml(plan)}</div>
                </div>
              </div>

              <div class="verify-audit-grid">
                <div class="verify-audit-item">
                  <span class="verify-audit-label">Submitted By Closer</span>
                  <span class="verify-audit-val" style="color:#fbbf24;">👤 ${escapeHtml(closer)}</span>
                </div>
                <div class="verify-audit-item">
                  <span class="verify-audit-label">Payment Gateway</span>
                  <span class="verify-audit-val">${escapeHtml(method)}</span>
                </div>
                <div class="verify-audit-item">
                  <span class="verify-audit-label">Trx ID / Bank Ref</span>
                  <span class="verify-audit-val mono" style="color:#38bdf8;">${escapeHtml(trxId)}</span>
                </div>
                <div class="verify-audit-item">
                  <span class="verify-audit-label">Client Email</span>
                  <span class="verify-audit-val">${escapeHtml(email)}</span>
                </div>
                <div class="verify-audit-item" style="grid-column: 1 / -1;">
                  <span class="verify-audit-label">Submission Timestamp</span>
                  <span class="verify-audit-val text-muted" style="font-size:11px;">⏱️ ${submittedAt}</span>
                </div>
              </div>

              <div class="verify-actions-row">
                <button type="button" class="btn btn-ghost btn-xs" onclick="selectLead('${lead.id}'); closeScoreModal();">
                  💬 View Chat
                </button>
                <button type="button" class="btn btn-rose btn-sm" onclick="rejectFakeDeal('${lead.id}')">
                  ❌ Reject Fake Sale
                </button>
                <button type="button" class="btn btn-emerald btn-sm" onclick="verifyPaymentDeal('${lead.id}')">
                  ✅ Verify & Turn Green
                </button>
              </div>
            </div>
          `;
        }).join('');
      }
    }
  }

  // Export Leaderboard CSV Audit
  function exportLeaderboardCsv() {
    let csv = 'Rank,Employee ID,Employee Name,Role,Deals Won,Total Sales (PKR),10k Target Status,Base Salary (PKR),Commissions (PKR),Total Take-Home Pay (PKR)\n';
    const closersMap = new Map();

    defaultAgents.forEach(a => { closersMap.set(a.id, { id: a.id, name: a.name, role: normalizeRole(a.role) }); });
    state.employees.forEach(e => { closersMap.set(e.id, { id: e.id, name: e.name, role: normalizeRole(e.designation || e.role) }); });

    const ranked = Array.from(closersMap.values()).map(c => {
      const perf = calculateCloserPerformance(c.id);
      return {
        id: c.id,
        name: c.name,
        role: c.role,
        dealsWon: perf.paidLeads.length,
        revenue: perf.totalSales,
        status: perf.isTargetAchieved ? 'Target Met (Unlocked)' : 'Target In Progress',
        baseSalary: perf.baseSalary,
        commissions: perf.commissionsEarned,
        totalPayout: perf.totalPayout
      };
    }).sort((a, b) => b.totalPayout - a.totalPayout);

    ranked.forEach((c, idx) => {
      csv += `${idx + 1},"${c.id}","${c.name}","${c.role}",${c.dealsWon},${c.revenue},"${c.status}",${c.baseSalary},${c.commissions},${c.totalPayout}\n`;
    });

    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.setAttribute('href', url);
    link.setAttribute('download', `Nunno_Payroll_Target_Commission_${new Date().toISOString().slice(0, 10)}.csv`);
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    showToast('📊 Exported payroll and task commission audit CSV!');
  }

  // ====================================================
  // WHATSAPP VOICE NOTES & AUDIO ENGINE (PTT & RECEIVE)
  // ====================================================

  function formatSeconds(totalSec) {
    const s = Math.round(Number(totalSec) || 0);
    const mins = Math.floor(s / 60);
    const secs = s % 60;
    return `${mins}:${secs < 10 ? '0' : ''}${secs}`;
  }

  function parseDurationToSeconds(durStr) {
    if (!durStr) return 20;
    const parts = durStr.split(':').map(Number);
    if (parts.length === 2 && !isNaN(parts[0]) && !isNaN(parts[1])) {
      return (parts[0] * 60) + parts[1];
    }
    return Number(durStr) || 20;
  }

  function generateWaveformBarsHtml(durSec) {
    const heights = [35, 60, 40, 80, 100, 50, 70, 90, 45, 65, 85, 30, 55, 95, 75, 40, 60, 85, 100, 70, 50, 35, 65, 80, 45, 60, 30, 50];
    return heights.map((h, i) => `<span class="vn-bar" data-bar-idx="${i}" style="height:${h}%;"></span>`).join('');
  }

  // Active Playback State
  const activeAudio = {
    msgId: null,
    audioElement: null,
    synthInterval: null,
    audioContext: null,
    oscillator: null,
    gainNode: null,
    currentRate: 1, // 1x, 1.5x, 2x
    currentTime: 0,
    duration: 0
  };

  function stopCurrentAudio() {
    if (activeAudio.audioElement) {
      try {
        activeAudio.audioElement.pause();
        activeAudio.audioElement.currentTime = 0;
      } catch (e) {}
      activeAudio.audioElement = null;
    }
    if (activeAudio.synthInterval) {
      clearInterval(activeAudio.synthInterval);
      activeAudio.synthInterval = null;
    }
    if (activeAudio.oscillator) {
      try { activeAudio.oscillator.stop(); } catch (e) {}
      activeAudio.oscillator = null;
    }
    if (activeAudio.gainNode) {
      try { activeAudio.gainNode.disconnect(); } catch (e) {}
      activeAudio.gainNode = null;
    }

    if (activeAudio.msgId) {
      updateVoicePlayerUI(activeAudio.msgId, false, 0, activeAudio.duration);
    }
    activeAudio.msgId = null;
    activeAudio.currentTime = 0;
  }

  async function playVoiceNote(msgId) {
    const lead = getLead(state.activeLeadId);
    if (!lead) return;
    const msg = lead.messages.find(m => m.id === msgId);
    if (!msg) return;
    if (!window.NUNNO_OFFLINE_PREVIEW && !msg.audioUrl) {
      try { await liveInbox.loadMedia(msg); } catch (error) { showToast(error.message); return; }
      if (!msg.audioUrl) { showToast('Audio is unavailable; this message has no real attachment.'); return; }
    }

    if (activeAudio.msgId === msgId) {
      // Toggle pause if already playing this message
      stopCurrentAudio();
      return;
    }

    // Stop whatever else might be playing
    stopCurrentAudio();

    activeAudio.msgId = msgId;
    const durSec = msg.durationSeconds || parseDurationToSeconds(msg.duration || '0:20');
    activeAudio.duration = durSec;
    activeAudio.currentTime = 0;

    updateVoicePlayerUI(msgId, true, 0, durSec);

    // If message contains a real playable audio data URL or blob
    if (msg.audioUrl) {
      try {
        const audio = new Audio(msg.audioUrl);
        audio.playbackRate = activeAudio.currentRate || 1;
        activeAudio.audioElement = audio;

        audio.addEventListener('timeupdate', () => {
          if (activeAudio.msgId !== msgId) return;
          activeAudio.currentTime = audio.currentTime;
          updateVoicePlayerUI(msgId, true, audio.currentTime, audio.duration || durSec);
        });

        audio.addEventListener('ended', () => {
          stopCurrentAudio();
        });

        audio.addEventListener('error', () => {
          playSyntheticVoice(msgId, durSec);
        });

        audio.play().catch(err => {
          playSyntheticVoice(msgId, durSec);
        });
      } catch (e) {
        playSyntheticVoice(msgId, durSec);
      }
    } else {
      // Pleasant audible speech-cadence synthesizer for demo & simulated voice notes
      playSyntheticVoice(msgId, durSec);
    }
  }

  function playSyntheticVoice(msgId, durationSec) {
    if (!window.NUNNO_OFFLINE_PREVIEW) { stopCurrentAudio(); showToast('Audio could not be played.'); return; }
    try {
      const AudioCtx = window.AudioContext || window.webkitAudioContext;
      if (!AudioCtx) {
        runTimerOnlyPlayback(msgId, durationSec);
        return;
      }
      if (!activeAudio.audioContext) {
        activeAudio.audioContext = new AudioCtx();
      }
      if (activeAudio.audioContext.state === 'suspended') {
        activeAudio.audioContext.resume();
      }

      const ctx = activeAudio.audioContext;
      const osc = ctx.createOscillator();
      const gain = ctx.createGain();
      const filter = ctx.createBiquadFilter();

      osc.type = 'triangle';
      osc.frequency.setValueAtTime(230, ctx.currentTime);
      filter.type = 'lowpass';
      filter.frequency.setValueAtTime(850, ctx.currentTime);
      gain.gain.setValueAtTime(0.08, ctx.currentTime);

      osc.connect(filter);
      filter.connect(gain);
      gain.connect(ctx.destination);

      osc.start();
      activeAudio.oscillator = osc;
      activeAudio.gainNode = gain;

      let step = 0;
      const intervalMs = 100;
      activeAudio.synthInterval = setInterval(() => {
        if (activeAudio.msgId !== msgId) {
          clearInterval(activeAudio.synthInterval);
          return;
        }
        activeAudio.currentTime += (intervalMs / 1000) * (activeAudio.currentRate || 1);

        step++;
        if (osc.frequency) {
          const modFreq = 220 + Math.sin(step * 0.4) * 40 + Math.cos(step * 0.2) * 15;
          osc.frequency.setValueAtTime(modFreq, ctx.currentTime);
        }

        updateVoicePlayerUI(msgId, true, activeAudio.currentTime, durationSec);

        if (activeAudio.currentTime >= durationSec) {
          stopCurrentAudio();
        }
      }, intervalMs);
    } catch (err) {
      runTimerOnlyPlayback(msgId, durationSec);
    }
  }

  function runTimerOnlyPlayback(msgId, durationSec) {
    const intervalMs = 100;
    activeAudio.synthInterval = setInterval(() => {
      if (activeAudio.msgId !== msgId) {
        clearInterval(activeAudio.synthInterval);
        return;
      }
      activeAudio.currentTime += (intervalMs / 1000) * (activeAudio.currentRate || 1);
      updateVoicePlayerUI(msgId, true, activeAudio.currentTime, durationSec);
      if (activeAudio.currentTime >= durationSec) {
        stopCurrentAudio();
      }
    }, intervalMs);
  }

  function updateVoicePlayerUI(msgId, isPlaying, currentSec, totalSec) {
    const player = document.getElementById(`vp_${msgId}`);
    if (!player) return;

    const playIcon = player.querySelector('.vn-icon-play');
    const pauseIcon = player.querySelector('.vn-icon-pause');
    const timer = player.querySelector('.vn-timer');
    const barsContainer = player.querySelector('.vn-waveform-bars');

    if (playIcon && pauseIcon) {
      playIcon.style.display = isPlaying ? 'none' : 'block';
      pauseIcon.style.display = isPlaying ? 'block' : 'none';
    }

    if (timer) {
      if (isPlaying && currentSec > 0) {
        timer.textContent = `${formatSeconds(currentSec)} / ${formatSeconds(totalSec)}`;
      } else {
        timer.textContent = formatSeconds(totalSec);
      }
    }

    if (barsContainer) {
      const bars = barsContainer.querySelectorAll('.vn-bar');
      const ratio = totalSec > 0 ? Math.min(1, Math.max(0, currentSec / totalSec)) : 0;
      const playedCount = Math.round(ratio * bars.length);
      bars.forEach((b, i) => {
        b.classList.toggle('played', i <= playedCount);
      });
    }
  }

  function cyclePlaybackSpeed(msgId) {
    const player = document.getElementById(`vp_${msgId}`);
    if (!player) return;
    const speedBtn = player.querySelector('.vn-speed-btn');

    const rates = [1, 1.5, 2];
    let nextIdx = (rates.indexOf(activeAudio.currentRate) + 1) % rates.length;
    activeAudio.currentRate = rates[nextIdx];

    if (speedBtn) {
      speedBtn.textContent = `${activeAudio.currentRate}x`;
    }

    if (activeAudio.audioElement) {
      activeAudio.audioElement.playbackRate = activeAudio.currentRate;
    }
  }

  function seekAudio(msgId, clickEvent) {
    const track = document.querySelector(`[data-track-id="${msgId}"]`);
    if (!track) return;
    const rect = track.getBoundingClientRect();
    const clickX = clickEvent.clientX - rect.left;
    const ratio = Math.max(0, Math.min(1, clickX / rect.width));

    const lead = getLead(state.activeLeadId);
    if (!lead) return;
    const msg = lead.messages.find(m => m.id === msgId);
    if (!msg) return;
    const totalSec = msg.durationSeconds || parseDurationToSeconds(msg.duration || '0:20');
    const targetTime = ratio * totalSec;

    if (activeAudio.msgId === msgId) {
      activeAudio.currentTime = targetTime;
      if (activeAudio.audioElement) {
        activeAudio.audioElement.currentTime = targetTime;
      }
      updateVoicePlayerUI(msgId, true, targetTime, totalSec);
    } else {
      playVoiceNote(msgId);
      setTimeout(() => {
        if (activeAudio.msgId === msgId) {
          activeAudio.currentTime = targetTime;
          if (activeAudio.audioElement) {
            activeAudio.audioElement.currentTime = targetTime;
          }
          updateVoicePlayerUI(msgId, true, targetTime, totalSec);
        }
      }, 60);
    }
  }

  // Microphone Recording State
  let mediaRecorder = null;
  let audioChunks = [];
  let recordTimer = null;
  let recordingSeconds = 0;
  let recordingLeadId = null;
  let mediaStream = null;

  async function startRecordingAudio() {
    const lead = getLead(state.activeLeadId);
    if (!lead) {
      showToast('⚠️ Please select a lead before recording audio.');
      return;
    }

    const myId = state.currentUser ? state.currentUser.id : state.currentAgent.id;
    const isAdmin = state.currentUser && state.currentUser.role === 'admin';
    if (lead.ownerId && lead.ownerId !== myId && !isAdmin) {
      showToast('⚠️ You cannot send voice notes to a lead owned by another agent!');
      return;
    }

    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
      fallbackSimulatedOrUploadAudio();
      return;
    }

    try {
      mediaStream = await navigator.mediaDevices.getUserMedia({ audio: true });
      recordingLeadId = lead.id;
      audioChunks = [];
      recordingSeconds = 0;

      const mimeTypes = ['audio/ogg;codecs=opus', 'audio/ogg', 'audio/webm;codecs=opus', 'audio/webm', 'audio/mp4'];
      let selectedMime = '';
      for (const m of mimeTypes) {
        if (MediaRecorder.isTypeSupported(m)) {
          selectedMime = m;
          break;
        }
      }

      mediaRecorder = selectedMime ? new MediaRecorder(mediaStream, { mimeType: selectedMime }) : new MediaRecorder(mediaStream);

      mediaRecorder.ondataavailable = (e) => {
        if (e.data && e.data.size > 0) {
          audioChunks.push(e.data);
        }
      };

      mediaRecorder.start(200);

      if (els.composerNormalView) els.composerNormalView.style.display = 'none';
      if (els.composerRecordingView) els.composerRecordingView.style.display = 'flex';
      if (els.voiceRecordBtn) els.voiceRecordBtn.classList.add('recording');
      if (els.recordingTimer) els.recordingTimer.textContent = '0:00';

      recordTimer = setInterval(() => {
        recordingSeconds++;
        if (els.recordingTimer) {
          els.recordingTimer.textContent = formatSeconds(recordingSeconds);
        }
        if (recordingSeconds >= 120) {
          sendRecordingAudio();
        }
      }, 1000);

    } catch (err) {
      console.warn('Microphone getUserMedia failed:', err);
      fallbackSimulatedOrUploadAudio();
    }
  }

  function cancelRecordingAudio() {
    if (recordTimer) {
      clearInterval(recordTimer);
      recordTimer = null;
    }
    if (mediaRecorder && mediaRecorder.state !== 'inactive') {
      mediaRecorder.stop();
    }
    if (mediaStream) {
      mediaStream.getTracks().forEach(t => t.stop());
      mediaStream = null;
    }
    audioChunks = [];
    recordingSeconds = 0;

    if (els.composerRecordingView) els.composerRecordingView.style.display = 'none';
    if (els.composerNormalView) els.composerNormalView.style.display = 'flex';
    if (els.voiceRecordBtn) els.voiceRecordBtn.classList.remove('recording');
    showToast('🗑️ Voice note discarded.');
  }

  function sendRecordingAudio() {
    if (!mediaRecorder || mediaRecorder.state === 'inactive') {
      cancelRecordingAudio();
      return;
    }

    const durationSec = Math.max(1, recordingSeconds);
    const targetLeadId = recordingLeadId;
    const durationStr = formatSeconds(durationSec);

    if (recordTimer) {
      clearInterval(recordTimer);
      recordTimer = null;
    }

    mediaRecorder.onstop = () => {
      const mime = mediaRecorder.mimeType || 'audio/webm';
      const audioBlob = new Blob(audioChunks, { type: mime });
      if (mediaStream) {
        mediaStream.getTracks().forEach(t => t.stop());
        mediaStream = null;
      }

      const reader = new FileReader();
      reader.onloadend = () => {
        const audioDataUrl = reader.result;
        dispatchOutgoingAudioMessage(audioDataUrl, durationStr, durationSec, targetLeadId);
      };
      reader.readAsDataURL(audioBlob);

      if (els.composerRecordingView) els.composerRecordingView.style.display = 'none';
      if (els.composerNormalView) els.composerNormalView.style.display = 'flex';
      if (els.voiceRecordBtn) els.voiceRecordBtn.classList.remove('recording');
    };

    mediaRecorder.stop();
  }

  function dispatchOutgoingAudioMessage(audioUrl, durationStr, durationSeconds, leadId) {
    if (!window.NUNNO_OFFLINE_PREVIEW) {
      return liveInbox.sendMedia(audioUrl, { voice: true, duration: durationSeconds, filename: 'voice-note.webm', leadId });
    }
    const lead = getLead(state.activeLeadId);
    if (!lead) return;

    const myId = state.currentUser ? state.currentUser.id : state.currentAgent.id;
    const myName = state.currentUser ? state.currentUser.name : state.currentAgent.name;

    if (!lead.ownerId) {
      lead.ownerId = myId;
      lead.ownerName = myName;
      lead.leadState = 'ongoing';
      showToast(`🎯 You responded with audio! Lead locked to you.`);
    }

    const now = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    const msgId = 'msg_' + Date.now();

    const newMsg = {
      id: msgId,
      sender: 'agent',
      agentName: myName,
      type: 'audio',
      audioUrl: audioUrl || null,
      duration: durationStr,
      durationSeconds: durationSeconds,
      text: `🎤 Voice note (${durationStr})`,
      time: now
    };

    lead.messages.push(newMsg);
    lead.status = 'replied';
    lead.lastActive = 'Just now';

    showToast(`🎙️ Voice note (${durationStr}) sent to ${lead.name}! 🚀`);
    renderConversationsList();
    renderActiveChat();
    syncLeadToServer(lead, { newMessage: newMsg });
  }

  function fallbackSimulatedOrUploadAudio() {
    if (!window.NUNNO_OFFLINE_PREVIEW) {
      showToast('Microphone unavailable. Choose an audio file to send instead.');
      els.audioFileInput?.click();
      return;
    }
    const confirmDemo = confirm(
      "Microphone access is not available or blocked in this browser.\n\n" +
      "• Click OK to send a simulated Voice Note ('0:18' Nunno Trading Breakdown)\n" +
      "• Click Cancel to select an audio file (.mp3, .wav, .m4a) from your computer"
    );
    if (confirmDemo) {
      dispatchOutgoingAudioMessage(null, '0:18', 18);
    } else if (els.audioFileInput) {
      els.audioFileInput.click();
    }
  }

  // Simulate Incoming Customer Audio Note
  function simulateClientAudioMessage(leadId) {
    const lead = getLead(leadId || state.activeLeadId);
    if (!lead) {
      showToast('⚠️ Please select a conversation first.');
      return;
    }

    const sampleAudios = [
      { dur: '0:24', sec: 24, text: '🎙️ Voice note (0:24): EasyPaisa account details chahiye bhai' },
      { dur: '0:18', sec: 18, text: '🎙️ Voice note (0:18): Signal accuracy aur live proof bhej dein' },
      { dur: '0:32', sec: 32, text: '🎙️ Voice note (0:32): 30% discount coupon code active hai?' },
      { dur: '0:45', sec: 45, text: '🎙️ Voice note (0:45): Whale package ke yearly terms discuss karne hain' }
    ];
    const picked = sampleAudios[Math.floor(Math.random() * sampleAudios.length)];
    const now = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    const msgId = 'msg_' + Date.now();

    const incomingMsg = {
      id: msgId,
      sender: 'lead',
      type: 'audio',
      duration: picked.dur,
      durationSeconds: picked.sec,
      text: picked.text,
      time: now
    };

    lead.messages.push(incomingMsg);
    lead.status = 'unread';
    lead.unreadCount = (lead.unreadCount || 0) + 1;
    lead.lastActive = 'Just now';

    playIncomingNotificationSound();

    showToast(`🎙️ Received new Voice Note from ${lead.name} (${picked.dur})!`);
    renderConversationsList();
    renderActiveChat();
    syncLeadToServer(lead, { newMessage: incomingMsg, unreadCount: lead.unreadCount });
  }

  function playIncomingNotificationSound() {
    try {
      const AudioCtx = window.AudioContext || window.webkitAudioContext;
      if (!AudioCtx) return;
      const ctx = new AudioCtx();
      const osc = ctx.createOscillator();
      const gain = ctx.createGain();
      osc.type = 'sine';
      osc.frequency.setValueAtTime(880, ctx.currentTime);
      osc.frequency.exponentialRampToValueAtTime(1320, ctx.currentTime + 0.12);
      gain.gain.setValueAtTime(0.12, ctx.currentTime);
      gain.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.25);
      osc.connect(gain);
      gain.connect(ctx.destination);
      osc.start();
      osc.stop(ctx.currentTime + 0.26);
    } catch (e) {}
  }

  const liveInbox = window.NunnoLiveInbox({ state, getLead, renderList: renderConversationsList, renderChat: renderActiveChat, toast: showToast, stopAudio: stopCurrentAudio });
  if (!window.NUNNO_OFFLINE_PREVIEW && els.simClientAudioBtn) { els.simClientAudioBtn.hidden = true; els.simClientAudioBtn.style.display = 'none'; }
  const photoInput = document.createElement('input');
  photoInput.type = 'file'; photoInput.accept = 'image/*'; photoInput.hidden = true;
  document.body.appendChild(photoInput);
  const photoButton = document.createElement('button');
  photoButton.type = 'button'; photoButton.className = 'btn-composer-icon'; photoButton.textContent = '📷'; photoButton.title = 'Send an image';
  photoButton.setAttribute('aria-label', 'Send an image');
  els.audioUploadBtn?.after(photoButton);
  photoButton.addEventListener('click', () => photoInput.click());
  photoInput.addEventListener('change', async () => {
    const file = photoInput.files?.[0]; photoInput.value = '';
    if (!file) return;
    if (file.size > 10*1024*1024) { showToast('Choose an image up to 10 MB.'); return; }
    const leadId = state.activeLeadId;
    const caption = (els.chatInput?.value || '').trim();
    if (els.chatInput) els.chatInput.value = '';
    const reader = new FileReader();
    reader.onload = () => liveInbox.sendMedia(reader.result, { filename: file.name, caption, leadId });
    reader.readAsDataURL(file);
  });

  // Audio Recording & Upload Event Listeners
  if (els.voiceRecordBtn) els.voiceRecordBtn.addEventListener('click', startRecordingAudio);
  if (els.cancelRecordBtn) els.cancelRecordBtn.addEventListener('click', cancelRecordingAudio);
  if (els.sendRecordBtn) els.sendRecordBtn.addEventListener('click', sendRecordingAudio);
  if (els.simClientAudioBtn) els.simClientAudioBtn.addEventListener('click', () => simulateClientAudioMessage());

  const composerFileInput = document.getElementById('composerFileInput') || els.audioFileInput;
  if (els.audioUploadBtn && composerFileInput) {
    els.audioUploadBtn.addEventListener('click', () => {
      const lead = getLead(state.activeLeadId);
      if (!lead) {
        showToast('⚠️ Please select a conversation first.');
        return;
      }
      composerFileInput.click();
    });

    composerFileInput.addEventListener('change', (e) => {
      const file = e.target.files && e.target.files[0];
      if (!file) return;

      if (file.size > 10 * 1024 * 1024) {
        showToast('⚠️ Attachment exceeds maximum 10 MB limit.');
        return;
      }

      const leadId = state.activeLeadId;
      const caption = (els.chatInput?.value || '').trim();
      if (els.chatInput) els.chatInput.value = '';

      const reader = new FileReader();
      reader.onload = (readEvt) => {
        const dataUrl = readEvt.target.result;
        if (file.type && file.type.startsWith('audio/')) {
          const tempAudio = new Audio(dataUrl);
          tempAudio.onloadedmetadata = () => {
            const sec = Math.round(tempAudio.duration) || 20;
            dispatchOutgoingAudioMessage(dataUrl, formatSeconds(sec), sec, leadId);
          };
          tempAudio.onerror = () => {
            dispatchOutgoingAudioMessage(dataUrl, '0:25', 25, leadId);
          };
        } else {
          // Documents (PDF, Word, Excel, text) or Images
          liveInbox.sendMedia(dataUrl, { filename: file.name, caption, leadId });
        }
      };
      reader.readAsDataURL(file);
      composerFileInput.value = '';
    });
  }

  const newChatBtn = document.getElementById('newChatBtn');
  if (newChatBtn) {
    newChatBtn.addEventListener('click', async () => {
      const phoneInput = prompt('Enter WhatsApp phone number to chat with:\n(e.g. 03318036576 or +923318036576)');
      if (!phoneInput) return;
      const clean = phoneInput.replace(/\D/g, '');
      if (clean.length < 10) {
        showToast('⚠️ Please enter a valid phone number (at least 10 digits).');
        return;
      }
      const nameInput = prompt('Enter contact name:', 'Client ' + clean.slice(-4)) || ('Contact ' + clean.slice(-4));
      try {
        const res = await fetch('api/leads_sync.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ action: 'add_lead', phone: clean, name: nameInput })
        });
        const data = await res.json();
        if (data.lead) {
          const exists = state.leads.find(l => l.id === data.lead.id || l.phone === data.lead.phone);
          if (!exists) {
            state.leads.unshift(data.lead);
          }
          state.activeLeadId = data.lead.id;
          renderConversationsList();
          renderActiveChat();
          showToast(`💬 Chat opened with ${data.lead.name} (${data.lead.display || data.lead.phone})!`);
        } else {
          showToast(data.message || 'Could not create new chat.');
        }
      } catch (err) {
        showToast('Error creating chat: ' + err.message);
      }
    });
  }

  const editLeadNameBtn = document.getElementById('editLeadNameBtn');
  if (editLeadNameBtn) {
    editLeadNameBtn.addEventListener('click', async () => {
      const lead = getLead(state.activeLeadId);
      if (!lead) return;
      const currentName = (lead.name === lead.phone || lead.name === lead.display) ? '' : lead.name;
      const newName = prompt(`Set custom name for ${lead.display || lead.phone}:`, currentName);
      if (newName === null) return;
      const trimmed = newName.trim();
      lead.name = trimmed || lead.phone;
      renderActiveChat();
      renderConversationsList();
      try {
        await fetch('api/leads_sync.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ action: 'update_lead', id: lead.id, name: lead.name })
        });
        showToast(`✅ Contact name updated to "${lead.name}"`);
      } catch (_) {}
    });
  }

  const editLeadPhoneBtn = document.getElementById('editLeadPhoneBtn');
  if (editLeadPhoneBtn) {
    editLeadPhoneBtn.addEventListener('click', async () => {
      const lead = getLead(state.activeLeadId);
      if (!lead) return;
      const newPhone = prompt(`Change phone number for "${lead.name}":`, lead.phone || '');
      if (!newPhone) return;
      const clean = newPhone.replace(/\D/g, '');
      if (clean.length < 10) {
        showToast('⚠️ Please enter a valid phone number (at least 10 digits).');
        return;
      }
      lead.phone = clean;
      lead.display = '+' + clean;
      renderActiveChat();
      renderConversationsList();
      try {
        await fetch('api/leads_sync.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ action: 'update_lead', id: lead.id, phone: clean })
        });
        showToast(`✅ Number updated to +${clean}! Messages will now deliver here.`);
      } catch (_) {}
    });
  }

  // Performance Score Modal Event Listeners
  if (els.openScoreBtn) els.openScoreBtn.addEventListener('click', () => openScoreModal('my'));
  if (els.closeScoreModalBtn) els.closeScoreModalBtn.addEventListener('click', closeScoreModal);
  if (els.tabMyScore) els.tabMyScore.addEventListener('click', () => renderScoreData('my'));
  if (els.tabVerifySales) els.tabVerifySales.addEventListener('click', () => renderScoreData('verify'));

  // Expose global functions for direct inline click handlers
  window.verifyPaymentDeal = verifyPaymentDeal;
  window.rejectFakeDeal = rejectFakeDeal;
  window.selectLead = selectLead;
  window.closeScoreModal = closeScoreModal;
  window.playVoiceNote = playVoiceNote;
  window.cyclePlaybackSpeed = cyclePlaybackSpeed;
  window.seekAudio = seekAudio;
  window.simulateClientAudioMessage = simulateClientAudioMessage;

  // Mark Paid Modal Event Listeners
  if (els.closeMarkPaidModalBtn) els.closeMarkPaidModalBtn.addEventListener('click', closeMarkPaidModal);
  if (els.cancelMarkPaidBtn) els.cancelMarkPaidBtn.addEventListener('click', closeMarkPaidModal);
  if (els.markPaidForm) els.markPaidForm.addEventListener('submit', handleMarkPaidSubmit);
  if (els.confirmMarkPaidBtn) els.confirmMarkPaidBtn.addEventListener('click', handleMarkPaidSubmit);

  // Can't Handle Modal Event Listeners
  if (els.closeCantHandleModalBtn) els.closeCantHandleModalBtn.addEventListener('click', closeCantHandleModal);
  if (els.cancelCantHandleBtn) els.cancelCantHandleBtn.addEventListener('click', closeCantHandleModal);
  if (els.cantHandleForm) els.cantHandleForm.addEventListener('submit', handleCantHandleSubmit);
  if (els.confirmCantHandleBtn) els.confirmCantHandleBtn.addEventListener('click', handleCantHandleSubmit);

  // Initialize App
  checkAuthSession();
  renderAgentProfile();
  renderConnectedNumber();
  refreshSenderConnection().catch(() => {});
  setInterval(() => {
    if (state.currentUser && !document.hidden && !els.connectWaModal?.classList.contains('open')) {
      refreshSenderConnection().catch(() => {
        renderSenderConnection({ is_configured: false, message: 'Cannot reach the WhatsApp service.' });
      });
    }
  }, 10000);
  renderConversationsList();
  renderActiveChat();
  syncDossierUiState();
  fetchServerLeads();
  updateCampaignPreview();
  renderCampaignAudienceUI();
});
