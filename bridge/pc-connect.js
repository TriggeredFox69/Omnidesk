'use strict';
// Expose only the authenticated bridge through Cloudflare, never the local UI.
const fs = require('node:fs');
const path = require('node:path');
const { randomBytes } = require('node:crypto');
const { spawn } = require('node:child_process');
const { startLocal } = require('./local');

async function main() {
  const privateDir = path.resolve(__dirname, '../data/whatsapp-bridge/pc-connection');
  fs.mkdirSync(privateDir, { recursive: true, mode: 0o700 });
  const tokenFile = path.join(privateDir, 'token');
  if (!fs.existsSync(tokenFile)) fs.writeFileSync(tokenFile, randomBytes(32).toString('hex'), { mode: 0o600, flag: 'wx' });
  const token = fs.readFileSync(tokenFile, 'utf8').trim();
  if (!/^[a-f0-9]{64}$/.test(token)) throw new Error('Invalid PC bridge token file. Restore the private token backup.');
  const app = await startLocal({ openBrowser: false, bridgePort: 3210, bridgeToken: token });
  const binary = process.env.CLOUDFLARED_PATH || 'cloudflared';
  const tunnel = spawn(binary, ['tunnel', '--url', 'http://127.0.0.1:3210', '--no-autoupdate'],
    { windowsHide: true, stdio: ['ignore', 'pipe', 'pipe'] });
  let stopping = false, published = false, buffer = '';
  async function stop(code) {
    if (stopping) return;
    stopping = true;
    tunnel.kill();
    await app.close();
    process.exitCode = code;
  }
  tunnel.on('error', error => { console.error('Tunnel could not start:', error.message); stop(1); });
  tunnel.on('exit', code => { if (!stopping) { console.error('PC connection stopped; hosted WhatsApp is offline.'); stop(code || 1); } });
  const capture = chunk => {
    const value = chunk.toString();
    fs.appendFileSync(path.join(privateDir, 'tunnel.log'), value, { mode: 0o600 });
    buffer = (buffer + value).slice(-16000);
    const match = buffer.match(/https:\/\/[a-z0-9-]+\.trycloudflare\.com\b/);
    if (match && !published) {
      published = true;
      const url = match[0];
      const config = `<?php\n// Private PC bridge connection. Do not commit or share this file.\nreturn ['url' => '${url}', 'token' => '${token}'];\n`;
      fs.writeFileSync(path.join(privateDir, 'connection.php'), config, { mode: 0o600 });
      fs.writeFileSync(path.join(privateDir, 'endpoint.json'), JSON.stringify({ url }), { mode: 0o600 });
      console.log('Private connection.php prepared in data/whatsapp-bridge/pc-connection/.');
      console.log('Upload it to the hosted data/whatsapp-bridge/ folder, alongside the updated PHP adapter.');
      console.log('Keep this process running. Restarting the tunnel changes the address; upload the regenerated file after each restart.');
    }
  };
  tunnel.stdout.on('data', capture);
  tunnel.stderr.on('data', capture);
  for (const signal of ['SIGINT', 'SIGTERM']) process.on(signal, () => stop(0));
}
if (require.main === module) main().catch(error => { console.error(error.message); process.exitCode = 1; });
