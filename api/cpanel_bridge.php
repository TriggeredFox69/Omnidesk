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
    $output = @shell_exec('pgrep -f "dist/index.js|server.js" 2>&1');
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

    case 'connect':
        $ch = curl_init('http://127.0.0.1:3210/connect');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => '{}',
            CURLOPT_TIMEOUT => 10,
            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token, 'Content-Type: application/json']
        ]);
        $res = curl_exec($ch);
        curl_close($ch);
        echo $res ?: json_encode(['success' => false, 'message' => 'Connect failed']);
        break;

    case 'test_send':
        $phone = preg_replace('/\D+/', '', $_GET['phone'] ?? '923046588806');
        if (str_starts_with($phone, '0092')) $phone = substr($phone, 2);
        elseif (str_starts_with($phone, '03') && strlen($phone) === 11) $phone = '92' . substr($phone, 1);
        elseif (str_starts_with($phone, '3') && strlen($phone) === 10) $phone = '92' . $phone;

        $type = $_GET['type'] ?? 'text';
        $msg = $_GET['msg'] ?? ('Omnidesk Live ' . ucfirst($type) . ' Test (' . date('H:i:s') . ')');
        $reqId = 'req_' . bin2hex(random_bytes(8));

        $payload = [
            'phone' => $phone,
            'message' => $msg,
            'request_id' => $reqId
        ];

        if ($type === 'doc') {
            $pdfContent = "%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj 2 0 obj<</Type/Pages/Count 1/Kids[3 0 R]>>endobj 3 0 obj<</Type/Page/MediaBox[0 0 612 792]/Parent 2 0 R/Resources<<>>>>endobj\nxref\n0 4\n0000000000 65535 f \n0000000009 00000 n \n0000000052 00000 n \n0000000101 00000 n \ntrailer<</Size 4/Root 1 0 R>>\nstartxref\n178\n%%EOF";
            $payload['media'] = [
                'mimetype' => 'application/pdf',
                'data' => base64_encode($pdfContent),
                'filename' => 'omnidesk_sample_document.pdf'
            ];
        } elseif ($type === 'voice') {
            $oggHeader = "OggS\x00\x02\x00\x00\x00\x00\x00\x00\x00\x00\x01\x00\x00\x00\x00\x00\x00\x00\x77\x55\x88\x99\x01\x13OpusHead\x01\x01\x00\x00\x80\xbb\x00\x00\x00\x00\x00";
            $payload['media'] = [
                'mimetype' => 'audio/ogg; codecs=opus',
                'data' => base64_encode($oggHeader),
                'filename' => 'omnidesk_voice_memo.ogg',
                'voice' => true
            ];
        }

        $ch = curl_init('http://127.0.0.1:3210/send');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $token,
                'Content-Type: application/json'
            ]
        ]);
        $res = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        echo json_encode([
            'http_code' => $code,
            'type' => $type,
            'target_phone' => $phone,
            'curl_error' => $err,
            'response' => json_decode($res, true) ?: $res
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
        $startCmd = "PORT=3210 WHATSAPP_BRIDGE_HOST=127.0.0.1 WHATSAPP_BRIDGE_TOKEN=" . escapeshellarg($token)
            . " WHATSAPP_DATA_DIR=" . escapeshellarg($dataDir . '/whatsapp-bridge') . " WHATSAPP_ENGINE=baileys "
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
        shell_exec('pkill -9 -f "dist/index.js" 2>&1');
        shell_exec('pkill -9 -f "server.js" 2>&1');
        echo json_encode(['success' => true, 'message' => 'Bridge process stopped.']);
        break;

    case 'restart':
        $pid = findBridgePid();
        if ($pid) shell_exec("kill -9 $pid 2>&1");
        shell_exec('pkill -9 -f "dist/index.js" 2>&1');
        shell_exec('pkill -9 -f "server.js" 2>&1');
        sleep(1);

        file_put_contents($connFile, "<?php\nreturn ['url' => 'http://127.0.0.1:3210', 'token' => '" . addslashes($token) . "'];\n");
        $scriptPath = file_exists($bridgeDir . '/dist/index.js') ? ($bridgeDir . '/dist/index.js') : ($bridgeDir . '/server.js');
        $startCmd = "PORT=3210 WHATSAPP_BRIDGE_HOST=127.0.0.1 WHATSAPP_BRIDGE_TOKEN=" . escapeshellarg($token)
            . " WHATSAPP_DATA_DIR=" . escapeshellarg($dataDir . '/whatsapp-bridge') . " WHATSAPP_ENGINE=baileys "
            . escapeshellarg($nodePath) . " " . escapeshellarg($scriptPath)
            . " </dev/null >> " . escapeshellarg($logFile) . " 2>&1 &";
        shell_exec($startCmd);

        $started = null;
        for ($i = 0; $i < 8; $i++) {
            usleep(500000);
            $started = checkBridgeLive();
            if ($started !== null) break;
        }

        echo json_encode([
            'success' => $started !== null,
            'action' => 'restart',
            'is_running' => $started !== null,
            'bridge_status' => $started,
            'pid' => findBridgePid(),
            'log_tail' => file_exists($logFile) ? implode("\n", array_slice(explode("\n", file_get_contents($logFile)), -10)) : ''
        ], JSON_PRETTY_PRINT);
        break;

    case 'log':
        $lines = min(200, max(5, (int)($_GET['lines'] ?? 50)));
        $content = file_exists($logFile) ? file_get_contents($logFile) : '';
        $all = explode("\n", $content);
        echo json_encode([
            'success' => true,
            'lines' => $lines,
            'log' => implode("\n", array_slice($all, -$lines))
        ], JSON_PRETTY_PRINT);
        break;

    case 'test_node':
        $res = shell_exec(escapeshellarg($nodePath) . " -e \"console.log('Node test: OK, Node version: ' + process.version);\" 2>&1");
        echo json_encode(['result' => trim($res)]);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action.']);
}
