<?php
/**
 * Nunno WhatsApp — Direct WhatsApp Sender Engine
 * Direct Number Connection (No Meta Cloud API)
 */

header('Content-Type: application/json');
$config = require __DIR__ . '/config.php';

$senderConfigFile = __DIR__ . '/../data/sender_config.json';
$senderConfig = file_exists($senderConfigFile) ? json_decode(file_get_contents($senderConfigFile), true) : [];
if (!is_array($senderConfig)) $senderConfig = [];

$currentNumber = $senderConfig['connected_number'] ?? $config['connected_whatsapp_number'] ?? '';

$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$action = $input['action'] ?? $_GET['action'] ?? '';

// 1. Get Sender Info
if ($action === 'get_sender_info') {
    echo json_encode([
        'success'          => true,
        'connected_number' => $currentNumber,
        'is_connected'     => !empty($currentNumber),
        'mode'             => 'direct_device',
        'anti_ban_delay'   => '4-8s jitter'
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
    $phone = trim($input['phone'] ?? '');
    $message = trim($input['message'] ?? '');

    if (empty($phone)) {
        echo json_encode(['success' => false, 'message' => 'Recipient phone number is required.']);
        exit;
    }

    // Clean recipient number
    $cleanRecipient = preg_replace('/\D+/', '', $phone);
    if (str_starts_with($cleanRecipient, '03') && strlen($cleanRecipient) === 11) {
        $cleanRecipient = '92' . substr($cleanRecipient, 1);
    }

    // Generate direct WhatsApp dispatch URLs
    $encodedText = rawurlencode($message);
    $waMeUrl = "https://wa.me/{$cleanRecipient}?text={$encodedText}";
    $waWebUrl = "https://web.whatsapp.com/send?phone={$cleanRecipient}&text={$encodedText}";
    $waAppUrl = "whatsapp://send?phone={$cleanRecipient}&text={$encodedText}";

    // Log the direct dispatch
    $logFile = __DIR__ . '/../data/campaign_logs.json';
    $logs = file_exists($logFile) ? json_decode(file_get_contents($logFile), true) : [];
    if (!is_array($logs)) $logs = [];

    $logs[] = [
        'timestamp'      => date('Y-m-d H:i:s'),
        'from_number'    => $currentNumber ?: 'Default Connected Number',
        'to_number'      => $cleanRecipient,
        'mode'           => 'direct_session',
        'status'         => 'dispatched'
    ];
    if (count($logs) > 500) $logs = array_slice($logs, -500);
    file_put_contents($logFile, json_encode($logs, JSON_PRETTY_PRINT));

    echo json_encode([
        'success'       => true,
        'dispatch_mode' => 'direct',
        'from_number'   => $currentNumber,
        'to_number'     => $cleanRecipient,
        'wa_me_url'     => $waMeUrl,
        'wa_web_url'    => $waWebUrl,
        'wa_app_url'    => $waAppUrl
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
