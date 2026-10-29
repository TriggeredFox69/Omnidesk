'use strict';
/**
 * Baileys WhatsApp Engine for Headless / CloudLinux / cPanel Environments
 * Zero Chromium or browser dependencies required.
 * Fully compliant with Signal Protocol retry-receipts & 100% full-duplex sync.
 */
const { EventEmitter } = require('node:events');
const path = require('node:path');
const fs = require('node:fs');
const {
  default: makeWASocket,
  useMultiFileAuthState,
  DisconnectReason,
  downloadMediaMessage,
  normalizeMessageContent,
  proto,
  Browsers,
  fetchLatestBaileysVersion
} = require('@whiskeysockets/baileys');
const pino = require('pino');
const NodeCache = require('@cacheable/node-cache').default || require('@cacheable/node-cache');
const { ensureOggOpus } = require('./audio');

class BaileysClient extends EventEmitter {
  constructor({ authPath, deviceName = 'Omnidesk Cloud' } = {}) {
    super();
    this.authPath = authPath || path.resolve(__dirname, '../data/whatsapp-bridge/baileys_auth');
    this.rawStorePath = path.resolve(this.authPath, '../raw_messages.json');
    this.lidMapPath = path.resolve(this.authPath, '../lid_map.json');
    this.deviceName = deviceName;
    this.sock = null;
    this.info = { wid: { user: '' } };
    this._destroyed = false;
    this._messageCache = new Map();
    this._rawMessageStore = new Map();
    this._contacts = new Map();
    this._lidToPhone = new Map();
    this._phoneToLid = new Map();
    this._msgRetryCache = new NodeCache({ stdTTL: 3600, useClones: false });
    this._loadRawStore();
    this._loadLidMap();
    // Seed known mappings
    this._recordLidPhone('208023050051744', '923046588806');
  }

  _recordLidPhone(rawLid, rawPhone) {
    if (!rawLid || !rawPhone) return;
    const lidClean = String(rawLid).split('@')[0].split(':')[0].replace(/\D/g, '');
    let phoneClean = String(rawPhone).split('@')[0].split(':')[0].replace(/\D/g, '');
    if (phoneClean.startsWith('0092')) phoneClean = phoneClean.slice(2);
    else if (phoneClean.startsWith('03') && phoneClean.length === 11) phoneClean = '92' + phoneClean.slice(1);
    else if (phoneClean.startsWith('3') && phoneClean.length === 10) phoneClean = '92' + phoneClean;
    if (!lidClean || !phoneClean || lidClean === phoneClean) return;
    this._lidToPhone.set(lidClean, phoneClean);
    this._phoneToLid.set(phoneClean, lidClean);
    this._saveLidMap();
  }

  _loadLidMap() {
    try {
      if (fs.existsSync(this.lidMapPath)) {
        const data = JSON.parse(fs.readFileSync(this.lidMapPath, 'utf8'));
        if (data && typeof data === 'object') {
          for (const [lid, phone] of Object.entries(data)) {
            if (lid && phone) {
              this._lidToPhone.set(String(lid), String(phone));
              this._phoneToLid.set(String(phone), String(lid));
            }
          }
        }
      }
    } catch (_) {}
  }

  _saveLidMap() {
    clearTimeout(this._saveLidTimer);
    this._saveLidTimer = setTimeout(() => {
      try {
        const obj = {};
        for (const [lid, phone] of this._lidToPhone.entries()) {
          obj[lid] = phone;
        }
        const tmp = this.lidMapPath + '.tmp';
        fs.mkdirSync(path.dirname(this.lidMapPath), { recursive: true, mode: 0o700 });
        fs.writeFileSync(tmp, JSON.stringify(obj, null, 2), 'utf8');
        fs.renameSync(tmp, this.lidMapPath);
      } catch (_) {}
    }, 500);
  }

