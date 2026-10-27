<?php
/**
 * Omnidesk WhatsApp Bridge Keep-Alive Ping
 * Pings the cloud bridge to keep Render/Railway 24/7 awake.
 * Can be called via cPanel Cron Jobs every 10 minutes:
 * curl -s https://omnidesk.lalagpt.pk/api/keep_alive.php > /dev/null 2>&1
 */
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');

require_once __DIR__ . '/whatsapp_bridge.php';

$bridgeStatus = whatsappBridgeRequest('/status');

echo json_encode([
    'success' => !empty($bridgeStatus['success']),
    'timestamp' => date('c'),
    'bridge' => $bridgeStatus
], JSON_PRETTY_PRINT);
