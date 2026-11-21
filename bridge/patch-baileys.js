'use strict';
/**
 * Baileys Signal Protocol Auto-Patcher
 * Fixes the upstream Baileys bug in messages-recv.js where 1-on-1 retry receipts evaluate key.fromMe to false,
 * causing Baileys to log "recv retry for not fromMe message" and abort sendMessagesAgain.
 * This fixes "Waiting for this message. This may take a while" and single grey ticks permanently.
 */
const fs = require('node:fs');
const path = require('node:path');

function patchBaileys(baseDir = __dirname) {
  const candidatePaths = [
    path.join(baseDir, 'node_modules/@whiskeysockets/baileys/lib/Socket/messages-recv.js'),
    path.join(baseDir, '../node_modules/@whiskeysockets/baileys/lib/Socket/messages-recv.js'),
    path.join(__dirname, 'node_modules/@whiskeysockets/baileys/lib/Socket/messages-recv.js')
  ];

  let targetPath = null;
  for (const p of candidatePaths) {
    if (fs.existsSync(p)) {
      targetPath = p;
      break;
    }
  }

  if (!targetPath) {
    console.log('[Baileys Patch] messages-recv.js not found in candidate paths. Skipping patch.');
    return false;
  }

  try {
    let code = fs.readFileSync(targetPath, 'utf8');
    const buggyTarget = "const fromMe = !attrs.recipient || ((attrs.type === 'retry' || attrs.type === 'sender') && isNodeFromMe);";
    const patchedFix = "const fromMe = !attrs.recipient || attrs.type === 'retry' || ((attrs.type === 'sender') && isNodeFromMe);";

    if (code.includes(buggyTarget)) {
      code = code.replace(buggyTarget, patchedFix);
      fs.writeFileSync(targetPath, code, 'utf8');
      console.log(`[Baileys Patch] Successfully applied Signal retry bug fix in ${targetPath}`);
      return true;
    } else if (code.includes(patchedFix)) {
      console.log(`[Baileys Patch] messages-recv.js is already patched at ${targetPath}`);
      return true;
    } else {
      console.log('[Baileys Patch] Target pattern not found (library version may differ).');
      return false;
    }
  } catch (err) {
    console.warn('[Baileys Patch] Error patching messages-recv.js:', err.message);
    return false;
  }
}

if (require.main === module) {
  patchBaileys();
}

module.exports = { patchBaileys };
