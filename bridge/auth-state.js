'use strict';
// WhatsApp login + Signal keys on disk, same file layout as Baileys'
// useMultiFileAuthState (existing linked sessions keep working), but every write
// is atomic: written to a temp file and renamed over the old one.
//
// Why: the bridge is restarted with kill -9 on deploys. A plain writeFile that is
// interrupted leaves a truncated session file -> "No session record" / "Bad MAC"
// -> the contact's phone shows "Waiting for this message". A truncated creds.json
// made Baileys silently start a brand-new identity (the phone looked logged out).
const fs = require('node:fs');
const fsp = require('node:fs/promises');
const path = require('node:path');

function createAtomicAuthState(folder, { proto, initAuthCreds, BufferJSON }) {
  fs.mkdirSync(folder, { recursive: true, mode: 0o700 });
  const fixFileName = file => String(file || '').replace(/\//g, '__').replace(/:/g, '-');
  const chains = new Map();   // file -> promise chain, so writes to one file stay in order
  let seq = 0;

  function serial(file, task) {
    const prev = chains.get(file) || Promise.resolve();
    const next = prev.then(task, task);
    const tail = next.catch(() => {});
    chains.set(file, tail);
    tail.then(() => { if (chains.get(file) === tail) chains.delete(file); });
    return next;
  }

  function writeData(data, file) {
    const target = path.join(folder, fixFileName(file));
    return serial(target, async () => {
      const tmp = `${target}.${process.pid}.${++seq}.tmp`;
      try {
        await fsp.writeFile(tmp, JSON.stringify(data, BufferJSON.replacer), { mode: 0o600 });
        await fsp.rename(tmp, target);
      } catch (err) {
        // The session folder was deleted (login scan discarded, device unlinked) while
        // this socket was still saving keys: nothing to save any more.
        if (err && err.code === 'ENOENT' && !fs.existsSync(folder)) return;
        throw err;
      }
    });
  }

  function readData(file) {
    const target = path.join(folder, fixFileName(file));
    return serial(target, async () => {
      try {
        return JSON.parse(await fsp.readFile(target, 'utf8'), BufferJSON.reviver);
      } catch (_) {
        return null;
      }
    });
  }

  function removeData(file) {
    const target = path.join(folder, fixFileName(file));
    return serial(target, () => fsp.unlink(target).catch(() => {}));
  }

  // Leftover temp files from a killed process are never valid data.
  try {
    for (const f of fs.readdirSync(folder)) if (f.endsWith('.tmp')) fs.rmSync(path.join(folder, f), { force: true });
  } catch (_) {}

  let creds = null;
  try {
    creds = JSON.parse(fs.readFileSync(path.join(folder, 'creds.json'), 'utf8'), BufferJSON.reviver);
  } catch (_) {}
  if (!creds) creds = initAuthCreds();

  return {
    state: {
      creds,
      keys: {
        get: async (type, ids) => {
          const data = {};
          await Promise.all(ids.map(async id => {
            let value = await readData(`${type}-${id}.json`);
            if (type === 'app-state-sync-key' && value) value = proto.Message.AppStateSyncKeyData.fromObject(value);
            data[id] = value;
          }));
          return data;
        },
        set: async data => {
          const tasks = [];
          for (const category in data) {
            for (const id in data[category]) {
              const value = data[category][id];
              const file = `${category}-${id}.json`;
              tasks.push(value ? writeData(value, file) : removeData(file));
            }
          }
          await Promise.all(tasks);
        }
      }
    },
    saveCreds: () => writeData(creds, 'creds.json'),
    // Resolves once every queued write has reached the disk (used on shutdown).
    flush: () => Promise.all([...chains.values()])
  };
}

module.exports = { createAtomicAuthState };
