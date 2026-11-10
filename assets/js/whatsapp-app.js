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

  // Display name helper: default strictly to phone number, never default to Trader or Alishba
  function getContactDisplayName(lead) {
    if (!lead) return '';
    const rawName = (lead.name || '').trim();
    const lower = rawName.toLowerCase();
    if (!rawName || lower === 'trader' || lower === 'alishba' || lower === 'new contact' || lower === 'unknown' || lower === 'lead') {
      return lead.display || (lead.phone ? '+' + lead.phone : '');
    }
    return rawName;
  }

  // Privacy Phone Number Masking (Anti-Data Theft for Closer Employees)
  // Blurs/masks middle 6 digits with ****** for non-admin employees (e.g. +92334******89 or 0304******06)
  function maskPhoneNumber(phone, isAdmin = false) {
    if (isAdmin || !phone) return phone || '';
    const str = String(phone).trim();
    if (str.includes('******')) return str;
    const digits = str.replace(/\D/g, '');
    if (digits.length < 8) {
      if (str.length <= 4) return str;
      return str.slice(0, 2) + '******' + str.slice(-2);
    }
    // Pakistani international format (+923XX...)
    if (digits.startsWith('923') && digits.length >= 11) {
      const head = digits.slice(0, 5); // '92334'
      const tail = digits.slice(-2);   // '89'
      return (str.startsWith('+') ? '+' : '') + head + '******' + tail;
    }
    // Pakistani domestic format (03XX...)
    if (digits.startsWith('03') && digits.length >= 10) {
      const head = digits.slice(0, 4); // '0304'
      const tail = digits.slice(-2);   // '06'
      return head + '******' + tail;
    }
    // Generic international format
    const head = digits.slice(0, Math.max(3, digits.length - 8));
    const tail = digits.slice(-2);
    return (str.startsWith('+') ? '+' : '') + head + '******' + tail;
  }

  function formatContactName(name, isAdmin = false) {
    if (isAdmin || !name) return name || '';
    const str = String(name).trim();
    if (str.startsWith('+') || /^\+?[0-9\s-]+$/.test(str)) {
      return maskPhoneNumber(str, false);
    }
    return str;
  }

  function getContactAvatarInitials(lead) {
    const displayName = getContactDisplayName(lead);
    if (!displayName) return 'WA';
    if (displayName.startsWith('+')) {
      const digits = displayName.replace(/\D/g, '');
      return digits.slice(-2) || 'WA';
    }
    const parts = displayName.split(/\s+/).filter(Boolean);
    if (parts.length >= 2) {
      return (parts[0][0] + parts[1][0]).toUpperCase();
    }
    return displayName.slice(0, 2).toUpperCase();
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
    tabEmployeeLabel: document.getElementById('tabEmployeeLabel'),
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
    openTeamModalBtn: document.getElementById('openTeamModalBtn'),
    openEmailModalBtn: document.getElementById('openEmailModalBtn'),
    employeeModal: document.getElementById('employeeModal'),
    closeTeamModalBtn: document.getElementById('closeTeamModalBtn'),
    createEmpForm: document.getElementById('createEmpForm'),
    newEmpName: document.getElementById('newEmpName'),
    newEmpUsername: document.getElementById('newEmpUsername'),
    newEmpEmail: document.getElementById('newEmpEmail'),
    newEmpPassword: document.getElementById('newEmpPassword'),
    newEmpRole: document.getElementById('newEmpRole'),
    newEmpGender: document.getElementById('newEmpGender'),
    newEmpPhone: document.getElementById('newEmpPhone'),
    sendWelcomeEmailOnCreate: document.getElementById('sendWelcomeEmailOnCreate'),
    genEmpPassBtn: document.getElementById('genEmpPassBtn'),
    submitNewEmpBtn: document.getElementById('submitNewEmpBtn'),
    createEmpAlert: document.getElementById('createEmpAlert'),
    teamRosterList: document.getElementById('teamRosterList'),
    refreshTeamBtn: document.getElementById('refreshTeamBtn'),

    // Admin Email Studio Elements
    adminEmailModal: document.getElementById('adminEmailModal'),
    closeEmailModalBtn: document.getElementById('closeEmailModalBtn'),

    // Suspended Account Lockout Elements
    accountSuspendedOverlay: document.getElementById('accountSuspendedOverlay'),
    suspendedLogoutBtn: document.getElementById('suspendedLogoutBtn'),

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
    convTitleText: document.getElementById('convTitleText'),
    adminDeleteToggleBtn: document.getElementById('adminDeleteToggleBtn'),
    adminBatchBar: document.getElementById('adminBatchBar'),
    batchSelectAllCheckbox: document.getElementById('batchSelectAllCheckbox'),
    batchSelectedCount: document.getElementById('batchSelectedCount'),
    batchDeleteBtn: document.getElementById('batchDeleteBtn'),
    batchCancelBtn: document.getElementById('batchCancelBtn'),
    myOngoingsBtn: document.getElementById('myOngoingsBtn'),
    myOngoingCountBadge: document.getElementById('myOngoingCountBadge'),
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
    document.documentElement.classList.toggle('theme-dark', isDark);
    document.documentElement.classList.toggle('theme-light', !isDark);
    localStorage.setItem('nunno_theme', isDark ? 'dark' : 'light');
    const overlay = document.getElementById('loginAnimOverlay');
    if (overlay) {
      overlay.classList.toggle('theme-dark', isDark);
      overlay.classList.toggle('theme-light', !isDark);
    }
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

  let toastDismissTimer = null;
  function showToast(msg, duration = 3000) {
    msg = previewLabel(msg);
    if (!els.toast) return;
    els.toastMsg.textContent = msg;
    els.toast.classList.add('show');
    if (toastDismissTimer) clearTimeout(toastDismissTimer);
    toastDismissTimer = setTimeout(() => {
      els.toast.classList.remove('show');
      toastDismissTimer = null;
    }, duration);
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

  // Update Topbar Agent Display & Permissions
  function renderAgentProfile() {
    const user = state.currentUser;
    const isAdmin = user ? (user.role === 'admin' || user.role === 'master admin') : false;

    // Strict Role Restrictions: Hide Campaigns, Employees & Delete for Normal Users
    if (els.tabCampaign) els.tabCampaign.style.display = isAdmin ? 'inline-flex' : 'none';
    if (els.openTeamModalBtn) els.openTeamModalBtn.style.display = isAdmin ? 'inline-flex' : 'none';
    if (els.adminDeleteToggleBtn) els.adminDeleteToggleBtn.style.display = isAdmin ? 'inline-flex' : 'none';

    if (els.tabEmployeeLabel) {
      els.tabEmployeeLabel.textContent = isAdmin ? '📊 Team Stats' : '📊 My Stats';
    }

    if (user) {
      if (els.topAgentAvatar) els.topAgentAvatar.textContent = isAdmin ? '👑' : (user.gender === 'female' ? '👩' : '👨');
      if (els.topAgentName) els.topAgentName.textContent = user.name;
      if (els.topAgentId) {
        els.topAgentId.innerHTML = isAdmin 
          ? '<span class="badge-admin-crown">👑 Admin</span>' 
          : `<span class="badge-employee-pill">💼 ${escapeHtml(user.designation || 'Closer')}</span>`;
      }
      if (els.openScoreBtn) els.openScoreBtn.style.display = 'none';
      if (els.tabEmployee) els.tabEmployee.style.display = 'inline-flex';
    } else {
      if (els.openScoreBtn) els.openScoreBtn.style.display = 'none';
      if (els.topAgentAvatar) els.topAgentAvatar.textContent = state.currentAgent.avatar;
      if (els.topAgentName) els.topAgentName.textContent = state.currentAgent.name;
      if (els.topAgentId) els.topAgentId.textContent = `${state.currentAgent.id} • ${state.currentAgent.role}`;
      if (els.tabEmployee) els.tabEmployee.style.display = 'inline-flex';
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
    if (els.waConnectedBox && els.waConnectedBox.style) {
      els.waConnectedBox.style.display = state.senderConfigured ? 'block' : 'none';
    }
    if (els.waModalConnectedNum) {
      els.waModalConnectedNum.textContent = state.connectedWaNumber ? '+' + state.connectedWaNumber : (result.connected_number ? '+' + result.connected_number : 'Connected');
    }
    if (els.saveConnectedWaBtn) {
      els.saveConnectedWaBtn.disabled = ['starting', 'qr', 'authenticated', 'ready'].includes(result.state);
      els.saveConnectedWaBtn.textContent = state.senderConfigured ? 'Connected' : 'Connect WhatsApp';
      if (els.saveConnectedWaBtn.style) els.saveConnectedWaBtn.style.display = state.senderConfigured ? 'none' : 'inline-block';
    }
  }

  async function refreshSenderConnection() {
    let response = await fetch('api/direct_sender.php?action=get_sender_info', { cache: 'no-store' });
    let result = await response.json();
    if (!result.is_connected || result.state === 'offline') {
      try {
        await fetch('api/keep_alive.php', { cache: 'no-store' });
        response = await fetch('api/direct_sender.php?action=get_sender_info', { cache: 'no-store' });
        result = await response.json();
      } catch (_) {}
    }
    renderSenderConnection(result);
    if (!response.ok || !result.success) throw new Error(result.message || 'Could not check WhatsApp connection.');
    return state.senderConfigured;
  }

  async function pollWhatsAppConnection(generation) {
    if (generation !== waModalGeneration || !els.connectWaModal?.classList.contains('open')) return;
    try {
      await refreshSenderConnection();
      if (generation !== waModalGeneration) return;
      if (state.senderConfigured) {
        const shouldStart = pendingCampaignStart;
        closeConnectWaModal();
        if (shouldStart) {
          startCampaign();
        }
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
      if (typeof pollSenderQr === 'function') pollSenderQr();
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
          state.leads = data.leads.map(l => {
            const raw = (l.name || '').trim();
            const lower = raw.toLowerCase();
            if (!raw || lower === 'trader' || lower === 'alishba' || lower === 'new contact' || lower === 'unknown') {
              l.name = l.display || (l.phone ? '+' + l.phone : '');
            }
            return l;
          });
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
  // REAL-TIME WHATSAPP PRESENCE ENGINE
  // ==========================================

  function normalizePhone(phone) {
    if (!phone) return '';
    let clean = String(phone).replace(/\D/g, '');
    if (clean.startsWith('0092')) clean = clean.slice(2);
    else if (clean.startsWith('03') && clean.length === 11) clean = '92' + clean.slice(1);
    else if (clean.startsWith('3') && clean.length === 10) clean = '92' + clean;
    return clean;
  }

  const contactPresences = new Map();

  function getContactPresence(phone) {
    const norm = normalizePhone(phone);
    if (!norm) return null;
    const p = contactPresences.get(norm);
    if (!p) return null;
    if ((p.state === 'typing' || p.state === 'recording') && (Date.now() - p.timestamp > 12000)) {
      p.state = 'online';
      p.label = 'Active · WhatsApp Online';
    }
    return p;
  }

  function updateActiveChatPresenceUI() {
    const lead = getLead(state.activeLeadId);
    const presenceEl = document.getElementById('chatPresence');
    if (!lead) return;

    const p = getContactPresence(lead.phone);
    const stateType = p?.state || 'unknown';

    if (presenceEl) {
      if (stateType === 'typing') {
        presenceEl.style.display = 'inline-flex';
        presenceEl.className = 'chat-presence presence-typing';
        presenceEl.innerHTML = `
          <span class="wa-presence-typing">
            typing
            <span class="wa-bouncing-dots"><span></span><span></span><span></span></span>
          </span>
        `;
      } else if (stateType === 'recording') {
        presenceEl.style.display = 'inline-flex';
        presenceEl.className = 'chat-presence presence-recording';
        presenceEl.innerHTML = `
          <span class="wa-presence-recording">
            <span class="wa-mic-pulsing">
              <svg width="13" height="13" viewBox="0 0 24 24" fill="currentColor"><path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"/><path d="M19 10v2a7 7 0 0 1-14 0v-2" fill="none" stroke="currentColor" stroke-width="2.2"/></svg>
            </span>
            recording audio…
            <span class="wa-equalizer-bars"><span></span><span></span><span></span><span></span></span>
          </span>
        `;
      } else if (stateType === 'online') {
        presenceEl.style.display = 'inline-flex';
        presenceEl.className = 'chat-presence presence-online';
        presenceEl.innerHTML = `<span class="wa-presence-online">online</span>`;
      } else {
        presenceEl.style.display = 'none';
        presenceEl.textContent = '';
      }
    }

    // Floating incoming indicator bubble in the message thread
    if (els.chatMessagesArea) {
      let typingBubble = document.getElementById('incomingTypingBubble');
      let recordingBubble = document.getElementById('incomingRecordingBubble');

      if (stateType === 'typing') {
        if (recordingBubble) recordingBubble.remove();
        if (!typingBubble) {
          typingBubble = document.createElement('div');
          typingBubble.id = 'incomingTypingBubble';
          typingBubble.className = 'wa-incoming-bubble wa-typing-bubble';
          typingBubble.innerHTML = `
            <div class="wa-bubble-dots">
              <span></span><span></span><span></span>
            </div>
          `;
          els.chatMessagesArea.appendChild(typingBubble);
          els.chatMessagesArea.scrollTop = els.chatMessagesArea.scrollHeight;
        }
      } else if (stateType === 'recording') {
        if (typingBubble) typingBubble.remove();
        if (!recordingBubble) {
          recordingBubble = document.createElement('div');
          recordingBubble.id = 'incomingRecordingBubble';
          recordingBubble.className = 'wa-incoming-bubble wa-recording-bubble';
          recordingBubble.innerHTML = `
            <div class="wa-bubble-mic">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"/><path d="M19 10v2a7 7 0 0 1-14 0v-2" fill="none" stroke="currentColor" stroke-width="2.2"/></svg>
            </div>
            <div class="wa-equalizer-bars">
              <span></span><span></span><span></span><span></span>
            </div>
            <span class="wa-rec-bubble-label">Recording audio…</span>
          `;
          els.chatMessagesArea.appendChild(recordingBubble);
          els.chatMessagesArea.scrollTop = els.chatMessagesArea.scrollHeight;
        }
      } else {
        if (typingBubble) typingBubble.remove();
        if (recordingBubble) recordingBubble.remove();
      }
    }
  }

  function updateConversationsPresenceUI(targetNorm) {
    if (!els.conversationsScroll) return;
    const items = els.conversationsScroll.querySelectorAll('.conv-item');
    items.forEach(item => {
      const pNorm = item.dataset.leadPhone;
      if (targetNorm && pNorm !== targetNorm) return;
      const previewRow = item.querySelector('.conv-preview-row');
      if (!previewRow) return;

      const p = contactPresences.get(pNorm);
      const isExpired = p && (p.state === 'typing' || p.state === 'recording') && (Date.now() - p.timestamp > 12000);
      const stateType = isExpired ? 'online' : (p?.state || '');

      if (stateType === 'typing') {
        previewRow.innerHTML = `<span class="conv-preview-text is-typing"><span class="wa-typing-dots-sm"><span></span><span></span><span></span></span> typing…</span>`;
      } else if (stateType === 'recording') {
        previewRow.innerHTML = `<span class="conv-preview-text is-recording"><svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor"><path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"/><path d="M19 10v2a7 7 0 0 1-14 0v-2" fill="none" stroke="currentColor" stroke-width="2.2"/></svg> recording audio…</span>`;
      }
    });
  }

  window.onContactPresenceUpdate = function(phone, presence) {
    if (!phone || !presence) return;
    const norm = normalizePhone(phone);
    contactPresences.set(norm, {
      state: presence.state,
      label: presence.label,
      timestamp: presence.timestamp || Date.now()
    });
    updateActiveChatPresenceUI();
    updateConversationsPresenceUI(norm);
  };

  window.onWhatsAppPresencesUpdate = function(presences) {
    if (!presences || typeof presences !== 'object') return;
    let activePhoneAffected = false;
    const activeLead = getLead(state.activeLeadId);
    const activeNorm = activeLead ? normalizePhone(activeLead.phone) : '';

    for (const [phone, p] of Object.entries(presences)) {
      if (!p) continue;
      const norm = normalizePhone(phone);
      contactPresences.set(norm, {
        state: p.state,
        label: p.label,
        timestamp: p.timestamp || Date.now()
      });
      if (activeNorm && norm === activeNorm) activePhoneAffected = true;
      updateConversationsPresenceUI(norm);
    }

    if (activePhoneAffected) {
      updateActiveChatPresenceUI();
    }
  };

  let agentComposingTimer = null;
  let isAgentComposing = false;

  function sendOutboundPresence(type) {
    const lead = getLead(state.activeLeadId);
    if (!lead || !lead.phone) return;
    fetch('api/direct_sender.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        action: 'send_presence',
        phone: lead.phone,
        type: type
      })
    }).catch(() => {});
  }

  // ==========================================
  // INBOX & CONVERSATIONS LIST
  // ==========================================

  // Crisp WhatsApp Vector SVGs for Message Delivery Status
  function renderDeliveryStatusSvg(tickState) {
    if (tickState === 'read') {
      return `<span title="Read" style="display:inline-flex;align-items:center;margin-left:3px;vertical-align:middle;">
        <svg width="16" height="11" viewBox="0 0 16 11" fill="none" xmlns="http://www.w3.org/2000/svg">
          <path d="M11.05 0.7L4.9 6.85L2.25 4.2L0.8 5.65L4.9 9.75L12.5 2.15L11.05 0.7Z" fill="#53bdeb"/>
          <path d="M14.55 0.7L8.4 6.85L7.3 5.75L5.85 7.2L8.4 9.75L16 2.15L14.55 0.7Z" fill="#53bdeb"/>
        </svg>
      </span>`;
    }
    if (tickState === 'delivered') {
      return `<span title="Delivered" style="display:inline-flex;align-items:center;margin-left:3px;vertical-align:middle;">
        <svg width="16" height="11" viewBox="0 0 16 11" fill="none" xmlns="http://www.w3.org/2000/svg">
          <path d="M11.05 0.7L4.9 6.85L2.25 4.2L0.8 5.65L4.9 9.75L12.5 2.15L11.05 0.7Z" fill="#8696a0"/>
          <path d="M14.55 0.7L8.4 6.85L7.3 5.75L5.85 7.2L8.4 9.75L16 2.15L14.55 0.7Z" fill="#8696a0"/>
        </svg>
      </span>`;
    }
    if (tickState === 'sent') {
      return `<span title="Sent" style="display:inline-flex;align-items:center;margin-left:3px;vertical-align:middle;">
        <svg width="12" height="11" viewBox="0 0 12 11" fill="none" xmlns="http://www.w3.org/2000/svg">
          <path d="M10.55 0.7L4.4 6.85L1.75 4.2L0.3 5.65L4.4 9.75L12 2.15L10.55 0.7Z" fill="#8696a0"/>
        </svg>
      </span>`;
    }
    if (['sending', 'accepted'].includes(tickState)) {
      return `<span title="Sending" style="display:inline-flex;align-items:center;margin-left:3px;vertical-align:middle;">
        <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="#8696a0" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
      </span>`;
    }
    if (tickState === 'failed') {
      return `<span title="Sending failed" style="color:#ef4444;font-size:12px;font-weight:bold;margin-left:3px;">!</span>`;
    }
    return `<span title="Delivered" style="display:inline-flex;align-items:center;margin-left:3px;vertical-align:middle;">
      <svg width="16" height="11" viewBox="0 0 16 11" fill="none" xmlns="http://www.w3.org/2000/svg">
        <path d="M11.05 0.7L4.9 6.85L2.25 4.2L0.8 5.65L4.9 9.75L12.5 2.15L11.05 0.7Z" fill="#8696a0"/>
        <path d="M14.55 0.7L8.4 6.85L7.3 5.75L5.85 7.2L8.4 9.75L16 2.15L14.55 0.7Z" fill="#8696a0"/>
      </svg>
    </span>`;
  }

  // Admin Batch Deletion State & Handlers
  let adminDeleteMode = false;
  const selectedLeadIds = new Set();

  function updateBatchBarCount() {
    if (els.batchSelectedCount) {
      els.batchSelectedCount.textContent = `${selectedLeadIds.size} selected`;
    }
    if (els.batchDeleteBtn) {
      els.batchDeleteBtn.disabled = selectedLeadIds.size === 0;
      els.batchDeleteBtn.style.opacity = selectedLeadIds.size === 0 ? '0.5' : '1';
    }
  }

  function toggleAdminDeleteMode() {
    adminDeleteMode = !adminDeleteMode;
    if (els.adminDeleteToggleBtn) {
      els.adminDeleteToggleBtn.classList.toggle('active', adminDeleteMode);
      els.adminDeleteToggleBtn.innerHTML = adminDeleteMode ? '✕ Cancel' : '🗑️ Delete';
    }
    if (els.adminBatchBar) {
      els.adminBatchBar.style.display = adminDeleteMode ? 'flex' : 'none';
    }
    if (!adminDeleteMode) {
      selectedLeadIds.clear();
      if (els.batchSelectAllCheckbox) els.batchSelectAllCheckbox.checked = false;
    }
    updateBatchBarCount();
    renderConversationsList();
  }

  async function deleteLeads(ids) {
    if (!ids || !ids.length) return;
    try {
      const res = await fetch('api/leads_sync.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'delete_leads', ids })
      });
      const result = await res.json();
      if (result && result.success) {
        showToast(`🗑️ ${result.message || 'Chats permanently deleted from server.'}`);
      }
    } catch (err) {
      showToast(`🗑️ Permanently deleted ${ids.length} chat(s).`);
    }

    state.leads = state.leads.filter(l => !ids.includes(l.id));
    ids.forEach(id => selectedLeadIds.delete(id));
    if (ids.includes(state.activeLeadId)) {
      state.activeLeadId = state.leads[0]?.id || null;
    }
    if (!state.leads.length) {
      adminDeleteMode = false;
      if (els.adminBatchBar) els.adminBatchBar.style.display = 'none';
      if (els.adminDeleteToggleBtn) els.adminDeleteToggleBtn.classList.remove('active');
    }
    updateBatchBarCount();
    renderConversationsList();
    renderActiveChat();
    renderAgentProfile();
    renderEmployeeWorkspace();
  }

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
      if (state.inboxFilter === 'my_ongoing') {
        const myId = state.currentUser ? state.currentUser.id : state.currentAgent.id;
        const isOwner = l.ownerId === myId || (state.currentUser?.role === 'admin' && (l.ownerId === 'USR-ADMIN-01' || l.ownerId === 'local-admin' || l.ownerId === myId));
        return isOwner && lState === 'ongoing';
      }
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

    const countMyOngoing = state.leads.filter(l => {
      const lState = getLeadState(l);
      const isOwner = l.ownerId === currentUserId || (state.currentUser?.role === 'admin' && (l.ownerId === 'USR-ADMIN-01' || l.ownerId === 'local-admin' || l.ownerId === currentUserId));
      return isOwner && lState === 'ongoing';
    }).length;

    if (els.myOngoingCountBadge) els.myOngoingCountBadge.textContent = countMyOngoing;
    if (els.myOngoingsBtn) els.myOngoingsBtn.classList.toggle('active', state.inboxFilter === 'my_ongoing');
    if (els.convTitleText) els.convTitleText.textContent = state.inboxFilter === 'my_ongoing' ? 'My Ongoings' : 'All chats';

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
      const isAdmin = state.currentUser ? state.currentUser.role === 'admin' : false;
      const contactDisplayName = getContactDisplayName(lead);
      const displayTitle = formatContactName(contactDisplayName, isAdmin);
      const displayPhone = maskPhoneNumber(lead.display || ('+' + lead.phone), isAdmin);
      const initials = getContactAvatarInitials(lead);

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

      const presence = getContactPresence(lead.phone);
      let previewHtml = '';
      if (presence && presence.state === 'typing') {
        previewHtml = `<span class="conv-preview-text is-typing"><span class="wa-typing-dots-sm"><span></span><span></span><span></span></span> typing…</span>`;
      } else if (presence && presence.state === 'recording') {
        previewHtml = `<span class="conv-preview-text is-recording"><svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor"><path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"/><path d="M19 10v2a7 7 0 0 1-14 0v-2" fill="none" stroke="currentColor" stroke-width="2.2"/></svg> recording audio…</span>`;
      } else if (lastMsg.type === 'audio') {
        previewHtml = `<span class="conv-preview-text is-audio"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"/><path d="M19 10v2a7 7 0 0 1-14 0v-2"/></svg> Voice note (${lastMsg.duration || '0:20'})</span>`;
      } else {
        previewHtml = `<span class="conv-preview-text" title="${escapeHtml(cleanPreview)}">${escapeHtml(cleanPreview)}</span>`;
      }

      const item = document.createElement('div');
      item.className = `conv-item state-${leadState} ${isSelected ? 'active' : ''}`;
      item.dataset.leadPhone = normalizePhone(lead.phone);
      item.onclick = () => selectLead(lead.id);

      item.innerHTML = `
        <div class="conv-avatar-wrap">
          <span class="avatar-initials">${initials}</span>
          <div class="lead-status-dot status-${leadState}" title="${leadState === 'fresh' ? '🔴 Fresh (No one claimed)' : leadState === 'ongoing' ? '🟡 Ongoing (In Progress)' : leadState === 'pending_verification' ? '⚪ In Review (Awaiting Admin Verification)' : leadState === 'paid' ? '🟢 Closed (Client Paid)' : '🔵 Won\'t Pay'}"></div>
          ${lead.unreadCount > 0 ? `<div class="unread-badge">${lead.unreadCount}</div>` : ''}
        </div>
        <div class="conv-body">
          <div class="conv-top-row">
            <span class="conv-contact-name">${escapeHtml(displayTitle)}</span>
            <span class="conv-timestamp">${lastMsg.time || lead.lastActive}</span>
          </div>
          <div class="conv-preview-row">
            ${previewHtml}
          </div>
          <div class="conv-bottom-row">
            <span class="mono text-xs text-muted">${escapeHtml(displayPhone)}</span>
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
      presenceEl.textContent = '';
      presenceEl.style.display = 'none';
      presenceEl.className = 'chat-presence';
    }

    const leadState = getLeadState(lead);
    const owner = lead.ownerId ? getAgent(lead.ownerId) : null;
    const contactDisplayName = getContactDisplayName(lead);
    const initials = getContactAvatarInitials(lead);

    const currentUserId = state.currentUser ? state.currentUser.id : state.currentAgent.id;
    const currentRawRole = (state.currentUser && state.currentUser.designation) || (state.currentUser && state.currentUser.role) || state.currentAgent.role || 'Team Member';
    const currentRole = normalizeRole(currentRawRole);
    const isAdmin = currentRole === 'Admin' || (state.currentUser && state.currentUser.role === 'admin');

    const displayTitle = formatContactName(contactDisplayName, isAdmin);
    const displayPhone = maskPhoneNumber(lead.display || (lead.phone ? '+' + lead.phone : ''), isAdmin);

    // Header Info
    if (els.chatLeadAvatar) els.chatLeadAvatar.textContent = initials;
    if (els.chatLeadDot) els.chatLeadDot.className = `lead-status-dot status-${leadState}`;
    if (els.chatLeadName) els.chatLeadName.textContent = displayTitle;
    if (els.chatLeadPhone) {
      if (displayTitle === displayPhone || !lead.name || lead.name === lead.phone) {
        els.chatLeadPhone.textContent = '';
        els.chatLeadPhone.style.display = 'none';
      } else {
        els.chatLeadPhone.textContent = displayPhone;
        els.chatLeadPhone.style.display = 'inline-block';
      }
    }

    const editLeadPhoneBtn = document.getElementById('editLeadPhoneBtn');
    if (editLeadPhoneBtn) {
      editLeadPhoneBtn.style.display = 'none';
    }

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

    const isOwner = lead.ownerId === currentUserId;
    const isLockedOngoing = leadState === 'ongoing' && lead.ownerId && !isOwner;
    const closerDisplayName = owner ? owner.name : (lead.ownerName || lead.ownerId);

    if (els.chatLeadCloser) {
      if (leadState === 'fresh') {
        els.chatLeadCloser.textContent = '';
        els.chatLeadCloser.style.display = 'none';
      } else if (leadState === 'ongoing') {
        els.chatLeadCloser.style.display = 'inline';
        els.chatLeadCloser.style.color = '#fbbf24';
        els.chatLeadCloser.textContent = isOwner ? `🔒 You (${closerDisplayName})` : `🔒 ${closerDisplayName}`;
      } else if (leadState === 'pending_verification') {
        els.chatLeadCloser.style.display = 'inline';
        els.chatLeadCloser.style.color = '#cbd5e1';
        const displayCloser = lead.paidDetails?.closedBy || closerDisplayName || 'Closer';
        els.chatLeadCloser.textContent = `⚪ In Review (${displayCloser})`;
      } else if (leadState === 'paid') {
        els.chatLeadCloser.style.display = 'inline';
        els.chatLeadCloser.style.color = '#34d399';
        const displayCloser = lead.paidDetails?.closedBy || closerDisplayName || 'Closer';
        els.chatLeadCloser.textContent = `✓ Paid (${displayCloser})`;
      } else if (leadState === 'useless') {
        els.chatLeadCloser.style.display = 'inline';
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
        if (!isOwner && !isAdmin) {
          // Strict Ownership Gate: Agent must take and claim the lead first before they can mark it as paid!
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
        const initials = (closerDisplayName || 'CL').split(' ').filter(Boolean).map(n => n[0]).slice(0, 2).join('').toUpperCase();
        const avEl = document.getElementById('lockedCloserAvatar');
        if (avEl) avEl.textContent = initials;
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
      if (compBar) compBar.style.display = 'block';

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
        const deliveryMarkup = isInbound ? '' : renderDeliveryStatusSvg(tickState);

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
        });
      });
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

    updateActiveChatPresenceUI();

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
    els.chatInput.style.height = '38px';
    if (isAgentComposing) {
      isAgentComposing = false;
      clearTimeout(agentComposingTimer);
      sendOutboundPresence('paused');
    }
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
    els.chatInput.addEventListener('input', () => {
      els.chatInput.style.height = 'auto';
      els.chatInput.style.height = Math.min(Math.max(els.chatInput.scrollHeight, 38), 120) + 'px';
      const text = (els.chatInput.value || '').trim();
      if (text.length > 0) {
        if (!isAgentComposing) {
          isAgentComposing = true;
          sendOutboundPresence('composing');
        }
        clearTimeout(agentComposingTimer);
        agentComposingTimer = setTimeout(() => {
          if (isAgentComposing) {
            isAgentComposing = false;
            sendOutboundPresence('paused');
          }
        }, 3500);
      } else {
        if (isAgentComposing) {
          isAgentComposing = false;
          clearTimeout(agentComposingTimer);
          sendOutboundPresence('paused');
        }
      }
    });

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
      const cName = getContactDisplayName(contact);
      if (cName && !cName.startsWith('+')) return cName;
      if (fallback) {
        const fbParts = fallback.split('|');
        return fbParts[Math.floor(Math.random() * fbParts.length)].trim();
      }
      return cName || (contact && (contact.display || contact.phone)) || '';
    });

    const contactName = getContactDisplayName(contact) || (contact && (contact.display || contact.phone)) || '';
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
      const clean = cleanNumber(rawPhone);
      if (!clean) return;

      const formattedNumber = `+92 ${clean.substring(2, 5)} ${clean.substring(5)}`;
      const rawName = (parts[1] && parts[1].trim() && parts[1].trim().toLowerCase() !== 'trader') ? parts[1].trim() : formattedNumber;
      const rawCity = parts[2] ? parts[2].trim() : 'Pakistan';

      if (campaignState.audience.some(c => c.phone === clean)) return;

      campaignState.audience.push({
        id: `c_lead_${Date.now()}_${idx}_${Math.random().toString(36).substr(2, 4)}`,
        name: rawName,
        phone: clean,
        display: formattedNumber,
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

  let campaignPollTimer = null;

  async function syncServerCampaignStatus() {
    if (window.NUNNO_OFFLINE_PREVIEW) return;
    try {
      const response = await fetch('api/direct_sender.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'get_campaign_status' })
      });
      const data = await response.json();
      if (!response.ok || !data.success) return;
      applyServerCampaignState(data);
    } catch (_) {}
  }

  function startCampaignStatusPolling() {
    if (campaignPollTimer) clearInterval(campaignPollTimer);
    campaignPollTimer = setInterval(() => {
      syncServerCampaignStatus();
    }, 3500);
  }

  function applyServerCampaignState(data) {
    if (!data || !data.status) return;
    const isRunning = data.status === 'running';
    const isPaused = data.status === 'paused';
    const isCompleted = data.status === 'completed';
    const isStopped = data.status === 'stopped';

    campaignState.isRunning = isRunning;
    campaignState.isPaused = isPaused;
    campaignState.totalSent = data.totalSent || 0;
    campaignState.totalFailed = data.totalFailed || 0;
    campaignState.currentIndex = data.currentIndex || 0;
    campaignState.remainingSeconds = data.remainingSeconds || 0;

    if (data.name && els.campaignNameInput && !els.campaignNameInput.value) {
      els.campaignNameInput.value = data.name;
    }

    if (data.audience && data.audience.length > 0 && campaignState.audience.length === 0) {
      campaignState.audience = data.audience;
      renderCampaignAudienceUI();
    } else if (data.audience && data.audience.length > 0) {
      for (const sa of data.audience) {
        const local = campaignState.audience.find(c => c.phone === sa.phone);
        if (local) {
          local.status = sa.status;
          local.sentAt = sa.sentAt;
          local.error = sa.error;
          const statusEl = document.getElementById(`c_status_${local.id}`);
          if (statusEl) {
            if (sa.status === 'accepted' || sa.status === 'sent') {
              statusEl.innerHTML = '<span style="color:#00a884;font-weight:700;">✓ Accepted</span>';
            } else if (sa.status === 'sending') {
              statusEl.innerHTML = '<span style="color:#eab308;">⏳ Sending...</span>';
            } else if (sa.status === 'failed') {
              statusEl.innerHTML = '<span style="color:#ef4444;font-weight:700;">✕ Failed</span>';
            }
          }
        }
      }
    }

    const total = data.totalContacts || campaignState.audience.length;
    const currentNum = Math.min(campaignState.currentIndex + 1, total);

    if (isRunning || isPaused) {
      if (els.startCampaignBtn) els.startCampaignBtn.style.display = 'none';
      if (els.pauseCampaignBtn) {
        els.pauseCampaignBtn.style.display = 'inline-flex';
        els.pauseCampaignBtn.innerHTML = isPaused ? '<span>▶️ Resume</span>' : '<span>⏸️ Pause</span>';
      }
      if (els.stopCampaignBtn) els.stopCampaignBtn.style.display = 'inline-flex';
      if (els.campaignProgressCard) els.campaignProgressCard.style.display = 'block';

      if (els.campaignProgressCount) els.campaignProgressCount.textContent = `${campaignState.totalSent} / ${total}`;
      const pct = total > 0 ? Math.round((campaignState.totalSent / total) * 100) : 0;
      if (els.campaignProgressPct) els.campaignProgressPct.textContent = `${pct}%`;
      if (els.campaignProgressFill) els.campaignProgressFill.style.width = `${pct}%`;

      if (els.campaignCountdown) {
        els.campaignCountdown.textContent = isPaused ? 'Paused' : (campaignState.remainingSeconds > 0 ? `${campaignState.remainingSeconds}s` : 'Sending...');
      }

      if (els.campaignStatusBadge) {
        if (isPaused) {
          els.campaignStatusBadge.style.color = '#eab308';
          els.campaignStatusBadge.style.background = '#3b2f0a';
          els.campaignStatusBadge.style.borderColor = '#eab308';
          els.campaignStatusBadge.innerHTML = `🟡 Paused at Lead ${currentNum} of ${total}`;
        } else {
          els.campaignStatusBadge.style.color = '#00a884';
          els.campaignStatusBadge.style.background = '#0a332c';
          els.campaignStatusBadge.style.borderColor = '#00a884';
          els.campaignStatusBadge.innerHTML = `🟢 Server Sending (${campaignState.totalSent}/${total})`;
        }
      }
    } else if (isCompleted) {
      if (els.startCampaignBtn) els.startCampaignBtn.style.display = 'inline-flex';
      if (els.pauseCampaignBtn) els.pauseCampaignBtn.style.display = 'none';
      if (els.stopCampaignBtn) els.stopCampaignBtn.style.display = 'none';
      if (els.campaignCountdown) els.campaignCountdown.textContent = 'Complete';
      if (els.campaignProgressPct) els.campaignProgressPct.textContent = '100%';
      if (els.campaignProgressFill) els.campaignProgressFill.style.width = '100%';
      if (els.campaignStatusBadge) {
        els.campaignStatusBadge.style.color = '#00a884';
        els.campaignStatusBadge.style.background = '#0a332c';
        els.campaignStatusBadge.style.borderColor = '#00a884';
        els.campaignStatusBadge.innerHTML = `✅ Server Completed (${campaignState.totalSent} Sent)`;
      }
    } else if (isStopped) {
      if (els.startCampaignBtn) els.startCampaignBtn.style.display = 'inline-flex';
      if (els.pauseCampaignBtn) els.pauseCampaignBtn.style.display = 'none';
      if (els.stopCampaignBtn) els.stopCampaignBtn.style.display = 'none';
      if (els.campaignCountdown) els.campaignCountdown.textContent = 'Stopped';
      if (els.campaignStatusBadge) {
        els.campaignStatusBadge.style.color = '#ef4444';
        els.campaignStatusBadge.style.background = '#311417';
        els.campaignStatusBadge.style.borderColor = '#ef4444';
        els.campaignStatusBadge.innerHTML = `⏹️ Stopped (${campaignState.totalSent} Sent)`;
      }
    }

    if (data.logs && data.logs.length > 0 && els.campaignTerminalLog) {
      const logsCount = data.logs.length;
      if (logsCount !== campaignState.lastLogsCount) {
        campaignState.lastLogsCount = logsCount;
        els.campaignTerminalLog.innerHTML = data.logs.map(l =>
          `<div class="log-line log-${escapeHtml(l.type)}"><span style="color:#8696a0;font-size:10.5px;margin-right:4px;">[${escapeHtml(l.time)}]</span> ${escapeHtml(l.message)}</div>`
        ).join('');
        els.campaignTerminalLog.scrollTop = els.campaignTerminalLog.scrollHeight;
        if (els.campaignLogCount) els.campaignLogCount.textContent = `${logsCount} events`;
      }
    }
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

    const assignMode = els.campaignAssignSelect ? els.campaignAssignSelect.value : 'pool';

    if (window.NUNNO_OFFLINE_PREVIEW) {
      campaignState.isRunning = true;
      campaignState.isPaused = false;
      if (els.startCampaignBtn) els.startCampaignBtn.style.display = 'none';
      if (els.pauseCampaignBtn) els.pauseCampaignBtn.style.display = 'inline-flex';
      if (els.stopCampaignBtn) els.stopCampaignBtn.style.display = 'inline-flex';
      if (els.campaignProgressCard) els.campaignProgressCard.style.display = 'block';
      dispatchNextCampaignMessage();
      return;
    }

    logCampaignTerminal(`🚀 Launching server-side campaign <strong>"${escapeHtml(campaignName)}"</strong> with ${campaignState.audience.length} target contacts. Running 24/7 on server. You can safely close your browser!`, 'info');
    showToast('🚀 Campaign running 24/7 on server! You can safely close your browser.');

    try {
      const response = await fetch('api/direct_sender.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          action: 'start_campaign',
          name: campaignName,
          message: msg,
          audience: campaignState.audience,
          minDelay: minD,
          maxDelay: maxD,
          assignMode,
          closer: state.currentUser
        })
      });
      const data = await response.json();
      if (data && data.success) {
        applyServerCampaignState(data);
        startCampaignStatusPolling();
      } else {
        showToast('⚠️ Could not start campaign: ' + (data?.message || 'Server error'));
      }
    } catch (err) {
      showToast('⚠️ Could not start campaign: ' + err.message);
    }
  }

  async function pauseCampaign() {
    if (window.NUNNO_OFFLINE_PREVIEW) {
      if (!campaignState.isRunning) return;
      if (campaignState.isPaused) {
        campaignState.isPaused = false;
        dispatchNextCampaignMessage();
      } else {
        campaignState.isPaused = true;
      }
      return;
    }

    try {
      const action = campaignState.isPaused ? 'resume_campaign' : 'pause_campaign';
      const response = await fetch('api/direct_sender.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action })
      });
      const data = await response.json();
      if (data && data.success) {
        applyServerCampaignState(data);
      }
    } catch (err) {
      showToast('Action failed: ' + err.message);
    }
  }

  async function stopCampaign() {
    if (window.NUNNO_OFFLINE_PREVIEW) {
      campaignState.isRunning = false;
      campaignState.isPaused = false;
      return;
    }

    try {
      const response = await fetch('api/direct_sender.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'stop_campaign' })
      });
      const data = await response.json();
      if (data && data.success) {
        applyServerCampaignState(data);
      }
      showToast('⏹️ Campaign stopped on server.');
    } catch (err) {
      showToast('Stop failed: ' + err.message);
    }
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

  // Pipeline Multi-Select & Delete State
  let pipelineDeleteMode = false;
  const selectedPipelineIds = new Set();

  function togglePipelineDeleteMode() {
    pipelineDeleteMode = !pipelineDeleteMode;
    const btn = document.getElementById('pipelineDeleteToggleBtn');
    const bar = document.getElementById('pipelineBatchBar');
    if (btn) {
      btn.classList.toggle('active', pipelineDeleteMode);
      btn.innerHTML = pipelineDeleteMode ? '✕ Cancel' : '🗑️ Delete Chats';
    }
    if (bar) {
      bar.style.display = pipelineDeleteMode ? 'flex' : 'none';
    }
    if (!pipelineDeleteMode) {
      selectedPipelineIds.clear();
      const selectAll = document.getElementById('pipelineSelectAllCheckbox');
      if (selectAll) selectAll.checked = false;
    }
    updatePipelineBatchBar();
    renderEmployeeWorkspace();
  }

  function updatePipelineBatchBar() {
    const countEl = document.getElementById('pipelineSelectedCount');
    const delBtn = document.getElementById('pipelineDeleteBtn');
    if (countEl) countEl.textContent = `${selectedPipelineIds.size} selected`;
    if (delBtn) {
      delBtn.disabled = selectedPipelineIds.size === 0;
      delBtn.style.opacity = selectedPipelineIds.size === 0 ? '0.5' : '1';
    }
  }

  window.deletePipelineSingle = function(leadId) {
    const lead = getLead(leadId);
    const name = lead ? getContactDisplayName(lead) : 'this conversation';
    if (!confirm(`Permanently delete conversation with ${name}? This will remove all message history from the server and free storage.`)) {
      return;
    }
    deleteLeads([leadId]);
    selectedPipelineIds.delete(leadId);
    updatePipelineBatchBar();
  };

  els.employeeLeadList?.addEventListener('click', event => {
    const leadButton = event.target.closest('[data-employee-lead]');
    if (!leadButton) return;
    const leadId = leadButton.dataset.employeeLead;
    if (pipelineDeleteMode) {
      if (selectedPipelineIds.has(leadId)) {
        selectedPipelineIds.delete(leadId);
      } else {
        selectedPipelineIds.add(leadId);
      }
      updatePipelineBatchBar();
      renderEmployeeWorkspace();
      return;
    }
    switchTab('inbox');
    if (leadId) {
      selectLead(leadId);
    } else {
      document.querySelector('.inbox-filter-btn[data-filter="fresh"]')?.click();
    }
  });

  els.employeeLeadList?.addEventListener('change', event => {
    const chk = event.target.closest('.pipeline-lead-checkbox');
    if (!chk) return;
    const leadId = chk.dataset.leadId;
    if (chk.checked) selectedPipelineIds.add(leadId);
    else selectedPipelineIds.delete(leadId);
    updatePipelineBatchBar();
    renderEmployeeWorkspace();
  });

  document.getElementById('pipelineDeleteToggleBtn')?.addEventListener('click', togglePipelineDeleteMode);
  document.getElementById('pipelineCancelDeleteBtn')?.addEventListener('click', togglePipelineDeleteMode);
  document.getElementById('pipelineDeleteBtn')?.addEventListener('click', () => {
    if (!selectedPipelineIds.size) {
      showToast('⚠️ Please select at least one customer conversation.');
      return;
    }
    if (!confirm(`Permanently delete ${selectedPipelineIds.size} customer conversation(s) from server? This will remove chat history and save storage.`)) {
      return;
    }
    deleteLeads(Array.from(selectedPipelineIds));
    selectedPipelineIds.clear();
    togglePipelineDeleteMode();
  });
  document.getElementById('pipelineSelectAllCheckbox')?.addEventListener('change', (e) => {
    const isChecked = e.target.checked;
    const user = state.currentUser || state.currentAgent;
    const isAdmin = user ? user.role === 'admin' : false;
    const activeId = user ? user.id : '';
    const mine = isAdmin 
      ? state.leads 
      : state.leads.filter(lead => lead.ownerId === activeId || lead.paidDetails?.closedById === activeId);
    let filteredMine = mine;
    if (currentPipelineFilter !== 'all') {
      filteredMine = mine.filter(lead => getLeadState(lead) === currentPipelineFilter);
    }
    if (isChecked) {
      filteredMine.forEach(l => selectedPipelineIds.add(l.id));
    } else {
      selectedPipelineIds.clear();
    }
    updatePipelineBatchBar();
    renderEmployeeWorkspace();
  });

  // Profile & Workspace Pipeline Filter State
  let currentPipelineFilter = 'all';

  // Smooth Count-Up Animation Engine for Stats & Bento Widgets
  function animateCount(elementId, targetValue, duration = 850, prefix = '', suffix = '') {
    const el = document.getElementById(elementId);
    if (!el) return;
    const rawTarget = typeof targetValue === 'number' ? targetValue : parseInt(String(targetValue).replace(/\D/g, '') || '0', 10);
    const prevVal = parseInt(el.dataset.currentVal || '0', 10);
    el.dataset.currentVal = rawTarget;
    
    if (prevVal === rawTarget && el.textContent.trim() !== '') {
      el.textContent = `${prefix}${rawTarget.toLocaleString()}${suffix}`;
      return;
    }
    
    const startTime = performance.now();
    function step(now) {
      const elapsed = now - startTime;
      const progress = Math.min(elapsed / duration, 1);
      // easeOutCubic curve for smooth rolling animation
      const ease = 1 - Math.pow(1 - progress, 3);
      const current = Math.round(prevVal + (rawTarget - prevVal) * ease);
      el.textContent = `${prefix}${current.toLocaleString()}${suffix}`;
      if (progress < 1) {
        requestAnimationFrame(step);
      } else {
        el.textContent = `${prefix}${rawTarget.toLocaleString()}${suffix}`;
      }
    }
    requestAnimationFrame(step);
  }

  function renderEmployeeWorkspace() {
    const user = state.currentUser || state.currentAgent;
    if (!user) return;
    const isAdmin = user.role === 'admin';
    const employeeName = user.name || 'there';

    // 1. Profile Hero Card
    const nameTarget = document.getElementById('profileCloserName');
    if (nameTarget) nameTarget.textContent = employeeName;
    const roleTarget = document.getElementById('profileCloserRole');
    if (roleTarget) {
      roleTarget.textContent = isAdmin 
        ? '👑 System Administrator' 
        : (user.gender === 'female' ? '👩 Sales Team Lead' : (user.role === 'lead' ? '👨 Sales Team Lead' : '👨 Closer Specialist'));
    }
    const idTarget = document.getElementById('profileCloserId');
    if (idTarget) idTarget.textContent = user.id || 'EMP-TL-01';
    
    const statusTag = document.getElementById('profileStatusTag');
    if (statusTag) {
      statusTag.textContent = isAdmin ? 'Admin Root' : (user.gender === 'female' ? 'Voice Notes: On' : 'Live Closer');
    }
    const vmTarget = document.getElementById('profileVmStatus');
    if (vmTarget) {
      vmTarget.remove();
    }
    const avatarTarget = document.getElementById('profileAvatarText');
    if (avatarTarget) {
      const parts = employeeName.split(/\s+/).filter(Boolean);
      avatarTarget.textContent = parts.length >= 2 ? (parts[0][0] + parts[1][0]).toUpperCase() : employeeName.slice(0, 2).toUpperCase();
    }

    // 2. Compute the 5 Core Requested Metrics
    const activeId = user.id;
    const mine = isAdmin 
      ? state.leads 
      : state.leads.filter(lead => lead.ownerId === activeId || lead.paidDetails?.closedById === activeId);

    const ongoing = mine.filter(lead => getLeadState(lead) === 'ongoing');
    const inReview = mine.filter(lead => getLeadState(lead) === 'pending_verification');
    const paid = mine.filter(lead => getLeadState(lead) === 'paid');
    const wontPay = mine.filter(lead => getLeadState(lead) === 'useless');
    const totalClosed = paid.length + wontPay.length;
    const revenue = paid.reduce((sum, lead) => sum + (Number(lead.paidDetails?.amount) || (String(lead.interest || '').includes('Starter') ? 1700 : 5000)), 0);

    // 3. Dynamic Animated Bento KPI Widgets
    animateCount('employeeMetricOngoing', ongoing.length);
    animateCount('employeeMetricClosed', totalClosed);
    animateCount('employeeMetricWontPay', wontPay.length);
    animateCount('employeeMetricReview', inReview.length);
    animateCount('employeeMetricPaidRevenueVal', revenue);

    // Sync legacy/fallback text targets if present
    const paidEl = document.getElementById('employeeMetricPaidRevenue');
    if (paidEl) paidEl.textContent = `PKR ${revenue.toLocaleString()}`;

    const closedSub = document.getElementById('employeeMetricClosedSub');
    if (closedSub) closedSub.textContent = `${paid.length} won • ${wontPay.length} lost`;

    const dealsSub = document.getElementById('employeeMetricDeals');
    if (dealsSub) dealsSub.textContent = `${paid.length} verified client transactions`;

    const assignedCount = document.getElementById('profileAssignedCount');
    if (assignedCount) assignedCount.textContent = mine.length;

    // Monthly Target Quota Progress
    const targetQuota = 25000;
    const quotaPct = Math.min(Math.round((revenue / targetQuota) * 100), 100);
    const quotaFill = document.getElementById('revenueQuotaFill');
    if (quotaFill) quotaFill.style.width = `${quotaPct}%`;
    const quotaPctEl = document.getElementById('revenueQuotaPercent');
    if (quotaPctEl) quotaPctEl.textContent = `${quotaPct}%`;

    // SVG Circular Win Rate Ring
    const closedDeals = paid.length + wontPay.length;
    const winRate = closedDeals > 0 ? Math.round((paid.length / closedDeals) * 100) : 0;
    const winRateRing = document.getElementById('bentoWinRateRing');
    if (winRateRing) {
      // Circumference = 2 * PI * 14 ~= 88
      const circumference = 88;
      const offset = circumference - (winRate / 100) * circumference;
      winRateRing.style.strokeDashoffset = offset;
    }
    const winRateBadge = document.getElementById('profileWinRateBadge');
    if (winRateBadge) winRateBadge.textContent = `Win Rate: ${winRate}%`;

    // Sync with My Score Modal Strip
    const setMetric = (id, value) => { const element = document.getElementById(id); if (element) element.textContent = value; };
    setMetric('scoreOngoingCount', ongoing.length);
    setMetric('scoreClosedCount', totalClosed);
    setMetric('scoreWontPayCount', wontPay.length);
    setMetric('scoreInReviewCount', inReview.length);
    setMetric('scorePaidCount', `PKR ${revenue.toLocaleString()}`);

    // 4. Pipeline Distribution Bar & Percentage Chips
    const totalCount = mine.length || 1;
    const pctOngoing = Math.round((ongoing.length / totalCount) * 100);
    const pctReview = Math.round((inReview.length / totalCount) * 100);
    const pctPaid = Math.round((paid.length / totalCount) * 100);
    const pctWontPay = Math.round((wontPay.length / totalCount) * 100);

    const progOngoing = document.getElementById('progSegOngoing');
    if (progOngoing) progOngoing.style.width = `${pctOngoing}%`;
    const progReview = document.getElementById('progSegReview');
    if (progReview) progReview.style.width = `${pctReview}%`;
    const progPaid = document.getElementById('progSegPaid');
    if (progPaid) progPaid.style.width = `${pctPaid}%`;
    const progWontPay = document.getElementById('progSegWontPay');
    if (progWontPay) progWontPay.style.width = `${pctWontPay}%`;

    const cOngoing = document.getElementById('chipOngoingVal');
    if (cOngoing) cOngoing.textContent = `${pctOngoing}% (${ongoing.length})`;
    const cReview = document.getElementById('chipReviewVal');
    if (cReview) cReview.textContent = `${pctReview}% (${inReview.length})`;
    const cPaid = document.getElementById('chipPaidVal');
    if (cPaid) cPaid.textContent = `${pctPaid}% (${paid.length})`;
    const cWontPay = document.getElementById('chipWontPayVal');
    if (cWontPay) cWontPay.textContent = `${pctWontPay}% (${wontPay.length})`;

    // 5. Render Earnings & Withdrawal Requests Portal (for TL and TM)
    renderWithdrawalSection(user, mine, revenue);

    // 6. Render Pipeline Customer List
    if (!els.employeeLeadList) return;
    let filteredMine = mine;
    if (currentPipelineFilter !== 'all') {
      filteredMine = mine.filter(lead => getLeadState(lead) === currentPipelineFilter);
    }

    const sorted = [...filteredMine].sort((a, b) => {
      const stateRank = { ongoing: 0, fresh: 1, pending_verification: 2, paid: 3, useless: 4 };
      return (stateRank[getLeadState(a)] ?? 5) - (stateRank[getLeadState(b)] ?? 5);
    });

    if (!sorted.length) {
      els.employeeLeadList.innerHTML = '<div class="employee-empty" style="padding:28px;text-align:center;color:var(--text-muted);"><span style="font-size:24px;display:block;margin-bottom:6px;">📭</span><strong>Pipeline clear.</strong><p style="font-size:12px;margin:4px 0;">No conversations under this filter.</p></div>';
      return;
    }

    const isPipelineDelete = pipelineDeleteMode;
    const stateLabel = { ongoing: 'Ongoing', fresh: 'New', pending_verification: 'In Review', paid: 'Paid', useless: "Won't Pay" };
    els.employeeLeadList.innerHTML = sorted.map(lead => {
      const status = getLeadState(lead);
      const lastMessage = [...(lead.messages || [])].reverse().find(message => message.sender === 'lead')?.text || lead.notes || 'No message preview yet.';
      const rawName = getContactDisplayName(lead);
      const displayName = formatContactName(rawName, isAdmin);
      const isChecked = selectedPipelineIds.has(lead.id);
      const avatarInitials = getContactAvatarInitials(lead);

      const checkHtml = isPipelineDelete ? `
        <span class="pipeline-check-wrap" onclick="event.stopPropagation();" style="display:inline-flex;align-items:center;padding-right:10px;">
          <input type="checkbox" class="pipeline-lead-checkbox" data-lead-id="${escapeHtml(lead.id)}" ${isChecked ? 'checked' : ''} style="width:18px;height:18px;accent-color:#ef4444;cursor:pointer;">
        </span>
      ` : '';

      const trashBtnHtml = isPipelineDelete ? `
        <span class="pipeline-row-delete-btn" title="Permanently delete conversation from server" onclick="event.stopPropagation(); deletePipelineSingle('${escapeHtml(lead.id)}');">
          🗑️
        </span>
      ` : '';

      return `<button class="employee-lead-row ${isChecked ? 'selected-delete' : ''}" type="button" data-employee-lead="${escapeHtml(lead.id)}">${checkHtml}<span class="employee-lead-avatar">${escapeHtml(avatarInitials)}</span><span class="employee-lead-main"><strong>${escapeHtml(displayName)}</strong><small>${escapeHtml(String(lastMessage).slice(0, 90))}</small></span><span class="employee-lead-product">${escapeHtml(lead.interest || 'Nunno Pro')}</span><span class="employee-lead-status ${escapeHtml(status)}">${escapeHtml(stateLabel[status] || status)}</span>${trashBtnHtml}<span aria-hidden="true" class="employee-lead-arrow">↗</span></button>`;
    }).join('');
  }

  // Filter Buttons in Pipeline
  document.addEventListener('click', e => {
    const filterBtn = e.target.closest('[data-pipeline-filter]');
    if (!filterBtn) return;
    document.querySelectorAll('[data-pipeline-filter]').forEach(b => b.classList.remove('active'));
    filterBtn.classList.add('active');
    currentPipelineFilter = filterBtn.getAttribute('data-pipeline-filter') || 'all';
    renderEmployeeWorkspace();
  });

  // ==========================================
  // EARNINGS & WITHDRAWAL MANAGEMENT MODULE
  // ==========================================
  let localWithdrawals = [];
  let withdrawalAvailableBalance = 0;

  async function loadWithdrawals(userId) {
    try {
      const res = await fetch(`api/withdrawals.php?action=get_withdrawals&user_id=${encodeURIComponent(userId || '')}`);
      if (res.ok) {
        const data = await res.json();
        if (data.success && Array.isArray(data.withdrawals)) {
          localWithdrawals = data.withdrawals;
          localStorage.setItem('nunno_cached_withdrawals', JSON.stringify(localWithdrawals));
          return localWithdrawals;
        }
      }
    } catch (_) {}
    const cached = localStorage.getItem('nunno_cached_withdrawals');
    if (cached) {
      try { localWithdrawals = JSON.parse(cached); } catch (_) {}
    }
    return localWithdrawals;
  }

  async function renderWithdrawalSection(user, mineLeads, totalRevenue) {
    if (!user) return;
    const isAdmin = user.role === 'admin';
    const activeUserId = user.id;

    await loadWithdrawals(activeUserId);

    // Calculate commissions & earnings for Team Lead (TL) & Team Member (TM)
    const paidLeads = (mineLeads || []).filter(l => getLeadState(l) === 'paid');
    let earnedCommission = 0;
    if (paidLeads.length > 0) {
      earnedCommission = paidLeads.reduce((sum, lead) => {
        const amt = Number(lead.paidDetails?.amount) || (String(lead.interest || '').includes('Starter') ? 1700 : 5000);
        if (amt >= 15000) return sum + 10000;
        if (amt >= 5000) return sum + 2000;
        if (amt >= 1700) return sum + 700;
        return sum + Math.round(amt * 0.4);
      }, 0);
    }
    
    // For TL & TM: base payout pool with test allowance if 0 leads yet, plus commissions
    const basePayoutPool = isAdmin ? (totalRevenue > 0 ? totalRevenue : 25000) : Math.max(earnedCommission, paidLeads.length ? 5000 : 7500);

    const userWds = isAdmin ? localWithdrawals : localWithdrawals.filter(w => w.userId === activeUserId);
    const pendingWds = userWds.filter(w => w.status === 'pending');
    const settledWds = userWds.filter(w => w.status === 'approved');

    const totalPending = pendingWds.reduce((s, w) => s + (Number(w.amount) || 0), 0);
    const totalSettled = settledWds.reduce((s, w) => s + (Number(w.amount) || 0), 0);

    withdrawalAvailableBalance = Math.max(0, basePayoutPool - totalSettled - totalPending);

    // Update financial balance cards with animated counter rolling
    animateCount('wdAvailableAmount', withdrawalAvailableBalance);
    animateCount('wdPendingAmount', totalPending);
    animateCount('wdSettledAmount', totalSettled);

    const pCountEl = document.getElementById('wdPendingCount');
    if (pCountEl) pCountEl.textContent = `${pendingWds.length} request${pendingWds.length === 1 ? '' : 's'} under review`;

    const sCountEl = document.getElementById('wdSettledCount');
    if (sCountEl) sCountEl.textContent = `${settledWds.length} payout${settledWds.length === 1 ? '' : 's'} completed`;

    const modalBalEl = document.getElementById('modalWdAvailableBalance');
    if (modalBalEl) modalBalEl.textContent = withdrawalAvailableBalance.toLocaleString();

    // Render Recent Withdrawal Requests
    const historyList = document.getElementById('withdrawalHistoryList');
    const historyCount = document.getElementById('withdrawalHistoryCount');
    if (historyCount) historyCount.textContent = `${userWds.length} Request${userWds.length === 1 ? '' : 's'}`;

    if (!historyList) return;

    if (!userWds.length) {
      historyList.innerHTML = `
        <div style="padding:24px 16px;text-align:center;color:var(--text-muted);font-size:12px;">
          <span style="font-size:22px;display:block;margin-bottom:4px;">💳</span>
          No withdrawal requests yet. Click <strong>"Request Payout"</strong> above to cash out earnings.
        </div>
      `;
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
      'USDT (TRC20)': '💎'
    };

    historyList.innerHTML = userWds.map(wd => {
      const isPending = wd.status === 'pending';
      const isApproved = wd.status === 'approved';
      const isRejected = wd.status === 'rejected';

      const institutionDisplay = wd.institution || wd.method || 'Digital Wallet';
      const icon = methodIcons[wd.institution] || methodIcons[wd.method] || (wd.method === 'Bank Account' ? '🏦' : (wd.method === 'USDT (TRC20)' ? '💎' : '📱'));
      const dateStr = wd.created_at ? new Date(wd.created_at).toLocaleDateString('en-PK', { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' }) : 'Recently';

      // 24-Hour Settlement Guarantee countdown clock for pending requests
      const timerPill = isPending ? `
        <div class="wd-timer-pill" data-created="${escapeHtml(wd.created_at || new Date().toISOString())}">
          <span>⏳ Settling in:</span>
          <span class="wd-timer-clock">24h 00m 00s remaining</span>
        </div>
      ` : '';

      const adminActions = (isAdmin && isPending) ? `
        <div style="display:flex;align-items:center;gap:6px;margin-top:6px;justify-content:flex-end;">
          <button type="button" class="btn btn-emerald btn-xs" onclick="updateWithdrawalStatus('${escapeHtml(wd.id)}', 'approved')" style="padding:3px 10px;font-size:11px;border-radius:6px;font-weight:700;">
            ✓ Mark Paid
          </button>
          <button type="button" class="btn btn-rose btn-xs" onclick="updateWithdrawalStatus('${escapeHtml(wd.id)}', 'rejected')" style="padding:3px 10px;font-size:11px;border-radius:6px;font-weight:700;">
            ✕ Reject
          </button>
        </div>
      ` : '';

      return `
        <div class="wd-row-item">
          <div class="wd-row-left">
            <div class="wd-method-icon-circle">${icon}</div>
            <div class="wd-row-info">
              <span class="wd-row-title">${escapeHtml(institutionDisplay)} • ${escapeHtml(wd.accountTitle || wd.userName)}</span>
              <div class="wd-row-meta">
                <span class="mono">${escapeHtml(wd.accountNumber)}</span>
                <span>•</span>
                <span>${escapeHtml(dateStr)}</span>
                ${wd.note ? `<span>• <em>${escapeHtml(wd.note)}</em></span>` : ''}
                ${wd.txRef ? `<span class="badge-tx" style="background:rgba(16,185,129,0.12);color:#10b981;padding:1px 6px;border-radius:4px;font-size:9.5px;font-weight:700;">Tx: ${escapeHtml(wd.txRef)}</span>` : ''}
              </div>
              ${timerPill}
            </div>
          </div>
          <div class="wd-row-right">
            <div style="text-align:right;">
              <span class="wd-row-amount">PKR ${Number(wd.amount || 0).toLocaleString()}</span>
              <div>
                <span class="wd-status-pill ${wd.status}">
                  ${isApproved ? '🟢 Paid / Sent' : (isPending ? '🟡 Pending Review' : '🔴 Rejected')}
                </span>
              </div>
              ${adminActions}
            </div>
          </div>
        </div>
      `;
    }).join('');

    // Start 24-hour settlement ticker
    startWithdrawalCountdownTicker();
  }

  // Real-Time 24-Hour Settlement Countdown Ticker
  function startWithdrawalCountdownTicker() {
    if (window._wdCountdownInterval) {
      clearInterval(window._wdCountdownInterval);
    }
    const updatePills = () => {
      const pills = document.querySelectorAll('.wd-timer-pill[data-created]');
      if (!pills.length) return;
      const now = Date.now();
      pills.forEach(pill => {
        const createdStr = pill.getAttribute('data-created');
        const createdMs = createdStr ? new Date(createdStr).getTime() : now;
        const deadlineMs = createdMs + (24 * 60 * 60 * 1000);
        const diff = deadlineMs - now;
        const clockEl = pill.querySelector('.wd-timer-clock');
        if (!clockEl) return;
        if (diff <= 0) {
          clockEl.textContent = '⚡ Processing Disbursement';
          pill.style.background = 'rgba(16, 185, 129, 0.15)';
          pill.style.borderColor = 'rgba(16, 185, 129, 0.35)';
          pill.style.color = '#10b981';
        } else {
          const totalSecs = Math.floor(diff / 1000);
          const hrs = Math.floor(totalSecs / 3600);
          const mins = Math.floor((totalSecs % 3600) / 60);
          const secs = totalSecs % 60;
          clockEl.textContent = `${hrs}h ${String(mins).padStart(2, '0')}m ${String(secs).padStart(2, '0')}s remaining`;
        }
      });
    };
    updatePills();
    window._wdCountdownInterval = setInterval(updatePills, 1000);
  }

  // Update Withdrawal Status (Admin action)
  window.updateWithdrawalStatus = async function(wdId, newStatus) {
    const txRef = newStatus === 'approved' ? prompt('Enter Bank / Transfer Transaction Reference (e.g. EP-8819024) or leave empty:', 'TX-' + Math.floor(100000 + Math.random() * 900000)) : '';
    try {
      const res = await fetch('api/withdrawals.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'update_status', id: wdId, status: newStatus, txRef })
      });
      const data = await res.json();
      if (data.success) {
        showToast(newStatus === 'approved' ? '🟢 Withdrawal approved & marked as sent!' : '🔴 Withdrawal request rejected.');
        dispatchWithdrawalStatusEmail(wdId, newStatus, txRef);
        renderEmployeeWorkspace();
      } else {
        showToast(data.error || 'Failed to update withdrawal status.');
      }
    } catch (_) {
      // Local fallback
      const item = localWithdrawals.find(w => w.id === wdId);
      if (item) {
        item.status = newStatus;
        if (txRef) item.txRef = txRef;
        localStorage.setItem('nunno_cached_withdrawals', JSON.stringify(localWithdrawals));
        showToast(newStatus === 'approved' ? '🟢 Withdrawal marked paid locally!' : '🔴 Withdrawal marked rejected locally.');
        dispatchWithdrawalStatusEmail(wdId, newStatus, txRef);
        renderEmployeeWorkspace();
      }
    }
  };

  function dispatchWithdrawalStatusEmail(wdId, status, txRef) {
    const targetWd = localWithdrawals.find(w => w.id === wdId);
    if (!targetWd) return;
    const emps = getLocalEmployees();
    const closer = emps.find(e => e.id === targetWd.userId);
    const closerEmail = closer?.email || targetWd.email;
    if (!closerEmail) return;

    fetch('api/email_service.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        action: 'send_email',
        target_type: 'custom',
        target_custom_email: closerEmail,
        target_custom_name: targetWd.userName || closer?.name,
        template_id: status === 'approved' ? 'withdrawal_approved' : 'withdrawal_rejected',
        subject: status === 'approved'
          ? `Funds Disbursed: Your PKR ${targetWd.amount.toLocaleString()} Payout is Complete [${targetWd.institution || targetWd.method}]`
          : `Notice: Withdrawal Request Requires Resubmission [PKR ${targetWd.amount.toLocaleString()}]`,
        badge: status === 'approved' ? 'Funds Sent' : 'Needs Attention',
        message_html: status === 'approved'
          ? `<h3>Payout Disbursed Successfully! 🟢</h3><p>Dear ${escapeHtml(targetWd.userName)}, we are pleased to confirm that your payout of <strong>PKR ${targetWd.amount.toLocaleString()}</strong> has been disbursed to your ${escapeHtml(targetWd.institution || targetWd.method)} account (<code>${escapeHtml(targetWd.accountNumber)}</code>).</p><div style="background:#ecfdf5;border:1px solid #a7f3d0;border-radius:12px;padding:14px;margin:16px 0;"><strong>Transaction Ref:</strong> ${escapeHtml(txRef || 'TX-VERIFIED')}<br><strong>Account Title:</strong> ${escapeHtml(targetWd.accountTitle)}<br><strong>Status:</strong> Completed &amp; Verified</div>`
          : `<h3>Withdrawal Request Update ⚠️</h3><p>Hello ${escapeHtml(targetWd.userName)}, your payout request of <strong>PKR ${targetWd.amount.toLocaleString()}</strong> could not be completed during audit. Your funds have been refunded back to your available balance. Please verify your account details and resubmit.</p>`,
        variables: { name: targetWd.userName, amount: targetWd.amount.toLocaleString(), method: targetWd.institution || targetWd.method, tx_ref: txRef || 'TX-VERIFIED' }
      })
    }).catch(() => {});
  }

  // Modal Open & Close Event Handlers
  const withdrawModal = document.getElementById('withdrawModal');
  const openWdBtn = document.getElementById('openWithdrawModalBtn');
  const closeWdBtn = document.getElementById('closeWithdrawModalBtn');
  const cancelWdBtn = document.getElementById('cancelWithdrawBtn');

  const WALLET_INSTITUTIONS = [
    { value: 'EasyPaisa', label: 'EasyPaisa (Telenor Microfinance)' },
    { value: 'JazzCash', label: 'JazzCash (Mobilink Microfinance)' },
    { value: 'SadaPay', label: 'SadaPay (Mastercard Wallet)' },
    { value: 'NayaPay', label: 'NayaPay (Visa Wallet)' },
    { value: 'UPaisa', label: 'UPaisa (U Microfinance Bank)' },
    { value: 'Zindigi', label: 'Zindigi (JS Bank Digital)' }
  ];

  const BANK_INSTITUTIONS = [
    { value: 'Meezan Bank', label: 'Meezan Bank Limited (Islamic)' },
    { value: 'HBL', label: 'HBL — Habib Bank Limited' },
    { value: 'UBL', label: 'UBL — United Bank Limited' },
    { value: 'MCB Bank', label: 'MCB Bank Limited' },
    { value: 'Bank Alfalah', label: 'Bank Alfalah Limited' },
    { value: 'Allied Bank', label: 'Allied Bank Limited (ABL)' },
    { value: 'Faysal Bank', label: 'Faysal Bank Limited' },
    { value: 'Askari Bank', label: 'Askari Bank Limited' },
    { value: 'Standard Chartered', label: 'Standard Chartered Pakistan' },
    { value: 'Bank of Punjab', label: 'Bank of Punjab (BOP)' },
    { value: 'Dubai Islamic Bank', label: 'Dubai Islamic Bank (DIB)' },
    { value: 'JS Bank', label: 'JS Bank Limited' },
    { value: 'Soneri Bank', label: 'Soneri Bank Limited' },
    { value: 'Habib Metro Bank', label: 'Habib Metropolitan Bank' },
    { value: 'Bank Al Habib', label: 'Bank AL Habib Limited' },
    { value: 'Al Baraka Bank', label: 'Al Baraka Islamic Bank' }
  ];

  function syncPayoutChannel(channel) {
    const instGroup = document.getElementById('wdInstitutionGroup');
    const instLabel = document.getElementById('wdInstitutionLabel');
    const instSelect = document.getElementById('wdInstitutionSelect');
    const titleLabel = document.getElementById('wdTitleLabel');
    const numLabel = document.getElementById('wdNumberLabel');
    const numInput = document.getElementById('wdAccountNumberInput');
    const numHint = document.getElementById('wdNumberHint');
    const errBox = document.getElementById('wdTechnicalError');
    if (errBox) { errBox.style.display = 'none'; errBox.textContent = ''; }

    if (channel === 'wallet') {
      if (instGroup) instGroup.style.display = 'block';
      if (instLabel) instLabel.textContent = 'Select Digital Wallet';
      if (instSelect) {
        instSelect.innerHTML = WALLET_INSTITUTIONS.map(w => `<option value="${w.value}">${w.label}</option>`).join('');
      }
      if (titleLabel) titleLabel.textContent = 'Account Title / Full Name';
      if (numLabel) numLabel.textContent = 'Wallet Mobile Number (11 or 12 Digits)';
      if (numInput) {
        numInput.placeholder = 'e.g. 03081234567 or +923081234567';
      }
      if (numHint) {
        numHint.textContent = 'Enter 11 digits starting with 0 (e.g. 03081234567) or 12 digits with +92 (e.g. +923081234567).';
      }
    } else if (channel === 'bank') {
      if (instGroup) instGroup.style.display = 'block';
      if (instLabel) instLabel.textContent = 'Select Commercial Bank';
      if (instSelect) {
        instSelect.innerHTML = BANK_INSTITUTIONS.map(b => `<option value="${b.value}">${b.label}</option>`).join('');
      }
      if (titleLabel) titleLabel.textContent = 'Account Title (As per Bank Records)';
      if (numLabel) numLabel.textContent = 'Bank Account Number / IBAN';
      if (numInput) {
        numInput.placeholder = 'e.g. PK36MEZN0001234567890101 or Branch Account #';
      }
      if (numHint) {
        numHint.textContent = 'Enter valid 24-character Pakistani IBAN or branch account number.';
      }
    } else if (channel === 'crypto') {
      if (instGroup) instGroup.style.display = 'none';
      if (titleLabel) titleLabel.textContent = 'Wallet Label / Destination Note';
      if (numLabel) numLabel.textContent = 'USDT TRC20 Wallet Address';
      if (numInput) {
        numInput.placeholder = 'e.g. Txyz... (TRC20 Network only)';
      }
      if (numHint) {
        numHint.textContent = 'TRC20 network addresses must begin with "T". Verify destination address carefully.';
      }
    }
  }

  function openWithdrawModal() {
    if (!withdrawModal) return;
    const effectiveBal = withdrawalAvailableBalance > 0 ? withdrawalAvailableBalance : 7500;
    const modalBal = document.getElementById('modalWdAvailableBalance');
    if (modalBal) modalBal.textContent = effectiveBal.toLocaleString();

    // Reset error container
    const errBox = document.getElementById('wdTechnicalError');
    if (errBox) {
      errBox.style.display = 'none';
      errBox.textContent = '';
    }

    const amtInput = document.getElementById('wdAmountInput');
    if (amtInput) {
      amtInput.value = '';
      amtInput.placeholder = 'e.g. 2,000';
    }

    const user = state.currentUser || state.currentAgent;
    const titleInput = document.getElementById('wdAccountTitleInput');
    if (titleInput && (!titleInput.value || titleInput.value.trim() === '')) {
      titleInput.value = user?.name || 'Ali Raza';
    }
    const numInput = document.getElementById('wdAccountNumberInput');
    if (numInput && (!numInput.value || numInput.value.trim() === '')) {
      numInput.value = user?.phone || '03089774571';
    }

    // Default to wallet channel
    syncPayoutChannel('wallet');
    const firstOption = document.querySelector('#withdrawModal .method-option[data-channel="wallet"]');
    if (firstOption) {
      document.querySelectorAll('#withdrawModal .method-option').forEach(o => o.classList.remove('active'));
      firstOption.classList.add('active');
      const radio = firstOption.querySelector('input[type="radio"]');
      if (radio) radio.checked = true;
    }

    withdrawModal.style.display = 'flex';
  }

  function closeWithdrawModal() {
    if (withdrawModal) withdrawModal.style.display = 'none';
  }

  openWdBtn?.addEventListener('click', openWithdrawModal);
  closeWdBtn?.addEventListener('click', closeWithdrawModal);
  cancelWdBtn?.addEventListener('click', closeWithdrawModal);
  document.getElementById('heroQuickWithdrawBtn')?.addEventListener('click', openWithdrawModal);
  document.getElementById('cardQuickWithdrawBtn')?.addEventListener('click', openWithdrawModal);

  // Method Selection
  document.querySelectorAll('#withdrawModal .method-option').forEach(opt => {
    opt.addEventListener('click', () => {
      document.querySelectorAll('#withdrawModal .method-option').forEach(o => o.classList.remove('active'));
      opt.classList.add('active');
      const radio = opt.querySelector('input[type="radio"]');
      if (radio) radio.checked = true;
      const channel = opt.dataset.channel || (radio?.value === 'USDT (TRC20)' ? 'crypto' : (radio?.value === 'Bank Account' ? 'bank' : 'wallet'));
      syncPayoutChannel(channel);
    });
  });

  // Quick Amount Chips
  document.querySelectorAll('#withdrawModal .btn-amt-chip').forEach(chip => {
    chip.addEventListener('click', () => {
      const amtInput = document.getElementById('wdAmountInput');
      if (!amtInput) return;
      const amt = chip.dataset.amt;
      const effectiveBal = withdrawalAvailableBalance > 0 ? withdrawalAvailableBalance : 7500;
      if (amt === 'all') {
        amtInput.value = effectiveBal;
      } else {
        amtInput.value = amt;
      }
    });
  });

  // Submit Withdrawal Form with Live Deduction and Technical Error Guard
  async function handleWithdrawSubmit(e) {
    if (e) e.preventDefault();
    const errBox = document.getElementById('wdTechnicalError');
    const showWdError = (msg) => {
      if (errBox) {
        errBox.textContent = msg;
        errBox.style.display = 'block';
        errBox.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
      } else {
        showToast(msg);
      }
    };

    if (errBox) {
      errBox.style.display = 'none';
      errBox.textContent = '';
    }

    const amtInput = document.getElementById('wdAmountInput');
    const rawAmt = amtInput?.value?.trim();
    const amount = Number(rawAmt);

    // 1. Validation: Amount > 0
    if (!rawAmt || isNaN(amount) || amount <= 0) {
      showWdError('⚠️ [ERR_INVALID_AMOUNT] Please specify a valid withdrawal amount greater than PKR 0.');
      if (amtInput) amtInput.focus();
      return;
    }

    // 2. Validation: Amount <= available balance
    const currentMax = withdrawalAvailableBalance > 0 ? withdrawalAvailableBalance : 7500;
    if (amount > currentMax) {
      showWdError(`⚠️ [ERR_EXCEEDS_BALANCE] Technical Error: Requested amount (PKR ${amount.toLocaleString()}) exceeds your available balance (PKR ${currentMax.toLocaleString()}).`);
      if (amtInput) amtInput.focus();
      return;
    }

    const activeMethodRadio = document.querySelector('input[name="payoutMethod"]:checked');
    const selectedMethod = activeMethodRadio?.value || 'Digital Wallet';
    const activeOption = activeMethodRadio?.closest('.method-option');
    const channel = activeOption?.dataset.channel || (selectedMethod === 'USDT (TRC20)' ? 'crypto' : (selectedMethod === 'Bank Account' ? 'bank' : 'wallet'));

    const instSelect = document.getElementById('wdInstitutionSelect');
    const institution = (channel === 'crypto') ? 'USDT (TRC20)' : (instSelect?.value || selectedMethod);

    const user = state.currentUser || state.currentAgent;
    let accountTitle = document.getElementById('wdAccountTitleInput')?.value.trim();
    let accountNumber = document.getElementById('wdAccountNumberInput')?.value.trim();
    const note = document.getElementById('wdNoteInput')?.value.trim() || '';

    // 3. Validation: Account Title
    if (!accountTitle || accountTitle.length < 3) {
      showWdError('⚠️ [ERR_INVALID_TITLE] Beneficiary account title must be at least 3 characters.');
      document.getElementById('wdAccountTitleInput')?.focus();
      return;
    }

    // 4. Validation: Channel Specific Number/Address
    if (channel === 'wallet') {
      const cleanPhone = accountNumber.replace(/[\s\-\(\)]/g, '');
      const is11Digits = /^0[0-9]{10}$/.test(cleanPhone);
      const is12Digits = /^\+?92[0-9]{10}$/.test(cleanPhone);

      if (!is11Digits && !is12Digits) {
        showWdError(`⚠️ [ERR_INVALID_WALLET_NUMBER] For digital wallets (${institution}), please enter exactly 11 digits starting with 0 (e.g. 03081234567) or 12 digits starting with +92 (e.g. +923081234567). Current length: ${cleanPhone.length} digits.`);
        document.getElementById('wdAccountNumberInput')?.focus();
        return;
      }
      accountNumber = cleanPhone;
    } else if (channel === 'bank') {
      if (!accountNumber || accountNumber.length < 8) {
        showWdError('⚠️ [ERR_INVALID_BANK_ACCOUNT] Please enter a valid bank account number or 24-character IBAN.');
        document.getElementById('wdAccountNumberInput')?.focus();
        return;
      }
    } else if (channel === 'crypto') {
      if (!accountNumber || !accountNumber.startsWith('T') || accountNumber.length < 30) {
        showWdError('⚠️ [ERR_INVALID_CRYPTO_ADDRESS] USDT TRC20 wallet addresses must begin with "T" and contain at least 34 characters.');
        document.getElementById('wdAccountNumberInput')?.focus();
        return;
      }
    }

    // 5. LIVE BALANCE DEDUCTION (Immediate visual response)
    withdrawalAvailableBalance = Math.max(0, currentMax - amount);
    animateCount('wdAvailableAmount', withdrawalAvailableBalance);
    const curPendingEl = document.getElementById('wdPendingAmount');
    if (curPendingEl) {
      const curPendingVal = Number(curPendingEl.textContent.replace(/[^0-9]/g, '')) || 0;
      animateCount('wdPendingAmount', curPendingVal + amount);
    }
    const modalBalEl = document.getElementById('modalWdAvailableBalance');
    if (modalBalEl) modalBalEl.textContent = withdrawalAvailableBalance.toLocaleString();

    const payload = {
      action: 'request_withdrawal',
      userId: user?.id || 'EMP-TL-01',
      userName: user?.name || 'Ali Raza',
      userRole: user?.role || 'lead',
      amount,
      method: selectedMethod,
      institution,
      accountTitle,
      accountNumber,
      note
    };

    // Close modal immediately and show guarantee notification
    closeWithdrawModal();
    showToast(`🟢 PKR ${amount.toLocaleString()} requested! Funds will be audited & disbursed within 24 hours.`);

    // Dispatch withdrawal lodged email confirmation to closer
    if (state.currentUser && state.currentUser.email) {
      fetch('api/email_service.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          action: 'send_email',
          target_type: 'custom',
          target_custom_email: state.currentUser.email,
          target_custom_name: state.currentUser.name,
          template_id: 'withdrawal_lodged',
          subject: `Withdrawal Lodged: PKR ${amount.toLocaleString()} Processing Notice`,
          badge: 'Audit In Progress',
          message_html: `<h3>Withdrawal Request Confirmed ⏱️</h3><p>Hello ${escapeHtml(state.currentUser.name)}, your withdrawal request of <strong>PKR ${amount.toLocaleString()}</strong> via <strong>${escapeHtml(institution)}</strong> has been lodged.</p><div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:12px;padding:16px;margin:16px 0;"><strong>Amount:</strong> PKR ${amount.toLocaleString()}<br><strong>Account:</strong> ${escapeHtml(accountTitle)} (${escapeHtml(accountNumber)})<br><strong>Destination:</strong> ${escapeHtml(institution)}<br><strong>Audit SLA:</strong> Within 24 Hours Guaranteed</div><p>Our accounts team will verify and transfer your funds within 24 hours.</p>`,
          variables: { name: state.currentUser.name, amount: amount.toLocaleString(), method: institution }
        })
      }).catch(() => {});
    }

    try {
      const res = await fetch('api/withdrawals.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify(payload)
      });
      if (res.ok) {
        const data = await res.json();
        if (data.success) {
          await loadWithdrawals(payload.userId);
          renderEmployeeWorkspace();
          return;
        } else {
          showToast(`⚠️ Server notice: ${data.error || 'Failed to submit.'}`);
        }
      }
    } catch (err) {
      console.warn('API submission error, using local fallback:', err);
    }

    // Local fallback persistence
    const newWd = {
      id: 'WD-' + Date.now().toString().slice(-5) + Math.floor(Math.random() * 90 + 10),
      userId: payload.userId,
      userName: payload.userName,
      userRole: payload.userRole,
      amount: payload.amount,
      method: payload.method,
      institution: payload.institution,
      accountTitle: payload.accountTitle,
      accountNumber: payload.accountNumber,
      note: payload.note,
      status: 'pending',
      created_at: new Date().toISOString()
    };
    localWithdrawals.unshift(newWd);
    localStorage.setItem('nunno_cached_withdrawals', JSON.stringify(localWithdrawals));
    renderEmployeeWorkspace();
  }

  document.getElementById('withdrawRequestForm')?.addEventListener('submit', handleWithdrawSubmit);
  document.getElementById('submitWithdrawBtn')?.addEventListener('click', handleWithdrawSubmit);

  // ==========================================
  // AUTHENTICATION & EMPLOYEE PROVISIONING MODULE
  // ==========================================

  // Check active session on load
  function checkAuthSession() {
    if (window.NUNNO_ACTIVE_USER && typeof window.NUNNO_ACTIVE_USER === 'object') {
      applyUserSession(window.NUNNO_ACTIVE_USER);
      return;
    }
    const localSession = localStorage.getItem('nunno_active_session');
    if (localSession) {
      try {
        const u = JSON.parse(localSession);
        if (u && (u.username || u.id)) {
          applyUserSession(u);
          return;
        }
      } catch (e) {}
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
          // Default to Closer session (Hamza Khan) so phone masking & role restrictions are active by default
          const defaultCloser = {
            id: 'USR-EMP-01',
            name: 'Hamza Khan',
            username: 'closer',
            role: 'member',
            designation: 'Closer Specialist',
            gender: 'male',
            status: 'active'
          };
          applyUserSession(defaultCloser);
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
        const defaultCloser = {
          id: 'USR-EMP-01',
          name: 'Hamza Khan',
          username: 'closer',
          role: 'member',
          designation: 'Closer Specialist',
          gender: 'male',
          status: 'active'
        };
        applyUserSession(defaultCloser);
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

    if (!overlay) {
      if (typeof onComplete === 'function') onComplete();
      return;
    }

    const userName = (user && user.name) ? user.name : '';
    const isAdmin = (user && user.role === 'admin');

    if (roleTag) {
      roleTag.textContent = isAdmin ? 'Admin Suite' : 'Closer Portal';
    }
    if (subtext) {
      subtext.textContent = userName ? `Welcome back, ${userName}` : (isAdmin ? 'Opening Executive Suite…' : 'Opening Closer Workspace…');
    }

    // 1. Instantly hide the login card and header so the login card cannot flash
    if (loginContainer) {
      loginContainer.style.display = 'none';
    }

    // Adapt animation palette to current light / dark theme
    const isDark = document.body.classList.contains('theme-dark') || localStorage.getItem('nunno_theme') === 'dark';
    overlay.classList.toggle('theme-dark', isDark);
    overlay.classList.toggle('theme-light', !isDark);

    // 2. Force CSS reflow so the animation restarts smoothly from frame 0
    overlay.classList.remove('active');
    void overlay.offsetWidth;
    overlay.classList.add('active');

    // 3. At 1.6s when the 360 rotation & zoom-out completes, reveal the dashboard
    setTimeout(() => {
      overlay.classList.remove('active');
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
    const roleTag = document.getElementById('animRoleTag');
    const subtext = document.getElementById('animSubtext');

    if (!overlay) {
      if (typeof onComplete === 'function') onComplete();
      return;
    }

    if (roleTag) {
      roleTag.textContent = 'Secure Sign Out';
    }
    if (subtext) {
      subtext.textContent = 'Closing your workspace…';
    }

    // Adapt animation palette to current light / dark theme
    const isDark = document.body.classList.contains('theme-dark') || localStorage.getItem('nunno_theme') === 'dark';
    overlay.classList.toggle('theme-dark', isDark);
    overlay.classList.toggle('theme-light', !isDark);

    // Force CSS reflow so the 360 spin & zoom-out animation always replays cleanly from 0s
    overlay.classList.remove('active');
    void overlay.offsetWidth;
    overlay.classList.add('active');

    // Complete the full 1.6s signature 360 rotation & zoom-out
    setTimeout(() => {
      overlay.classList.remove('active');
      if (typeof onComplete === 'function') {
        onComplete();
      }
    }, 1600);
  }

  function updateVoiceRecordingPermission() {
    const isMale = state.currentUser && state.currentUser.gender === 'male';
    if (els.voiceRecordBtn) {
      if (isMale) {
        els.voiceRecordBtn.disabled = true;
        els.voiceRecordBtn.setAttribute('disabled', 'disabled');
        els.voiceRecordBtn.classList.add('vm-disabled');
        els.voiceRecordBtn.title = 'Voice notes are restricted for male closers';
      } else {
        els.voiceRecordBtn.disabled = false;
        els.voiceRecordBtn.removeAttribute('disabled');
        els.voiceRecordBtn.classList.remove('vm-disabled');
        els.voiceRecordBtn.title = 'Record voice note';
      }
    }
  }

  function applyUserSession(user) {
    state.currentUser = user;
    refreshSenderConnection().catch(() => {});
    localStorage.setItem('nunno_active_session', JSON.stringify(user));
    hideAuthOverlay();

    // Check account suspension status immediately
    if (user.status === 'suspended') {
      showAccountSuspendedScreen(user);
    } else {
      hideAccountSuspendedScreen();
    }

    // Map to active closer/agent
    state.currentAgent = {
      id: user.id,
      name: user.name,
      role: user.designation || (user.role === 'admin' ? 'Master Admin' : 'Closer'),
      avatar: user.role === 'admin' ? '👑' : (user.gender === 'female' ? '👩' : '👨'),
      color: user.role === 'admin' ? '#a855f7' : '#25d366'
    };

    renderAgentProfile();
    updateVoiceRecordingPermission();

    // If Admin, show Employees and Email Studio buttons and fetch roster
    if (user.role === 'admin') {
      if (els.openTeamModalBtn) els.openTeamModalBtn.style.display = 'inline-flex';
      if (els.openEmailModalBtn) els.openEmailModalBtn.style.display = 'inline-flex';
      fetchTeamRoster();
    } else {
      if (els.openTeamModalBtn) els.openTeamModalBtn.style.display = 'none';
      if (els.openEmailModalBtn) els.openEmailModalBtn.style.display = 'none';
    }

    renderConversationsList();
    renderActiveChat();
    renderEmployeeWorkspace();
    if (user.role === 'admin') switchTab('inbox');
    else switchTab('employee');
  }

  function showAccountSuspendedScreen(user) {
    const overlay = document.getElementById('accountSuspendedOverlay');
    if (!overlay) return;
    const nameEl = document.getElementById('suspendedEmpName');
    const userEl = document.getElementById('suspendedEmpUsername');
    if (nameEl) nameEl.textContent = user?.name || 'Authorized Closer';
    if (userEl) userEl.textContent = '@' + (user?.username || 'user');
    overlay.style.display = 'flex';
    document.body.style.overflow = 'hidden';
  }

  function hideAccountSuspendedScreen() {
    const overlay = document.getElementById('accountSuspendedOverlay');
    if (overlay) overlay.style.display = 'none';
    document.body.style.overflow = '';
  }

  document.getElementById('suspendedLogoutBtn')?.addEventListener('click', () => {
    hideAccountSuspendedScreen();
    handleLogout();
  });

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
      let userToApply = data.user;
      if (data.user && data.user.id === 'local-admin') {
        const localEmps = getLocalEmployees();
        const matched = localEmps.find(e => e.username.toLowerCase() === username.toLowerCase());
        if (matched) userToApply = matched;
      }
      triggerLoginAnimation(userToApply, () => {
        applyUserSession(userToApply);
        showToast(`Welcome back, ${userToApply.name}!`);
        if (els.authPassword) els.authPassword.value = '';
      });
    })
    .catch(err => {
      // Offline fallback: check default admin
      if (username.toLowerCase() === 'admin' && (password === 'Admin@Omnidesk2026!' || password === 'admin123')) {
        const offlineAdmin = {
          id: 'USR-ADMIN-01',
          name: 'System Administrator',
          username: 'admin',
          email: 'admin@nunno.cloud',
          role: 'admin',
          designation: 'Master Administrator',
          gender: 'male'
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
      const matched = localEmps.find(e => (e.username.toLowerCase() === username.toLowerCase() || (e.email && e.email.toLowerCase() === username.toLowerCase())) && (e.password === password || e.altPassword === password));
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
      if (els.openEmailModalBtn) els.openEmailModalBtn.style.display = 'none';
      hideAccountSuspendedScreen();
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
    const email = (els.newEmpEmail?.value || '').trim() || (username + '@nunno.cloud');
    const designation = (els.newEmpRole?.value || 'Team Member');
    const role = designation === 'Admin' ? 'admin' : (designation === 'Team Lead' ? 'lead' : 'member');
    const gender = (els.newEmpGender?.value || 'male');
    const phone = (els.newEmpPhone?.value || '').trim();
    const shouldSendWelcome = els.sendWelcomeEmailOnCreate ? els.sendWelcomeEmailOnCreate.checked : true;

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
      body: JSON.stringify({ name, username, password, email, designation, role, gender, phone })
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

      // Dispatch welcome email with credentials if option enabled
      if (shouldSendWelcome && email) {
        dispatchWelcomeEmail(name, username, password, email, designation);
      }

      if (els.newEmpName) els.newEmpName.value = '';
      if (els.newEmpUsername) els.newEmpUsername.value = '';
      if (els.newEmpEmail) els.newEmpEmail.value = '';
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
        email,
        password,
        role,
        designation,
        gender,
        phone,
        status: 'active',
        created_at: new Date().toLocaleString()
      };
      localEmps.push(newEmp);
      saveLocalEmployees(localEmps);
      showCreateEmpAlert(`✅ Employee account created for ${name} (Stored locally).`, 'success');
      showToast(`Employee ${name} provisioned!`);

      if (shouldSendWelcome && email) {
        dispatchWelcomeEmail(name, username, password, email, designation);
      }

      if (els.newEmpName) els.newEmpName.value = '';
      if (els.newEmpUsername) els.newEmpUsername.value = '';
      if (els.newEmpEmail) els.newEmpEmail.value = '';
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

  function dispatchWelcomeEmail(name, username, password, email, designation) {
    fetch('api/email_service.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        action: 'send_email',
        target_type: 'custom',
        target_custom_email: email,
        target_custom_name: name,
        template_id: 'welcome',
        subject: `Welcome to OmniDesk — Your Closer Account is Ready [${username}]`,
        badge: 'Account Ready',
        message_html: `<h3>Welcome to the Team, ${escapeHtml(name)}! 👋</h3><p>Your authorized OmniDesk sales closer workspace has been provisioned by the Administrator.</p><div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;padding:16px;margin:16px 0;"><strong>Workspace URL:</strong> <a href="${window.location.origin}">${window.location.origin}</a><br><strong>Username:</strong> <code style="background:#ede9fe;color:#5b21b6;padding:2px 6px;border-radius:4px;font-weight:700;">${escapeHtml(username)}</code><br><strong>Initial Password:</strong> <code style="background:#fee2e2;color:#991b1b;padding:2px 6px;border-radius:4px;font-weight:700;">${escapeHtml(password)}</code><br><strong>Designation:</strong> ${escapeHtml(designation)}</div><p style="font-size:12px;color:#64748b;">Please sign in and change your initial password immediately.</p>`,
        variables: { name, username, password, role: designation, email, portal_url: window.location.origin }
      })
    })
    .then(r => r.json())
    .then(res => {
      if (res && res.success) {
        showToast(`✉️ Welcome email dispatched to ${email}`);
      }
    })
    .catch(() => {});
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
          populateAdminEmailUsers();
          if (els.headerEmpCount) els.headerEmpCount.textContent = data.employees.length;
        }
      })
      .catch(() => {
        const localEmps = getLocalEmployees();
        state.employees = localEmps;
        renderRosterList(localEmps);
        populateAdminEmailUsers();
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
      const isMale = (emp.gender || 'male') === 'male';
      const genderTag = isMale ? '👨 Male' : '👩 Female';
      const vmStatus = isMale ? '<span style="color:#ef4444;font-size:10px;font-weight:700;">🚫 VM Disabled</span>' : '<span style="color:#10b981;font-size:10px;font-weight:700;">🎙️ VM Active</span>';

      return `
        <article class="emp-roster-card ${isSuspended ? 'suspended' : ''}">
          <div class="emp-card-user">
            <div class="emp-avatar ${isAdmin ? 'admin-av' : ''}">${initials}</div>
            <div class="emp-card-identity">
              <div class="emp-card-name">
                <span>${escapeHtml(emp.name)}</span>
                ${isAdmin ? '<span class="badge-role-admin" style="font-size:9px;">Admin</span>' : ''}
              </div>
              <div class="emp-card-sub">
                <span class="emp-card-username">@${escapeHtml(emp.username)}</span>
                <span class="emp-card-role">${escapeHtml(emp.designation || emp.role)}</span>
                <span style="font-size:10.5px;color:var(--text-muted);margin-left:6px;">${genderTag} • ${vmStatus}</span>
                ${emp.email ? `<span style="font-size:10.5px;color:var(--text-secondary);display:block;margin-top:2px;">✉️ ${escapeHtml(emp.email)}</span>` : ''}
              </div>
            </div>
          </div>
          <div class="emp-actions-group">
            <span class="emp-status-badge ${isSuspended ? 'suspended' : 'active'}">
              <span class="emp-status-indicator" aria-hidden="true"></span>${isSuspended ? '🔴 Suspended' : '🟢 Active'}
            </span>
            ${!isAdmin ? `
              <button type="button" class="btn-emp-action ${isSuspended ? 'reactivate' : 'suspend'}" onclick="window.OmnideskToggleStatus('${emp.id}')" title="${isSuspended ? 'Reactivate Employee Access' : 'Suspend Employee Access'}">
                ${isSuspended ? '✅ Reactivate' : '🚫 Suspend'}
              </button>
              <button type="button" class="btn-emp-action mail" onclick="window.OmnideskQuickEmail('${emp.id}')" title="Send Email to ${escapeHtml(emp.name)}">
                ✉️ Email
              </button>
              <button type="button" class="btn-emp-action delete" onclick="window.OmnideskDeleteEmp('${emp.id}', '${escapeHtml(emp.name)}')" title="Remove employee" aria-label="Remove ${escapeHtml(emp.name)}">
                ✕ Remove
              </button>
            ` : ''}
          </div>
        </article>
      `;
    }).join('');
  }

  // Global window hooks for inline onclicks in roster
  window.OmnideskToggleStatus = function(empId) {
    const targetEmp = (state.employees || []).find(e => e.id === empId);
    const willSuspend = targetEmp && targetEmp.status !== 'suspended';
    const actionText = willSuspend ? 'suspend this employee and lock their workspace' : 'reactivate this employee and restore their workspace';

    if (!confirm(`Are you sure you want to ${actionText}?`)) return;

    fetch('api/auth.php?action=toggle_status', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ id: empId })
    })
    .then(res => res.json())
    .then(data => {
      if (data && data.success) {
        showToast(willSuspend ? '🚫 Employee suspended. Access locked.' : '🟢 Employee access restored.');
        fetchTeamRoster();

        // If target is currently active user on this client, update lock screen immediately
        if (state.currentUser && state.currentUser.id === empId) {
          state.currentUser.status = willSuspend ? 'suspended' : 'active';
          if (willSuspend) showAccountSuspendedScreen(state.currentUser);
          else hideAccountSuspendedScreen();
        }

        // Send email notice
        if (targetEmp && targetEmp.email) {
          fetch('api/email_service.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
              action: 'send_email',
              target_type: 'custom',
              target_custom_email: targetEmp.email,
              target_custom_name: targetEmp.name,
              template_id: willSuspend ? 'account_suspended' : 'account_reactivated',
              subject: willSuspend ? 'Urgent: Your OmniDesk Closer Account has been Suspended' : 'Welcome Back: Your OmniDesk Access is Restored',
              badge: willSuspend ? 'Suspended' : 'Restored',
              message_html: willSuspend 
                ? `<h3>Account Access Suspended 🚫</h3><p>Dear ${escapeHtml(targetEmp.name)}, your OmniDesk closer workspace access (<code>${escapeHtml(targetEmp.username)}</code>) has been suspended by Administration effective immediately.</p><p>All WhatsApp sending, inbound replies, and withdrawals are locked. Please contact management for review.</p>`
                : `<h3>Welcome Back! Your Access is Restored 🟢</h3><p>Dear ${escapeHtml(targetEmp.name)}, your OmniDesk closer workspace access (<code>${escapeHtml(targetEmp.username)}</code>) has been reactivated with full closing privileges.</p>`,
              variables: { name: targetEmp.name, username: targetEmp.username, email: targetEmp.email }
            })
          }).catch(() => {});
        }
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
        showToast(target.status === 'suspended' ? '🚫 Employee suspended (local).' : '🟢 Employee reactivated (local).');
        if (state.currentUser && state.currentUser.id === empId) {
          state.currentUser.status = target.status;
          if (target.status === 'suspended') showAccountSuspendedScreen(state.currentUser);
          else hideAccountSuspendedScreen();
        }
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
      try {
        const parsed = JSON.parse(raw);
        if (Array.isArray(parsed) && parsed.some(e => e.username === 'ali') && parsed.some(e => e.username === 'asima')) {
          return parsed;
        }
      } catch (e) {}
    }
    const defaultEmps = [
      { id: 'USR-ADMIN-01', name: 'System Administrator', username: 'admin', email: 'admin@nunno.cloud', password: 'Admin@Omnidesk2026!', altPassword: 'admin123', role: 'admin', designation: 'Admin', gender: 'male', status: 'active' },
      { id: 'EMP-TL-01', name: 'Ali Raza (Team Lead)', username: 'ali', email: 'ali@nunno.cloud', password: 'ali123', altPassword: 'Lead@123', role: 'lead', designation: 'Male Team Lead', gender: 'male', status: 'active' },
      { id: 'EMP-TL-02', name: 'Asima Noor (Team Lead)', username: 'asima', email: 'asima@nunno.cloud', password: 'asima123', altPassword: 'Lead@123', role: 'lead', designation: 'Female Team Lead', gender: 'female', status: 'active' },
      { id: 'EMP-101', name: 'Hamza Khan', username: 'hamza', email: 'hamza@nunno.cloud', password: 'Agent@123', altPassword: 'closer123', role: 'member', designation: 'Closer Specialist', gender: 'male', status: 'active' }
    ];
    localStorage.setItem('nunno_local_employees', JSON.stringify(defaultEmps));
    return defaultEmps;
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
  document.getElementById('quickFillMaleLeadBtn')?.addEventListener('click', () => {
    if (els.authUsername) els.authUsername.value = 'ali';
    if (els.authPassword) els.authPassword.value = 'ali123';
    if (els.authAlert) els.authAlert.style.display = 'none';
  });
  document.getElementById('quickFillFemaleLeadBtn')?.addEventListener('click', () => {
    if (els.authUsername) els.authUsername.value = 'asima';
    if (els.authPassword) els.authPassword.value = 'asima123';
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
  // ADMIN EMAIL COMMUNICATIONS STUDIO CONTROLLER
  // ==========================================

  let emailTemplatesCache = [];
  let emailRosterCache = [];

  function openAdminEmailModal(preselectEmpId) {
    if (!state.currentUser || state.currentUser.role !== 'admin') {
      showToast('⚠️ Access Denied: Only Administrator can access the Email Studio.');
      return;
    }
    if (els.adminEmailModal) els.adminEmailModal.classList.add('open');
    switchAdminEmailTab('compose');
    populateAdminEmailUsers(preselectEmpId);
    loadAdminEmailTemplates();
  }

  function closeAdminEmailModal() {
    if (els.adminEmailModal) els.adminEmailModal.classList.remove('open');
  }

  function switchAdminEmailTab(tab) {
    const composeBtn = document.getElementById('tabEmailComposeBtn');
    const logsBtn = document.getElementById('tabEmailLogsBtn');
    const settingsBtn = document.getElementById('tabEmailSettingsBtn');

    const composeView = document.getElementById('viewEmailCompose');
    const logsView = document.getElementById('viewEmailLogs');
    const settingsView = document.getElementById('viewEmailSettings');

    if (composeBtn) composeBtn.classList.toggle('active', tab === 'compose');
    if (logsBtn) logsBtn.classList.toggle('active', tab === 'logs');
    if (settingsBtn) settingsBtn.classList.toggle('active', tab === 'settings');

    if (composeView) composeView.style.display = tab === 'compose' ? 'grid' : 'none';
    if (logsView) logsView.style.display = tab === 'logs' ? 'block' : 'none';
    if (settingsView) settingsView.style.display = tab === 'settings' ? 'block' : 'none';

    if (tab === 'logs') loadAdminEmailLogs();
    if (tab === 'settings') loadAdminEmailSettings();
  }

  function populateAdminEmailUsers(preselectEmpId) {
    const select = document.getElementById('modalEmailUserSelect');
    if (!select) return;

    fetch('api/auth.php?action=get_team')
      .then(r => r.json())
      .then(data => {
        emailRosterCache = (data && data.success && Array.isArray(data.users)) ? data.users : getLocalEmployees();
        renderUserOptions(preselectEmpId);
      })
      .catch(() => {
        emailRosterCache = getLocalEmployees();
        renderUserOptions(preselectEmpId);
      });

    function renderUserOptions(preselectId) {
      select.innerHTML = '';
      emailRosterCache.forEach(u => {
        const opt = document.createElement('option');
        opt.value = u.id;
        opt.textContent = `${u.name} (@${u.username}) — ${u.email || 'no email registered'}`;
        opt.dataset.email = u.email || '';
        opt.dataset.name = u.name;
        opt.dataset.username = u.username;
        opt.dataset.role = u.role;
        select.appendChild(opt);
      });
      if (preselectId) {
        select.value = preselectId;
        const targetType = document.getElementById('modalEmailTargetType');
        if (targetType) targetType.value = 'user';
        handleEmailTargetTypeChange();
      }
      updateModalEmailPreview();
    }
  }

  function handleEmailTargetTypeChange() {
    const targetType = document.getElementById('modalEmailTargetType')?.value || 'user';
    const userGroup = document.getElementById('modalEmailUserGroup');
    const customGroup = document.getElementById('modalEmailCustomGroup');

    if (userGroup) userGroup.style.display = targetType === 'user' ? 'block' : 'none';
    if (customGroup) customGroup.style.display = targetType === 'custom' ? 'flex' : 'none';

    updateModalEmailPreview();
  }

  async function loadAdminEmailTemplates() {
    const select = document.getElementById('modalEmailTemplateSelect');
    if (!select) return;

    try {
      const res = await fetch('api/email_service.php?action=get_templates');
      const data = await res.json();
      if (data && data.success && Array.isArray(data.templates)) {
        emailTemplatesCache = data.templates;
      }
    } catch (_) {}

    if (!emailTemplatesCache || !emailTemplatesCache.length) {
      emailTemplatesCache = [
        { id: 'welcome', name: '👋 New Employee Onboarding & Login Credentials', badge: 'Account Activated', subject: 'Welcome to the OmniDesk Team! Your Closer Credentials', default_body: '<h3>Welcome to OmniDesk Closer Workspace! 🚀</h3><p>Dear {name}, your official closer account has been provisioned.</p><div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;padding:16px;margin:16px 0;"><strong>Username:</strong> <code>{username}</code><br><strong>Access Level:</strong> {role}<br><strong>Security:</strong> Temporary password generated</div><p>Log in securely to start handling leads and closing deals.</p>' },
        { id: 'withdrawal_lodged', name: '💳 Withdrawal Request Received (24-Hour Settlement)', badge: 'Audit In Progress', subject: 'Withdrawal Lodged: PKR {amount} Processing Notice', default_body: '<h3>Withdrawal Request Confirmed ⏱️</h3><p>Hello {name}, your withdrawal request of <strong>PKR {amount}</strong> via <strong>{method}</strong> has been logged.</p><div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:12px;padding:16px;margin:16px 0;"><strong>Amount:</strong> PKR {amount}<br><strong>Destination:</strong> {method}<br><strong>Audit SLA:</strong> Within 24 Hours Guaranteed</div>' },
        { id: 'withdrawal_approved', name: '💰 Withdrawal Approved & Payout Disbursed', badge: 'Funds Sent', subject: 'Funds Disbursed: Your PKR {amount} Payout is Complete [{method}]', default_body: '<h3>Payout Disbursed Successfully! 🟢</h3><p>Dear {name}, we are pleased to confirm that your payout of <strong>PKR {amount}</strong> has been disbursed to your {method} account.</p><div style="background:#ecfdf5;border:1px solid #a7f3d0;border-radius:12px;padding:14px;margin:16px 0;"><strong>Status:</strong> Completed &amp; Verified<br><strong>Disbursed At:</strong> {date}</div>' },
        { id: 'withdrawal_rejected', name: '⚠️ Withdrawal Notice: Action Required', badge: 'Needs Attention', subject: 'Notice: Withdrawal Request Requires Resubmission [PKR {amount}]', default_body: '<h3>Withdrawal Request Update ⚠️</h3><p>Hello {name}, your payout request of <strong>PKR {amount}</strong> could not be completed during audit. Your funds have been restored to your available balance.</p>' },
        { id: 'account_suspended', name: '🚫 Account Suspension Formal Notice', badge: 'Suspended', subject: 'Urgent: Your OmniDesk Closer Account has been Suspended', default_body: '<h3>Account Access Suspended 🚫</h3><p>Dear {name}, your OmniDesk closer workspace access (<code>{username}</code>) has been suspended by Administration effective immediately. All outbound sending, inbound replies, and withdrawals are locked.</p>' },
        { id: 'account_reactivated', name: '🟢 Account Reactivated — Workspace Restored', badge: 'Access Restored', subject: 'Welcome Back: Your OmniDesk Access is Restored [{username}]', default_body: '<h3>Welcome Back! Your Access is Restored 🟢</h3><p>Dear {name}, your OmniDesk closer workspace (<code>{username}</code>) has been reactivated with full closing and payout privileges.</p>' },
        { id: 'sales_milestone', name: '🎯 Monthly Sales Milestone & Leaderboard Honor', badge: 'Top Closer', subject: 'Congratulations on Crushing Your Target, {name}! 🎯', default_body: '<h3>Outstanding Closing Performance! 🏆</h3><p>Congratulations {name}! You have achieved your sales volume target this cycle on OmniDesk. Your performance bonus qualification is active.</p>' },
        { id: 'system_notice', name: '📢 System Maintenance & Bridge Advisory', badge: 'System Advisory', subject: 'Advisory: Scheduled OmniDesk Optimization & Bridge Health Check', default_body: '<h3>System Maintenance Notice 🛠️</h3><p>Dear OmniDesk Team, scheduled maintenance will be conducted on the WhatsApp bridge socket. Chat data and pending payouts remain fully preserved.</p>' },
        { id: 'lead_alert', name: '⚡ Unaddressed Lead Reassignment Warning', badge: 'Urgent Response', subject: 'Pipeline Alert: Lead Awaiting Follow-up [Reassignment Warning]', default_body: '<h3>Urgent: Lead Awaiting Follow-up ⚡</h3><p>Hello {name}, customer inquiries are waiting for your response. Unanswered leads are recycled back to the pool after 24 hours.</p>' },
        { id: 'custom', name: '📝 Custom Team Announcement / Directive', badge: 'Announcement', subject: 'Directive from OmniDesk Administration', default_body: '<h3>Team Update 📢</h3><p>Hello Team,</p><p>Please review the following directive from OmniDesk Administration:</p><div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;padding:16px;margin:16px 0;">Please keep WhatsApp follow-ups active and ensure deal notes are accurately filled.</div>' }
      ];
    }

    select.innerHTML = '';
    emailTemplatesCache.forEach(tpl => {
      const opt = document.createElement('option');
      opt.value = tpl.id;
      opt.textContent = tpl.name;
      select.appendChild(opt);
    });

    applySelectedTemplate();
  }

  function applySelectedTemplate() {
    const templateId = document.getElementById('modalEmailTemplateSelect')?.value;
    const tpl = emailTemplatesCache.find(t => t.id === templateId) || emailTemplatesCache[0];
    if (!tpl) return;

    const subjInput = document.getElementById('modalEmailSubject');
    const badgeInput = document.getElementById('modalEmailBadge');
    const bodyInput = document.getElementById('modalEmailBody');

    if (subjInput) subjInput.value = tpl.subject || '';
    if (badgeInput) badgeInput.value = tpl.badge || 'Notice';
    if (bodyInput) bodyInput.value = tpl.default_body || '';

    updateModalEmailPreview();
  }

  function updateModalEmailPreview() {
    const iframe = document.getElementById('modalEmailPreviewIframe');
    if (!iframe) return;

    const badge = document.getElementById('modalEmailBadge')?.value || 'Official Notice';
    let bodyHtml = document.getElementById('modalEmailBody')?.value || '<p>OmniDesk automated dispatch.</p>';

    // Sample/Selected user values
    const targetType = document.getElementById('modalEmailTargetType')?.value || 'user';
    const userSelect = document.getElementById('modalEmailUserSelect');
    const selectedOpt = userSelect?.options[userSelect.selectedIndex];

    let targetName = 'Team Member';
    let targetUsername = 'closer';
    let targetEmail = 'member@nunno.cloud';
    let targetRole = 'Closer Specialist';

    if (targetType === 'user' && selectedOpt) {
      targetName = selectedOpt.dataset.name || selectedOpt.textContent.split('(')[0].trim();
      targetUsername = selectedOpt.dataset.username || 'closer';
      targetEmail = selectedOpt.dataset.email || 'member@nunno.cloud';
      targetRole = selectedOpt.dataset.role || 'Closer Specialist';
    } else if (targetType === 'custom') {
      targetName = document.getElementById('modalEmailCustomName')?.value || 'Closer Partner';
      targetEmail = document.getElementById('modalEmailCustomAddress')?.value || 'closer@gmail.com';
    } else if (targetType === 'all') {
      targetName = 'All OmniDesk Team Members';
    }

    const todayStr = new Date().toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
    const portalUrl = window.location.origin + window.location.pathname;

    const replacements = {
      '{name}': targetName,
      '{username}': targetUsername,
      '{email}': targetEmail,
      '{role}': targetRole,
      '{amount}': '5,000',
      '{method}': 'EasyPaisa (03001234567)',
      '{date}': todayStr,
      '{portal_url}': portalUrl,
      '{password}': '••••••••'
    };

    let interpolatedBody = bodyHtml;
    for (const [k, v] of Object.entries(replacements)) {
      interpolatedBody = interpolatedBody.split(k).join(v);
    }

    const html = `<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <style>
    body { margin:0; padding:16px; background:#f1f5f9; font-family:-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; color:#0f172a; line-height:1.6; }
    .email-container { max-width:560px; margin:0 auto; background:#ffffff; border-radius:14px; overflow:hidden; border:1px solid #e2e8f0; box-shadow:0 8px 30px rgba(0,0,0,0.06); }
    .email-header { background:linear-gradient(135deg, #0b141a 0%, #111b21 100%); padding:22px 24px; color:#ffffff; }
    .email-badge { display:inline-block; font-size:10px; font-weight:800; text-transform:uppercase; letter-spacing:0.8px; padding:3px 9px; border-radius:999px; background:rgba(0,168,132,0.22); color:#25d366; border:1px solid rgba(0,168,132,0.4); margin-bottom:8px; }
    .email-title { font-size:18px; font-weight:800; margin:0; color:#ffffff; }
    .email-body { padding:24px; font-size:13.5px; color:#334155; }
    .email-body h3 { margin-top:0; color:#0f172a; font-size:16px; }
    .email-body code { background:#f1f5f9; padding:2px 6px; border-radius:4px; font-size:12px; font-family:monospace; color:#0f172a; border:1px solid #e2e8f0; }
    .email-cta-box { text-align:center; margin:24px 0 12px; }
    .email-btn { display:inline-block; background:#00a884; color:#ffffff !important; text-decoration:none; font-weight:700; font-size:13px; padding:11px 24px; border-radius:8px; box-shadow:0 4px 12px rgba(0,168,132,0.3); }
    .email-footer { background:#f8fafc; border-top:1px solid #e2e8f0; padding:16px 24px; font-size:11px; color:#64748b; text-align:center; }
  </style>
</head>
<body>
  <div class="email-container">
    <div class="email-header">
      <div class="email-badge">${escapeHtml(badge)}</div>
      <h2 class="email-title">OmniDesk Communications</h2>
    </div>
    <div class="email-body">
      ${interpolatedBody}
      <div class="email-cta-box">
        <a href="${escapeHtml(portalUrl)}" class="email-btn" target="_blank">Access OmniDesk Portal &rarr;</a>
      </div>
    </div>
    <div class="email-footer">
      Sent securely by OmniDesk Enterprise &bull; 24/7 Verified Closer Operations
    </div>
  </div>
</body>
</html>`;

    iframe.srcdoc = html;
  }

  async function handleSendAdminEmail(e) {
    if (e) e.preventDefault();
    const alertBox = document.getElementById('modalEmailAlert');
    const sendBtn = document.getElementById('modalEmailSendBtn');

    const showAlert = (msg, isSuccess = false) => {
      if (alertBox) {
        alertBox.textContent = msg;
        alertBox.className = 'auth-alert ' + (isSuccess ? 'success' : '');
        alertBox.style.display = 'block';
      }
    };

    const targetType = document.getElementById('modalEmailTargetType')?.value || 'user';
    const targetUserId = document.getElementById('modalEmailUserSelect')?.value || '';
    const customEmail = document.getElementById('modalEmailCustomAddress')?.value?.trim() || '';
    const customName = document.getElementById('modalEmailCustomName')?.value?.trim() || 'Valued Recipient';
    const templateId = document.getElementById('modalEmailTemplateSelect')?.value || 'custom';
    const subject = document.getElementById('modalEmailSubject')?.value?.trim() || '';
    const badge = document.getElementById('modalEmailBadge')?.value?.trim() || 'Notice';
    const bodyHtml = document.getElementById('modalEmailBody')?.value?.trim() || '';

    if (!subject) { showAlert('Please enter an email subject.'); return; }
    if (!bodyHtml) { showAlert('Please enter email message content.'); return; }
    if (targetType === 'custom' && !customEmail) { showAlert('Please enter a target recipient email address.'); return; }

    if (sendBtn) {
      sendBtn.disabled = true;
      sendBtn.innerHTML = '<span>⏳ Dispatching Email...</span>';
    }

    try {
      const res = await fetch('api/email_service.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          action: 'send_email',
          target_type: targetType,
          target_user_id: targetUserId,
          target_custom_email: customEmail,
          target_custom_name: customName,
          template_id: templateId,
          subject: subject,
          badge: badge,
          message_html: bodyHtml
        })
      });

      const data = await res.json();
      if (data && data.success) {
        showAlert(`✅ ${data.message || 'Email successfully dispatched!'}`, true);
        showToast(`✉️ Dispatched ${data.dispatched_count || 1} email(s) successfully!`);
      } else {
        showAlert(data.error || 'Failed to dispatch email.');
      }
    } catch (err) {
      showAlert('✅ Email recorded to dispatch audit trail (local simulation).', true);
      showToast('✉️ Email dispatched to queue.');
    } finally {
      if (sendBtn) {
        sendBtn.disabled = false;
        sendBtn.innerHTML = '<span>✉️ Dispatch Email Now</span> <span>&rarr;</span>';
      }
    }
  }

  async function loadAdminEmailLogs() {
    const tbody = document.getElementById('modalEmailLogsTbody');
    if (!tbody) return;
    tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;padding:18px;color:var(--text-muted);">Fetching audit logs...</td></tr>';

    try {
      const res = await fetch('api/email_service.php?action=get_logs');
      const data = await res.json();
      if (data && data.success && Array.isArray(data.logs) && data.logs.length) {
        tbody.innerHTML = '';
        data.logs.forEach(log => {
          const tr = document.createElement('tr');
          tr.style.borderBottom = '1px solid var(--border-subtle)';
          tr.innerHTML = `
            <td style="padding:8px 12px;font-family:monospace;font-size:11px;">${escapeHtml(log.id || '-')}</td>
            <td style="padding:8px 12px;font-weight:600;">${escapeHtml(log.recipient || '-')}</td>
            <td style="padding:8px 12px;max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">${escapeHtml(log.subject || '-')}</td>
            <td style="padding:8px 12px;"><span class="badge-role-lead" style="font-size:10px;">${escapeHtml(log.template || 'Custom')}</span></td>
            <td style="padding:8px 12px;font-size:11px;color:var(--text-muted);">${escapeHtml(log.dispatched_at ? new Date(log.dispatched_at).toLocaleString() : '-')}</td>
            <td style="padding:8px 12px;"><span style="color:var(--accent-brand);font-weight:700;">🟢 Dispatched</span></td>
          `;
          tbody.appendChild(tr);
        });
        return;
      }
    } catch (_) {}

    tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;padding:24px;color:var(--text-muted);">No dispatched emails logged yet.</td></tr>';
  }

  async function loadAdminEmailSettings() {
    try {
      const res = await fetch('api/email_service.php?action=get_settings');
      const data = await res.json();
      if (data && data.success && data.settings) {
        const s = data.settings;
        const setVal = (id, val) => { const el = document.getElementById(id); if (el) el.value = val || ''; };
        setVal('modalEmailTransport', s.transport);
        setVal('modalEmailSenderName', s.sender_name);
        setVal('modalEmailSenderEmail', s.sender_email);
        setVal('modalEmailReplyTo', s.reply_to);
        setVal('modalEmailSmtpHost', s.smtp_host);
        setVal('modalEmailSmtpPort', s.smtp_port);
        setVal('modalEmailSmtpSecure', s.smtp_secure);
        setVal('modalEmailSmtpUser', s.smtp_user);
        setVal('modalEmailPortalUrl', s.portal_url);
      }
    } catch (_) {}
  }

  async function handleSaveAdminEmailSettings(e) {
    if (e) e.preventDefault();
    const alertBox = document.getElementById('modalEmailSettingsAlert');
    const getVal = id => document.getElementById(id)?.value?.trim() || '';

    const payload = {
      action: 'save_settings',
      transport: getVal('modalEmailTransport'),
      sender_name: getVal('modalEmailSenderName'),
      sender_email: getVal('modalEmailSenderEmail'),
      reply_to: getVal('modalEmailReplyTo'),
      smtp_host: getVal('modalEmailSmtpHost'),
      smtp_port: parseInt(getVal('modalEmailSmtpPort'), 10) || 465,
      smtp_secure: getVal('modalEmailSmtpSecure'),
      smtp_user: getVal('modalEmailSmtpUser'),
      smtp_pass: getVal('modalEmailSmtpPass'),
      portal_url: getVal('modalEmailPortalUrl')
    };

    try {
      const res = await fetch('api/email_service.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      });
      const data = await res.json();
      if (data && data.success) {
        if (alertBox) {
          alertBox.textContent = '✅ Mailer settings successfully updated.';
          alertBox.className = 'auth-alert success';
          alertBox.style.display = 'block';
        }
        showToast('Mailer settings updated.');
      }
    } catch (_) {}
  }

  window.OmnideskQuickEmail = function(empId) {
    if (els.employeeModal) els.employeeModal.classList.remove('open');
    openAdminEmailModal(empId);
  };

  // Wire Admin Email Studio listeners
  document.getElementById('openEmailModalBtn')?.addEventListener('click', () => openAdminEmailModal());
  document.getElementById('closeEmailModalBtn')?.addEventListener('click', closeAdminEmailModal);
  document.getElementById('tabEmailComposeBtn')?.addEventListener('click', () => switchAdminEmailTab('compose'));
  document.getElementById('tabEmailLogsBtn')?.addEventListener('click', () => switchAdminEmailTab('logs'));
  document.getElementById('tabEmailSettingsBtn')?.addEventListener('click', () => switchAdminEmailTab('settings'));
  document.getElementById('modalEmailForm')?.addEventListener('submit', handleSendAdminEmail);
  document.getElementById('modalEmailSendBtn')?.addEventListener('click', handleSendAdminEmail);
  document.getElementById('modalRefreshEmailLogsBtn')?.addEventListener('click', loadAdminEmailLogs);
  document.getElementById('modalEmailSettingsForm')?.addEventListener('submit', handleSaveAdminEmailSettings);
  document.getElementById('modalEmailSaveSettingsBtn')?.addEventListener('click', handleSaveAdminEmailSettings);
  document.getElementById('modalEmailTemplateSelect')?.addEventListener('change', applySelectedTemplate);
  document.getElementById('modalEmailTargetType')?.addEventListener('change', handleEmailTargetTypeChange);
  document.getElementById('modalEmailUserSelect')?.addEventListener('change', updateModalEmailPreview);
  document.getElementById('modalEmailCustomName')?.addEventListener('input', updateModalEmailPreview);
  document.getElementById('modalEmailCustomAddress')?.addEventListener('input', updateModalEmailPreview);
  ['modalEmailSubject', 'modalEmailBadge', 'modalEmailBody'].forEach(id => {
    document.getElementById(id)?.addEventListener('input', updateModalEmailPreview);
  });

  document.querySelectorAll('#modalEmailChips .btn-amt-chip').forEach(chip => {
    chip.addEventListener('click', () => {
      const variable = chip.dataset.var;
      const bodyInput = document.getElementById('modalEmailBody');
      if (bodyInput && variable) {
        const start = bodyInput.selectionStart || 0;
        const end = bodyInput.selectionEnd || 0;
        const text = bodyInput.value;
        bodyInput.value = text.substring(0, start) + variable + text.substring(end);
        bodyInput.focus();
        bodyInput.selectionStart = bodyInput.selectionEnd = start + variable.length;
        updateModalEmailPreview();
      }
    });
  });

  // ==========================================
  // MODAL 5: MARK PAID (DEAL WON POPUP)
  // ==========================================

  function openMarkPaidModal(lead) {
    if (!lead) return;
    const currentUserId = state.currentUser ? state.currentUser.id : state.currentAgent.id;
    const isAdmin = state.currentUser && state.currentUser.role === 'admin';
    const isOwner = lead.ownerId === currentUserId;

    if (!isOwner && !isAdmin) {
      showToast('⚠️ Please claim and take this lead first before recording a sale or submitting for review!');
      return;
    }

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

    const currentUserId = state.currentUser ? state.currentUser.id : state.currentAgent.id;
    const isAdmin = state.currentUser && state.currentUser.role === 'admin';
    const isOwner = lead.ownerId === currentUserId;

    if (!isOwner && !isAdmin) {
      showToast('⚠️ Action blocked: You must take and claim this lead first before recording a sale.');
      return;
    }

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
    if (state.currentUser && state.currentUser.gender === 'male') {
      showToast('⚠️ Voice note recording is restricted for male closers.', 4000);
      return;
    }

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
      // High-Fidelity Audio Constraints: Mono, 48kHz (native WhatsApp Opus standard), full dynamic range
      const audioConstraints = {
        channelCount: { ideal: 1 },
        sampleRate: { ideal: 48000 },
        sampleSize: { ideal: 16 },
        echoCancellation: { ideal: true },
        noiseSuppression: { ideal: false }, // Avoid aggressive clipping/muffling so voice stays natural, warm, and crisp
        autoGainControl: { ideal: true }
      };

      try {
        mediaStream = await navigator.mediaDevices.getUserMedia({ audio: audioConstraints });
      } catch (_) {
        mediaStream = await navigator.mediaDevices.getUserMedia({ audio: true });
      }

      recordingLeadId = lead.id;
      audioChunks = [];
      recordingSeconds = 0;

      const mimeTypes = ['audio/ogg;codecs=opus', 'audio/ogg', 'audio/webm;codecs=opus', 'audio/webm', 'audio/mp4'];
      let selectedMime = '';
      for (const m of mimeTypes) {
        if (typeof MediaRecorder !== 'undefined' && MediaRecorder.isTypeSupported && MediaRecorder.isTypeSupported(m)) {
          selectedMime = m;
          break;
        }
      }

      // Studio-grade 128 kbps Opus bitrate (eliminates robotic / muffled 16-32 kbps default)
      const recorderOptions = {
        audioBitsPerSecond: 128000
      };
      if (selectedMime) recorderOptions.mimeType = selectedMime;

      try {
        mediaRecorder = new MediaRecorder(mediaStream, recorderOptions);
      } catch (err) {
        try {
          mediaRecorder = selectedMime
            ? new MediaRecorder(mediaStream, { mimeType: selectedMime, audioBitsPerSecond: 64000 })
            : new MediaRecorder(mediaStream);
        } catch (_) {
          mediaRecorder = new MediaRecorder(mediaStream);
        }
      }

      mediaRecorder.ondataavailable = (e) => {
        if (e.data && e.data.size > 0) {
          audioChunks.push(e.data);
        }
      };

      // 1000ms chunk interval allows Opus encoder full-frame lookahead and continuous VBR optimization
      mediaRecorder.start(1000);
      sendOutboundPresence('recording');

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
    sendOutboundPresence('paused');

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
      sendOutboundPresence('paused');

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

  // Simulate Incoming Customer Audio Note (with live recording animation preview)
  function simulateClientAudioMessage(leadId) {
    const lead = getLead(leadId || state.activeLeadId);
    if (!lead) {
      showToast('⚠️ Please select a conversation first.');
      return;
    }

    const norm = normalizePhone(lead.phone);
    // 1. Show live incoming recording animation first (header + message bubble + list preview)
    contactPresences.set(norm, { state: 'recording', timestamp: Date.now() });
    updateActiveChatPresenceUI();
    updateConversationsPresenceUI(norm);
    showToast(`🎙️ ${lead.name} is recording a voice note…`, 2200);

    // 2. Deliver audio after short recording interval
    setTimeout(() => {
      contactPresences.delete(norm);
      updateActiveChatPresenceUI();
      updateConversationsPresenceUI(norm);

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
    }, 2400);
  }

  // Simulate Incoming Customer Typing Animation
  function simulateClientTyping(leadId, durationMs = 3000) {
    const lead = getLead(leadId || state.activeLeadId);
    if (!lead) {
      showToast('⚠️ Please select a conversation first.');
      return;
    }
    const norm = normalizePhone(lead.phone);
    contactPresences.set(norm, { state: 'typing', timestamp: Date.now() });
    updateActiveChatPresenceUI();
    updateConversationsPresenceUI(norm);
    showToast(`✍️ ${lead.name} is typing…`, 2500);

    setTimeout(() => {
      contactPresences.delete(norm);
      updateActiveChatPresenceUI();
      updateConversationsPresenceUI(norm);
    }, durationMs);
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

  if (els.myOngoingsBtn) {
    els.myOngoingsBtn.addEventListener('click', () => {
      state.inboxFilter = (state.inboxFilter === 'my_ongoing') ? 'all' : 'my_ongoing';
      renderConversationsList();
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
      const curDisplayName = getContactDisplayName(lead);
      const isCustom = curDisplayName && curDisplayName !== lead.phone && curDisplayName !== lead.display && !curDisplayName.startsWith('+');
      const currentPromptVal = isCustom ? curDisplayName : '';
      const newName = prompt(`Set custom name for ${lead.display || ('+' + lead.phone)}:`, currentPromptVal);
      if (newName === null) return;
      const trimmed = newName.trim();
      lead.name = trimmed || (lead.display || ('+' + lead.phone));
      renderActiveChat();
      renderConversationsList();
      try {
        await fetch('api/leads_sync.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ action: 'update_lead', id: lead.id, phone: lead.phone, name: lead.name })
        });
        showToast(`✅ Contact name saved as "${lead.name}"`);
      } catch (_) {}
    });
  }

  // Admin Batch Deletion Listeners
  if (els.adminDeleteToggleBtn) {
    els.adminDeleteToggleBtn.addEventListener('click', () => {
      toggleAdminDeleteMode();
    });
  }
  if (els.batchSelectAllCheckbox) {
    els.batchSelectAllCheckbox.addEventListener('change', (e) => {
      const isChecked = e.target.checked;
      if (isChecked) {
        state.leads.forEach(l => selectedLeadIds.add(l.id));
      } else {
        selectedLeadIds.clear();
      }
      updateBatchBarCount();
      renderConversationsList();
    });
  }
  if (els.batchDeleteBtn) {
    els.batchDeleteBtn.addEventListener('click', () => {
      if (!selectedLeadIds.size) {
        showToast('⚠️ Please select at least one chat to delete.');
        return;
      }
      if (!confirm(`Permanently delete ${selectedLeadIds.size} selected conversation(s)? All message history will be removed and server storage will be freed.`)) {
        return;
      }
      deleteLeads(Array.from(selectedLeadIds));
    });
  }
  if (els.batchCancelBtn) {
    els.batchCancelBtn.addEventListener('click', () => {
      toggleAdminDeleteMode();
    });
  }

  // Anti-Theft Locked Shield Action Buttons
  document.getElementById('lockedBackToMineBtn')?.addEventListener('click', () => {
    const mineBtn = document.getElementById('kpiFilterMine');
    if (mineBtn) {
      mineBtn.click();
    } else {
      switchTab('employee');
    }
  });

  document.getElementById('lockedBrowsePoolBtn')?.addEventListener('click', () => {
    document.querySelector('.inbox-filter-btn[data-filter="fresh"]')?.click();
  });

  // Stats Page Navigation (Full Dedicated Page viewEmployee)
  if (els.openScoreBtn) els.openScoreBtn.addEventListener('click', () => switchTab('employee'));
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
  window.simulateClientTyping = simulateClientTyping;

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


  if (els.toast) {
    els.toast.addEventListener('click', () => {
      els.toast.classList.remove('show');
      if (toastDismissTimer) {
        clearTimeout(toastDismissTimer);
        toastDismissTimer = null;
      }
    });
  }

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
  syncServerCampaignStatus();
  startCampaignStatusPolling();
});
