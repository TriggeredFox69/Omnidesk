'use strict';

// WhatsApp Web can publish the outgoing message before sendMessage returns,
// and sometimes returns undefined despite submission. Observe both signals;
// never send a second time just to obtain a message ID.
async function submitOnce(client, recipient, text, timeoutMs = 30000, sendOptions = {}) {
  const mediaHash = typeof text === 'object' && text.data ? require('node:crypto').createHash('sha256').update(Buffer.from(text.data, 'base64')).digest('base64') : null;
  const matchesContent = message => mediaHash ? message.hasMedia && message._data?.filehash === mediaHash : message.body === text;
  const serialize = value => typeof value === 'string' ? value : value?._serialized;
  const destinations = new Set([recipient]);
  if (client.getContactLidAndPhone) {
    try {
      for (const entry of await client.getContactLidAndPhone([recipient])) {
        if (entry.lid) destinations.add(serialize(entry.lid));
        if (entry.pn) destinations.add(serialize(entry.pn));
      }
    } catch (_) { /* Phone-based IDs still work if alias lookup is unavailable. */ }
  }
  let timer;
  let stopped = false;
  const startedAt = Math.floor(Date.now() / 1000);
  async function outgoingHistory() {
    const messages = new Map();
    let readable = false;
    if (!client.getChatById) return { readable, messages };
    for (const destination of destinations) {
      try {
        const chat = await client.getChatById(destination);
        const recent = await chat.fetchMessages({ limit: 30, fromMe: true });
        readable = true;
        for (const message of recent) {
          if (message.id?._serialized) messages.set(message.id._serialized, message);
        }
      } catch (_) { /* Try the other phone/LID representation. */ }
    }
    return { readable, messages };
  }
  // Snapshot before submitting: an older identical message must never be
  // mistaken for confirmation of this request.
  const before = await outgoingHistory();
  let resolveObserved;
  const observed = new Promise(resolve => { resolveObserved = resolve; });
  const onMessage = message => {
    if (!(message.fromMe || message.id?.fromMe) || !message.id?._serialized || !matchesContent(message)) return;
    if (!destinations.has(serialize(message.to)) && !destinations.has(serialize(message.id.remote))) return;
    resolveObserved(message);
  };
  client.on('message_create', onMessage);
  client.on('message_ack', onMessage);
  try {
    const timeout = new Promise((_, reject) => {
      timer = setTimeout(() => reject(new Error('Message submission is unconfirmed. It may already have been sent; check the chat before retrying.')), timeoutMs);
    });
    const returned = Promise.resolve().then(() => client.sendMessage(recipient, text, { sendSeen: false, waitUntilMsgSent: true, ...sendOptions }))
      .then(message => message?.id?._serialized ? message : observed);
    const recovered = (async () => {
      while (!stopped && before.readable) {
        await new Promise(resolve => setTimeout(resolve, 500));
        if (stopped) break;
        const after = await outgoingHistory();
        const matches = [...after.messages.values()].filter(message =>
          !before.messages.has(message.id._serialized) &&
          (message.fromMe || message.id.fromMe) && matchesContent(message) &&
          Number(message.timestamp) >= startedAt);
        if (matches.length === 1) return matches[0];
      }
      return observed;
    })();
    // A matching outgoing event confirms submission, not recipient delivery.
    return await Promise.race([returned, observed, recovered, timeout]);
  } finally {
    stopped = true;
    clearTimeout(timer);
    client.removeListener('message_create', onMessage);
    client.removeListener('message_ack', onMessage);
  }
}
module.exports = { submitOnce };
