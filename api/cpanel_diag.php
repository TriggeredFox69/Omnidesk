<?php
header('Content-Type: application/json');

$baseDir = realpath(__DIR__ . '/..');
$connFile = $baseDir . '/data/whatsapp-bridge/connection.php';
$tokenFile = $baseDir . '/data/whatsapp-bridge/token';
$logFile = $baseDir . '/data/whatsapp-bridge/bridge.log';

$conn = file_exists($connFile) ? require($connFile) : null;

// Check and setup cron job to keep bridge alive 24/7
if (isset($_GET['cron'])) {
    $currentCron = @shell_exec('crontab -l 2>&1');
    $cronCmd = '* * * * * /usr/bin/curl -s "https://omnidesk.lalagpt.pk/api/keep_alive.php" >/dev/null 2>&1';
    $installed = false;
    if (isset($_GET['install_cron'])) {
        $lines = array_filter(array_map('trim', explode("\n", (string)$currentCron)));
        $filtered = array_filter($lines, fn($l) => !str_contains($l, 'keep_alive.php'));
        $filtered[] = $cronCmd;
        $newCrontab = implode("\n", $filtered) . "\n";
        $tmp = tempnam(sys_get_temp_dir(), 'cron');
        file_put_contents($tmp, $newCrontab);
        $res = @shell_exec("crontab " . escapeshellarg($tmp) . " 2>&1");
        @unlink($tmp);
        $currentCron = @shell_exec('crontab -l 2>&1');
        $installed = true;
    }
    echo json_encode([
        'success' => true,
        'installed' => $installed,
        'crontab' => $currentCron
    ], JSON_PRETTY_PRINT);
    exit;
}

// Allow 1-click git pull and cPanel deployment
if (isset($_GET['deploy']) || isset($_GET['git_pull'])) {
    $repoDir = '/home/dailygol/repositories/Omnidesk';
    $targetDir = '/home/dailygol/omnidesk.lalagpt.pk';
    $pullOut = @shell_exec("cd " . escapeshellarg($repoDir) . " && git fetch origin main 2>&1 && git reset --hard origin/main 2>&1");
    $deployOut = @shell_exec("cd " . escapeshellarg($repoDir) . " && /bin/cp -R api assets index.php index.html login.php open-omnidesk.html " . escapeshellarg($targetDir) . "/ 2>&1 && /bin/cp -R bridge/dist " . escapeshellarg($targetDir) . "/bridge/ 2>/dev/null || true && /bin/cp -f bridge/*.js bridge/*.sh " . escapeshellarg($targetDir) . "/bridge/ 2>&1 && chmod +x " . escapeshellarg($targetDir) . "/bridge/*.sh 2>/dev/null || true");
    echo json_encode([
        'success' => true,
        'git_pull' => trim((string)$pullOut),
        'deploy' => trim((string)$deployOut)
    ], JSON_PRETTY_PRINT);
    exit;
}

