#!/bin/bash
# ========================================================
# Chatly Deployment Script for cPanel (chatly.pk)
# ========================================================
set -e
export PATH="/opt/alt/alt-nodejs22/root/usr/bin:/usr/local/bin:/usr/bin:/bin:$PATH"
export npm_config_ignore_scripts=true

TARGETS=(
  "/home/dailygol/chatly.pk"
  "/home/dailygol/public_html/chatly.pk"
  "/home/dailygol/omnidesk.lalagpt.pk"
  "/home/dailygol/public_html/omnidesk"
)

if [ "$1" == "clean" ] || [ "$1" == "clean_pair" ]; then
  echo "Wiping stale WhatsApp session files, prekeys, and raw messages for 100% clean pairing..."
  for TARGET in "${TARGETS[@]}"; do
    rm -rf "$TARGET/data/whatsapp-bridge/baileys_auth" \
           "$TARGET/data/whatsapp-bridge/baileys_auth.bak" \
           "$TARGET/data/whatsapp-bridge/creds.json.bak" \
           "$TARGET/data/whatsapp-bridge/raw_messages.json" \
           "$TARGET/data/whatsapp-bridge/lid_map.json" \
           "$TARGET/data/whatsapp-bridge/devices" 2>/dev/null || true
  done
fi

for TARGET in "${TARGETS[@]}"; do
  if [ -d "$TARGET" ] || [ -d "$(dirname "$TARGET")" ]; then
    echo "Deploying to $TARGET..."
    /bin/mkdir -p "$TARGET/api" "$TARGET/assets" "$TARGET/bridge" "$TARGET/data" "$TARGET/cc" "$TARGET/ss"

    # Core Directories
    /bin/cp -rf api/* "$TARGET/api/" 2>/dev/null || true
    /bin/cp -rf assets/* "$TARGET/assets/" 2>/dev/null || true
    /bin/cp -rf cc/* "$TARGET/cc/" 2>/dev/null || true
    /bin/cp -rf ss/* "$TARGET/ss/" 2>/dev/null || true

    # Core Root Files
    /bin/cp -f index.php index.html login.php admin.php sadmin.php cc.php ss.php email_hub.php open-omnidesk.html README.md LOCAL-TEST.md "$TARGET/" 2>/dev/null || true
    /bin/cp -f .htaccess "$TARGET/" 2>/dev/null || true

    # Bridge Files
    /bin/cp -R bridge/dist "$TARGET/bridge/" 2>/dev/null || true
    /bin/cp -f bridge/*.js bridge/*.sh bridge/package.json bridge/package-lock.json "$TARGET/bridge/" 2>/dev/null || true
    /bin/chmod +x "$TARGET/bridge/"*.sh 2>/dev/null || true

    # Data files (preserve existing runtime json if present)
    /bin/cp -f data/.htaccess "$TARGET/data/" 2>/dev/null || true
    /bin/cp -n data/companies.json data/email_config.json "$TARGET/data/" 2>/dev/null || /bin/cp -f data/companies.json data/email_config.json "$TARGET/data/" 2>/dev/null || true

    # Permissions
    /bin/chmod 755 "$TARGET" "$TARGET/cc" "$TARGET/ss" 2>/dev/null || true
    /bin/chmod 775 "$TARGET/data" 2>/dev/null || true
    echo "Successfully deployed to $TARGET"
  fi
done

# Ensure bridge node_modules exists and is 100% functional
echo "Verifying WhatsApp Bridge Node environment..."
cd /home/dailygol/chatly.pk/bridge
NODE_BIN="/opt/alt/alt-nodejs22/root/usr/bin/node"
if [ ! -x "$NODE_BIN" ]; then NODE_BIN="$(which node 2>/dev/null || echo node)"; fi
NODE_NPM="/opt/alt/alt-nodejs22/root/usr/bin/npm"
if [ ! -x "$NODE_NPM" ]; then NODE_NPM="$(which npm 2>/dev/null || echo npm)"; fi

if [ -f "bridge.bundle.js" ]; then
    echo "Using bridge.bundle.js (all dependencies built in; no npm install needed)."
elif ! "$NODE_BIN" -e "if (require('./node_modules/@whiskeysockets/baileys/package.json').version !== '7.0.0-rc14') process.exit(1); import('@whiskeysockets/baileys').then(() => { require('qrcode'); require('pino'); }).catch(() => process.exit(1))" >/dev/null 2>&1; then
    echo "Bridge dependencies missing or broken. Installing with --ignore-scripts..."
    "$NODE_NPM" install --omit=dev --no-audit --no-fund --ignore-scripts || echo "npm install failed; the bridge cannot start without bridge.bundle.js."
fi
# Restart WhatsApp bridge daemon with fresh code
echo "Restarting WhatsApp bridge service..."
# Stop the supervisor first (so it does not restart node), then let node save its
# WhatsApp keys on SIGTERM before anything is forced.
pkill -TERM -f "run-daemon.sh" 2>/dev/null || true
pkill -TERM -f "bridge/server.js" 2>/dev/null || true
pkill -TERM -f "bridge/bridge.bundle.js" 2>/dev/null || true
for i in 1 2 3 4 5 6 7 8 9 10; do
    pgrep -f "bridge/(server|bridge\.bundle)\.js" >/dev/null 2>&1 || break
    sleep 0.5
done
pkill -9 -f "run-daemon.sh" 2>/dev/null || true
pkill -9 -f "bridge/server.js" 2>/dev/null || true
pkill -9 -f "bridge/bridge.bundle.js" 2>/dev/null || true
fuser -k 3210/tcp 2>/dev/null || true
sleep 1
if [ "${1:-}" = "--reset-closer-whatsapp" ]; then
    CLOSER_DEVICE_DIR="/home/dailygol/chatly.pk/data/whatsapp-bridge/devices"
    if [ "$CLOSER_DEVICE_DIR" != "/home/dailygol/chatly.pk/data/whatsapp-bridge/devices" ]; then
        echo "Refusing to reset an unexpected WhatsApp device directory."
        exit 1
    fi
    /bin/mkdir -p "$CLOSER_DEVICE_DIR"
    /usr/bin/find "$CLOSER_DEVICE_DIR" -mindepth 1 -maxdepth 1 -exec /bin/rm -rf -- {} +
    /bin/rm -f /home/dailygol/chatly.pk/data/whatsapp-bridge/inbox.json \
        /home/dailygol/chatly.pk/data/whatsapp-bridge/inbox.json.tmp \
        /home/dailygol/chatly.pk/data/whatsapp-bridge/raw_messages.json \
        /home/dailygol/chatly.pk/data/whatsapp-bridge/lid_map.json
    echo "Closer WhatsApp sessions and bridge sync cache cleared. Chatly accounts, leads, campaigns, and CRM records preserved."
fi
rm -f /home/dailygol/chatly.pk/data/whatsapp-bridge/daemon.pid /home/dailygol/chatly.pk/data/whatsapp-bridge/daemon.lock /home/dailygol/chatly.pk/data/whatsapp-bridge/upgrade.lock 2>/dev/null || true
if [ -f "/home/dailygol/chatly.pk/bridge/run-daemon.sh" ]; then
    nohup /bin/bash /home/dailygol/chatly.pk/bridge/run-daemon.sh </dev/null >/dev/null 2>&1 &
    disown 2>/dev/null || true
fi
sleep 2
echo "Deployment complete! Checking bridge status..."
curl -s -m 3 http://127.0.0.1:3210/status || echo "Bridge starting up..."
