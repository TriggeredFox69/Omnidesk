<?php
// Bump together with `version` in bridge/service.js. After a deploy, the first
// request that sees the bridge still running the OLD code restarts it once, so the
// person updating cPanel never has to restart anything by hand.
const EXPECTED_BRIDGE_VERSION = '18.7';

function ensureBridgeCurrent(?array $status = null): void {
    static $checked = false;
    if ($checked) return;
    $checked = true;
    // A remote bridge cannot be restarted by this cPanel account.
    $conn = @include __DIR__ . '/../data/whatsapp-bridge/connection.php';
    $host = is_array($conn) ? (parse_url((string) ($conn['url'] ?? ''), PHP_URL_HOST) ?: '127.0.0.1') : '127.0.0.1';
    if (!in_array($host, ['127.0.0.1', 'localhost', '::1'], true)) return;
    if (!bridgePortOpen()) {
        ensureBridgeRunning(false);
        return;
    }
    $status = $status ?? whatsappBridgeRequest('/status');
    $unresponsive = empty($status['success']);
    $running = (string) ($status['version'] ?? '');
    $health = __DIR__ . '/../data/whatsapp-bridge/health.json';
    if (!$unresponsive) {
        if (is_file($health)) @unlink($health);
        if ($running === EXPECTED_BRIDGE_VERSION) return;
    } else {
        // One slow answer (a busy campaign) is not a dead bridge: restarting would drop
        // every linked phone. Only restart if it has stayed unresponsive for 90 seconds.
        $h = json_decode((string) @file_get_contents($health), true) ?: [];
        if (empty($h['first_fail'])) { @file_put_contents($health, json_encode(['first_fail' => time()])); return; }
        if (time() - (int) $h['first_fail'] < 90) return;
        @unlink($health);
    }
    $lock = __DIR__ . '/../data/whatsapp-bridge/upgrade.lock';
    if (is_file($lock) && time() - filemtime($lock) < 600) return;   // at most once per 10 minutes
    $last = json_decode((string) @file_get_contents($lock), true) ?: [];
    if (!$unresponsive) {
        // Already restarted twice and it still reports the same version: the deployed
        // bridge code itself is that version (bridge.bundle.js not rebuilt). Restarting
        // again would only disconnect everyone every 10 minutes, so stop and log it.
        $tries = (($last['from'] ?? null) === $running && ($last['expected'] ?? null) === EXPECTED_BRIDGE_VERSION) ? (int) ($last['tries'] ?? 0) : 0;
        if ($tries >= 2) {
            if (time() - filemtime($lock) > 3600) { @touch($lock); error_log('[Bridge] Deployed bridge reports ' . $running . ' but PHP expects ' . EXPECTED_BRIDGE_VERSION . '. Rebuild bridge/bridge.bundle.js (cd bridge && npm run build) and deploy again.'); }
            return;
        }
        @file_put_contents($lock, json_encode(['from' => $running, 'expected' => EXPECTED_BRIDGE_VERSION, 'tries' => $tries + 1]));
    } else {
        @touch($lock);
    }
    error_log($unresponsive
        ? '[Bridge] Local bridge accepted a connection but did not answer its health check; restarting it.'
        : '[Bridge] Running ' . ($status['version'] ?? '?') . ', expected ' . EXPECTED_BRIDGE_VERSION . ': restarting with the new code.');
    ensureBridgeRunning(true);
}

require_once __DIR__ . '/session_user.php';

/** users.json as id => user. */
function chatlyUsersById(): array {
    static $map = null;
    if ($map !== null) return $map;
    $map = [];
    $users = json_decode((string) @file_get_contents(__DIR__ . '/../data/users.json'), true);
    foreach ((is_array($users) ? $users : []) as $u) if (!empty($u['id'])) $map[(string) $u['id']] = $u;
    return $map;
}

/** A company's leads file (Nunno falls back to the legacy data/leads.json). */
function chatlyCompanyLeads(string $companyId): array {
    $id = preg_replace('/[^A-Za-z0-9_-]/', '', $companyId) ?: 'COMP-001';
    $file = __DIR__ . '/../data/tenants/' . $id . '/leads.json';
    if (!is_file($file) && $id === 'COMP-001') $file = __DIR__ . '/../data/leads.json';
    $leads = json_decode((string) @file_get_contents($file), true);
    return is_array($leads) ? $leads : [];
}