// Session auto-healer & restorer: consolidate creds.json into standard directory and restart
if (isset($_GET['restore_session'])) {
    $authDir1 = $baseDir . '/data/whatsapp-bridge/baileys_auth';
    $authDir2 = $baseDir . '/data/whatsapp-bridge/whatsapp-bridge/baileys_auth';
    $sourceCreds = null;
    $sourceDir = null;

    if (file_exists($authDir1 . '/creds.json') && filesize($authDir1 . '/creds.json') > 100) {
        $sourceCreds = $authDir1 . '/creds.json';
        $sourceDir = $authDir1;
    } elseif (file_exists($authDir2 . '/creds.json') && filesize($authDir2 . '/creds.json') > 100) {
        $sourceCreds = $authDir2 . '/creds.json';
        $sourceDir = $authDir2;
    } elseif (file_exists($baseDir . '/data/whatsapp-bridge/creds.json.bak')) {
        @mkdir($authDir1, 0775, true);
        @copy($baseDir . '/data/whatsapp-bridge/creds.json.bak', $authDir1 . '/creds.json');
        $sourceCreds = $authDir1 . '/creds.json';
        $sourceDir = $authDir1;
    }

    $copyResults = [];
    if ($sourceDir && is_dir($sourceDir)) {
        @mkdir($authDir1, 0775, true);
        @mkdir($authDir2, 0775, true);
        $targetDirs = array_unique([$authDir1, $authDir2]);
        foreach ($targetDirs as $td) {
            if ($td !== $sourceDir) {
                $cp = @shell_exec("/bin/cp -R " . escapeshellarg($sourceDir) . "/* " . escapeshellarg($td) . "/ 2>&1");
                $copyResults[$td] = trim((string)$cp);
            }
        }
    }

    // Kill running bridge processes
    $output = @shell_exec('pgrep -f "dist/index.js|server.js" 2>&1');
    if ($output) {
        $pids = array_filter(array_map('trim', explode("\n", trim($output))), 'is_numeric');
        foreach ($pids as $p) @shell_exec("kill -9 $p 2>&1");
    }
    sleep(1);

    // Restart daemon pointing strictly to standard data directory
    $nodePath = '/opt/alt/alt-nodejs22/root/usr/bin/node';
    $bridgeDir = $baseDir . '/bridge';
    $token = $conn['token'] ?? (file_exists($tokenFile) ? trim(file_get_contents($tokenFile)) : '');
    $scriptPath = file_exists($bridgeDir . '/dist/index.js') ? ($bridgeDir . '/dist/index.js') : ($bridgeDir . '/server.js');
    $startCmd = "PORT=3210 WHATSAPP_BRIDGE_HOST=127.0.0.1 WHATSAPP_BRIDGE_TOKEN=" . escapeshellarg($token)
        . " WHATSAPP_DATA_DIR=" . escapeshellarg($baseDir . '/data/whatsapp-bridge') . " WHATSAPP_ENGINE=baileys "
        . escapeshellarg($nodePath) . " " . escapeshellarg($scriptPath)
        . " </dev/null >> " . escapeshellarg($logFile) . " 2>&1 &";
    shell_exec($startCmd);
    sleep(2);

    echo json_encode([
        'success' => true,
        'sourceDir' => $sourceDir,
        'sourceCreds' => $sourceCreds,
        'copyResults' => $copyResults
    ], JSON_PRETTY_PRINT);
    exit;
}

