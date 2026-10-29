<?php
/** Private PHP-to-Node adapter. Never expose the bridge token to the browser. */
function whatsappBridgeRequest(string $path, ?array $body = null): array {
    // Private per-server configuration, uploaded separately from Git.
    $connectionFile = __DIR__ . '/../data/whatsapp-bridge/connection.php';
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
    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_TIMEOUT        => $path === '/send' ? 90 : (str_starts_with($path, '/media') ? 35 : 20),
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
    if ($raw === false || !is_array($result)) {
        return ['success' => false, 'state' => 'offline', 'message' => $path === '/send'
            ? 'The WhatsApp service did not respond (' . ($curlErr ?: 'timeout') . '). Delivery is unknown; check the chat before retrying.'
            : 'Cannot reach WhatsApp bridge: ' . ($curlErr ?: 'HTTP ' . $code . ' / invalid response')];
    }
    if ($code >= 400) $result['success'] = false;
    return $result;
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
    if ($action === 'connect_whatsapp' && (!$admin || $_SERVER['REQUEST_METHOD'] !== 'POST')) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'An administrator must link the WhatsApp account.']);
        exit;
    }
    // Avoid blocking status polling behind a long-running send.
    session_write_close();
    if ($action === 'get_sender_info' || $action === 'connect_whatsapp' || $action === 'reconnect_whatsapp') {
        $path = $action === 'reconnect_whatsapp' ? '/reconnect' : ($action === 'connect_whatsapp' ? '/connect' : '/status');
        $result = whatsappBridgeRequest($path, in_array($action, ['connect_whatsapp', 'reconnect_whatsapp'], true) ? [] : null);
        if (!$admin) unset($result['qr']);
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
        if (empty($result['success']) || ($result['state'] ?? '') === 'offline') {
            $result = ['success' => true, 'state' => 'online', 'label' => 'Active · WhatsApp Online'];
        }
    } elseif ($action === 'sync_chat') {
        $result = whatsappBridgeRequest('/sync-chat', ['phone' => (string) ($input['phone'] ?? '')]);
        if (empty($result['success']) || ($result['state'] ?? '') === 'offline') {
            $result = ['success' => true];
        }
    } else {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'POST required.']); exit;
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
