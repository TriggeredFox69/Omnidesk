'use strict';
const fs = require('node:fs');
const path = require('node:path');
const { createHash } = require('node:crypto');
const { downloadAttachment } = require('./media');
const tenants = require('./tenants');
const { store: chatStore } = require('./store');
const statusOf = ack => ack < 0 ? 'failed' : ack >= 3 ? 'read' : ack === 2 ? 'delivered' : ack === 1 ? 'sent' : 'accepted';
function createInbox({ getClient, storageFile, getMediaClients, store: storeOverride }) {
  const st = storeOverride || chatStore();   // chats in Supabase while its switch is on
  const messages = new Map();
  const aliases = new Map();
  const pendingAcks = new Map();
  const statusRank = { accepted: 0, sent: 1, delivered: 2, read: 3, failed: -1 };
  let revision = 0, saveTimer;
  const epoch = require('node:crypto').randomUUID();
  const inboxStartTime = Math.floor(Date.now() / 1000);
  const HISTORY_WINDOW_S = 6 * 3600;
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
    }, 1000);   // one write per second at most, even when a campaign records a message every few ms
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

  // Numbers Chatly messaged in the last few minutes. A campaign's CRM lead is written a few
  // seconds after the send (batched), so a very quick reply must not be taken for a personal chat.
  const chatlySentTo = new Map();   // "<company>:<last 9 digits>" -> time
  const CHATLY_SENT_TTL_MS = 10 * 60 * 1000;
  const sentKey = (companyId, phone) => { const d = tenants.digits(phone); return `${companyId}:${d.length >= 9 ? d.slice(-9) : d}`; };
  function rememberChatlyPhone(companyId, phone) {
    chatlySentTo.set(sentKey(companyId, phone), Date.now());
    if (chatlySentTo.size > 5000) chatlySentTo.delete(chatlySentTo.keys().next().value);
  }
  function sentByChatlyRecently(companyId, phone) {
    const at = chatlySentTo.get(sentKey(companyId, phone));
    return at !== undefined && Date.now() - at < CHATLY_SENT_TTL_MS;
  }

  function isNunnoTL(userId) {
    const u = tenants.usersById().get(String(userId));
    // a Super Lead (works as a TM and a TL) gets this too
    return !!u && (String(u.role || '').toLowerCase() === 'lead' || !!u.super_lead) && (tenants.companyOfUser(userId) === 'COMP-001');
  }
  // A lead for a chat a Nunno TL started from their phone, owned by that TL. One per number
  // (fixed id), so two quick messages never create two leads.
  async function createTlOutreachLead(companyId, phone, tlId, inbound = false, contactName = '') {
    const tl = tenants.usersById().get(String(tlId)) || {};
    const digits = tenants.digits(phone);
    const now = Math.floor(Date.now() / 1000);
    const name = String(contactName || '').trim() && !/^\+?\d+$/.test(String(contactName).trim()) ? String(contactName).trim().slice(0, 60) : '+' + digits;
    const lead = {
      id: 'lead_tl_' + digits, company_id: companyId, name, phone: digits, display: '+' + digits,
      city: '', network: 'WhatsApp', ownerId: tl.id || tlId, ownerName: tl.name || '', ownerUsername: tl.username || '',
      leadState: 'ongoing', status: inbound ? 'new' : 'replied', unreadCount: 0, interest: inbound ? 'TL Inbound' : 'TL Outreach',
      lastActive: 'Just now', lastMessageAt: now,
      notes: inbound
        ? `Messaged ${tl.name || 'the Team Leader'}'s WhatsApp first (inbound).`
        : `Started by ${tl.name || 'the Team Leader'} from their own WhatsApp (recruiting).`,
      messages: []
    };
    if (st.enabled()) {
      await st.saveLeads(companyId, [lead]);
    } else {
      const leads = tenants.readLeads(companyId);
      if (leads.some(l => tenants.samePhone(l.phone, digits))) return true;
      leads.unshift(lead);
      tenants.writeLeads(companyId, leads);
    }
    return true;
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
    // Never backfill old WhatsApp history. Replies that arrived while the bridge was
    // restarting or reconnecting come in with their original time, so they are kept
    // as long as they are newer than HISTORY_WINDOW_S before this bridge started.
    const msgTs = Number(message.timestamp || 0);
    if (msgTs > 0 && msgTs < (inboxStartTime - HISTORY_WINDOW_S)) { debug('dropped: historical message prior to connection'); return; }
    // Keep the inbox limited to Chatly lead conversations. Inbound personal
    // chats from a linked phone are ignored unless Chatly already knows the lead.
    // A reply is kept only if it is a lead of the company that owns this WhatsApp;
    // anything else on a closer's phone is a personal chat and stays private.
    const companyId = tenants.companyOfDevice(opts.viaUserId);
    if (fromMe && opts.fromChatly) rememberChatlyPhone(companyId, phone);
    let known = sentByChatlyRecently(companyId, phone)
      || (st.enabled() ? await st.isLead(companyId, phone) : tenants.isLeadOf(companyId, phone));
    // Nunno Team Leaders and Super Leads work their own WhatsApp fully in Chatly: a chat they
    // start from their phone AND a new person who messages them first both become their lead,
    // with every message after it, both ways. (Team Members only ever get campaign chats.)
    if (!known && (!fromMe || !opts.fromChatly) && opts.viaUserId && isNunnoTL(opts.viaUserId)) {
      known = await createTlOutreachLead(companyId, phone, opts.viaUserId, !fromMe, fromMe ? '' : message._data?.notifyName)
        .catch(err => { debug('TL lead failed: ' + err.message); return false; });
    }
    if (!fromMe && !known) { debug(`dropped: not a ${companyId} lead (personal chat)`); return; }
    // Messages typed on a closer's own phone to someone who is not a lead are personal
    // chats too: never stored, never turned into leads. Chatly's own sends (campaigns,
    // the Sales Desk) are flagged fromChatly; replies to real leads are always kept.
    if (fromMe && !opts.fromChatly && !known && !messages.has(id)) { debug('dropped: sent from the phone to a non-lead (personal chat)'); return; }
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
      // Chats in Supabase: one queued row write instead of rewriting the company's leads.json.
      if (st.enabled()) {
        if (ackStatusOnly) {
          if (item.deliveryStatus) st.queueStatus(item.companyId || companyOfMessageId.get(waId) || null, waId, item.deliveryStatus);
        } else if (item.companyId && cleanPhone) {
          st.queueMessage(item.companyId, cleanPhone, leadMessage(item, null), true);
        }
        return;
      }
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
        }
        // No lead is ever created here. Leads come from Chatly itself (campaign sends are
        // written by the campaign's CRM sync; the Sales Desk adds them explicitly). Creating
        // one for every chat a closer started on their phone filled the CRM with personal chats.
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
        // Only Chatly messages have a CRM lead to tick. Ticks for a closer's personal
        // messages used to read every company's leads.json looking for them.
        if (companyOfMessageId.has(id)) syncToLeadsFile({ waMessageId: id, deliveryStatus: nextStatus }, true);
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
