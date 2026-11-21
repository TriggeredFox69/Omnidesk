#!/bin/bash
# 24/7 Supervisor daemon for Nunno WhatsApp Bridge on cPanel
export PATH="/opt/alt/alt-nodejs22/root/usr/bin:/usr/local/bin:/usr/bin:/bin:$PATH"
export npm_config_ignore_scripts=true
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

# bridge.bundle.js has Baileys and every dependency built in (no npm install on the
# server). Fall back to server.js + node_modules only if the bundle is missing.
SCRIPT="$BASE_DIR/bridge/bridge.bundle.js"
if [ ! -f "$SCRIPT" ]; then SCRIPT="$BASE_DIR/bridge/server.js"; fi

PIDFILE="$WHATSAPP_DATA_DIR/daemon.pid"
# Temporary diagnostics: put "debug" in data/whatsapp-bridge/log_level and restart.
if [ -s "$WHATSAPP_DATA_DIR/log_level" ]; then
    export WHATSAPP_BRIDGE_LOG_LEVEL="$(head -c 10 "$WHATSAPP_DATA_DIR/log_level" | tr -cd 'a-z')"
fi
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
# Processes started from PHP inherit a 4 GB address-space cap on this host. Baileys 7
# loads a WebAssembly module, and V8 normally reserves ~10 GB of address space per
# WebAssembly memory, so it failed with "Cannot allocate Wasm memory" and WhatsApp
# could not start. This flag makes V8 reserve only what it uses (Node >= 20.15 / 22.2).
NODE_FLAGS=""
if "$NODE" --disable-wasm-trap-handler -e 0 >/dev/null 2>&1; then
    NODE_FLAGS="--disable-wasm-trap-handler"
fi

DELAY=2
while true; do
    # Keep the log small: rotate at 20 MB (one old copy kept).
    if [ -f "$LOGFILE" ] && [ "$(stat -c %s "$LOGFILE" 2>/dev/null || echo 0)" -gt 20971520 ]; then
        mv -f "$LOGFILE" "$LOGFILE.1"
    fi
    STARTED=$(date +%s)
    "$NODE" $NODE_FLAGS "$SCRIPT" >> "$LOGFILE" 2>&1
    EXIT_CODE=$?
    if [ ! -f "$PIDFILE" ]; then
        break
    fi
    if [ $(( $(date +%s) - STARTED )) -gt 120 ]; then DELAY=2; else DELAY=$(( DELAY * 2 )); [ $DELAY -gt 60 ] && DELAY=60; fi
    echo "[$(date -u)] Bridge process exited with code $EXIT_CODE. Restarting in ${DELAY}s..." >> "$LOGFILE"
    sleep $DELAY
done

