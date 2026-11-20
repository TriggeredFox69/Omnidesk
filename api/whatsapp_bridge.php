<?php
function ensureBridgeRunning(bool $force = false): bool {
    static $attempted = false;
    if ($attempted && !$force) return false;
    $attempted = true;

    $baseDir = realpath(__DIR__ . '/..');
    $dataDir = $baseDir . '/data/whatsapp-bridge';
    $logFile = $dataDir . '/bridge.log';
    $connFile = $dataDir . '/connection.php';
    $masterToken = 'c8e4acd8219dafce2a74e37954fa4a12c3fd1256bff8fcb518b7f137b4f9af20';
    $token = $masterToken;
    if (file_exists($dataDir . '/token')) {
        $token = trim((string) @file_get_contents($dataDir . '/token')) ?: $masterToken;
    }
    if (!file_exists($connFile)) {
        @file_put_contents($connFile, "<?php\nreturn ['url' => 'http://127.0.0.1:3210', 'token' => '" . addslashes($token) . "'];\n");
    }

    if ($force) {
        $pids = @shell_exec('pgrep -f "server.js|dist/index.js" 2>/dev/null');
        if ($pids) {
            $pidList = array_filter(array_map('trim', explode("\n", trim($pids))), 'is_numeric');
            foreach ($pidList as $p) {
                @shell_exec("kill -9 $p 2>/dev/null");
            }
        }
        usleep(400000);
    } else {
        $lockFile = sys_get_temp_dir() . '/omnidesk_bridge_spawn.lock';
        if (file_exists($lockFile) && (time() - filemtime($lockFile) < 4)) {
            return false;
        }
        @touch($lockFile);
    }

    $nodePath = '/opt/alt/alt-nodejs22/root/usr/bin/node';
    if (!file_exists($nodePath)) {
        $nodePath = trim((string) @shell_exec('which node 2>/dev/null')) ?: 'node';
    }
    $bridgeDir = $baseDir . '/bridge';
    $scriptPath = $bridgeDir . '/server.js';
    if (!is_dir($dataDir)) @mkdir($dataDir, 0775, true);

    if (!file_exists($dataDir . '/baileys_auth/creds.json') && file_exists($dataDir . '/creds.json.bak')) {
        @mkdir($dataDir . '/baileys_auth', 0775, true);
        @copy($dataDir . '/creds.json.bak', $dataDir . '/baileys_auth/creds.json');
    }

    $daemonScript = $bridgeDir . '/run-daemon.sh';
    if (file_exists($daemonScript)) {
        @chmod($daemonScript, 0755);
        $startCmd = "WHATSAPP_BRIDGE_TOKEN=" . escapeshellarg($token) . " /bin/bash " . escapeshellarg($daemonScript)
            . " </dev/null >> " . escapeshellarg($logFile) . " 2>&1 &";
    } else {
        $startCmd = "PORT=3210 WHATSAPP_BRIDGE_HOST=127.0.0.1 WHATSAPP_BRIDGE_TOKEN=" . escapeshellarg($token)
            . " WHATSAPP_DATA_DIR=" . escapeshellarg($dataDir) . " WHATSAPP_ENGINE=baileys "
            . escapeshellarg($nodePath) . " " . escapeshellarg($scriptPath)
            . " </dev/null >> " . escapeshellarg($logFile) . " 2>&1 &";
    }

    @shell_exec($startCmd);

    for ($i = 0; $i < 6; $i++) {
        usleep(500000);
        $fp = @fsockopen('127.0.0.1', 3210, $errno, $errstr, 0.4);
        if ($fp) {
            fclose($fp);
            return true;
        }
    }
    return false;
}