// Full pristine CRM restore: resets leads to 8 core leads, wipes all mixed history and Bad MAC auth, starts fresh
if (isset($_GET['restore_pristine']) || isset($_GET['restore_clean_crm'])) {
    $output = @shell_exec('pgrep -f "dist/index.js|server.js" 2>&1');
    if ($output) {
        $pids = array_filter(array_map('trim', explode("\n", trim($output))), 'is_numeric');
        foreach ($pids as $p) @shell_exec("kill -9 $p 2>&1");
    }
    sleep(1);

    $auth1 = $baseDir . '/data/whatsapp-bridge/baileys_auth';
    $auth2 = $baseDir . '/data/whatsapp-bridge/whatsapp-bridge/baileys_auth';
    @shell_exec('rm -rf ' . escapeshellarg($auth1) . ' ' . escapeshellarg($auth2));
    @unlink($baseDir . '/data/whatsapp-bridge/creds.json.bak');
    @unlink($baseDir . '/data/whatsapp-bridge/creds.json.bak2');
    @unlink($baseDir . '/data/whatsapp-bridge/raw_messages.json');
    @unlink($baseDir . '/data/whatsapp-bridge/whatsapp-bridge/raw_messages.json');
    @unlink($baseDir . '/data/whatsapp-bridge/grep_923.txt');
    @unlink($baseDir . '/data/whatsapp-bridge/lid_map.json');
    @file_put_contents($baseDir . '/data/whatsapp-bridge/inbox.json', '[]');
    @file_put_contents($logFile, '');
    @file_put_contents($baseDir . '/data/campaign_logs.json', '[]');

    $pristineLeads = [
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
            'interest' => 'Live Delivery Verification',
            'lastActive' => 'Just now',
            'notes' => 'Personal test chat. Any message, voice memo, or document sent here arrives instantly on your linked phone.',
            'messages' => [
                ['sender' => 'lead', 'text' => '⚡ This is your personal linked WhatsApp chat (+92 331 8036576). Send any message, voice memo, or document to see it arrive directly on your phone!', 'time' => '12:00 PM']
            ]
        ],
        [
            'id' => 'lead_1',
            'name' => 'Zeeshan Tariq',
            'phone' => '923008472910',
            'display' => '+92 300 8472910',
            'city' => 'Lahore',
            'network' => 'Jazz',
            'ownerId' => 'EMP-101',
            'ownerName' => 'Hamza Khan',
            'leadState' => 'ongoing',
            'status' => 'replied',
            'unreadCount' => 0,
            'interest' => 'Nunno Pro',
            'lastActive' => '2 mins ago',
            'notes' => 'Interested in Pro plan. Asked for EasyPaisa account details. Follow up today.',
            'messages' => [
                ['sender' => 'agent', 'agentName' => 'Hamza Khan', 'text' => 'Salam Zeeshan Bhai! Nunno AI Cloud ne Pakistan ke crypto traders ke liye Liquidation Magnet System launch kiya hai jo exact level pehle dikhata hai jahan whales target kar rahi hain. 30% OFF offer available hai!', 'time' => '10:15 AM'],
                ['sender' => 'lead', 'text' => 'Assalam o alaikum bhai, EasyPaisa se payment ho sakti hai? Aur breakout alert kitne symbols pe aata hai?', 'time' => '10:32 AM'],
                ['sender' => 'agent', 'agentName' => 'Hamza Khan', 'text' => 'Ji bilkul Zeeshan Bhai! EasyPaisa, JazzCash aur USDT teenon supported hain. Pro plan mein 12-layer multi-symbol alerts live aate hain.', 'time' => '10:35 AM'],
                ['sender' => 'lead', 'text' => 'Theek hai, mujhe EasyPaisa account number share kar dein, main abhi activate karwana chahta hoon.', 'time' => '10:40 AM']
            ]
        ],
        [
            'id' => 'lead_2',
            'name' => 'Bilal Ahmed',
            'phone' => '923214567890',
            'display' => '+92 321 4567890',
            'city' => 'Karachi',
            'network' => 'Jazz',
            'ownerId' => null,
            'leadState' => 'fresh',
            'status' => 'unread',
            'unreadCount' => 2,
            'interest' => 'Nunno Pro',
            'lastActive' => '14 mins ago',
            'notes' => 'High intent retail trader. Scalping Binance futures.',
            'messages' => [
                ['sender' => 'agent', 'agentName' => 'System Outreach', 'text' => '⚡ FLASH 48-HOUR TRADER PASS: Bilal Bhai, Nunno Pro pe flat 40% discount chal raha hai. EasyPaisa / JazzCash instant accepted: nunno.cloud', 'time' => '09:00 AM'],
                ['sender' => 'lead', 'text' => 'AoA bhai, kya isme Gold ($XAUUSD) ke liquidation wicks bhi detect hote hain?', 'time' => '09:45 AM'],
                ['sender' => 'lead', 'text' => 'Mujhe demo video ya coupon code send karein.', 'time' => '09:46 AM']
            ]
        ],
        [
            'id' => 'lead_3',
            'name' => 'Usman Ali',
            'phone' => '923335123984',
            'display' => '+92 333 5123984',
            'city' => 'Islamabad',
            'network' => 'Ufone',
            'ownerId' => 'EMP-102',
            'ownerName' => 'Bilal Tariq',
            'leadState' => 'ongoing',
            'status' => 'sent',
            'unreadCount' => 0,
            'interest' => 'Nunno Starter',
            'lastActive' => '1 hour ago',
            'notes' => 'Outreach sent. Awaiting response.',
            'messages' => [
                ['sender' => 'agent', 'agentName' => 'Bilal Tariq', 'text' => 'Hello Usman! Are sudden market manipulation wicks hurting your positions? Check Nunno Cloud AI indicator: nunno.cloud', 'time' => '08:30 AM']
            ]
        ],
        [
            'id' => 'lead_4',
            'name' => 'Kashif Mehmood',
            'phone' => '923129876543',
            'display' => '+92 312 9876543',
            'city' => 'Rawalpindi',
            'network' => 'Zong',
            'ownerId' => 'EMP-101',
            'ownerName' => 'Hamza Khan',
            'leadState' => 'paid',
            'status' => 'paid',
            'verificationStatus' => 'verified',
            'verifiedBy' => 'System Administrator',
            'paidDetails' => [
                'plan' => 'Nunno Starter',
                'amount' => 1700,
                'method' => 'JazzCash',
                'trxId' => 'JC-88391024',
                'email' => 'kashif.m@gmail.com',
                'closedBy' => 'Hamza Khan',
                'closedById' => 'EMP-101',
                'closedAt' => '2026-10-23T14:30:00Z'
            ],
            'unreadCount' => 0,
            'interest' => 'Nunno Starter (Paid)',
            'lastActive' => 'Yesterday',
            'notes' => 'Verified client. Enjoys the liquidation magnet alerts.',
            'messages' => [
                ['sender' => 'lead', 'text' => 'Sent Rs. 1,700 via JazzCash. Trx ID: JC-88391024.', 'time' => 'Yesterday'],
                ['sender' => 'agent', 'agentName' => 'Hamza Khan', 'text' => 'Payment received and verified, Kashif Bhai! Your Nunno Starter access is active.', 'time' => 'Yesterday']
            ]
        ],
        [
            'id' => 'lead_5',
            'name' => 'Hamza Sheikh',
            'phone' => '923451234567',
            'display' => '+92 345 1234567',
            'city' => 'Peshawar',
            'network' => 'Telenor',
            'ownerId' => null,
            'leadState' => 'fresh',
            'status' => 'unread',
            'unreadCount' => 1,
            'interest' => 'Nunno Elite',
            'lastActive' => '3 hours ago',
            'notes' => 'Prop firm challenge trader.',
            'messages' => [
                ['sender' => 'lead', 'text' => 'Does this work on Forex prop firms like FTMO?', 'time' => '07:15 AM']
            ]
        ],
        [
            'id' => 'lead_6',
            'name' => 'Waqas Rasheed',
            'phone' => '923027891234',
            'display' => '+92 302 7891234',
            'city' => 'Faisalabad',
            'network' => 'Jazz',
            'ownerId' => 'EMP-103',
            'ownerName' => 'Ayesha Noor',
            'leadState' => 'ongoing',
            'status' => 'replied',
            'unreadCount' => 0,
            'interest' => 'Nunno Pro',
            'lastActive' => '5 hours ago',
            'notes' => 'Asked for demo access.',
            'messages' => [
                ['sender' => 'agent', 'agentName' => 'Ayesha Noor', 'text' => 'Salam Waqas! Sent you the demo breakdown.', 'time' => '05:00 AM']
            ]
        ],
        [
            'id' => 'lead_7',
            'name' => 'Omer Farooq',
            'phone' => '923226789012',
            'display' => '+92 322 6789012',
            'city' => 'Multan',
            'network' => 'Warid',
            'ownerId' => 'EMP-104',
            'ownerName' => 'Zain Ahmed',
            'leadState' => 'useless',
            'status' => 'useless',
            'unreadCount' => 0,
            'interest' => 'Free Tier',
            'lastActive' => '2 days ago',
            'notes' => 'Refused to pay, looking for free pirated indicators.',
            'messages' => [
                ['sender' => 'lead', 'text' => 'Free mein kuch nahi dete aap log?', 'time' => '2 days ago']
            ]
        ],
        [
            'id' => 'lead_8',
            'name' => 'Rizwan Qureshi',
            'phone' => '923348901234',
            'display' => '+92 334 8901234',
            'city' => 'Sialkot',
            'network' => 'Ufone',
            'ownerId' => null,
            'leadState' => 'fresh',
            'status' => 'unread',
            'unreadCount' => 1,
            'interest' => 'Nunno Pro',
            'lastActive' => '6 hours ago',
            'notes' => 'Crypto spot holder.',
            'messages' => [
                ['sender' => 'lead', 'text' => 'Aslam o alikum, discount code chal raha koi?', 'time' => '04:10 AM']
            ]
        ]
    ];
    $leadsFile = $baseDir . '/data/leads.json';
    file_put_contents($leadsFile, json_encode($pristineLeads, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    $nodePath = '/opt/alt/alt-nodejs22/root/usr/bin/node';
    $bridgeDir = $baseDir . '/bridge';
    $token = $conn['token'] ?? (file_exists($tokenFile) ? trim(file_get_contents($tokenFile)) : '');
    $scriptPath = file_exists($bridgeDir . '/dist/index.js') ? ($bridgeDir . '/dist/index.js') : ($bridgeDir . '/server.js');
    $startCmd = "PORT=3210 WHATSAPP_BRIDGE_HOST=127.0.0.1 WHATSAPP_BRIDGE_TOKEN=" . escapeshellarg($token)
        . " WHATSAPP_DATA_DIR=" . escapeshellarg($baseDir . '/data/whatsapp-bridge') . " WHATSAPP_ENGINE=baileys "
        . escapeshellarg($nodePath) . " " . escapeshellarg($scriptPath)
        . " </dev/null >> " . escapeshellarg($logFile) . " 2>&1 &";
    shell_exec($startCmd);
    sleep(2);

    echo json_encode([
        'success' => true,
        'message' => 'CRM restored cleanly to pristine state. Mixed chats wiped. Bridge restarted for clean QR pairing.',
        'leads_count' => count($pristineLeads)
    ], JSON_PRETTY_PRINT);
    exit;
}

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
        . " WHATSAPP_DATA_DIR=" . escapeshellarg($baseDir . '/data/whatsapp-bridge') . " WHATSAPP_ENGINE=baileys "
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
        . " WHATSAPP_DATA_DIR=" . escapeshellarg($baseDir . '/data/whatsapp-bridge') . " WHATSAPP_ENGINE=baileys "
        . escapeshellarg($nodePath) . " " . escapeshellarg($scriptPath)
        . " </dev/null >> " . escapeshellarg($logFile) . " 2>&1 &";
    shell_exec($startCmd);
    sleep(2);
    echo json_encode(['success' => true, 'purged_sessions' => $purged]);
    exit;
}

