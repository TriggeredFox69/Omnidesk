'use strict';
/**
 * Baileys WhatsApp Engine for Headless / CloudLinux / cPanel Environments
 * Zero Chromium or browser dependencies required.
 */
const { EventEmitter } = require('node:events');
const path = require('node:path');
const fs = require('node:fs');
const { default: makeWASocket, useMultiFileAuthState, DisconnectReason } = require('@whiskeysockets/baileys');
const pino = require('pino');

class BaileysClient extends EventEmitter {
  constructor({ authPath, deviceName = 'Omnidesk Cloud' } = {}) {
    super();
    this.authPath = authPath || path.resolve(__dirname, '../data/whatsapp-bridge/baileys_auth');
    this.deviceName = deviceName;
    this.sock = null;
    this.info = { wid: { user: '' } };
    this._destroyed = false;
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
      syncFullHistory: false
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
        const text = msg.message.conversation || msg.message.extendedTextMessage?.text || '';

        const adaptedMsg = {
          id: { _serialized: msg.key.id, fromMe },
          from: jid,
          to: fromMe ? jid : `${this.info.wid.user}@s.whatsapp.net`,
          body: text,
          timestamp: Number(msg.messageTimestamp || Math.floor(Date.now() / 1000)),
          hasMedia: Boolean(msg.message.imageMessage || msg.message.audioMessage),
          type: msg.message.audioMessage ? 'audio' : (msg.message.imageMessage ? 'image' : 'chat')
        };
        this.emit('message_create', adaptedMsg);
      }
    });

    // Handle delivery / read receipts
    this.sock.ev.on('messages.update', updates => {
      for (const update of updates || []) {
        if (update.update?.status) {
          const status = update.update.status;
          const ack = status >= 4 ? 3 : (status === 3 ? 2 : 1);
          this.emit('message_ack', { id: { _serialized: update.key.id, fromMe: update.key.fromMe } }, ack);
        }
      }
    });
  }

  async getNumberId(phone) {
    if (!this.sock) return null;
    const cleanPhone = phone.replace(/\D/g, '');
    try {
      const [result] = await this.sock.onWhatsApp(cleanPhone);
      if (result?.exists) return { _serialized: result.jid };
    } catch (_) {}
    return { _serialized: `${cleanPhone}@s.whatsapp.net` };
  }

  async sendMessage(toJid, content, options = {}) {
    if (!this.sock) throw new Error('WhatsApp connection is not initialized.');
    const jid = toJid.includes('@') ? toJid : `${toJid}@s.whatsapp.net`;
    let sent;

    if (typeof content === 'string') {
      sent = await this.sock.sendMessage(jid, { text: content });
    } else if (content && content.data && content.mimetype) {
      const buffer = Buffer.from(content.data, 'base64');
      const isVoice = Boolean(options.sendAudioAsVoice || (content.mimetype.startsWith('audio/') && options.voice));
      if (isVoice) {
        sent = await this.sock.sendMessage(jid, {
          audio: buffer,
          mimetype: 'audio/mp4',
          ptt: true
        });
      } else if (content.mimetype.startsWith('image/')) {
        sent = await this.sock.sendMessage(jid, {
          image: buffer,
          caption: options.caption || ''
        });
      } else {
        sent = await this.sock.sendMessage(jid, {
          document: buffer,
          mimetype: content.mimetype,
          fileName: content.filename || 'attachment',
          caption: options.caption || ''
        });
      }
    } else {
      sent = await this.sock.sendMessage(jid, { text: String(content) });
    }

    const id = sent?.key?.id || `BAIL_${Date.now()}`;
    return {
      id: { _serialized: id, fromMe: true },
      ack: 1
    };
  }

  async getMessageById() {
    return null;
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
