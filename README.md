# OmniDesk

OmniDesk is a WhatsApp-first workspace: link a device, sync your conversations, reply from an inbox, and run campaigns from an explicitly loaded audience.

## Run locally

Requirements: Node.js 22 or newer and an internet connection for the initial dependency install and WhatsApp connection.

1. Double-click `start-local-whatsapp.cmd` (or run `node bridge/local.js`).
2. Open the local page shown by the launcher.
3. Choose **Connect WhatsApp** and scan the QR from WhatsApp → Linked devices.
4. Keep the launcher running while you use the local app.

The local launcher uses the Baileys WebSocket bridge and does not require PHP or Chromium. Linked-device credentials are stored under `data/whatsapp-bridge/local/baileys_auth/`; inbox history is stored locally under `data/whatsapp-bridge/local/inbox.json`. Keep the `data/` folder private and backed up only where appropriate.

## Inbox and sync

- Incoming messages, messages sent from the linked phone, and OmniDesk replies enter the same inbox.
- Message changes and delivery receipts are collected from the connected-device event stream and reflected in the web inbox through a frequent cursor poll.
- Recent history is synced on connection, with a focused backfill when a chat is opened. The local inbox is persisted across restarts.
- Text, media attachments, voice notes, and delivery status are handled by the bridge.

## Hosted deployment

The hosted UI is served by `index.php`; the WhatsApp bridge runs separately using `node bridge/server.js`. Point PHP at a private, authenticated bridge using `WHATSAPP_BRIDGE_URL` and `WHATSAPP_BRIDGE_TOKEN`, or use the private bridge registration flow already provided by the app. Keep the bridge behind a trusted network boundary and keep credentials out of the public web root. PHP-only shared hosting cannot run the Node bridge.

The optional Meta Cloud API mode is separate from linked-device mode and requires Meta webhook and API configuration. A standard Cloud API registration does not provide a personal linked-device session.

## Project map

- `index.html` and `assets/css/omnidesk.css` — OmniDesk interface and product styling.
- `assets/js/whatsapp-app.js` — inbox, contact workflow, and campaign manager.
- `assets/js/live-inbox.js` — message event sync, replies, and media handling.
- `bridge/` — authenticated WhatsApp service and client adapters.
- `api/` — PHP routes for the hosted application and Cloud API webhook.
