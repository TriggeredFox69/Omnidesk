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

const file = path.join(__dirname, '..', 'node_modules', '@whiskeysockets', 'baileys', 'lib', 'Socket', 'messages-send.js');
const MARK = '/* chatly-patch: retry-resend-once */';

let src = fs.readFileSync(file, 'utf8');
if (src.includes(MARK)) {
  console.log('[patch-baileys] retry-resend fix already applied');
  process.exit(0);
}

const patches = [
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
];

for (const p of patches) {
  const count = src.split(p.from).length - 1;
  if (count !== 1) {
    console.error(`[patch-baileys] Cannot apply retry-resend fix: expected 1 match, found ${count}. Baileys changed; review ${file}`);
    process.exit(1);
  }
  src = src.replace(p.from, p.to);
}
fs.writeFileSync(file, src);
console.log('[patch-baileys] retry-resend fix applied');
