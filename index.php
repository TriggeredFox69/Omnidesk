<?php
/**
 * Main Omnidesk renders the same page as the local WhatsApp launcher.
 * Edit index.html for interface changes in both versions.
 * Hosted authentication and WhatsApp requests still use the PHP API.
 */
$config = require __DIR__ . '/api/config.php';
$jsonFlags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
$templates = json_encode(array_column($config['default_templates'], null, 'id'), $jsonFlags);
$publicConfig = json_encode([
    'connected_whatsapp_number' => $config['connected_whatsapp_number'] ?? '+923046588806',
    'sender_engine' => $config['sender_engine'] ?? [],
], $jsonFlags);

// Override local defaults before the shared app initializes. Never inject the
// local launcher's automatic administrator login or private bridge credentials.
$runtime = "<script>window.NUNNO_TEMPLATES = {$templates}; window.NUNNO_CONFIG = {$publicConfig};</script>";
header('Content-Type: text/html; charset=UTF-8');
header('Cache-Control: no-cache');
echo str_replace('</head>', $runtime . "\n</head>", file_get_contents(__DIR__ . '/index.html'));