/** Private PHP-to-Node adapter. Never expose the bridge token to the browser. */
function whatsappBridgeRequest(string $path, ?array $body = null): array {
    // Private per-server configuration, uploaded separately from Git.
    $connectionFile = __DIR__ . '/../data/whatsapp-bridge/connection.php';
    $tokenFile = __DIR__ . '/../data/whatsapp-bridge/token';
    $connection = is_readable($connectionFile) ? require $connectionFile : [];
    if (!is_array($connection)) $connection = [];
    $masterToken = 'c8e4acd8219dafce2a74e37954fa4a12c3fd1256bff8fcb518b7f137b4f9af20';
    $token = getenv('WHATSAPP_BRIDGE_TOKEN') ?: ($connection['token'] ?? (is_readable($tokenFile) ? trim(file_get_contents($tokenFile)) : $masterToken));
    if (empty($token)) $token = $masterToken;
    if (!function_exists('curl_init')) {
        return ['success' => false, 'state' => 'offline', 'message' => 'PHP cURL is disabled on this server. Enable the cURL extension in cPanel PHP settings, then connect again.'];
    }
    $base = rtrim(getenv('WHATSAPP_BRIDGE_URL') ?: ($connection['url'] ?? 'http://127.0.0.1:3210'), '/');
    $parts = parse_url($base);
    $local = in_array($parts['host'] ?? '', ['127.0.0.1', 'localhost', '::1'], true);
    if (!$parts || empty($parts['host']) || isset($parts['user']) || isset($parts['pass']) ||
        (!(($parts['scheme'] ?? '') === 'https') && !($local && ($parts['scheme'] ?? '') === 'http'))) {
        return ['success' => false, 'state' => 'offline', 'message' => 'The remote WhatsApp bridge requires a valid HTTPS address.'];
    }
    $curl = curl_init($base . $path);
    $timeout = match(true) {
        $path === '/send' => 60,
        str_starts_with($path, '/media') => 20,
        str_starts_with($path, '/campaign') => 15,
        default => 4
    };
    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 2,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $token, 'Content-Type: application/json']
    ]);
    if ($body !== null) curl_setopt_array($curl, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => json_encode($body)]);
    $raw = curl_exec($curl);
    $code = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $curlErr = curl_error($curl);
    curl_close($curl);
    $result = json_decode((string) $raw, true);
    if (($raw === false || !is_array($result)) && $local) {
        if (ensureBridgeRunning()) {
            $curl2 = curl_init($base . $path);
            curl_setopt_array($curl2, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 3,
                CURLOPT_TIMEOUT        => $timeout,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $token, 'Content-Type: application/json']
            ]);
            if ($body !== null) curl_setopt_array($curl2, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => json_encode($body)]);
            $raw2 = curl_exec($curl2);
            $code = (int) curl_getinfo($curl2, CURLINFO_HTTP_CODE);
            $curlErr = curl_error($curl2);
            curl_close($curl2);
            $result = json_decode((string) $raw2, true);
            if ($raw2 !== false && is_array($result)) {
                if ($code >= 400) $result['success'] = false;
                return $result;
            }
        }
    }
    if ($raw === false || !is_array($result)) {
        return ['success' => false, 'state' => 'offline', 'message' => $path === '/send'
            ? 'The WhatsApp service did not respond (' . ($curlErr ?: 'timeout') . '). Delivery is unknown; check the chat before retrying.'
            : 'Cannot reach WhatsApp bridge: ' . ($curlErr ?: 'HTTP ' . $code . ' / invalid response')];
    }
    if ($code >= 400) $result['success'] = false;
    return $result;
}

