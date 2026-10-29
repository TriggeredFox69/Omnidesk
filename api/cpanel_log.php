<?php
$baseDir = realpath(__DIR__ . '/..');

if (isset($_GET['file'])) {
    $f = $baseDir . '/data/whatsapp-bridge/' . basename($_GET['file']);
    if (file_exists($f)) {
        header('Content-Type: text/plain');
        echo file_get_contents($f);
        exit;
    }
}

header('Content-Type: application/json');
$logFile = $baseDir . '/data/whatsapp-bridge/bridge.log';
$campaignLog = $baseDir . '/data/campaign_logs.json';

$maxLines = isset($_GET['lines']) ? min(1000, max(10, (int)$_GET['lines'])) : 100;
echo json_encode([
    'bridge_log_recent' => file_exists($logFile) ? array_slice(explode("\n", file_get_contents($logFile)), -$maxLines) : [],
    'campaign_logs_recent' => file_exists($campaignLog) ? array_slice(json_decode(file_get_contents($campaignLog), true) ?: [], -10) : []
], JSON_PRETTY_PRINT);
