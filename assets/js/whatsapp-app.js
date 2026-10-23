/**
 * Nunno WhatsApp Omni-Desk & Team CRM Engine
 * Multi-Agent Ownership, Anti-Theft Lead Locking, Unified 3-Pane Messenger & Campaign Queue
 */

document.addEventListener('DOMContentLoaded', () => {

  // Default Team Agents
  const defaultAgents = [
    { id: 'EMP-101', name: 'Hamza Khan', role: 'Senior Closer', avatar: '👨‍💼', color: '#6366f1' },
    { id: 'EMP-102', name: 'Bilal Tariq', role: 'Crypto Specialist', avatar: '🧑‍💻', color: '#06b6d4' },
    { id: 'EMP-103', name: 'Ayesha Noor', role: 'Sales Executive', avatar: '👩‍💼', color: '#ec4899' },
    { id: 'EMP-104', name: 'Zain Ahmed', role: 'Outreach Rep', avatar: '👨‍🦱', color: '#10b981' }
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
        { sender: 'lead', text: 'Theek hai, mujhe EasyPaisa account number share kar dein, main abhi activate karwana chahta hoon.', time: '10:40 AM' }
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
        { sender: 'lead', text: 'Mujhe demo video ya coupon code send karein.', time: '09:46 AM' }
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
        { sender: 'lead', text: 'Whale tier ka yearly price kya hai? Kya 1-on-1 trading architect support milega?', time: '06:15 PM' }
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
    }
  ];

  // Helper: Single Source of Truth for Traffic-Light Status
  // RED = Fresh (Unclaimed)
  // YELLOW = Ongoing (Claimed by an agent)
  // GREEN = Closed (Client has paid!)
  // BLUE = Useless (Dealt with, won't pay / dead lead)
  function getLeadState(lead) {
    if (!lead) return 'fresh';
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

    // Manual .txt Import Modal
    importTxtModal: document.getElementById('importTxtModal'),
    openImportModalBtn: document.getElementById('openImportModalBtn'),
    closeImportModalBtn: document.getElementById('closeImportModalBtn'),
    txtFileInput: document.getElementById('txtFileInput'),
    txtPasteArea: document.getElementById('txtPasteArea'),
    importAssignMode: document.getElementById('importAssignMode'),
    confirmImportBtn: document.getElementById('confirmImportBtn'),

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
    markUselessHeaderBtn: document.getElementById('markUselessHeaderBtn'),
    otherCloserNotice: document.getElementById('otherCloserNotice'),
    otherCloserName: document.getElementById('otherCloserName'),
    chatMessagesArea: document.getElementById('chatMessagesArea'),
    chatInput: document.getElementById('chatInput'),
    sendMsgBtn: document.getElementById('sendMsgBtn'),
    launchNativeWaBtn: document.getElementById('launchNativeWaBtn'),

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
    dossierTransferSelect: document.getElementById('dossierTransferSelect'),
    dossierNotes: document.getElementById('dossierNotes'),
    notesSavedIndicator: document.getElementById('notesSavedIndicator'),
    matrixBtns: document.querySelectorAll('.matrix-btn'),
    salesTags: document.querySelectorAll('.sales-tag'),

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
    } else {
      if (els.topAgentAvatar) els.topAgentAvatar.textContent = state.currentAgent.avatar;
      if (els.topAgentName) els.topAgentName.textContent = state.currentAgent.name;
      if (els.topAgentId) els.topAgentId.textContent = `${state.currentAgent.id} • ${state.currentAgent.role}`;
      if (els.openTeamModalBtn) els.openTeamModalBtn.style.display = 'none';
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

    if (lead.ownerId && lead.ownerId !== state.currentAgent.id) {
      const owner = getAgent(lead.ownerId);
      showToast(`⚠️ This lead is already locked by ${owner ? owner.name : lead.ownerId}!`);
      return;
    }

    lead.ownerId = state.currentAgent.id;
    lead.leadState = 'ongoing'; // YELLOW: Ongoing

    showToast(`🟡 Lead Claimed! You are now chatting with ${lead.name} (Ongoing).`);
    renderConversationsList();
    renderActiveChat();
    renderAgentProfile();
    syncLeadToServer(lead);
  }

  // Release Lead -> Transitions back to RED (Fresh / Unclaimed)
  function releaseLead(leadId) {
    const lead = getLead(leadId);
    if (!lead) return;

    lead.ownerId = null;
    lead.leadState = 'fresh'; // RED: Fresh

    showToast(`🔴 Lead released back to Open Pool (Fresh).`);
    renderConversationsList();
    renderActiveChat();
    renderAgentProfile();
    syncLeadToServer(lead);
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

  // Transfer Lead
  function transferLead(leadId, targetAgentId) {
    const lead = getLead(leadId);
    const targetAgent = getAgent(targetAgentId);
    if (!lead || !targetAgent) return;

    lead.ownerId = targetAgentId;
    lead.leadState = 'ongoing';

    showToast(`➡️ Lead successfully transferred to ${targetAgent.name}!`);
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
      if (state.inboxFilter === 'paid') return lState === 'paid';
      if (state.inboxFilter === 'useless') return lState === 'useless';
      if (state.inboxFilter === 'mine') return l.ownerId === state.currentAgent.id;
      return true;
    });

    // Update Subbar KPI widgets and badges
    const countAll = state.leads.length;
    const countFresh = state.leads.filter(l => getLeadState(l) === 'fresh').length;
    const countOngoing = state.leads.filter(l => getLeadState(l) === 'ongoing').length;
    const countPaid = state.leads.filter(l => getLeadState(l) === 'paid').length;
    const countUseless = state.leads.filter(l => getLeadState(l) === 'useless').length;
    const countMine = state.leads.filter(l => l.ownerId === state.currentAgent.id).length;

    if (els.kpiCountAll) els.kpiCountAll.textContent = countAll;
    if (els.kpiCountFresh) els.kpiCountFresh.textContent = countFresh;
    if (els.kpiCountOngoing) els.kpiCountOngoing.textContent = countOngoing;
    if (els.kpiCountPaid) els.kpiCountPaid.textContent = countPaid;
    if (els.kpiCountUseless) els.kpiCountUseless.textContent = countUseless;
    if (els.myLeadsCountBadge) els.myLeadsCountBadge.textContent = countMine;
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

      // Clean 4-State Badge
      let statusBadgeHtml = '';
      if (leadState === 'fresh') {
        statusBadgeHtml = `<span class="status-pill-clean fresh">🔴 Fresh</span>`;
      } else if (leadState === 'ongoing') {
        const ownerName = owner ? owner.name.split(' ')[0] : 'Claimed';
        statusBadgeHtml = `<span class="status-pill-clean ongoing">🟡 Ongoing (${ownerName})</span>`;
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
          <div class="lead-status-dot status-${leadState}" title="${leadState === 'fresh' ? '🔴 Fresh (No one claimed)' : leadState === 'ongoing' ? '🟡 Ongoing (In Progress)' : '🟢 Closed (Client Paid)'}"></div>
          ${lead.unreadCount > 0 ? `<div class="unread-badge">${lead.unreadCount}</div>` : ''}
        </div>
        <div class="conv-body">
          <div class="conv-top-row">
            <span class="conv-contact-name">${lead.name}</span>
            <span class="conv-timestamp">${lastMsg.time || lead.lastActive}</span>
          </div>
          <div class="conv-preview-row">
            <span class="text-xs text-muted">${lastMsg.text}</span>
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
    // On mobile screens, activate full-screen chat thread
    if (els.viewInbox) {
      els.viewInbox.classList.add('mobile-chat-open');
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
      } else if (leadState === 'paid') {
        els.chatLeadStatusBadge.className = 'status-pill-clean paid';
        els.chatLeadStatusBadge.innerHTML = '🟢 Closed (Paid)';
      } else if (leadState === 'useless') {
        els.chatLeadStatusBadge.className = 'status-pill-clean useless';
        els.chatLeadStatusBadge.innerHTML = "🔵 Won't Pay";
      }
    }

    if (els.chatLeadCloser) {
      if (leadState === 'fresh') {
        els.chatLeadCloser.style.color = '#ff6688';
        els.chatLeadCloser.textContent = 'Open Pool';
      } else if (leadState === 'ongoing') {
        els.chatLeadCloser.style.color = '#fbbf24';
        els.chatLeadCloser.textContent = `🔒 ${owner ? owner.name : lead.ownerId}`;
      } else if (leadState === 'paid') {
        els.chatLeadCloser.style.color = '#34d399';
        els.chatLeadCloser.textContent = `✓ Paid & Active`;
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

    // Header Won't Pay Button
    if (els.markUselessHeaderBtn) {
      if (leadState === 'useless') {
        els.markUselessHeaderBtn.className = 'btn btn-ghost btn-xs';
        els.markUselessHeaderBtn.style.color = '#38bdf8';
        els.markUselessHeaderBtn.style.borderColor = 'rgba(56, 189, 248, 0.4)';
        els.markUselessHeaderBtn.innerHTML = `🔵 Won't Pay ✓`;
        els.markUselessHeaderBtn.onclick = () => reopenLead(lead.id);
      } else {
        els.markUselessHeaderBtn.className = 'btn btn-useless btn-xs';
        els.markUselessHeaderBtn.style.color = '#ffffff';
        els.markUselessHeaderBtn.style.borderColor = 'transparent';
        els.markUselessHeaderBtn.innerHTML = `Won't Pay`;
        els.markUselessHeaderBtn.onclick = () => markLeadUseless(lead.id);
      }
    }

    // Header Mark Paid Button
    if (els.markPaidHeaderBtn) {
      if (leadState === 'paid') {
        els.markPaidHeaderBtn.className = 'btn btn-ghost btn-xs';
        els.markPaidHeaderBtn.style.color = '#10b981';
        els.markPaidHeaderBtn.style.borderColor = 'rgba(16, 185, 129, 0.4)';
        els.markPaidHeaderBtn.innerHTML = `🟢 Closed (Paid) ✓`;
        els.markPaidHeaderBtn.onclick = () => reopenLead(lead.id);
      } else {
        els.markPaidHeaderBtn.className = 'btn btn-paid btn-xs';
        els.markPaidHeaderBtn.style.color = '#ffffff';
        els.markPaidHeaderBtn.style.borderColor = 'transparent';
        els.markPaidHeaderBtn.innerHTML = `Mark Paid`;
        els.markPaidHeaderBtn.onclick = () => markLeadPaid(lead.id);
      }
    }

    // Conflict Lock Notice
    if (els.otherCloserNotice) {
      if (leadState === 'ongoing' && lead.ownerId !== state.currentAgent.id) {
        els.otherCloserNotice.style.display = 'block';
        if (els.otherCloserName) els.otherCloserName.textContent = owner ? owner.name : lead.ownerId;
        if (els.chatInput) {
          els.chatInput.disabled = true;
          els.chatInput.placeholder = `Lead is ongoing with ${owner ? owner.name : lead.ownerId}. Messaging locked.`;
        }
        if (els.sendMsgBtn) els.sendMsgBtn.disabled = true;
      } else {
        els.otherCloserNotice.style.display = 'none';
        if (els.chatInput) {
          els.chatInput.disabled = false;
          els.chatInput.placeholder = 'Type WhatsApp message... (Press Enter to send)';
        }
        if (els.sendMsgBtn) els.sendMsgBtn.disabled = false;
      }
    }

    // Render Messages Thread
    if (els.chatMessagesArea) {
      els.chatMessagesArea.innerHTML = `
        <div class="msg-date-divider">TODAY • ENCRYPTED WHATSAPP THREAD</div>
      `;

      lead.messages.forEach(msg => {
        const bubble = document.createElement('div');
        const isInbound = msg.sender === 'lead';
        bubble.className = `msg-bubble ${isInbound ? 'msg-inbound' : 'msg-outbound'}`;

        bubble.innerHTML = `
          ${!isInbound ? `<div class="msg-sender-tag">${msg.agentName || 'Agent'}</div>` : ''}
          <div>${formatText(msg.text)}</div>
          <div class="msg-meta-row">
            <span>${msg.time}</span>
            ${!isInbound ? `<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#53bdeb" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/><polyline points="20 12 14.5 17.5"/></svg>` : ''}
          </div>
        `;
        els.chatMessagesArea.appendChild(bubble);
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
      if (leadState === 'ongoing' && lead.ownerId === state.currentAgent.id) {
        els.dossierReleaseBtn.style.display = 'block';
        els.dossierReleaseBtn.onclick = () => releaseLead(lead.id);
      } else {
        els.dossierReleaseBtn.style.display = 'none';
      }
    }

    // WIDGET 3: Power Conversion Buttons
    if (els.dossierPaidBtn) {
      if (leadState === 'paid') {
        els.dossierPaidBtn.className = 'btn btn-ghost btn-sm';
        els.dossierPaidBtn.innerHTML = 'Re-open Lead to Ongoing';
        els.dossierPaidBtn.onclick = () => reopenLead(lead.id);
      } else {
        els.dossierPaidBtn.className = 'btn btn-paid btn-sm';
        els.dossierPaidBtn.innerHTML = '✅ Confirm Client Paid (Closed)';
        els.dossierPaidBtn.onclick = () => markLeadPaid(lead.id);
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

    // Check lock
    if (lead.ownerId && lead.ownerId !== state.currentAgent.id) {
      showToast('⚠️ You cannot send messages to a lead owned by another agent!');
      return;
    }

    const text = els.chatInput.value.trim();
    if (!text) return;

    // If unclaimed, automatically claim when agent responds
    if (!lead.ownerId) {
      lead.ownerId = state.currentAgent.id;
      showToast(`🎯 You responded first! Lead automatically locked to you.`);
    }

    const now = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });

    lead.messages.push({
      sender: 'agent',
      agentName: state.currentAgent.name,
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

  // Dossier Transfer Select
  if (els.dossierTransferSelect) {
    els.dossierTransferSelect.addEventListener('change', (e) => {
      const targetId = e.target.value;
      if (targetId && state.activeLeadId) {
        transferLead(state.activeLeadId, targetId);
        e.target.value = '';
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
    const designation = (els.newEmpRole?.value || 'Senior Closer');
    const role = designation.toLowerCase().includes('admin') ? 'admin' : 'employee';
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
      { id: 'USR-ADMIN-01', name: 'System Administrator', username: 'admin', email: 'admin@nunno.cloud', password: 'Admin@Omnidesk2026!', role: 'admin', designation: 'Master Administrator', status: 'active' },
      { id: 'EMP-101', name: 'Hamza Khan', username: 'hamza', email: 'hamza@nunno.cloud', password: 'Agent@123', role: 'employee', designation: 'Senior Closer', status: 'active' }
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

  // Initialize App
  checkAuthSession();
  renderAgentProfile();
  renderConnectedNumber();
  renderConversationsList();
  renderActiveChat();
  fetchServerLeads();
});
