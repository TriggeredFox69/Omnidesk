'use strict';
/**
 * Baileys WhatsApp Engine for Headless / CloudLinux / cPanel Environments
 * Zero Chromium or browser dependencies required.
 * Answers WhatsApp's resend requests from the saved message store, keeps the
 * LID <-> phone map in step with Baileys, and writes keys atomically.
 */
const { EventEmitter } = require('node:events');
const path = require('node:path');
const fs = require('node:fs');
// Baileys v7 is ESM-only. Load it dynamically from this CommonJS bridge.
// (bridge.bundle.js has it built in, so the server needs no npm install.)
let baileysApi;
let makeWASocket, DisconnectReason, makeCacheableSignalKeyStore, initAuthCreds, BufferJSON;
let downloadMediaMessage, normalizeMessageContent, proto, Browsers, fetchLatestBaileysVersion;
const { createAtomicAuthState } = require('./auth-state');

async function loadBaileys() {
  if (baileysApi) return baileysApi;
  const mod = await import('@whiskeysockets/baileys');
  // ESM build: named exports + default = makeWASocket. An old CommonJS build
  // arrives wrapped as { default: module.exports }.
  const api = typeof mod.makeWASocket === 'function' ? mod : (mod.default && typeof mod.default === 'object' ? mod.default : mod);
  const make = typeof api.makeWASocket === 'function' ? api.makeWASocket : (typeof api.default === 'function' ? api.default : null);
  if (!make) throw new Error('The installed @whiskeysockets/baileys package is not usable (makeWASocket missing).');
  baileysApi = { ...api, makeWASocket: make };
  return baileysApi;
}

// WhatsApp Web version: fetched once per process, never allowed to stall a connect.
let waVersionPromise = null;
function getWaVersion() {
  if (!waVersionPromise) {
    const fallback = [2, 3000, 1043857760];
    waVersionPromise = Promise.race([
      Promise.resolve().then(() => fetchLatestBaileysVersion()).then(v => (v && Array.isArray(v.version) ? v.version : fallback)).catch(() => fallback),
      new Promise(resolve => setTimeout(() => resolve(fallback), 6000))
    ]);
  }
  return waVersionPromise;
}

const RAW_STORE_LIMIT = 5000;
const NUMBER_CACHE_MS = 6 * 60 * 60 * 1000;
let pino;
try {
  pino = require('pino');
} catch (_) {
  pino = () => ({ level: 'silent', trace: () => {}, debug: () => {}, info: () => {}, warn: () => {}, error: () => {}, fatal: () => {}, child: () => pino() });
}
let NodeCache;
try {
  NodeCache = require('@cacheable/node-cache').default || require('@cacheable/node-cache');
} catch (_) {
  try { NodeCache = require('node-cache'); } catch (__) {
    NodeCache = class { constructor() { this.m = new Map(); } get(k) { return this.m.get(k); } set(k, v) { this.m.set(k, v); } del(k) { this.m.delete(k); } has(k) { return this.m.has(k); } flushAll() { this.m.clear(); } };
  }
}
const { ensureOggOpus, isOgg } = require('./audio');

class BaileysClient extends EventEmitter {
  constructor({ authPath, deviceName = 'Omnidesk Cloud' } = {}) {
    super();
    this.isBaileys = true;
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
    this._presences = new Map();
    this._msgRetryCache = new NodeCache({ stdTTL: 3600, useClones: false });
    this._numberCache = new Map();
    this._sentTo = new Map();   // message id -> chat it was sent to (answers resend requests)
    this._rawStoreLoaded = false;
    this._loadLidMap();
  }

  _resolvePhoneFromJid(jid) {
    if (!jid) return '';
    const clean = String(jid).split('@')[0].split(':')[0].replace(/\D/g, '');
    if (String(jid).endsWith('@lid')) {
      if (this._lidToPhone.has(clean)) return this._lidToPhone.get(clean);
      for (const [phone, lid] of this._phoneToLid.entries()) {
        if (lid === clean) return phone;
      }
      return clean;
    }
    let norm = clean;
    if (norm.startsWith('0092')) norm = norm.slice(2);
    else if (norm.startsWith('03') && norm.length === 11) norm = '92' + norm.slice(1);
    else if (norm.startsWith('3') && norm.length === 10) norm = '92' + norm;
    return norm;
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
    this._saveRawTimer = setTimeout(() => { this._saveRawTimer = null; this._writeRawStoreNow(); }, 1000);
  }

