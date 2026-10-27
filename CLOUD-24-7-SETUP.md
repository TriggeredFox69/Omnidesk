# 24/7 WhatsApp Cloud Hosting Setup (Run Without Your PC)

Currently, `start-online-whatsapp.cmd` runs the WhatsApp Web engine (Node.js + Chromium) on your local computer, using a Cloudflare tunnel to route messages from `omnidesk.lalagpt.pk` through your phone.

To keep Omnidesk online **24 hours a day, 7 days a week without keeping your PC powered on**, the bridge service needs to run in the cloud instead of your PC.

---

## Why Can't Shared cPanel Run It Directly?
Standard shared cPanel hosting (`lalagpt.pk`) supports PHP, but:
1. It does not include root access to install Google Chrome/Chromium dependencies (`libnss3`, `libasound2`, etc.).
2. Shared hosting kills background processes that exceed RAM or CPU limits.

The solution is to host the lightweight bridge container (which we've already configured with [bridge/Dockerfile](file:///d:/ANTIGRAVITY/nunno_whatsapp/bridge/Dockerfile)) on a 24/7 cloud provider.

---

## Method 1: Render.com or Railway (Recommended — 5 Minutes, Zero Maintenance)

Both Render and Railway can build and run Docker containers directly from your GitHub repository (`TriggeredFox69/Omnidesk`).

### Steps for Render.com:
1. Go to [render.com](https://render.com) and sign in with your GitHub account.
2. Click **New +** > **Web Service**.
3. Select your repository: `TriggeredFox69/Omnidesk`.
4. In the settings:
   - **Root Directory**: `bridge`
   - **Runtime**: `Docker`
   - **Instance Type**: Free or Starter
5. Under **Environment Variables**, add:
   - `WHATSAPP_BRIDGE_TOKEN`: Generate any 64-character random string (or use your existing token).
   - `PUBLIC_BRIDGE_URL`: `https://<your-service-name>.onrender.com`
   - `OMNIDESK_API_URL`: `https://omnidesk.lalagpt.pk/api/direct_sender.php`
6. Click **Create Web Service**.
7. Once deployed, Render gives you a live HTTPS URL. The bridge will automatically register itself with your Omnidesk cPanel dashboard!
8. Open [omnidesk.lalagpt.pk](https://omnidesk.lalagpt.pk), scan the QR code once from your phone, and you are done! Your PC can remain off 24/7.

---

## Method 2: A Cheap $3–$4/mo Linux VPS (DigitalOcean / Hetzner / Vultr)

If you send large campaigns or want maximum reliability with a dedicated IP:

1. Create a minimal Ubuntu 22.04 / 24.04 droplet/VPS on Hetzner ($3.50/mo) or DigitalOcean ($4/mo).
2. SSH into your VPS and run:
   ```bash
   # 1. Install Node.js 22 & Chromium
   curl -fsSL https://deb.nodesource.com/setup_22.x | sudo -E bash -
   sudo apt-get install -y nodejs chromium-browser

   # 2. Clone repository & install PM2 (process manager)
   git clone https://github.com/TriggeredFox69/Omnidesk.git
   cd Omnidesk/bridge
   npm install --omit=dev
   sudo npm install -g pm2

   # 3. Start bridge with PM2 (auto-restarts on reboot)
   export WHATSAPP_BROWSER_PATH=/usr/bin/chromium-browser
   pm2 start server.js --name "whatsapp-bridge"
   pm2 startup
   pm2 save
   ```
3. Point your domain (e.g. `bridge.lalagpt.pk`) or a free Cloudflare tunnel to port `3210`.
4. The bridge will stay running 24/7/365 without ever touching your PC again.

---

## Summary Comparison

| Hosting Method | Requires PC On? | Cost | Setup Time | Reliability |
|---|---|---|---|---|
| **Local PC (`start-online-whatsapp.cmd`)** | Yes | Free | Done (Working now) | Good while PC is on |
| **Render / Railway (Docker)** | **No** | Free / ~$5/mo | ~5 minutes | High |
| **Cloud VPS (DigitalOcean/Hetzner)** | **No** | ~$3.50/mo | ~10 minutes | Maximum (Enterprise) |