function chatlyLast9(string $p): string {
    $d = preg_replace('/\D+/', '', $p);
    return strlen($d) >= 9 ? substr($d, -9) : $d;
}

/**
 * Live WhatsApp events a user may see. The bridge serves one feed for the whole
 * platform; each page only gets its own company's conversations, and a closer only
 * their own: messages from their linked WhatsApp, or on leads they own.
 */
function chatlyFilterEvents(array $messages, array $user): array {
    if (chatlyIsSuperAdmin($user)) return $messages;
    $company = chatlyUserCompany($user);
    $isAdmin = chatlyIsCompanyAdmin($user);
    $myId = (string) ($user['id'] ?? '');
    $myUsername = strtolower((string) ($user['username'] ?? ''));
    $users = chatlyUsersById();
    $phones = [];
    foreach (chatlyCompanyLeads($company) as $l) {
        if (!$isAdmin) {
            $owner = (string) ($l['ownerId'] ?? ($l['assignedOwnerId'] ?? ''));
            $ownerUser = strtolower((string) ($l['ownerUsername'] ?? ''));
            if ($owner !== $myId && !($myUsername && $ownerUser === $myUsername)) continue;
        }
        $k = chatlyLast9((string) ($l['phone'] ?? ''));
        if ($k !== '') $phones[$k] = true;
    }
    $out = [];
    foreach ($messages as $m) {
        $via = (string) ($m['viaUserId'] ?? '');
        if ($via !== '') {
            $viaCompany = chatlyUserCompany($users[$via] ?? ['company_id' => '']);
            if ($viaCompany !== $company) continue;
            if (!$isAdmin && $via !== $myId && !isset($phones[chatlyLast9((string) ($m['phone'] ?? ''))])) continue;
            $out[] = $m;
            continue;
        }
        if (isset($phones[chatlyLast9((string) ($m['phone'] ?? ''))])) $out[] = $m;
    }
    return $out;
}

/** Active TMs/TLs of a company (the only people a campaign may send from). */
function chatlyCompanySenderIds(string $companyId): array {
    $ids = [];
    foreach (chatlyUsersById() as $id => $u) {
        $role = strtolower((string) ($u['role'] ?? 'member'));
        if (chatlyUserCompany($u) === $companyId && strtolower((string) ($u['status'] ?? 'active')) === 'active' && in_array($role, ['member', 'lead'], true)) $ids[$id] = true;
    }
    return $ids;
}

// Which linked WhatsApp shows / receives "typing…" and "recording audio…" for a chat:
// a closer always uses their own phone; an admin may name the chat owner's phone if it
// belongs to their company. '' means the company's main number.
function chatlyPresenceDevice(array $user, bool $admin, string $companyId, array $input): string {
    if (!$admin) return preg_replace('/[^A-Za-z0-9_-]/', '', (string) ($user['id'] ?? ''));
    $owner = preg_replace('/[^A-Za-z0-9_-]/', '', (string) ($input['owner_id'] ?? ''));
    return ($owner !== '' && isset(chatlyCompanySenderIds($companyId)[$owner])) ? $owner : '';
}

function chatlyCompanyName(string $companyId): string {
    $companies = json_decode((string) @file_get_contents(__DIR__ . '/../data/companies.json'), true);
    foreach ((is_array($companies) ? $companies : []) as $c) if (($c['id'] ?? '') === $companyId) return (string) ($c['name'] ?? $companyId);
    return $companyId;
}

function bridgePortOpen(): bool {
    $fp = @fsockopen('127.0.0.1', 3210, $errno, $errstr, 0.5);
    if ($fp) { fclose($fp); return true; }
    return false;
}

