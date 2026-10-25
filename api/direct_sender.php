<?php
/**
 * Nunno WhatsApp — Direct WhatsApp Sender Engine
 * Linked WhatsApp Web sender, with an optional Business Cloud API mode.
 */

header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 60 * 60 * 24 * 7,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}
$config = require __DIR__ . '/config.php';

$senderConfigFile = __DIR__ . '/../data/sender_config.json';
$senderConfig = file_exists($senderConfigFile) ? json_decode(file_get_contents($senderConfigFile), true) : [];
if (!is_array($senderConfig)) $senderConfig = [];

$currentNumber = getenv('WHATSAPP_DISPLAY_PHONE_NUMBER') ?: ($senderConfig['connected_number'] ?? $config['connected_whatsapp_number'] ?? '');
$cloudToken = getenv('WHATSAPP_ACCESS_TOKEN') ?: '';
$cloudConfigured = !empty($cloudToken) && !empty(getenv('WHATSAPP_PHONE_NUMBER_ID')) && !empty(getenv('WHATSAPP_GRAPH_API_VERSION'));

$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$action = $input['action'] ?? $_GET['action'] ?? '';

// Regular WhatsApp via a linked device is the default. Cloud API remains opt-in.
if ((getenv('WHATSAPP_SEND_MODE') ?: 'whatsapp_web') !== 'cloud_api' &&
    in_array($action, ['get_sender_info', 'connect_whatsapp', 'send_direct', 'get_send_status', 'get_events', 'get_media', 'get_presence', 'sync_chat'], true)) {
    require_once __DIR__ . '/whatsapp_bridge.php';
    handleWhatsAppBridge($action, $input);
}
if ($action === 'connect_whatsapp') {
    http_response_code(409);
    echo json_encode(['success' => false, 'message' => 'This server is set to Cloud API mode. Set WHATSAPP_SEND_MODE=whatsapp_web on the PHP server to use QR linking.']);
    exit;
}

// 1. Get Sender Info
if ($action === 'get_sender_info') {
    echo json_encode([
        'success'          => true,
        'connected_number' => $currentNumber,
        'is_connected'     => $cloudConfigured,
        'mode'             => $cloudConfigured ? 'whatsapp_cloud_api' : 'not_configured',
        'is_configured'    => $cloudConfigured
    ]);
    exit;
}

// 2. Set / Update Connected WhatsApp Number
if ($action === 'set_sender_number') {
    $newNumber = trim($input['phone_number'] ?? '');
    // Clean and normalize
    $clean = preg_replace('/\D+/', '', $newNumber);
    if (!empty($clean)) {
        if (str_starts_with($clean, '0092')) $clean = substr($clean, 2);
        elseif (str_starts_with($clean, '03') && strlen($clean) === 11) $clean = '92' . substr($clean, 1);
        elseif (str_starts_with($clean, '3') && strlen($clean) === 10) $clean = '92' . $clean;
    }

    $senderConfig['connected_number'] = $clean ?: $newNumber;
    $senderConfig['updated_at'] = date('Y-m-d H:i:s');
    file_put_contents($senderConfigFile, json_encode($senderConfig, JSON_PRETTY_PRINT));

    echo json_encode([
        'success'          => true,
        'message'          => 'WhatsApp number updated successfully!',
        'connected_number' => $senderConfig['connected_number']
    ]);
    exit;
}

