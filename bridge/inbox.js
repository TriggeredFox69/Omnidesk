'use strict';
const fs = require('node:fs');
const path = require('node:path');
const { createHash } = require('node:crypto');
const { downloadAttachment } = require('./media');
const tenants = require('./tenants');
const statusOf = ack => ack < 0 ? 'failed' : ack >= 3 ? 'read' : ack === 2 ? 'delivered' : ack === 1 ? 'sent' : 'accepted';
function createInbox({ getClient, storageFile, getMediaClients }) {
  const messages = new Map();
  const aliases = new Map();
  const pendingAcks = new Map();
  const statusRank = { accepted: 0, sent: 1, delivered: 2, read: 3, failed: -1 };
  let revision = 0, saveTimer;
  const epoch = require('node:crypto').randomUUID();
  const inboxStartTime = Math.floor(Date.now() / 1000);
  if (storageFile && fs.existsSync(storageFile)) {
    try { for (const item of JSON.parse(fs.readFileSync(storageFile, 'utf8'))) messages.set(item.waMessageId, { ...item, revision: ++revision }); } catch (_) {}
  }
  function save() {
    if (!storageFile) return;
    clearTimeout(saveTimer);
    saveTimer = setTimeout(() => {
      try {
      fs.mkdirSync(path.dirname(storageFile), { recursive: true, mode: 0o700 });
      fs.writeFileSync(storageFile + '.tmp', JSON.stringify([...messages.values()]), { mode: 0o600 });
      fs.renameSync(storageFile + '.tmp', storageFile);
      } catch (error) { console.error('Inbox persistence:', error.message); }
    }, 100);
  }
  async function phoneFor(id) {
    if (!id || /@(g\.us|broadcast|newsletter)$/.test(id)) return null;
    if (aliases.has(id)) return aliases.get(id);
    const client = getClient();
    if (client && typeof client.getContactLidAndPhone === 'function') {
      try {
        const list = await client.getContactLidAndPhone([id]);
        const pn = list?.[0]?.pn;
        if (pn) {
          const phone = pn.split('@')[0].split(':')[0];
          aliases.set(id, phone);
          return phone;
        }
      } catch (_) {}
    }
    if (id.endsWith('@c.us') || id.endsWith('@s.whatsapp.net')) {
      const p = id.split('@')[0].split(':')[0];
      aliases.set(id, p);
      return p;
    }
    if (id.endsWith('@lid') || (id.length >= 14 && id.startsWith('2080'))) {
      if (client && typeof client.resolveLid === 'function') {
        try {
          const resolved = await client.resolveLid(id);
          if (resolved) {
            aliases.set(id, resolved);
            return resolved;
          }
        } catch (_) {}
      }
      const cleanLid = id.split('@')[0].split(':')[0].replace(/\D/g, '');
      if (cleanLid) {
        aliases.set(id, cleanLid);
        return cleanLid;
      }
      return null;
    }
    const clean = id.split('@')[0].split(':')[0].replace(/\D/g, '');
    if (clean) {
      aliases.set(id, clean);
      return clean;
    }
    return null;
  }
  // Which company a message belongs to: the closer's company for their own WhatsApp,
  // the main sender's company otherwise (see tenants.js).
  const companyOfMessageId = new Map();   // waMessageId -> company (for later ticks)
  function rememberCompany(id, companyId) {
    companyOfMessageId.set(id, companyId);
    if (companyOfMessageId.size > 5000) companyOfMessageId.delete(companyOfMessageId.keys().next().value);
  }

  async function ingest(message, knownPhone, opts = {}) {
    const id = message.id?._serialized;
    if (!id) return;
    const fromMe = Boolean(message.fromMe || message.id.fromMe);
    const remote = message.id?.remote?._serialized || message.id?.remote || '';
    const resolvedRemotePhone = remote ? await phoneFor(remote) : null;
    const phone = opts.phone || resolvedRemotePhone || knownPhone || await phoneFor(fromMe ? message.to : message.from);
    const debug = why => { if (process.env.WHATSAPP_BRIDGE_DEBUG) console.log(`[Inbox:debug] ${id} phone=${phone || '?'} ${why}`); };
    if (!phone || !/^\d{10,18}$/.test(phone)) { debug('dropped: no phone number for this chat'); return; }
    // Never backfill or import old WhatsApp chats sent before session initialization
    const msgTs = Number(message.timestamp || 0);
    if (msgTs > 0 && msgTs < (inboxStartTime - 30)) { debug('dropped: historical message prior to connection'); return; }
    // Keep the inbox limited to Chatly lead conversations. Inbound personal
    // chats from a linked phone are ignored unless Chatly already knows the lead.
    // A reply is kept only if it is a lead of the company that owns this WhatsApp;
    // anything else on a closer's phone is a personal chat and stays private.
    const companyId = tenants.companyOfDevice(opts.viaUserId);
    if (!fromMe && !tenants.isLeadOf(companyId, phone)) { debug(`dropped: not a ${companyId} lead (personal chat)`); return; }
    debug(fromMe ? 'kept: sent by us' : 'kept: reply from a lead');
    const previous = messages.get(id);
    const pendingAck = pendingAcks.get(id);
    if (pendingAck !== undefined) pendingAcks.delete(id);
    const type = ['ptt', 'audio'].includes(message.type) ? 'audio' :
      message.type === 'image' ? 'image' : message.type === 'video' ? 'video' :
        (message.type === 'document' ? 'document' : (previous?.type || 'text'));
    const bodyText = message.body || (type === 'audio' ? 'Voice note' : type === 'image' ? 'Image' : (type === 'video' ? 'Video' : (type === 'document' ? 'Document' : '')));
    const text = bodyText || previous?.text || '';
    const hasMedia = Boolean(message.hasMedia || previous?.hasMedia);
    if (!text && !hasMedia) return;

    const contactName = message._data?.notifyName || previous?.name || phone;
    const value = { id: 'wa_' + createHash('sha256').update(id).digest('hex').slice(0,24), waMessageId: id,
      phone, name: contactName, sender: fromMe ? 'agent' : (previous?.sender || 'lead'),
      agentName: fromMe ? (opts.agentName || 'WhatsApp') : undefined, type, text,
      ...(opts.viaUserId ? { viaUserId: opts.viaUserId, viaNumber: opts.viaNumber || '' } : {}),
      timestamp: Number(message.timestamp) || previous?.timestamp || Math.floor(Date.now()/1000), hasMedia,
      durationSeconds: Number(message._data?.duration) || previous?.durationSeconds || 0,
      deliveryStatus: fromMe ? (pendingAck !== undefined ? statusOf(pendingAck) : statusOf(message.ack || 0)) : undefined };
    if (previous && ['delivered','read'].includes(previous.deliveryStatus) && ['accepted','sent'].includes(value.deliveryStatus)) value.deliveryStatus = previous.deliveryStatus;
    if (previous && JSON.stringify({ ...previous, revision: 0 }) === JSON.stringify({ ...value, revision: 0 })) return;
    messages.set(id, { ...value, revision: ++revision });
    if (messages.size > 2000) messages.delete(messages.keys().next().value);
    save();
    rememberCompany(id, companyId);
    syncToLeadsFile({ ...value, companyId }, false);
  }


  // Save a message (or a tick) on the lead of the right company.
  function syncToLeadsFile(item, ackStatusOnly = false) {
    if (!item) return;
    try {
      const waId = item.waMessageId || item.id;
      const cleanPhone = tenants.digits(item.phone);
      if (!cleanPhone && !waId) return;
      // Ticks: the company remembered for this message, else whichever company has it.
      const companies = item.companyId ? [item.companyId]
        : (companyOfMessageId.has(waId) ? [companyOfMessageId.get(waId)] : tenants.companyIds());
      for (const companyId of companies) {
        const leads = tenants.readLeads(companyId);
        let targetLead = cleanPhone ? leads.find(l => tenants.samePhone(l.phone, cleanPhone)) : null;
        if (!targetLead && waId) {
          targetLead = leads.find(l => Array.isArray(l.messages) && l.messages.some(m => m.waMessageId === waId || m.id === waId));
        }
        let modified = false;
        if (targetLead) {
          if (!Array.isArray(targetLead.messages)) targetLead.messages = [];
          const existing = targetLead.messages.find(m => (m.waMessageId === waId || m.id === waId));
          if (existing) {
            if (item.deliveryStatus && existing.deliveryStatus !== item.deliveryStatus && item.deliveryStatus !== 'accepted') {
              existing.deliveryStatus = item.deliveryStatus;
              modified = true;
            }
          } else if (!ackStatusOnly) {
            targetLead.messages.push(leadMessage(item, targetLead));
            targetLead.lastMessageAt = item.timestamp || Math.floor(Date.now() / 1000);
            if (item.sender === 'lead') {
              targetLead.status = 'unread';
              targetLead.unreadCount = (targetLead.unreadCount || 0) + 1;
            } else {
              targetLead.status = 'replied';
            }
            modified = true;
          }
        } else if (!ackStatusOnly && item.viaUserId && item.sender === 'agent' && cleanPhone.length >= 10) {
          // A closer started a chat from their own phone: it becomes THEIR lead in
          // THEIR company (cold outreach). Nothing is created for the main number.
          const owner = tenants.usersById().get(String(item.viaUserId)) || {};
          leads.push({
            id: 'lead_wa_' + cleanPhone,
            company_id: companyId,
            name: item.name && item.name !== cleanPhone ? item.name : '+' + cleanPhone,
            phone: cleanPhone,
            display: '+' + cleanPhone,
            city: '',
            network: 'WhatsApp',
            ownerId: item.viaUserId,
            ownerName: owner.name || item.agentName || '',
            ownerUsername: owner.username || '',
            leadState: 'ongoing',
            status: 'replied',
            unreadCount: 0,
            interest: 'WhatsApp outreach',
            lastActive: 'Just now',
            lastMessageAt: item.timestamp || Math.floor(Date.now() / 1000),
            notes: 'Started from the closer’s connected WhatsApp',
            messages: [leadMessage(item, null)]
          });
          modified = true;
        }
        if (modified) tenants.writeLeads(companyId, leads);
        if (targetLead || modified) break;
      }
    } catch (_) {}
  }

  function leadMessage(item, lead) {
    const ts = item.timestamp || Math.floor(Date.now() / 1000);
    return {
      id: item.id || ('msg_inbox_' + Date.now()),
      waMessageId: item.waMessageId || item.id,
      deliveryStatus: item.deliveryStatus || 'delivered',
      sender: item.sender || 'agent',
      agentName: item.agentName || (item.sender === 'agent' ? 'Agent' : (lead && lead.name) || ''),
      text: item.text || '',
      time: new Date(ts * 1000).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }),
      timestamp: ts,
      type: item.type || 'text',
      hasMedia: Boolean(item.hasMedia),
      durationSeconds: item.durationSeconds || 0
    };
  }


  function ack(message, value) {
    const id = message.id?._serialized;
    const nextStatus = statusOf(value);
    const item = messages.get(id);
    if (!item) {
      if (id) {
        const current = pendingAcks.get(id);
        if (current === undefined || Number(value) === -1 || Number(value) > current) pendingAcks.set(id, Number(value));
        if (pendingAcks.size > 2000) pendingAcks.delete(pendingAcks.keys().next().value);
        syncToLeadsFile({ waMessageId: id, deliveryStatus: nextStatus }, true);
      }
      return;
    }
    const currentRank = statusRank[item.deliveryStatus] ?? -1;
    const nextRank = statusRank[nextStatus] ?? -1;
    if (item.deliveryStatus !== nextStatus && (nextRank > currentRank || (nextStatus === 'failed' && currentRank < 2))) {
      item.deliveryStatus = nextStatus; item.revision = ++revision; save();
      syncToLeadsFile(item, true);
    }
  }
  async function syncChat(phone) {
    // Historical per-chat sync disabled: Chatly only keeps messages created after WhatsApp is connected.
    return;
  }
  async function syncRecent() {
    // Historical recent-chats sync disabled: Chatly only keeps messages created after WhatsApp is connected.
    return;
  }
  // The company number, then every closer's linked WhatsApp: an attachment lives
  // on whichever one received it.
  function mediaClients() {
    const list = [getClient(), ...(typeof getMediaClients === 'function' ? getMediaClients() : [])];
    return list.filter((c, i) => c && typeof c.getMessageById === 'function' && list.indexOf(c) === i);
  }
  async function media(id) {
    if (!mediaClients().length) {
      throw Object.assign(new Error('Reconnect WhatsApp to load this attachment.'), { status: 409 });
    }
    let timer;
    try { return await Promise.race([download(id), new Promise((_,reject) => { timer = setTimeout(() => reject(new Error('Attachment download timed out. It may have expired; ask the sender to resend it.')), 30000); })]); }
    finally { clearTimeout(timer); }
  }
  async function download(id) {
    let message = null, owner = null;
    for (const c of mediaClients()) {
      message = await c.getMessageById(id).catch(() => null);
      if (message?.hasMedia) { owner = c; break; }
    }
    if (!owner) throw new Error('Attachment is unavailable.');
    const m = await downloadAttachment(owner, message);
    if (!m?.data || m.data.length > 14*1024*1024) throw new Error('Attachment is unavailable or exceeds 10 MB.');
    return { success: true, data_url: `data:${m.mimetype || 'application/octet-stream'};base64,${m.data}`, filename: m.filename || '' };
  }
  return { ingest, ack, syncChat, syncRecent, media,
    events: after => ({ success: true, epoch, cursor: revision, messages: [...messages.values()].filter(m => m.revision > Number(after || 0)) }) };
}
module.exports = { createInbox, statusOf };
