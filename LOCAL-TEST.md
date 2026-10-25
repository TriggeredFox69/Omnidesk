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
Remove “Nunno Local Test” from Linked devices on your phone to unlink it.

If port 8080 is in use, run in PowerShell:

```powershell
$env:NUNNO_LOCAL_PORT = '8081'
node bridge/local.js
```

The offline simulator never needs the Node bridge. Real WhatsApp sending cannot work without internet.

## Live inbox, attachments, and presence

The local app now syncs the 20 most recent direct chats (up to 30 messages each); opening a conversation refreshes its latest 50 messages. Incoming text, audio, and images arrive through live message events with a two-second UI poll. Message metadata is retained in the private `data/whatsapp-bridge/local/inbox.json` file across restarts. Groups and older full-history import are outside this local inbox.

Use the microphone or audio-file button for real audio sends, and the camera button for PNG/JPEG/WebP/GIF images. Images and audio are limited to 10 MB; expired attachments show an error. The live app no longer creates simulated voice notes.

A clock means submission is still pending, one grey tick means sent but not confirmed delivered, two grey ticks mean delivered, and two blue ticks mean read. Read receipts can be hidden by WhatsApp privacy settings.

Online, typing, recording, and last-seen text come from WhatsApp presence signals for the active chat. If the account hides them or WhatsApp does not supply them, the app says unavailable. Message times are never represented as last seen. No integration can guarantee visibility of privacy-hidden status; updates are polled approximately every two seconds.
