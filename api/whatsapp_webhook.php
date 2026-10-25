<?php
/** Receive WhatsApp Cloud API delivery status updates. */
header('Cache-Control: no-cache, no-store, must-revalidate');

$verifyToken = getenv('WHATSAPP_WEBHOOK_VERIFY_TOKEN') ?: '';
$appSecret = getenv('WHATSAPP_APP_SECRET') ?: '';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $mode = $_GET['hub_mode'] ?? $_GET['hub.mode'] ?? '';
    $token = $_GET['hub_verify_token'] ?? $_GET['hub.verify_token'] ?? '';
    $challenge = $_GET['hub_challenge'] ?? $_GET['hub.challenge'] ?? '';
    if ($mode === 'subscribe' && $verifyToken !== '' && hash_equals($verifyToken, $token)) {
        header('Content-Type: text/plain');
        echo $challenge;
        exit;
    }
    http_response_code(403);
    exit('Webhook verification failed.');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || $appSecret === '') {
    http_response_code(403);
    exit('Webhook is not configured.');
}

$body = file_get_contents('php://input');
$signature = $_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? '';
$expected = 'sha256=' . hash_hmac('sha256', $body, $appSecret);
if ($signature === '' || !hash_equals($expected, $signature)) {
    http_response_code(403);
    exit('Invalid signature.');
}

$payload = json_decode($body, true);
if (!is_array($payload)) {
    http_response_code(400);
    exit('Invalid JSON.');
}

$updates = [];
foreach (($payload['entry'] ?? []) as $entry) {
    foreach (($entry['changes'] ?? []) as $change) {
        foreach (($change['value']['statuses'] ?? []) as $status) {
            $messageId = $status['id'] ?? '';
            $state = $status['status'] ?? '';
            if ($messageId === '' || !in_array($state, ['sent', 'delivered', 'read', 'failed'], true)) continue;
            $updates[$messageId] = [
                'status' => $state,
                'status_at' => isset($status['timestamp']) ? date('Y-m-d H:i:s', (int) $status['timestamp']) : date('Y-m-d H:i:s'),
                'error' => $state === 'failed' ? ($status['errors'][0]['title'] ?? $status['errors'][0]['message'] ?? 'WhatsApp reported delivery failure.') : null
            ];
        }
    }
}

$logFile = __DIR__ . '/../data/campaign_logs.json';
$handle = fopen($logFile, 'c+');
if ($handle === false || !flock($handle, LOCK_EX)) {
    if (is_resource($handle)) fclose($handle);
    http_response_code(500);
    exit('Could not update delivery log.');
}
$contents = stream_get_contents($handle);
$logs = json_decode($contents ?: '[]', true);
if (!is_array($logs)) $logs = [];
foreach ($logs as &$log) {
    $messageId = $log['message_id'] ?? '';
    if (isset($updates[$messageId])) {
        $log['status'] = $updates[$messageId]['status'];
        $log['status_at'] = $updates[$messageId]['status_at'];
        if ($updates[$messageId]['error']) $log['error'] = $updates[$messageId]['error'];
    }
}
unset($log);
rewind($handle);
ftruncate($handle, 0);
fwrite($handle, json_encode($logs, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
fflush($handle);
flock($handle, LOCK_UN);
fclose($handle);

header('Content-Type: application/json');
echo json_encode(['success' => true]);
