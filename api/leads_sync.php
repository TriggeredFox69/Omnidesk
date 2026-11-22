<?php
/**
 * Nunno WhatsApp — Centralized Leads & Multi-Agent State Synchronization
 * Ensures zero scattered data across team members on cPanel
 */

header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 60 * 60 * 24 * 7,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

// Leads are private company data: a real sign-in is required for every action.
require_once __DIR__ . '/session_user.php';
$sessionUser = chatlyRequireUser();
if ($sessionUser && isset($sessionUser['status']) && $sessionUser['status'] === 'suspended') {
    $blockedActions = ['save_lead', 'claim_lead', 'update_lead_state', 'mark_paid', 'delete_leads', 'delete_lead', 'clear_demo_data'];
    $reqAction = $_GET['action'] ?? (json_decode(file_get_contents('php://input'), true)['action'] ?? '');
    if (in_array($reqAction, $blockedActions)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Account access suspended. All workspace operations are locked.']);
        exit;
    }
}

require_once __DIR__ . '/tenant_manager.php';
$activeCompanyId = resolveActiveCompanyId();
$dataFile = getTenantLeadsFile($activeCompanyId);
$companyConfig = getTenantConfig($activeCompanyId);

// This endpoint never writes to $_SESSION: release the session lock now so the dozen
// parallel startup/poll requests are not serialized behind each other (PHP file sessions).
if (session_status() === PHP_SESSION_ACTIVE) session_write_close();

// Helper: Read current centralized leads
function getStoredLeads($dataFile) {
    if (file_exists($dataFile)) {
        $content = file_get_contents($dataFile);
        $decoded = json_decode($content, true);
        if (is_array($decoded)) {
            return $decoded;
        }
    }
    return null;
}