  _writeRawStoreNow() {
    {
      try {
        const obj = {};
        const entries = [...this._rawMessageStore.entries()].slice(-RAW_STORE_LIMIT);
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
    }
  }

  _rememberRaw(id, message) {
    if (!id || !message) return;
    this._rawMessageStore.delete(id);
    this._rawMessageStore.set(id, message);
    while (this._rawMessageStore.size > RAW_STORE_LIMIT) this._rawMessageStore.delete(this._rawMessageStore.keys().next().value);
    this._saveRawStore();
  }

  _scheduleReconnect(delayOverride) {
    if (this._destroyed) return;
    if (this._reconnectTimer) clearTimeout(this._reconnectTimer);
    const delay = typeof delayOverride === 'number' ? delayOverride : Math.min(25000, Math.max(3000, ((this._reconnectAttempts || 0) + 1) * 3000));
    this._reconnectAttempts = (this._reconnectAttempts || 0) + 1;
    console.log(`[Baileys] Socket closed. Auto-reconnecting in ${delay / 1000}s (attempt ${this._reconnectAttempts})...`);
    this._reconnectTimer = setTimeout(async () => {
      this._reconnectTimer = null;
      if (this._destroyed) return;
      try {
        if (this.sock) {
          try { this.sock.ev.removeAllListeners(); } catch (_) {}
          try { this.sock.end(undefined); } catch (_) {}
        }
        await this.initialize();
      } catch (err) {
        console.error(`[Baileys] Reconnect failed: ${err.message}. Retrying...`);
        this._scheduleReconnect();
      }
    }, delay);
  }

  _isValidCreds(filePath) {
    try {
      if (!fs.existsSync(filePath) || fs.statSync(filePath).size < 100) return false;
      const content = JSON.parse(fs.readFileSync(filePath, 'utf8'));
      return Boolean(content && (content.me?.id || content.registered === true));
    } catch (_) {
      return false;
    }
  }

  async initialize() {
    this._destroyed = false;
    fs.mkdirSync(this.authPath, { recursive: true, mode: 0o700 });

    await loadBaileys();
    ({
      makeWASocket,
      DisconnectReason,
      makeCacheableSignalKeyStore,
      initAuthCreds,
      BufferJSON,
      downloadMediaMessage,
      normalizeMessageContent,
      proto,
      Browsers,
      fetchLatestBaileysVersion
    } = baileysApi);
    if (!this._rawStoreLoaded) { this._rawStoreLoaded = true; this._loadRawStore(); }

    // Restore creds.json from the last good copy ONLY if the live file is damaged.
    const credsFile = path.join(this.authPath, 'creds.json');
    const backupFile = path.join(this.authPath, '../creds.json.bak');
    const altBackup = path.join(this.authPath + '.bak', 'creds.json');
    if (fs.existsSync(credsFile) && !this._isValidCreds(credsFile)) {
      const good = [backupFile, altBackup].find(f => this._isValidCreds(f));
      if (good) {
        console.log('[Baileys] creds.json was damaged; restoring the last good copy.');
        try { fs.copyFileSync(good, credsFile + '.restore'); fs.renameSync(credsFile + '.restore', credsFile); } catch (_) {}
      }
    } else if (!fs.existsSync(credsFile)) {
      // No login here (new device or a deliberate wipe): never resurrect an old one.
      try { if (fs.existsSync(backupFile)) fs.unlinkSync(backupFile); } catch (_) {}
      try { if (fs.existsSync(path.dirname(altBackup))) fs.rmSync(path.dirname(altBackup), { recursive: true, force: true }); } catch (_) {}
    }

    this._auth = createAtomicAuthState(this.authPath, { proto, initAuthCreds, BufferJSON });
    const { state, saveCreds } = this._auth;
    const logger = pino({ level: process.env.WHATSAPP_BRIDGE_LOG_LEVEL || 'warn' });
    this._logger = logger;
    const version = await getWaVersion();
    if (this._destroyed) return;

    const getMessageFn = async (key) => {
      const id = key?.id;
      if (!id) return undefined;
      const remote = key?.remoteJid || '';
      const comboKey = remote ? `${remote}_${id}` : '';
      const cleanRemote = remote.split('@')[0].split(':')[0].replace(/\D/g, '');
      const cleanCombo = cleanRemote ? `${cleanRemote}_${id}` : '';

      if (this._rawMessageStore.has(id)) {
        const raw = this._rawMessageStore.get(id);
        try {
          return proto.Message.fromObject(raw);
        } catch (_) {
          return raw;
        }
      }
      if (comboKey && this._rawMessageStore.has(comboKey)) {
        const raw = this._rawMessageStore.get(comboKey);
        try {
          return proto.Message.fromObject(raw);
        } catch (_) {
          return raw;
        }
      }
      if (cleanCombo && this._rawMessageStore.has(cleanCombo)) {
        const raw = this._rawMessageStore.get(cleanCombo);
        try {
          return proto.Message.fromObject(raw);
        } catch (_) {
          return raw;
        }
      }
      // Never fabricate a retry payload from CRM text: WhatsApp needs the
      // original message proto to resend the same encrypted message safely.
      return undefined;
    };
    this.getMessage = getMessageFn;

    this.sock = makeWASocket({
      version,
      // Signal keys are cached in memory and written to disk atomically.
      auth: { creds: state.creds, keys: makeCacheableSignalKeyStore(state.keys, logger) },
      printQRInTerminal: false,
      logger,
      browser: Browsers.ubuntu('Chrome'),
      // Chatly keeps conversations created in Chatly and their live replies;
      // never import a linked phone's personal WhatsApp history.
      syncFullHistory: false,
      enableRecentMessageCache: true,
      enableAutoSessionRecreation: true,
      keepAliveIntervalMs: 25000,
      connectTimeoutMs: 60000,
      defaultQueryTimeoutMs: 60000,
      retryRequestDelayMs: 250,
      // false = the TM's own phone keeps getting WhatsApp notifications while Chatly is linked.
      markOnlineOnConnect: false,
      // Groups, status updates and channels are never Chatly conversations; skipping
      // them avoids decrypting (and retrying) thousands of messages from big groups.
      shouldIgnoreJid: jid => typeof jid === 'string' && (jid.endsWith('@g.us') || jid.endsWith('@broadcast') || jid.endsWith('@newsletter')),
      msgRetryCounterCache: this._msgRetryCache,
      getMessage: getMessageFn,
      // Used by scripts/patch-baileys.js: a resend request from one of our own devices
      // may omit the chat; we know where every message we sent went.
      resolveRetryDestination: id => this._sentTo.get(id)
    });
    const sock = this.sock;

    const safeSaveCreds = async () => {
      await saveCreds();
      try {
        if (this._isValidCreds(credsFile)) {
          fs.copyFileSync(credsFile, backupFile + '.tmp');
          fs.renameSync(backupFile + '.tmp', backupFile);
          const altDir = path.dirname(altBackup);
          fs.mkdirSync(altDir, { recursive: true, mode: 0o700 });
          fs.copyFileSync(credsFile, altBackup + '.tmp');
          fs.renameSync(altBackup + '.tmp', altBackup);
        }
      } catch (_) {}
    };

    this.sock.ev.on('creds.update', safeSaveCreds);

    this.sock.ev.on('connection.update', async (update) => {
      if (this._destroyed || this.sock !== sock) return;
      const { connection, lastDisconnect, qr } = update;

      if (qr) {
        // Clean slate for new login: purge stale contact session files from any previous account
        try {
          if (fs.existsSync(this.authPath)) {
            const files = fs.readdirSync(this.authPath);
            for (const f of files) {
              if (f.startsWith('session-')) {
                try { fs.unlinkSync(path.join(this.authPath, f)); } catch (_) {}
              }
            }
          }
        } catch (_) {}
        this.emit('qr', qr);
      }

      if (connection === 'close') {
        const statusCode = lastDisconnect?.error?.output?.statusCode;
        const loggedOut = statusCode === DisconnectReason.loggedOut;
        if (loggedOut) {
          console.warn('[Baileys] WhatsApp session logged out or revoked. Purging credentials and backups...');
          try {
            const backupFile = path.join(this.authPath, '../creds.json.bak');
            const altBak = this.authPath + '.bak';
            if (fs.existsSync(backupFile)) try { fs.unlinkSync(backupFile); } catch (_) {}
            if (fs.existsSync(altBak)) try { fs.rmSync(altBak, { recursive: true, force: true }); } catch (_) {}
            if (fs.existsSync(this.authPath)) {
              fs.rmSync(this.authPath, { recursive: true, force: true });
            }
          } catch (_) {}
          this.emit('auth_failure');
          this.emit('disconnected');
        } else if (statusCode === DisconnectReason.restartRequired) {
          // Normal right after a QR scan: WhatsApp asks for a fresh socket. Do it at once.
          console.log('[Baileys] Pairing complete; restarting the socket to finish login.');
          this._scheduleReconnect(300);
        } else if (statusCode === DisconnectReason.connectionReplaced) {
          // Another session took over this login (a second bridge copy, or the same
          // creds on another PC). Reconnecting at once makes both kick each other in a
          // loop, so wait a minute before trying again.
          console.warn('[Baileys] Session replaced by another connection with the same login. Retrying in 60s.');
          this.emit('disconnected');
          if (!this._destroyed) {
            if (this._reconnectTimer) clearTimeout(this._reconnectTimer);
            this._reconnectAttempts = 8;
            this._reconnectTimer = setTimeout(() => { this._reconnectTimer = null; this._scheduleReconnect(); }, 60000);
          }
        } else {
          this.emit('disconnected');
          if (!this._destroyed) {
            this._scheduleReconnect();
          }
        }
      } else if (connection === 'open') {
        this._reconnectAttempts = 0;
        if (this._reconnectTimer) {
          clearTimeout(this._reconnectTimer);
          this._reconnectTimer = null;
        }
        const rawId = this.sock.user?.id || '';
        const userNum = rawId.split(':')[0] || rawId.split('@')[0];
        this.info.wid.user = userNum;
        if (this.sock.user?.lid) {
          this._recordLidPhone(this.sock.user.lid, userNum);
        }
        // Save verified working credentials to backup
        try {
          const credsFile = path.join(this.authPath, 'creds.json');
          const backupFile = path.join(this.authPath, '../creds.json.bak');
          if (this._isValidCreds(credsFile)) {
            fs.copyFileSync(credsFile, backupFile + '.tmp');
            fs.renameSync(backupFile + '.tmp', backupFile);
          }
        } catch (_) {}
        this.emit('authenticated');
        this.emit('ready');
      }
    });

    const registerContacts = (contactsList) => {
      for (const c of contactsList || []) {
        if (!c || !c.id) continue;
        const idIsLid = String(c.id).endsWith('@lid');
        const pnJid = idIsLid ? (c.phoneNumber || c.jid || '') : c.id;
        const lidJid = idIsLid ? c.id : (c.lid || '');
        const phone = pnJid ? String(pnJid).split('@')[0].split(':')[0] : '';
        const name = c.name || c.notify || c.verifiedName || '';
        if (phone && name) {
          this._contacts.set(phone, name);
          this._contacts.set(c.id, name);
        }
        if (lidJid && phone) this._recordLidPhone(lidJid, phone);
      }
    };

    this.sock.ev.on('contacts.upsert', registerContacts);
    this.sock.ev.on('contacts.update', registerContacts);
    this.sock.ev.on('chats.phoneNumberShare', ({ lid, jid }) => {
      if (lid && jid) this._recordLidPhone(lid, jid);
    });
    // Baileys 7 reports every LID <-> phone pair it learns (and moves the encryption
    // session across). Keeping the same map here means replies from a LID land on
    // the right lead, and our replies go to the right number.
    this.sock.ev.on('lid-mapping.update', update => {
      for (const m of (Array.isArray(update) ? update : [update])) {
        if (m && m.lid && m.pn) this._recordLidPhone(m.lid, m.pn);
      }
    });

    this.sock.ev.on('presence.update', ({ id, presences }) => {
      if (!id || !presences) return;
      const primaryPhone = this._resolvePhoneFromJid(id);
      for (const [participant, p] of Object.entries(presences)) {
        if (!p) continue;
        const participantPhone = participant ? this._resolvePhoneFromJid(participant) : '';
        const targetPhone = participantPhone || primaryPhone;
        const lkp = p.lastKnownPresence;
        let state = 'unknown';
        let label = 'Active on WhatsApp';
        if (lkp === 'composing') {
          state = 'typing';
          label = 'typing...';
        } else if (lkp === 'recording') {
          state = 'recording';
          label = 'recording audio...';
        } else if (lkp === 'available') {
          state = 'online';
          label = 'Active · WhatsApp Online';
        } else if (lkp === 'unavailable') {
          state = 'offline';
          label = p.lastSeen ? `last seen ${new Date(p.lastSeen * 1000).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}` : 'offline';
        } else if (lkp === 'paused') {
          state = 'online';
          label = 'Active · WhatsApp Online';
        }

        const entry = {
          state,
          label,
          lastSeen: p.lastSeen || null,
          timestamp: Date.now()
        };

        if (targetPhone) {
          this._presences.set(targetPhone, entry);
        }
        const cleanId = String(id).split('@')[0].split(':')[0].replace(/\D/g, '');
        if (cleanId) {
          this._presences.set(cleanId, entry);
        }
        if (participant) {
          const cleanPart = String(participant).split('@')[0].split(':')[0].replace(/\D/g, '');
          if (cleanPart) this._presences.set(cleanPart, entry);
        }
      }
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
        const alt = msg.key?.remoteJidAlt || (!fromMe ? msg.key?.senderPn : '') || '';
        if (alt && /@s\.whatsapp\.net$/.test(alt)) {
          const altPhone = alt.split('@')[0].split(':')[0];
          this._recordLidPhone(lidClean, altPhone);
          jid = `${altPhone}@s.whatsapp.net`;
        } else if (msg._resolvedPn) {
          jid = `${msg._resolvedPn}@s.whatsapp.net`;
        } else if (lidClean === myLid && myNum) {
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

      // Use sender WhatsApp pushName for contact display
      const notifyName = msg.pushName || '';

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
            const buffer = await downloadMediaMessage(msg, 'buffer', {}, { logger: this._logger, reuploadRequest: m => this.sock.updateMediaMessage(m) });
            return { data: buffer.toString('base64'), mimetype: media?.mimetype || (content.stickerMessage ? 'image/webp' : 'application/octet-stream'), filename };
          } catch (err) {
            console.error('[Baileys] Media download failed:', err.message);
            return null;
          }
        }
      };
    };

    const lidStorePn = async lidJid => {
      try {
        const pn = await sock.signalRepository?.lidMapping?.getPNForLID?.(lidJid);
        return pn ? String(pn).split('@')[0].split(':')[0] : '';
      } catch (_) { return ''; }
    };
    const emitMessages = async messages => {
      for (const msg of messages || []) {
        const remote = msg?.key?.remoteJid || '';
        if (remote.endsWith('@lid') && !msg.key.remoteJidAlt && !msg.key.senderPn) {
          const lidClean = remote.split('@')[0].split(':')[0];
          if (!this._lidToPhone.has(lidClean)) {
            const pn = await lidStorePn(remote);
            if (pn) { this._recordLidPhone(lidClean, pn); msg._resolvedPn = pn; }
          }
        }
        const adaptedMsg = adaptMessage(msg);
        if (process.env.WHATSAPP_BRIDGE_DEBUG) {
          console.log(`[Baileys:debug] in id=${msg?.key?.id} fromMe=${Boolean(msg?.key?.fromMe)} remote=${remote} alt=${msg?.key?.remoteJidAlt || ''} -> ${adaptedMsg ? adaptedMsg.id.remote : 'skipped (no text/media)'}`);
        }
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
      if (this.sock !== sock) return;
      for (const msg of messages || []) {
        if (msg.key?.id && msg.message) this._rememberRaw(msg.key.id, msg.message);
      }
      emitMessages(messages).catch(err => console.error('[Baileys] Message handling failed:', err.message));
    });

    // Accept useful contact mappings, but never backfill old WhatsApp chats.
    this.sock.ev.on('messaging-history.set', ({ chats, contacts, messages }) => {
      console.log(`[Baileys] Ignoring historical chat import (${chats?.length || 0} chats, ${messages?.length || 0} messages); live Chatly conversations only.`);
      registerContacts(contacts);
    });

    // Handle delivery / read receipts
    this.sock.ev.on('messages.update', updates => {
      for (const update of updates || []) {
        const status = update.update?.status;
        if (typeof status === 'number' && update.key?.id) {
          // Baileys status values: ERROR=0, PENDING=1, SERVER_ACK=2, DELIVERY_ACK=3, READ=4, PLAYED=5.
          if (status === 0) {
            const reason = (update.update.messageStubParameters || []).join(' ');
            console.warn(`[Baileys] WhatsApp refused message ${update.key.id}${reason ? ': ' + reason : ''}`);
          }
          const ack = status === 0 ? -1 : (status >= 4 ? 3 : (status === 3 ? 2 : (status === 2 ? 1 : 0)));
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

  }

  _normalizeJid(jidOrPhone) {
    let raw = String(jidOrPhone || '').trim();
    if (raw.endsWith('@g.us') || raw.endsWith('@broadcast') || raw.endsWith('@newsletter')) {
      return raw;
    }
    // If a LID is passed, map it to its registered phone number if known
    if (raw.endsWith('@lid')) {
      const lidClean = raw.split('@')[0].split(':')[0].replace(/\D/g, '');
      if (this._lidToPhone.has(lidClean)) {
        return `${this._lidToPhone.get(lidClean)}@s.whatsapp.net`;
      }
      // Unknown LID: send to the LID itself. Turning its digits into a phone
      // number reached nobody and left the message on a single tick forever.
      return `${lidClean}@lid`;
    }
    {
      // A bare LID stored as if it were a phone number (older inbox rows).
      const digits = raw.split('@')[0].split(':')[0].replace(/\D/g, '');
      if (digits.length >= 14 && this._lidToPhone.has(digits)) return `${this._lidToPhone.get(digits)}@s.whatsapp.net`;
    }
    if (raw.endsWith('@s.whatsapp.net') || raw.endsWith('@c.us')) {
      raw = raw.split('@')[0];
    }
    raw = raw.replace(/\D/g, '');
    if (raw.startsWith('0092')) raw = raw.slice(2);
    else if (raw.startsWith('03') && raw.length === 11) raw = '92' + raw.slice(1);
    else if (raw.startsWith('3') && raw.length === 10) raw = '92' + raw;

    // CRITICAL: All 1-on-1 outgoing messages MUST be sent to @s.whatsapp.net!
    // Never send 1-on-1 messages to @lid, as it causes "Waiting for this message"
    // on the recipient's phone and prevents the message from syncing to the sender's own phone.
    return `${raw}@s.whatsapp.net`;
  }

  async getNumberId(phone) {
    if (!this.sock) return null;
    const cleanPhone = phone.replace(/\D/g, '');
    let norm = cleanPhone;
    if (norm.startsWith('0092')) norm = norm.slice(2);
    else if (norm.startsWith('03') && norm.length === 11) norm = '92' + norm.slice(1);
    else if (norm.startsWith('3') && norm.length === 10) norm = '92' + norm;

    // A LID that was stored as if it were a phone number: address the LID's real number.
    if (norm.length >= 14 && this._lidToPhone.has(norm)) return { _serialized: `${this._lidToPhone.get(norm)}@s.whatsapp.net` };
    if (!/^\d{8,15}$/.test(norm)) return null;
    // A number we already chat with (known LID pair) needs no lookup.
    if (this._phoneToLid.has(norm)) return { _serialized: `${norm}@s.whatsapp.net` };
    const cached = this._numberCache.get(norm);
    if (cached && Date.now() - cached.at < NUMBER_CACHE_MS) return cached.exists ? { _serialized: cached.jid } : null;
    try {
      const [result] = await this.sock.onWhatsApp(norm);
      if (result && result.exists) {
        if (result.lid) {
          this._recordLidPhone(result.lid, result.jid || norm);
        }
        const targetJid = (result.jid && !result.jid.endsWith('@lid')) ? result.jid : `${norm}@s.whatsapp.net`;
        this._numberCache.set(norm, { exists: true, jid: targetJid, at: Date.now() });
        if (this._numberCache.size > 20000) this._numberCache.delete(this._numberCache.keys().next().value);
        return { _serialized: targetJid };
      }
      if (result && result.exists === false) {
        this._numberCache.set(norm, { exists: false, at: Date.now() });
        return null;
      }
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
    if (this.sock?.signalRepository?.lidMapping?.getPNForLID) {
      try {
        const pn = await this.sock.signalRepository.lidMapping.getPNForLID(`${lidClean}@lid`);
        if (pn) {
          const phone = String(pn).split('@')[0].split(':')[0];
          this._recordLidPhone(lidClean, phone);
          return phone;
        }
      } catch (_) {}
    }
    // A Chatly message id that embeds this LID tells us which lead it is (local file only).
    try {
      const candidatePaths = [
        path.resolve(__dirname, '../data/tenants/COMP-001/leads.json'),
        path.resolve(__dirname, '../data/leads.json')
      ];
      for (const leadsPath of candidatePaths) {
        if (!fs.existsSync(leadsPath)) continue;
        const leads = JSON.parse(fs.readFileSync(leadsPath, 'utf8'));
        for (const lead of Array.isArray(leads) ? leads : []) {
          const phone = lead.phone ? String(lead.phone).replace(/\D/g, '') : '';
          if (!phone || !Array.isArray(lead.messages)) continue;
          if (lead.messages.some(m => String(m.waMessageId || m.id || '').includes(lidClean + '@lid'))) {
            this._recordLidPhone(lidClean, phone);
            return phone;
          }
        }
        break;
      }
    } catch (_) {}
    return null;
  }

  resetContactSession() {
    // Keep the Signal ratchet intact; Baileys handles retry handshakes natively.
    return false;
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
    const cleanPhone = cleanJid.split('@')[0].split(':')[0].replace(/\D/g, '');
    return {
      id: { _serialized: cleanJid },
      fetchMessages: async ({ limit = 50 } = {}) => {
        const msgs = [];
        const seenIds = new Set();
        for (const msg of this._messageCache.values()) {
          const remote = (typeof msg.id?.remote === 'string' ? msg.id.remote : msg.id?.remote?._serialized) || '';
          const remotePhone = remote.split('@')[0].split(':')[0].replace(/\D/g, '');
          if (remote === cleanJid || (cleanPhone && remotePhone === cleanPhone)) {
            const mId = msg.id?._serialized || msg.id;
            if (mId && !seenIds.has(mId)) {
              seenIds.add(mId);
              msgs.push(msg);
            }
          }
        }
        // Also load from inbox.json so all ingested phone & campaign messages are included
        try {
          const inboxCandidates = [
            path.resolve(this.authPath, '../inbox.json'),
            path.resolve(__dirname, '../data/whatsapp-bridge/inbox.json')
          ];
          for (const ip of inboxCandidates) {
            if (fs.existsSync(ip)) {
              const raw = fs.readFileSync(ip, 'utf8');
              const items = JSON.parse(raw);
              if (Array.isArray(items)) {
                for (const item of items) {
                  const itemClean = String(item.phone || '').replace(/\D/g, '');
                  if (itemClean && cleanPhone && itemClean === cleanPhone) {
                    const waId = item.waMessageId || item.id;
                    if (waId && !seenIds.has(waId)) {
                      seenIds.add(waId);
                      msgs.push({
                        id: { _serialized: waId, fromMe: item.sender === 'agent', remote: cleanJid },
                        fromMe: item.sender === 'agent',
                        from: item.sender === 'agent' ? `${this.info?.wid?.user || ''}@s.whatsapp.net` : cleanJid,
                        to: item.sender === 'agent' ? cleanJid : `${this.info?.wid?.user || ''}@s.whatsapp.net`,
                        body: item.text || '',
                        timestamp: item.timestamp || Math.floor(Date.now() / 1000),
                        hasMedia: Boolean(item.hasMedia),
                        type: item.type || 'chat',
                        ack: item.deliveryStatus === 'read' ? 3 : (item.deliveryStatus === 'delivered' ? 2 : 1)
                      });
                    }
                  }
                }
              }
              break;
            }
          }
        } catch (_) {}
        msgs.sort((a, b) => (a.timestamp || 0) - (b.timestamp || 0));
        return msgs.slice(-limit);
      }
    };
  }

  async getChats() {
    const chatMap = new Map();
    for (const msg of this._messageCache.values()) {
      const remote = (typeof msg.id?.remote === 'string' ? msg.id.remote : msg.id?.remote?._serialized) || (msg.fromMe ? msg.to : msg.from);
      if (remote && !chatMap.has(remote)) {
        chatMap.set(remote, {
          id: { _serialized: remote },
          isGroup: remote.endsWith('@g.us'),
          timestamp: msg.timestamp || 0,
          fetchMessages: async ({ limit = 30 } = {}) => {
            const msgs = [];
            for (const m of this._messageCache.values()) {
              const r = (typeof m.id?.remote === 'string' ? m.id.remote : m.id?.remote?._serialized) || '';
              if (r === remote) msgs.push(m);
            }
            return msgs.slice(-limit);
          }
        });
      }
    }
    return [...chatMap.values()];
  }

  // Contacts whose WhatsApp moved to LID addressing must be messaged at their LID, the
  // way WhatsApp Web does. A message addressed to the phone number was encrypted for
  // the old phone-number sessions; the contact's phone could not open it, showed
  // "Waiting for this message" and had to ask for a resend. Only Baileys' own LID
  // store is used here (it comes from WhatsApp's servers), never a guessed pair.
  async _preferLidAddress(jid) {
    if (!jid.endsWith('@s.whatsapp.net') || !this.sock?.signalRepository?.lidMapping?.getLIDForPN) return jid;
    try {
      const lid = await this.sock.signalRepository.lidMapping.getLIDForPN(jid);
      const user = lid ? String(lid).split('@')[0].split(':')[0] : '';
      if (user && String(lid).includes('@lid')) {
        this._recordLidPhone(user, jid);
        return `${user}@lid`;
      }
    } catch (_) {}
    return jid;
  }

  async sendMessage(toJid, content, options = {}) {
    if (!this.sock) throw new Error('WhatsApp connection is not initialized.');
    const jid = await this._preferLidAddress(this._normalizeJid(toJid));
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
        const validPtt = isOgg(buffer);
        sent = await this.sock.sendMessage(jid, {
          audio: buffer,
          mimetype: validPtt ? 'audio/ogg; codecs=opus' : (content.mimetype || 'audio/mp4'),
          ptt: validPtt
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
    if (sent?.key?.id && sent?.message) this._rememberRaw(sent.key.id, sent.message);
    if (sent?.key?.id) {
      this._sentTo.set(sent.key.id, sent.key.remoteJid || jid);
      if (this._sentTo.size > 5000) this._sentTo.delete(this._sentTo.keys().next().value);
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
            const buffer = await downloadMediaMessage(fakeMsg, 'buffer', {}, { logger: this._logger, reuploadRequest: m => this.sock.updateMediaMessage(m) });
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

  async subscribePresence(phone) {
    if (!this.sock || !phone) return;
    try {
      const clean = String(phone).replace(/\D/g, '');
      let norm = clean;
      if (norm.startsWith('0092')) norm = norm.slice(2);
      else if (norm.startsWith('03') && norm.length === 11) norm = '92' + norm.slice(1);
      else if (norm.startsWith('3') && norm.length === 10) norm = '92' + norm;

      let jid = '';
      if (this._phoneToLid.has(norm)) {
        jid = `${this._phoneToLid.get(norm)}@lid`;
      } else {
        jid = `${norm}@s.whatsapp.net`;
      }
      if (typeof this.sock.presenceSubscribe === 'function') {
        await this.sock.presenceSubscribe(jid).catch(() => {});
      }
    } catch (_) {}
  }

  getPresence(phone) {
    if (!phone) return { success: true, state: 'unknown', label: 'Online / last seen unavailable' };
    const clean = String(phone).replace(/\D/g, '');
    let norm = clean;
    if (norm.startsWith('0092')) norm = norm.slice(2);
    else if (norm.startsWith('03') && norm.length === 11) norm = '92' + norm.slice(1);
    else if (norm.startsWith('3') && norm.length === 10) norm = '92' + norm;

    let p = this._presences.get(norm) || this._presences.get(clean);
    if (!p && this._phoneToLid.has(norm)) {
      p = this._presences.get(this._phoneToLid.get(norm));
    }

    if (!p) {
      return { success: true, state: 'unknown', label: 'Online / last seen unavailable' };
    }

    // Auto-expire typing and recording states after 12s of inactivity
    if ((p.state === 'typing' || p.state === 'recording') && (Date.now() - p.timestamp > 12000)) {
      p = { ...p, state: 'online', label: 'Active · WhatsApp Online' };
      this._presences.set(norm, p);
    }

    return {
      success: true,
      state: p.state,
      label: p.label,
      lastSeen: p.lastSeen,
      timestamp: p.timestamp
    };
  }

  async sendPresence(phone, type = 'composing') {
    if (!this.sock) return;
    const clean = String(phone).replace(/\D/g, '');
    let norm = clean;
    if (norm.startsWith('0092')) norm = norm.slice(2);
    else if (norm.startsWith('03') && norm.length === 11) norm = '92' + norm.slice(1);
    else if (norm.startsWith('3') && norm.length === 10) norm = '92' + norm;

    let targetJid = `${norm}@s.whatsapp.net`;
    try {
      const idObj = await this.getNumberId(norm).catch(() => null);
      if (idObj?._serialized) targetJid = idObj._serialized;
    } catch (_) {}

    const validTypes = ['composing', 'recording', 'paused', 'available', 'unavailable'];
    const pType = validTypes.includes(type) ? type : 'composing';

    try {
      if (typeof this.sock.sendPresenceUpdate === 'function') {
        await this.sock.sendPresenceUpdate(pType, targetJid);
        console.log(`[Baileys] Outbound presence '${pType}' sent to ${targetJid}`);
      }
    } catch (err) {
      console.warn(`[Baileys] Outbound sendPresence failed: ${err.message}`);
    }
  }

  getRecentPresences() {
    const now = Date.now();
    const result = {};
    for (const [key, p] of this._presences.entries()) {
      if (!p) continue;
      if (p.state === 'typing' || p.state === 'recording') {
        if (now - p.timestamp <= 12000) {
          result[key] = { state: p.state, label: p.label, timestamp: p.timestamp };
        }
      } else if (now - p.timestamp <= 45000) {
        result[key] = { state: p.state, label: p.label, timestamp: p.timestamp };
      }
    }
    return result;
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
    this._presences.clear();
    try {
      if (fs.existsSync(this.rawStorePath)) fs.unlinkSync(this.rawStorePath);
      if (fs.existsSync(this.authPath)) {
        fs.rmSync(this.authPath, { recursive: true, force: true });
      }
      const parentBak = path.join(this.authPath, '../creds.json.bak');
      if (fs.existsSync(parentBak)) try { fs.unlinkSync(parentBak); } catch (_) {}
      const altBak = this.authPath + '.bak';
      if (fs.existsSync(altBak)) try { fs.rmSync(altBak, { recursive: true, force: true }); } catch (_) {}
    } catch (_) {}
    this._destroyed = false;
  }

  async destroy() {
    this._destroyed = true;
    if (this._reconnectTimer) {
      clearTimeout(this._reconnectTimer);
      this._reconnectTimer = null;
    }
    if (this.sock) {
      try { this.sock.ev.removeAllListeners(); } catch (_) {}
      try { this.sock.end(undefined); } catch (_) {}
      this.sock = null;
    }
    await this.flush();
  }

  // Wait for queued key/creds writes and the message store to reach the disk.
  async flush() {
    try { if (this._auth && this._auth.flush) await Promise.race([this._auth.flush(), new Promise(r => setTimeout(r, 3000))]); } catch (_) {}
    if (this._saveRawTimer) {
      clearTimeout(this._saveRawTimer);
      this._saveRawTimer = null;
      this._writeRawStoreNow();
    }
  }
}

module.exports = { BaileysClient };
