# Nunno WhatsApp Outreach Hub & Campaign Studio

An enterprise-grade, high-conversion WhatsApp campaign and outreach studio designed specifically for **Nunno** (`nunno.cloud`) — the AI-driven crypto & liquidation intelligence platform. Built with Vanilla JS, PHP, and responsive dark-cyber CSS matching Nunno's design system.

---

## ⚡ Why Traditional Automated Spam Bots Get Banned

Sending automated unsolicited bulk messages to scraped databases on personal WhatsApp accounts triggers Meta's automated anti-abuse systems:
1. **Velocity & Fingerprint Detection**: Firing hundreds of messages programmatically from a single web session or emulator is instantly flagged as bot activity.
2. **High Block & Report Rates**: When unknown recipients receive unsolicited messages, recipient reports immediately result in automated permanent phone number blacklisting.
3. **Telecommunications Regulations**: Telecommunications authorities enforce strict anti-spam regulations on commercial electronic messages.

### The Nunno Solution: Direct WhatsApp Sender Engine
- **Direct Number Dispatch**: No Meta Cloud API, no Graph API tokens, and no Meta verification needed.
- **Anti-Ban Cadence Engine**: Dispatches with randomized 4-8s jitter delays to mimic natural human typing and avoid automated bot detection.
- **Connect Your Number**: Set your custom WhatsApp phone number directly in the app settings, allowing your team to outreach seamlessly from your own connected number.

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
├── index.html              # Standalone web interface (runs locally / offline)
└── README.md
```

---

## 🛠️ How to Deploy & Use

### Option 1: Local Usage
Open `index.html` or run a local PHP server:
```bash
php -S localhost:8080 -t d:\ANTIGRAVITY\nunno_whatsapp
```
Navigate to `http://localhost:8080` in your browser.

### Option 2: Upload to cPanel
1. Upload the `nunno_whatsapp` folder to your server (e.g. `public_html/whatsapp` or as a subdomain `whatsapp.nunno.cloud`).
2. Sibling linking is pre-configured to `nunno_sales` and `nunno_marketing`.
3. To configure Meta Cloud API (optional), edit `api/config.php` and insert your Phone Number ID and System User Access Token.