  _loadRawStore() {
    try {
      if (fs.existsSync(this.rawStorePath)) {
        const revive = (k, v) => (v && v.type === 'Buffer' && Array.isArray(v.data)) ? Buffer.from(v.data) : v;
        const data = JSON.parse(fs.readFileSync(this.rawStorePath, 'utf8'), revive);
        if (data && typeof data === 'object') {
          for (const [k, v] of Object.entries(data)) {
            if (typeof v === 'string') {
              try {
                this._rawMessageStore.set(k, proto.Message.decode(Buffer.from(v, 'base64')));
              } catch (_) {}
            } else if (v && typeof v === 'object') {
              try {
                this._rawMessageStore.set(k, proto.Message.fromObject(v));
              } catch (_) {
                this._rawMessageStore.set(k, v);
              }
            }
          }
        }
      }
    } catch (_) {}
  }

  _saveRawStore() {
    clearTimeout(this._saveRawTimer);
    this._saveRawTimer = setTimeout(() => {
      try {
        const obj = {};
        const entries = [...this._rawMessageStore.entries()].slice(-2000);
        for (const [k, v] of entries) {
          try {
            const buf = proto.Message.encode(proto.Message.fromObject(v)).finish();
            obj[k] = Buffer.from(buf).toString('base64');
          } catch (_) {
            obj[k] = v;
          }
        }
        const tmp = this.rawStorePath + '.tmp';
        fs.mkdirSync(path.dirname(this.rawStorePath), { recursive: true, mode: 0o700 });
        fs.writeFileSync(tmp, JSON.stringify(obj), 'utf8');
        fs.renameSync(tmp, this.rawStorePath);
      } catch (_) {}
    }, 1000);
  }

