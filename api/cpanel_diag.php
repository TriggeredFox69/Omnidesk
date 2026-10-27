<?php
header('Content-Type: application/json');

$baseDir = realpath(__DIR__ . '/..');
$connFile = $baseDir . '/data/whatsapp-bridge/connection.php';
$tokenFile = $baseDir . '/data/whatsapp-bridge/token';
$logFile = $baseDir . '/data/whatsapp-bridge/bridge.log';

$conn = file_exists($connFile) ? require($connFile) : null;

// Test curl directly to 127.0.0.1:3210/status
$ch = curl_init('http://127.0.0.1:3210/status');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 3,
    CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . ($conn['token'] ?? '')]
]);
$localStatus = curl_exec($ch);
$localHttp = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$localErr = curl_error($ch);
curl_close($ch);

echo json_encode([
    'connection_php' => $conn,
    'local_bridge_status' => json_decode($localStatus, true),
    'local_bridge_http' => $localHttp,
    'local_bridge_err' => $localErr,
    'recent_log' => file_exists($logFile) ? array_slice(explode("\n", file_get_contents($logFile)), -30) : []
], JSON_PRETTY_PRINT);
