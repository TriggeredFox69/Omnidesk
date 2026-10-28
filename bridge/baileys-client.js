'use strict';
/**
 * Baileys WhatsApp Engine for Headless / CloudLinux / cPanel Environments
 * Zero Chromium or browser dependencies required.
 */
const { EventEmitter } = require('node:events');
const path = require('node:path');
const fs = require('node:fs');
const { default: makeWASocket, useMultiFileAuthState, DisconnectReason, downloadMediaMessage } = require('@whiskeysockets/baileys');
const pino = require('pino');
const { ensureOggOpus } = require('./audio');

class BaileysClient extends EventEmitter {
  constructor({ authPath, deviceName = 'Omnidesk Cloud' } = {}) {
    super();
    this.authPath = authPath || path.resolve(__dirname, '../data/whatsapp-bridge/baileys_auth');
    this.deviceName = deviceName;
    this.sock = null;
    this.info = { wid: { user: '' } };
    this._destroyed = false;
    this._messageCache = new Map();
  }

  async initialize() {
    this._destroyed = false;
    fs.mkdirSync(this.authPath, { recursive: true, mode: 0o700 });
    const { state, saveCreds } = await useMultiFileAuthState(this.authPath);

    this.sock = makeWASocket({
      auth: state,
      printQRInTerminal: false,
      logger: pino({ level: 'silent' }),
      browser: [this.deviceName, 'Chrome', '1.0.0'],
      syncFullHistory: true
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
        this.emit('authenticated');
        this.emit('ready');
      }
    });

    // Handle incoming messages
    this.sock.ev.on('messages.upsert', async ({ messages }) => {
      for (const msg of messages || []) {
        if (!msg.message) continue;
        const fromMe = Boolean(msg.key?.fromMe);
        const jid = msg.key?.remoteJid || '';
        const text = msg.message.conversation ||
          msg.message.extendedTextMessage?.text ||
          msg.message.imageMessage?.caption ||
          msg.message.documentMessage?.caption ||
          msg.message.documentMessage?.title ||
          '';

        const isAudio = Boolean(msg.message.audioMessage);
        const isImage = Boolean(msg.message.imageMessage);
        const isDoc = Boolean(msg.message.documentMessage);
        const type = isAudio ? 'audio' : (isImage ? 'image' : (isDoc ? 'document' : 'chat'));

        const adaptedMsg = {
          id: { _serialized: msg.key.id, fromMe, remote: jid },
          from: jid,
          to: fromMe ? jid : `${this.info.wid.user}@s.whatsapp.net`,
          body: text,
          timestamp: Number(msg.messageTimestamp || Math.floor(Date.now() / 1000)),
          hasMedia: Boolean(isAudio || isImage || isDoc),
          type,
          downloadMedia: async () => {
            try {
              const buffer = await downloadMediaMessage(msg, 'buffer', {});
              const mime = msg.message.imageMessage?.mimetype ||
                msg.message.audioMessage?.mimetype ||
                msg.message.documentMessage?.mimetype ||
                'application/octet-stream';
              const filename = msg.message.documentMessage?.fileName ||
                (type === 'image' ? 'image.jpg' : (type === 'audio' ? 'voice.ogg' : 'document.pdf'));
              return { data: buffer.toString('base64'), mimetype: mime, filename };
            } catch (err) {
              console.error('[Baileys] Media download failed:', err.message);
              return null;
            }
          }
        };
        this._messageCache.set(msg.key.id, adaptedMsg);
        if (this._messageCache.size > 500) {
          this._messageCache.delete(this._messageCache.keys().next().value);
        }
        this.emit('message_create', adaptedMsg);
      }
    });

    // Handle full chat history sync from newly linked device
    this.sock.ev.on('messaging-history.set', async ({ chats, contacts, messages }) => {
      console.log(`[Baileys] History sync received: ${chats?.length || 0} chats, ${messages?.length || 0} messages.`);
      for (const msg of messages || []) {
        if (!msg.message) continue;
        const fromMe = Boolean(msg.key?.fromMe);
        const jid = msg.key?.remoteJid || '';
        if (jid.endsWith('@g.us') || jid.endsWith('@broadcast') || jid.endsWith('@newsletter')) continue;
        const text = msg.message.conversation ||
          msg.message.extendedTextMessage?.text ||
          msg.message.imageMessage?.caption ||
          msg.message.documentMessage?.caption ||
          msg.message.documentMessage?.title ||
          '';

        const isAudio = Boolean(msg.message.audioMessage);
        const isImage = Boolean(msg.message.imageMessage);
        const isDoc = Boolean(msg.message.documentMessage);
        const type = isAudio ? 'audio' : (isImage ? 'image' : (isDoc ? 'document' : 'chat'));

        const adaptedMsg = {
          id: { _serialized: msg.key.id, fromMe, remote: jid },
          from: jid,
          to: fromMe ? jid : `${this.info.wid.user}@s.whatsapp.net`,
          body: text,
          timestamp: Number(msg.messageTimestamp || Math.floor(Date.now() / 1000)),
          hasMedia: Boolean(isAudio || isImage || isDoc),
          type,
          downloadMedia: async () => {
            try {
              const buffer = await downloadMediaMessage(msg, 'buffer', {});
              const mime = msg.message.imageMessage?.mimetype ||
                msg.message.audioMessage?.mimetype ||
                msg.message.documentMessage?.mimetype ||
                'application/octet-stream';
              const filename = msg.message.documentMessage?.fileName ||
                (type === 'image' ? 'image.jpg' : (type === 'audio' ? 'voice.ogg' : 'document.pdf'));
              return { data: buffer.toString('base64'), mimetype: mime, filename };
            } catch (err) {
              console.error('[Baileys] Media download failed:', err.message);
              return null;
            }
          }
        };
        this._messageCache.set(msg.key.id, adaptedMsg);
        this.emit('message_create', adaptedMsg);
      }
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
      if (result && result.exists) return { _serialized: result.jid };
      if (result && result.exists === false) return null;
    } catch (_) {}
    return { _serialized: `${norm}@s.whatsapp.net` };
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
    console.log(`[Baileys] Message sent successfully! ID: ${id} to ${jid}`);

    const adaptedOut = {
      id: { _serialized: id, fromMe: true, remote: jid },
      fromMe: true,
      from: `${myNum}@s.whatsapp.net`,
      to: jid,
      body: typeof content === 'string'
        ? content
        : (options.caption || (isVoice ? 'Voice note' : (content.mimetype?.startsWith('image/') ? 'Image' : (content.filename || 'Document')))),
      type: isVoice ? 'audio' : (content.mimetype?.startsWith('image/') ? 'image' : (content.mimetype ? 'document' : 'chat')),
      hasMedia: Boolean(content && content.data),
      timestamp: Math.floor(Date.now() / 1000),
      ack: 1,
      downloadMedia: async () => {
        if (!content || !content.data) return null;
        return { data: content.data, mimetype: content.mimetype || 'application/octet-stream', filename: content.filename || 'file' };
      }
    };
    this._messageCache.set(id, adaptedOut);
    if (this._messageCache.size > 500) {
      this._messageCache.delete(this._messageCache.keys().next().value);
    }

    return adaptedOut;
  }

  async getMessageById(id) {
    return this._messageCache.get(id) || null;
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