if (isset($_GET['restart'])) {
    @unlink($baseDir . '/data/whatsapp-bridge/daemon.pid');
    $output = @shell_exec('pgrep -f "run-daemon.sh|dist/index.js|server.js" 2>&1');
    if ($output) {
        $pids = array_filter(array_map('trim', explode("\n", trim($output))), 'is_numeric');
        foreach ($pids as $p) @shell_exec("kill -9 $p 2>&1");
    }
    sleep(1);
    $nodePath = '/opt/alt/alt-nodejs22/root/usr/bin/node';
    $bridgeDir = $baseDir . '/bridge';
    $token = $conn['token'] ?? (file_exists($tokenFile) ? trim(file_get_contents($tokenFile)) : '');
    $scriptPath = file_exists($bridgeDir . '/dist/index.js') ? ($bridgeDir . '/dist/index.js') : ($bridgeDir . '/server.js');
    $daemonScript = $bridgeDir . '/run-daemon.sh';
    if (file_exists($daemonScript)) {
        @chmod($daemonScript, 0755);
        $startCmd = "WHATSAPP_BRIDGE_TOKEN=" . escapeshellarg($token) . " /bin/bash " . escapeshellarg($daemonScript)
            . " </dev/null >> " . escapeshellarg($logFile) . " 2>&1 &";
    } else {
        $startCmd = "PORT=3210 WHATSAPP_BRIDGE_HOST=127.0.0.1 WHATSAPP_BRIDGE_TOKEN=" . escapeshellarg($token)
            . " WHATSAPP_DATA_DIR=" . escapeshellarg($baseDir . '/data/whatsapp-bridge') . " WHATSAPP_ENGINE=baileys "
            . escapeshellarg($nodePath) . " " . escapeshellarg($scriptPath)
            . " </dev/null >> " . escapeshellarg($logFile) . " 2>&1 &";
    }
    shell_exec($startCmd);
    sleep(2);
    echo json_encode(['success' => true, 'restarted' => true]);
    exit;
}

