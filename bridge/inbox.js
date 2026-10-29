'use strict';
const fs = require('node:fs');
const path = require('node:path');
const { createHash } = require('node:crypto');
const { downloadAttachment } = require('./media');
const statusOf = ack => ack < 0 ? 'failed' : ack >= 3 ? 'read' : ack === 2 ? 'delivered' : ack === 1 ? 'sent' : 'accepted';
function createInbox({ getClient, storageFile }) {
  const messages = new Map();
  const aliases = new Map();
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
  async function ingest(message, knownPhone) {
    const id = message.id?._serialized;
    if (!id) return;
    const fromMe = Boolean(message.fromMe || message.id.fromMe);
    const remote = message.id?.remote?._serialized || message.id?.remote || '';
    const resolvedRemotePhone = remote ? await phoneFor(remote) : null;
    const phone = resolvedRemotePhone || knownPhone || await phoneFor(fromMe ? message.to : message.from);
    if (!phone || !/^\d{10,18}$/.test(phone)) return;
    const previous = messages.get(id);
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
      agentName: fromMe ? 'WhatsApp' : undefined, type, text,
      timestamp: Number(message.timestamp) || previous?.timestamp || Math.floor(Date.now()/1000), hasMedia,
      durationSeconds: Number(message._data?.duration) || previous?.durationSeconds || 0,
      deliveryStatus: fromMe ? statusOf(message.ack || 0) : undefined };
    if (previous && ['delivered','read'].includes(previous.deliveryStatus) && ['accepted','sent'].includes(value.deliveryStatus)) value.deliveryStatus = previous.deliveryStatus;
    if (previous && JSON.stringify({ ...previous, revision: 0 }) === JSON.stringify({ ...value, revision: 0 })) return;
    messages.set(id, { ...value, revision: ++revision });
    if (messages.size > 2000) messages.delete(messages.keys().next().value);
    save();
  }
  function ack(message, value) {
    const item = messages.get(message.id?._serialized);
    if (item && item.deliveryStatus !== statusOf(value)) { item.deliveryStatus = statusOf(value); item.revision = ++revision; save(); }
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
  async function media(id) {
    const client = getClient();
    if (!client || typeof client.getMessageById !== 'function') {
      throw Object.assign(new Error('Reconnect WhatsApp to load this attachment.'), { status: 409 });
    }
    let timer;
    try { return await Promise.race([download(id), new Promise((_,reject) => { timer = setTimeout(() => reject(new Error('Attachment download timed out. It may have expired; ask the sender to resend it.')), 30000); })]); }
    finally { clearTimeout(timer); }
  }
  async function download(id) {
    const message = await getClient().getMessageById(id);
    if (!message?.hasMedia) throw new Error('Attachment is unavailable.');
    const m = await downloadAttachment(getClient(), message);
    if (!m?.data || m.data.length > 14*1024*1024) throw new Error('Attachment is unavailable or exceeds 10 MB.');
    return { success: true, data_url: `data:${m.mimetype || 'application/octet-stream'};base64,${m.data}`, filename: m.filename || '' };
  }
  return { ingest, ack, syncChat, syncRecent, media,
    events: after => ({ success: true, epoch, cursor: revision, messages: [...messages.values()].filter(m => m.revision > Number(after || 0)) }) };
}
module.exports = { createInbox, statusOf };
