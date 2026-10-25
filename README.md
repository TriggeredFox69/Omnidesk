# Nunno WhatsApp Outreach Hub & Campaign Studio

An enterprise-grade, high-conversion WhatsApp campaign and outreach studio designed specifically for **Nunno** (`nunno.cloud`) — the AI-driven crypto & liquidation intelligence platform. Built with Vanilla JS, PHP, and responsive dark-cyber CSS matching Nunno's design system.

---

## ⚡ Why Traditional Automated Spam Bots Get Banned

Sending automated unsolicited bulk messages to scraped databases on personal WhatsApp accounts triggers Meta's automated anti-abuse systems:
1. **Velocity & Fingerprint Detection**: Firing hundreds of messages programmatically from a single web session or emulator is instantly flagged as bot activity.
2. **High Block & Report Rates**: When unknown recipients receive unsolicited messages, recipient reports immediately result in automated permanent phone number blacklisting.
3. **Telecommunications Regulations**: Telecommunications authorities enforce strict anti-spam regulations on commercial electronic messages.

### Automatic Campaign Sending (QR linking)
The default sender is your regular WhatsApp account, linked by QR code. The PHP app talks to a private Node service in `bridge/`; the service keeps a WhatsApp Web session open and saves it across restarts. No Business API credentials are needed in this mode.

1. Install Node.js 22+ and enable PHP cURL. Run the bridge as the same OS user as PHP, so PHP can read its private token.
2. In `bridge/`, run `npm ci`, then `npm start`. The first install downloads Chromium. Linux hosts also need Chromium's system libraries; see the [upstream installation guide](https://wwebjs.dev/guide/installation). Run the browser as an unprivileged user.
3. Open the PHP app, sign in as an administrator, and click the sender connection pill. Click **Connect WhatsApp**, then scan with **WhatsApp → Linked devices → Link a device** on your phone.
4. Load recipients and click **Start Campaign**. Messages send automatically at the selected interval. If you start before linking, the app opens the QR flow and continues automatically after linking. Cancel cancels that pending launch.
5. Keep the campaign page open while it sends. Pause/Stop prevent subsequent sends; an already submitted message may still finish. Delivery is shown only after WhatsApp acknowledgement, separately from submission.

Use a persistent service manager to keep `npm start` running after SSH closes and after reboot. PHP-only shared hosting cannot run the QR sender: the host must allow a persistent Node process and Chromium, or the bridge must run on a separate server. Deploying the PHP files alone is insufficient.

The bridge binds to `127.0.0.1:3210`. It generates a private token and stores linked-device data under `data/whatsapp-bridge/` (ignored by Git and denied by Apache). Do not expose this directory or the bridge port publicly. Other web servers need equivalent rules denying `/data/` and `/bridge/`.

Optional server settings:
- `WHATSAPP_BRIDGE_URL` (PHP): defaults to `http://127.0.0.1:3210`.
- `WHATSAPP_BRIDGE_TOKEN` (both processes): same random secret, at least 32 characters. Required when PHP and Node do not share the private token file. For separate servers, use HTTPS and restrict network access to the PHP host.
- `WHATSAPP_BRIDGE_HOST` / `WHATSAPP_BRIDGE_PORT` (Node): default `127.0.0.1` / `3210`.
- `WHATSAPP_BROWSER_PATH` (Node): optional path to an installed Chrome/Chromium executable.

