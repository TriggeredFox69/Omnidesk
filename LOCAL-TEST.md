# Test locally without cPanel

## Completely offline — simulated messages

Double-click **index.html**, expand **Offline demo only**, and choose **Open offline simulation**. No PHP, Node, login, or internet connection is required for that explicit demo. Opening index.html normally shows the live-app launcher so simulated sends cannot be mistaken for real messages.
Open Campaign Studio, import or paste test numbers, and click Start Campaign.
The normal queue, delays, pause/resume, and CRM conversation flow run locally.
The yellow OFFLINE TEST banner and simulated message labels mean **nothing is sent to WhatsApp**.
Reloading resets the in-memory campaign test data. Offline mode only activates for a `file:` URL; it cannot replace the hosted sender.

## Real WhatsApp on this PC — internet required

Double-click **start-local-whatsapp.cmd**. It starts the local app and opens
**http://127.0.0.1:8080**. No PHP or cPanel is involved. Node.js 22+ is required;
the launcher installs the locked bridge dependencies on first run if needed.

Click Connect WhatsApp and scan the QR in WhatsApp → Linked devices → Link a device.
Then load your test recipients and click Start Campaign. These messages are real.
Keep the terminal and campaign page open. Close the terminal to stop the local server.

The local launcher is a single-user test workspace bound only to this computer.
It signs you in as Local Tester and does not read or update the hosted CRM or its employees.
Campaign and CRM assignment test data stay in memory and reset when the launcher closes. Synced message metadata is retained across restarts.
The linked-device session is saved privately under `data/whatsapp-bridge/local/`.
Saved chat and voice-message records load even while the phone is reconnecting.
Voice recordings themselves are downloaded from WhatsApp when played; expired
attachments may need to be resent.
Remove “Nunno Local Test” from Linked devices on your phone to unlink it.

If port 8080 is in use, run in PowerShell:

```powershell
$env:NUNNO_LOCAL_PORT = '8081'
node bridge/local.js
```

The offline simulator never needs the Node bridge. Real WhatsApp sending cannot work without internet.

## Moving from this PC to cPanel

### Temporary PC connection for PHP-only hosting

Install Cloudflare's official `cloudflared` executable, either on PATH or at
`%LOCALAPPDATA%\NunnoTools\cloudflared.exe`. Close the regular local launcher,
then run `start-online-whatsapp.cmd`. It starts the same local app and saved
WhatsApp session, with an authenticated bridge on loopback port 3210. Only that
bridge is tunneled; the local automatic-login page on port 8080 is not exposed.

Deploy the updated `api/whatsapp_bridge.php`, then upload the generated private
`data/whatsapp-bridge/pc-connection/connection.php` to the website's
`data/whatsapp-bridge/connection.php`. Do not commit this file or its token to Git.
The hosted app then reads the PC's saved inbox and uses its linked account.
Keep the PC awake, online, and the launcher running. Closing it disconnects the
hosted sender. Quick Tunnel addresses change after a restart; re-upload the newly
generated connection file each time. This is a temporary workaround without an
uptime guarantee; a named Cloudflare tunnel is needed for a stable address.

Git transfers the app code, not private history or the linked-device session.
The local inbox is `data/whatsapp-bridge/local/inbox.json`; the hosted bridge uses
`data/whatsapp-bridge/inbox.json`. Keep a private backup before any migration.
Do not commit either file, the token, or the authentication directory to Git.

The server needs Node.js 22+, Chromium dependencies, PHP cURL, and a persistent
process running `node bridge/server.js`. Uploading PHP files does not start it.
If the hosting plan does not support this, run the bridge on a suitable server
and configure the PHP bridge URL and matching private token as described in README.
Link the hosted bridge with WhatsApp, then let it sync recent chats. Migrating
saved local history requires a private, deduplicated merge into the hosted inbox
while its bridge is stopped; do not overwrite an existing hosted inbox.

The cPanel deployment recipe copies application files only and leaves `data/`
and installed bridge dependencies in place. The two deployment roots have
separate data directories unless explicitly configured to share a backend.

## Live inbox, attachments, and presence

The local app now syncs the 20 most recent direct chats (up to 30 messages each); opening a conversation refreshes its latest 50 messages. Incoming text, audio, and images arrive through live message events with a two-second UI poll. Message metadata is retained in the private `data/whatsapp-bridge/local/inbox.json` file across restarts. Groups and older full-history import are outside this local inbox.

Use the microphone or audio-file button for real audio sends, and the camera button for PNG/JPEG/WebP/GIF images. Images and audio are limited to 10 MB; expired attachments show an error. The live app no longer creates simulated voice notes.

A clock means submission is still pending, one grey tick means sent but not confirmed delivered, two grey ticks mean delivered, and two blue ticks mean read. Read receipts can be hidden by WhatsApp privacy settings.

Online, typing, recording, and last-seen text come from WhatsApp presence signals for the active chat. If the account hides them or WhatsApp does not supply them, the app says unavailable. Message times are never represented as last seen. No integration can guarantee visibility of privacy-hidden status; updates are polled approximately every two seconds.
