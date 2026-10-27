<?php
header('Content-Type: application/json');

$nodePath = '/opt/alt/alt-nodejs22/root/usr/bin/node';
$bridgeDir = realpath(__DIR__ . '/../bridge');

$modules = [];
foreach (['qrcode', 'pino', '@whiskeysockets/baileys'] as $mod) {
    $modules[$mod] = is_dir($bridgeDir . '/node_modules/' . $mod);
}

echo json_encode([
    'node_modules_dir' => is_dir($bridgeDir . '/node_modules'),
    'modules' => $modules,
    'npm_running' => (bool) @exec('pgrep -f "npm" 2>&1')
], JSON_PRETTY_PRINT);
