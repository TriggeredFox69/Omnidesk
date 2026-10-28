<?php
/**
 * Omnidesk WhatsApp Bridge Keep-Alive & Auto-Healer
 * Checks 127.0.0.1:3210. If down, automatically starts the Node 22 bridge.
 */
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');

require_once __DIR__ . '/whatsapp_bridge.php';

$bridgeStatus = whatsappBridgeRequest('/status');

$started = false;
if (empty($bridgeStatus['success']) || ($bridgeStatus['state'] ?? '') === 'offline' || isset($_GET['restart'])) {
    $baseDir = realpath(__DIR__ . '/..');
    $dataDir = $baseDir . '/data/whatsapp-bridge';
    $logFile = $dataDir . '/bridge.log';
    $connFile = $dataDir . '/connection.php';
    $conn = file_exists($connFile) ? require($connFile) : [];
    $token = $conn['token'] ?? '';
    if (empty($token) && file_exists($dataDir . '/token')) {
        $token = trim(file_get_contents($dataDir . '/token'));
    }
    if (empty($token)) {
        $token = bin2hex(random_bytes(16));
        file_put_contents($dataDir . '/token', $token);
    }
    file_put_contents($connFile, "<?php\nreturn ['url' => 'http://127.0.0.1:3210', 'token' => '" . addslashes($token) . "'];\n");

    // If restart requested, kill any running bridge processes first
    if (isset($_GET['restart'])) {
        $output = @shell_exec('pgrep -f "dist/index.js|server.js" 2>&1');
        if ($output) {
            $pids = array_filter(array_map('trim', explode("\n", trim($output))), 'is_numeric');
            foreach ($pids as $p) @shell_exec("kill -9 $p 2>&1");
        }
        sleep(1);
    }

    $nodePath = '/opt/alt/alt-nodejs22/root/usr/bin/node';
    $bridgeDir = $baseDir . '/bridge';
    $scriptPath = file_exists($bridgeDir . '/dist/index.js') ? ($bridgeDir . '/dist/index.js') : ($bridgeDir . '/server.js');
    $authDir = $dataDir . '/whatsapp-bridge';
    if (!is_dir($authDir)) @mkdir($authDir, 0775, true);

    $startCmd = "PORT=3210 WHATSAPP_BRIDGE_HOST=127.0.0.1 WHATSAPP_BRIDGE_TOKEN=" . escapeshellarg($token)
        . " WHATSAPP_DATA_DIR=" . escapeshellarg($authDir) . " WHATSAPP_ENGINE=baileys "
        . escapeshellarg($nodePath) . " " . escapeshellarg($scriptPath)
        . " </dev/null >> " . escapeshellarg($logFile) . " 2>&1 &";

    shell_exec($startCmd);
    $started = true;
    sleep(3);
    $bridgeStatus = whatsappBridgeRequest('/status');
}

echo json_encode([
    'success' => !empty($bridgeStatus['success']),
    'auto_started' => $started,
    'timestamp' => date('c'),
    'bridge' => $bridgeStatus
], JSON_PRETTY_PRINT);