// Allow cleaning up corrupted leaked Askari messages from lead_self
// Allow cleaning up corrupted leaked Askari messages from lead_self and restoring pristine state
if (isset($_GET['clean_self_leak']) || isset($_GET['fix_mixed_chats'])) {
    $leadsFile = $baseDir . '/data/leads.json';
    $cleanedCount = 0;
    $movedCount = 0;
    if (file_exists($leadsFile)) {
        $leads = json_decode(file_get_contents($leadsFile), true);
        if (is_array($leads)) {
            // Find lead_self and askari lead
            $selfIndex = null;
            $askariIndex = null;
            foreach ($leads as $i => $l) {
                if (($l['id'] ?? '') === 'lead_self' || ($l['phone'] ?? '') === '923318036576') {
                    $selfIndex = $i;
                }
                if (($l['phone'] ?? '') === '923046588806' || ($l['id'] ?? '') === 'lead_07ebafd64de2') {
                    $askariIndex = $i;
                }
            }

            if ($selfIndex !== null && isset($leads[$selfIndex]['messages'])) {
                $allSelfMsgs = $leads[$selfIndex]['messages'];
                $cleanSelfMsgs = [];
                $askariMsgs = [];

                foreach ($allSelfMsgs as $m) {
                    $txt = $m['text'] ?? '';
                    if (str_contains($txt, '⚡ This is your personal linked WhatsApp chat')) {
                        $cleanSelfMsgs[] = $m;
                    } else {
                        // All other messages in lead_self are the leaked conversation with Askari
                        $m['phone'] = '923046588806';
                        $askariMsgs[] = $m;
                        $movedCount++;
                    }
                }

                if (empty($cleanSelfMsgs)) {
                    $cleanSelfMsgs = [
                        ['sender' => 'lead', 'text' => '⚡ This is your personal linked WhatsApp chat (+92 331 8036576). Send any message, voice memo, or document to see it arrive directly on your phone!', 'time' => '12:00 PM']
                    ];
                }

                $leads[$selfIndex]['messages'] = $cleanSelfMsgs;
                $leads[$selfIndex]['name'] = 'My WhatsApp (Self Test)';
                $leads[$selfIndex]['phone'] = '923318036576';
                $leads[$selfIndex]['display'] = '+92 331 8036576';
                $leads[$selfIndex]['unreadCount'] = 0;

                // Merge into Askari's lead
                if ($askariIndex !== null) {
                    $existingAskariMsgs = $leads[$askariIndex]['messages'] ?? [];
                    $existingIds = [];
                    foreach ($existingAskariMsgs as $em) {
                        if (!empty($em['waMessageId'])) $existingIds[$em['waMessageId']] = true;
                        if (!empty($em['id'])) $existingIds[$em['id']] = true;
                    }
                    foreach ($askariMsgs as $am) {
                        $mid = $am['waMessageId'] ?? ($am['id'] ?? '');
                        if (empty($mid) || !isset($existingIds[$mid])) {
                            $existingAskariMsgs[] = $am;
                            if (!empty($mid)) $existingIds[$mid] = true;
                        }
                    }
                    $leads[$askariIndex]['messages'] = $existingAskariMsgs;
                    $leads[$askariIndex]['name'] = '+923046588806';
                    $leads[$askariIndex]['phone'] = '923046588806';
                    $leads[$askariIndex]['display'] = '+92 304 6588806';
                }
            }

            // Remove corrupt duplicate leads e.g. lead_1793819274070_923491133010
            $leads = array_values(array_filter($leads, function($l) {
                return !str_contains($l['id'] ?? '', 'lead_1793819274070_');
            }));

            file_put_contents($leadsFile, json_encode($leads, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }
    }

    // Clean inbox.json on both paths
    $inboxFiles = [
        $baseDir . '/data/whatsapp-bridge/inbox.json',
        $baseDir . '/data/whatsapp-bridge/whatsapp-bridge/inbox.json'
    ];
    $inboxRemapped = 0;
    foreach ($inboxFiles as $ibFile) {
        if (file_exists($ibFile)) {
            $ib = json_decode(file_get_contents($ibFile), true);
            if (is_array($ib)) {
                foreach ($ib as &$msg) {
                    $isAskari = ($msg['name'] ?? '') === '923046588806'
                        || ($msg['name'] ?? '') === 'Askari'
                        || (str_contains($msg['text'] ?? '', 'Askari') && ($msg['phone'] ?? '') === '923318036576');
                    if ($isAskari && ($msg['phone'] ?? '') === '923318036576') {
                        $msg['phone'] = '923046588806';
                        $msg['name'] = '923046588806';
                        $inboxRemapped++;
                    }
                }
                unset($msg);
                file_put_contents($ibFile, json_encode($ib, JSON_PRETTY_PRINT));
            }
        }
    }

    echo json_encode([
        'success' => true,
        'moved_messages' => $movedCount,
        'inbox_remapped' => $inboxRemapped,
        'message' => 'Chat mixture cleanly separated! Lead self restored to pristine state, Askari messages isolated.'
    ], JSON_PRETTY_PRINT);
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
        . " WHATSAPP_DATA_DIR=" . escapeshellarg($baseDir . '/data/whatsapp-bridge') . " WHATSAPP_ENGINE=baileys "
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

$auth1 = $baseDir . '/data/whatsapp-bridge/baileys_auth';
$auth2 = $baseDir . '/data/whatsapp-bridge/whatsapp-bridge/baileys_auth';
$credsPreview = null;
foreach ([$auth1 . '/creds.json', $auth2 . '/creds.json'] as $cp) {
    if (file_exists($cp)) {
        $cdata = json_decode(@file_get_contents($cp), true);
        if ($cdata) {
            $credsPreview = [
                'path' => $cp,
                'registered' => $cdata['registered'] ?? false,
                'me' => $cdata['me'] ?? null,
                'filesize' => filesize($cp)
            ];
            break;
        }
    }
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
    'auth_summary' => [
        'auth1_exists' => is_dir($auth1),
        'auth1_creds' => file_exists($auth1 . '/creds.json') ? filesize($auth1 . '/creds.json') : false,
        'auth1_files_count' => is_dir($auth1) ? count(scandir($auth1)) : 0,
        'backup_creds_exists' => file_exists($baseDir . '/data/whatsapp-bridge/creds.json.bak') ? filesize($baseDir . '/data/whatsapp-bridge/creds.json.bak') : false,
        'creds_preview' => $credsPreview,
        'running_processes' => trim((string)shell_exec('ps aux | grep -E "node|index.js|server.js" | grep -v grep 2>&1'))
    ],
    'grep_923' => file_exists($baseDir . '/data/whatsapp-bridge/grep_923.txt') ? file_get_contents($baseDir . '/data/whatsapp-bridge/grep_923.txt') : null,
    'log_tail' => file_exists($logFile) ? array_slice(explode("\n", trim((string)shell_exec('tail -n 60 ' . escapeshellarg($logFile)))), -50) : [],
    'recent_log' => []
], JSON_PRETTY_PRINT);