To unlink, remove **Nunno Campaigns** from Linked devices on your phone. Use Connect again to link a replacement session. This integration uses [whatsapp-web.js](https://docs.wwebjs.dev/), an unofficial WhatsApp Web client; WhatsApp changes can require dependency updates and account restrictions are possible. Send only to recipients who expect your messages.

Requests are serialized and repeated campaign requests are deduplicated while the bridge process remains running (last 2,000 requests). If a send times out, check the WhatsApp chat before retrying, especially after restarting the bridge. Campaign progress is browser-session state, not a durable background job.

### Optional Cloud API mode
Set `WHATSAPP_SEND_MODE=cloud_api` in PHP to use the existing Cloud API sender instead. Configure `WHATSAPP_PHONE_NUMBER_ID`, `WHATSAPP_ACCESS_TOKEN`, and `WHATSAPP_GRAPH_API_VERSION`. Optional settings are `WHATSAPP_DISPLAY_PHONE_NUMBER`, `WHATSAPP_TEMPLATE_NAME`, and `WHATSAPP_TEMPLATE_LANGUAGE`. Cloud delivery receipts require `WHATSAPP_WEBHOOK_VERIFY_TOKEN` and `WHATSAPP_APP_SECRET`, with `/api/whatsapp_webhook.php` as the Meta webhook callback. QR linking is only available in the default mode.

---

## 🚀 Key Features

- **Pakistani Number Sanitizer & Validator**:
  - Automatically sanitizes mixed formats (`0300...`, `+92300...`, `0092...`, dashes, spaces) into standard E.164 (`923001234567`).
  - Identifies telecom operators: **Jazz**, **Telenor**, **Zong**, and **Ufone**.
  - Automatically deduplicates and filters blacklisted numbers.
- **Dynamic Spintax & Merge Tag Engine**:
  - Generates varied opening greetings and phrasing per message to prevent message hash matching.
  - Merge tags: `{name}`, `{city}`, `{plan}`, `{price}`, `{discount}`, `{coupon}`, `{link}`.
- **Pre-loaded Nunno Crypto & Forex Copy**:
  - *Crypto Liquidation Alert* (Roman Urdu for Pakistani traders)
  - *Gold ($XAUUSD) & Forex Scalper Pitch*
  - *Flash 48-Hour Discount (EasyPaisa / JazzCash / Binance Pay)*
  - *Institutional Alpha (English)*
- **Interactive Smartphone Simulator**:
  - Live WhatsApp dark mode bubble (`#005c4b`) with verified badges, formatting (`*bold*`, `_italic_`, `~strike~`, `` `code` ``), and CTA buttons.
- **Audit Logging & CSV Export**:
  - Track dispatched, skipped, converted, and opted-out leads.
  - Export full campaign performance reports in 1 click.
- **Ecosystem Integration**:
  - Direct header links between `Nunno Sales CRM`, `Nunno Marketing Studio`, and `Nunno WhatsApp Hub`.

---

## 📁 File Structure

```
nunno_whatsapp/
├── api/
│   ├── config.php          # Platform settings, plans, templates, Meta API keys
│   ├── meta_api.php        # Official Meta Cloud API connector & webhook listener
│   └── sanitizer.php       # Pakistani phone number validator & network identifier
├── assets/
│   ├── css/
│   │   └── whatsapp.css    # Nunno dark-cyber stylesheet with WhatsApp emerald accents
│   ├── images/
│   │   ├── nunno-logo.png  # Official Nunno logo
│   │   └── nunno-logo.svg
│   └── js/
│       └── whatsapp-app.js # Core client logic (Spintax, queue, simulator, CSV)
├── data/
│   ├── blacklist.json      # Unsubscribed / opted-out phone numbers
│   └── campaign_logs.json  # Audit log of sent campaigns
├── index.php               # Dynamic PHP interface (cPanel ready)
├── index.html              # Static interface preview; PHP sending requires index.php
└── README.md
```

---

## 🛠️ How to Deploy & Use

### Option 1: Local Usage

For offline simulation, double-click `index.html`. For real QR-linked sending without PHP, double-click `start-local-whatsapp.cmd`. See [LOCAL-TEST.md](LOCAL-TEST.md).

For the original PHP-based setup:
Opening `index.html` directly now runs the offline simulator; it sends no real messages. To test the PHP backend instead:
```bash
php -S localhost:8080 -t .
```
Navigate to `http://localhost:8080/index.php`, start the Node bridge, and link WhatsApp using the QR flow above.

### Option 2: Upload to cPanel
The main `index.php` entry point renders the same `index.html` page used by
`http://127.0.0.1:8080/`, with server-side campaign configuration. Edit that shared
HTML file for interface changes in both versions. The local automatic test login
is not enabled on the public site. Real WhatsApp still requires the bridge below;
sharing the interface does not transfer a PC session or make PHP run Node.js.

1. Upload the `nunno_whatsapp` folder to your server (e.g. `public_html/whatsapp` or as a subdomain `whatsapp.nunno.cloud`).
2. Sibling linking is pre-configured to `nunno_sales` and `nunno_marketing`.
3. Install the bridge dependencies and run the Node service persistently on a Chromium-capable host. Follow the QR setup above. Keep the bridge token server-side.
4. The deployment recipe preserves runtime data and excludes Node dependencies; run `npm ci` in the deployed `bridge/` directory after dependency changes. A mirrored PHP deployment must point to the same bridge and use the same private token to share one WhatsApp account.
