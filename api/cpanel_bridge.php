<?php
/**
 * cPanel Native WhatsApp Bridge Daemon Manager
 * Allows installing, launching, monitoring, and maintaining the bridge 24/7 on cPanel.
 */
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');

$nodePath = '/opt/alt/alt-nodejs22/root/usr/bin/node';
$npmPath = '/opt/alt/alt-nodejs22/root/usr/bin/npm';
$baseDir = realpath(__DIR__ . '/..');
$bridgeDir = $baseDir . '/bridge';
$dataDir = $baseDir . '/data/whatsapp-bridge';
$logFile = $dataDir . '/bridge.log';
$tokenFile = $dataDir . '/token';
$connFile = $dataDir . '/connection.php';
$pidFile = $dataDir . '/bridge.pid';

if (!is_dir($dataDir)) @mkdir($dataDir, 0775, true);

// Ensure local bridge token exists
if (!file_exists($tokenFile) || filesize($tokenFile) < 32) {
    file_put_contents($tokenFile, bin2hex(random_bytes(32)));
}
$token = trim(file_get_contents($tokenFile));

function checkBridgeLive(): ?array {
    $ch = curl_init('http://127.0.0.1:3210/status');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 2,
        CURLOPT_CONNECTTIMEOUT => 2,
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $GLOBALS['token']]
    ]);
    $res = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($res !== false && $code === 200) {
        $data = json_decode($res, true);
        if (is_array($data)) return $data;
    }
    return null;
}

function findBridgePid(): ?int {
    $output = @shell_exec('pgrep -f "bridge/server.js" 2>&1');
    if ($output) {
        $pids = array_filter(array_map('trim', explode("\n", trim($output))), 'is_numeric');
        if (!empty($pids)) return (int) end($pids);
    }
    return null;
}

$action = $_GET['action'] ?? $_POST['action'] ?? ($argv[1] ?? 'status');

switch ($action) {
    case 'status':
        $live = checkBridgeLive();
        $pid = findBridgePid();
        $hasModules = file_exists($bridgeDir . '/dist/index.js') || is_dir($bridgeDir . '/node_modules/@whiskeysockets/baileys');
        echo json_encode([
            'success' => true,
            'is_running' => $live !== null,
            'pid' => $pid,
            'modules_installed' => $hasModules,
            'has_bundle' => file_exists($bridgeDir . '/dist/index.js'),
            'bridge_status' => $live,
            'log_tail' => file_exists($logFile) ? implode("\n", array_slice(explode("\n", file_get_contents($logFile)), -15)) : ''
        ], JSON_PRETTY_PRINT);
        break;

    case 'kill_stuck':
    case 'stop':
        $pid = findBridgePid();
        if ($pid) shell_exec("kill -9 $pid 2>&1");
        shell_exec('pkill -9 -f "npm" 2>&1');
        shell_exec('pkill -9 -f "server.js" 2>&1');
        echo json_encode(['success' => true, 'message' => 'All bridge and npm background processes stopped.']);
        break;

    case 'clean_install':
    case 'install':
        // If pre-bundled dist exists, no installation is needed!
        if (file_exists($bridgeDir . '/dist/index.js')) {
            echo json_encode([
                'success' => true,
                'installed' => true,
                'message' => 'Pre-compiled bundle is present! Zero npm install needed.'
            ], JSON_PRETTY_PRINT);
            break;
        }
        $installLog = $dataDir . '/install.log';
        $npmRunning = (bool) @exec('pgrep -f "npm" 2>&1');
        if (!$npmRunning) {
            $cleanCmd = "cd " . escapeshellarg($bridgeDir) . " && rm -rf node_modules package-lock.json && PUPPETEER_SKIP_CHROMIUM_DOWNLOAD=1 "
                . escapeshellarg($npmPath) . " install --omit=dev --omit=optional --no-audit --no-fund > "
                . escapeshellarg($installLog) . " 2>&1 &";
            shell_exec($cleanCmd);
        }
        $hasModules = is_dir($bridgeDir . '/node_modules/@whiskeysockets/baileys');
        echo json_encode([
            'success' => $hasModules,
            'installed' => $hasModules,
            'in_progress' => (bool) @exec('pgrep -f "npm" 2>&1')
        ], JSON_PRETTY_PRINT);
        break;

    case 'start':
    case 'cron':
        $live = checkBridgeLive();
        if ($live !== null) {
            file_put_contents($connFile, "<?php\nreturn ['url' => 'http://127.0.0.1:3210', 'token' => '" . addslashes($token) . "'];\n");
            echo json_encode([
                'success' => true,
                'message' => 'Bridge is already running!',
                'bridge_status' => $live
            ], JSON_PRETTY_PRINT);
            break;
        }

        // Kill any zombie processes
        $oldPid = findBridgePid();
        if ($oldPid) @shell_exec("kill -9 $oldPid 2>&1");

        // Set local connection config
        file_put_contents($connFile, "<?php\nreturn ['url' => 'http://127.0.0.1:3210', 'token' => '" . addslashes($token) . "'];\n");

        // Determine executable script (dist/index.js pre-compiled bundle or server.js)
        $scriptPath = file_exists($bridgeDir . '/dist/index.js') ? ($bridgeDir . '/dist/index.js') : ($bridgeDir . '/server.js');

        // Spawn background node process with stdio closed
        $startCmd = "PORT=3210 WHATSAPP_BRIDGE_HOST=127.0.0.1 WHATSAPP_BRIDGE_TOKEN=" . escapeshellarg($token) . " WHATSAPP_ENGINE=baileys "
            . escapeshellarg($nodePath) . " " . escapeshellarg($scriptPath)
            . " </dev/null >> " . escapeshellarg($logFile) . " 2>&1 &";

        shell_exec($startCmd);

        // Wait up to 3 seconds for it to bind
        $started = null;
        for ($i = 0; $i < 6; $i++) {
            usleep(500000);
            $started = checkBridgeLive();
            if ($started !== null) break;
        }

        echo json_encode([
            'success' => $started !== null,
            'is_running' => $started !== null,
            'bridge_status' => $started,
            'pid' => findBridgePid(),
            'log_tail' => file_exists($logFile) ? implode("\n", array_slice(explode("\n", file_get_contents($logFile)), -10)) : ''
        ], JSON_PRETTY_PRINT);
        break;

    case 'stop':
        $pid = findBridgePid();
        if ($pid) {
            shell_exec("kill -9 $pid 2>&1");
        }
        echo json_encode(['success' => true, 'message' => 'Bridge process stopped.']);
        break;

    case 'restart':
        $pid = findBridgePid();
        if ($pid) shell_exec("kill -9 $pid 2>&1");
        usleep(500000);
        header('Location: ?action=start');
        exit;

    case 'test_node':
        $res = shell_exec(escapeshellarg($nodePath) . " -e \"console.log('Node test: OK, Node version: ' + process.version);\" 2>&1");
        echo json_encode(['result' => trim($res)]);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action.']);
}
