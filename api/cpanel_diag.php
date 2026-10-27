<?php
header('Content-Type: application/json');

$nodePath = '/opt/alt/alt-nodejs22/root/usr/bin/node';
$baseDir = realpath(__DIR__ . '/..');
$bridgeDir = $baseDir . '/bridge';

$checks = [];

// Check server.js lines
if (file_exists($bridgeDir . '/server.js')) {
    $lines = file($bridgeDir . '/server.js');
    $checks['server_js_head'] = array_slice($lines, 0, 12);
}

// Check require tests
$testModules = ['qrcode', 'pino', '@whiskeysockets/baileys', './baileys-client'];
foreach ($testModules as $mod) {
    $code = "try { require('$mod'); console.log('OK'); } catch (e) { console.log('ERR: ' + e.message); }";
    $cmd = "cd " . escapeshellarg($bridgeDir) . " && " . escapeshellarg($nodePath) . " -e " . escapeshellarg($code) . " 2>&1";
    $checks['require_' . $mod] = trim(shell_exec($cmd));
}

echo json_encode($checks, JSON_PRETTY_PRINT);
