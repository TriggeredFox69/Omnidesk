'use strict';
const fs = require('node:fs');
const path = require('node:path');
const { randomUUID } = require('node:crypto');

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
  const cName = contact?.name || contact?.display || contact?.phone || 'Trader';
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
    .replace(/\{city\}/gi, cCity);

  return msg;
}

function createCampaignManager({ send, dataDir, getLeadsPath }) {
  const stateFile = path.join(dataDir, 'campaign_state.json');
  let loopTimer = null;
  let countdownTimer = null;
  let inFlight = false;

  let state = {
    id: null,
    name: 'OmniDesk campaign',
    status: 'idle', // 'idle' | 'running' | 'paused' | 'stopped' | 'completed'
    audience: [],
    currentIndex: 0,
    totalSent: 0,
    totalFailed: 0,
    minDelay: 6,
    maxDelay: 12,
    remainingSeconds: 0,
    messageTemplate: '',
    assignMode: 'pool',
    closer: null,
    startedAt: null,
    updatedAt: null,
    logs: []
  };

  function log(message, type = 'info') {
    const time = new Date().toLocaleTimeString();
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

  function updateCrmLead(contact, crmMessage, assignMode, closer) {
    try {
      const leadsFile = typeof getLeadsPath === 'function' ? getLeadsPath() : null;
      if (!leadsFile || !fs.existsSync(leadsFile)) return;
      let leads = JSON.parse(fs.readFileSync(leadsFile, 'utf8'));
      if (!Array.isArray(leads)) return;

      const cleanPhone = String(contact.phone || '').replace(/\D/g, '');
      let existingLead = leads.find(l => String(l.phone || '').replace(/\D/g, '') === cleanPhone);

      const ownerId = (assignMode === 'mine' && closer?.id) ? closer.id : null;
      const ownerName = (assignMode === 'mine' && closer?.name) ? closer.name : null;

      if (existingLead) {
        existingLead.messages = existingLead.messages || [];
        if (!existingLead.messages.some(m => m.waMessageId === crmMessage.waMessageId)) {
          existingLead.messages.push(crmMessage);
        }
        existingLead.lastActive = 'Just now';
        existingLead.status = 'sent';
      } else {
        const newLead = {
          id: `lead_${Date.now()}_${contact.phone}`,
          name: contact.name || contact.display || `+${cleanPhone}`,
          phone: cleanPhone,
          display: contact.display || `+${cleanPhone}`,
          city: contact.city || 'Pakistan',
          network: contact.network || 'WhatsApp',
          ownerId: ownerId,
          ownerName: ownerName,
          leadState: (assignMode === 'mine') ? 'ongoing' : 'fresh',
          status: 'sent',
          unreadCount: 0,
          interest: 'Campaign Outreach',
          lastActive: 'Just now',
          notes: `Outreach sent via campaign "${state.name}". Assign: ${assignMode === 'mine' ? (closer?.name || 'Closer') : 'Open Pool'}.`,
          messages: [crmMessage]
        };
        leads.unshift(newLead);
      }

      const tmp = leadsFile + '.tmp';
      fs.writeFileSync(tmp, JSON.stringify(leads, null, 2), 'utf8');
      fs.renameSync(tmp, leadsFile);
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

    contact.status = 'sending';
    contact.requestId = contact.requestId || randomUUID();
    const personalizedMsg = personalizeMessage(state.messageTemplate, contact);
    contact.preparedMessage = personalizedMsg;
    saveState();

    inFlight = true;
    try {
      const result = await send({
        phone: contact.phone,
        message: personalizedMsg,
        request_id: contact.requestId
      });

      contact.status = 'accepted';
      contact.sentAt = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
      contact.messageId = result.message_id;
      state.totalSent++;

      const crmMessage = {
        id: `msg_c_${Date.now()}`,
        waMessageId: result.message_id,
        deliveryStatus: 'accepted',
        sender: 'agent',
        agentName: state.closer?.name || 'Campaign Outreach',
        text: personalizedMsg,
        time: contact.sentAt
      };

      updateCrmLead(contact, crmMessage, state.assignMode, state.closer);

      log(`⚡ [${currentNum}/${total}] WhatsApp accepted message for ${contact.display || contact.phone} (${contact.name || 'Contact'})`, 'success');
    } catch (err) {
      contact.status = 'failed';
      contact.error = err.message;
      state.totalFailed++;
      log(`❌ [${currentNum}/${total}] Failed for ${contact.display || contact.phone}: ${err.message}`, 'error');
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

    const minSec = state.minDelay || 6;
    const maxSec = state.maxDelay || 12;
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
    log(`🎉 Campaign completed! Total sent: ${state.totalSent}, Failed: ${state.totalFailed}. All leads registered in CRM.`, 'success');
    saveState();
  }

  function start({ name, message, audience, minDelay, maxDelay, assignMode, closer }) {
    clearTimeout(loopTimer);
    clearInterval(countdownTimer);
    inFlight = false;

    state.id = 'camp_' + Date.now();
    state.name = (name || '').trim() || 'OmniDesk campaign';
    state.messageTemplate = message || '';
    state.audience = (audience || []).map(c => ({
      id: c.id || ('c_' + Math.random().toString(36).slice(2, 9)),
      phone: String(c.phone || '').replace(/\D/g, ''),
      display: c.display || ('+' + String(c.phone || '').replace(/\D/g, '')),
      name: c.name || '',
      city: c.city || 'Pakistan',
      network: c.network || 'WhatsApp',
      status: c.status || 'pending',
      requestId: c.requestId || null
    }));
    state.currentIndex = 0;
    state.totalSent = 0;
    state.totalFailed = 0;
    state.minDelay = Math.max(1, parseInt(minDelay, 10) || 6);
    state.maxDelay = Math.max(state.minDelay, parseInt(maxDelay, 10) || 12);
    state.remainingSeconds = 0;
    state.assignMode = assignMode || 'pool';
    state.closer = closer || null;
    state.status = 'running';
    state.startedAt = Date.now();
    state.logs = [];

    log(`🚀 Campaign "${state.name}" launched with ${state.audience.length} contacts. Cadence: ${state.minDelay}s - ${state.maxDelay}s. Running headless 24/7 on server.`, 'info');
    saveState();

    step();
    return getStatus();
  }

  function pause() {
    if (state.status !== 'running') return getStatus();
    clearTimeout(loopTimer);
    clearInterval(countdownTimer);
    state.status = 'paused';
    log(`⏸️ Campaign paused by operator at contact ${state.currentIndex + 1} of ${state.audience.length}. Standing by.`, 'warn');
    saveState();
    return getStatus();
  }

  function resume() {
    if (state.status !== 'paused') return getStatus();
    state.status = 'running';
    log(`▶️ Campaign resumed. Dispatching next recipient...`, 'info');
    saveState();
    step();
    return getStatus();
  }

  function stop() {
    clearTimeout(loopTimer);
    clearInterval(countdownTimer);
    state.status = 'stopped';
    state.remainingSeconds = 0;
    log(`⏹️ Campaign halted by operator. WhatsApp accepted ${state.totalSent} of ${state.audience.length} messages.`, 'warn');
    saveState();
    return getStatus();
  }

  function getStatus() {
    return {
      success: true,
      id: state.id,
      name: state.name,
      status: state.status,
      totalContacts: state.audience.length,
      currentIndex: state.currentIndex,
      totalSent: state.totalSent,
      totalFailed: state.totalFailed,
      minDelay: state.minDelay,
      maxDelay: state.maxDelay,
      remainingSeconds: state.remainingSeconds,
      assignMode: state.assignMode,
      startedAt: state.startedAt,
      updatedAt: state.updatedAt,
      logs: state.logs.slice(-50),
      audience: state.audience.map(c => ({
        id: c.id,
        phone: c.phone,
        display: c.display,
        name: c.name,
        city: c.city,
        network: c.network,
        status: c.status,
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
