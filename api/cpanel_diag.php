<?php
header('Content-Type: application/json');

$baseDir = realpath(__DIR__ . '/..');
$connFile = $baseDir . '/data/whatsapp-bridge/connection.php';
$tokenFile = $baseDir . '/data/whatsapp-bridge/token';
$logFile = $baseDir . '/data/whatsapp-bridge/bridge.log';

$conn = file_exists($connFile) ? require($connFile) : null;

// Allow executing clean pairing action directly through diag to eliminate stale prekeys
if (isset($_GET['clean_pair'])) {
    $authDir = $baseDir . '/data/whatsapp-bridge/whatsapp-bridge/baileys_auth';
    if (is_dir($authDir)) {
        @shell_exec('rm -rf ' . escapeshellarg($authDir));
    }
    @unlink($baseDir . '/data/whatsapp-bridge/raw_messages.json');
    @unlink($baseDir . '/data/whatsapp-bridge/whatsapp-bridge/raw_messages.json');
    $output = @shell_exec('pgrep -f "dist/index.js|server.js" 2>&1');
    if ($output) {
        $pids = array_filter(array_map('trim', explode("\n", trim($output))), 'is_numeric');
        foreach ($pids as $p) @shell_exec("kill -9 $p 2>&1");
    }
    sleep(1);
    $nodePath = '/opt/alt/alt-nodejs22/root/usr/bin/node';
    $bridgeDir = $baseDir . '/bridge';
    $scriptPath = file_exists($bridgeDir . '/dist/index.js') ? ($bridgeDir . '/dist/index.js') : ($bridgeDir . '/server.js');
    $token = $conn['token'] ?? (file_exists($tokenFile) ? trim(file_get_contents($tokenFile)) : '');
    $startCmd = "PORT=3210 WHATSAPP_BRIDGE_HOST=127.0.0.1 WHATSAPP_BRIDGE_TOKEN=" . escapeshellarg($token)
        . " WHATSAPP_DATA_DIR=" . escapeshellarg($baseDir . '/data/whatsapp-bridge/whatsapp-bridge') . " WHATSAPP_ENGINE=baileys "
        . escapeshellarg($nodePath) . " " . escapeshellarg($scriptPath)
        . " </dev/null >> " . escapeshellarg($logFile) . " 2>&1 &";
    shell_exec($startCmd);
    sleep(3);
}

// Allow targeted session reset for a single phone/LID to eliminate "Waiting for this message"
if (isset($_GET['reset_session'])) {
    $target = preg_replace('/\D/', '', $_GET['reset_session']);
    $authDir = $baseDir . '/data/whatsapp-bridge/whatsapp-bridge/baileys_auth';
    $purged = [];
    if (is_dir($authDir) && !empty($target)) {
        $files = scandir($authDir);
        foreach ($files as $file) {
            if (str_contains($file, $target) || (str_contains($file, '208023050051744') && $target === '923046588806')) {
                @unlink($authDir . '/' . $file);
                $purged[] = $file;
            }
        }
    }
    // Graceful reload without touching login
    $output = @shell_exec('pgrep -f "dist/index.js|server.js" 2>&1');
    if ($output) {
        $pids = array_filter(array_map('trim', explode("\n", trim($output))), 'is_numeric');
        foreach ($pids as $p) @shell_exec("kill -9 $p 2>&1");
    }
    sleep(1);
    $nodePath = '/opt/alt/alt-nodejs22/root/usr/bin/node';
    $bridgeDir = $baseDir . '/bridge';
    $token = $conn['token'] ?? (file_exists($tokenFile) ? trim(file_get_contents($tokenFile)) : '');
    $scriptPath = file_exists($bridgeDir . '/dist/index.js') ? ($bridgeDir . '/dist/index.js') : ($bridgeDir . '/server.js');
    $startCmd = "PORT=3210 WHATSAPP_BRIDGE_HOST=127.0.0.1 WHATSAPP_BRIDGE_TOKEN=" . escapeshellarg($token)
        . " WHATSAPP_DATA_DIR=" . escapeshellarg($baseDir . '/data/whatsapp-bridge/whatsapp-bridge') . " WHATSAPP_ENGINE=baileys "
        . escapeshellarg($nodePath) . " " . escapeshellarg($scriptPath)
        . " </dev/null >> " . escapeshellarg($logFile) . " 2>&1 &";
    shell_exec($startCmd);
    sleep(2);
    echo json_encode(['success' => true, 'purged_sessions' => $purged]);
    exit;
}

