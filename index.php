<?php
/**
 * Main Omnidesk renders the same page as the local WhatsApp launcher.
 * Edit index.html for interface changes in both versions.
 */
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 60 * 60 * 24 * 30, // 30 days
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

$config = require __DIR__ . '/api/config.php';

// If no authenticated user session exists, route to the Landing Page (WhatsApp Web QR journey)
if (empty($_SESSION['omnidesk_user'])) {
    require __DIR__ . '/login.php';
    exit;
}
$user = $_SESSION['omnidesk_user'];

$jsonFlags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
$templates = json_encode(array_column($config['default_templates'], null, 'id'), $jsonFlags);
$publicConfig = json_encode([
    'connected_whatsapp_number' => $user['phone'] ?? ($config['connected_whatsapp_number'] ?? ''),
    'sender_engine' => $config['sender_engine'] ?? [],
], $jsonFlags);
$userJson = json_encode($user, $jsonFlags);

$runtime = "<script>\n" .
    "window.NUNNO_TEMPLATES = {$templates};\n" .
    "window.NUNNO_CONFIG = {$publicConfig};\n" .
    "window.NUNNO_ACTIVE_USER = {$userJson};\n" .
    "</script>";

header('Content-Type: text/html; charset=UTF-8');
header('Cache-Control: no-cache, no-store, must-revalidate');
echo str_replace('</head>', $runtime . "\n</head>", file_get_contents(__DIR__ . '/index.html'));
