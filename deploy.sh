#!/bin/bash
# ========================================================
# Chatly Deployment Script for cPanel (chatly.pk)
# ========================================================
set -e
export PATH="/opt/alt/alt-nodejs22/root/usr/bin:/usr/local/bin:/usr/bin:/bin:$PATH"

TARGETS=(
  "/home/dailygol/chatly.pk"
  "/home/dailygol/public_html/chatly.pk"
  "/home/dailygol/omnidesk.lalagpt.pk"
  "/home/dailygol/public_html/omnidesk"
)

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
    /bin/cp -f bridge/*.js bridge/*.sh bridge/package.json "$TARGET/bridge/" 2>/dev/null || true
    /bin/chmod +x "$TARGET/bridge/"*.sh 2>/dev/null || true
    if [ -d "/home/dailygol/repositories/Omnidesk/bridge/node_modules" ] && [ ! -d "$TARGET/bridge/node_modules" ]; then
        ln -sfn /home/dailygol/repositories/Omnidesk/bridge/node_modules "$TARGET/bridge/node_modules" 2>/dev/null || true
    fi

    # Data files (preserve existing runtime json if present)
    /bin/cp -f data/.htaccess "$TARGET/data/" 2>/dev/null || true
    /bin/cp -n data/companies.json data/email_config.json "$TARGET/data/" 2>/dev/null || /bin/cp -f data/companies.json data/email_config.json "$TARGET/data/" 2>/dev/null || true

    # Permissions
    /bin/chmod 755 "$TARGET" "$TARGET/cc" "$TARGET/ss" 2>/dev/null || true
    /bin/chmod 775 "$TARGET/data" 2>/dev/null || true
    echo "Successfully deployed to $TARGET"
  fi
done

# Ensure node_modules exists
if [ ! -d "/home/dailygol/chatly.pk/bridge/node_modules/@whiskeysockets/baileys" ]; then
    echo "Installing missing bridge node_modules..."
    cd /home/dailygol/chatly.pk/bridge
    if [ -d "/home/dailygol/repositories/Omnidesk/bridge/node_modules/@whiskeysockets/baileys" ]; then
        ln -sfn /home/dailygol/repositories/Omnidesk/bridge/node_modules ./node_modules 2>/dev/null || true
    fi
    if [ ! -d "/home/dailygol/chatly.pk/bridge/node_modules/@whiskeysockets/baileys" ]; then
        NODE_NPM="/opt/alt/alt-nodejs22/root/usr/bin/npm"
        if [ ! -x "$NODE_NPM" ]; then NODE_NPM="$(which npm 2>/dev/null || echo npm)"; fi
        "$NODE_NPM" install --omit=dev --no-audit --no-fund --ignore-scripts 2>&1 || true
    fi
fi

# Restart WhatsApp bridge daemon with fresh code
echo "Restarting WhatsApp bridge service..."
pkill -9 -f "bridge/server.js" 2>/dev/null || true
pkill -9 -f "run-daemon.sh" 2>/dev/null || true
fuser -k 3210/tcp 2>/dev/null || true
sleep 1
rm -f /home/dailygol/chatly.pk/data/whatsapp-bridge/daemon.pid /home/dailygol/chatly.pk/data/whatsapp-bridge/daemon.lock /home/dailygol/chatly.pk/data/whatsapp-bridge/upgrade.lock 2>/dev/null || true
if [ -f "/home/dailygol/chatly.pk/bridge/run-daemon.sh" ]; then
    nohup /bin/bash /home/dailygol/chatly.pk/bridge/run-daemon.sh >/dev/null 2>&1 &
fi
echo "Deployment complete!"
