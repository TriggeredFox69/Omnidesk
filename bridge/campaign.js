'use strict';
const fs = require('node:fs');
const path = require('node:path');
const { randomUUID } = require('node:crypto');
const { store: chatStore, key9 } = require('./store');

// Today's date in Pakistan (UTC+5, no daylight saving), Y-m-d. Daily message counts belong
// to this day, the same day api/quota.php uses, so they reset at 12:00 AM PKT.
function pakistanDay(now = Date.now()) {
  return new Date(now + 5 * 3600 * 1000).toISOString().slice(0, 10);
}

function resolveSpintax(text) {
  if (!text) return '';
  return text.replace(/\{([^{}]+)\}/g, (match, contents) => {
    // {name|Bhai|Trader} is a placeholder with fallbacks, not a random choice: leave
    // it for personalizeMessage (picking randomly sent people the literal word "name").
    const head = contents.split('|')[0].trim().toLowerCase();
    if (['name', 'phone', 'city', 'plan', 'price', 'coupon', 'link', 'company'].includes(head)) {
      return match;
    }
    if (contents.includes('|')) {
      const parts = contents.split('|');
      return parts[Math.floor(Math.random() * parts.length)].trim();
    }
    return contents;
  });
}

function personalizeMessage(template, contact, companyName = 'Chatly', link = '') {
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
    .replace(/\{coupon\}/gi, 'CHATLY30');
  // {link} = the company's own link (admin portal setting). Never chatly.pk: cold campaign messages
  // carrying Chatly's domain got it listed as "Spam URLs" by web filters (FortiGuard). No link set:
  // the tag is left out and the spacing tidied.
  const lk = String(link || '').trim();
  if (lk) msg = msg.replace(/\{link\}/gi, lk);
  else msg = msg.replace(/[ \t]*\{link\}[ \t]*/gi, ' ').replace(/[ \t]{2,}/g, ' ').replace(/[ \t]+\n/g, '\n').replace(/\n[ \t]+/g, '\n').trim();

  return msg;
}

