'use strict';
const fs = require('node:fs');
const path = require('node:path');
const { randomUUID } = require('node:crypto');

function resolveSpintax(text) {
  if (!text) return '';
  return text.replace(/\{([^{}]+)\}/g, (match, contents) => {
    const lower = contents.trim().toLowerCase();
    if (['name', 'phone', 'city', 'plan', 'price', 'coupon', 'link', 'company'].includes(lower)) {
      return match;
    }
    if (contents.includes('|')) {
      const parts = contents.split('|');
      return parts[Math.floor(Math.random() * parts.length)].trim();
    }
    return contents;
  });
}

function personalizeMessage(template, contact, companyName = 'Chatly') {
  if (!template) return '';
  let msg = resolveSpintax(template);
  const cName = contact?.name || contact?.display || contact?.phone || 'Valued Client';
  const cPhone = contact?.display || contact?.phone || '';
  const cCity = contact?.city || 'Pakistan';

  msg = msg.replace(/\{name(?:\|([^{}]+))?\}/gi, (match, fallback) => {
    if (cName && !cName.startsWith('+')) return cName;
    if (fallback) {
      const fbParts = fallback.split('|');
      return fbParts[Math.floor(Math.random() * fbParts.length)].trim();
    }
    return cName;
  });

  msg = msg
    .replace(/\{name\}/gi, cName)
    .replace(/\{phone\}/gi, cPhone)
    .replace(/\{city\}/gi, cCity)
    .replace(/\{company\}/gi, companyName)
    .replace(/\{plan\}/gi, 'Exclusive Business Plan')
    .replace(/\{price\}/gi, 'Rs. 5,000')
    .replace(/\{coupon\}/gi, 'CHATLY30')
    .replace(/\{link\}/gi, 'https://chatly.pk');

  return msg;
}

