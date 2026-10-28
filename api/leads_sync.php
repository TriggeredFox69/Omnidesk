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

// 1. Get All Leads
if ($action === 'get_leads') {
    echo json_encode([
        'success' => true,
        'leads'   => $currentLeads,
        'count'   => count($currentLeads)
    ]);
    exit;
}

// 2. Update Lead State, Ownership, or Notes
if ($action === 'update_lead') {
    $leadId = $input['id'] ?? '';
    if (empty($leadId)) {
        echo json_encode(['success' => false, 'message' => 'Lead ID required']);
        exit;
    }

    $found = false;
    foreach ($currentLeads as &$lead) {
        if (($lead['id'] ?? '') === $leadId) {
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
            if (isset($input['name'])) $lead['name'] = $input['name'];
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
                $lead['messages'][] = $input['newMessage'];
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
    $name = trim($input['name'] ?? 'New Contact');
    $cleanPhone = preg_replace('/\D+/', '', $rawPhone);
    if (str_starts_with($cleanPhone, '0092')) $cleanPhone = substr($cleanPhone, 2);
    elseif (str_starts_with($cleanPhone, '03') && strlen($cleanPhone) === 11) $cleanPhone = '92' . substr($cleanPhone, 1);
    elseif (str_starts_with($cleanPhone, '3') && strlen($cleanPhone) === 10) $cleanPhone = '92' . $cleanPhone;

    if (strlen($cleanPhone) < 10) {
        echo json_encode(['success' => false, 'message' => 'Valid phone number is required (at least 10 digits).']);
        exit;
    }

    foreach ($currentLeads as $existing) {
        if ($existing['phone'] === $cleanPhone) {
            echo json_encode(['success' => true, 'lead' => $existing, 'existing' => true]);
            exit;
        }
    }

    $newLead = [
        'id' => 'lead_' . bin2hex(random_bytes(6)),
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
