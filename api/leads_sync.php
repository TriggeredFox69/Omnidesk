<?php
/**
 * Nunno WhatsApp — Centralized Leads & Multi-Agent State Synchronization
 * Ensures zero scattered data across team members on cPanel
 */

header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');

$dataFile = __DIR__ . '/../data/leads.json';

// Helper: Read current centralized leads
function getStoredLeads($dataFile) {
    if (file_exists($dataFile)) {
        $content = file_get_contents($dataFile);
        $decoded = json_decode($content, true);
        if (is_array($decoded) && count($decoded) > 0) {
            return $decoded;
        }
    }
    return null;
}

// Helper: Save centralized leads safely with atomic write
function saveStoredLeads($dataFile, $leads) {
    $tempFile = $dataFile . '.tmp.' . uniqid();
    file_put_contents($tempFile, json_encode($leads, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    rename($tempFile, $dataFile);
}

// Default initial leads if data/leads.json doesn't exist yet
$defaultLeads = [
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
    ]
];

// Initialize if not present
$currentLeads = getStoredLeads($dataFile);
if ($currentLeads === null) {
    saveStoredLeads($dataFile, $defaultLeads);
    $currentLeads = $defaultLeads;
}

// Router
$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$action = $input['action'] ?? $_GET['action'] ?? 'get_leads';

// Clear all demo data, wipe inboxes, and reset to 03318036576
if ($action === 'clear_demo_data' || $action === 'wipe_demo') {
    $selfOnly = [
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
    saveStoredLeads($dataFile, $selfOnly);

    // Wipe old inbox messages from both directories
    $dataDir = dirname(__DIR__) . '/data/whatsapp-bridge';
    @unlink($dataDir . '/inbox.json');
    @unlink($dataDir . '/whatsapp-bridge/inbox.json');
    file_put_contents($dataDir . '/inbox.json', '[]');
    if (is_dir($dataDir . '/whatsapp-bridge')) {
        file_put_contents($dataDir . '/whatsapp-bridge/inbox.json', '[]');
    }

    // Reset campaign logs
    $campaignLog = dirname(__DIR__) . '/data/campaign_logs.json';
    if (file_exists($campaignLog)) file_put_contents($campaignLog, '[]');

    // Restart bridge to reload empty inbox while KEEPING baileys_auth session
    $output = @shell_exec('pgrep -f "dist/index.js|server.js" 2>&1');
    if ($output) {
        $pids = array_filter(array_map('trim', explode("\n", trim($output))), 'is_numeric');
        foreach ($pids as $p) @shell_exec("kill -9 $p 2>&1");
    }
    sleep(1);

    $nodePath = '/opt/alt/alt-nodejs22/root/usr/bin/node';
    $bridgeDir = dirname(__DIR__) . '/bridge';
    $connFile = $dataDir . '/connection.php';
    $conn = file_exists($connFile) ? require($connFile) : [];
    $tokenFile = $dataDir . '/token';
    $token = $conn['token'] ?? (file_exists($tokenFile) ? trim(file_get_contents($tokenFile)) : '');
    // Ensure connection.php uses local HTTP, not HTTPS, to avoid SSL wrong version error
    file_put_contents($connFile, "<?php\nreturn ['url' => 'http://127.0.0.1:3210', 'token' => '" . addslashes($token) . "'];\n");

    $logFile = $dataDir . '/bridge.log';
    $scriptPath = file_exists($bridgeDir . '/dist/index.js') ? ($bridgeDir . '/dist/index.js') : ($bridgeDir . '/server.js');
    $startCmd = "PORT=3210 WHATSAPP_BRIDGE_HOST=127.0.0.1 WHATSAPP_BRIDGE_TOKEN=" . escapeshellarg($token)
        . " WHATSAPP_DATA_DIR=" . escapeshellarg($dataDir . '/whatsapp-bridge') . " WHATSAPP_ENGINE=baileys "
        . escapeshellarg($nodePath) . " " . escapeshellarg($scriptPath)
        . " </dev/null >> " . escapeshellarg($logFile) . " 2>&1 &";
    shell_exec($startCmd);
    sleep(2);

    echo json_encode([
        'success' => true,
        'message' => 'All demo data and old chats wiped cleanly. 24/7 live sync active on 03318036576.',
        'leads' => $selfOnly,
        'count' => 1
    ], JSON_PRETTY_PRINT);
    exit;
}

// 1. Get All Leads
if ($action === 'get_leads') {
    $modified = false;
    foreach ($currentLeads as &$lead) {
        $curName = trim($lead['name'] ?? '');
        $lower = strtolower($curName);
        if ($lower === 'trader' || $lower === 'alishba' || empty($curName)) {
            $p = preg_replace('/\D+/', '', $lead['phone'] ?? '');
            $lead['name'] = !empty($p) ? ('+' . $p) : ($lead['display'] ?? 'Contact');
            $modified = true;
        }
    }
    unset($lead);
    if ($modified) {
        saveStoredLeads($dataFile, $currentLeads);
    }
    echo json_encode([
        'success' => true,
        'leads'   => $currentLeads,
        'count'   => count($currentLeads)
    ]);
    exit;
}

// 2. Update Lead State, Ownership, or Notes
// 2. Update Lead State, Ownership, or Notes
if ($action === 'update_lead') {
    $leadId = $input['id'] ?? '';
    $inputPhone = isset($input['phone']) ? preg_replace('/\D+/', '', $input['phone']) : '';
    if (str_starts_with($inputPhone, '0092')) $inputPhone = substr($inputPhone, 2);
    elseif (str_starts_with($inputPhone, '03') && strlen($inputPhone) === 11) $inputPhone = '92' . substr($inputPhone, 1);
    elseif (str_starts_with($inputPhone, '3') && strlen($inputPhone) === 10) $inputPhone = '92' . $inputPhone;
    if (empty($inputPhone) && str_starts_with($leadId, 'wa_lead_')) {
        $inputPhone = preg_replace('/\D+/', '', substr($leadId, 8));
    }

    if (empty($leadId) && empty($inputPhone)) {
        echo json_encode(['success' => false, 'message' => 'Lead ID or phone required']);
        exit;
    }

    $found = false;
    foreach ($currentLeads as &$lead) {
        $leadPhone = preg_replace('/\D+/', '', $lead['phone'] ?? '');
        if (($lead['id'] ?? '') === $leadId || (!empty($inputPhone) && $leadPhone === $inputPhone)) {
            if (isset($input['phone'])) {
                $cleanP = preg_replace('/\D+/', '', $input['phone']);
                if (str_starts_with($cleanP, '0092')) $cleanP = substr($cleanP, 2);
                elseif (str_starts_with($cleanP, '03') && strlen($cleanP) === 11) $cleanP = '92' . substr($cleanP, 1);
                elseif (str_starts_with($cleanP, '3') && strlen($cleanP) === 10) $cleanP = '92' . $cleanP;
                if (!empty($cleanP)) {
                    $lead['phone'] = $cleanP;
                    $lead['display'] = '+' . $cleanP;
                }
            }
            if (isset($input['leadState'])) $lead['leadState'] = $input['leadState'];
            if (isset($input['status'])) $lead['status'] = $input['status'];
            if (array_key_exists('ownerId', $input)) $lead['ownerId'] = $input['ownerId'];
            if (array_key_exists('ownerName', $input)) $lead['ownerName'] = $input['ownerName'];
            if (isset($input['name']) && trim($input['name']) !== '') {
                $lead['name'] = trim($input['name']);
            }
            if (isset($input['email'])) $lead['email'] = $input['email'];
            if (isset($input['paidDetails'])) $lead['paidDetails'] = $input['paidDetails'];
            if (isset($input['notes'])) $lead['notes'] = $input['notes'];
            if (isset($input['verificationStatus'])) $lead['verificationStatus'] = $input['verificationStatus'];
            if (isset($input['verifiedBy'])) $lead['verifiedBy'] = $input['verifiedBy'];
            if (isset($input['verifiedAt'])) $lead['verifiedAt'] = $input['verifiedAt'];
            if (isset($input['rejectionReason'])) $lead['rejectionReason'] = $input['rejectionReason'];
            if (isset($input['unreadCount'])) $lead['unreadCount'] = (int)$input['unreadCount'];
            if (isset($input['lastActive'])) $lead['lastActive'] = $input['lastActive'];
            if (isset($input['newMessage'])) {
                if (!isset($lead['messages'])) $lead['messages'] = [];
                $newMsg = $input['newMessage'];
                $exists = false;
                if (!empty($newMsg['waMessageId'])) {
                    foreach ($lead['messages'] as $m) {
                        if (($m['waMessageId'] ?? '') === $newMsg['waMessageId']) {
                            $exists = true;
                            break;
                        }
                    }
                }
                if (!$exists) {
                    $lead['messages'][] = $newMsg;
                }
            }
            if (isset($input['updateMessage']) && is_array($input['updateMessage'])) {
                $update = $input['updateMessage'];
                foreach (($lead['messages'] ?? []) as &$message) {
                    if (($message['waMessageId'] ?? '') === ($update['waMessageId'] ?? '')) {
                        if (isset($update['deliveryStatus'])) $message['deliveryStatus'] = $update['deliveryStatus'];
                        if (array_key_exists('deliveryError', $update)) $message['deliveryError'] = $update['deliveryError'];
                        break;
                    }
                }
                unset($message);
            }
            $found = true;
            break;
        }
    }
    unset($lead);

    if (!$found && (!empty($inputPhone) || !empty($leadId))) {
        $targetPhone = !empty($inputPhone) ? $inputPhone : preg_replace('/\D+/', '', $leadId);
        $rawContactName = trim($input['name'] ?? '');
        $lowerC = strtolower($rawContactName);
        $contactName = (!empty($rawContactName) && $lowerC !== 'trader' && $lowerC !== 'alishba' && $lowerC !== 'new contact') ? $rawContactName : ('+' . $targetPhone);
        $newLead = [
            'id' => !empty($leadId) ? $leadId : ('lead_' . bin2hex(random_bytes(6))),
            'name' => $contactName,
            'phone' => $targetPhone,
            'display' => '+' . $targetPhone,
            'city' => 'Inbound',
            'network' => 'WhatsApp',
            'ownerId' => 'USR-ADMIN-01',
            'ownerName' => 'System Administrator',
            'leadState' => 'fresh',
            'status' => 'unread',
            'unreadCount' => 1,
            'interest' => 'Direct WhatsApp',
            'lastActive' => 'Just now',
            'notes' => 'Inbound contact automatically synchronized.',
            'messages' => isset($input['newMessage']) ? [$input['newMessage']] : []
        ];
        array_unshift($currentLeads, $newLead);
        $found = true;
    }

    if ($found) {
        saveStoredLeads($dataFile, $currentLeads);
        echo json_encode(['success' => true, 'message' => 'Lead updated successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Lead not found']);
    }
    exit;
}

// 2b. Add Custom Lead / Start New Chat
if ($action === 'add_lead') {
    $rawPhone = $input['phone'] ?? '';
    $cleanPhone = preg_replace('/\D+/', '', $rawPhone);
    if (str_starts_with($cleanPhone, '0092')) $cleanPhone = substr($cleanPhone, 2);
    elseif (str_starts_with($cleanPhone, '03') && strlen($cleanPhone) === 11) $cleanPhone = '92' . substr($cleanPhone, 1);
    elseif (str_starts_with($cleanPhone, '3') && strlen($cleanPhone) === 10) $cleanPhone = '92' . $cleanPhone;

    if (strlen($cleanPhone) < 10) {
        echo json_encode(['success' => false, 'message' => 'Valid phone number is required (at least 10 digits).']);
        exit;
    }

    $rawName = trim($input['name'] ?? '');
    $lower = strtolower($rawName);
    $name = (!empty($rawName) && $lower !== 'trader' && $lower !== 'alishba' && $lower !== 'new contact') ? $rawName : ('+' . $cleanPhone);

    foreach ($currentLeads as &$existing) {
        if ($existing['phone'] === $cleanPhone) {
            if (!empty($name) && $name !== $cleanPhone && (($existing['name'] ?? '') === $cleanPhone || empty($existing['name']))) {
                $existing['name'] = $name;
                saveStoredLeads($dataFile, $currentLeads);
            }
            echo json_encode(['success' => true, 'lead' => $existing, 'existing' => true]);
            exit;
        }
    }
    unset($existing);

    $leadId = $input['id'] ?? ('lead_' . bin2hex(random_bytes(6)));
    $newLead = [
        'id' => $leadId,
        'name' => $name,
        'phone' => $cleanPhone,
        'display' => '+' . $cleanPhone,
        'city' => 'Pakistan',
        'network' => 'WhatsApp',
        'ownerId' => 'USR-ADMIN-01',
        'ownerName' => 'System Administrator',
        'leadState' => 'ongoing',
        'status' => 'replied',
        'unreadCount' => 0,
        'interest' => 'Direct Outreach',
        'lastActive' => 'Just now',
        'notes' => 'Custom contact added for direct WhatsApp communication.',
        'messages' => []
    ];
    array_unshift($currentLeads, $newLead);
    saveStoredLeads($dataFile, $currentLeads);
    echo json_encode(['success' => true, 'lead' => $newLead, 'message' => 'New chat created successfully!']);
    exit;
}

// 3. Batch Append Imported Numbers
if ($action === 'import_leads') {
    $newLeads = $input['leads'] ?? [];
    if (!is_array($newLeads) || empty($newLeads)) {
        echo json_encode(['success' => false, 'message' => 'No leads provided']);
        exit;
    }

    $existingPhones = array_column($currentLeads, 'phone');
    $added = 0;

    foreach ($newLeads as $nl) {
        if (!in_array($nl['phone'], $existingPhones)) {
            $currentLeads[] = $nl;
            $existingPhones[] = $nl['phone'];
            $added++;
        }
    }

    saveStoredLeads($dataFile, $currentLeads);
    echo json_encode([
        'success' => true,
        'added'   => $added,
        'total'   => count($currentLeads)
    ]);
    exit;
}

// Default response
echo json_encode(['success' => true, 'leads' => $currentLeads]);
