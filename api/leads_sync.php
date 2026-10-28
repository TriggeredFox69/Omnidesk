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
    ],
    [
        'id' => 'lead_1',
        'name' => 'Zeeshan Tariq',
        'phone' => '923008472910',
        'display' => '+92 300 8472910',
        'city' => 'Lahore',
        'network' => 'Jazz',
        'ownerId' => 'EMP-101',
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
            ['sender' => 'lead', 'text' => 'Theek hai, mujhe EasyPaisa account number share kar dein, main abhi activate karwana chahta hoon.', 'time' => '10:40 AM'],
            ['id' => 'msg_lead1_vn1', 'sender' => 'lead', 'type' => 'audio', 'duration' => '0:24', 'durationSeconds' => 24, 'text' => '🎙️ Voice note (0:24)', 'time' => '10:41 AM'],
            ['id' => 'msg_lead1_vn2', 'sender' => 'agent', 'agentName' => 'Hamza Khan', 'type' => 'audio', 'duration' => '0:18', 'durationSeconds' => 18, 'text' => '🎙️ Voice note (0:18)', 'time' => '10:42 AM']
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
            ['sender' => 'lead', 'text' => 'Mujhe demo video ya coupon code send karein.', 'time' => '09:46 AM'],
            ['id' => 'msg_lead2_vn1', 'sender' => 'lead', 'type' => 'audio', 'duration' => '0:35', 'durationSeconds' => 35, 'text' => '🎙️ Voice note (0:35)', 'time' => '09:48 AM']
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
        'leadState' => 'paid',
        'status' => 'paid',
        'unreadCount' => 0,
        'interest' => 'Nunno Starter (Paid Rs. 1,700)',
        'lastActive' => 'Yesterday',
        'notes' => 'PAID Rs. 1,700 via JazzCash. Activated Starter account.',
        'messages' => [
            ['sender' => 'agent', 'agentName' => 'Hamza Khan', 'text' => 'AoA Kashif! Nunno Starter sirf Rs. 1,700/mo mein live signals provide karta hai.', 'time' => 'Yesterday'],
            ['sender' => 'lead', 'text' => 'Maine PKR 1,700 JazzCash kar diye hain! Mera login access activate kar dein.', 'time' => 'Yesterday'],
            ['sender' => 'agent', 'agentName' => 'Hamza Khan', 'text' => 'Payment verified Kashif Bhai! Aapka account activate ho gaya hai. Welcome to Nunno VIP Alpha!', 'time' => 'Yesterday']
        ]
    ],
    [
        'id' => 'lead_5',
        'name' => 'M. Rizwan',
        'phone' => '923458765432',
        'display' => '+92 345 8765432',
        'city' => 'Faisalabad',
        'network' => 'Telenor',
        'ownerId' => null,
        'leadState' => 'fresh',
        'status' => 'replied',
        'unreadCount' => 1,
        'interest' => 'Nunno Whale VIP',
        'lastActive' => '3 hours ago',
        'notes' => 'Fund manager managing 10 BTC portfolio. Interested in Whale Tier.',
        'messages' => [
            ['sender' => 'lead', 'text' => 'Bhai aapka Whale order flow heatmap TradingView pe integrate ho sakta hai?', 'time' => '07:15 AM']
        ]
    ],
    [
        'id' => 'lead_6',
        'name' => 'Asad Siddiqui',
        'phone' => '923012345678',
        'display' => '+92 301 2345678',
        'city' => 'Multan',
        'network' => 'Jazz',
        'ownerId' => 'EMP-103',
        'leadState' => 'ongoing',
        'status' => 'unread',
        'unreadCount' => 1,
        'interest' => 'Nunno Pro',
        'lastActive' => '45 mins ago',
        'notes' => 'Discussing 30% discount coupon.',
        'messages' => [
            ['sender' => 'agent', 'agentName' => 'Ayesha Noor', 'text' => 'Salam Asad! Special 30% discount coupon code NUNNO30 valid hai.', 'time' => '10:00 AM'],
            ['sender' => 'lead', 'text' => 'Agar main 3 months ka aik sath loon toh koi extra discount milega?', 'time' => '10:14 AM']
        ]
    ],
    [
        'id' => 'lead_7',
        'name' => 'Omer Farooq',
        'phone' => '923319988776',
        'display' => '+92 331 9988776',
        'city' => 'Peshawar',
        'network' => 'Ufone',
        'ownerId' => 'EMP-104',
        'leadState' => 'useless',
        'status' => 'useless',
        'unreadCount' => 0,
        'interest' => 'None (Dead Lead)',
        'lastActive' => '2 days ago',
        'notes' => 'Wants completely free signals. Argued about pricing and refused payment. Marked as useless / won\'t pay.',
        'messages' => [
            ['sender' => 'agent', 'agentName' => 'Zain Ahmed', 'text' => 'Salam Omer Bhai! Nunno Pro platform pe 30% discount offer live hai.', 'time' => '2 days ago'],
            ['sender' => 'lead', 'text' => 'Mujhe free chahiye, paise nahi de sakta. Free signals group hai toh batao warna message mat karo.', 'time' => '2 days ago']
        ]
    ],
    [
        'id' => 'lead_8',
        'name' => 'Dr. Salman Khan',
        'phone' => '923401122334',
        'display' => '+92 340 1122334',
        'city' => 'Sialkot',
        'network' => 'Telenor',
        'ownerId' => 'EMP-101',
        'leadState' => 'paid',
        'status' => 'paid',
        'unreadCount' => 0,
        'interest' => 'Nunno Whale VIP (Paid Rs. 15,000)',
        'lastActive' => '3 days ago',
        'notes' => 'PAID Rs. 15,000 via Binance Pay USDT. Activated Whale VIP.',
        'messages' => [
            ['sender' => 'agent', 'agentName' => 'Hamza Khan', 'text' => 'Welcome to Nunno Whale VIP Dr. Salman! VIP Alpha channel access granted.', 'time' => '3 days ago']
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
