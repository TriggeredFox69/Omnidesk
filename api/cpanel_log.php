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

if (isset($_GET['clear'])) {
    if (file_exists($logFile)) @file_put_contents($logFile, '');
    echo json_encode(['success' => true, 'cleared' => true]);
    exit;
}

$maxLines = isset($_GET['lines']) ? min(500, max(10, (int)$_GET['lines'])) : 50;
$logLines = [];
if (file_exists($logFile)) {
    $tail = @shell_exec('tail -n ' . (int)$maxLines . ' ' . escapeshellarg($logFile) . ' 2>&1');
    if ($tail !== null && $tail !== false) {
        $logLines = array_values(array_filter(explode("\n", trim((string)$tail))));
    } else {
        $fp = @fopen($logFile, 'r');
        if ($fp) {
            $size = filesize($logFile);
            $readSize = min($size, 32768);
            if ($size > $readSize) fseek($fp, -$readSize, SEEK_END);
            $chunk = (string)fread($fp, $readSize);
            fclose($fp);
            $logLines = array_slice(array_values(array_filter(explode("\n", trim($chunk)))), -$maxLines);
        }
    }
}
echo json_encode([
    'bridge_log_recent' => $logLines,
    'campaign_logs_recent' => file_exists($campaignLog) ? array_slice(json_decode(file_get_contents($campaignLog), true) ?: [], -10) : []
], JSON_PRETTY_PRINT);
