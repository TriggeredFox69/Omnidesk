#!/bin/bash
# ========================================================
# Chatly Deployment Script for cPanel (chatly.pk)
# ========================================================
set -e

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

    # Data files (preserve existing runtime json if present)
    /bin/cp -f data/.htaccess "$TARGET/data/" 2>/dev/null || true
    /bin/cp -n data/companies.json data/email_config.json "$TARGET/data/" 2>/dev/null || /bin/cp -f data/companies.json data/email_config.json "$TARGET/data/" 2>/dev/null || true

    # Permissions
    /bin/chmod 755 "$TARGET" "$TARGET/cc" "$TARGET/ss" 2>/dev/null || true
    /bin/chmod 775 "$TARGET/data" 2>/dev/null || true
    echo "Successfully deployed to $TARGET"
  fi
done