if (isset($_GET['restart'])) {
    $output = @shell_exec('pgrep -f "dist/index.js|server.js" 2>&1');
    if ($output) {
        $pids = array_filter(array_map('trim', explode("\n", trim($output))), 'is_numeric');
        foreach ($pids as $p) @shell_exec("kill -9 $p 2>&1");
    }
    sleep(1);
    $nodePath = '/opt/alt/alt-nodejs22/root/usr/bin/node';
    $bridgeDir = $baseDir . '/bridge';
    $token = $conn['token'] ?? (file_exists($tokenFile) ? trim(file_get_contents($tokenFile)) : '');
    $scriptPath = file_exists($bridgeDir . '/dist/index.js') ? ($bridgeDir . '/dist/index.js') : ($bridgeDir . '/server.js');
    $startCmd = "PORT=3210 WHATSAPP_BRIDGE_HOST=127.0.0.1 WHATSAPP_BRIDGE_TOKEN=" . escapeshellarg($token)
        . " WHATSAPP_DATA_DIR=" . escapeshellarg($baseDir . '/data/whatsapp-bridge/whatsapp-bridge') . " WHATSAPP_ENGINE=baileys "
        . escapeshellarg($nodePath) . " " . escapeshellarg($scriptPath)
        . " </dev/null >> " . escapeshellarg($logFile) . " 2>&1 &";
    shell_exec($startCmd);
    sleep(2);
    echo json_encode(['success' => true, 'restarted' => true]);
    exit;
}