function ensureBridgeRunning(bool $force = false): bool {
    static $attempted = false;
    if ($attempted && !$force) return false;
    $attempted = true;
    // A slow bridge is still a running bridge. Starting another copy used to pile up
    // duplicate processes (cPanel then killed them all) and made two copies of the
    // same WhatsApp login kick each other off.
    if (!$force && bridgePortOpen()) return true;

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
        // Stop only THIS bridge (its supervisor and its node process), never every "server.js" on the account.
        @unlink($dataDir . '/daemon.pid');
        $pids = @shell_exec('pgrep -f ' . escapeshellarg($baseDir . '/bridge/') . ' 2>/dev/null');
        if ($pids) {
            $pidList = array_filter(array_map('trim', explode("\n", trim($pids))), 'is_numeric');
            // Ask nicely first: the bridge saves its WhatsApp keys on SIGTERM. A hard
            // kill mid-write corrupts sessions ("Waiting for this message").
            foreach ($pidList as $p) @shell_exec("kill -TERM $p 2>/dev/null");
            for ($i = 0; $i < 20 && bridgePortOpen(); $i++) usleep(250000);
            foreach ($pidList as $p) @shell_exec("kill -9 $p 2>/dev/null");
        }
        usleep(400000);
    } else {
        $lockFile = sys_get_temp_dir() . '/omnidesk_bridge_spawn.lock';
        if (file_exists($lockFile) && (time() - filemtime($lockFile) < 30)) {
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
        $path === '/send', $path === '/device/send' => 60,
        $path === '/device/adopt' => 20,
        $path === '/device/pair' => 25,   // may wait for the session to be ready for a login
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
    $curlErrNo = curl_errno($curl);
    curl_close($curl);
    $result = json_decode((string) $raw, true);
    // Only restart when nothing is listening (refused / could not connect). A timeout means busy, not dead.
    if (($raw === false || !is_array($result)) && $local && in_array($curlErrNo, [7, 52], true) && !bridgePortOpen()) {
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

function resolveBridgeSpintax(string $text): string {
    if ($text === '') return '';
    return preg_replace_callback('/\{([^{}]+)\}/', function($m) {
        $contents = $m[1];
        $lower = strtolower(trim($contents));
        if (in_array($lower, ['name', 'phone', 'city', 'plan', 'price', 'coupon', 'link', 'company'], true)) {
            return $m[0];
        }
        if (str_contains($contents, '|')) {
            $parts = explode('|', $contents);
            return trim($parts[array_rand($parts)]);
        }
        return $contents;
    }, $text);
}

function personalizeBridgeMessage(string $template, array $contact, string $companyName = 'Nunno Official'): string {
    if ($template === '') return '';
    $cleanPhone = preg_replace('/\D+/', '', $contact['phone'] ?? '');
    $cName = !empty($contact['name']) && !str_starts_with($contact['name'], '+') ? $contact['name'] : '';
    $cPhone = !empty($contact['display']) ? $contact['display'] : ('+' . $cleanPhone);
    $cCity = $contact['city'] ?? 'Pakistan';

    $msg = preg_replace_callback('/\{name(?:\|([^{}]+))?\}/i', function($m) use ($cName, $cPhone) {
        if (!empty($cName)) return $cName;
        if (!empty($m[1])) {
            $parts = explode('|', $m[1]);
            return trim($parts[array_rand($parts)]);
        }
        return $cPhone;
    }, $template);

    $msg = resolveBridgeSpintax($msg);
    $msg = str_ireplace(
        ['{name}', '{phone}', '{city}', '{company}', '{plan}', '{price}', '{coupon}', '{link}'],
        [$cName ?: $cPhone, $cPhone, $cCity, $companyName, 'Exclusive Business Plan', 'Rs. 5,000', 'NUNNO30', 'https://chatly.pk'],
        $msg
    );
    return $msg;
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
    $campName = $input['name'] ?? 'Outreach Campaign';
    $timestampNow = time();
    $modified = false;

    foreach ($audience as $idx => $contact) {
        $rawPhone = $contact['phone'] ?? '';
        $cleanPhone = preg_replace('/\D+/', '', $rawPhone);
        if (str_starts_with($cleanPhone, '0092')) $cleanPhone = substr($cleanPhone, 2);
        elseif (str_starts_with($cleanPhone, '03') && strlen($cleanPhone) === 11) $cleanPhone = '92' . substr($cleanPhone, 1);
        elseif (str_starts_with($cleanPhone, '3') && strlen($cleanPhone) === 10) $cleanPhone = '92' . $cleanPhone;

        if (strlen($cleanPhone) < 8) continue;

        $assignedCloser = !empty($input['closer_per_contact']) ? ($closers[$idx] ?? $closers[0]) : $closers[$idx % $closerCount];
        $ownerId = $assignedCloser['id'] ?? 'EMP-TM-1795103817';
        $ownerName = $assignedCloser['name'] ?? 'askariraza';
        $ownerUsername = $assignedCloser['username'] ?? $ownerName;

        $found = false;
        foreach ($leads as &$lead) {
            $lp = preg_replace('/\D+/', '', $lead['phone'] ?? '');
            if ($lp === $cleanPhone) {
                $lead['ownerId'] = $ownerId;
                $lead['ownerName'] = $ownerName;
                $lead['ownerUsername'] = $ownerUsername;
                if (!isset($lead['leadState']) || $lead['leadState'] === 'fresh') {
                    $lead['leadState'] = 'fresh';
                }
                if (!isset($lead['messages']) || !is_array($lead['messages'])) {
                    $lead['messages'] = [];
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
                'leadState' => 'fresh',
                'status' => 'queued',
                'unreadCount' => 0,
                'interest' => 'Campaign Outreach',
                'lastActive' => 'Just now',
                'lastMessageAt' => $timestampNow,
                'notes' => "Queued for outreach via campaign \"{$campName}\". Allocated Closer: {$ownerName}.",
                'messages' => []
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
    if (in_array($action, ['campaign_device_status', 'campaign_device_connect', 'campaign_device_reset', 'campaign_device_pair', 'list_campaign_devices'], true)) {
        handleCampaignDevice($action, $input);
    }
    if (in_array($action, ['login_qr_start', 'login_qr_status', 'login_qr_reset', 'login_qr_pair'], true)) {
        handleLoginQr($action, $input);
    }
    // Everything below needs a real sign-in. (Visitors used to get an automatic
    // "System Administrator" here, which let anyone read every company's chats.)
    $user = chatlySessionUser();
    if (!$user) {
        session_write_close();
        if ($action === 'get_sender_info') {
            $s = whatsappBridgeRequest('/status');
            echo json_encode(['success' => true, 'mode' => 'whatsapp_web', 'state' => $s['state'] ?? 'offline',
                'is_connected' => !empty($s['is_connected']), 'is_configured' => !empty($s['is_connected']),
                'connected_number' => '', 'qr' => null, 'message' => 'Sign in to manage WhatsApp.']);
            exit;
        }
        http_response_code(401);
        echo json_encode(['success' => false, 'authenticated' => false, 'message' => 'Please sign in.']);
        exit;
    }
    $admin = chatlyIsCompanyAdmin($user) || chatlyIsSuperAdmin($user);
    $myCompany = chatlyUserCompany($user);
    // Campaigns always run for the signed-in admin's own company (super admin may pick one).
    $campaignCompany = (chatlyIsSuperAdmin($user) && !empty($input['company_id']))
        ? (preg_replace('/[^A-Za-z0-9_-]/', '', (string) $input['company_id']) ?: $myCompany)
        : $myCompany;
    if (in_array($action, ['start_campaign', 'pause_campaign', 'resume_campaign', 'stop_campaign', 'clear_campaign', 'get_campaign_status'], true)) {
        $input['company_id'] = $campaignCompany;
        $input['company_name'] = chatlyCompanyName($campaignCompany);
        if ($action !== 'get_campaign_status' && !$admin) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Only company administrators can manage campaigns.']);
            exit;
        }
    }
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
        // Typing / recording states come from the WhatsApps this user chats through: a closer's
        // own linked phone, or (for an admin) the company's linked phones, plus the main number.
        $presenceDevices = $admin ? array_keys(chatlyCompanySenderIds($myCompany)) : [(string) ($user['id'] ?? '')];
        $presenceDevices = array_slice(array_filter(array_map(fn($d) => preg_replace('/[^A-Za-z0-9_-]/', '', (string) $d), $presenceDevices)), 0, 500);
        $result = whatsappBridgeRequest('/events?after=' . (int) ($input['after'] ?? 0) . '&devices=' . rawurlencode(implode(',', $presenceDevices)));
        if (empty($result['success']) || ($result['state'] ?? '') === 'offline') {
            $result = ['success' => true, 'epoch' => 'online', 'cursor' => (int) ($input['after'] ?? 0), 'messages' => []];
        }
        $result['messages'] = chatlyFilterEvents(is_array($result['messages'] ?? null) ? $result['messages'] : [], $user);
    } elseif ($action === 'get_media') {
        $result = whatsappBridgeRequest('/media?id=' . rawurlencode((string) ($input['message_id'] ?? '')));
    } elseif ($action === 'get_presence') {
        $result = whatsappBridgeRequest('/presence?phone=' . rawurlencode((string) ($input['phone'] ?? '')) . '&device=' . rawurlencode(chatlyPresenceDevice($user, $admin, $myCompany, $input)));
        if (empty($result['success'])) {
            $result = ['success' => true, 'state' => 'unknown', 'label' => 'Online / last seen unavailable'];
        }
    } elseif ($action === 'send_presence') {
        $phone = preg_replace('/\D+/', '', (string) ($input['phone'] ?? ''));
        if (str_starts_with($phone, '0092')) $phone = substr($phone, 2);
        elseif (str_starts_with($phone, '03') && strlen($phone) === 11) $phone = '92' . substr($phone, 1);
        elseif (str_starts_with($phone, '3') && strlen($phone) === 10) $phone = '92' . $phone;
        $type = (string) ($input['type'] ?? 'composing');
        $result = whatsappBridgeRequest('/presence', ['phone' => $phone, 'type' => $type, 'device' => chatlyPresenceDevice($user, $admin, $myCompany, $input)]);
        if (empty($result['success'])) {
            $result = ['success' => true];
        }
    } elseif ($action === 'sync_chat') {
        $result = whatsappBridgeRequest('/sync-chat', ['phone' => (string) ($input['phone'] ?? '')]);
        if (empty($result['success']) || ($result['state'] ?? '') === 'offline') {
            $result = ['success' => true];
        }
    } elseif ($action === 'restart_bridge' || $action === 'restart_daemon') {
        require_once __DIR__ . '/admin_guard.php';
        requireBridgeAdmin();
        ensureBridgeRunning(true);
        $result = ['success' => true, 'message' => 'WhatsApp bridge supervisor restarted with the latest Baileys engine.'];
    } elseif ($action === 'start_campaign') {
        if (!$admin) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Only company administrators can launch campaigns.']);
            exit;
        }
        // Verify bridge process is active and running the current engine.
        $check = whatsappBridgeRequest('/status');
        ensureBridgeCurrent($check);
        if (empty($check['success']) && !bridgePortOpen()) {
            ensureBridgeRunning(false);
            for ($attempt = 0; $attempt < 6; $attempt++) {
                usleep(400000);
                $check = whatsappBridgeRequest('/status');
                if (!empty($check['success'])) break;
            }
        }

        // Campaigns send from each Team Member's own linked WhatsApp, not the main number;
        // the bridge refuses to start when none of the selected TMs is connected.
        // Only this company's active TMs/TLs may be senders.
        $allowedSenders = chatlyCompanySenderIds($campaignCompany);
        foreach (['senders', 'closers'] as $k) {
            if (is_array($input[$k] ?? null)) {
                $input[$k] = array_values(array_filter($input[$k], fn($s) => is_array($s) && isset($allowedSenders[(string) ($s['id'] ?? '')])));
            }
        }
        unset($input['closer']);
        $result = whatsappBridgeRequest('/campaign/start', $input);
        if (!empty($result['success'])) {
            // The audience is NOT copied into the CRM here any more. That created a
            // "Queued for outreach" lead for every number of every campaign (14,000+ leads that
            // were never messaged, and a huge leads.json). The bridge creates each lead, owned by
            // the TM who sent it, when its message is actually sent (campaign.js applyCrmItem).
            unset($result['allocations']);
        } elseif (empty($result['message'])) {
            $result['message'] = 'The WhatsApp bridge did not accept the campaign. Check that the bridge is online.';
        }
    } elseif ($action === 'pause_campaign') {
        $result = whatsappBridgeRequest('/campaign/pause', $input);
    } elseif ($action === 'resume_campaign') {
        $result = whatsappBridgeRequest('/campaign/resume', $input);
    } elseif ($action === 'stop_campaign') {
        $result = whatsappBridgeRequest('/campaign/stop', $input);
    } elseif ($action === 'clear_campaign') {
        $result = whatsappBridgeRequest('/campaign/clear', $input);
        if (empty($result['success'])) {
            $result = ['success' => true, 'message' => 'Campaign cleared.'];
        }
        $stateFiles = $campaignCompany === 'COMP-001'
            ? [__DIR__ . '/../data/whatsapp-bridge/campaign_state.json', __DIR__ . '/../data/campaign_state.json']
            : [__DIR__ . '/../data/whatsapp-bridge/campaign_state_' . $campaignCompany . '.json'];
        foreach ($stateFiles as $sf) {
            if (file_exists($sf)) {
                $cur = json_decode(@file_get_contents($sf), true) ?: [];
                $cur['audience'] = [];
                $cur['status'] = 'idle';
                $cur['currentIndex'] = 0;
                $cur['totalSent'] = 0;
                $cur['totalFailed'] = 0;
                $cur['remainingSeconds'] = 0;
                $cur['logs'] = [];
                @file_put_contents($sf, json_encode($cur, JSON_PRETTY_PRINT));
            }
        }
    } elseif ($action === 'get_campaign_status') {
        $result = whatsappBridgeRequest('/campaign/status?company_id=' . rawurlencode($campaignCompany));
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
        $sendBody = ['phone' => $phone,
            'message' => (string) ($input['message'] ?? ''),
            'media' => $input['media'] ?? null,
            'request_id' => $requestId];
        $sendRoute = '/send';
        // A Team Member who linked their own WhatsApp sends from it, not from the company number.
        if (!$admin && !empty($user['id']) && empty($user['auto'])) {
            $dev = whatsappBridgeRequest('/device/status?id=' . rawurlencode((string) $user['id']));
            if (!empty($dev['is_connected'])) {
                $sendRoute = '/device/send';
                $sendBody['id'] = (string) $user['id'];
            }
        }
        $result = whatsappBridgeRequest($sendRoute, $sendBody);
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

/**
 * Campaign devices: every Team Member links their own WhatsApp (QR in their
 * workspace). Campaigns split the dropped numbers equally between the TMs whose
 * WhatsApp is connected, and each TM's share is sent from that TM's number.
 * A TM can only manage their own device; admins can see and reset all of them.
 */
function handleCampaignDevice(string $action, array $input): void {
    $user = chatlySessionUser();
    session_write_close();
    if (!is_array($user) || empty($user['id'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Please sign in first.']);
        exit;
    }
    ensureBridgeCurrent();
    $role = strtolower($user['role'] ?? '');
    $isAdmin = in_array($role, ['admin', 'superadmin', 'master admin'], true);
    $targetId = (string) ($input['user_id'] ?? $_GET['user_id'] ?? '');
    if ($targetId === '' || !$isAdmin) $targetId = (string) $user['id'];
    // A company admin manages only their own company's closers.
    if ($targetId !== (string) $user['id'] && !chatlyIsSuperAdmin($user)) {
        $target = chatlyUsersById()[$targetId] ?? null;
        if (!$target || chatlyUserCompany($target) !== chatlyUserCompany($user)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'That team member belongs to another company.']);
            exit;
        }
    }

    if ($action === 'list_campaign_devices') {
        if (!$isAdmin) { http_response_code(403); echo json_encode(['success' => false, 'message' => 'Administrator access required.']); exit; }
        $result = whatsappBridgeRequest('/devices');
        if (!chatlyIsSuperAdmin($user) && is_array($result['devices'] ?? null)) {
            $users = chatlyUsersById();
            $mine = chatlyUserCompany($user);
            $result['devices'] = array_values(array_filter($result['devices'], fn($d) =>
                isset($users[(string) ($d['user_id'] ?? '')]) && chatlyUserCompany($users[(string) $d['user_id']]) === $mine));
        }
    } elseif ($action === 'campaign_device_status') {
        $result = whatsappBridgeRequest('/device/status?id=' . rawurlencode($targetId));
    } elseif ($action === 'campaign_device_connect') {
        $result = whatsappBridgeRequest('/device/connect', ['id' => $targetId, 'name' => (string) ($user['name'] ?? $targetId), 'force' => !empty($input['force'])]);
    } elseif ($action === 'campaign_device_pair') {
        // Link with a phone-number code instead of a QR (people with only one phone).
        // Empty number = the account's own phone.
        if (($wait = chatlyPairingThrottle()) > 0) {
            http_response_code(429);
            echo json_encode(['success' => false, 'message' => "Please wait {$wait} seconds before asking for another code."]);
            exit;
        }
        $phone = trim((string) ($input['phone'] ?? '')) ?: (string) ((chatlyUsersById()[$targetId]['phone'] ?? '') ?: ($user['phone'] ?? ''));
        $result = whatsappBridgeRequest('/device/pair', ['id' => $targetId, 'phone' => $phone, 'name' => (string) ($user['name'] ?? $targetId)]);
    } else {
        $result = whatsappBridgeRequest('/device/reset', ['id' => $targetId, 'name' => (string) ($user['name'] ?? $targetId), 'relink' => !empty($input['relink'])]);
    }
    if (($result['state'] ?? '') === 'offline' && empty($result['user_id'])) {
        $result = ['success' => false, 'state' => 'offline', 'is_connected' => false, 'message' => $result['message'] ?? 'The WhatsApp bridge is offline.'];
    }
    if (empty($result['success'])) http_response_code(503);
    echo json_encode($result);
    exit;
}

/** Map of userId => connected device number, from the bridge. Empty when the bridge is down. */
function campaignConnectedDevices(array $users = []): array {
    ensureBridgeCurrent();
    $res = whatsappBridgeRequest('/devices');
    $out = [];
    foreach (($res['devices'] ?? []) as $d) {
        if (!empty($d['is_connected']) && !empty($d['user_id'])) $out[$d['user_id']] = (string) ($d['connected_number'] ?? '');
    }
    // The company's main linked WhatsApp belongs to whichever TM scanned it; count that TM too.
    $main = whatsappBridgeRequest('/status');
    $mainNum = preg_replace('/\D+/', '', (string) ($main['connected_number'] ?? ''));
    if (!empty($main['is_connected']) && strlen($mainNum) >= 9) {
        foreach ($users as $u) {
            $p = preg_replace('/\D+/', '', (string) ($u['phone'] ?? ''));
            if (strlen($p) >= 9 && substr($p, -9) === substr($mainNum, -9) && !isset($out[$u['id']])) $out[$u['id']] = $mainNum;
        }
    }
    return $out;
}

/** Find the Chatly account that owns a WhatsApp number (last 9 digits match). */
function findUserByWhatsApp(string $phone): ?array {
    $phone = preg_replace('/\D+/', '', $phone);
    if (strlen($phone) < 9) return null;
    $users = json_decode((string) @file_get_contents(__DIR__ . '/../data/users.json'), true);
    foreach ((is_array($users) ? $users : []) as $u) {
        $p = preg_replace('/\D+/', '', (string) ($u['phone'] ?? ''));
        if (strlen($p) >= 9 && substr($p, -9) === substr($phone, -9)) return $u;
    }
    return null;
}

/**
 * Login QR. Each visitor gets their OWN WhatsApp session (not the company's main
 * number). When they scan it, the server learns the phone; if it belongs to an
 * account, that session becomes the TM's linked WhatsApp (campaigns, chats). The
 * verified phone is stored in this PHP session so qr_login / tm_register can trust it.
 */
/**
 * Phone-number linking codes make WhatsApp show a "link a device" notification on that
 * number's phone, so they are rate-limited per browser session: 20 s apart, 6 per 10 min.
 * Returns the seconds to wait (0 = allowed, and the request is counted).
 */
function chatlyPairingThrottle(bool $closeSession = true): int {
    if (session_status() !== PHP_SESSION_ACTIVE) @session_start();
    $now = time();
    $hist = array_values(array_filter((array) ($_SESSION['pairing_requests'] ?? []), fn($t) => $now - (int) $t < 600));
    $wait = 0;
    if ($hist && $now - (int) end($hist) < 20) $wait = 20 - ($now - (int) end($hist));
    elseif (count($hist) >= 6) $wait = 600 - ($now - (int) $hist[0]);
    if ($wait <= 0) { $hist[] = $now; $wait = 0; }
    $_SESSION['pairing_requests'] = $hist;
    if ($closeSession) session_write_close();
    return $wait;
}

function handleLoginQr(string $action, array $input = []): void {
    if (!bridgePortOpen()) {
        ensureBridgeRunning(false);
        for ($i = 0; $i < 6; $i++) {
            usleep(400000);
            if (bridgePortOpen()) break;
        }
    }
    ensureBridgeCurrent();
    $loginId = $_SESSION['qr_login_id'] ?? '';
    // Already scanned in this browser a moment ago: stay on the "connected" card.
    if ($action === 'login_qr_status' && !$loginId && !empty($_SESSION['qr_verified_phone']) && time() - (int) ($_SESSION['qr_verified_at'] ?? 0) < 900) {
        $phone = (string) $_SESSION['qr_verified_phone'];
        session_write_close();
        echo json_encode(['success' => true, 'mode' => 'whatsapp_web', 'state' => 'ready', 'qr' => null, 'is_connected' => true,
            'connected_number' => $phone, 'registered' => (bool) findUserByWhatsApp($phone), 'message' => '']);
        exit;
    }
    if ($action === 'login_qr_reset' && $loginId) {
        whatsappBridgeRequest('/device/reset', ['id' => $loginId]);
        $loginId = '';
    }
    if ($action === 'login_qr_pair') {
        // "Link with phone number instead": same private login session as the QR, linked by
        // typing a code into WhatsApp. Once linked, login_qr_status takes over as for a scan.
        if (($wait = chatlyPairingThrottle(false)) > 0) {
            session_write_close();
            http_response_code(429);
            echo json_encode(['success' => false, 'message' => "Please wait {$wait} seconds before asking for another code."]);
            exit;
        }
        if (!$loginId) {
            $loginId = 'login_' . bin2hex(random_bytes(8));
            $_SESSION['qr_login_id'] = $loginId;
            unset($_SESSION['qr_verified_phone'], $_SESSION['qr_verified_at'], $_SESSION['qr_pending_device']);
        }
        session_write_close();
        $snap = whatsappBridgeRequest('/device/pair', ['id' => $loginId, 'phone' => (string) ($input['phone'] ?? ''), 'name' => 'Login']);
        if (empty($snap['success']) || empty($snap['pairing_code'])) http_response_code(($snap['state'] ?? '') === 'offline' ? 503 : 400);
        echo json_encode([
            'success' => !empty($snap['success']) && !empty($snap['pairing_code']),
            'pairing_code' => $snap['pairing_code'] ?? null,
            'pairing_phone' => $snap['pairing_phone'] ?? null,
            'state' => $snap['state'] ?? 'offline',
            'message' => $snap['message'] ?? 'Could not get a code. Try again.',
        ]);
        exit;
    }
    if ($action !== 'login_qr_status' || !$loginId) {
        if (!$loginId) {
            $loginId = 'login_' . bin2hex(random_bytes(8));
            $_SESSION['qr_login_id'] = $loginId;
            unset($_SESSION['qr_verified_phone'], $_SESSION['qr_verified_at'], $_SESSION['qr_pending_device']);
        }
        $snap = whatsappBridgeRequest('/device/connect', ['id' => $loginId, 'name' => 'Login']);
    } else {
        $snap = whatsappBridgeRequest('/device/status?id=' . rawurlencode($loginId));
        if (($snap['state'] ?? '') === 'disconnected' && empty($snap['user_id'])) {
            // The bridge restarted or the scan expired: start a fresh code.
            $snap = whatsappBridgeRequest('/device/connect', ['id' => $loginId, 'name' => 'Login']);
        }
    }

    $out = [
        'success' => !empty($snap['success']),
        'mode' => 'whatsapp_web',
        'state' => $snap['state'] ?? 'offline',
        'qr' => $snap['qr'] ?? null,
        'pairing_code' => $snap['pairing_code'] ?? null,   // lets the page see when a code expired
        'is_connected' => false,
        'connected_number' => '',
        'message' => $snap['message'] ?? '',
    ];
    // Never substitute the shared/admin WhatsApp session here. A closer's
    // login QR must always belong to that browser's private login_<id> device.
    if (!empty($snap['is_connected']) && !empty($snap['connected_number'])) {
        $phone = preg_replace('/\D+/', '', (string) $snap['connected_number']);
        $_SESSION['qr_verified_phone'] = $phone;
        $_SESSION['qr_verified_at'] = time();
        $owner = findUserByWhatsApp($phone);
        if ($owner) {
            // This scan is now that TM's own linked WhatsApp.
            $adopted = whatsappBridgeRequest('/device/adopt', ['from' => $loginId, 'to' => $owner['id'], 'name' => $owner['name'] ?? '']);
            if (!empty($adopted['success'])) unset($_SESSION['qr_login_id'], $_SESSION['qr_pending_device']);
        } else {
            $_SESSION['qr_pending_device'] = $loginId;   // claimed when they finish signing up
        }
        $out['is_connected'] = true;
        $out['state'] = 'ready';
        $out['connected_number'] = $phone;
        $out['registered'] = (bool) $owner;
    }
    session_write_close();
    if (empty($out['success'])) http_response_code(503);
    echo json_encode($out);
    exit;
}
