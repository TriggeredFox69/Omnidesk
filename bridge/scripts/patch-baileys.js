'use strict';
// Patches applied to node_modules/@whiskeysockets/baileys before bridge.bundle.js is
// built (npm run build runs this first). Each patch is idempotent and fails loudly if
// the code it targets has changed, so an upgrade can never silently drop a fix.
//
// 1. Retry resend encrypted twice -> WhatsApp rejects it (error 479, "smax-invalid")
//    -> the contact's phone stays on "Waiting for this message".
//    When a phone cannot decrypt a message it asks for it again (retry receipt).
//    Baileys 7.0.0-rc14 pushes that device into the normal device fan-out AND encrypts
//    it again in the dedicated retry block, so the stanza carries the message twice.
//    Upstream: https://github.com/WhiskeySockets/Baileys/issues/2781 (fix from PR #2839,
//    not merged as of rc14). The fix: the retry device is encrypted once, in the retry
//    block, after making sure a session exists for it.
const fs = require('node:fs');
const path = require('node:path');

//
// 2. A resend request from one of OUR other devices (e.g. a Meta-hosted device, id 99)
//    can arrive without the "recipient" attribute. Baileys then resends with no chat
//    address and crashes ("Cannot destructure property 'user' of jidDecode(...)"), so
//    that device stays on "Waiting for this message". The bridge remembers where each
//    message it sent went; Baileys now asks it (config.resolveRetryDestination).
//
// 3. When a contact's phone cannot decrypt a message it asks for a resend. rc14 resent
//    with the SAME session the phone just failed on, and only rebuilt the session on a
//    second request if none had been rebuilt for that device in the last hour (and it
//    never passed the phone's MAC-error code, so its "recreate immediately" rule could
//    not fire). A broken session therefore stayed broken: every message showed
//    "Waiting for this message". Now any resend request rebuilds the session (fresh
//    key exchange) for that device first; resends per message stay capped by Baileys'
//    retry limit.
const socketDir = path.join(__dirname, '..', 'node_modules', '@whiskeysockets', 'baileys', 'lib', 'Socket');

function applyAll(fileName, mark, patches) {
  const file = path.join(socketDir, fileName);
  let src = fs.readFileSync(file, 'utf8');
  if (src.includes(mark)) {
    console.log(`[patch-baileys] ${fileName}: already applied`);
    return;
  }
  for (const p of patches) {
    const count = src.split(p.from).length - 1;
    if (count !== 1) {
      console.error(`[patch-baileys] Cannot patch ${fileName}: expected 1 match, found ${count}. Baileys changed; review ${file}`);
      process.exit(1);
    }
    src = src.replace(p.from, p.to);
  }
  fs.writeFileSync(file, src);
  console.log(`[patch-baileys] ${fileName}: patched`);
}

const MARK = '/* chatly-patch: retry-resend-once */';
applyAll('messages-send.js', MARK, [
  {
    from: `        if (participant) {
            if (!isGroup && !isStatus) {
                additionalAttributes = { ...additionalAttributes, device_fanout: 'false' };
            }
            const { user, device } = jidDecode(participant.jid);
            devices.push({
                user,
                device,
                jid: participant.jid
            });
        }`,
    to: `        ${MARK}
        // A retry resend is encrypted once, in the isRetryResend block below. Adding the
        // device to the fan-out as well encrypted it twice and WhatsApp rejected it (479).
        if (participant && !isGroup && !isStatus) {
            additionalAttributes = { ...additionalAttributes, device_fanout: 'false' };
        }`
  },
  {
    from: `                    : encodeWAMessage(messageToSend);
                const { type, ciphertext: encryptedContent } = await signalRepository.encryptMessage({
                    data: encodedMessageToSend,
                    jid: participant.jid
                });`,
    to: `                    : encodeWAMessage(messageToSend);
                await assertSessions([participant.jid]);
                const { type, ciphertext: encryptedContent } = await signalRepository.encryptMessage({
                    data: encodedMessageToSend,
                    jid: participant.jid
                });`
  }
]);

applyAll('messages-recv.js', '/* chatly-patch: retry-destination */', [
  {
    from: `        const remoteJid = !isNodeFromMe || isJidGroup(attrs.from) ? attrs.from : attrs.recipient;`,
    to: `        /* chatly-patch: retry-destination */
        let remoteJid = !isNodeFromMe || isJidGroup(attrs.from) ? attrs.from : attrs.recipient;
        if (!remoteJid && attrs.type === 'retry' && isNodeFromMe && typeof config.resolveRetryDestination === 'function') {
            remoteJid = config.resolveRetryDestination(attrs.id) || remoteJid;
        }`
  }
]);

applyAll('messages-recv.js', '/* chatly-patch: fresh-session-on-retry */', [
  {
    from: `        if (enableAutoSessionRecreation && messageRetryManager && retryCount > 1 && !injectedFromBundle) {`,
    to: `        /* chatly-patch: fresh-session-on-retry */
        if (enableAutoSessionRecreation && messageRetryManager && retryCount >= 1 && !injectedFromBundle) {`
  },
  {
    from: `                const result = messageRetryManager.shouldRecreateSession(participant, hasSession.exists);`,
    to: `                const result = { recreate: true, reason: hasSession.exists ? 'contact could not decrypt: fresh session for the resend' : 'no session' };`
  }
]);