// Helper: Save centralized leads safely with atomic write and automatic rolling backups
function saveStoredLeads($dataFile, $leads) {
    global $activeCompanyId;
    $tempFile = $dataFile . '.tmp.' . uniqid();
    file_put_contents($tempFile, json_encode($leads, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    rename($tempFile, $dataFile);

    // Keep legacy data/leads.json mirrored for COMP-001 for backwards compatibility
    if ($activeCompanyId === 'COMP-001') {
        @file_put_contents(__DIR__ . '/../data/leads.json', json_encode($leads, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    // Automated rolling snapshot backup (keeps hourly / daily fallback)
    try {
        $backupDir = dirname($dataFile) . '/backups';
        if (!is_dir($backupDir)) {
            @mkdir($backupDir, 0755, true);
        }
        $todayBackup = $backupDir . '/leads_' . date('Y-m-d') . '.json';
        if (!file_exists($todayBackup) || (time() - filemtime($todayBackup) > 3600)) {
            @copy($dataFile, $todayBackup);
        }
    } catch (Exception $e) {}
}

// Seamless 2-Way Sync: Merge incoming & outgoing messages and ACKs from inbox.json into centralized leads
function syncInboxMessagesIntoLeads(&$leads, $dataFile) {
    global $activeCompanyId;
    $dataRoot = dirname(__DIR__) . '/data';
    $inboxFiles = [
        $dataRoot . '/whatsapp-bridge/inbox.json',
        $dataRoot . '/whatsapp-bridge/whatsapp-bridge/inbox.json',
        $dataRoot . '/inbox.json'
    ];

    // High performance mtime cache: skip file I/O & loops completely if no new messages were written
    $cacheFile = sys_get_temp_dir() . '/chatly_inbox_mtime_' . md5($dataFile);
    // Signature = every inbox file's mtime AND size (mtime alone has 1-second resolution, so two
    // writes in the same second looked identical and the second batch of messages was skipped).
    clearstatcache();
    $sigParts = [];
    foreach ($inboxFiles as $f) {
        $sigParts[] = file_exists($f) ? (@filemtime($f) . ':' . @filesize($f)) : '-';
    }
    $maxMtime = implode(',', $sigParts);   // (kept the old variable name: it is what gets cached)
    if (file_exists($cacheFile) && @file_get_contents($cacheFile) === $maxMtime) {
        return false;
    }

    $inboxMessages = [];
    foreach ($inboxFiles as $f) {
        if (file_exists($f)) {
            $raw = @file_get_contents($f);
            $parsed = json_decode($raw, true);
            if (is_array($parsed)) {
                // Every message is merged. (Only taking the last 150 lost replies whenever more
                // than 150 messages arrived between two polls, because the mtime cache below then
                // marks the inbox as processed.) The cache already skips this when nothing changed.
                foreach ($parsed as $m) {
                    $waId = $m['waMessageId'] ?? ($m['id'] ?? '');
                    if ($waId && !isset($inboxMessages[$waId])) {
                        $inboxMessages[$waId] = $m;
                    }
                }
            }
        }
    }
    if (empty($inboxMessages)) { @file_put_contents($cacheFile, (string)$maxMtime); return false; }

    $cleanDigits = function($p) {
        $c = preg_replace('/\D/', '', (string)$p);
        if (str_starts_with($c, '0092')) $c = substr($c, 2);
        elseif (str_starts_with($c, '03') && strlen($c) === 11) $c = '92' . substr($c, 1);
        elseif (str_starts_with($c, '3') && strlen($c) === 10) $c = '92' . $c;
        return $c;
    };

    // Index existing leads by clean phone
    $leadMap = [];
    foreach ($leads as $idx => $l) {
        $p = $cleanDigits($l['phone'] ?? '');
        if ($p) $leadMap[$p] = $idx;
    }

    // users.json id => company, to tell which company each closer's WhatsApp belongs to.
    $userCompany = [];
    $userRows = json_decode((string) @file_get_contents($dataRoot . '/users.json'), true);
    foreach ((is_array($userRows) ? $userRows : []) as $u) {
        if (!empty($u['id'])) $userCompany[(string) $u['id']] = chatlyUserCompany($u);
    }

    $modified = false;
    foreach ($inboxMessages as $m) {
        $mPhone = $cleanDigits($m['phone'] ?? '');
        if (!$mPhone || strlen($mPhone) < 8) continue;

        // The WhatsApp feed is shared by every company. A message belongs here only if
        // it came through one of THIS company's closers, or is on one of its leads.
        $via = (string) ($m['viaUserId'] ?? '');
        if ($via !== '' && ($userCompany[$via] ?? 'COMP-001') !== $activeCompanyId) continue;

        // Only messages on an existing lead are merged; no lead is ever created from the
        // WhatsApp feed. Leads come from Chatly itself (campaign sends, Sales Desk). Creating
        // one for every chat a closer had on their phone filled the CRM with personal chats.
        $targetIdx = $leadMap[$mPhone] ?? null;
        if ($targetIdx === null) continue;

        $lead = &$leads[$targetIdx];
        if (!isset($lead['messages']) || !is_array($lead['messages'])) {
            $lead['messages'] = [];
        }

        $waMsgId = $m['waMessageId'] ?? $m['id'];
        $existingMsgIdx = null;
        foreach ($lead['messages'] as $i => $lm) {
            if (($lm['waMessageId'] ?? '') === $waMsgId || ($lm['id'] ?? '') === $waMsgId) {
                $existingMsgIdx = $i;
                break;
            }
        }

        $deliveryStatus = $m['deliveryStatus'] ?? 'delivered';
        if ($existingMsgIdx !== null) {
            // Update deliveryStatus if it transitioned (e.g. accepted -> delivered -> read)
            if (($lead['messages'][$existingMsgIdx]['deliveryStatus'] ?? '') !== $deliveryStatus && $deliveryStatus !== 'accepted') {
                $lead['messages'][$existingMsgIdx]['deliveryStatus'] = $deliveryStatus;
                $modified = true;
            }
        } else {
            $msgEntry = [
                'id' => $m['id'] ?? ('msg_' . uniqid()),
                'waMessageId' => $waMsgId,
                'deliveryStatus' => $deliveryStatus,
                'sender' => $m['sender'] ?? 'agent',
                'agentName' => $m['agentName'] ?? 'Agent',
                'text' => $m['text'] ?? '',
                'type' => $m['type'] ?? 'text',
                'timestamp' => $m['timestamp'] ?? time(),
                'time' => date('H:i', $m['timestamp'] ?? time()),
                'hasMedia' => !empty($m['hasMedia']),
                'duration' => $m['duration'] ?? '',
                'durationSeconds' => $m['durationSeconds'] ?? 0
            ];
            $lead['messages'][] = $msgEntry;
            usort($lead['messages'], function($a, $b) {
                return ($a['timestamp'] ?? 0) - ($b['timestamp'] ?? 0);
            });
            $lead['lastMessageAt'] = max($lead['lastMessageAt'] ?? 0, $m['timestamp'] ?? time());
            $lead['lastActive'] = 'Just now';
            if (($m['sender'] ?? '') === 'lead') {
                $lead['status'] = 'unread';
                $lead['unreadCount'] = ($lead['unreadCount'] ?? 0) + 1;
            } else {
                $lead['status'] = 'replied';
            }
            if ($lead['leadState'] === 'fresh') {
                $lead['leadState'] = 'ongoing';
            }
            $modified = true;
        }
    }

    if ($modified) {
        saveStoredLeads($dataFile, $leads);
    }
    // Mark this inbox version as merged only after the merge and save went through.
    @file_put_contents($cacheFile, (string)$maxMtime);
    return $modified;
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

const CHATLY_SUMMARY_MESSAGES = 12;   // messages per lead in the light background sync

// May this user (a closer / team lead) see this lead? Admins see everything (checked by callers).
function chatlyLeadVisibleTo($l, $myId, $myUser, $myNameClean) {
    $ownerId = $l['ownerId'] ?? ($l['assignedOwnerId'] ?? ($l['assignedCloser']['id'] ?? ''));
    $ownerUser = strtolower($l['ownerUsername'] ?? ($l['assignedOwnerUsername'] ?? ($l['assignedCloser']['username'] ?? '')));
    $ownerNameClean = preg_replace('/\s+/', '', strtolower($l['ownerName'] ?? ($l['assignedOwnerName'] ?? '')));

    // If lead is assigned to someone else, reject completely
    if ($ownerId && $myId && $ownerId !== $myId) return false;
    if ($ownerUser && $myUser && $ownerUser !== $myUser) return false;
    if ($ownerNameClean && $myNameClean && $ownerNameClean !== $myNameClean && strpos($ownerNameClean, $myNameClean) === false && strpos($myNameClean, $ownerNameClean) === false) {
        return false;
    }

    if ($ownerId && $myId && $ownerId === $myId) return true;
    if ($myUser && $ownerUser && $ownerUser === $myUser) return true;
    if ($myNameClean && $ownerNameClean && ($ownerNameClean === $myNameClean || strpos($ownerNameClean, $myNameClean) !== false)) return true;
    if (!empty($l['originalOwnerId']) && $l['originalOwnerId'] === $myId) return true;
    return false;
}

// Revision tag of everything get_leads reads for this user. The dashboard polls get_leads
// every 5 seconds from every open tab; when the tag it sends back still matches, nothing
// changed, so we answer "unchanged" without reading or re-sending the whole CRM.
function chatlyLeadsRevision($dataFile, $sessionUser) {
    $dataRoot = dirname(__DIR__) . '/data';
    $parts = [$dataFile, (string) ($sessionUser['id'] ?? ''), (string) ($sessionUser['role'] ?? '')];
    foreach ([$dataFile, $dataRoot . '/whatsapp-bridge/inbox.json', $dataRoot . '/whatsapp-bridge/whatsapp-bridge/inbox.json', $dataRoot . '/inbox.json'] as $f) {
        $parts[] = is_file($f) ? (filemtime($f) . ':' . filesize($f)) : '-';
    }
    return substr(md5(implode('|', $parts)), 0, 16);
}
if (($_GET['action'] ?? '') === 'get_leads' && !empty($_GET['rev'])) {
    clearstatcache();
    if (hash_equals(chatlyLeadsRevision($dataFile, $sessionUser), (string) $_GET['rev'])) {
        echo json_encode(['success' => true, 'unchanged' => true, 'rev' => (string) $_GET['rev'], 'company_id' => $activeCompanyId]);
        exit;
    }
}

// Initialize if not present
$currentLeads = getStoredLeads($dataFile);
if ($currentLeads === null) {
    $initLeads = ($activeCompanyId === 'COMP-001') ? $defaultLeads : [];
    saveStoredLeads($dataFile, $initLeads);
    $currentLeads = $initLeads;
}

// Router
$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$action = $input['action'] ?? $_GET['action'] ?? 'get_leads';

// Clear all demo data, wipe inboxes, and reset to 03318036576
if (($action === 'clear_demo_data' || $action === 'wipe_demo') && !chatlyIsCompanyAdmin($sessionUser) && !chatlyIsSuperAdmin($sessionUser)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Only company administrators can clear data.']);
    exit;
}
if (($action === 'seed_demo_campaign' || $action === 'seed_demo') && !chatlyIsSuperAdmin($sessionUser)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Demo data can only be seeded by the super admin.']);
    exit;
}
if ($action === 'clear_demo_data' || $action === 'wipe_demo') {
    $selfOnly = [
        [
            'id' => 'lead_self',
            'name' => 'My WhatsApp (Self Test)',
            'phone' => '923318036576',
            'display' => '+92 331 8036576',
            'city' => 'Connected Number',
            'network' => 'Ufone',
            'company_id' => $activeCompanyId,
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
        . " WHATSAPP_DATA_DIR=" . escapeshellarg($dataDir) . " WHATSAPP_ENGINE=baileys "
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

// Permanently delete single or multiple conversations
if ($action === 'delete_leads' || $action === 'delete_lead') {
    $leadIds = $input['ids'] ?? [];
    if (empty($leadIds) && !empty($input['id'])) {
        $leadIds = [$input['id']];
    }
    if (empty($leadIds)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'No lead IDs provided for deletion']);
        exit;
    }

    // Extract all phone numbers associated with the targeted leads
    $phonesToDelete = [];
    foreach ($currentLeads as $l) {
        if (in_array($l['id'], $leadIds)) {
            $clean = preg_replace('/\D/', '', $l['phone'] ?? '');
            if ($clean) $phonesToDelete[] = $clean;
        }
    }
    foreach ($leadIds as $id) {
        $clean = preg_replace('/\D/', '', $id);
        if (strlen($clean) >= 9) {
            $phonesToDelete[] = $clean;
        }
    }
    $phonesToDelete = array_values(array_unique($phonesToDelete));

    $initialCount = count($currentLeads);
    $currentLeads = array_values(array_filter($currentLeads, function($l) use ($leadIds, $phonesToDelete) {
        if (in_array($l['id'], $leadIds)) return false;
        $clean = preg_replace('/\D/', '', $l['phone'] ?? '');
        if ($clean && in_array($clean, $phonesToDelete)) return false;
        return true;
    }));
    $deletedCount = $initialCount - count($currentLeads);
    saveStoredLeads($dataFile, $currentLeads);

    // Also purge deleted numbers from all bridge & cloud inbox files so they never resurrect
    $dataRoot = dirname(__DIR__) . '/data';
    $inboxFiles = [
        $dataRoot . '/whatsapp-bridge/inbox.json',
        $dataRoot . '/whatsapp-bridge/whatsapp-bridge/inbox.json',
        $dataRoot . '/whatsapp-bridge/local/inbox.json',
        $dataRoot . '/whatsapp-cloud/inbox.json',
        $dataRoot . '/inbox.json'
    ];
    foreach ($inboxFiles as $inboxFile) {
        if (file_exists($inboxFile)) {
            $inbox = json_decode(@file_get_contents($inboxFile), true) ?: [];
            $newInbox = array_values(array_filter($inbox, function($m) use ($phonesToDelete) {
                $sender = preg_replace('/\D/', '', $m['from'] ?? $m['sender'] ?? '');
                return !in_array($sender, $phonesToDelete);
            }));
            @file_put_contents($inboxFile, json_encode($newInbox, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }
    }

    echo json_encode([
        'success' => true,
        'deleted_count' => $deletedCount,
        'remaining_count' => count($currentLeads),
        'message' => "Successfully deleted {$deletedCount} conversation(s) permanently."
    ]);
    exit;
}

// Seed Demo Campaign Leads with Voice Notes & Real-Time Sync Structure
if ($action === 'seed_demo_campaign' || $action === 'seed_demo') {
    $shouldClear = !empty($_GET['clear']) || !empty($_GET['reset']) || !empty($_POST['clear']);
    if ($shouldClear) {
        $currentLeads = [];
    }

    $askariId = 'EMP-TM-MWMK43N9';
    $askariName = 'Askari Raza';
    $askariUser = 'askari';

    $erijId = 'EMP-TM-ERIJ';
    $erijName = 'Syeda Erij Fatima';
    $erijUser = 'erij';

    $demoLeads = [
        // 5 LEADS ALLOTTED TO ASKARI RAZA
        [
            'id' => 'lead_wa_923245678910',
            'company_id' => $activeCompanyId,
            'name' => 'Faisal Raza (+923245678910)',
            'phone' => '923245678910',
            'display' => '+92 324 5678910',
            'city' => 'Multan',
            'network' => 'WhatsApp',
            'ownerId' => $askariId,
            'ownerName' => $askariName,
            'ownerUsername' => $askariUser,
            'leadState' => 'ongoing',
            'status' => 'replied',
            'unreadCount' => 0,
            'interest' => 'Nunno Cloud Scalper Pro',
            'lastActive' => 'Just now',
            'lastMessageAt' => time() - 60,
            'dealAmount' => 5000,
            'notes' => 'Outreach Campaign lead. High intent buyer.',
            'messages' => [
                [
                    'id' => 'msg_demo_c1',
                    'waMessageId' => 'true_923245678910@s.whatsapp.net_C1',
                    'deliveryStatus' => 'read',
                    'sender' => 'agent',
                    'agentName' => $askariName,
                    'text' => 'Hello Faisal Raza, welcome to Nunno Cloud automated Forex & Crypto desk! Let me know if you would like to test our signals.',
                    'type' => 'text',
                    'timestamp' => time() - 1800,
                    'time' => date('H:i', time() - 1800)
                ],
                [
                    'id' => 'msg_demo_c2',
                    'waMessageId' => 'false_923245678910@s.whatsapp.net_C2',
                    'deliveryStatus' => 'delivered',
                    'sender' => 'lead',
                    'text' => 'AoA Askari bro, I want to subscribe to Nunno Pro. Please share payment details.',
                    'type' => 'text',
                    'timestamp' => time() - 1200,
                    'time' => date('H:i', time() - 1200)
                ],
                [
                    'id' => 'msg_demo_c3',
                    'waMessageId' => 'true_923245678910@s.whatsapp.net_C3',
                    'deliveryStatus' => 'read',
                    'sender' => 'agent',
                    'agentName' => $askariName,
                    'text' => 'Walaikum Assalam Faisal bhai! EasyPaisa / JazzCash account: 0331-8036576 (Title: Nunno Official). Share screenshot once done.',
                    'type' => 'text',
                    'timestamp' => time() - 600,
                    'time' => date('H:i', time() - 600)
                ],
                [
                    'id' => 'msg_demo_vn1',
                    'waMessageId' => 'false_923245678910@s.whatsapp.net_VN1',
                    'deliveryStatus' => 'delivered',
                    'sender' => 'lead',
                    'text' => '🎙️ Voice note (0:18): Bhai EasyPaisa pe 5,000 PKR bhej diya hai, receipt check karein',
                    'type' => 'audio',
                    'duration' => '0:18',
                    'durationSeconds' => 18,
                    'hasMedia' => true,
                    'timestamp' => time() - 120,
                    'time' => date('H:i', time() - 120)
                ]
            ]
        ],
        [
            'id' => 'lead_wa_923122759900',
            'company_id' => $activeCompanyId,
            'name' => 'Naveed Sheikh (+923122759900)',
            'phone' => '923122759900',
            'display' => '+92 312 2759900',
            'city' => 'Karachi',
            'network' => 'WhatsApp',
            'ownerId' => $askariId,
            'ownerName' => $askariName,
            'ownerUsername' => $askariUser,
            'leadState' => 'ongoing',
            'status' => 'unread',
            'unreadCount' => 1,
            'interest' => 'Forex VIP Lifetime',
            'lastActive' => '2m ago',
            'lastMessageAt' => time() - 120,
            'dealAmount' => 15000,
            'notes' => 'Hot prospect interested in VIP lifetime signals.',
            'messages' => [
                [
                    'id' => 'msg_nav_1',
                    'waMessageId' => 'true_923122759900@s.whatsapp.net_1',
                    'deliveryStatus' => 'read',
                    'sender' => 'agent',
                    'agentName' => $askariName,
                    'text' => 'Assalam o Alaikum Naveed bhai, special 30% discount on Whale Lifetime package ending tonight!',
                    'type' => 'text',
                    'timestamp' => time() - 900,
                    'time' => date('H:i', time() - 900)
                ],
                [
                    'id' => 'msg_nav_vn1',
                    'waMessageId' => 'false_923122759900@s.whatsapp.net_VN1',
                    'deliveryStatus' => 'delivered',
                    'sender' => 'lead',
                    'text' => '🎙️ Voice note (0:24): Assalam o Alaikum, live proofs aur accuracy stats share kar dein',
                    'type' => 'audio',
                    'duration' => '0:24',
                    'durationSeconds' => 24,
                    'hasMedia' => true,
                    'timestamp' => time() - 120,
                    'time' => date('H:i', time() - 120)
                ]
            ]
        ],
        [
            'id' => 'lead_wa_923332190847',
            'company_id' => $activeCompanyId,
            'name' => 'Tariq Enterprise (+923332190847)',
            'phone' => '923332190847',
            'display' => '+92 333 2190847',
            'city' => 'Lahore',
            'network' => 'WhatsApp',
            'ownerId' => $askariId,
            'ownerName' => $askariName,
            'ownerUsername' => $askariUser,
            'leadState' => 'fresh',
            'status' => 'replied',
            'unreadCount' => 0,
            'interest' => 'Campaign Outreach',
            'lastActive' => '10m ago',
            'lastMessageAt' => time() - 600,
            'notes' => 'New lead from Nunno Multi-Device Campaign.',
            'messages' => [
                [
                    'id' => 'msg_tar_1',
                    'waMessageId' => 'true_923332190847@s.whatsapp.net_1',
                    'deliveryStatus' => 'delivered',
                    'sender' => 'agent',
                    'agentName' => $askariName,
                    'text' => 'Exclusive trade signals and automated bot copy trading available now. Interested in free trial?',
                    'type' => 'text',
                    'timestamp' => time() - 600,
                    'time' => date('H:i', time() - 600)
                ]
            ]
        ],
        [
            'id' => 'lead_wa_923009876543',
            'company_id' => $activeCompanyId,
            'name' => 'Usman Butt (+923009876543)',
            'phone' => '923009876543',
            'display' => '+92 300 9876543',
            'city' => 'Islamabad',
            'network' => 'WhatsApp',
            'ownerId' => $askariId,
            'ownerName' => $askariName,
            'ownerUsername' => $askariUser,
            'leadState' => 'pending_verification',
            'status' => 'pending_verification',
            'verificationStatus' => 'pending',
            'unreadCount' => 0,
            'interest' => 'Nunno Scalper VIP Pro',
            'lastActive' => '5m ago',
            'lastMessageAt' => time() - 300,
            'dealAmount' => 12000,
            'notes' => 'Transferred via JazzCash Rs. 12,000. TID: JC99882211',
            'paidDetails' => [
                'amount' => 12000,
                'commission' => 1200,
                'plan' => 'Nunno Scalper VIP Pro',
                'closedBy' => $askariName,
                'closerId' => $askariId,
                'closerUsername' => $askariUser,
                'method' => 'JazzCash',
                'trxId' => 'JC99882211',
                'notes' => 'JazzCash Deposit TID: JC99882211',
                'markedAt' => date('c', time() - 300)
            ],
            'messages' => [
                [
                    'id' => 'msg_usm_1',
                    'waMessageId' => 'true_923009876543@s.whatsapp.net_1',
                    'deliveryStatus' => 'read',
                    'sender' => 'agent',
                    'agentName' => $askariName,
                    'text' => 'Salam Usman bhai, JazzCash account details bhej di hain.',
                    'type' => 'text',
                    'timestamp' => time() - 600,
                    'time' => date('H:i', time() - 600)
                ],
                [
                    'id' => 'msg_usm_2',
                    'waMessageId' => 'false_923009876543@s.whatsapp.net_2',
                    'deliveryStatus' => 'delivered',
                    'sender' => 'lead',
                    'text' => 'Done Askari bhai! Sent 12,000 PKR to JazzCash. TID is JC99882211. Kindly verify and activate VIP signals.',
                    'type' => 'text',
                    'timestamp' => time() - 300,
                    'time' => date('H:i', time() - 300)
                ]
            ]
        ],
        [
            'id' => 'lead_wa_923451122334',
            'company_id' => $activeCompanyId,
            'name' => 'Kamran Siddiqui (+923451122334)',
            'phone' => '923451122334',
            'display' => '+92 345 1122334',
            'city' => 'Rawalpindi',
            'network' => 'WhatsApp',
            'ownerId' => $askariId,
            'ownerName' => $askariName,
            'ownerUsername' => $askariUser,
            'leadState' => 'paid',
            'status' => 'paid',
            'verificationStatus' => 'verified',
            'unreadCount' => 0,
            'interest' => 'Pro Trader Standard',
            'lastActive' => '1h ago',
            'lastMessageAt' => time() - 3600,
            'dealAmount' => 8000,
            'notes' => 'Successfully closed deal. Paid via Bank Alfalah.',
            'messages' => [
                [
                    'id' => 'msg_kam_1',
                    'sender' => 'agent',
                    'agentName' => $askariName,
                    'text' => '🟢 PAYMENT VERIFIED & APPROVED (DEAL WON)! Welcome to Nunno Trading Club.',
                    'type' => 'text',
                    'timestamp' => time() - 3600,
                    'time' => date('H:i', time() - 3600)
                ]
            ]
        ],

        // 5 LEADS ALLOTTED TO SYEDA ERIJ FATIMA
        [
            'id' => 'lead_wa_923351234567',
            'company_id' => $activeCompanyId,
            'name' => 'Zeeshan Haider (+923351234567)',
            'phone' => '923351234567',
            'display' => '+92 335 1234567',
            'city' => 'Karachi',
            'network' => 'WhatsApp',
            'ownerId' => $erijId,
            'ownerName' => $erijName,
            'ownerUsername' => $erijUser,
            'leadState' => 'ongoing',
            'status' => 'replied',
            'unreadCount' => 0,
            'interest' => 'Crypto Scalper Elite',
            'lastActive' => 'Just now',
            'lastMessageAt' => time() - 90,
            'dealAmount' => 6000,
            'notes' => 'Allotted to Erij. Active dialogue.',
            'messages' => [
                [
                    'id' => 'msg_erij_z1',
                    'waMessageId' => 'true_923351234567@s.whatsapp.net_1',
                    'deliveryStatus' => 'read',
                    'sender' => 'agent',
                    'agentName' => $erijName,
                    'text' => 'Salam Zeeshan bhai! Nunno Automated Scalper packages mein interested hain aap?',
                    'type' => 'text',
                    'timestamp' => time() - 1500,
                    'time' => date('H:i', time() - 1500)
                ],
                [
                    'id' => 'msg_erij_z2',
                    'waMessageId' => 'false_923351234567@s.whatsapp.net_2',
                    'deliveryStatus' => 'delivered',
                    'sender' => 'lead',
                    'text' => 'Ji Erij sister, monthly signals aur automation details share kar dain.',
                    'type' => 'text',
                    'timestamp' => time() - 90,
                    'time' => date('H:i', time() - 90)
                ]
            ]
        ],
        [
            'id' => 'lead_wa_923159988776',
            'company_id' => $activeCompanyId,
            'name' => 'Maryam Qureshi (+923159988776)',
            'phone' => '923159988776',
            'display' => '+92 315 9988776',
            'city' => 'Lahore',
            'network' => 'WhatsApp',
            'ownerId' => $erijId,
            'ownerName' => $erijName,
            'ownerUsername' => $erijUser,
            'leadState' => 'ongoing',
            'status' => 'unread',
            'unreadCount' => 1,
            'interest' => 'Forex Swing Masters',
            'lastActive' => '4m ago',
            'lastMessageAt' => time() - 240,
            'dealAmount' => 7500,
            'notes' => 'High interest in copy trading.',
            'messages' => [
                [
                    'id' => 'msg_erij_m1',
                    'waMessageId' => 'false_923159988776@s.whatsapp.net_VN1',
                    'deliveryStatus' => 'delivered',
                    'sender' => 'lead',
                    'text' => '🎙️ Voice note (0:15): Assalam o Alaikum, fees structure EasyPaisa pe payable hai?',
                    'type' => 'audio',
                    'duration' => '0:15',
                    'durationSeconds' => 15,
                    'hasMedia' => true,
                    'timestamp' => time() - 240,
                    'time' => date('H:i', time() - 240)
                ]
            ]
        ],
        [
            'id' => 'lead_wa_923023344556',
            'company_id' => $activeCompanyId,
            'name' => 'Bilal Ahmed (+923023344556)',
            'phone' => '923023344556',
            'display' => '+92 302 3344556',
            'city' => 'Faisalabad',
            'network' => 'WhatsApp',
            'ownerId' => $erijId,
            'ownerName' => $erijName,
            'ownerUsername' => $erijUser,
            'leadState' => 'fresh',
            'status' => 'unread',
            'unreadCount' => 0,
            'interest' => 'Campaign Dispatch',
            'lastActive' => '15m ago',
            'lastMessageAt' => time() - 900,
            'notes' => 'Dispatched via Erij device.',
            'messages' => [
                [
                    'id' => 'msg_erij_b1',
                    'waMessageId' => 'true_923023344556@s.whatsapp.net_1',
                    'deliveryStatus' => 'delivered',
                    'sender' => 'agent',
                    'agentName' => $erijName,
                    'text' => 'Assalam o Alaikum! Nunno Cloud automated Forex & Crypto signals desk se rabta kar rahe hain.',
                    'type' => 'text',
                    'timestamp' => time() - 900,
                    'time' => date('H:i', time() - 900)
                ]
            ]
        ],
        [
            'id' => 'lead_wa_923145566778',
            'company_id' => $activeCompanyId,
            'name' => 'Imran Abbasi (+923145566778)',
            'phone' => '923145566778',
            'display' => '+92 314 5566778',
            'city' => 'Peshawar',
            'network' => 'WhatsApp',
            'ownerId' => $erijId,
            'ownerName' => $erijName,
            'ownerUsername' => $erijUser,
            'leadState' => 'pending_verification',
            'status' => 'pending_verification',
            'verificationStatus' => 'pending',
            'unreadCount' => 0,
            'interest' => 'Nunno VIP Annual',
            'lastActive' => '8m ago',
            'lastMessageAt' => time() - 480,
            'dealAmount' => 10000,
            'notes' => 'Transferred via EasyPaisa Rs. 10,000. TID: EP55443322',
            'paidDetails' => [
                'amount' => 10000,
                'commission' => 1000,
                'plan' => 'Nunno VIP Annual',
                'closedBy' => $erijName,
                'closerId' => $erijId,
                'closerUsername' => $erijUser,
                'method' => 'EasyPaisa',
                'trxId' => 'EP55443322',
                'notes' => 'EasyPaisa Deposit TID: EP55443322',
                'markedAt' => date('c', time() - 480)
            ],
            'messages' => [
                [
                    'id' => 'msg_erij_i1',
                    'sender' => 'lead',
                    'text' => 'Sister Erij, 10,000 PKR EasyPaisa par transfer kar diye hain. TID: EP55443322.',
                    'type' => 'text',
                    'timestamp' => time() - 480,
                    'time' => date('H:i', time() - 480)
                ]
            ]
        ],
        [
            'id' => 'lead_wa_923214433221',
            'company_id' => $activeCompanyId,
            'name' => 'Dr. Adnan Tariq (+923214433221)',
            'phone' => '923214433221',
            'display' => '+92 321 4433221',
            'city' => 'Quetta',
            'network' => 'WhatsApp',
            'ownerId' => $erijId,
            'ownerName' => $erijName,
            'ownerUsername' => $erijUser,
            'leadState' => 'paid',
            'status' => 'paid',
            'verificationStatus' => 'verified',
            'unreadCount' => 0,
            'interest' => 'Whale Lifetime VIP',
            'lastActive' => '2h ago',
            'lastMessageAt' => time() - 7200,
            'dealAmount' => 15000,
            'notes' => 'Closed by Erij. Verified deal.',
            'messages' => [
                [
                    'id' => 'msg_erij_a1',
                    'sender' => 'agent',
                    'agentName' => $erijName,
                    'text' => '🟢 PAYMENT VERIFIED & APPROVED (DEAL WON)! Lifetime membership confirmed.',
                    'type' => 'text',
                    'timestamp' => time() - 7200,
                    'time' => date('H:i', time() - 7200)
                ]
            ]
        ]
    ];

    if ($shouldClear) {
        $currentLeads = $demoLeads;
    } else {
        $existingIds = array_column($currentLeads, 'id');
        foreach ($demoLeads as $dl) {
            $found = false;
            foreach ($currentLeads as &$cl) {
                if ($cl['id'] === $dl['id'] || ($cl['phone'] ?? '') === $dl['phone']) {
                    $cl = array_merge($cl, $dl);
                    $found = true;
                    break;
                }
            }
            unset($cl);
            if (!$found) {
                array_unshift($currentLeads, $dl);
            }
        }
    }

    saveStoredLeads($dataFile, $currentLeads);
    echo json_encode([
        'success' => true,
        'message' => 'Demo campaign seeded: 10 leads (5 for Askari, 5 for Erij) with conversations, voice notes, and pending deals.',
        'leads_count' => count($currentLeads)
    ]);
    exit;
}

// 0b. Get All Pending Verification Deals for Admin Audit
if ($action === 'get_pending_deals') {
    $pending = [];
    foreach ($currentLeads as $l) {
        $state = strtolower($l['leadState'] ?? ($l['status'] ?? ''));
        $verifStatus = strtolower($l['verificationStatus'] ?? '');
        if ($state === 'pending_verification' || $verifStatus === 'pending') {
            $pending[] = $l;
        }
    }
    echo json_encode([
        'success' => true,
        'deals' => $pending,
        'count' => count($pending)
    ]);
    exit;
}

// 0c. Admin Action: Verify Deal & Release Commission to Closer Wallet
if ($action === 'verify_deal') {
    $userRole = strtolower($sessionUser['role'] ?? '');
    if (!in_array($userRole, ['admin', 'superadmin'], true)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Only administrators can verify deals and release commissions.']);
        exit;
    }

    $leadId = $input['id'] ?? '';
    if (empty($leadId)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Lead ID is required.']);
        exit;
    }

    $foundLead = null;
    foreach ($currentLeads as &$l) {
        if (($l['id'] ?? '') === $leadId || ($l['phone'] ?? '') === $leadId) {
            $l['leadState'] = 'paid';
            $l['status'] = 'paid';
            $l['verificationStatus'] = 'verified';
            $l['verifiedBy'] = $sessionUser['name'] ?? 'System Administrator';
            $l['verifiedAt'] = date('Y-m-d H:i:s');

            $amount = (int)($l['paidDetails']['amount'] ?? ($l['dealAmount'] ?? 5000));
            $commission = (int)($l['paidDetails']['commission'] ?? 1500);
            $plan = $l['paidDetails']['plan'] ?? ($l['interest'] ?? 'Nunno Pro');
            $closerId = $l['originalOwnerId'] ?? ($l['paidDetails']['closedById'] ?? ($l['ownerId'] ?? ''));
            $closerName = $l['originalOwnerName'] ?? ($l['paidDetails']['closedBy'] ?? ($l['ownerName'] ?? 'Closer'));

            // Credit closer commission in data/users.json
            if ($closerId) {
                $usersFile = __DIR__ . '/../data/users.json';
                if (file_exists($usersFile)) {
                    $users = json_decode(file_get_contents($usersFile), true) ?: [];
                    foreach ($users as &$u) {
                        if (($u['id'] ?? '') === $closerId || strtolower($u['username'] ?? '') === strtolower($closerId)) {
                            $u['commission_balance'] = ((int)($u['commission_balance'] ?? 0)) + $commission;
                            $u['total_sales_amount'] = ((int)($u['total_sales_amount'] ?? 0)) + $amount;
                            $u['deals_won_count'] = ((int)($u['deals_won_count'] ?? 0)) + 1;
                            break;
                        }
                    }
                    unset($u);
                    file_put_contents($usersFile, json_encode($users, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                }
            }

            // Append official confirmation to messages
            if (!isset($l['messages']) || !is_array($l['messages'])) $l['messages'] = [];
            $l['messages'][] = [
                'id' => 'verif_' . time(),
                'sender' => 'agent',
                'agentName' => 'Security Audit',
                'text' => "🟢 PAYMENT VERIFIED & COMMISSION RELEASED!\n• Deal: {$plan} (Rs. " . number_format($amount) . ")\n• Commission Released: Rs. " . number_format($commission) . " to {$closerName}\n• Approved by: " . ($sessionUser['name'] ?? 'System Administrator'),
                'time' => date('H:i'),
                'timestamp' => time()
            ];

            $foundLead = $l;
            break;
        }
    }
    unset($l);

    if ($foundLead) {
        saveStoredLeads($dataFile, $currentLeads);
        echo json_encode([
            'success' => true,
            'message' => 'Deal verified successfully! Commission released to closer wallet.',
            'lead' => $foundLead
        ]);
    } else {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Lead not found.']);
    }
    exit;
}

// 0d. Admin Action: Reject Fake Transaction
if ($action === 'reject_deal') {
    $userRole = strtolower($sessionUser['role'] ?? '');
    if (!in_array($userRole, ['admin', 'superadmin'], true)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Only administrators can reject deals.']);
        exit;
    }

    $leadId = $input['id'] ?? '';
    $reason = trim($input['reason'] ?? 'Fake slip or payment not received on banking portal');

    $foundLead = null;
    foreach ($currentLeads as &$l) {
        if (($l['id'] ?? '') === $leadId || ($l['phone'] ?? '') === $leadId) {
            $l['leadState'] = !empty($l['ownerId']) ? 'ongoing' : 'fresh';
            $l['status'] = !empty($l['ownerId']) ? 'replied' : 'unread';
            $l['verificationStatus'] = 'rejected';
            $l['rejectionReason'] = $reason;
            $l['rejectedBy'] = $sessionUser['name'] ?? 'System Administrator';
            $l['rejectedAt'] = date('Y-m-d H:i:s');
            $l['paidDetails'] = null;

            if (!isset($l['messages']) || !is_array($l['messages'])) $l['messages'] = [];
            $l['messages'][] = [
                'id' => 'rej_' . time(),
                'sender' => 'agent',
                'agentName' => 'Security Audit',
                'text' => "🔴 TRANSACTION REJECTED (AUDIT NOTICE)\n• Reason: {$reason}\n• Audited by: " . ($sessionUser['name'] ?? 'System Administrator') . "\n• Status reverted to Ongoing.",
                'time' => date('H:i'),
                'timestamp' => time()
            ];

            $foundLead = $l;
            break;
        }
    }
    unset($l);

    if ($foundLead) {
        saveStoredLeads($dataFile, $currentLeads);
        echo json_encode([
            'success' => true,
            'message' => 'Transaction rejected and lead reverted to ongoing.',
            'lead' => $foundLead
        ]);
    } else {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Lead not found.']);
    }
    exit;
}

// 1. Get All Leads
if ($action === 'get_leads') {
    syncInboxMessagesIntoLeads($currentLeads, $dataFile);
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
    $leadsToReturn = $currentLeads;
    $myId = $sessionUser['id'] ?? ($_GET['user_id'] ?? '');
    $myUser = strtolower($sessionUser['username'] ?? ($_GET['username'] ?? ''));
    $userRole = strtolower($sessionUser['role'] ?? ($_GET['user_role'] ?? 'member'));
    $myNameClean = preg_replace('/\s+/', '', strtolower($sessionUser['name'] ?? ($_GET['user_name'] ?? '')));

    // Strict Closer & Team Lead Isolation:
    // Only administrators in executive mode see company-wide chats.
    // Every Sales Closer (TM) and Team Lead (TL) strictly sees ONLY their allotted leads.
    if ($userRole !== 'admin' && $userRole !== 'superadmin' && ($myId || $myUser || $myNameClean)) {
        $leadsToReturn = array_values(array_filter($currentLeads, fn($l) => chatlyLeadVisibleTo($l, $myId, $myUser, $myNameClean)));
    }

    // Light sync: the dashboard's background poll asks for summaries (each lead with only
    // its latest messages + the total count). Opening a chat loads its full history through
    // get_lead_messages, so long conversations are not re-sent every few seconds.
    if (($_GET['summary'] ?? '') === '1') {
        $leadsToReturn = array_map(function ($l) {
            $msgs = is_array($l['messages'] ?? null) ? $l['messages'] : [];
            $l['messageCount'] = count($msgs);
            $l['messages'] = count($msgs) > CHATLY_SUMMARY_MESSAGES ? array_slice($msgs, -CHATLY_SUMMARY_MESSAGES) : $msgs;
            $l['summary'] = true;
            return $l;
        }, $leadsToReturn);
    }

    clearstatcache();
    echo json_encode([
        'success'        => true,
        'leads'          => $leadsToReturn,
        'count'          => count($leadsToReturn),
        'company_id'     => $activeCompanyId,
        'company_config' => $companyConfig,
        'rev'            => chatlyLeadsRevision($dataFile, $sessionUser)
    ]);
    exit;
}

// Full message history of ONE conversation (loaded when a chat is opened).
// Same visibility rule as get_leads: closers only ever see their own leads.
if ($action === 'get_lead_messages') {
    syncInboxMessagesIntoLeads($currentLeads, $dataFile);
    $wantId = (string) ($input['id'] ?? ($_GET['id'] ?? ''));
    $wantPhone = preg_replace('/\D+/', '', (string) ($input['phone'] ?? ($_GET['phone'] ?? '')));
    $myId = $sessionUser['id'] ?? '';
    $myUser = strtolower($sessionUser['username'] ?? '');
    $userRole = strtolower($sessionUser['role'] ?? 'member');
    $myNameClean = preg_replace('/\s+/', '', strtolower($sessionUser['name'] ?? ''));
    foreach ($currentLeads as $l) {
        $lp = preg_replace('/\D+/', '', (string) ($l['phone'] ?? ''));
        if (($wantId !== '' && ($l['id'] ?? '') === $wantId) || ($wantPhone !== '' && $lp === $wantPhone)) {
            if ($userRole !== 'admin' && $userRole !== 'superadmin' && ($myId || $myUser || $myNameClean) && !chatlyLeadVisibleTo($l, $myId, $myUser, $myNameClean)) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'This conversation is assigned to another agent.']);
                exit;
            }
            $msgs = is_array($l['messages'] ?? null) ? $l['messages'] : [];
            echo json_encode(['success' => true, 'id' => $l['id'] ?? '', 'messages' => $msgs, 'messageCount' => count($msgs)]);
            exit;
        }
    }
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Conversation not found.']);
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
            if (isset($input['isTakeover'])) $lead['isTakeover'] = (bool)$input['isTakeover'];
            if (array_key_exists('originalOwnerId', $input)) $lead['originalOwnerId'] = $input['originalOwnerId'];
            if (array_key_exists('originalOwnerName', $input)) $lead['originalOwnerName'] = $input['originalOwnerName'];
            if (isset($input['takenOverBy'])) $lead['takenOverBy'] = $input['takenOverBy'];
            if (isset($input['takenOverById'])) $lead['takenOverById'] = $input['takenOverById'];
            if (isset($input['takenOverAt'])) $lead['takenOverAt'] = $input['takenOverAt'];
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
            if (isset($input['lastMessageAt'])) $lead['lastMessageAt'] = (int)$input['lastMessageAt'];
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
                $lead['lastMessageAt'] = max($lead['lastMessageAt'] ?? 0, (int)($newMsg['timestamp'] ?? time()));
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
            'company_id' => $activeCompanyId,
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