  async initialize() {
    this._destroyed = false;
    fs.mkdirSync(this.authPath, { recursive: true, mode: 0o700 });
    const { state, saveCreds } = await useMultiFileAuthState(this.authPath);
    let version = [2, 3000, 1043857760];
    try {
      const v = await fetchLatestBaileysVersion();
      if (v?.version) version = v.version;
    } catch (_) {}

    this.sock = makeWASocket({
      version,
      auth: state,
      printQRInTerminal: false,
      logger: pino({ level: 'silent' }),
      browser: Browsers.ubuntu('Chrome'),
      syncFullHistory: true,
      msgRetryCounterCache: this._msgRetryCache,
      getMessage: async (key) => {
        const id = key?.id;
        if (id && this._rawMessageStore.has(id)) {
          const raw = this._rawMessageStore.get(id);
          try {
            return proto.Message.fromObject(raw);
          } catch (_) {
            return raw;
          }
        }
        const cached = this._messageCache.get(id);
        if (cached?.body) {
          return { conversation: cached.body };
        }
        return undefined;
      }
    });

    this.sock.ev.on('creds.update', saveCreds);

    this.sock.ev.on('connection.update', async (update) => {
      if (this._destroyed) return;
      const { connection, lastDisconnect, qr } = update;

      if (qr) {
        this.emit('qr', qr);
      }

      if (connection === 'close') {
        const statusCode = lastDisconnect?.error?.output?.statusCode;
        const loggedOut = statusCode === DisconnectReason.loggedOut;
        if (loggedOut) {
          this.emit('auth_failure');
          this.emit('disconnected');
        } else {
          this.emit('disconnected');
          if (!this._destroyed) {
            setTimeout(() => this.initialize().catch(() => {}), 4000);
          }
        }
      } else if (connection === 'open') {
        const rawId = this.sock.user?.id || '';
        const userNum = rawId.split(':')[0] || rawId.split('@')[0];
        this.info.wid.user = userNum;
        if (this.sock.user?.lid) {
          this._recordLidPhone(this.sock.user.lid, userNum);
        }
        this.emit('authenticated');
        this.emit('ready');
      }
    });

    const registerContacts = (contactsList) => {
      for (const c of contactsList || []) {
        if (!c) continue;
        const phone = c.id ? c.id.split('@')[0].split(':')[0] : '';
        const name = c.name || c.notify || c.verifiedName || '';
        if (phone && name) {
          this._contacts.set(phone, name);
          this._contacts.set(c.id, name);
        }
        if (c.lid && phone) {
          this._recordLidPhone(c.lid, phone);
        }
      }
    };

    this.sock.ev.on('contacts.upsert', registerContacts);
    this.sock.ev.on('contacts.update', registerContacts);
    this.sock.ev.on('chats.phoneNumberShare', ({ lid, jid }) => {
      if (lid && jid) this._recordLidPhone(lid, jid);
    });

    const adaptMessage = msg => {
      if (!msg?.message || !msg.key?.id) return null;
      const content = normalizeMessageContent(msg.message) || msg.message;
      const fromMe = Boolean(msg.key?.fromMe);
      let jid = msg.key?.remoteJid || '';
      const myNum = this.info?.wid?.user || '';
      const myLid = this.sock?.user?.lid ? this.sock.user.lid.split('@')[0].split(':')[0] : '';

      // Normalize LID to real phone if known or if context links to our previous chat
      if (jid.endsWith('@lid')) {
        const lidClean = jid.split('@')[0].split(':')[0];
        if (lidClean === myLid && myNum) {
          jid = `${myNum}@s.whatsapp.net`;
        } else if (this._lidToPhone.has(lidClean)) {
          jid = `${this._lidToPhone.get(lidClean)}@s.whatsapp.net`;
        } else {
          const stanza = content.extendedTextMessage?.contextInfo?.stanzaId;
          if (stanza) {
            const cached = this._messageCache.get(stanza);
            const targetJid = cached?.to || cached?.from;
            if (targetJid && !targetJid.endsWith('@lid')) {
              const targetPhone = targetJid.split('@')[0].split(':')[0];
              this._recordLidPhone(lidClean, targetPhone);
              jid = `${targetPhone}@s.whatsapp.net`;
            }
          }
        }
      }

      const media = content.imageMessage || content.videoMessage || content.audioMessage || content.documentMessage || content.stickerMessage;
      const isAudio = Boolean(content.audioMessage);
      const isImage = Boolean(content.imageMessage || content.stickerMessage);
      const isVideo = Boolean(content.videoMessage);
      const isDoc = Boolean(content.documentMessage);
      const type = isAudio ? 'audio' : (isImage ? 'image' : (isVideo ? 'video' : (isDoc ? 'document' : 'chat')));
      const text = content.conversation ||
        content.extendedTextMessage?.text ||
        content.imageMessage?.caption ||
        content.videoMessage?.caption ||
        content.documentMessage?.caption ||
        content.documentMessage?.title ||
        content.buttonsResponseMessage?.selectedDisplayText ||
        content.listResponseMessage?.title ||
        content.templateButtonReplyMessage?.selectedId ||
        content.protocolMessage?.editedMessage?.conversation ||
        content.protocolMessage?.editedMessage?.extendedTextMessage?.text ||
        '';

      // Drop non-media protocol frames with empty text (reactions, stubs, receipts)
      if (type === 'chat' && !text.trim() && !media) {
        return null;
      }

      const extension = isVideo ? 'mp4' : (isImage ? (content.stickerMessage ? 'webp' : 'jpg') : (isAudio ? 'ogg' : 'bin'));
      const filename = content.documentMessage?.fileName || media?.fileName || `${type}.${extension}`;

      // Do NOT invent names: leave notifyName empty so contact names are strictly user-managed
      const notifyName = '';

      return {
        id: { _serialized: msg.key.id, fromMe, remote: jid },
        from: fromMe ? `${myNum}@s.whatsapp.net` : jid,
        to: fromMe ? jid : `${myNum}@s.whatsapp.net`,
        body: text,
        timestamp: Number(msg.messageTimestamp || Math.floor(Date.now() / 1000)),
        hasMedia: Boolean(media),
        type,
        _data: { notifyName, duration: media?.seconds || 0 },
        downloadMedia: async () => {
          try {
            const buffer = await downloadMediaMessage(msg, 'buffer', {});
            return { data: buffer.toString('base64'), mimetype: media?.mimetype || (content.stickerMessage ? 'image/webp' : 'application/octet-stream'), filename };
          } catch (err) {
            console.error('[Baileys] Media download failed:', err.message);
            return null;
          }
        }
      };
    };

    const emitMessages = messages => {
      for (const msg of messages || []) {
        const adaptedMsg = adaptMessage(msg);
        if (!adaptedMsg) continue;
        this._messageCache.set(msg.key.id, adaptedMsg);
        if (this._messageCache.size > 2000) {
          this._messageCache.delete(this._messageCache.keys().next().value);
        }
        this.emit('message_create', adaptedMsg);
      }
    };

    // Handle incoming and outgoing live messages
    this.sock.ev.on('messages.upsert', ({ messages }) => {
      for (const msg of messages || []) {
        if (msg.key?.id && msg.message) {
          this._rawMessageStore.set(msg.key.id, msg.message);
        }
      }
      this._saveRawStore();
      emitMessages(messages);
    });

    // Handle full chat history sync from newly linked device
    this.sock.ev.on('messaging-history.set', async ({ chats, contacts, messages }) => {
      console.log(`[Baileys] History sync received: ${chats?.length || 0} chats, ${messages?.length || 0} messages.`);
      registerContacts(contacts);
      for (const msg of messages || []) {
        if (msg.key?.id && msg.message) {
          this._rawMessageStore.set(msg.key.id, msg.message);
        }
      }
      this._saveRawStore();
      emitMessages((messages || []).filter(msg => {
        const jid = msg.key?.remoteJid || '';
        return !jid.endsWith('@g.us') && !jid.endsWith('@broadcast') && !jid.endsWith('@newsletter');
      }));
    });

    // Handle delivery / read receipts
    this.sock.ev.on('messages.update', updates => {
      for (const update of updates || []) {
        if (update.update?.status) {
          const status = update.update.status;
          const ack = status >= 4 ? 3 : (status === 3 ? 2 : 1);
          const remoteJid = update.key?.remoteJid || '';
          this.emit('message_ack', {
            id: { _serialized: update.key.id, fromMe: update.key.fromMe, remote: remoteJid },
            fromMe: update.key.fromMe,
            from: remoteJid,
            to: remoteJid
          }, ack);
        }
      }
    });

    // Intercept retry receipts directly from the socket to eliminate "Waiting for this message"
    if (this.sock.ws) {
      this.sock.ws.on('CB:receipt', async (node) => {
        try {
          if (node?.attrs?.type === 'retry' && node?.attrs?.id) {
            const id = node.attrs.id;
            const from = node.attrs.from || '';
            const participant = node.attrs.participant || from;
            console.log(`[Baileys] Socket retry receipt received for msg ${id} from ${participant}`);

            let rawMsg = null;
            if (this._rawMessageStore.has(id)) {
              rawMsg = this._rawMessageStore.get(id);
            } else if (this._messageCache.has(id)) {
              const c = this._messageCache.get(id);
              if (c?.body) rawMsg = { conversation: c.body };
            }

            if (rawMsg) {
              const retryChild = Array.isArray(node.content) ? node.content.find(c => c?.tag === 'retry') : null;
              const count = +(retryChild?.attrs?.count || 1);
              console.log(`[Baileys] Re-encrypting & resending message ${id} to ${participant} (retry count ${count})`);

              // Auto-heal: If retry count >= 2, the phone's local ratchet is desynced. Purge stale session so WhatsApp issues fresh prekeys.
              if (count >= 2) {
                console.log(`[Baileys Auto-Heal] Purging desynchronized session record for ${participant} to force clean handshake...`);
                this.resetContactSession(participant);
              }

              try {
                if (typeof this.sock.assertSessions === 'function') {
                  await this.sock.assertSessions([participant], true);
                }
              } catch (_) {}

              await this.sock.relayMessage(from, rawMsg, {
                messageId: id,
                participant: { jid: participant, count }
              });
              console.log(`[Baileys] Re-relay completed successfully for msg ${id} to ${participant}`);
            }
          }
        } catch (err) {
          console.error('[Baileys] Error in socket retry receipt handler:', err?.message || err);
        }
      });
    }
  }

