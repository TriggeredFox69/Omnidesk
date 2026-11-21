#!/bin/bash
# 24/7 Supervisor daemon for Nunno WhatsApp Bridge on cPanel
export PATH="/opt/alt/alt-nodejs22/root/usr/bin:/usr/local/bin:/usr/bin:/bin:$PATH"
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

SCRIPT="$BASE_DIR/bridge/server.js"

PIDFILE="$WHATSAPP_DATA_DIR/daemon.pid"
LOGFILE="$WHATSAPP_DATA_DIR/bridge.log"

# Only ONE supervisor may run. Extra copies used to start extra bridges that fought
# over the port and over the same WhatsApp login, and cPanel killed them all.
LOCKFILE="$WHATSAPP_DATA_DIR/daemon.lock"
if command -v flock >/dev/null 2>&1; then
    exec 9>"$LOCKFILE"
    if ! flock -n 9; then
        echo "[$(date -u)] Supervisor already running; this copy exits." >> "$LOGFILE"
        exit 0
    fi
elif [ -f "$PIDFILE" ] && kill -0 "$(cat "$PIDFILE" 2>/dev/null)" 2>/dev/null; then
    echo "[$(date -u)] Supervisor already running (pid $(cat "$PIDFILE")); this copy exits." >> "$LOGFILE"
    exit 0
fi

echo $$ > "$PIDFILE"

# Supervisor loop: restart Node if it ever stops. Quick crashes back off (2s -> 60s)
# so a broken start cannot spin and burn the account's CPU/process limits.
DELAY=2
while true; do
    STARTED=$(date +%s)
    "$NODE" "$SCRIPT" >> "$LOGFILE" 2>&1
    EXIT_CODE=$?
    if [ ! -f "$PIDFILE" ]; then
        break
    fi
    if [ $(( $(date +%s) - STARTED )) -gt 120 ]; then DELAY=2; else DELAY=$(( DELAY * 2 )); [ $DELAY -gt 60 ] && DELAY=60; fi
    echo "[$(date -u)] Bridge process exited with code $EXIT_CODE. Restarting in ${DELAY}s..." >> "$LOGFILE"
    sleep $DELAY
done

