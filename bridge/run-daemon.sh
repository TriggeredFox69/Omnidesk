#!/bin/bash
# 24/7 Supervisor daemon for Nunno WhatsApp Bridge on cPanel
export PORT=3210
export WHATSAPP_BRIDGE_HOST=127.0.0.1
export WHATSAPP_ENGINE=baileys

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
BASE_DIR="$(cd "$SCRIPT_DIR/.." && pwd)"
export WHATSAPP_DATA_DIR="$BASE_DIR/data/whatsapp-bridge"
mkdir -p "$WHATSAPP_DATA_DIR"

NODE="/opt/alt/alt-nodejs22/root/usr/bin/node"
if [ ! -x "$NODE" ]; then
    NODE="$(which node 2>/dev/null || echo node)"
fi

SCRIPT="$BASE_DIR/bridge/dist/index.js"
if [ ! -f "$SCRIPT" ]; then
    SCRIPT="$BASE_DIR/bridge/server.js"
fi

PIDFILE="$WHATSAPP_DATA_DIR/daemon.pid"
LOGFILE="$WHATSAPP_DATA_DIR/bridge.log"

echo $$ > "$PIDFILE"

# Supervisor loop: if Node ever terminates, restart it automatically within 2 seconds
while true; do
    "$NODE" "$SCRIPT" >> "$LOGFILE" 2>&1
    EXIT_CODE=$?
    if [ ! -f "$PIDFILE" ]; then
        break
    fi
    echo "[$(date -u)] Bridge process exited with code $EXIT_CODE. Auto-restarting in 2s..." >> "$LOGFILE"
    sleep 2
done