  _normalizeJid(jidOrPhone) {
    let raw = String(jidOrPhone || '').trim();
    if (raw.endsWith('@s.whatsapp.net') || raw.endsWith('@c.us')) {
      raw = raw.split('@')[0];
    }
    raw = raw.replace(/\D/g, '');
    if (raw.startsWith('0092')) raw = raw.slice(2);
    else if (raw.startsWith('03') && raw.length === 11) raw = '92' + raw.slice(1);
    else if (raw.startsWith('3') && raw.length === 10) raw = '92' + raw;
    return `${raw}@s.whatsapp.net`;
  }

  async getNumberId(phone) {
    if (!this.sock) return null;
    const cleanPhone = phone.replace(/\D/g, '');
    let norm = cleanPhone;
    if (norm.startsWith('0092')) norm = norm.slice(2);
    else if (norm.startsWith('03') && norm.length === 11) norm = '92' + norm.slice(1);
    else if (norm.startsWith('3') && norm.length === 10) norm = '92' + norm;

    try {
      const [result] = await this.sock.onWhatsApp(norm);
      if (result && result.exists) {
        if (result.lid) {
          this._recordLidPhone(result.lid, result.jid || norm);
        }
        return { _serialized: result.jid };
      }
      if (result && result.exists === false) return null;
    } catch (_) {}
    return { _serialized: `${norm}@s.whatsapp.net` };
  }