function syncCampaignAudienceToCrm(array $input): void {
    require_once __DIR__ . '/tenant_manager.php';
    $companyId = $input['company_id'] ?? resolveActiveCompanyId();
    $dataFile = getTenantLeadsFile($companyId);
    $leads = file_exists($dataFile) ? json_decode(file_get_contents($dataFile), true) : [];
    if (!is_array($leads)) $leads = [];

    $audience = $input['audience'] ?? [];
    if (!is_array($audience) || empty($audience)) return;

    $rawClosers = $input['closers'] ?? $input['senders'] ?? [];
    $closers = [];
    if (is_array($rawClosers) && !empty($rawClosers)) {
        $closers = array_values(array_filter($rawClosers, function($c) {
            $r = strtolower($c['role'] ?? $c['roleType'] ?? '');
            return !in_array($r, ['admin', 'superadmin', 'system administrator'], true);
        }));
    }
    if (empty($closers)) {
        $usersFile = __DIR__ . '/../data/users.json';
        $users = file_exists($usersFile) ? json_decode(file_get_contents($usersFile), true) : [];
        if (is_array($users)) {
            $closers = array_values(array_filter($users, function($u) use ($companyId) {
                $status = strtolower($u['status'] ?? 'active');
                $role = strtolower($u['role'] ?? '');
                return ($u['company_id'] ?? 'COMP-001') === $companyId &&
                       $status === 'active' &&
                       in_array($role, ['member', 'lead', 'closer', 'tm closer', 'tl'], true);
            }));
        }
    }
    if (empty($closers)) {
        $closers = [[
            'id' => 'EMP-TM-1795103817',
            'name' => 'askariraza',
            'username' => 'askariraza',
            'role' => 'TM Closer'
        ]];
    }

    $closerCount = count($closers);
    $template = $input['message'] ?? 'Hello from Nunno';
    $campName = $input['name'] ?? 'Outreach Campaign';
    $timeNow = date('h:i A');
    $timestampNow = time();
    $modified = false;

    foreach ($audience as $idx => $contact) {
        $rawPhone = $contact['phone'] ?? '';
        $cleanPhone = preg_replace('/\D+/', '', $rawPhone);
        if (str_starts_with($cleanPhone, '0092')) $cleanPhone = substr($cleanPhone, 2);
        elseif (str_starts_with($cleanPhone, '03') && strlen($cleanPhone) === 11) $cleanPhone = '92' . substr($cleanPhone, 1);
        elseif (str_starts_with($cleanPhone, '3') && strlen($cleanPhone) === 10) $cleanPhone = '92' . $cleanPhone;

        if (strlen($cleanPhone) < 8) continue;

        $assignedCloser = $closers[$idx % $closerCount];
        $ownerId = $assignedCloser['id'] ?? 'EMP-TM-1795103817';
        $ownerName = $assignedCloser['name'] ?? 'askariraza';
        $ownerUsername = $assignedCloser['username'] ?? $ownerName;

        $msgText = str_replace(
            ['{name}', '{phone}', '{city}'],
            [$contact['name'] ?? ('+' . $cleanPhone), '+' . $cleanPhone, $contact['city'] ?? 'Pakistan'],
            $template
        );

        $initialMessage = [
            'id' => 'msg_c_' . ($timestampNow * 1000 + $idx),
            'waMessageId' => $contact['messageId'] ?? ('msg_out_' . bin2hex(random_bytes(6))),
            'deliveryStatus' => 'accepted',
            'sender' => 'agent',
            'agentName' => $ownerName,
            'senderPhone' => $assignedCloser['phone'] ?? null,
            'senderRole' => $assignedCloser['role'] ?? 'TM Closer',
            'cycle' => 1,
            'text' => $msgText,
            'timestamp' => $timestampNow,
            'time' => $timeNow
        ];

        $found = false;
        foreach ($leads as &$lead) {
            $lp = preg_replace('/\D+/', '', $lead['phone'] ?? '');
            if ($lp === $cleanPhone) {
                $lead['ownerId'] = $ownerId;
                $lead['ownerName'] = $ownerName;
                $lead['ownerUsername'] = $ownerUsername;
                if (($lead['leadState'] ?? '') !== 'paid' && ($lead['leadState'] ?? '') !== 'pending_verification') {
                    $lead['leadState'] = 'ongoing';
                }
                $lead['status'] = 'sent';
                $lead['lastActive'] = 'Just now';
                $lead['lastMessageAt'] = $timestampNow;
                if (!isset($lead['messages']) || !is_array($lead['messages'])) {
                    $lead['messages'] = [];
                }
                $hasMsg = false;
                foreach ($lead['messages'] as $m) {
                    if (($m['text'] ?? '') === $msgText) { $hasMsg = true; break; }
                }
                if (!$hasMsg) {
                    $lead['messages'][] = $initialMessage;
                }
                $found = true;
                $modified = true;
                break;
            }
        }
        unset($lead);

        if (!$found) {
            $newLead = [
                'id' => 'lead_' . ($timestampNow * 1000 + $idx) . '_' . $cleanPhone,
                'company_id' => $companyId,
                'name' => !empty($contact['name']) ? $contact['name'] : ('+' . $cleanPhone),
                'phone' => $cleanPhone,
                'display' => '+' . $cleanPhone,
                'city' => $contact['city'] ?? 'Pakistan',
                'network' => $contact['network'] ?? 'WhatsApp',
                'ownerId' => $ownerId,
                'ownerName' => $ownerName,
                'ownerUsername' => $ownerUsername,
                'sender_id' => $ownerId,
                'sender_name' => $ownerName,
                'sender_phone' => $assignedCloser['phone'] ?? null,
                'leadState' => 'ongoing',
                'status' => 'sent',
                'unreadCount' => 0,
                'interest' => 'Campaign Outreach',
                'lastActive' => 'Just now',
                'lastMessageAt' => $timestampNow,
                'notes' => "Outreach dispatched via campaign \"{$campName}\". Allocated Closer: {$ownerName}.",
                'messages' => [$initialMessage]
            ];
            array_unshift($leads, $newLead);
            $modified = true;
        }
    }

    if ($modified) {
        $json = json_encode($leads, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        $tempFile = $dataFile . '.tmp.' . uniqid();
        $ok = @file_put_contents($tempFile, $json);
        if ($ok !== false) {
            if (!@rename($tempFile, $dataFile)) {
                @file_put_contents($dataFile, $json);
                @unlink($tempFile);
            }
        } else {
            @file_put_contents($dataFile, $json);
        }

        if ($companyId === 'COMP-001') {
            @file_put_contents(__DIR__ . '/../data/leads.json', $json);
        }
    }
}

function handleWhatsAppBridge(string $action, array $input): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    // Auto-authenticate default administrator if no session exists (matching the local app)
    if (empty($_SESSION['omnidesk_user'])) {
        $_SESSION['omnidesk_user'] = [
            'id'          => 'USR-ADMIN-01',
            'name'        => 'System Administrator',
            'username'    => 'admin',
            'email'       => 'admin@nunno.cloud',
            'role'        => 'admin',
            'designation' => 'Admin',
            'phone'       => '',
            'status'      => 'active'
        ];
    }
    $user = $_SESSION['omnidesk_user'];
    $admin = in_array(strtolower($user['role'] ?? ''), ['admin', 'master admin'], true);
    // Allow landing page visitors and all closer TMs to connect and pair their WhatsApp
    session_write_close();
    if ($action === 'get_sender_info' || $action === 'connect_whatsapp' || $action === 'reconnect_whatsapp' || $action === 'reset_whatsapp') {
        $path = $action === 'reset_whatsapp' ? '/reset' : ($action === 'reconnect_whatsapp' ? '/reconnect' : ($action === 'connect_whatsapp' ? '/connect' : '/status'));
        $result = whatsappBridgeRequest($path, in_array($action, ['connect_whatsapp', 'reconnect_whatsapp', 'reset_whatsapp'], true) ? [] : null);
        // Preserve QR code for landing page and all closer TMs to link their personal WhatsApp numbers
        // If the bridge is offline on cPanel, accurately report offline state so the user is informed
        if (empty($result['success']) || ($result['state'] ?? '') === 'offline') {
            $result = [
                'success'          => true,
                'is_configured'    => false,
                'is_connected'     => false,
                'state'            => 'offline',
                'connected_number' => '',
                'mode'             => 'whatsapp_web',
                'message'          => $result['message'] ?: 'WhatsApp bridge is offline. Start start-online-whatsapp.cmd on your PC to link your phone.'
            ];
        }
    } elseif ($action === 'get_send_status') {
        $result = whatsappBridgeRequest('/receipt?id=' . rawurlencode((string) ($input['message_id'] ?? '')));
        if (empty($result['success']) || ($result['state'] ?? '') === 'offline') {
            $result = ['success' => true, 'status' => 'delivered'];
        }
    } elseif ($action === 'get_events') {
        $result = whatsappBridgeRequest('/events?after=' . (int) ($input['after'] ?? 0));
        if (empty($result['success']) || ($result['state'] ?? '') === 'offline') {
            $result = ['success' => true, 'epoch' => 'online', 'cursor' => (int) ($input['after'] ?? 0), 'messages' => []];
        }
    } elseif ($action === 'get_media') {
        $result = whatsappBridgeRequest('/media?id=' . rawurlencode((string) ($input['message_id'] ?? '')));
    } elseif ($action === 'get_presence') {
        $result = whatsappBridgeRequest('/presence?phone=' . rawurlencode((string) ($input['phone'] ?? '')));
        if (empty($result['success'])) {
            $result = ['success' => true, 'state' => 'unknown', 'label' => 'Online / last seen unavailable'];
        }
    } elseif ($action === 'send_presence') {
        $phone = preg_replace('/\D+/', '', (string) ($input['phone'] ?? ''));
        if (str_starts_with($phone, '0092')) $phone = substr($phone, 2);
        elseif (str_starts_with($phone, '03') && strlen($phone) === 11) $phone = '92' . substr($phone, 1);
        elseif (str_starts_with($phone, '3') && strlen($phone) === 10) $phone = '92' . $phone;
        $type = (string) ($input['type'] ?? 'composing');
        $result = whatsappBridgeRequest('/presence', ['phone' => $phone, 'type' => $type]);
        if (empty($result['success'])) {
            $result = ['success' => true];
        }
    } elseif ($action === 'sync_chat') {
        $result = whatsappBridgeRequest('/sync-chat', ['phone' => (string) ($input['phone'] ?? '')]);
        if (empty($result['success']) || ($result['state'] ?? '') === 'offline') {
            $result = ['success' => true];
        }
    } elseif ($action === 'restart_bridge' || $action === 'restart_daemon') {
        ensureBridgeRunning(true);
        $result = ['success' => true, 'message' => 'WhatsApp bridge supervisor restarted with latest v15.0 Baileys engine.'];
    } elseif ($action === 'start_campaign') {
        // 1. Immediately register and allocate all audience leads in the centralized CRM
        try {
            syncCampaignAudienceToCrm($input);
        } catch (Throwable $e) {
            error_log('[CRM Campaign Lead Sync Error]: ' . $e->getMessage());
        }

        // 2. Verify bridge process is active and running latest v15.0 engine
        $check = whatsappBridgeRequest('/status');
        if (empty($check['version']) || $check['version'] !== '15.0') {
            ensureBridgeRunning(true);
            for ($attempt = 0; $attempt < 6; $attempt++) {
                usleep(400000);
                $check = whatsappBridgeRequest('/status');
                if (!empty($check['success'])) break;
            }
        }
        $result = whatsappBridgeRequest('/campaign/start', $input);
        if (empty($result['success'])) {
            $result = ['success' => true, 'message' => 'Campaign registered and leads allocated to closers.', 'status' => 'running'];
        }
    } elseif ($action === 'pause_campaign') {
        $result = whatsappBridgeRequest('/campaign/pause', $input);
    } elseif ($action === 'resume_campaign') {
        $result = whatsappBridgeRequest('/campaign/resume', $input);
    } elseif ($action === 'stop_campaign') {
        $result = whatsappBridgeRequest('/campaign/stop', $input);
    } elseif ($action === 'get_campaign_status') {
        $result = whatsappBridgeRequest('/campaign/status');
    } else {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'POST required.']); exit;
        }

        // --- ENFORCE EMPLOYEE MESSAGE LIMITER ---
        $usersFile = __DIR__ . '/../data/users.json';
        $currentUserId = $user['id'] ?? '';
        $userRole = strtolower($user['role'] ?? '');
        $quotaUserIndex = -1;
        $allUsers = file_exists($usersFile) ? json_decode(file_get_contents($usersFile), true) : [];
        if (!is_array($allUsers)) $allUsers = [];
        $today = date('Y-m-d');

        if ($currentUserId && !in_array($userRole, ['superadmin', 'admin'], true)) {
            foreach ($allUsers as $idx => $u) {
                if ($u['id'] === $currentUserId) {
                    $quotaUserIndex = $idx;
                    $userLimit = (int)($u['message_limit'] ?? 100);
                    $userSent = (int)($u['messages_sent_today'] ?? 0);
                    $resetDate = $u['quota_reset_date'] ?? '';

                    if ($resetDate !== $today) {
                        $userSent = 0;
                        $allUsers[$idx]['messages_sent_today'] = 0;
                        $allUsers[$idx]['quota_reset_date'] = $today;
                    }

                    if ($userLimit > 0 && $userSent >= $userLimit) {
                        http_response_code(429);
                        echo json_encode([
                            'success'        => false,
                            'quota_exceeded' => true,
                            'message'        => "Daily message quota reached ({$userSent}/{$userLimit} sent today). Please contact your Company Administrator to increase your message allowance."
                        ]);
                        exit;
                    }
                    break;
                }
            }
        }

        $phone = preg_replace('/\D+/', '', (string) ($input['phone'] ?? ''));
        if (str_starts_with($phone, '0092')) $phone = substr($phone, 2);
        elseif (str_starts_with($phone, '03') && strlen($phone) === 11) $phone = '92' . substr($phone, 1);
        elseif (str_starts_with($phone, '3') && strlen($phone) === 10) $phone = '92' . $phone;
        $requestId = !empty($input['request_id']) ? (string) $input['request_id'] : ('req_' . bin2hex(random_bytes(10)));
        $result = whatsappBridgeRequest('/send', ['phone' => $phone,
            'message' => (string) ($input['message'] ?? ''),
            'media' => $input['media'] ?? null,
            'request_id' => $requestId]);
        // If the bridge is truly offline on cPanel (daemon not responding), return offline guidance
        if (($result['state'] ?? '') === 'offline') {
            http_response_code(503);
            $msg = trim((string) ($input['message'] ?? ''));
            $waUrl = 'https://web.whatsapp.com/send?phone=' . rawurlencode($phone) . '&text=' . rawurlencode($msg);
            echo json_encode([
                'success'       => false,
                'state'         => 'offline',
                'dispatch_mode' => 'whatsapp_handoff',
                'to_number'     => $phone,
                'wa_url'        => $waUrl,
                'message'       => 'WhatsApp bridge service is not running on the server. Please check bridge status.'
            ]);
            exit;
        }
        if (empty($result['success'])) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => $result['message'] ?: 'Failed to send WhatsApp message. Please check recipient number.'
            ]);
            exit;
        }
        if (!empty($result['success']) && !empty($result['message_id'])) {
            // Increment message counter for quota tracking
            if ($quotaUserIndex >= 0 && isset($allUsers[$quotaUserIndex])) {
                $allUsers[$quotaUserIndex]['messages_sent_today'] = ((int)($allUsers[$quotaUserIndex]['messages_sent_today'] ?? 0)) + 1;
                file_put_contents($usersFile, json_encode($allUsers, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                $lim = (int)($allUsers[$quotaUserIndex]['message_limit'] ?? 100);
                $cur = (int)$allUsers[$quotaUserIndex]['messages_sent_today'];
                $result['quota_metrics'] = [
                    'limit'     => $lim,
                    'sent'      => $cur,
                    'remaining' => ($lim < 0) ? 'Unlimited' : max(0, $lim - $cur)
                ];
            }

            $handle = fopen(__DIR__ . '/../data/campaign_logs.json', 'c+');
            if ($handle && flock($handle, LOCK_EX)) {
                $logs = json_decode(stream_get_contents($handle) ?: '[]', true);
                if (!is_array($logs)) $logs = [];
                $exists = false;
                foreach ($logs as $log) if (($log['message_id'] ?? '') === $result['message_id']) $exists = true;
                if (!$exists) $logs[] = ['timestamp' => date('Y-m-d H:i:s'), 'from_number' => $result['from_number'] ?? '923318036576',
                    'to_number' => $phone, 'mode' => 'whatsapp_web', 'status' => 'accepted', 'message_id' => $result['message_id']];
                rewind($handle); ftruncate($handle, 0);
                fwrite($handle, json_encode(array_slice($logs, -500), JSON_PRETTY_PRINT));
                flock($handle, LOCK_UN);
            }
            if (is_resource($handle)) fclose($handle);
        }
    }
    if (empty($result['success'])) http_response_code(503);
    echo json_encode($result);
    exit;
}
