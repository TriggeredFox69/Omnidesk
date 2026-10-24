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
      id: 'lead_1',
      name: 'Zeeshan Tariq',
      phone: '923008472910',
      display: '+92 300 8472910',
      city: 'Lahore',
      network: 'Jazz',
      ownerId: 'EMP-101', // Owned by Hamza
      leadState: 'ongoing', // YELLOW: Ongoing communication
      status: 'replied',
      unreadCount: 0,
      interest: 'Nunno Pro',
      lastActive: '2 mins ago',
      notes: 'Interested in Pro plan. Asked for EasyPaisa account details. Follow up today.',
      messages: [
        { sender: 'agent', agentName: 'Hamza Khan', text: 'Salam Zeeshan Bhai! Nunno AI Cloud ne Pakistan ke crypto traders ke liye Liquidation Magnet System launch kiya hai jo exact level pehle dikhata hai jahan whales target kar rahi hain. 30% OFF offer available hai!', time: '10:15 AM' },
        { sender: 'lead', text: 'Assalam o alaikum bhai, EasyPaisa se payment ho sakti hai? Aur breakout alert kitne symbols pe aata hai?', time: '10:32 AM' },
        { sender: 'agent', agentName: 'Hamza Khan', text: 'Ji bilkul Zeeshan Bhai! EasyPaisa, JazzCash aur USDT teenon supported hain. Pro plan mein 12-layer multi-symbol alerts live aate hain.', time: '10:35 AM' },
        { sender: 'lead', text: 'Theek hai, mujhe EasyPaisa account number share kar dein, main abhi activate karwana chahta hoon.', time: '10:40 AM' },
        { id: 'msg_lead1_vn1', sender: 'lead', type: 'audio', duration: '0:24', durationSeconds: 24, text: '🎙️ Voice note (0:24)', time: '10:41 AM' },
        { id: 'msg_lead1_vn2', sender: 'agent', agentName: 'Hamza Khan', type: 'audio', duration: '0:18', durationSeconds: 18, text: '🎙️ Voice note (0:18)', time: '10:42 AM' }
      ]
    },
    {
      id: 'lead_2',
      name: 'Bilal Ahmed',
      phone: '923214567890',
      display: '+92 321 4567890',
      city: 'Karachi',
      network: 'Jazz',
      ownerId: null, // RED: Fresh Lead (Unclaimed)!
      leadState: 'fresh',
      status: 'unread',
      unreadCount: 2,
      interest: 'Nunno Pro',
      lastActive: '14 mins ago',
      notes: 'High intent retail trader. Scalping Binance futures.',
      messages: [
        { sender: 'agent', agentName: 'System Outreach', text: '⚡ FLASH 48-HOUR TRADER PASS: Bilal Bhai, Nunno Pro pe flat 40% discount chal raha hai. EasyPaisa / JazzCash instant accepted: nunno.cloud', time: '09:00 AM' },
        { sender: 'lead', text: 'AoA bhai, kya isme Gold ($XAUUSD) ke liquidation wicks bhi detect hote hain?', time: '09:45 AM' },
        { sender: 'lead', text: 'Mujhe demo video ya coupon code send karein.', time: '09:46 AM' },
        { id: 'msg_lead2_vn1', sender: 'lead', type: 'audio', duration: '0:35', durationSeconds: 35, text: '🎙️ Voice note (0:35)', time: '09:48 AM' }
      ]
    },
    {
      id: 'lead_3',
      name: 'Usman Ali',
      phone: '923335123984',
      display: '+92 333 5123984',
      city: 'Islamabad',
      network: 'Ufone',
      ownerId: 'EMP-102', // YELLOW: Owned by Bilal Tariq
      leadState: 'ongoing',
      status: 'sent',
      unreadCount: 0,
      interest: 'Nunno Starter',
      lastActive: '1 hour ago',
      notes: 'Outreach sent. Awaiting response.',
      messages: [
        { sender: 'agent', agentName: 'Bilal Tariq', text: 'Hello Usman! Are sudden market manipulation wicks hurting your positions? Check Nunno Cloud AI indicator: nunno.cloud', time: '08:30 AM' }
      ]
    },
    {
      id: 'lead_4',
      name: 'Kashif Mehmood',
      phone: '923129876543',
      display: '+92 312 9876543',
      city: 'Rawalpindi',
      network: 'Zong',
      ownerId: 'EMP-101', // GREEN: Closed - Client Paid!
      leadState: 'paid',
      status: 'paid',
      verificationStatus: 'verified',
      verifiedBy: 'System Administrator',
      paidDetails: {
        plan: 'Nunno Starter',
        amount: 1700,
        method: 'JazzCash',
        trxId: 'JC-88391024',
        email: 'kashif.m@gmail.com',
        closedBy: 'Hamza Khan',
        closedById: 'EMP-101',
        closedAt: '2026-10-23T14:30:00Z'
      },
      unreadCount: 0,
      interest: 'Nunno Starter (Paid Rs. 1,700)',
      lastActive: 'Yesterday',
      notes: 'PAID Rs. 1,700 via JazzCash. Activated Starter account.',
      messages: [
        { sender: 'agent', agentName: 'Hamza Khan', text: 'AoA Kashif! Nunno Starter sirf Rs. 1,700/mo mein live signals provide karta hai.', time: 'Yesterday' },
        { sender: 'lead', text: 'Maine PKR 1,700 JazzCash kar diye hain! Mera login access activate kar dein.', time: 'Yesterday' },
        { sender: 'agent', agentName: 'Hamza Khan', text: 'Payment verified Kashif Bhai! Aapka account activate ho gaya hai. Welcome to Nunno VIP Alpha!', time: 'Yesterday' }
      ]
    },
    {
      id: 'lead_5',
      name: 'Hamza Sheikh',
      phone: '923451234567',
      display: '+92 345 1234567',
      city: 'Faisalabad',
      network: 'Telenor',
      ownerId: null, // RED: Fresh Lead (Unclaimed)!
      leadState: 'fresh',
      status: 'unread',
      unreadCount: 1,
      interest: 'Nunno Whale VIP',
      lastActive: '3 hours ago',
      notes: 'Big portfolio trader. Looking for private whale signals.',
      messages: [
        { sender: 'agent', agentName: 'System Outreach', text: 'Salam Hamza! Institutional order flow alpha is now live on Nunno Whale Suite.', time: 'Yesterday' },
        { sender: 'lead', text: 'Whale tier ka yearly price kya hai? Kya 1-on-1 trading architect support milega?', time: '06:15 PM' },
        { id: 'msg_lead5_vn1', sender: 'lead', type: 'audio', duration: '0:42', durationSeconds: 42, text: '🎙️ Voice note (0:42)', time: '06:17 PM' }
      ]
    },
    {
      id: 'lead_6',
      name: 'Waqas Rasheed',
      phone: '923027891234',
      display: '+92 302 7891234',
      city: 'Multan',
      network: 'Jazz',
      ownerId: 'EMP-103', // YELLOW: Owned by Ayesha
      leadState: 'ongoing',
      status: 'replied',
      unreadCount: 0,
      interest: 'Nunno Pro',
      lastActive: '4 hours ago',
      notes: 'Requested discount coupon NUNNO30.',
      messages: [
        { sender: 'agent', agentName: 'Ayesha Noor', text: 'Salam Waqas Bhai! Nunno Pro pe 30% OFF coupon `{coupon|NUNNO30}` available hai.', time: '03:10 PM' },
        { sender: 'lead', text: 'Theek hai main shaam ko office se nikal kar subscription le leta hoon.', time: '04:00 PM' }
      ]
    },
    {
      id: 'lead_7',
      name: 'Omer Farooq',
      phone: '923226789012',
      display: '+92 322 6789012',
      city: 'Sialkot',
      network: 'Jazz',
      ownerId: 'EMP-101', // Owned by Hamza
      leadState: 'useless', // BLUE: Useless / Won't Pay!
      status: 'useless',
      unreadCount: 0,
      interest: 'Nunno Pro',
      lastActive: '5 hours ago',
      notes: 'Demanded free indicators only. Explicitly refused payment. Marked useless / dead lead.',
      messages: [
        { sender: 'agent', agentName: 'Hamza Khan', text: 'Salam Omer Bhai! Nunno Pro 30% discount offer link yahan hai: nunno.cloud', time: '11:00 AM' },
        { sender: 'lead', text: 'Maine pay nahi karna, free trial do warna mat message karo.', time: '11:15 AM' },
        { sender: 'agent', agentName: 'Hamza Khan', text: '🚫 CLIENT MARKED USELESS: Handled by agent, but client will not pay / uninterested.', time: '11:16 AM' }
      ]
    },
    {
      id: 'lead_8',
      name: 'Rizwan Qureshi',
      phone: '923348901234',
      display: '+92 334 8901234',
      city: 'Peshawar',
      network: 'Ufone',
      ownerId: null, // RED: Fresh Lead (Unclaimed)!
      leadState: 'fresh',
      status: 'sent',
      unreadCount: 0,
      interest: 'Nunno Pro',
      lastActive: '6 hours ago',
      notes: 'Cold outreach sent.',
      messages: [
        { sender: 'agent', agentName: 'System Outreach', text: 'Salam Rizwan! Nunno Cloud Liquidation Radar live access at nunno.cloud', time: '01:00 PM' }
      ]
    },
    {
      id: 'lead_9',
      name: 'Farhan Saeed',
      phone: '923015556677',
      display: '+92 301 5556677',
      city: 'Islamabad',
      network: 'Jazz',
      ownerId: 'EMP-102',
      leadState: 'pending_verification',
      status: 'pending_verification',
      verificationStatus: 'pending',
      unreadCount: 0,
      interest: 'Nunno Pro',
      lastActive: '10 mins ago',
      notes: 'Paid Rs. 5,000 via EasyPaisa. Trx Ref: EP-984729104. Awaiting Admin verification.',
      paidDetails: {
        plan: 'Nunno Pro',
        amount: 5000,
        method: 'EasyPaisa',
        trxId: 'EP-984729104',
        email: 'farhan.crypto@gmail.com',
        closedBy: 'Bilal Tariq',
        closedById: 'EMP-102',
        closedAt: new Date(Date.now() - 3600000).toISOString()
      },
      messages: [
        { sender: 'agent', agentName: 'Bilal Tariq', text: 'Salam Farhan Bhai! Nunno Pro subscription details share kar di hain.', time: '05:40 PM' },
        { sender: 'lead', text: 'Maine PKR 5,000 EasyPaisa kar diye hain. Trx TID: EP-984729104. Account email: farhan.crypto@gmail.com', time: '06:10 PM' },
        { sender: 'agent', agentName: 'Bilal Tariq', text: '⚪ PAYMENT SUBMITTED (PENDING ADMIN VERIFICATION)\n• Plan: Nunno Pro (Rs. 5,000)\n• Method: EasyPaisa\n• Trx Ref: EP-984729104\n• Account Email: farhan.crypto@gmail.com\n⏳ Awaiting Admin verification in the Admin Portal before commission & green status unlock.', time: '06:12 PM' }
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
    leads: [...initialLeads],
    activeLeadId: 'lead_1',
    inboxFilter: 'all', // 'all', 'unread', 'replied', 'sent', 'mine', 'unclaimed'
    searchQuery: '',

    // Direct Connected WhatsApp Sender (Direct Device Mode — Zero Meta API)
    connectedWaNumber: localStorage.getItem('nunno_connected_wa_number') || '',

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
    viewInbox: document.getElementById('viewInbox'),
    viewCampaign: document.getElementById('viewCampaign'),

    // Topbar Badges & Agent Pill
    agentPill: document.getElementById('agentPill'),
    topAgentAvatar: document.getElementById('topAgentAvatar'),
    topAgentName: document.getElementById('topAgentName'),
    topAgentId: document.getElementById('topAgentId'),
    unreadGlobalBadge: document.getElementById('unreadGlobalBadge'),
    myLeadsCountBadge: document.getElementById('myLeadsCountBadge'),
    openTeamModalBtn: document.getElementById('openTeamModalBtn'),
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
    connectWaModal: document.getElementById('connectWaModal'),
    closeConnectWaModalBtn: document.getElementById('closeConnectWaModalBtn'),
    cancelConnectWaBtn: document.getElementById('cancelConnectWaBtn'),
    saveConnectedWaBtn: document.getElementById('saveConnectedWaBtn'),
    inputConnectedWaNumber: document.getElementById('inputConnectedWaNumber'),

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
    launchNativeWaBtn: document.getElementById('launchNativeWaBtn'),
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
    openLeaderboardBtn: document.getElementById('openLeaderboardBtn'),

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
    tabTeamScores: document.getElementById('tabTeamScores'),
    tabVerifySales: document.getElementById('tabVerifySales'),
    viewMyScoreTab: document.getElementById('viewMyScoreTab'),
    viewTeamScoresTab: document.getElementById('viewTeamScoresTab'),
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
    teamLeaderboardTable: document.getElementById('teamLeaderboardTable'),
    exportLeaderboardBtn: document.getElementById('exportLeaderboardBtn'),

    // Toast
    toast: document.getElementById('toast'),
    toastMsg: document.getElementById('toastMsg')
  };

  // Toast Function
  function showToast(msg, duration = 3000) {
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

  // Update Topbar Agent Display
  function renderAgentProfile() {
    const user = state.currentUser;
    if (user) {
      const isAdmin = user.role === 'admin';
      if (els.topAgentAvatar) els.topAgentAvatar.textContent = isAdmin ? '👑' : '👨‍💼';
      if (els.topAgentName) els.topAgentName.textContent = user.name;
      if (els.topAgentId) {
        els.topAgentId.innerHTML = isAdmin 
          ? '<span class="badge-admin-crown">👑 Admin</span>' 
          : `<span class="badge-employee-pill">💼 ${escapeHtml(user.designation || 'Closer')}</span>`;
      }
      if (els.openTeamModalBtn) {
        els.openTeamModalBtn.style.display = isAdmin ? 'inline-flex' : 'none';
      }
      if (els.openLeaderboardBtn) {
        els.openLeaderboardBtn.style.display = isAdmin ? 'inline-flex' : 'none';
      }
    } else {
      if (els.topAgentAvatar) els.topAgentAvatar.textContent = state.currentAgent.avatar;
      if (els.topAgentName) els.topAgentName.textContent = state.currentAgent.name;
      if (els.topAgentId) els.topAgentId.textContent = `${state.currentAgent.id} • ${state.currentAgent.role}`;
      if (els.openTeamModalBtn) els.openTeamModalBtn.style.display = 'none';
      if (els.openLeaderboardBtn) els.openLeaderboardBtn.style.display = 'none';
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
    if (state.connectedWaNumber) {
      els.connectedNumberText.textContent = state.connectedWaNumber;
      els.connectedNumberText.style.color = 'var(--c-wa)';
    } else {
      els.connectedNumberText.textContent = 'Pending Setup (Click to Set)';
      els.connectedNumberText.style.color = '#94a3b8';
    }
  }

  function openConnectWaModal() {
    if (els.inputConnectedWaNumber) {
      els.inputConnectedWaNumber.value = state.connectedWaNumber || '';
    }
    if (els.connectWaModal) els.connectWaModal.classList.add('open');
  }

  function closeConnectWaModal() {
    if (els.connectWaModal) els.connectWaModal.classList.remove('open');
  }

  function saveConnectedWaNumber() {
    const val = els.inputConnectedWaNumber ? els.inputConnectedWaNumber.value.trim() : '';
    if (!val) {
      state.connectedWaNumber = '';
      localStorage.removeItem('nunno_connected_wa_number');
      renderConnectedNumber();
      closeConnectWaModal();
      showToast('Connected WhatsApp number cleared. You can provide it anytime.');
      return;
    }

    state.connectedWaNumber = val;
    localStorage.setItem('nunno_connected_wa_number', val);
    renderConnectedNumber();
    closeConnectWaModal();
    showToast(`✅ Direct WhatsApp sender set to: ${val}`);

    // Sync with backend direct_sender.php
    fetch('api/direct_sender.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'set_sender_number', phone_number: val })
    }).catch(() => {});
  }

  // ==========================================
  // ==========================================
  // LEAD LOCKING, OWNERSHIP & PAYMENT ENGINE
  // ==========================================

  // Centralized Server Sync (No Scattered Data across employees on cPanel)
  function syncLeadToServer(lead, extraData = {}) {
    if (!lead) return;
    const payload = {
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
    fetch('api/leads_sync.php?action=get_leads')
      .then(res => res.json())
      .then(data => {
        if (data && data.success && Array.isArray(data.leads) && data.leads.length > 0) {
          state.leads = data.leads;
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
            <span class="text-xs text-muted" style="${lastMsg.type === 'audio' ? 'color:#53bdeb;display:inline-flex;align-items:center;gap:4px;' : ''}">${lastMsg.type === 'audio' ? `<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"/><path d="M19 10v2a7 7 0 0 1-14 0v-2"/></svg> Voice note (${lastMsg.duration || '0:20'})` : escapeHtml(lastMsg.text)}</span>
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

    renderConversationsList();
    renderActiveChat();
    renderAgentProfile();
  }

  // ==========================================
  // ACTIVE CHAT & LEAD DOSSIER
  // ==========================================

  function renderActiveChat() {
    const lead = getLead(state.activeLeadId);
    if (!lead) return;

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
        els.chatInput.placeholder = 'Type WhatsApp message... (Press Enter to send)';
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
              ${!isInbound ? `<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#53bdeb" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/><polyline points="20 12 14.5 17.5"/></svg>` : ''}
            </div>
          `;
        } else {
          bubble.innerHTML = `
            ${!isInbound ? `<div class="msg-sender-tag">${escapeHtml(msg.agentName || 'Agent')}</div>` : ''}
            <div>${formatText(msg.text)}</div>
            <div class="msg-meta-row">
              <span>${escapeHtml(msg.time)}</span>
              ${!isInbound ? `<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#53bdeb" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/><polyline points="20 12 14.5 17.5"/></svg>` : ''}
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

      els.chatMessagesArea.scrollTop = els.chatMessagesArea.scrollHeight;
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

    // Direct WhatsApp Web Launch Button
    if (els.launchNativeWaBtn) {
      els.launchNativeWaBtn.onclick = () => {
        const text = els.chatInput ? els.chatInput.value.trim() : '';
        const encoded = encodeURIComponent(text);
        const url = `https://web.whatsapp.com/send?phone=${lead.phone}&text=${encoded}`;
        window.open(url, '_blank');
        showToast(`🚀 Opening direct WhatsApp chat with ${lead.name}`);
      };
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
  function sendMessage() {
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

    // If unclaimed, automatically claim when agent responds
    if (!lead.ownerId) {
      lead.ownerId = myId;
      lead.ownerName = myName;
      lead.leadState = 'ongoing';
      showToast(`🎯 You responded first! Lead automatically locked to you.`);
    }

    const now = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });

    lead.messages.push({
      sender: 'agent',
      agentName: myName,
      text: text,
      time: now
    });

    lead.status = 'replied';
    lead.lastActive = 'Just now';

    els.chatInput.value = '';
    renderConversationsList();
    renderActiveChat();
    syncLeadToServer(lead, { newMessage: lead.messages[lead.messages.length - 1] });
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
  // MANUAL .TXT UPLOADER / PARSER
  // ==========================================

  function openImportModal() {
    if (els.importTxtModal) els.importTxtModal.classList.add('open');
  }

  function closeImportModal() {
    if (els.importTxtModal) els.importTxtModal.classList.remove('open');
  }

  if (els.openImportModalBtn) els.openImportModalBtn.addEventListener('click', openImportModal);
  if (els.closeImportModalBtn) els.closeImportModalBtn.addEventListener('click', closeImportModal);

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

  function handleImport() {
    const text = els.txtPasteArea.value.trim();
    if (!text) {
      showToast('⚠️ Please paste numbers or select a .txt file.');
      return;
    }

    const lines = text.split(/[\r\n]+/);
    const assignMode = els.importAssignMode.value; // 'pool', 'mine', 'distribute'
    let addedCount = 0;

    lines.forEach((line, idx) => {
      line = line.trim();
      if (!line) return;
      const parts = line.split(/[,\t;|]+/);
      const rawPhone = parts[0] ? parts[0].trim() : '';
      const name = parts[1] ? parts[1].trim() : 'Trader';
      const city = parts[2] ? parts[2].trim() : 'Pakistan';

      const clean = cleanNumber(rawPhone);
      if (!clean) return;

      // Check if already in leads
      if (state.leads.some(l => l.phone === clean)) return;

      let ownerId = null;
      if (assignMode === 'mine') {
        ownerId = state.currentAgent.id;
      } else if (assignMode === 'distribute') {
        const agentIdx = idx % state.agents.length;
        ownerId = state.agents[agentIdx].id;
      }

      state.leads.push({
        id: `lead_${Date.now()}_${idx}`,
        name: name,
        phone: clean,
        display: `+92 ${clean.substring(2, 5)} ${clean.substring(5)}`,
        city: city,
        network: getNetwork(clean),
        ownerId: ownerId,
        status: 'unread',
        unreadCount: 1,
        interest: 'Nunno Pro',
        lastActive: 'Just imported',
        notes: `Imported via .txt file on ${new Date().toLocaleDateString()}.`,
        messages: [
          { sender: 'lead', text: 'Lead imported from list. Ready for outreach.', time: 'Just now' }
        ]
      });
      addedCount++;
    });

    closeImportModal();
    els.txtPasteArea.value = '';
    renderConversationsList();
    renderAgentProfile();
    showToast(`✅ Successfully imported ${addedCount} valid Pakistani numbers!`);

    // Sync imported leads to cPanel server (prevents data scatter)
    fetch('api/leads_sync.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'import_leads', leads: state.leads })
    }).catch(() => {});
  }

  if (els.confirmImportBtn) els.confirmImportBtn.addEventListener('click', handleImport);

  // File reader for .txt file input
  if (els.txtFileInput) {
    els.txtFileInput.addEventListener('change', (e) => {
      if (e.target.files.length) {
        const reader = new FileReader();
        reader.onload = (ev) => {
          if (els.txtPasteArea) els.txtPasteArea.value = ev.target.result;
        };
        reader.readAsText(e.target.files[0]);
      }
    });
  }

  // ==========================================
  // MOBILE & TABLET RESPONSIVE DRAWER CONTROLS
  // ==========================================

  // Toggle Lead CRM Drawer (Tablet & Mobile)
  function toggleDossierDrawer(forceState) {
    if (!els.leadDossierCol) return;
    const shouldOpen = typeof forceState === 'boolean'
      ? forceState
      : !els.leadDossierCol.classList.contains('drawer-open');

    if (shouldOpen) {
      els.leadDossierCol.classList.add('drawer-open');
      if (els.dossierBackdrop) els.dossierBackdrop.classList.add('active');
    } else {
      els.leadDossierCol.classList.remove('drawer-open');
      if (els.dossierBackdrop) els.dossierBackdrop.classList.remove('active');
    }
  }

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
    state.currentTab = tabName;
    toggleDossierDrawer(false);

    // Buttons
    [els.tabInbox, els.tabCampaign].forEach(b => {
      if (b) b.classList.remove('active', 'tab-wa');
    });

    // Views
    if (els.viewInbox) els.viewInbox.style.display = 'none';
    if (els.viewCampaign) els.viewCampaign.style.display = 'none';

    if (tabName === 'inbox') {
      if (els.tabInbox) els.tabInbox.classList.add('active', 'tab-wa');
      if (els.viewInbox) els.viewInbox.style.display = '';
      renderConversationsList();
      renderActiveChat();
    } else if (tabName === 'campaign') {
      if (els.tabCampaign) els.tabCampaign.classList.add('active', 'tab-wa');
      if (els.viewCampaign) els.viewCampaign.style.display = 'block';
    }
  }

  if (els.tabInbox) els.tabInbox.addEventListener('click', () => switchTab('inbox'));
  if (els.tabCampaign) els.tabCampaign.addEventListener('click', () => switchTab('campaign'));

  // ==========================================
  // AUTHENTICATION & EMPLOYEE PROVISIONING MODULE
  // ==========================================

  // Check active session on load
  function checkAuthSession() {
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
          showAuthOverlay();
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
        showAuthOverlay();
      });
  }

  function showAuthOverlay() {
    if (els.authOverlay) els.authOverlay.classList.remove('hidden');
    if (els.authAlert) {
      els.authAlert.className = 'auth-alert';
      els.authAlert.style.display = 'none';
    }
  }

  function hideAuthOverlay() {
    if (els.authOverlay) els.authOverlay.classList.add('hidden');
  }

  function applyUserSession(user) {
    state.currentUser = user;
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
      els.authSubmitBtn.innerHTML = '<span>Verifying...</span>';
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
      applyUserSession(data.user);
      showToast(`Welcome back, ${data.user.name}!`);
      if (els.authPassword) els.authPassword.value = '';
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
        applyUserSession(offlineAdmin);
        showToast('Logged in as Master Administrator (Offline Mode)');
        return;
      }

      // Check local employees in offline mode
      const localEmps = getLocalEmployees();
      const matched = localEmps.find(e => (e.username.toLowerCase() === username.toLowerCase() || (e.email && e.email.toLowerCase() === username.toLowerCase())) && e.password === password);
      if (matched) {
        applyUserSession(matched);
        showToast(`Welcome, ${matched.name}!`);
        return;
      }

      showAuthError(err.message || 'Invalid username or password.');
    })
    .finally(() => {
      if (els.authSubmitBtn) {
        els.authSubmitBtn.disabled = false;
        els.authSubmitBtn.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg><span>Sign In to Omni-Desk</span>';
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
    state.currentUser = null;
    localStorage.removeItem('nunno_active_session');
    if (els.openTeamModalBtn) els.openTeamModalBtn.style.display = 'none';
    if (els.authUsername) els.authUsername.value = '';
    if (els.authPassword) els.authPassword.value = '';
    showAuthOverlay();
    showToast('Logged out of Omni-Desk.');
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
        <div class="emp-roster-card ${isSuspended ? 'suspended' : ''}">
          <div class="emp-card-user">
            <div class="emp-avatar ${isAdmin ? 'admin-av' : ''}">${isAdmin ? '👑' : initials}</div>
            <div>
              <div class="emp-card-name">${escapeHtml(emp.name)} ${isAdmin ? '<span class="badge-role-admin" style="font-size:9px;">Admin</span>' : ''}</div>
              <div class="emp-card-sub">
                <span class="mono">${escapeHtml(emp.username)}</span>
                <span>&bull;</span>
                <span>${escapeHtml(emp.designation || emp.role)}</span>
              </div>
            </div>
          </div>
          <div class="emp-actions-group">
            <span class="emp-status-badge ${isSuspended ? 'suspended' : 'active'}">
              ${isSuspended ? '🔴 Suspended' : '🟢 Active'}
            </span>
            ${!isAdmin ? `
              <button class="btn-emp-action" onclick="window.OmnideskToggleStatus('${emp.id}')" title="${isSuspended ? 'Activate Employee' : 'Suspend Employee'}">
                ${isSuspended ? 'Activate' : 'Suspend'}
              </button>
              <button class="btn-emp-action delete" onclick="window.OmnideskDeleteEmp('${emp.id}', '${escapeHtml(emp.name)}')" title="Delete Employee">
                ✕
              </button>
            ` : ''}
          </div>
        </div>
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
  if (els.closeConnectWaModalBtn) els.closeConnectWaModalBtn.addEventListener('click', closeConnectWaModal);
  if (els.cancelConnectWaBtn) els.cancelConnectWaBtn.addEventListener('click', closeConnectWaModal);
  if (els.saveConnectedWaBtn) els.saveConnectedWaBtn.addEventListener('click', saveConnectedWaNumber);

  // Authentication & Employee Modal Event Listeners
  if (els.authForm) els.authForm.addEventListener('submit', handleLogin);
  if (els.authSubmitBtn) els.authSubmitBtn.addEventListener('click', handleLogin);
  if (els.authTogglePass) els.authTogglePass.addEventListener('click', togglePasswordVisibility);
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

    // Count pending verification deals
    const allPendingDeals = state.leads.filter(l => getLeadState(l) === 'pending_verification');
    if (els.pendingVerifCount) els.pendingVerifCount.textContent = allPendingDeals.length;
    if (els.pendingAuditHeaderCount) els.pendingAuditHeaderCount.textContent = `${allPendingDeals.length} Pending Deals`;

    // Toggle tab display
    if (els.tabMyScore) els.tabMyScore.classList.toggle('active', activeTab === 'my');
    if (els.tabTeamScores) els.tabTeamScores.classList.toggle('active', activeTab === 'team');
    if (els.tabVerifySales) els.tabVerifySales.classList.toggle('active', activeTab === 'verify');

    if (els.viewMyScoreTab) els.viewMyScoreTab.style.display = activeTab === 'my' ? 'block' : 'none';
    if (els.viewTeamScoresTab) els.viewTeamScoresTab.style.display = activeTab === 'team' ? 'block' : 'none';
    if (els.viewVerifySalesTab) els.viewVerifySalesTab.style.display = activeTab === 'verify' ? 'block' : 'none';

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

  function playVoiceNote(msgId) {
    const lead = getLead(state.activeLeadId);
    if (!lead) return;
    const msg = lead.messages.find(m => m.id === msgId);
    if (!msg) return;

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
      audioChunks = [];
      recordingSeconds = 0;

      const mimeTypes = ['audio/webm;codecs=opus', 'audio/webm', 'audio/ogg;codecs=opus', 'audio/ogg', 'audio/mp4'];
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
        dispatchOutgoingAudioMessage(audioDataUrl, durationStr, durationSec);
      };
      reader.readAsDataURL(audioBlob);

      if (els.composerRecordingView) els.composerRecordingView.style.display = 'none';
      if (els.composerNormalView) els.composerNormalView.style.display = 'flex';
      if (els.voiceRecordBtn) els.voiceRecordBtn.classList.remove('recording');
    };

    mediaRecorder.stop();
  }

  function dispatchOutgoingAudioMessage(audioUrl, durationStr, durationSeconds) {
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

  // Audio Recording & Upload Event Listeners
  if (els.voiceRecordBtn) els.voiceRecordBtn.addEventListener('click', startRecordingAudio);
  if (els.cancelRecordBtn) els.cancelRecordBtn.addEventListener('click', cancelRecordingAudio);
  if (els.sendRecordBtn) els.sendRecordBtn.addEventListener('click', sendRecordingAudio);
  if (els.simClientAudioBtn) els.simClientAudioBtn.addEventListener('click', () => simulateClientAudioMessage());

  if (els.audioUploadBtn && els.audioFileInput) {
    els.audioUploadBtn.addEventListener('click', () => {
      const lead = getLead(state.activeLeadId);
      if (!lead) {
        showToast('⚠️ Please select a conversation first.');
        return;
      }
      els.audioFileInput.click();
    });

    els.audioFileInput.addEventListener('change', (e) => {
      const file = e.target.files && e.target.files[0];
      if (!file) return;

      const reader = new FileReader();
      reader.onload = (readEvt) => {
        const dataUrl = readEvt.target.result;
        const tempAudio = new Audio(dataUrl);
        tempAudio.onloadedmetadata = () => {
          const sec = Math.round(tempAudio.duration) || 20;
          dispatchOutgoingAudioMessage(dataUrl, formatSeconds(sec), sec);
        };
        tempAudio.onerror = () => {
          dispatchOutgoingAudioMessage(dataUrl, '0:25', 25);
        };
      };
      reader.readAsDataURL(file);
      els.audioFileInput.value = '';
    });
  }

  // Performance Score Modal Event Listeners
  if (els.openScoreBtn) els.openScoreBtn.addEventListener('click', () => openScoreModal('my'));
  if (els.openLeaderboardBtn) els.openLeaderboardBtn.addEventListener('click', () => openScoreModal('team'));
  if (els.closeScoreModalBtn) els.closeScoreModalBtn.addEventListener('click', closeScoreModal);
  if (els.tabMyScore) els.tabMyScore.addEventListener('click', () => renderScoreData('my'));
  if (els.tabTeamScores) els.tabTeamScores.addEventListener('click', () => renderScoreData('team'));
  if (els.tabVerifySales) els.tabVerifySales.addEventListener('click', () => renderScoreData('verify'));
  if (els.exportLeaderboardBtn) els.exportLeaderboardBtn.addEventListener('click', exportLeaderboardCsv);

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
  renderConversationsList();
  renderActiveChat();
  fetchServerLeads();
});