  async resolveLid(rawLid) {
    if (!rawLid) return null;
    const lidClean = String(rawLid).split('@')[0].split(':')[0].replace(/\D/g, '');
    if (this._lidToPhone.has(lidClean)) {
      return this._lidToPhone.get(lidClean);
    }
    for (const [phone, lid] of this._phoneToLid.entries()) {
      if (lid === lidClean) {
        this._lidToPhone.set(lidClean, phone);
        return phone;
      }
    }
    // Proactively query leads from leads.json
    try {
      const leadsPath = path.resolve(__dirname, '../data/leads.json');
      if (fs.existsSync(leadsPath)) {
        const leads = JSON.parse(fs.readFileSync(leadsPath, 'utf8'));
        if (Array.isArray(leads) && this.sock) {
          for (const lead of leads) {
            const phone = lead.phone ? String(lead.phone).replace(/\D/g, '') : '';
            if (phone && !this._phoneToLid.has(phone)) {
              try {
                const [res] = await this.sock.onWhatsApp(phone);
                if (res?.lid) {
                  const resLid = res.lid.split('@')[0].split(':')[0];
                  this._recordLidPhone(resLid, phone);
                  if (resLid === lidClean) return phone;
                }
              } catch (_) {}
            }
          }
        }
      }
    } catch (_) {}
    return null;
  }

  resetContactSession(contactJidOrPhone) {
    if (!contactJidOrPhone) return false;
    const clean = String(contactJidOrPhone).split('@')[0].split(':')[0].replace(/\D/g, '');
    if (!clean) return false;
    try {
      if (fs.existsSync(this.authPath)) {
        const files = fs.readdirSync(this.authPath);
        for (const file of files) {
          if (file.startsWith('session-') && (file.includes(clean) || (clean === '923046588806' && file.includes('208023050051744')))) {
            try {
              fs.unlinkSync(path.join(this.authPath, file));
              console.log(`[Baileys Auto-Heal] Purged stale session file: ${file}`);
            } catch (_) {}
          }
        }
      }
      return true;
    } catch (e) {
      console.warn('[Baileys Auto-Heal] resetContactSession notice:', e.message);
      return false;
    }
  }

