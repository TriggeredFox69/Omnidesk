'use strict';
const fs = require('node:fs');
const path = require('node:path');
const { createHash } = require('node:crypto');
const { downloadAttachment } = require('./media');
const statusOf = ack => ack < 0 ? 'failed' : ack >= 3 ? 'read' : ack === 2 ? 'delivered' : ack === 1 ? 'sent' : 'accepted';
function createInbox({ getClient, storageFile, getMediaClients }) {
  const messages = new Map();
  const aliases = new Map();
  const pendingAcks = new Map();
  const statusRank = { accepted: 0, sent: 1, delivered: 2, read: 3, failed: -1 };
  let revision = 0, saveTimer;
  const epoch = require('node:crypto').randomUUID();
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
  function isChatlyLead(phone) {
    const wanted = String(phone || '').replace(/\D/g, '').slice(-9);
    if (wanted.length < 9) return false;
    const candidates = [
      path.resolve(__dirname, '../data/tenants/COMP-001/leads.json'),
      path.resolve(__dirname, '../data/leads.json')
    ];
    for (const file of candidates) {
      try {
        if (!fs.existsSync(file)) continue;
        const leads = JSON.parse(fs.readFileSync(file, 'utf8'));
        if (Array.isArray(leads) && leads.some(lead => String(lead.phone || '').replace(/\D/g, '').slice(-9) === wanted)) return true;
      } catch (_) {}
    }
    return false;
  }
  async function ingest(message, knownPhone, opts = {}) {
    const id = message.id?._serialized;
    if (!id) return;
    const fromMe = Boolean(message.fromMe || message.id.fromMe);
    const remote = message.id?.remote?._serialized || message.id?.remote || '';
    const resolvedRemotePhone = remote ? await phoneFor(remote) : null;
    const phone = opts.phone || resolvedRemotePhone || knownPhone || await phoneFor(fromMe ? message.to : message.from);
    if (!phone || !/^\d{10,18}$/.test(phone)) return;
    // Keep the inbox limited to Chatly lead conversations. Inbound personal
    // chats from a linked phone are ignored unless Chatly already knows the lead.
    if (!fromMe && !isChatlyLead(phone)) return;
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
    syncToLeadsFile(value, false);
  }

  function syncToLeadsFile(item, ackStatusOnly = false) {
    if (!item) return;
    try {
      const candidates = [
        path.resolve(__dirname, '../data/tenants/COMP-001/leads.json'),
        path.resolve(__dirname, '../data/leads.json')
      ];
      const waId = item.waMessageId || item.id;
      const cleanPhone = String(item.phone || '').replace(/\D/g, '');
      if (!cleanPhone && !waId) return;

      for (const p of candidates) {
        if (!fs.existsSync(p)) continue;
        let leads = [];
        try { leads = JSON.parse(fs.readFileSync(p, 'utf8')); } catch (_) {}
        if (!Array.isArray(leads)) continue;

        let modified = false;
        let targetLead = cleanPhone ? leads.find(l => String(l.phone || '').replace(/\D/g, '') === cleanPhone) : null;
        if (!targetLead && waId) {
          targetLead = leads.find(l => Array.isArray(l.messages) && l.messages.some(m => m.waMessageId === waId || m.id === waId));
        }

        if (targetLead) {
          if (!Array.isArray(targetLead.messages)) targetLead.messages = [];
          const existing = targetLead.messages.find(m => (m.waMessageId === waId || m.id === waId));
          if (existing) {
            if (item.deliveryStatus && existing.deliveryStatus !== item.deliveryStatus && item.deliveryStatus !== 'accepted') {
              existing.deliveryStatus = item.deliveryStatus;
              modified = true;
            }
          } else if (!ackStatusOnly) {
            targetLead.messages.push({
              id: item.id || ('msg_inbox_' + Date.now()),
              waMessageId: waId,
              deliveryStatus: item.deliveryStatus || 'delivered',
              sender: item.sender || 'agent',
              agentName: item.agentName || (item.sender === 'agent' ? 'Askari Raza' : targetLead.name),
              text: item.text || '',
              time: new Date((item.timestamp || Math.floor(Date.now() / 1000)) * 1000).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }),
              timestamp: item.timestamp || Math.floor(Date.now() / 1000),
              type: item.type || 'text',
              hasMedia: Boolean(item.hasMedia),
              durationSeconds: item.durationSeconds || 0
            });
            targetLead.lastMessageAt = item.timestamp || Math.floor(Date.now() / 1000);
            if (item.sender === 'lead') {
              targetLead.status = 'unread';
              targetLead.unreadCount = (targetLead.unreadCount || 0) + 1;
            } else {
              targetLead.status = 'replied';
            }
            modified = true;
          }
        } else if (!ackStatusOnly && cleanPhone && cleanPhone.length >= 10) {
          const defaultDisplay = '+' + cleanPhone;
          leads.push({
            id: 'lead_wa_' + cleanPhone,
            company_id: 'COMP-001',
            name: item.name || defaultDisplay,
            phone: cleanPhone,
            display: defaultDisplay,
            city: '',
            network: 'WhatsApp',
            ownerId: item.viaUserId || 'EMP-TM-MWMK43N9',
            ownerName: item.agentName || 'Askari Raza',
            ownerUsername: 'askari',
            leadState: 'ongoing',
            status: item.sender === 'lead' ? 'unread' : 'replied',
            unreadCount: item.sender === 'lead' ? 1 : 0,
            interest: 'WhatsApp Live Sync',
            lastActive: 'Just now',
            lastMessageAt: item.timestamp || Math.floor(Date.now() / 1000),
            notes: 'Live synced from connected WhatsApp',
            messages: [{
              id: item.id || ('msg_inbox_' + Date.now()),
              waMessageId: waId,
              deliveryStatus: item.deliveryStatus || 'delivered',
              sender: item.sender || 'agent',
              agentName: item.agentName || 'Askari Raza',
              text: item.text || '',
              time: new Date((item.timestamp || Math.floor(Date.now() / 1000)) * 1000).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }),
              timestamp: item.timestamp || Math.floor(Date.now() / 1000),
              type: item.type || 'text',
              hasMedia: Boolean(item.hasMedia),
              durationSeconds: item.durationSeconds || 0
            }]
          });
          modified = true;
        }

        if (modified) {
          // Write-then-rename: PHP may read leads.json at any moment and must never see half a file.
          const tmp = `${p}.bridge-${process.pid}.tmp`;
          try { fs.writeFileSync(tmp, JSON.stringify(leads, null, 2), 'utf8'); fs.renameSync(tmp, p); }
          catch (_) { try { fs.unlinkSync(tmp); } catch (__) {} }
        }
      }
    } catch (_) {}
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
    if (!/^\d{10,18}$/.test(phone)) throw new Error('Invalid phone number.');
    const client = getClient();
    const id = await client.getNumberId(phone);
    if (!id) return;
    const chat = await client.getChatById(id._serialized);
    for (const message of await chat.fetchMessages({ limit: 50 })) await ingest(message);
  }
  async function syncRecent() {
    if (!getClient().getChats) return;
    const chats = (await getClient().getChats()).filter(c => !c.isGroup && /@(c\.us|lid|s\.whatsapp\.net)$/.test(c.id?._serialized || '')).sort((a,b) => b.timestamp-a.timestamp).slice(0,20);
    for (const chat of chats) {
      try {
        const phone = await phoneFor(chat.id._serialized);
        if (phone) for (const m of await chat.fetchMessages({ limit: 30 })) await ingest(m, phone);
      } catch (_) { /* One unavailable chat must not stop inbox synchronization. */ }
    }
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