function createCampaignManager({ send, dataDir, getLeadsPath, getUsersPath }) {
  const stateFile = path.join(dataDir, 'campaign_state.json');
  let loopTimer = null;
  let countdownTimer = null;
  let inFlight = false;

  let state = {
    id: null,
    name: 'Multi-Device Round-Robin Campaign',
    companyId: 'COMP-001',
    companyName: 'Chatly Official',
    status: 'idle', // 'idle' | 'running' | 'paused' | 'stopped' | 'completed'
    audience: [],
    currentIndex: 0,
    totalSent: 0,
    totalFailed: 0,
    minDelay: 4,
    maxDelay: 8,
    remainingSeconds: 0,
    messageTemplate: '',
    variation: 'text',
    media: null,
    assignMode: 'pool',
    closer: null,
    startedAt: null,
    updatedAt: null,
    logs: [],
    // Multi-Device Round-Robin Pool
    sendersPool: [],
    poolSize: 1,
    currentCycle: 1,
    totalCycles: 1,
    activeSenderIndex: 0,
    // Closers Allocation Pool (TMs and TLs only, Admins strictly excluded)
    closersPool: []
  };

  function log(message, type = 'info') {
    const time = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' });
    const entry = { time, message, type };
    state.logs.push(entry);
    if (state.logs.length > 500) state.logs.shift();
    saveState();
  }

  function saveState() {
    try {
      state.updatedAt = Date.now();
      const tmp = stateFile + '.tmp';
      fs.writeFileSync(tmp, JSON.stringify(state, null, 2), 'utf8');
      fs.renameSync(tmp, stateFile);
    } catch (_) {}
  }

  function loadState() {
    try {
      if (fs.existsSync(stateFile)) {
        const loaded = JSON.parse(fs.readFileSync(stateFile, 'utf8'));
        if (loaded && typeof loaded === 'object') {
          state = { ...state, ...loaded };
          if (state.status === 'running') {
            state.status = 'paused';
            log('Campaign paused after server restart. Click Resume to continue.', 'warn');
          }
        }
      }
    } catch (_) {}
  }

  function resolveCompanyClosers(companyId) {
    try {
      const usersFile = typeof getUsersPath === 'function' ? getUsersPath() : path.join(dataDir, '../users.json');
      if (fs.existsSync(usersFile)) {
        const users = JSON.parse(fs.readFileSync(usersFile, 'utf8') || '[]');
        if (Array.isArray(users)) {
          // STRICT RULE: Only active Team Members (TM, role: member) and Team Leaders (TL, role: lead).
          // Admins and Superadmins are strictly excluded from receiving or owning leads!
          const matched = users.filter(u => {
            const uComp = u.company_id || 'COMP-001';
            const status = (u.status || 'active').toLowerCase();
            const role = (u.role || 'member').toLowerCase();
            return uComp === companyId && status === 'active' && (role === 'member' || role === 'lead');
          });

          if (matched.length > 0) {
            matched.sort((a, b) => {
              const order = { member: 1, lead: 2 };
              return (order[a.role] || 99) - (order[b.role] || 99);
            });

            return matched.map((u, idx) => {
              let roleLabel = 'TM Closer';
              if (u.role === 'lead') roleLabel = 'Team Leader (TL)';
              return {
                index: idx + 1,
                id: u.id,
                name: u.name,
                username: u.username || '',
                role: roleLabel,
                roleType: u.role || 'member',
                phone: String(u.phone || '').replace(/\D/g, '')
              };
            });
          }
        }
      }
    } catch (err) {
      console.warn('[Campaign] resolveCompanyClosers warning:', err.message);
    }

    // Default fallback closer: Askari Raza
    return [{
      index: 1,
      id: 'EMP-TM-MWMK43N9',
      name: 'Askari Raza',
      username: 'askari',
      role: 'TM Closer',
      roleType: 'member',
      phone: '923318036576'
    }];
  }

  function resolveCompanySenders(companyId) {
    try {
      const usersFile = typeof getUsersPath === 'function' ? getUsersPath() : path.join(dataDir, '../users.json');
      if (fs.existsSync(usersFile)) {
        const users = JSON.parse(fs.readFileSync(usersFile, 'utf8') || '[]');
        if (Array.isArray(users)) {
          // Prefer active TMs and TLs for the sending device pool
          let matched = users.filter(u => {
            const uComp = u.company_id || 'COMP-001';
            const status = (u.status || 'active').toLowerCase();
            const role = (u.role || 'member').toLowerCase();
            return uComp === companyId && status === 'active' && u.phone && String(u.phone).replace(/\D/g, '').length >= 10 && role !== 'admin' && role !== 'superadmin';
          });

          // If no TMs/TLs with phone found, fallback to any active user with phone
          if (matched.length === 0) {
            matched = users.filter(u => {
              const uComp = u.company_id || 'COMP-001';
              const status = (u.status || 'active').toLowerCase();
              return uComp === companyId && status === 'active' && u.phone && String(u.phone).replace(/\D/g, '').length >= 10;
            });
          }

          if (matched.length > 0) {
            // Sort: TMs first, then TLs, then Admins
            matched.sort((a, b) => {
              const order = { member: 1, lead: 2, admin: 3 };
              return (order[a.role] || 99) - (order[b.role] || 99);
            });

            // Deduplicate by unique phone number
            const seenPhones = new Set();
            const uniqueMatched = [];
            for (const u of matched) {
              const p = String(u.phone || '').replace(/\D/g, '');
              if (!seenPhones.has(p)) {
                seenPhones.add(p);
                uniqueMatched.push(u);
              }
            }

            return uniqueMatched.map((u, idx) => {
              const cleanPhone = String(u.phone).replace(/\D/g, '');
              let roleLabel = 'TM Closer';
              if (u.role === 'lead') roleLabel = 'Team Leader (TL)';
              else if (u.role === 'admin') roleLabel = 'Admin';
              else if (u.role === 'member') roleLabel = 'TM Closer';

              return {
                index: idx + 1,
                id: u.id,
                name: u.name,
                username: u.username || '',
                role: roleLabel,
                roleType: u.role || 'member',
                phone: cleanPhone,
                display: u.phone.startsWith('+') ? u.phone : ('+' + cleanPhone),
                messagesSent: 0,
                status: 'ready'
              };
            });
          }
        }
      }
    } catch (err) {
      console.warn('[Campaign] resolveCompanySenders warning:', err.message);
    }

    // Default fallback single device
    return [{
      index: 1,
      id: 'SENDER-FALLBACK-01',
      name: 'Primary Connected Device',
      username: 'primary',
      role: 'TM Closer',
      roleType: 'member',
      phone: '923318036576',
      display: '+92 331 8036576',
      messagesSent: 0,
      status: 'ready'
    }];
  }

  function incrementUserMessageCount(userId) {
    if (!userId) return;
    try {
      const usersFile = typeof getUsersPath === 'function' ? getUsersPath() : path.join(dataDir, '../users.json');
      if (fs.existsSync(usersFile)) {
        const users = JSON.parse(fs.readFileSync(usersFile, 'utf8') || '[]');
        if (Array.isArray(users)) {
          const user = users.find(u => u.id === userId);
          if (user) {
            user.messages_sent_today = (Number(user.messages_sent_today) || 0) + 1;
            fs.writeFileSync(usersFile, JSON.stringify(users, null, 2), 'utf8');
          }
        }
      }
    } catch (_) {}
  }

  function generateDemoContacts(companyName) {
    return [
      { id: 'demo_01', name: 'Tariq Mehmood', phone: '923015544332', display: '+92 301 5544332', city: 'Lahore', network: 'Jazz' },
      { id: 'demo_02', name: 'Bilal Ashraf', phone: '923218899001', display: '+92 321 8899001', city: 'Faisalabad', network: 'Warid' },
      { id: 'demo_03', name: 'Dr. Kamran Qureshi', phone: '923001234567', display: '+92 300 1234567', city: 'Karachi', network: 'Mobilink' },
      { id: 'demo_04', name: 'Hassan Farooq', phone: '923337788990', display: '+92 333 7788990', city: 'Islamabad', network: 'Ufone' },
      { id: 'demo_05', name: 'Usman Ghani', phone: '923451122334', display: '+92 345 1122334', city: 'Rawalpindi', network: 'Telenor' },
      { id: 'demo_06', name: 'Malik Jahangir', phone: '923124455667', display: '+92 312 4455667', city: 'Multan', network: 'Zong' },
      { id: 'demo_07', name: 'Sufyan Ahmed', phone: '923029988776', display: '+92 302 9988776', city: 'Sialkot', network: 'Jazz' },
      { id: 'demo_08', name: 'Rehan Siddiqui', phone: '923348877665', display: '+92 334 8877665', city: 'Gujranwala', network: 'Ufone' },
      { id: 'demo_09', name: 'Waleed Sheikh', phone: '923226655443', display: '+92 322 6655443', city: 'Peshawar', network: 'Warid' },
      { id: 'demo_10', name: 'Zohaib Hassan', phone: '923075566778', display: '+92 307 5566778', city: 'Hyderabad', network: 'Jazz' }
    ];
  }

  function updateCrmLead(contact, crmMessage, assignMode, activeSender) {
    try {
      const companyId = state.companyId || 'COMP-001';
      const cleanId = String(companyId).replace(/[^a-zA-Z0-9_-]/g, '') || 'COMP-001';

      // Always resolve target files to update both tenant file and global mirror
      const tenantPath = path.resolve(__dirname, `../data/tenants/${cleanId}/leads.json`);
      const globalPath = path.resolve(__dirname, '../data/leads.json');
      const targetPaths = [tenantPath];
      if (cleanId === 'COMP-001' && globalPath !== tenantPath) {
        targetPaths.push(globalPath);
      }

      // Read current leads from tenant file (or global file if tenant not initialized)
      let leads = [];
      let sourceFile = fs.existsSync(tenantPath) ? tenantPath : (fs.existsSync(globalPath) ? globalPath : null);
      if (sourceFile) {
        try {
          const raw = fs.readFileSync(sourceFile, 'utf8');
          leads = JSON.parse(raw);
        } catch (_) {}
      }
      if (!Array.isArray(leads)) leads = [];

      const cleanPhone = String(contact.phone || '').replace(/\D/g, '');
      let existingLead = leads.find(l => String(l.phone || '').replace(/\D/g, '') === cleanPhone);

      // STRICT LEAD ALLOCATION RULE:
      // Lead ownership MUST go to the active closer (TM or TL) whose device sent the message!
      let ownerId = (activeSender && activeSender.roleType !== 'admin') ? activeSender.id : contact.assignedOwnerId;
      let ownerName = (activeSender && activeSender.roleType !== 'admin') ? activeSender.name : contact.assignedOwnerName;
      let ownerUsername = (activeSender && activeSender.roleType !== 'admin') ? (activeSender.username || activeSender.name) : contact.assignedOwnerUsername;

      if (!ownerId && state.closersPool && state.closersPool.length > 0) {
        const fallbackCloser = state.closersPool[0];
        ownerId = fallbackCloser.id;
        ownerName = fallbackCloser.name;
        ownerUsername = fallbackCloser.username || '';
      }

      if (existingLead) {
        existingLead.messages = existingLead.messages || [];
        if (!existingLead.messages.some(m => m.waMessageId === crmMessage.waMessageId)) {
          existingLead.messages.push(crmMessage);
        }
        existingLead.lastActive = 'Just now';
        existingLead.status = 'sent';
        if (existingLead.leadState !== 'paid' && existingLead.leadState !== 'pending_verification') {
          existingLead.leadState = 'ongoing';
        }
        // Auto-fix / allocate if previously unowned, fallback, or locked to admin
        if (ownerId && (!existingLead.ownerId || existingLead.ownerId.includes('ADMIN') || existingLead.ownerId.includes('FALLBACK') || existingLead.ownerId === 'USR-ADMIN-01')) {
          existingLead.ownerId = ownerId;
          existingLead.ownerName = ownerName;
          existingLead.ownerUsername = ownerUsername;
        }
        existingLead.lastSenderPhone = activeSender ? activeSender.phone : null;
        existingLead.lastSenderName = activeSender ? activeSender.name : null;
      } else {
        const newLead = {
          id: `lead_${Date.now()}_${cleanPhone}`,
          company_id: cleanId,
          name: contact.name || contact.display || `+${cleanPhone}`,
          phone: cleanPhone,
          display: contact.display || `+${cleanPhone}`,
          city: contact.city || 'Pakistan',
          network: contact.network || 'WhatsApp',
          ownerId: ownerId,
          ownerName: ownerName,
          ownerUsername: ownerUsername,
          sender_id: activeSender ? activeSender.id : ownerId,
          sender_name: activeSender ? activeSender.name : ownerName,
          sender_phone: activeSender ? activeSender.phone : null,
          leadState: 'ongoing',
          status: 'sent',
          unreadCount: 0,
          interest: 'Campaign Outreach',
          lastActive: 'Just now',
          notes: `Outreach dispatched via campaign "${state.name}". Allocated Closer: ${ownerName || 'TM Closer'} (${activeSender?.role || contact.assignedOwnerRole || 'Closer'}). Device: ${activeSender ? activeSender.name : 'Primary Device'}.`,
          messages: [crmMessage]
        };
        leads.unshift(newLead);
      }

      // Write atomically to all target files
      for (const targetFile of targetPaths) {
        try {
          const dir = path.dirname(targetFile);
          if (!fs.existsSync(dir)) fs.mkdirSync(dir, { recursive: true });
          const tmp = targetFile + '.tmp.' + Date.now();
          fs.writeFileSync(tmp, JSON.stringify(leads, null, 2), 'utf8');
          fs.renameSync(tmp, targetFile);
        } catch (fErr) {
          console.warn(`[Campaign] Failed writing leads to ${targetFile}:`, fErr.message);
        }
      }
    } catch (err) {
      console.warn('[Campaign CRM Sync Error]:', err.message);
    }
  }

  async function step() {
    if (state.status !== 'running' || inFlight) return;

    while (state.currentIndex < state.audience.length &&
           ['accepted', 'sent', 'delivered', 'read'].includes(state.audience[state.currentIndex].status)) {
      state.currentIndex++;
    }

    if (state.currentIndex >= state.audience.length) {
      finish();
      return;
    }

    const contact = state.audience[state.currentIndex];
    const total = state.audience.length;
    const currentNum = state.currentIndex + 1;

    // Strict Round-Robin Algorithm:
    // Senders: 1 to M. Contact i (0-based) goes to sender (i % M).
    // Cycle: floor(i / M) + 1. Total Cycles: ceil(N / M).
    const poolSize = Math.max(1, state.sendersPool.length);
    const senderIndex = state.currentIndex % poolSize;
    const currentCycle = Math.floor(state.currentIndex / poolSize) + 1;
    const totalCycles = Math.ceil(total / poolSize);

    state.activeSenderIndex = senderIndex;
    state.currentCycle = currentCycle;
    state.totalCycles = totalCycles;

    const activeSender = state.sendersPool[senderIndex];

    contact.status = 'sending';
    contact.requestId = contact.requestId || randomUUID();
    contact.senderId = activeSender.id;
    contact.senderName = activeSender.name;
    contact.senderRole = activeSender.role;
    contact.senderPhone = activeSender.phone;
    if (activeSender.roleType !== 'admin') {
      contact.assignedOwnerId = activeSender.id;
      contact.assignedOwnerName = activeSender.name;
      contact.assignedOwnerUsername = activeSender.username || activeSender.name;
      contact.assignedOwnerRole = activeSender.role || 'TM Closer';
    }
    contact.cycle = currentCycle;
    contact.senderIndex = senderIndex + 1;

    const personalizedMsg = personalizeMessage(state.messageTemplate, contact, state.companyName);
    contact.preparedMessage = personalizedMsg;
    saveState();

    inFlight = true;
    try {
      const sendPayload = {
        phone: contact.phone,
        message: personalizedMsg,
        request_id: contact.requestId,
        sender_id: activeSender.id,
        sender_phone: activeSender.phone,
        sender_name: activeSender.name,
        sender_role: activeSender.role,
        cycle: currentCycle,
        sender_index: senderIndex + 1,
        simulate: false,
        allow_simulation: false
      };
      if (state.media) {
        sendPayload.media = state.media;
      }

      const result = await send(sendPayload);
      if (result && result.simulated) {
        throw new Error('WhatsApp device is not connected on server. Scan QR code to link your phone.');
      }

      contact.status = 'accepted';
      contact.sentAt = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' });
      contact.messageId = result?.message_id || ('msg_' + Date.now());
      state.totalSent++;

      // Increment active sender's count
      activeSender.messagesSent = (activeSender.messagesSent || 0) + 1;
      activeSender.lastSentAt = contact.sentAt;

      // Update user quota today in users.json
      incrementUserMessageCount(activeSender.id);

      const crmMessage = {
        id: `msg_c_${Date.now()}`,
        waMessageId: contact.messageId,
        deliveryStatus: 'accepted',
        sender: 'agent',
        agentName: activeSender.name,
        senderPhone: activeSender.phone,
        senderRole: activeSender.role,
        cycle: currentCycle,
        text: personalizedMsg,
        time: contact.sentAt
      };

      updateCrmLead(contact, crmMessage, state.assignMode, activeSender);

      log(`🔄 [Cycle ${currentCycle}/${totalCycles} · Device ${senderIndex + 1}/${poolSize}] ${activeSender.name} (${activeSender.role} · ${activeSender.display}) ➔ Dispatched to ${contact.display || contact.phone} (${contact.name || 'Lead'}) [Allocated Closer: ${contact.assignedOwnerName || 'Closer'} · ${contact.assignedOwnerRole || 'TM'}]`, 'success');

      // Check if a full round-robin cycle just completed
      if (poolSize > 1 && (senderIndex + 1 === poolSize) && currentNum < total) {
        log(`✨ [Cycle ${currentCycle}/${totalCycles} Finished] All ${poolSize} devices sent 1 message each. Starting Cycle ${currentCycle + 1}/${totalCycles}...`, 'info');
      }

    } catch (err) {
      contact.status = 'failed';
      contact.error = err.message;
      state.totalFailed++;
      log(`❌ [Cycle ${currentCycle}/${totalCycles} · Device ${senderIndex + 1}/${poolSize}] Failed for ${contact.display || contact.phone}: ${err.message}`, 'error');
    } finally {
      inFlight = false;
      state.currentIndex++;
      saveState();
    }

    if (state.status !== 'running') return;

    if (state.currentIndex >= state.audience.length) {
      finish();
      return;
    }

    const minSec = state.minDelay || 4;
    const maxSec = state.maxDelay || 8;
    const jitterSec = Math.floor(Math.random() * (maxSec - minSec + 1)) + minSec;
    state.remainingSeconds = jitterSec;
    saveState();

    if (countdownTimer) clearInterval(countdownTimer);
    countdownTimer = setInterval(() => {
      if (state.status !== 'running') {
        clearInterval(countdownTimer);
        return;
      }
      state.remainingSeconds--;
      if (state.remainingSeconds <= 0) {
        clearInterval(countdownTimer);
      }
    }, 1000);

    if (loopTimer) clearTimeout(loopTimer);
    loopTimer = setTimeout(() => {
      step();
    }, jitterSec * 1000);
  }

  function finish() {
    clearTimeout(loopTimer);
    clearInterval(countdownTimer);
    state.status = 'completed';
    state.remainingSeconds = 0;
    log(`🎉 Campaign completed! Total sent: ${state.totalSent}, Failed: ${state.totalFailed} across ${state.totalCycles} full cycles utilizing all ${state.poolSize} connected WhatsApp devices.`, 'success');
    saveState();
  }

  function start(params = {}) {
    clearTimeout(loopTimer);
    clearInterval(countdownTimer);
    inFlight = false;

    const companyId = params.company_id || params.companyId || 'COMP-001';
    const companyName = params.company_name || (companyId === 'COMP-004' ? 'Metalink' : (companyId === 'COMP-001' ? 'Nunno Official' : 'Chatly Partner'));

    state.id = 'camp_' + Date.now();
    state.name = (params.name || '').trim() || `Outreach Campaign (${companyName})`;
    state.companyId = companyId;
    state.companyName = companyName;
    state.messageTemplate = params.message || '';
    state.variation = params.variation || 'text';
    state.media = params.media || null;
    if (params.image_url && !state.media) {
      state.media = { mimetype: 'image/jpeg', filename: 'flyer.jpg', url: params.image_url };
    }
    if (params.audio_url && !state.media) {
      state.media = { mimetype: 'audio/ogg', filename: 'voice_pitch.ogg', url: params.audio_url };
    }

    // 1. Build Round-Robin Senders Pool
    let sendersPool = [];
    if (Array.isArray(params.senders) && params.senders.length > 0) {
      sendersPool = params.senders.map((s, idx) => ({
        index: idx + 1,
        id: s.id || `sender_${idx + 1}`,
        name: s.name || `Device ${idx + 1}`,
        username: s.username || '',
        role: s.role || 'TM Closer',
        roleType: s.roleType || 'member',
        phone: String(s.phone || '').replace(/\D/g, ''),
        display: s.display || ('+' + String(s.phone || '').replace(/\D/g, '')),
        messagesSent: 0,
        status: 'ready'
      }));
    } else {
      sendersPool = resolveCompanySenders(companyId);
    }

    state.sendersPool = sendersPool;
    state.poolSize = Math.max(1, sendersPool.length);

    // 1b. Build Closers Pool (All active TMs and TLs, Admins strictly excluded)
    let closersPool = [];
    if (Array.isArray(params.closers) && params.closers.length > 0) {
      closersPool = params.closers.filter(c => c.roleType !== 'admin' && c.role !== 'Admin');
    }
    if (!closersPool.length && sendersPool.length > 0) {
      closersPool = sendersPool.filter(s => s.roleType !== 'admin' && s.role !== 'Admin');
    }
    if (!closersPool.length) {
      closersPool = resolveCompanyClosers(companyId);
    }
    state.closersPool = closersPool;
    const closerCount = Math.max(1, closersPool.length);

    // 2. Build Audience (Target Leads)
    let rawAudience = Array.isArray(params.audience) && params.audience.length > 0 ? params.audience : [];
    if (!rawAudience.length) {
      // Auto-load leads from company database
      try {
        const leadsFile = typeof getLeadsPath === 'function' ? getLeadsPath(companyId) : null;
        if (leadsFile && fs.existsSync(leadsFile)) {
          const stored = JSON.parse(fs.readFileSync(leadsFile, 'utf8') || '[]');
          if (Array.isArray(stored) && stored.length > 0) {
            const filter = params.filter || 'all';
            if (filter === 'new') {
              rawAudience = stored.filter(l => l.leadState === 'fresh' || l.status === 'new' || (!l.messages || l.messages.length <= 1));
            } else if (filter === 'ongoing') {
              rawAudience = stored.filter(l => l.leadState === 'ongoing');
            } else {
              rawAudience = stored;
            }
          }
        }
      } catch (_) {}
    }

    // If still empty (e.g. fresh company or test mode), use 10 realistic prospects so round-robin can run
    if (!rawAudience.length || params.filter === 'demo' || params.demo) {
      rawAudience = generateDemoContacts(companyName);
    }

    // STRICT CLOSER ALLOCATION: Round-robin distribute every contact to company's active TMs and TLs
    state.audience = rawAudience.map((c, idx) => {
      const assignedCloser = closersPool[idx % closerCount];
      return {
        id: c.id || ('c_' + Math.random().toString(36).slice(2, 9)),
        phone: String(c.phone || '').replace(/\D/g, ''),
        display: c.display || ('+' + String(c.phone || '').replace(/\D/g, '')),
        name: c.name || '',
        city: c.city || 'Pakistan',
        network: c.network || 'WhatsApp',
        status: 'pending',
        requestId: null,
        senderId: null,
        senderName: null,
        senderRole: null,
        senderPhone: null,
        assignedOwnerId: assignedCloser ? assignedCloser.id : null,
        assignedOwnerName: assignedCloser ? assignedCloser.name : null,
        assignedOwnerUsername: assignedCloser ? (assignedCloser.username || '') : '',
        assignedOwnerRole: assignedCloser ? assignedCloser.role : 'TM Closer',
        cycle: 1,
        sentAt: null,
        error: null
      };
    });

    state.currentIndex = 0;
    state.totalSent = 0;
    state.totalFailed = 0;
    const requestedDelay = parseInt(params.delay, 10) || parseInt(params.minDelay, 10) || 5;
    state.minDelay = Math.max(2, requestedDelay);
    state.maxDelay = Math.max(state.minDelay, parseInt(params.maxDelay, 10) || (state.minDelay + 4));
    state.remainingSeconds = 0;
    state.assignMode = params.assignMode || 'pool';
    state.closer = params.closer || null;
    state.status = 'running';
    state.startedAt = Date.now();
    state.logs = [];

    state.totalCycles = Math.ceil(state.audience.length / state.poolSize);
    state.currentCycle = 1;
    state.activeSenderIndex = 0;

    const deviceRosterSummary = state.sendersPool.map(s => `${s.index}. ${s.name} (${s.role} · ${s.display})`).join(' | ');

    log(`🚀 Campaign "${state.name}" launched for ${companyName}!`, 'info');
    log(`📱 Multi-Device Pool: ${state.poolSize} connected WhatsApps aligned for Round-Robin order: ${deviceRosterSummary}`, 'info');
    log(`🎯 Target Audience: ${state.audience.length} prospects. Total Cycles: ${state.totalCycles} cycles of ${state.poolSize} devices (1 message/device per cycle). Cadence: ${state.minDelay}s - ${state.maxDelay}s.`, 'info');
    saveState();

    setTimeout(() => {
      step().catch(err => log('Step execution error: ' + err.message, 'error'));
    }, 100);

    return getStatus();
  }

  function pause() {
    if (state.status !== 'running') return getStatus();
    clearTimeout(loopTimer);
    clearInterval(countdownTimer);
    state.status = 'paused';
    log(`⏸️ Campaign paused by operator at lead ${state.currentIndex + 1} of ${state.audience.length} (Cycle ${state.currentCycle}/${state.totalCycles}). Standing by.`, 'warn');
    saveState();
    return getStatus();
  }

  function resume() {
    if (state.status !== 'paused') return getStatus();
    state.status = 'running';
    log(`▶️ Campaign resumed. Picking up round-robin sequence at Device ${state.activeSenderIndex + 1}/${state.poolSize}...`, 'info');
    saveState();
    setTimeout(() => {
      step().catch(err => log('Step execution error: ' + err.message, 'error'));
    }, 100);
    return getStatus();
  }

  function stop() {
    clearTimeout(loopTimer);
    clearInterval(countdownTimer);
    state.status = 'stopped';
    state.remainingSeconds = 0;
    log(`⏹️ Campaign halted by operator. Dispatched ${state.totalSent} of ${state.audience.length} messages across ${state.currentCycle} cycles.`, 'warn');
    saveState();
    return getStatus();
  }

  function getStatus() {
    const poolSize = Math.max(1, state.sendersPool.length);
    const activeIndex = state.currentIndex % poolSize;
    const currentCycle = Math.min(state.totalCycles || 1, Math.floor(state.currentIndex / poolSize) + 1);

    return {
      success: true,
      id: state.id,
      name: state.name,
      companyId: state.companyId,
      companyName: state.companyName,
      status: state.status,
      totalContacts: state.audience.length,
      currentIndex: state.currentIndex,
      totalSent: state.totalSent,
      totalFailed: state.totalFailed,
      poolSize: poolSize,
      currentCycle: currentCycle,
      totalCycles: state.totalCycles || 1,
      activeSenderIndex: activeIndex,
      activeSender: state.sendersPool[activeIndex] || null,
      sendersPool: state.sendersPool,
      minDelay: state.minDelay,
      maxDelay: state.maxDelay,
      remainingSeconds: state.remainingSeconds,
      assignMode: state.assignMode,
      startedAt: state.startedAt,
      updatedAt: state.updatedAt,
      logs: state.logs.slice(-60),
      audience: state.audience.map(c => ({
        id: c.id,
        phone: c.phone,
        display: c.display,
        name: c.name,
        city: c.city,
        network: c.network,
        status: c.status,
        senderId: c.senderId,
        senderName: c.senderName,
        senderRole: c.senderRole,
        senderPhone: c.senderPhone,
        cycle: c.cycle,
        senderIndex: c.senderIndex,
        sentAt: c.sentAt,
        error: c.error
      }))
    };
  }

  loadState();

  return {
    start,
    pause,
    resume,
    stop,
    getStatus
  };
}

module.exports = {
  createCampaignManager,
  resolveSpintax,
  personalizeMessage
};