  async getContactLidAndPhone(ids) {
    const results = [];
    const myLid = this.sock?.user?.lid ? this.sock.user.lid.split('@')[0].split(':')[0] : '';
    const myNum = this.info?.wid?.user || '';
    for (const rawId of ids || []) {
      const id = String(rawId || '').trim();
      const clean = id.split('@')[0].split(':')[0].replace(/\D/g, '');
      if (clean === myLid && myNum) {
        results.push({ lid: `${myLid}@lid`, pn: `${myNum}@s.whatsapp.net` });
        continue;
      }
      if (this._lidToPhone.has(clean)) {
        const pn = this._lidToPhone.get(clean);
        results.push({ lid: `${clean}@lid`, pn: `${pn}@s.whatsapp.net` });
        continue;
      }
      if (this._phoneToLid.has(clean)) {
        const lid = this._phoneToLid.get(clean);
        results.push({ lid: `${lid}@lid`, pn: `${clean}@s.whatsapp.net` });
        continue;
      }
    }
    return results;
  }

  async getChatById(chatId) {
    const cleanJid = this._normalizeJid(chatId);
    return {
      id: { _serialized: cleanJid },
      fetchMessages: async ({ limit = 50 } = {}) => {
        const msgs = [];
        for (const msg of this._messageCache.values()) {
          if (msg.from === cleanJid || msg.to === cleanJid || msg.id?.remote === cleanJid) {
            msgs.push(msg);
          }
        }
        return msgs.slice(-limit);
      }
    };
  }

  async getChats() {
    const chatMap = new Map();
    for (const msg of this._messageCache.values()) {
      const remote = msg.id?.remote || (msg.fromMe ? msg.to : msg.from);
      if (remote && !chatMap.has(remote)) {
        chatMap.set(remote, {
          id: { _serialized: remote },
          isGroup: remote.endsWith('@g.us'),
          timestamp: msg.timestamp || 0,
          fetchMessages: async ({ limit = 30 } = {}) => {
            const msgs = [];
            for (const m of this._messageCache.values()) {
              if (m.from === remote || m.to === remote || m.id?.remote === remote) msgs.push(m);
            }
            return msgs.slice(-limit);
          }
        });
      }
    }
    return [...chatMap.values()];
  }

