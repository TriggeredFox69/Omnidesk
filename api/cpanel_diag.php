<?php
header('Content-Type: application/json');

$nodePath = '/opt/alt/alt-nodejs22/root/usr/bin/node';
$baseDir = realpath(__DIR__ . '/..');
$bridgeDir = $baseDir . '/bridge';
$distFile = $bridgeDir . '/dist/index.js';
$logFile = $baseDir . '/data/whatsapp-bridge/bridge.log';

$testCmd = "PORT=3210 WHATSAPP_BRIDGE_HOST=127.0.0.1 WHATSAPP_ENGINE=baileys "
    . escapeshellarg($nodePath) . " " . escapeshellarg($distFile) . " 2>&1";

// Run with 3 second limit or read log
echo json_encode([
    'dist_exists' => file_exists($distFile),
    'dist_size' => file_exists($distFile) ? filesize($distFile) : 0,
    'full_bridge_log' => file_exists($logFile) ? file_get_contents($logFile) : null,
    'node_test_run' => shell_exec("timeout 3 " . $testCmd . " || " . $testCmd)
], JSON_PRETTY_PRINT);
