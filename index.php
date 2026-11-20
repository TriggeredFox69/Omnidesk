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

// If no authenticated user session exists, route to the Landing Page
if (empty($_SESSION['omnidesk_user'])) {
    header('Location: /');
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

$content = file_get_contents(__DIR__ . '/index.html');
$content = str_replace('</head>', $runtime . "\n</head>", $content);

// For TM and TL closer accounts, eliminate single-frame portal flash by pre-rendering the employee workspace
$userRole = strtolower($user['role'] ?? 'member');
$hasChat = !empty($_GET['chat']);
$activeTab = $_GET['tab'] ?? '';
if (!in_array($userRole, ['admin', 'superadmin'], true) && !$hasChat && $activeTab !== 'inbox') {
    $content = str_replace('<html lang="en">', '<html lang="en" class="preload-agent-view">', $content);
    $content = str_replace('<main class="inbox-view-container" id="viewInbox">', '<main class="inbox-view-container" id="viewInbox" style="display:none;">', $content);
    $content = str_replace('<main class="employee-workspace" id="viewEmployee" style="display:none;">', '<main class="employee-workspace" id="viewEmployee" style="display:block;">', $content);
}

echo $content;