  async sendMessage(toJid, content, options = {}) {
    if (!this.sock) throw new Error('WhatsApp connection is not initialized.');
    const jid = this._normalizeJid(toJid);
    let sent;
    const isVoice = Boolean(options.sendAudioAsVoice || (content?.mimetype?.startsWith('audio/') && options.voice));

    console.log(`[Baileys] Sending to ${jid}: ${typeof content === 'string' ? content.slice(0, 40) : (isVoice ? 'Voice Note' : (content?.mimetype || 'media'))}`);

    if (typeof content === 'string') {
      sent = await this.sock.sendMessage(jid, { text: content });
    } else if (content && content.data && content.mimetype) {
      let buffer = Buffer.from(content.data, 'base64');
      if (isVoice) {
        // WhatsApp PTT requires genuine OGG OPUS container for native mobile voice note playback
        buffer = await ensureOggOpus(buffer);
        sent = await this.sock.sendMessage(jid, {
          audio: buffer,
          mimetype: 'audio/ogg; codecs=opus',
          ptt: true
        });
      } else if (content.mimetype.startsWith('image/')) {
        sent = await this.sock.sendMessage(jid, {
          image: buffer,
          caption: options.caption || ''
        });
      } else if (content.mimetype.startsWith('audio/')) {
        sent = await this.sock.sendMessage(jid, {
          audio: buffer,
          mimetype: content.mimetype,
          ptt: false
        });
      } else if (content.mimetype.startsWith('video/')) {
        sent = await this.sock.sendMessage(jid, {
          video: buffer,
          mimetype: content.mimetype,
          caption: options.caption || ''
        });
      } else {
        // Document sending (PDF, Word, Excel, text, zip, etc.)
        sent = await this.sock.sendMessage(jid, {
          document: buffer,
          mimetype: content.mimetype || 'application/pdf',
          fileName: content.filename || 'document.pdf',
          caption: options.caption || ''
        });
      }
    } else {
      sent = await this.sock.sendMessage(jid, { text: String(content) });
    }

    const id = sent?.key?.id || `BAIL_${Date.now()}`;
    const myNum = this.info?.wid?.user || '';

    // CRITICAL: Cache raw message proto so retry requests can decrypt and prevent "Waiting for this message"
    if (sent?.key?.id && sent?.message) {
      this._rawMessageStore.set(sent.key.id, sent.message);
      this._saveRawStore();
    }

    console.log(`[Baileys] Message sent successfully! ID: ${id} to ${jid}`);

    const adaptedOut = {
      id: { _serialized: id, fromMe: true, remote: jid },
      fromMe: true,
      from: `${myNum}@s.whatsapp.net`,
      to: jid,
      body: typeof content === 'string'
        ? content
        : (options.caption || (isVoice ? 'Voice note' : (content.mimetype?.startsWith('image/') ? 'Image' : (content.filename || 'Document')))),
      type: isVoice ? 'audio' : (content.mimetype?.startsWith('image/') ? 'image' : (content.mimetype?.startsWith('video/') ? 'video' : (content.mimetype ? 'document' : 'chat'))),
      hasMedia: Boolean(content && content.data),
      timestamp: Math.floor(Date.now() / 1000),
      ack: 1,
      downloadMedia: async () => {
        if (!content || !content.data) return null;
        return { data: content.data, mimetype: content.mimetype || 'application/octet-stream', filename: content.filename || 'file' };
      }
    };
    this._messageCache.set(id, adaptedOut);
    if (this._messageCache.size > 2000) {
      this._messageCache.delete(this._messageCache.keys().next().value);
    }

    return adaptedOut;
  }

  async getMessageById(id) {
    if (this._messageCache.has(id)) return this._messageCache.get(id);
    if (this._rawMessageStore.has(id)) {
      const raw = this._rawMessageStore.get(id);
      const fakeMsg = { key: { id }, message: raw };
      const media = raw.imageMessage || raw.videoMessage || raw.audioMessage || raw.documentMessage || raw.stickerMessage;
      if (!media) return null;
      return {
        id: { _serialized: id },
        hasMedia: true,
        downloadMedia: async () => {
          try {
            const buffer = await downloadMediaMessage(fakeMsg, 'buffer', {});
            return { data: buffer.toString('base64'), mimetype: media.mimetype || (raw.stickerMessage ? 'image/webp' : 'application/octet-stream'), filename: media.fileName || '' };
          } catch (err) {
            console.error('[Baileys] Media download fallback failed:', err.message);
            return null;
          }
        }
      };
    }
    return null;
  }

  async resetSession() {
    this._destroyed = true;
    if (this.sock) {
      try { this.sock.end(undefined); } catch (_) {}
      this.sock = null;
    }
    this._rawMessageStore.clear();
    this._messageCache.clear();
    this._contacts.clear();
    this._lidToPhone.clear();
    this._phoneToLid.clear();
    try {
      if (fs.existsSync(this.rawStorePath)) fs.unlinkSync(this.rawStorePath);
      if (fs.existsSync(this.authPath)) {
        fs.rmSync(this.authPath, { recursive: true, force: true });
      }
    } catch (_) {}
    this._destroyed = false;
  }

  async destroy() {
    this._destroyed = true;
    if (this.sock) {
      try { this.sock.end(undefined); } catch (_) {}
      this.sock = null;
    }
  }
}

module.exports = { BaileysClient };
