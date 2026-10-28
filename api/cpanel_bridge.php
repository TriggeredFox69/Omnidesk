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

    case 'view_qr':
        $live = checkBridgeLive();
        if ($live && !empty($live['is_connected'])) {
            header('Content-Type: text/html');
            echo "<div style='background:#0b141a;color:#fff;font-family:sans-serif;text-align:center;padding:60px;'><h2 style='color:#25d366;'>✓ WhatsApp is Already Connected!</h2><p style='color:#8696a0;font-size:16px;'>Connected number: <strong>+" . htmlspecialchars($live['connected_number'] ?? '') . "</strong></p><p style='margin-top:20px;'><a href='/' style='color:#25d366;text-decoration:none;font-weight:700;'>&larr; Open Omnidesk CRM</a></p></div>";
            exit;
        }
        $qr = $live['qr'] ?? '';
        if (!$qr) {
            $ch = curl_init('http://127.0.0.1:3210/connect');
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => '{}',
                CURLOPT_TIMEOUT => 10,
                CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token, 'Content-Type: application/json']
            ]);
            curl_exec($ch);
            curl_close($ch);
            usleep(800000);
            $live = checkBridgeLive();
            $qr = $live['qr'] ?? '';
        }
        header('Content-Type: text/html');
        echo "<!DOCTYPE html><html><head><title>Link WhatsApp - Omnidesk 24/7</title><meta name='viewport' content='width=device-width,initial-scale=1'><meta http-equiv='refresh' content='12'></head><body style='background:#0b141a;color:#fff;font-family:system-ui,-apple-system,sans-serif;text-align:center;padding:30px 15px;'><div style='max-width:380px;margin:0 auto;background:#111b21;padding:26px 20px;border-radius:16px;box-shadow:0 10px 30px rgba(0,0,0,0.5);border:1px solid #222d34;'><h2 style='margin-top:0;font-size:20px;color:#25d366;'>📱 Link Your WhatsApp</h2><p style='color:#8696a0;font-size:13px;line-height:1.5;'>Open WhatsApp on your phone &rarr; <b>Settings</b> &rarr; <b>Linked devices</b> &rarr; <b>Link a device</b>, then scan below:</p><div style='background:#fff;padding:12px;border-radius:12px;display:inline-block;margin:16px 0;box-shadow:0 4px 14px rgba(0,0,0,0.3);'>" . ($qr ? "<img src='{$qr}' width='260' height='260' style='display:block;border-radius:8px;' alt='WhatsApp QR Code'>" : "<p style='color:#111;padding:40px 0;'>Generating QR code...</p>") . "</div><p style='color:#8696a0;font-size:12px;'>This page auto-refreshes every 12 seconds.<br>Once scanned, your phone stays connected 24/7 on cPanel!</p><a href='/' style='display:inline-block;margin-top:14px;color:#25d366;font-size:14px;font-weight:600;text-decoration:none;'>&larr; Back to Omnidesk CRM</a></div></body></html>";
        exit;

    case 'relink':
        // Kill existing bridge processes
        $pid = findBridgePid();
        if ($pid) shell_exec("kill -9 $pid 2>&1");
        shell_exec('pkill -9 -f "dist/index.js" 2>&1');
        shell_exec('pkill -9 -f "server.js" 2>&1');
        sleep(1);

        // Clear stale auth sessions causing Bad MAC Signal ratchet errors
        $primaryAuth = $dataDir . '/whatsapp-bridge/baileys_auth';
        $secondaryAuth = $bridgeDir . '/data/whatsapp-bridge/baileys_auth';
        shell_exec("rm -rf " . escapeshellarg($primaryAuth) . " " . escapeshellarg($secondaryAuth) . " 2>&1");
        @unlink($logFile);

        $senderConfigFile = $baseDir . '/data/sender_config.json';
        file_put_contents($senderConfigFile, json_encode([
            'connected_number' => '923318036576',
            'display' => '+92 331 8036576',
            'updated_at' => date('Y-m-d H:i:s')
        ], JSON_PRETTY_PRINT));

        file_put_contents($connFile, "<?php\nreturn ['url' => 'http://127.0.0.1:3210', 'token' => '" . addslashes($token) . "'];\n");

        $scriptPath = file_exists($bridgeDir . '/dist/index.js') ? ($bridgeDir . '/dist/index.js') : ($bridgeDir . '/server.js');
        $startCmd = "PORT=3210 WHATSAPP_BRIDGE_HOST=127.0.0.1 WHATSAPP_BRIDGE_TOKEN=" . escapeshellarg($token)
            . " WHATSAPP_DATA_DIR=" . escapeshellarg($dataDir . '/whatsapp-bridge') . " WHATSAPP_ENGINE=baileys "
            . escapeshellarg($nodePath) . " " . escapeshellarg($scriptPath)
            . " </dev/null >> " . escapeshellarg($logFile) . " 2>&1 &";
        shell_exec($startCmd);

        for ($i = 0; $i < 10; $i++) {
            usleep(500000);
            $started = checkBridgeLive();
            if ($started !== null) break;
        }

        // Trigger /connect to generate fresh QR
        $ch = curl_init('http://127.0.0.1:3210/connect');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => '{}',
            CURLOPT_TIMEOUT => 10,
            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token, 'Content-Type: application/json']
        ]);
        curl_exec($ch);
        curl_close($ch);

        sleep(2);
        $finalStatus = checkBridgeLive();

        echo json_encode([
            'success' => true,
            'message' => 'Stale session cleared. Scan this QR code with WhatsApp on your phone (+923318036576) to link cleanly.',
            'bridge_status' => $finalStatus,
            'qr' => $finalStatus['qr'] ?? null
        ], JSON_PRETTY_PRINT);
        break;

    case 'test_send':
        $phone = preg_replace('/\D+/', '', $_GET['phone'] ?? '923318036576');
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
        } elseif ($type === 'image') {
            // Valid 1x1 PNG image
            $pngB64 = 'iVBORw0KGgoAAAANSUhEUgAAAAoAAAAKCAYAAACNMs+9AAAAFUlEQVR42mNk+M9QzwAEjDAGNYx0AAB00Qf3uQ7WkQAAAABJRU5ErkJggg==';
            $payload['media'] = [
                'mimetype' => 'image/png',
                'data' => $pngB64,
                'filename' => 'omnidesk_sample_image.png'
            ];
        } elseif ($type === 'voice') {
            // Genuine OGG Opus Audio stream for WhatsApp mobile PTT playback
            $validOggOpusB64 = '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';
            $payload['media'] = [
                'mimetype' => 'audio/ogg; codecs=opus',
                'data' => $validOggOpusB64,
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

    case 'deploy':
    case 'git_pull':
        $gitPath = trim((string) @shell_exec('which git 2>&1')) ?: 'git';
        $search = @shell_exec('find /home/dailygol -maxdepth 4 -name ".git" 2>/dev/null');
        $lines = array_filter(array_map('trim', explode("\n", (string)$search)));
        $results = [];
        foreach ($lines as $gitDir) {
            $repo = dirname($gitDir);
            $gitCmd = "cd " . escapeshellarg($repo) . " && " . escapeshellarg($gitPath) . " fetch origin main 2>&1 && " . escapeshellarg($gitPath) . " reset --hard origin/main 2>&1";
            $gitOut = @shell_exec($gitCmd);
            $deployCmd = "cd " . escapeshellarg($repo) . " && /bin/cp -R api assets index.php index.html login.php /home/dailygol/omnidesk.lalagpt.pk/ 2>&1 && /bin/cp -R bridge/dist /home/dailygol/omnidesk.lalagpt.pk/bridge/ 2>&1 && /bin/cp -f bridge/*.js /home/dailygol/omnidesk.lalagpt.pk/bridge/ 2>&1";
            $deployOut = @shell_exec($deployCmd);
            $results[] = [
                'repo' => $repo,
                'git' => trim((string)$gitOut),
                'deploy' => trim((string)$deployOut)
            ];
        }
        echo json_encode(['success' => !empty($results), 'repos' => $results], JSON_PRETTY_PRINT);
        break;

    case 'test_node':
        $nodeVer = shell_exec(escapeshellarg($nodePath) . " -v 2>&1");
        $ffmpeg = shell_exec("which ffmpeg 2>&1") ?: 'none';
        echo json_encode(['node' => trim($nodeVer), 'ffmpeg' => trim($ffmpeg)]);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action.']);
}
