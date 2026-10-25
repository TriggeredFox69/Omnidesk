<?php
/** Private PHP-to-Node adapter. Never expose the bridge token to the browser. */
function whatsappBridgeRequest(string $path, ?array $body = null): array {
    $tokenFile = __DIR__ . '/../data/whatsapp-bridge/token';
    $token = getenv('WHATSAPP_BRIDGE_TOKEN') ?: (is_readable($tokenFile) ? trim(file_get_contents($tokenFile)) : '');
    if ($token === '' || !function_exists('curl_init')) {
        return ['success' => false, 'state' => 'offline', 'message' => 'The WhatsApp linking service is not running yet. Start the Node bridge on the server and enable PHP cURL, then click Connect again.'];
    }
    $base = rtrim(getenv('WHATSAPP_BRIDGE_URL') ?: 'http://127.0.0.1:3210', '/');
    $curl = curl_init($base . $path);
    curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_TIMEOUT => $path === '/send' ? 90 : (str_starts_with($path, '/media') ? 35 : 15),
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token, 'Content-Type: application/json']]);
    if ($body !== null) curl_setopt_array($curl, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => json_encode($body)]);
    $raw = curl_exec($curl);
    $code = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);
    $result = json_decode((string) $raw, true);
    if ($raw === false || !is_array($result)) {
        return ['success' => false, 'state' => 'offline', 'message' => $path === '/send'
            ? 'The WhatsApp service did not respond. Delivery is unknown; check the chat before retrying.'
            : 'Cannot reach the WhatsApp linking service. Start the Node bridge on the server, then reconnect.'];
    }
    if ($code >= 400) $result['success'] = false;
    return $result;
}

function handleWhatsAppBridge(string $action, array $input): void {
    $user = $_SESSION['omnidesk_user'] ?? null;
    if (!is_array($user)) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Please sign in to connect or send WhatsApp messages.']);
        exit;
    }
    $admin = in_array(strtolower($user['role'] ?? ''), ['admin', 'master admin'], true);
    if ($action === 'connect_whatsapp' && (!$admin || $_SERVER['REQUEST_METHOD'] !== 'POST')) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'An administrator must link the WhatsApp account.']);
        exit;
    }
    // Avoid blocking status polling behind a long-running send.
    session_write_close();
    if ($action === 'get_sender_info' || $action === 'connect_whatsapp') {
        $result = whatsappBridgeRequest($action === 'connect_whatsapp' ? '/connect' : '/status', $action === 'connect_whatsapp' ? [] : null);
        if (!$admin) unset($result['qr']);
    } elseif ($action === 'get_send_status') {
        $result = whatsappBridgeRequest('/receipt?id=' . rawurlencode((string) ($input['message_id'] ?? '')));
    } elseif ($action === 'get_events') {
        $result = whatsappBridgeRequest('/events?after=' . (int) ($input['after'] ?? 0));
    } elseif ($action === 'get_media') {
        $result = whatsappBridgeRequest('/media?id=' . rawurlencode((string) ($input['message_id'] ?? '')));
    } elseif ($action === 'get_presence') {
        $result = whatsappBridgeRequest('/presence?phone=' . rawurlencode((string) ($input['phone'] ?? '')));
    } elseif ($action === 'sync_chat') {
        $result = whatsappBridgeRequest('/sync-chat', ['phone' => (string) ($input['phone'] ?? '')]);
    } else {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'POST required.']); exit;
        }
        $phone = preg_replace('/\D+/', '', (string) ($input['phone'] ?? ''));
        if (str_starts_with($phone, '0092')) $phone = substr($phone, 2);
        elseif (str_starts_with($phone, '03') && strlen($phone) === 11) $phone = '92' . substr($phone, 1);
        elseif (str_starts_with($phone, '3') && strlen($phone) === 10) $phone = '92' . $phone;
        $result = whatsappBridgeRequest('/send', ['phone' => $phone,
            'message' => (string) ($input['message'] ?? ''),
            'media' => $input['media'] ?? null,
            'request_id' => (string) ($input['request_id'] ?? '')]);
        if (!empty($result['success'])) {
            $handle = fopen(__DIR__ . '/../data/campaign_logs.json', 'c+');
            if ($handle && flock($handle, LOCK_EX)) {
                $logs = json_decode(stream_get_contents($handle) ?: '[]', true);
                if (!is_array($logs)) $logs = [];
                $exists = false;
                foreach ($logs as $log) if (($log['message_id'] ?? '') === $result['message_id']) $exists = true;
                if (!$exists) $logs[] = ['timestamp' => date('Y-m-d H:i:s'), 'from_number' => $result['from_number'],
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
