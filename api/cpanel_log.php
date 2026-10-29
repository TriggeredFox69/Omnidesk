<?php
header('Content-Type: application/json');

$baseDir = realpath(__DIR__ . '/..');
$logFile = $baseDir . '/data/whatsapp-bridge/bridge.log';
$campaignLog = $baseDir . '/data/campaign_logs.json';

$maxLines = isset($_GET['lines']) ? min(1000, max(10, (int)$_GET['lines'])) : 100;
echo json_encode([
    'bridge_log_recent' => file_exists($logFile) ? array_slice(explode("\n", file_get_contents($logFile)), -$maxLines) : [],
    'campaign_logs_recent' => file_exists($campaignLog) ? array_slice(json_decode(file_get_contents($campaignLog), true) ?: [], -10) : []
], JSON_PRETTY_PRINT);