function createCampaignManager({ send, deviceSend, deviceReady, mainStatus, inbox, dataDir, getLeadsPath, getUsersPath, offlineGraceMs, stateFile: stateFileOption, store: storeOverride, isBusyElsewhere }) {
  const digits = v => String(v || '').replace(/\D/g, '');
  const chatStoreRef = storeOverride || chatStore();   // chats in Supabase while its switch is on
  const samePhone = (a, b) => { a = digits(a); b = digits(b); return !!a && !!b && (a === b || (a.length >= 9 && b.length >= 9 && a.slice(-9) === b.slice(-9))); };
  const isMainOnline = () => { const m = typeof mainStatus === 'function' ? mainStatus() : null; return !!(m && m.ready); };
  const viaMain = s => isMainOnline();
  // One manager per company, each with its own state file (see service.js).
  const stateFile = stateFileOption || path.join(dataDir, 'campaign_state.json');

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
    closersPool: [],
    lanes: [],
    activeLaneId: null
  };

  function log(message, type = 'info') {
    const time = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' });
    const entry = { time, message, type };
    state.logs.push(entry);
    if (state.logs.length > 500) state.logs.shift();
    saveState();
  }

  // The state file holds the whole audience (10k-100k numbers). Writing it after every
  // message blocked the bridge (and every WhatsApp socket) for hundreds of ms, so writes
  // are batched: at most one compact write every SAVE_EVERY_MS, and an immediate write on
  // start / pause / resume / stop / finish.
  // Big audiences make the file big (100k numbers ~ 40 MB), so they are saved less often.
  const saveEveryMs = () => (state.audience.length >= 50000 ? 5000 : state.audience.length >= 20000 ? 3000 : 1500);
  let saveTimer = null;
  function writeStateNow() {
    if (saveTimer) { clearTimeout(saveTimer); saveTimer = null; }
    try {
      state.updatedAt = Date.now();
      const tmp = stateFile + '.tmp';
      fs.writeFileSync(tmp, JSON.stringify(state), 'utf8');
      fs.renameSync(tmp, stateFile);
    } catch (_) {}
  }
  function saveState() {
    state.updatedAt = Date.now();
    if (!saveTimer) saveTimer = setTimeout(writeStateNow, saveEveryMs());
  }

  function loadState() {
    try {
      if (fs.existsSync(stateFile)) {
        const loaded = JSON.parse(fs.readFileSync(stateFile, 'utf8'));
        if (loaded && typeof loaded === 'object') {
          state = { ...state, ...loaded };
          (state.audience || []).forEach(c => { if (c.status === 'sending') c.status = 'pending'; });
          if (state.status === 'running') {
            state.status = 'paused';
            state.resumeAfterRestart = true;
            log('Server restarted. The campaign will continue by itself as soon as a team WhatsApp is back online.', 'warn');
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

    // No invented closer: a company with no active TM/TL has nobody to send from.
    return [];
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

  // Per-TM "messages sent today" counters are collected in memory and written to
  // users.json together with the CRM batch (one read + one write every few seconds).
  const pendingUserCounts = new Map();
  function incrementUserMessageCount(userId) {
    if (!userId) return;
    pendingUserCounts.set(userId, (pendingUserCounts.get(userId) || 0) + 1);
    scheduleCrmFlush();
  }
  function flushUserCounts() {
    if (!pendingUserCounts.size) return;
    const counts = new Map(pendingUserCounts);
    pendingUserCounts.clear();
    try {
      const usersFile = typeof getUsersPath === 'function' ? getUsersPath() : path.join(dataDir, '../users.json');
      if (!fs.existsSync(usersFile)) return;
      const users = JSON.parse(fs.readFileSync(usersFile, 'utf8') || '[]');
      if (!Array.isArray(users)) return;
      let changed = false;
      const today = pakistanDay();
      for (const user of users) {
        const n = counts.get(user.id);
        if (!n) continue;
        // A count from an earlier day starts again at 0 (daily limits reset at 12:00 AM PKT).
        // Without this, yesterday's count kept growing, and a later reset elsewhere wiped
        // today's sends with it.
        if (user.quota_reset_date !== today) { user.messages_sent_today = 0; user.quota_reset_date = today; }
        user.messages_sent_today = (Number(user.messages_sent_today) || 0) + n;
        changed = true;
      }
      if (changed) {
        const tmp = usersFile + '.tmp.' + process.pid;
        fs.writeFileSync(tmp, JSON.stringify(users, null, 2), 'utf8');
        fs.renameSync(tmp, usersFile);
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

  function syncAudienceToCrm(audience, companyId, messageTemplate, campaignName) {
    try {
      const cleanId = String(companyId || 'COMP-001').replace(/[^a-zA-Z0-9_-]/g, '') || 'COMP-001';
      const tenantPath = path.resolve(__dirname, `../data/tenants/${cleanId}/leads.json`);
      const globalPath = path.resolve(__dirname, '../data/leads.json');
      const targetPaths = [tenantPath];
      if (cleanId === 'COMP-001' && globalPath !== tenantPath) {
        targetPaths.push(globalPath);
      }

      let leads = [];
      let sourceFile = fs.existsSync(tenantPath) ? tenantPath : (fs.existsSync(globalPath) ? globalPath : null);
      if (sourceFile) {
        try {
          leads = JSON.parse(fs.readFileSync(sourceFile, 'utf8'));
        } catch (_) {}
      }
      if (!Array.isArray(leads)) leads = [];

      let modified = false;
      const nowStr = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
      const nowTs = Math.floor(Date.now() / 1000);

      for (const contact of audience) {
        const cleanPhone = String(contact.phone || '').replace(/\D/g, '');
        if (!cleanPhone || cleanPhone.length < 8) continue;

        let existing = leads.find(l => String(l.phone || '').replace(/\D/g, '') === cleanPhone);
        const ownerId = contact.assignedOwnerId || null;
        const ownerName = contact.assignedOwnerName || '';
        const ownerUsername = contact.assignedOwnerUsername || '';
        const personalizedMsg = personalizeMessage(messageTemplate, contact, state.companyName, state.campaignLink);

        if (existing) {
          existing.ownerId = ownerId;
          existing.ownerName = ownerName;
          existing.ownerUsername = ownerUsername;
          if (existing.leadState !== 'paid' && existing.leadState !== 'pending_verification') {
            existing.leadState = 'fresh';
          }
          existing.status = 'queued';
          existing.lastActive = 'Just now';
          existing.lastMessageAt = nowTs;
          existing.messages = existing.messages || [];
          modified = true;
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
            sender_id: ownerId,
            sender_name: ownerName,
            sender_phone: contact.senderPhone || null,
            leadState: 'fresh',
            status: 'queued',
            unreadCount: 0,
            interest: 'Campaign Outreach',
            lastActive: 'Just now',
            lastMessageAt: nowTs,
            notes: `Queued for outreach via campaign "${campaignName}". Allocated Closer: ${ownerName}.`,
            messages: []
          };
          leads.unshift(newLead);
          modified = true;
        }
      }

      if (modified) {
        for (const targetFile of targetPaths) {
          try {
            const dir = path.dirname(targetFile);
            if (!fs.existsSync(dir)) fs.mkdirSync(dir, { recursive: true });
            const tmp = targetFile + '.tmp.' + Date.now();
            fs.writeFileSync(tmp, JSON.stringify(leads, null, 2), 'utf8');
            try {
              fs.renameSync(tmp, targetFile);
            } catch (_) {
              fs.writeFileSync(targetFile, JSON.stringify(leads, null, 2), 'utf8');
              try { fs.unlinkSync(tmp); } catch (_) {}
            }
          } catch (e) {
            console.warn('[Campaign syncAudienceToCrm warning]:', e.message);
          }
        }
      }
    } catch (err) {
      console.warn('[Campaign syncAudienceToCrm error]:', err.message);
    }
  }

  // ── CRM updates ──────────────────────────────────────────────────────────────
  // Every sent message records itself on the receiver's CRM lead (so a reply opens the
  // right chat, owned by the TM who sent it). Reading + rewriting a company's whole
  // leads.json for EVERY message froze the bridge on big CRMs, so sends are queued and
  // applied in one read + one write every CRM_FLUSH_MS (and at pause / stop / finish).
  const CRM_FLUSH_MS = 3000;
  const crmQueue = [];
  let crmTimer = null;
  function scheduleCrmFlush() {
    if (!crmTimer) crmTimer = setTimeout(flushCrm, CRM_FLUSH_MS);
  }
  function updateCrmLead(contact, crmMessage, assignMode, activeSender) {
    crmQueue.push({
      companyId: (activeSender && activeSender.companyId) || state.companyId || 'COMP-001', campaignName: state.name,
      closersPool: (activeSender && activeSender.companyId) ? [] : (state.closersPool || []),
      contact: { phone: contact.phone, name: contact.name, display: contact.display, city: contact.city, network: contact.network,
        assignedOwnerId: contact.assignedOwnerId, assignedOwnerName: contact.assignedOwnerName,
        assignedOwnerUsername: contact.assignedOwnerUsername, assignedOwnerRole: contact.assignedOwnerRole },
      crmMessage, activeSender: activeSender || null
    });
    scheduleCrmFlush();
  }

  function applyCrmItem(leads, byPhone, cleanId, item) {
    const { contact, crmMessage, activeSender } = item;
    const cleanPhone = String(contact.phone || '').replace(/\D/g, '');
    if (!cleanPhone) return;
    const existingLead = byPhone.get(cleanPhone);

    // STRICT LEAD ALLOCATION RULE: the lead belongs to the closer (TM / TL) whose device sent it.
    let ownerId = (activeSender && activeSender.roleType !== 'admin') ? activeSender.id : contact.assignedOwnerId;
    let ownerName = (activeSender && activeSender.roleType !== 'admin') ? activeSender.name : contact.assignedOwnerName;
    let ownerUsername = (activeSender && activeSender.roleType !== 'admin') ? (activeSender.username || activeSender.name) : contact.assignedOwnerUsername;
    if (!ownerId && item.closersPool.length > 0) {
      const fallbackCloser = item.closersPool[0];
      ownerId = fallbackCloser.id;
      ownerName = fallbackCloser.name;
      ownerUsername = fallbackCloser.username || '';
    }

    // The chat list is ordered by the newest message, so every campaign send carries its time.
    const sentAt = Number(crmMessage.timestamp) || Math.floor(Date.now() / 1000);
    if (existingLead) {
      existingLead.messages = existingLead.messages || [];
      if (!existingLead.messages.some(m => m.waMessageId === crmMessage.waMessageId)) existingLead.messages.push(crmMessage);
      existingLead.lastMessageAt = Math.max(Number(existingLead.lastMessageAt) || 0, sentAt);
      existingLead.lastActive = 'Just now';
      existingLead.status = 'sent';
      if (existingLead.leadState !== 'paid' && existingLead.leadState !== 'pending_verification') existingLead.leadState = 'ongoing';
      if (ownerId && (!existingLead.ownerId || existingLead.ownerId.includes('ADMIN') || existingLead.ownerId.includes('FALLBACK') || existingLead.ownerId === 'USR-ADMIN-01')) {
        existingLead.ownerId = ownerId;
        existingLead.ownerName = ownerName;
        existingLead.ownerUsername = ownerUsername;
      }
      existingLead.lastSenderPhone = activeSender ? activeSender.phone : null;
      existingLead.lastSenderName = activeSender ? activeSender.name : null;
      return;
    }
    const newLead = {
      id: `lead_${Date.now()}_${cleanPhone}`,
      company_id: cleanId,
      name: contact.name || contact.display || `+${cleanPhone}`,
      phone: cleanPhone,
      display: contact.display || `+${cleanPhone}`,
      city: contact.city || 'Pakistan',
      network: contact.network || 'WhatsApp',
      ownerId, ownerName, ownerUsername,
      sender_id: activeSender ? activeSender.id : ownerId,
      sender_name: activeSender ? activeSender.name : ownerName,
      sender_phone: activeSender ? activeSender.phone : null,
      leadState: 'ongoing',
      status: 'sent',
      unreadCount: 0,
      interest: 'Campaign Outreach',
      lastActive: 'Just now',
      lastMessageAt: sentAt,
      notes: `Outreach dispatched via campaign "${item.campaignName}". Allocated Closer: ${ownerName || 'TM Closer'} (${activeSender?.role || contact.assignedOwnerRole || 'Closer'}). Device: ${activeSender ? activeSender.name : 'Primary Device'}.`,
      messages: [crmMessage]
    };
    leads.unshift(newLead);
    byPhone.set(cleanPhone, newLead);
  }

  // Chats in Supabase: the batch's leads are looked up in one call, changed / created in memory
  // with the same rules (applyCrmItem), then saved with their new messages. If Supabase is
  // unreachable the batch goes back in the queue and is retried.
  let crmStoreBusy = false, crmStoreFailures = 0;
  async function flushCrmToStore(byCompany) {
    crmStoreBusy = true;
    try {
      for (const [cleanId, items] of byCompany) {
        try {
          const phones = [...new Set(items.map(i => digits(i.contact.phone)).filter(Boolean))];
          const found = await chatStoreRef.findLeads(cleanId, phones);
          const byKey = new Map();
          for (const l of found) { const k = key9(l.phone); if (k && !byKey.has(k)) byKey.set(k, l); }
          const byPhone = new Map();
          for (const p of phones) { const l = byKey.get(key9(p)); if (l) { l.messages = []; byPhone.set(p, l); } }
          const created = [];
          for (const item of items) applyCrmItem(created, byPhone, cleanId, item);
          const leads = [...new Set(byPhone.values())];
          const msgItems = [];
          for (const l of leads) {
            for (const m of l.messages || []) msgItems.push({ phone: digits(l.phone), msg: m, touch: false });
            delete l.messages; delete l.messageCount;
          }
          await chatStoreRef.saveLeads(cleanId, leads);
          if (msgItems.length) await chatStoreRef.addMessagesNow(cleanId, msgItems);
          crmStoreFailures = 0;
        } catch (err) {
          crmStoreFailures++;
          if (crmStoreFailures === 1 || crmStoreFailures % 20 === 0) console.warn('[Campaign] Saving chats to Supabase failed, will retry:', err.message);
          crmQueue.unshift(...items);
        }
      }
    } finally {
      crmStoreBusy = false;
      if (crmQueue.length) {
        if (!crmTimer) crmTimer = setTimeout(flushCrm, crmStoreFailures ? Math.min(30000, 1000 * 2 ** Math.min(crmStoreFailures, 5)) : CRM_FLUSH_MS);
      }
    }
  }

  function flushCrm() {
    if (crmTimer) { clearTimeout(crmTimer); crmTimer = null; }
    flushUserCounts();
    if (!crmQueue.length) return;
    if (chatStoreRef.enabled() && crmStoreBusy) { scheduleCrmFlush(); return; }
    const batch = crmQueue.splice(0, crmQueue.length);
    const byCompany = new Map();
    for (const item of batch) {
      const id = String(item.companyId).replace(/[^a-zA-Z0-9_-]/g, '') || 'COMP-001';
      if (!byCompany.has(id)) byCompany.set(id, []);
      byCompany.get(id).push(item);
    }
    if (chatStoreRef.enabled()) { flushCrmToStore(byCompany); return; }
    for (const [cleanId, items] of byCompany) {
      try {
        // The company's CRM file (service.js passes getLeadsPath; same tenant path as below).
        const tenantPath = (typeof getLeadsPath === 'function' && getLeadsPath(cleanId)) || path.resolve(__dirname, `../data/tenants/${cleanId}/leads.json`);
        const globalPath = path.resolve(__dirname, '../data/leads.json');
        const targetPaths = [tenantPath];
        // Nunno keeps a legacy mirror at data/leads.json (only if it exists).
        const sameDataRoot = path.resolve(tenantPath, '../../..') === path.dirname(globalPath);
        if (cleanId === 'COMP-001' && sameDataRoot && globalPath !== tenantPath && fs.existsSync(globalPath)) targetPaths.push(globalPath);

        let leads = [];
        const sourceFile = fs.existsSync(tenantPath) ? tenantPath : (fs.existsSync(globalPath) ? globalPath : null);
        if (sourceFile) { try { leads = JSON.parse(fs.readFileSync(sourceFile, 'utf8')); } catch (_) {} }
        if (!Array.isArray(leads)) leads = [];
        const byPhone = new Map();
        for (const l of leads) {
          const p = String(l.phone || '').replace(/\D/g, '');
          if (p && !byPhone.has(p)) byPhone.set(p, l);
        }
        for (const item of items) applyCrmItem(leads, byPhone, cleanId, item);

        const body = JSON.stringify(leads, null, 2);
        for (const targetFile of targetPaths) {
          try {
            fs.mkdirSync(path.dirname(targetFile), { recursive: true });
            const tmp = targetFile + '.bridge-' + process.pid + '.tmp';
            fs.writeFileSync(tmp, body, 'utf8');
            try { fs.renameSync(tmp, targetFile); }
            catch (_) { fs.writeFileSync(targetFile, body, 'utf8'); try { fs.unlinkSync(tmp); } catch (_) {} }
          } catch (fErr) {
            console.warn(`[Campaign] Failed writing leads to ${targetFile}:`, fErr.message);
          }
        }
      } catch (err) {
        console.warn('[Campaign CRM Sync Error]:', err.message);
      }
    }
  }

  // ───────────────────────── Equal-split, per-TM lanes ─────────────────────────
  // Every TM in the campaign whose own WhatsApp is connected gets a lane. The
  // dropped numbers are split equally (contact i -> lane i % n), and every lane
  // sends from its TM's own number in parallel. If a TM's WhatsApp drops for more
  // than OFFLINE_GRACE_MS, their unsent numbers move to the TMs still connected,
  // so the campaign always runs until every number has been messaged.
  const OFFLINE_GRACE_MS = offlineGraceMs || 15000;
  // The company chooses the gap between messages (one number, 1-60 s). Below ~6 s numbers get
  // restricted much faster; the campaign page colours that red, and the choice is theirs.
  const MIN_DELAY_S = 1;
  const MAX_DELAY_S = 60;
  const clampDelay = v => Math.min(MAX_DELAY_S, Math.max(MIN_DELAY_S, Math.round(Number(v) || 0) || MIN_DELAY_S));
  const laneTimers = new Map();
  const laneBusy = new Set();

  // ── Campaign media (image / voice note) lives in a file, not inside the state JSON ──
  // The state file is rewritten every few seconds; a base64 image or voice note inside it
  // made every one of those writes megabytes bigger.
  const mediaDir = path.join(dataDir || path.dirname(stateFile), 'campaign_media');
  const mediaCache = new Map();          // file -> base64 (only the current campaign's file)
  function storeCampaignMedia(companyId, campaignId, media) {
    if (!media || !media.data) return media || null;
    try {
      fs.mkdirSync(mediaDir, { recursive: true });
      const safeCompany = String(companyId || 'COMP').replace(/[^a-zA-Z0-9_-]/g, '');
      // drop this company's media from earlier campaigns (other companies' files stay)
      for (const f of fs.readdirSync(mediaDir)) if (f.startsWith(safeCompany + '__')) { try { fs.unlinkSync(path.join(mediaDir, f)); } catch (_) {} }
      const fromName = /\.([a-z0-9]{1,5})$/i.exec(String(media.filename || ''));
      const ext = (fromName ? fromName[1] : String(media.mimetype || 'bin').split('/')[1] || 'bin').split(';')[0].replace(/[^a-z0-9]/gi, '') || 'bin';
      const file = path.join(mediaDir, `${safeCompany}__${campaignId}.${ext}`);
      fs.writeFileSync(file, Buffer.from(media.data, 'base64'));
      mediaCache.clear();
      mediaCache.set(file, media.data);
      const { data, ...rest } = media;
      return { ...rest, file };
    } catch (err) {
      console.warn('[Campaign] Could not store campaign media as a file, keeping it inline:', err.message);
      return media;
    }
  }
  function mediaForSend(media) {
    if (!media || !media.file) return media || null;
    let data = mediaCache.get(media.file);
    if (!data) {
      data = fs.readFileSync(media.file).toString('base64');
      mediaCache.clear();
      mediaCache.set(media.file, data);
    }
    const { file, ...rest } = media;
    return { ...rest, data };
  }

  const DONE = new Set(['accepted', 'sent', 'delivered', 'read', 'failed']);
  const isDone = c => DONE.has(c.status);
  // Progress bookkeeping without rescanning the whole audience on every message:
  // doneCount = numbers finished (sent or failed); laneCursor = where each TM's next
  // pending number is. Both are rebuilt with recount() after loads, resumes and rebalances.
  let doneCount = 0;
  const laneCursor = new Map();
  function recount() {
    doneCount = 0;
    for (const c of state.audience) if (isDone(c)) doneCount++;
    laneCursor.clear();
    state.currentIndex = doneCount;
  }
  function nextPendingFor(laneId) {
    const list = state.audience;
    for (let i = laneCursor.get(laneId) || 0; i < list.length; i++) {
      const c = list[i];
      if (c.laneId === laneId && c.status === 'pending') { laneCursor.set(laneId, i); return c; }
    }
    laneCursor.set(laneId, list.length);
    return null;
  }
  const laneOf = id => (state.lanes || []).find(l => l.id === id);
  const deviceOnline = lane => lane.mode === 'main' ? isMainOnline() : (typeof deviceReady === 'function' && deviceReady(lane.id));
  // companyId: the closer's own company. In the super admin's Ultimate Campaign the lanes come
  // from every company, and each chat goes to the sending closer's own company inbox.
  const laneSender = lane => ({ id: lane.id, name: lane.name, username: lane.username, role: lane.role, roleType: lane.roleType, phone: lane.phone, companyId: lane.companyId || null });

  function clearLaneTimers() {
    for (const t of laneTimers.values()) clearTimeout(t);
    laneTimers.clear();
  }

  function scheduleLane(lane, ms) {
    if (laneTimers.has(lane.id)) clearTimeout(laneTimers.get(lane.id));
    laneTimers.set(lane.id, setTimeout(() => {
      laneTimers.delete(lane.id);
      runLane(lane.id).catch(err => log('Lane error: ' + err.message, 'error'));
    }, ms));
  }

  function rebalanceFrom(lane, why = "'s WhatsApp is offline") {
    const pending = state.audience.filter(c => c.laneId === lane.id && c.status === 'pending');
    const targets = state.lanes.filter(l => l.id !== lane.id && l.status !== 'offline' && l.status !== 'stopped'
      && !(l.pausedUntil && l.pausedUntil > Date.now()) && !laneHeldBack(l.id) && deviceOnline(l));
    if (!pending.length || !targets.length) return false;
    pending.forEach((c, i) => {
      const t = targets[i % targets.length];
      c.laneId = t.id;
      c.assignedOwnerId = t.id;
      c.assignedOwnerName = t.name;
      c.assignedOwnerUsername = t.username || t.name;
      c.assignedOwnerRole = t.role;
      t.assigned++;
    });
    lane.assigned -= pending.length;
    laneCursor.clear();
    const whyText = why.startsWith("'") ? `${lane.name}${why}` : `${lane.name} ${why}`;
    log(`🔁 ${whyText}. Moved their ${pending.length} unsent numbers to ${targets.length > 5 ? targets.length + ' other closers' : targets.map(t => t.name).join(', ')}.`, 'warn');
    targets.forEach(t => {
      if (t.status === 'done') t.status = 'ready';
      if (!laneTimers.has(t.id) && !laneBusy.has(t.id)) scheduleLane(t, 500);
    });
    saveState();
    return true;
  }

  // ── Protecting the team's numbers ──────────────────────────────────────────────
  // 1. WhatsApp refusing a number's messages means it is limiting (restricting) that number.
  //    After REFUSALS_TO_PAUSE refusals within an hour the number stops sending for 24 hours.
  //    Sending on regardless is how a 24-hour restriction becomes a permanent ban. Its unsent
  //    numbers wait; they are NOT moved to other closers (that pushes the same volume onto
  //    them and gets them limited too).
  // 2. Each closer's daily message limit (admin panel; resets 12:00 AM PKT) applies to
  //    campaigns too, not only to messages typed in the Sales Desk.
  const REFUSALS_TO_PAUSE = 2;
  const REFUSAL_WINDOW_MS = 3600 * 1000;
  const RESTRICT_PAUSE_MS = 24 * 3600 * 1000;
  const sentBy = new Map();   // WhatsApp message id -> { laneId, contact } (recent campaign sends)
  const nextPakistanMidnight = (now = Date.now()) => (Math.floor((now + 5 * 3600e3) / 86400e3) + 1) * 86400e3 - 5 * 3600e3;
  let laneUsers = { at: 0, byId: new Map() };
  function laneUser(id) {
    if (Date.now() - laneUsers.at > 10000) {
      const byId = new Map();
      try {
        const usersFile = typeof getUsersPath === 'function' ? getUsersPath() : path.join(dataDir, '../users.json');
        for (const u of JSON.parse(fs.readFileSync(usersFile, 'utf8') || '[]')) if (u && u.id) byId.set(String(u.id), u);
      } catch (_) {}
      laneUsers = { at: Date.now(), byId };
    }
    return laneUsers.byId.get(String(id)) || null;
  }
  // Stopped by the admin, or a Nunno TM whose plan is not verified yet (Team Inbox locked):
  // neither gets campaign numbers.
  function laneHeldBack(id) {
    const u = laneUser(id);
    return !!(u && (u.campaign_paused || (u.plan_locked && !u.has_nunno_plan)));
  }
  function dailyLimitReached(laneId) {
    const u = laneUser(laneId);
    const limit = Number(u && u.message_limit);
    if (!u || !(limit > 0)) return false;   // -1 / missing = unlimited
    const today = u.quota_reset_date === pakistanDay() ? (Number(u.messages_sent_today) || 0) : 0;
    return today + (pendingUserCounts.get(laneId) || 0) >= limit;
  }
  function pauseLane(lane, reason, until, message) {
    lane.pausedUntil = until;
    lane.pauseReason = reason;
    lane.status = 'limited';
    lane.nextAt = null;
    if (laneTimers.has(lane.id)) { clearTimeout(laneTimers.get(lane.id)); laneTimers.delete(lane.id); }
    log(message, 'warn');
    saveState();
  }
  function recordRefusal(lane) {
    const now = Date.now();
    lane.refusals = (lane.refusals || []).filter(t => now - t < REFUSAL_WINDOW_MS);
    lane.refusals.push(now);
    if (lane.refusals.length >= REFUSALS_TO_PAUSE && !(lane.pausedUntil > now)) {
      pauseLane(lane, 'restricted', now + RESTRICT_PAUSE_MS,
        `🛑 WhatsApp refused ${lane.refusals.length} messages from ${lane.name} (${lane.display}): it is limiting this number. ` +
        `Chatly stopped sending from it for 24 hours to protect it from a ban. Its remaining numbers wait.`);
      if (state.status === 'running') scheduleLane(lane, 10 * 60 * 1000);
    }
  }
  // Delivery receipts from the bridge: -1 = WhatsApp refused the message.
  function noteAck(messageId, ack) {
    if (Number(ack) !== -1 || !messageId) return;
    const hit = sentBy.get(messageId);
    if (!hit) return;
    sentBy.delete(messageId);
    const lane = laneOf(hit.laneId);
    const c = hit.contact;
    if (c && c.status !== 'failed') {
      c.status = 'failed';
      c.error = 'Refused by WhatsApp';
      state.totalSent = Math.max(0, state.totalSent - 1);
      state.totalFailed++;
      if (lane) { lane.sent = Math.max(0, (lane.sent || 0) - 1); lane.failed = (lane.failed || 0) + 1; }
    }
    if (lane) recordRefusal(lane);
    saveState();
  }

  function checkFinish() {
    if (state.status !== 'running') return;
    if (state.audience.length && doneCount >= state.audience.length) {
      recount();                                    // confirm before finishing
      if (doneCount >= state.audience.length) finish();
    }
  }

  async function runLane(laneId) {
    const lane = laneOf(laneId);
    if (!lane || state.status !== 'running' || laneBusy.has(laneId)) return;
    const contact = nextPendingFor(laneId);
    if (!contact) { lane.status = 'done'; saveState(); checkFinish(); return; }

    // Paused to protect the number (WhatsApp limiting it, or its daily limit): wait it out.
    const nowMs = Date.now();
    if (lane.pausedUntil && nowMs < lane.pausedUntil) {
      lane.status = 'limited';
      scheduleLane(lane, Math.min(lane.pausedUntil - nowMs, 10 * 60 * 1000));
      return;
    }
    if (lane.pausedUntil) {
      log(`▶️ ${lane.name}'s pause is over. Sending from their number again.`, 'info');
      lane.pausedUntil = null; lane.pauseReason = null; lane.refusals = [];
    }
    if (dailyLimitReached(lane.id)) {
      pauseLane(lane, 'daily_limit', nextPakistanMidnight(nowMs),
        `⏸️ ${lane.name} reached their daily message limit. Their remaining numbers continue after 12:00 AM.`);
      scheduleLane(lane, Math.min(lane.pausedUntil - nowMs, 10 * 60 * 1000));
      return;
    }
    // Stopped by the admin (admin portal → Stop): no more campaign messages for this closer.
    // Their unsent numbers move to the closers still sending; if there are none, they wait.
    if (laneHeldBack(lane.id)) {
      if (lane.status !== 'stopped') log(`⏹️ ${lane.name} was stopped by the admin and gets no more campaign messages.`, 'warn');
      lane.status = 'stopped';
      lane.nextAt = null;
      if (rebalanceFrom(lane, 'was stopped by the admin')) { lane.status = 'done'; saveState(); return; }
      saveState();
      scheduleLane(lane, 30000);
      return;
    }
    if (lane.status === 'stopped') log(`▶️ ${lane.name} was resumed by the admin.`, 'info');

    if (!deviceOnline(lane)) {
      if (lane.status !== 'offline') {
        lane.status = 'offline';
        lane.offlineSince = Date.now();
        log(`📴 ${lane.name}'s WhatsApp is not connected. Waiting for it to reconnect...`, 'warn');
      } else if (Date.now() - (lane.offlineSince || 0) > OFFLINE_GRACE_MS) {
        if (rebalanceFrom(lane)) { lane.status = 'done'; saveState(); return; }
      }
      saveState();
      scheduleLane(lane, 5000);
      return;
    }
    if (lane.status === 'offline') log(`📶 ${lane.name}'s WhatsApp is back. Resuming their numbers.`, 'info');
    lane.status = 'sending';
    lane.offlineSince = null;

    laneBusy.add(laneId);
    let notOnWhatsApp = false;
    contact.status = 'sending';
    contact.requestId = contact.requestId || randomUUID();
    contact.senderId = lane.id;
    contact.senderName = lane.name;
    contact.senderRole = lane.role;
    contact.senderPhone = lane.phone;
    const personalizedMsg = personalizeMessage(state.messageTemplate, contact, state.companyName, state.campaignLink);   // not stored per contact (keeps the state file small)
    state.activeLaneId = lane.id;
    saveState();
    const attemptStart = Date.now();
    const sinceLast = lane.lastAttemptAt ? Math.round((attemptStart - lane.lastAttemptAt) / 1000) : null;
    lane.lastAttemptAt = attemptStart;

    try {
      const payload = { phone: contact.phone, message: personalizedMsg, request_id: contact.requestId, simulate: false, allow_simulation: false };
      if (state.media) payload.media = mediaForSend(state.media);
      const result = lane.mode === 'main' ? await send(payload) : await deviceSend(lane.id, payload);
      if (result && result.simulated) throw new Error('WhatsApp is not connected.');

      contact.status = 'accepted';
      contact.sentAt = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' });
      contact.messageId = result?.message_id || ('msg_' + Date.now());
      state.totalSent++;
      lane.sent++;
      lane.lastSentAt = contact.sentAt;
      incrementUserMessageCount(lane.id);
      sentBy.set(contact.messageId, { laneId: lane.id, contact });   // a later "refused" receipt pauses the number
      if (sentBy.size > 5000) sentBy.delete(sentBy.keys().next().value);
      updateCrmLead(contact, {
        id: `msg_c_${Date.now()}`, waMessageId: contact.messageId, timestamp: Math.floor(Date.now() / 1000), deliveryStatus: 'accepted', sender: 'agent',
        agentName: lane.name, senderPhone: lane.phone, senderRole: lane.role, text: personalizedMsg, time: contact.sentAt
      }, state.assignMode, laneSender(lane));
      if (inbox && typeof inbox.ingest === 'function') {
        const cleanPhone = String(contact.phone || '').replace(/\D/g, '');
        inbox.ingest({
          id: { _serialized: contact.messageId, fromMe: true, remote: cleanPhone + '@s.whatsapp.net' },
          fromMe: true,
          type: state.media ? (state.media.mimetype?.startsWith('audio') ? 'ptt' : (state.media.mimetype?.startsWith('image') ? 'image' : 'document')) : 'chat',
          body: personalizedMsg,
          timestamp: Math.floor(Date.now() / 1000),
          ack: 0
        }, cleanPhone, { phone: cleanPhone, agentName: lane.name, viaUserId: lane.id, viaNumber: lane.phone, fromChatly: true }).catch(() => {});
      }
      // the real timing, so the log shows what actually happened (send time and gap since the last one)
      const took = ((Date.now() - attemptStart) / 1000).toFixed(1);
      log(`✅ ${lane.name} (${lane.display}) ➔ ${contact.display || contact.phone}  [${lane.sent + lane.failed}/${lane.assigned}] · sent in ${took}s${sinceLast !== null ? ` · ${sinceLast}s after the previous` : ''}`, 'success');
    } catch (err) {
      // A send that died because the TM's WhatsApp dropped ("Connection Closed", or a
      // submission that never confirmed while the device went offline) is retried after the
      // reconnect instead of being marked failed. At most twice per number.
      const msg = String((err && err.message) || '');
      const dropped = err && (err.deviceOffline
        || /connection (closed|terminated|lost)|not connected|socket/i.test(msg)
        || (/unconfirmed/i.test(msg) && !deviceOnline(lane)));
      if (dropped && (contact.retries || 0) < 2) {
        contact.retries = (contact.retries || 0) + 1;
        contact.status = 'pending';            // not sent: retry after reconnect, or move to another TM
        lane.status = 'offline';
        lane.offlineSince = lane.offlineSince || Date.now();
        log(`📴 ${lane.name}'s WhatsApp dropped. ${contact.display || contact.phone} will be retried.`, 'warn');
      } else {
        notOnWhatsApp = /not registered on WhatsApp/i.test(msg);
        contact.status = 'failed';
        contact.error = err.message;
        state.totalFailed++;
        lane.failed++;
        log(`❌ ${lane.name} ➔ ${contact.display || contact.phone}: ${err.message}`, 'error');
        // No CRM message for a send that never happened.
        // A number that is not on WhatsApp says nothing about this closer's number; any other
        // refusal may mean WhatsApp is limiting it.
        if (!notOnWhatsApp) recordRefusal(lane);
      }
    } finally {
      laneBusy.delete(laneId);
      if (isDone(contact)) doneCount++;
      state.currentIndex = doneCount;
      saveState();
    }

    if (state.status !== 'running') return;
    checkFinish();
    if (state.status !== 'running') return;
    if (lane.status === 'offline') { scheduleLane(lane, 5000); return; }
    if (lane.pausedUntil && Date.now() < lane.pausedUntil) return;   // recordRefusal already scheduled the re-check
    const minSec = clampDelay(state.minDelay || 10);
    const maxSec = Math.max(minSec, clampDelay(state.maxDelay || minSec));
    const gapSec = Math.floor(Math.random() * (maxSec - minSec + 1)) + minSec;   // one fixed gap when min = max
    // The gap counts from the START of this attempt, so "every 10 s" really is every 10 s even
    // when a send takes a few seconds. Applies to numbers not on WhatsApp too (rapid-fire
    // lookups look like scraping).
    const waitMs = Math.max(250, gapSec * 1000 - (Date.now() - attemptStart));
    lane.nextAt = Date.now() + waitMs;
    lane.status = 'waiting';
    saveState();
    scheduleLane(lane, waitMs);
  }

  function kickAllLanes() {
    (state.lanes || []).forEach((l, i) => scheduleLane(l, 150 + i * 400));   // small stagger between TMs
  }

  function finish() {
    clearLaneTimers();
    state.status = 'completed';
    state.remainingSeconds = 0;
    const per = (state.lanes || []).map(l => `${l.name}: ${l.sent}`).join(' · ');
    log(`🎉 Campaign completed! ${state.totalSent} sent, ${state.totalFailed} failed. ${per}`, 'success');
    flushCrm();
    writeStateNow();
  }

  function start(params = {}) {
    clearLaneTimers();
    laneBusy.clear();

    const companyId = params.company_id || params.companyId || 'COMP-001';
    const companyName = params.company_name || (companyId === 'COMP-004' ? 'Metalink' : (companyId === 'COMP-001' ? 'Nunno Official' : 'Chatly Partner'));

    // 1. Lanes: the TMs/TLs picked for this campaign whose own WhatsApp is connected.
    let picked = Array.isArray(params.senders) && params.senders.length ? params.senders : resolveCompanyClosers(companyId);
    picked = picked.filter(s => s && s.id && s.roleType !== 'admin' && s.roleType !== 'superadmin' && s.role !== 'Admin');
    const seenIds = new Set();
    picked = picked.filter(s => (seenIds.has(s.id) ? false : (seenIds.add(s.id), true)));
    // closers the admin stopped get no campaign numbers until resumed
    laneUsers.at = 0;   // read users.json fresh for the start
    const stoppedByAdmin = picked.filter(s => laneHeldBack(s.id));
    picked = picked.filter(s => !laneHeldBack(s.id));
    // A WhatsApp already sending another campaign (its company's, or the super admin's Ultimate
    // Campaign) is left out: two campaigns at once would double its sending speed.
    const busyElsewhere = picked.filter(s => typeof isBusyElsewhere === 'function' && isBusyElsewhere(s.id));
    picked = picked.filter(s => !busyElsewhere.includes(s));
    if (!picked.length && busyElsewhere.length) {
      return { success: false, status: state.status, message: 'Every WhatsApp picked for this campaign is already sending another campaign. Wait for it to finish, or stop it first.' };
    }
    const hasDevice = s => typeof deviceReady === 'function' && deviceReady(s.id);
    const anyDeviceReady = picked.some(s => hasDevice(s));
    const mainReady = isMainOnline();

    if (!picked.length) {
      return { success: false, status: state.status, message: stoppedByAdmin.length
        ? 'Every closer in this campaign is stopped in the admin portal. Resume at least one to send.'
        : 'No closers or team members found for this company to send campaign outreach.' };
    }

    if (!anyDeviceReady && !mainReady) {
      return { success: false, status: state.status, message: 'WhatsApp is not connected on the server. Please click "Link Phone / Scan QR" and scan the code using WhatsApp on your phone before starting the campaign.' };
    }

    // Distribute campaign across all company closers/employees
    let online = picked;
    const skipped = [];

    // 2. Audience: the dropped numbers (or the company's leads)
    let rawAudience = Array.isArray(params.audience) && params.audience.length > 0 ? params.audience : [];
    // (the old "message my existing leads" fallback reads leads.json, which is not used while chats are in Supabase)
    if (!rawAudience.length && !chatStoreRef.enabled()) {
      try {
        const leadsFile = typeof getLeadsPath === 'function' ? getLeadsPath(companyId) : null;
        if (leadsFile && fs.existsSync(leadsFile)) {
          const stored = JSON.parse(fs.readFileSync(leadsFile, 'utf8') || '[]');
          if (Array.isArray(stored)) {
            const filter = params.filter || 'all';
            rawAudience = filter === 'new' ? stored.filter(l => l.leadState === 'fresh' || l.status === 'new' || (!l.messages || l.messages.length <= 1))
              : filter === 'ongoing' ? stored.filter(l => l.leadState === 'ongoing') : stored;
          }
        }
      } catch (_) {}
    }
    const seenPhones = new Set();
    rawAudience = rawAudience.filter(c => {
      const p = String(c.phone || '').replace(/\D/g, '');
      if (p.length < 10 || seenPhones.has(p)) return false;
      seenPhones.add(p);
      return true;
    });
    if (!rawAudience.length) {
      return { success: false, status: state.status, message: 'No valid receiver numbers to message.' };
    }

    state.id = 'camp_' + Date.now();
    state.name = (params.name || '').trim() || `Outreach Campaign (${companyName})`;
    state.companyId = companyId;
    state.companyName = companyName;
    state.messageTemplate = params.message || '';
    state.variation = params.variation || 'text';
    state.autoData = Boolean(params.auto_data);   // numbers came from the Auto Data pool
    state.campaignLink = String(params.campaign_link || '').slice(0, 300);   // the company's own link for {link}
    state.media = storeCampaignMedia(companyId, state.id, params.media || null);
    if (params.image_url && !state.media) state.media = { mimetype: 'image/jpeg', filename: 'flyer.jpg', url: params.image_url };
    if (params.audio_url && !state.media) state.media = { mimetype: 'audio/ogg', filename: 'voice_pitch.ogg', url: params.audio_url };

    state.lanes = online.map((s, idx) => {
      const phone = String(s.phone || '').replace(/\D/g, '');
      const userHasDevice = hasDevice(s);
      return {
        index: idx + 1, id: s.id, name: s.name || `TM ${idx + 1}`, username: s.username || '',
        role: s.role || (s.roleType === 'lead' ? 'Team Leader (TL)' : 'TM Closer'), roleType: s.roleType || 'member',
        companyId: s.company_id ? String(s.company_id).replace(/[^a-zA-Z0-9_-]/g, '') : null, companyName: s.company_name || '',
        phone, display: s.device_number ? ('+' + s.device_number) : (s.display || (phone ? '+' + phone : '')),
        mode: userHasDevice ? 'device' : 'main',
        assigned: 0, sent: 0, failed: 0, status: 'ready', offlineSince: null, nextAt: null, lastSentAt: null
      };
    });
    const n = state.lanes.length;
    state.sendersPool = state.lanes;            // fields older UIs read
    state.closersPool = state.lanes;
    state.poolSize = n;

    // 3. Equal split: contact i -> lane i % n  (30 numbers, 3 TMs -> 10 each)
    state.audience = rawAudience.map((c, idx) => {
      const lane = state.lanes[idx % n];
      lane.assigned++;
      const phone = String(c.phone || '').replace(/\D/g, '');
      return {
        id: c.id || ('c_' + Math.random().toString(36).slice(2, 9)),
        phone, display: c.display || ('+' + phone), name: c.name || '', city: c.city || 'Pakistan', network: c.network || 'WhatsApp',
        status: 'pending', requestId: null, laneId: lane.id,
        senderId: null, senderName: null, senderRole: null, senderPhone: null,
        assignedOwnerId: lane.id, assignedOwnerName: lane.name, assignedOwnerUsername: lane.username || lane.name, assignedOwnerRole: lane.role,
        cycle: 1, sentAt: null, error: null
      };
    });

    // Note: Do NOT dump 10k-100k raw cold audience into CRM leads.json upfront!
    // Contacts are automatically registered as CRM leads in updateCrmLead() when a message is actually sent.

    state.currentIndex = 0;
    state.totalSent = 0;
    state.totalFailed = 0;
    // One gap for every message (the campaign page sends minDelay = maxDelay = the chosen gap).
    state.minDelay = clampDelay(parseInt(params.delay, 10) || parseInt(params.minDelay, 10) || 10);
    state.maxDelay = Math.max(state.minDelay, clampDelay(parseInt(params.maxDelay, 10) || state.minDelay));
    state.remainingSeconds = 0;
    state.assignMode = 'equal_split';
    state.status = 'running';
    state.startedAt = Date.now();
    state.logs = [];
    state.totalCycles = Math.ceil(state.audience.length / n);
    state.currentCycle = 1;

    log(`🚀 Campaign "${state.name}" launched for ${companyName}: ${state.audience.length} numbers split equally across ${n} Team Member${n > 1 ? 's' : ''}.`, 'info');
    log(`📱 ${state.lanes.map(l => `${l.name} (${l.display}) → ${l.assigned} numbers`).join(' | ')}`, 'info');
    if (skipped.length) log(`⚠️ Not included (WhatsApp not connected): ${skipped.map(s => s.name).join(', ')}`, 'warn');
    if (stoppedByAdmin.length) log(`⏹️ Not included (stopped by the admin, or Nunno plan not verified yet): ${stoppedByAdmin.map(s => s.name).join(', ')}`, 'warn');
    if (busyElsewhere.length) log(`⏭️ Not included (already sending another campaign): ${busyElsewhere.map(s => s.name).join(', ')}`, 'warn');
    log(`⏱️ Gap between messages: ${state.minDelay === state.maxDelay ? state.minDelay + ' s' : state.minDelay + '-' + state.maxDelay + ' s'} per WhatsApp.`, 'info');
    recount();
    writeStateNow();
    kickAllLanes();
    return { ...getStatus(), allocations: state.audience.map(c => ({ phone: c.phone, ownerId: c.assignedOwnerId })) };
  }

  function pause() {
    state.resumeAfterRestart = false;
    if (state.status !== 'running') return getStatus();
    clearLaneTimers();
    state.status = 'paused';
    (state.lanes || []).forEach(l => { l.nextAt = null; });
    log(`⏸️ Campaign paused. ${doneCount} of ${state.audience.length} done.`, 'warn');
    persistSoon();
    return getStatus();
  }

  function resume() {
    state.resumeAfterRestart = false;
    if (state.status !== 'paused') return getStatus();
    state.status = 'running';
    (state.lanes || []).forEach(l => { if (l.status !== 'done') { l.status = 'ready'; l.offlineSince = null; } });
    state.audience.forEach(c => { if (c.status === 'sending') c.status = 'pending'; });
    log('▶️ Campaign resumed on every connected Team Member.', 'info');
    recount();
    writeStateNow();
    kickAllLanes();
    return getStatus();
  }

  // Saving a big campaign (tens of thousands of numbers) takes a moment, so stop and pause
  // answer at once and save right after the reply has gone out. Nothing more is sent either
  // way: lane timers are cleared now, and a send already in flight sees the new status.
  function persistSoon() {
    setImmediate(() => { try { flushCrm(); } catch (_) {} writeStateNow(); });
  }

  function stop() {
    state.resumeAfterRestart = false;
    clearLaneTimers();
    state.status = 'stopped';
    state.remainingSeconds = 0;
    (state.lanes || []).forEach(l => { l.nextAt = null; });
    log(`⏹️ Campaign halted. ${state.totalSent} of ${state.audience.length} messages sent.`, 'warn');
    persistSoon();
    return getStatus();
  }

  function clear() {
    clearLaneTimers();
    laneBusy.clear();
    state.status = 'idle';
    state.audience = [];
    state.lanes = [];
    state.currentIndex = 0;
    state.totalSent = 0;
    state.totalFailed = 0;
    state.remainingSeconds = 0;
    state.logs = [];
    recount();
    writeStateNow();
    return getStatus();
  }

  // Numbers an Auto Data campaign never got to (it was stopped or paused early). The next
  // Auto Data campaign of the company uses them first, so stopping wastes no numbers.
  function leftover() {
    if (!state.autoData || state.status === 'running') return { success: true, phones: [] };
    const phones = [];
    for (const c of state.audience || []) {
      if (c.status === 'pending') phones.push(c.phone);
      if (phones.length >= 50000) break;
    }
    return { success: true, phones };
  }

  function firstMatching(list, test, limit) {
    const out = [];
    for (let i = 0; i < list.length && out.length < limit; i++) if (test(list[i])) out.push(list[i]);
    return out;
  }

  function getStatus() {
    const lanes = (state.lanes || []).map(l => ({
      ...l,
      connected: deviceOnline(l),
      remaining: Math.max(0, (l.assigned || 0) - (l.sent || 0) - (l.failed || 0)),
      nextInSeconds: l.nextAt ? Math.max(0, Math.round((l.nextAt - Date.now()) / 1000)) : 0
    }));
    const poolSize = Math.max(1, lanes.length);
    const done = doneCount;
    const active = lanes.find(l => l.id === state.activeLaneId) || lanes[0] || null;
    const waiting = lanes.filter(l => l.status === 'waiting').map(l => l.nextInSeconds);
    return {
      success: true,
      id: state.id, name: state.name, companyId: state.companyId, companyName: state.companyName,
      status: state.status,
      totalContacts: state.audience.length,
      currentIndex: done,
      totalSent: state.totalSent, totalFailed: state.totalFailed,
      poolSize,
      currentCycle: Math.min(state.totalCycles || 1, Math.floor(done / poolSize) + 1),
      totalCycles: state.totalCycles || 1,
      activeSenderIndex: active ? lanes.indexOf(active) : 0,
      activeSender: active,
      sendersPool: lanes,
      lanes,
      minDelay: state.minDelay, maxDelay: state.maxDelay,
      remainingSeconds: waiting.length ? Math.min(...waiting) : 0,
      assignMode: state.assignMode,
      startedAt: state.startedAt, updatedAt: state.updatedAt,
      logs: state.logs.slice(-60),
      audience: state.audience.length > 60
        ? firstMatching(state.audience, c => c.status === 'sending' || c.status === 'failed', 60).map(c => ({
            id: c.id, phone: c.phone, display: c.display, name: c.name, city: c.city, network: c.network,
            status: c.status, laneId: c.laneId, senderId: c.senderId, senderName: c.senderName || c.assignedOwnerName,
            senderRole: c.senderRole, senderPhone: c.senderPhone, cycle: c.cycle, sentAt: c.sentAt, error: c.error
          }))
        : state.audience.map(c => ({
            id: c.id, phone: c.phone, display: c.display, name: c.name, city: c.city, network: c.network,
            status: c.status, laneId: c.laneId, senderId: c.senderId, senderName: c.senderName || c.assignedOwnerName,
            senderRole: c.senderRole, senderPhone: c.senderPhone, cycle: c.cycle, sentAt: c.sentAt, error: c.error
          }))
    };
  }

  loadState();
  recount();

  // After a restart, WhatsApp sockets need a few seconds to reconnect. Check every 15 s
  // (for up to 15 minutes) and resume as soon as one of the campaign's WhatsApps is online.
  if (state.resumeAfterRestart && state.status === 'paused') {
    let tries = 0;
    const autoResume = setInterval(() => {
      tries++;
      if (!state.resumeAfterRestart || state.status !== 'paused') { clearInterval(autoResume); return; }
      if ((state.lanes || []).some(l => deviceOnline(l))) {
        clearInterval(autoResume);
        resume();
        log('▶️ Continued automatically after the server restart.', 'info');
      } else if (tries >= 60) {
        clearInterval(autoResume);
        state.resumeAfterRestart = false;
        log('No team WhatsApp came back online within 15 minutes. Click Resume once they are connected.', 'warn');
      }
    }, 15000);
    if (autoResume.unref) autoResume.unref();
  }

  // Is this closer's WhatsApp still sending for this campaign? (running, or coming back by
  // itself after a restart, with numbers left for them)
  function isLaneActive(userId) {
    if (!(state.status === 'running' || (state.status === 'paused' && state.resumeAfterRestart))) return false;
    const lane = laneOf(String(userId));
    return !!lane && lane.status !== 'done';
  }

  return {
    start,
    pause,
    resume,
    stop,
    clear,
    leftover,
    noteAck,
    isLaneActive,
    getStatus
  };
}

module.exports = {
  createCampaignManager,
  resolveSpintax,
  personalizeMessage
};