// 3. Direct Message Dispatch Helper
if ($action === 'send_direct') {
    $user = $_SESSION['omnidesk_user'] ?? null;
    if (!is_array($user)) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Please sign in before sending WhatsApp messages.']);
        exit;
    }

    $phone = trim($input['phone'] ?? '');
    $message = trim($input['message'] ?? '');

    if (empty($phone)) {
        echo json_encode(['success' => false, 'message' => 'Recipient phone number is required.']);
        exit;
    }

    // Clean recipient number
    $cleanRecipient = preg_replace('/\D+/', '', $phone);
    if (substr($cleanRecipient, 0, 4) === '0092') {
        $cleanRecipient = substr($cleanRecipient, 2);
    } elseif (substr($cleanRecipient, 0, 2) === '03' && strlen($cleanRecipient) === 11) {
        $cleanRecipient = '92' . substr($cleanRecipient, 1);
    } elseif (substr($cleanRecipient, 0, 1) === '3' && strlen($cleanRecipient) === 10) {
        $cleanRecipient = '92' . $cleanRecipient;
    }

    $phoneNumberId = getenv('WHATSAPP_PHONE_NUMBER_ID') ?: '';
    $accessToken = getenv('WHATSAPP_ACCESS_TOKEN') ?: '';
    $graphVersion = getenv('WHATSAPP_GRAPH_API_VERSION') ?: '';
    if ($message === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Message text is required.']);
        exit;
    }
    if (strlen($cleanRecipient) < 10 || strlen($cleanRecipient) > 15) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Recipient phone number is invalid.']);
        exit;
    }

    if (!$phoneNumberId || !$accessToken || !$graphVersion) {
        http_response_code(503);
        echo json_encode(['success' => false, 'message' => 'Automatic sending is not connected. Configure WHATSAPP_PHONE_NUMBER_ID, WHATSAPP_ACCESS_TOKEN, and WHATSAPP_GRAPH_API_VERSION on the PHP server. This app sends through the WhatsApp Business API; it cannot send from a personal WhatsApp session.']);
        exit;
    }
    if (!preg_match('/^v[0-9]+\\.[0-9]+$/', $graphVersion)) {
        http_response_code(503);
        echo json_encode(['success' => false, 'message' => 'WHATSAPP_GRAPH_API_VERSION must use the format vNN.N.']);
        exit;
    }
    $templateName = getenv('WHATSAPP_TEMPLATE_NAME') ?: '';
    $payload = ['messaging_product' => 'whatsapp', 'to' => $cleanRecipient];
    if ($templateName !== '') {
        $language = getenv('WHATSAPP_TEMPLATE_LANGUAGE') ?: 'en_US';
        $payload['type'] = 'template';
        $payload['template'] = [
            'name' => $templateName,
            'language' => ['code' => $language],
            'components' => [[
                'type' => 'body',
                'parameters' => [['type' => 'text', 'text' => $message]]
            ]]
        ];
    } else {
        $payload['type'] = 'text';
        $payload['text'] = ['preview_url' => false, 'body' => $message];
    }

    if (!function_exists('curl_init')) {
        http_response_code(503);
        echo json_encode(['success' => false, 'message' => 'The PHP cURL extension is required to send WhatsApp messages.']);
        exit;
    }
    $curl = curl_init("https://graph.facebook.com/{$graphVersion}/{$phoneNumberId}/messages");
    curl_setopt_array($curl, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $accessToken, 'Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_TIMEOUT => 30
    ]);
    $responseBody = curl_exec($curl);
    $curlError = curl_error($curl);
    $httpStatus = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);
    $apiResponse = json_decode((string) $responseBody, true);
    if ($responseBody === false || $httpStatus < 200 || $httpStatus >= 300 || empty($apiResponse['messages'][0]['id'])) {
        $errorMessage = $apiResponse['error']['message'] ?? ($curlError ?: 'WhatsApp API rejected the message.');
        http_response_code($httpStatus >= 400 ? $httpStatus : 502);
        echo json_encode(['success' => false, 'message' => $errorMessage]);
        exit;
    }

    // Log the direct dispatch
    $logFile = __DIR__ . '/../data/campaign_logs.json';
    $logs = file_exists($logFile) ? json_decode(file_get_contents($logFile), true) : [];
    if (!is_array($logs)) $logs = [];

    $logs[] = [
        'timestamp'      => date('Y-m-d H:i:s'),
        'from_number'    => $currentNumber ?: 'Default Connected Number',
        'to_number'      => $cleanRecipient,
        'mode'           => 'whatsapp_cloud_api',
        'status'         => 'accepted',
        'message_id'     => $apiResponse['messages'][0]['id']
    ];
    if (count($logs) > 500) $logs = array_slice($logs, -500);
    file_put_contents($logFile, json_encode($logs, JSON_PRETTY_PRINT));

    echo json_encode([
        'success'       => true,
        'dispatch_mode' => 'whatsapp_cloud_api',
        'from_number'   => $currentNumber,
        'to_number'     => $cleanRecipient,
        'message_id'    => $apiResponse['messages'][0]['id']
    ]);
    exit;
}

// 4. Logs
if ($action === 'get_logs') {
    $logFile = __DIR__ . '/../data/campaign_logs.json';
    $logs = file_exists($logFile) ? json_decode(file_get_contents($logFile), true) : [];
    echo json_encode(['success' => true, 'logs' => $logs]);
    exit;
}

if ($action === 'get_send_status') {
    $user = $_SESSION['omnidesk_user'] ?? null;
    if (!is_array($user) || !in_array(strtolower($user['role'] ?? ''), ['admin', 'master admin'], true)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Administrator access required.']);
        exit;
    }
    $messageId = trim($input['message_id'] ?? '');
    $logFile = __DIR__ . '/../data/campaign_logs.json';
    $logs = file_exists($logFile) ? json_decode(file_get_contents($logFile), true) : [];
    foreach (is_array($logs) ? array_reverse($logs) : [] as $log) {
        if (($log['message_id'] ?? '') === $messageId) {
            echo json_encode(['success' => true, 'status' => $log['status'] ?? 'accepted', 'error' => $log['error'] ?? null]);
            exit;
        }
    }
    echo json_encode(['success' => true, 'status' => 'accepted']);
    exit;
}

if ($action === 'clear_logs') {
    $logFile = __DIR__ . '/../data/campaign_logs.json';
    file_put_contents($logFile, json_encode([]));
    echo json_encode(['success' => true, 'message' => 'Logs cleared.']);
    exit;
}

echo json_encode([
    'success'          => true,
    'engine'           => 'Nunno Direct WhatsApp Sender',
    'connected_number' => $currentNumber ?: 'Pending User Configuration',
    'status'           => 'ready'
]);
