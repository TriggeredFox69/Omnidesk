# Nunno WhatsApp Outreach Hub & Campaign Studio

An enterprise-grade, high-conversion WhatsApp campaign and outreach studio designed specifically for **Nunno** (`nunno.cloud`) — the AI-driven crypto & liquidation intelligence platform. Built with Vanilla JS, PHP, and responsive dark-cyber CSS matching Nunno's design system.

---

## ⚡ Why Traditional Automated Spam Bots Get Banned

Sending automated unsolicited bulk messages to scraped databases on personal WhatsApp accounts triggers Meta's automated anti-abuse systems:
1. **Velocity & Fingerprint Detection**: Firing hundreds of messages programmatically from a single web session or emulator is instantly flagged as bot activity.
2. **High Block & Report Rates**: When unknown recipients receive unsolicited messages, recipient reports immediately result in automated permanent phone number blacklisting.
3. **Telecommunications Regulations**: Telecommunications authorities enforce strict anti-spam regulations on commercial electronic messages.

### Automatic Campaign Sending
Individual inbox messages use the official WhatsApp Business Cloud API when it is configured. Without Cloud API credentials, the app opens a prefilled chat in the user's WhatsApp session; the operator must press **Send** in WhatsApp. The campaign studio uses the Cloud API for automatic dispatch and falls back to an explicit per-contact WhatsApp link when credentials are absent. Configure these environment variables on the PHP server (keep the access token private; do not put it in browser code or commit it):

- `WHATSAPP_PHONE_NUMBER_ID`: Meta's Cloud API phone number ID.
- `WHATSAPP_ACCESS_TOKEN`: a server-side access token with WhatsApp messaging permissions.
- `WHATSAPP_GRAPH_API_VERSION`: a currently supported Graph API version, for example `vNN.N` (keep it current with Meta's supported versions).
- `WHATSAPP_DISPLAY_PHONE_NUMBER`: optional sender number shown in logs.
- `WHATSAPP_TEMPLATE_NAME` and `WHATSAPP_TEMPLATE_LANGUAGE`: optional approved template for outbound campaigns. When configured, the personalized campaign message is passed as the template's first body parameter; use a matching approved template. Without a template, the sender uses a text message, which WhatsApp only permits during an active customer service window.
- `WHATSAPP_WEBHOOK_VERIFY_TOKEN` and `WHATSAPP_APP_SECRET`: required to verify delivery callbacks. Set the Meta webhook callback URL to `https://your-domain/api/whatsapp_webhook.php` and subscribe the WhatsApp product to the `messages` webhook field.

If credentials are missing, automatic campaign dispatch is unavailable; the app provides a WhatsApp link for manual sending. If WhatsApp rejects a Cloud API message, the campaign stops at that contact and reports the API error. API acceptance is shown as pending; the campaign updates to delivered, read, or failed only after a signed status webhook arrives. Without webhook configuration, the app cannot confirm delivery.

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
For campaign sending, run the PHP app. Opening `index.html` directly or using a static preview does not execute the PHP sender API:
```bash
php -S localhost:8080 -t .
```
Navigate to `http://localhost:8080/index.php` and configure the WhatsApp environment variables in PHP before sending.

### Option 2: Upload to cPanel
1. Upload the `nunno_whatsapp` folder to your server (e.g. `public_html/whatsapp` or as a subdomain `whatsapp.nunno.cloud`).
2. Sibling linking is pre-configured to `nunno_sales` and `nunno_marketing`.
3. Configure the WhatsApp environment variables listed above in the server's PHP environment. Do not put access tokens in `api/config.php` or browser code.