// Allow cleaning up corrupted leaked Askari messages from lead_self
if (isset($_GET['clean_self_leak'])) {
    $askariMsgIds = [
        '3EB012E0A5466F178C3E54',
        'A510420E19E135CE02944E7C0B5FF584',
        'A53A72E4E265174553DB775BF560C6AA',
        '3EB0278D152C856A62B937',
        '3EB03FA0234C194BAC8FE1',
        '3EB0F70FC1E22B9463C6FE'
    ];
    $leadsFile = $baseDir . '/data/leads.json';
    if (file_exists($leadsFile)) {
        $leads = json_decode(file_get_contents($leadsFile), true);
        if (is_array($leads)) {
            foreach ($leads as &$lead) {
                if (($lead['id'] ?? '') === 'lead_self' && isset($lead['messages'])) {
                    $lead['messages'] = array_values(array_filter($lead['messages'], function($m) use ($askariMsgIds) {
                        return !in_array($m['waMessageId'] ?? '', $askariMsgIds);
                    }));
                }
            }
            unset($lead);
            file_put_contents($leadsFile, json_encode($leads, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }
    }
    $inboxFiles = [
        $baseDir . '/data/whatsapp-bridge/inbox.json',
        $baseDir . '/data/whatsapp-bridge/whatsapp-bridge/inbox.json'
    ];
    foreach ($inboxFiles as $ibFile) {
        if (file_exists($ibFile)) {
            $ib = json_decode(file_get_contents($ibFile), true);
            if (is_array($ib)) {
                foreach ($ib as &$msg) {
                    if (in_array($msg['waMessageId'] ?? '', $askariMsgIds)) {
                        $msg['phone'] = '923046588806';
                        $msg['name'] = '923046588806';
                    }
                }
                unset($msg);
                file_put_contents($ibFile, json_encode($ib, JSON_PRETTY_PRINT));
            }
        }
    }
    echo json_encode(['success' => true, 'cleaned_leads' => true]);
    exit;
}

// Allow executing wipe action directly through diag
if (isset($_GET['wipe'])) {
    @unlink($baseDir . '/data/whatsapp-bridge/inbox.json');
    @unlink($baseDir . '/data/whatsapp-bridge/whatsapp-bridge/inbox.json');
    file_put_contents($baseDir . '/data/whatsapp-bridge/inbox.json', '[]');
    if (is_dir($baseDir . '/data/whatsapp-bridge/whatsapp-bridge')) {
        file_put_contents($baseDir . '/data/whatsapp-bridge/whatsapp-bridge/inbox.json', '[]');
    }
    @unlink($baseDir . '/data/whatsapp-bridge/raw_messages.json');
    @unlink($baseDir . '/data/whatsapp-bridge/whatsapp-bridge/raw_messages.json');
    file_put_contents($baseDir . '/data/whatsapp-bridge/raw_messages.json', '{}');
    if (is_dir($baseDir . '/data/whatsapp-bridge/whatsapp-bridge')) {
        file_put_contents($baseDir . '/data/whatsapp-bridge/whatsapp-bridge/raw_messages.json', '{}');
    }
    $lidMap = ['208023050051744' => '923046588806'];
    file_put_contents($baseDir . '/data/whatsapp-bridge/lid_map.json', json_encode($lidMap, JSON_PRETTY_PRINT));
    if (is_dir($baseDir . '/data/whatsapp-bridge/whatsapp-bridge')) {
        file_put_contents($baseDir . '/data/whatsapp-bridge/whatsapp-bridge/lid_map.json', json_encode($lidMap, JSON_PRETTY_PRINT));
    }
    $leadsFile = $baseDir . '/data/leads.json';
    $selfLead = [
        [
            'id' => 'lead_self',
            'name' => 'My WhatsApp (Self Test)',
            'phone' => '923318036576',
            'display' => '+92 331 8036576',
            'city' => 'Connected Number',
            'network' => 'Ufone',
            'ownerId' => 'USR-ADMIN-01',
            'ownerName' => 'System Administrator',
            'leadState' => 'ongoing',
            'status' => 'replied',
            'unreadCount' => 0,
            'interest' => 'Live WhatsApp Direct Hub',
            'lastActive' => 'Just now',
            'notes' => 'Connected phone self-testing chat (+92 331 8036576). Messages, voice notes, and docs deliver straight to your linked phone 24/7!',
            'messages' => [
                ['sender' => 'lead', 'text' => '⚡ WhatsApp (+92 331 8036576) is connected 24/7! Send any text, voice note, or document to see it arrive directly on your phone.', 'time' => '12:00 PM']
            ]
        ]
    ];
    file_put_contents($leadsFile, json_encode($selfLead, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    $campaignLog = $baseDir . '/data/campaign_logs.json';
    if (file_exists($campaignLog)) file_put_contents($campaignLog, '[]');

    // Gracefully restart bridge without touching baileys_auth
    $output = @shell_exec('pgrep -f "dist/index.js|server.js" 2>&1');
    if ($output) {
        $pids = array_filter(array_map('trim', explode("\n", trim($output))), 'is_numeric');
        foreach ($pids as $p) @shell_exec("kill -9 $p 2>&1");
    }
    sleep(1);

    $nodePath = '/opt/alt/alt-nodejs22/root/usr/bin/node';
    $bridgeDir = $baseDir . '/bridge';
    $token = $conn['token'] ?? (file_exists($tokenFile) ? trim(file_get_contents($tokenFile)) : '');
    file_put_contents($connFile, "<?php\nreturn ['url' => 'http://127.0.0.1:3210', 'token' => '" . addslashes($token) . "'];\n");
    $scriptPath = file_exists($bridgeDir . '/dist/index.js') ? ($bridgeDir . '/dist/index.js') : ($bridgeDir . '/server.js');
    $startCmd = "PORT=3210 WHATSAPP_BRIDGE_HOST=127.0.0.1 WHATSAPP_BRIDGE_TOKEN=" . escapeshellarg($token)
        . " WHATSAPP_DATA_DIR=" . escapeshellarg($baseDir . '/data/whatsapp-bridge/whatsapp-bridge') . " WHATSAPP_ENGINE=baileys "
        . escapeshellarg($nodePath) . " " . escapeshellarg($scriptPath)
        . " </dev/null >> " . escapeshellarg($logFile) . " 2>&1 &";
    shell_exec($startCmd);
    sleep(2);
}

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

$sendDebug = null;
if (isset($_GET['test_send'])) {
    $sendCh = curl_init('http://127.0.0.1:3210/send');
    curl_setopt_array($sendCh, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . ($conn['token'] ?? ''), 'Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode(['phone' => '923318036576', 'message' => 'Omnidesk test ping'])
    ]);
    $sendRaw = curl_exec($sendCh);
    $sendCode = curl_getinfo($sendCh, CURLINFO_HTTP_CODE);
    $sendErr = curl_error($sendCh);
    curl_close($sendCh);
    $sendDebug = [
        'http_code' => $sendCode,
        'curl_err' => $sendErr,
        'raw_res' => $sendRaw,
        'json_res' => json_decode((string)$sendRaw, true)
    ];
}

echo json_encode([
    'baseDir' => $baseDir,
    'repo_commit' => shell_exec('cd /home/dailygol/repositories/Omnidesk && git log -1 --oneline 2>&1'),
    'repo_status' => shell_exec('cd /home/dailygol/repositories/Omnidesk && git status 2>&1'),
    'api_files' => scandir(__DIR__),
    'cpanel_bridge_exists' => file_exists(__DIR__ . '/cpanel_bridge.php'),
    'connection_php' => $conn,
    'local_bridge_status' => json_decode($localStatus, true),
    'local_bridge_http' => $localHttp,
    'local_bridge_err' => $localErr,
    'send_debug' => $sendDebug,
    'grep_923' => file_exists($baseDir . '/data/whatsapp-bridge/grep_923.txt') ? file_get_contents($baseDir . '/data/whatsapp-bridge/grep_923.txt') : null,
    'recent_log' => file_exists($logFile) ? array_slice(explode("\n", file_get_contents($logFile)), -30) : []
], JSON_PRETTY_PRINT);
