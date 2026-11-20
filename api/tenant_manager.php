<?php
// Multi-Tenant Isolation Engine for OmniDesk SaaS
// Guarantees separate databases, leads, campaigns, and product configurations per company.

function getTenantBaseDir(): string {
    $dir = __DIR__ . '/../data/tenants';
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    return $dir;
}

function resolveActiveCompanyId(): string {
    if (session_status() === PHP_SESSION_NONE) {
        @session_start();
    }
    $sessionUser = $_SESSION['omnidesk_user'] ?? null;
    $comp = 'COMP-001';

    if (!empty($sessionUser['company_id']) && $sessionUser['company_id'] !== 'GLOBAL') {
        $comp = $sessionUser['company_id'];
    }

    // Allow Super Admin or authorized request to specify company_id
    $reqComp = $_GET['company_id'] ?? $_POST['company_id'] ?? null;
    if (!$reqComp) {
        $raw = @file_get_contents('php://input');
        if ($raw) {
            $j = @json_decode($raw, true);
            if (is_array($j) && !empty($j['company_id'])) {
                $reqComp = $j['company_id'];
            }
        }
    }

    if (!empty($reqComp)) {
        $clean = preg_replace('/[^A-Za-z0-9_-]/', '', trim((string)$reqComp));
        if ($clean) {
            $isSuper = (!empty($sessionUser['role']) && $sessionUser['role'] === 'superadmin') ||
                       (!empty($sessionUser['company_id']) && $sessionUser['company_id'] === 'GLOBAL');
            if ($isSuper || empty($sessionUser) || $clean === ($sessionUser['company_id'] ?? '')) {
                $comp = $clean;
            }
        }
    }

    return $comp ?: 'COMP-001';
}

function getTenantDir(string $companyId): string {
    $clean = preg_replace('/[^A-Za-z0-9_-]/', '', trim($companyId)) ?: 'COMP-001';
    $dir = getTenantBaseDir() . '/' . $clean;
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    return $dir;
}

function getTenantLeadsFile(string $companyId): string {
    $dir = getTenantDir($companyId);
    $file = $dir . '/leads.json';
    if (!file_exists($file) || filesize($file) <= 10) {
        // Migration safeguard: if COMP-001, load existing leads from root data/leads.json if it exists
        $legacy = __DIR__ . '/../data/leads.json';
        if ($companyId === 'COMP-001' && file_exists($legacy) && filesize($legacy) > 10) {
            @copy($legacy, $file);
        } elseif ($companyId === 'COMP-004' || strtolower($companyId) === 'metalink') {
            $metalinkLeads = getMetalinkDemoLeads();
            file_put_contents($file, json_encode($metalinkLeads, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        } else {
            file_put_contents($file, json_encode([], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }
    }
    return $file;
}

function getMetalinkDemoLeads(): array {
    return [
        [
            'id' => 'meta_lead_01',
            'company_id' => 'COMP-004',
            'name' => 'Tariq Mehmood',
            'phone' => '923015544332',
            'display' => '+92 301 5544332',
            'city' => 'Lahore',
            'network' => 'Jazz',
            'ownerId' => null,
            'ownerName' => null,
            'ownerUsername' => null,
            'leadState' => 'fresh',
            'status' => 'fresh',
            'unreadCount' => 2,
            'interest' => 'Metalink Enterprise',
            'lastActive' => 'Just now',
            'dealAmount' => 7500,
            'notes' => 'Inbound prospect from Facebook campaign. Needs multi-exchange trading nodes. UNCLAIMED - Ready to claim!',
            'messages' => [
                [
                    'id' => 'msg_01_1',
                    'sender' => 'lead',
                    'text' => 'Salam bhai, mene Metalink Enterprise node ka campaign dekha tha. Kya aap mujhe monthly subscription aur features explain kr sakte hain?',
                    'time' => '02:15 PM'
                ],
                [
                    'id' => 'msg_01_2',
                    'sender' => 'lead',
                    'text' => 'Mujhe 3 multi-exchange nodes chahiye automated trading ke liye. Kya isme risk trailing included hai?',
                    'time' => '02:16 PM'
                ]
            ]
        ],
        [
            'id' => 'meta_lead_02',
            'company_id' => 'COMP-004',
            'name' => 'Bilal Ashraf',
            'phone' => '923224455667',
            'display' => '+92 322 4455667',
            'city' => 'Faisalabad',
            'network' => 'Warid',
            'ownerId' => null,
            'ownerName' => null,
            'ownerUsername' => null,
            'leadState' => 'fresh',
            'status' => 'fresh',
            'unreadCount' => 1,
            'interest' => 'Metalink Starter',
            'lastActive' => '10m ago',
            'dealAmount' => 2500,
            'notes' => 'Asked about minimum balance for Starter node. UNCLAIMED - Ready to claim!',
            'messages' => [
                [
                    'id' => 'msg_02_1',
                    'sender' => 'lead',
                    'text' => 'AoA sir, Starter package mein minimum balance kitna chahiye? EasyPaisa se payment kr sakta hu?',
                    'time' => '03:10 PM'
                ]
            ]
        ],
        [
            'id' => 'meta_lead_03',
            'company_id' => 'COMP-004',
            'name' => 'Dr. Kamran Qureshi',
            'phone' => '923337788990',
            'display' => '+92 333 7788990',
            'city' => 'Karachi',
            'network' => 'Ufone',
            'ownerId' => null,
            'ownerName' => null,
            'ownerUsername' => null,
            'leadState' => 'fresh',
            'status' => 'fresh',
            'unreadCount' => 1,
            'interest' => 'Metalink Wolf VIP',
            'lastActive' => '25m ago',
            'dealAmount' => 20000,
            'notes' => 'High-ticket fund manager. Wants custom API integration & Alpha Wolf group. UNCLAIMED - High commission!',
            'messages' => [
                [
                    'id' => 'msg_03_1',
                    'sender' => 'lead',
                    'text' => 'Hello team Metalink, I manage a private crypto & forex fund. Wolf VIP tier mein kya custom API integrations aur alpha group access included hai? Please call or reply.',
                    'time' => '03:45 PM'
                ]
            ]
        ],
        [
            'id' => 'meta_lead_04',
            'company_id' => 'COMP-004',
            'name' => 'Hassan Farooq',
            'phone' => '923129988112',
            'display' => '+92 312 9988112',
            'city' => 'Islamabad',
            'network' => 'Zong',
            'ownerId' => 'USR-ADMIN-04',
            'ownerName' => 'Zeeshan',
            'ownerUsername' => 'zeeshan',
            'leadState' => 'ongoing',
            'status' => 'replied',
            'unreadCount' => 0,
            'interest' => 'Metalink Enterprise',
            'lastActive' => '5m ago',
            'dealAmount' => 7500,
            'notes' => 'Payment initiated via Meezan Bank. Awaiting deposit receipt to close.',
            'messages' => [
                [
                    'id' => 'msg_04_1',
                    'sender' => 'lead',
                    'text' => 'AoA Hassan bhai, Enterprise package ke features confirm kr dein.',
                    'time' => '01:20 PM'
                ],
                [
                    'id' => 'msg_04_2',
                    'sender' => 'agent',
                    'agentName' => 'Zeeshan',
                    'text' => 'W/Salam Hassan bhai! Metalink Enterprise me apko 3 multi-exchange nodes, 24/7 automated risk trailing, aur VIP desk support milti hai. Monthly sub PKR 7,500 hai.',
                    'time' => '01:22 PM'
                ],
                [
                    'id' => 'msg_04_3',
                    'sender' => 'lead',
                    'text' => 'Zabardast. Bank account details send karein, me Meezan bank se transfer kr deta hu.',
                    'time' => '01:25 PM'
                ],
                [
                    'id' => 'msg_04_4',
                    'sender' => 'agent',
                    'agentName' => 'Zeeshan',
                    'text' => 'Ye lein account details: Meezan Bank | Metalink Systems | IBAN: PK36MEZN0099281726354001. Transfer kr ke receipt yahan share kr dein.',
                    'time' => '01:26 PM'
                ],
                [
                    'id' => 'msg_04_5',
                    'sender' => 'lead',
                    'text' => 'Transfer initiating now. Sending slip in 5 minutes.',
                    'time' => '01:30 PM'
                ]
            ]
        ],
        [
            'id' => 'meta_lead_05',
            'company_id' => 'COMP-004',
            'name' => 'Usman Ghani',
            'phone' => '923451122334',
            'display' => '+92 345 1122334',
            'city' => 'Rawalpindi',
            'network' => 'Telenor',
            'ownerId' => 'USR-ADMIN-04',
            'ownerName' => 'Zeeshan',
            'ownerUsername' => 'zeeshan',
            'leadState' => 'ongoing',
            'status' => 'replied',
            'unreadCount' => 0,
            'interest' => 'Metalink Starter',
            'lastActive' => '40m ago',
            'dealAmount' => 2500,
            'notes' => 'Ready to send payment via JazzCash for Starter node.',
            'messages' => [
                [
                    'id' => 'msg_05_1',
                    'sender' => 'lead',
                    'text' => 'Bhai kya isme 7 din ka performance check guarantee hai?',
                    'time' => '11:45 AM'
                ],
                [
                    'id' => 'msg_05_2',
                    'sender' => 'agent',
                    'agentName' => 'Zeeshan',
                    'text' => 'Ji bilkul Usman bhai! Metalink standard 7-day performance guarantee deti hai. Aap risk-free join kr sakte hain.',
                    'time' => '11:48 AM'
                ],
                [
                    'id' => 'msg_05_3',
                    'sender' => 'lead',
                    'text' => 'Theek hai, JazzCash number bhejein me payment bhej raha hu.',
                    'time' => '11:50 AM'
                ]
            ]
        ],
        [
            'id' => 'meta_lead_06',
            'company_id' => 'COMP-004',
            'name' => 'Malik Jahangir',
            'phone' => '923008899112',
            'display' => '+92 300 8899112',
            'city' => 'Multan',
            'network' => 'Mobilink',
            'ownerId' => 'USR-ADMIN-04',
            'ownerName' => 'Zeeshan',
            'ownerUsername' => 'zeeshan',
            'leadState' => 'paid',
            'status' => 'paid',
            'unreadCount' => 0,
            'interest' => 'Metalink Wolf VIP',
            'lastActive' => '1h ago',
            'dealAmount' => 20000,
            'verificationStatus' => 'verified',
            'verifiedBy' => 'Zeeshan (Admin)',
            'verifiedAt' => '2026-11-19T10:18:00Z',
            'paidDetails' => [
                'plan' => 'Metalink Wolf VIP',
                'amount' => 20000,
                'commission' => 10000,
                'closedBy' => 'Zeeshan'
            ],
            'notes' => 'Won Deal! Rs. 20,000 paid via HBL. Wolf Alpha node deployed.',
            'messages' => [
                [
                    'id' => 'msg_06_1',
                    'sender' => 'lead',
                    'text' => 'Salam Zeeshan bhai, Wolf VIP plan ki 20,000 payment HBL online se transfer kr di hai. Reference: HBL-99120488.',
                    'time' => '10:15 AM'
                ],
                [
                    'id' => 'msg_06_2',
                    'sender' => 'agent',
                    'agentName' => 'Admin Audit Verification',
                    'text' => "🟢 PAYMENT VERIFIED & APPROVED (DEAL WON)!\n• Verified By: Zeeshan (Admin)\n• Closer Credited: Zeeshan\n• Plan: Metalink Wolf VIP (Rs. 20,000)\n• Commission Earned: Rs. 10,000\n• Node Alpha License Activated! 🚀",
                    'time' => '10:18 AM'
                ]
            ]
        ],
        [
            'id' => 'meta_lead_07',
            'company_id' => 'COMP-004',
            'name' => 'Sufyan Ahmed',
            'phone' => '923067711223',
            'display' => '+92 306 7711223',
            'city' => 'Gujranwala',
            'network' => 'Jazz',
            'ownerId' => 'USR-ADMIN-04',
            'ownerName' => 'Zeeshan',
            'ownerUsername' => 'zeeshan',
            'leadState' => 'paid',
            'status' => 'paid',
            'unreadCount' => 0,
            'interest' => 'Metalink Enterprise',
            'lastActive' => '2h ago',
            'dealAmount' => 7500,
            'verificationStatus' => 'verified',
            'verifiedBy' => 'Zeeshan (Admin)',
            'verifiedAt' => '2026-11-19T09:45:00Z',
            'paidDetails' => [
                'plan' => 'Metalink Enterprise',
                'amount' => 7500,
                'commission' => 3000,
                'closedBy' => 'Zeeshan'
            ],
            'notes' => 'Won Deal! Rs. 7,500 paid via EasyPaisa. Enterprise node deployed.',
            'messages' => [
                [
                    'id' => 'msg_07_1',
                    'sender' => 'lead',
                    'text' => 'Salam bhai, EasyPaisa se 7,500 bhej diye hain. TID: 28819200411.',
                    'time' => '09:40 AM'
                ],
                [
                    'id' => 'msg_07_2',
                    'sender' => 'agent',
                    'agentName' => 'Admin Audit Verification',
                    'text' => "🟢 PAYMENT VERIFIED & APPROVED (DEAL WON)!\n• Verified By: Zeeshan (Admin)\n• Closer Credited: Zeeshan\n• Plan: Metalink Enterprise (Rs. 7,500)\n• Commission Earned: Rs. 3,000\n• 3 Multi-Exchange Nodes Active! 🚀",
                    'time' => '09:45 AM'
                ]
            ]
        ]
    ];
}

function getTenantCampaignLogsFile(string $companyId): string {
    $dir = getTenantDir($companyId);
    $file = $dir . '/campaign_logs.json';
    if (!file_exists($file)) {
        file_put_contents($file, json_encode([], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
    return $file;
}

function getTenantConfigFile(string $companyId): string {
    $dir = getTenantDir($companyId);
    $file = $dir . '/config.json';
    if (!file_exists($file)) {
        $isNunno = ($companyId === 'COMP-001');
        $defaultConfig = [
            'company_id'   => $companyId,
            'company_name' => $isNunno ? 'Nunno Official' : 'Company ' . $companyId,
            'products'     => $isNunno ? [
                ['id' => 'pro', 'name' => 'Nunno Pro', 'amount' => 5000, 'commission' => 2000, 'badge' => '💎 Most Popular'],
                ['id' => 'starter', 'name' => 'Nunno Starter', 'amount' => 1700, 'commission' => 700, 'badge' => '⚡ Scalper Entry'],
                ['id' => 'whale', 'name' => 'Whale VIP', 'amount' => 15000, 'commission' => 10000, 'badge' => '🐋 Institutional Alpha'],
                ['id' => 'annual', 'name' => 'Annual Pass', 'amount' => 35000, 'commission' => 15000, 'badge' => '👑 Unlimited Pass']
            ] : [
                ['id' => 'p_standard', 'name' => 'Standard Package', 'amount' => 5000, 'commission' => 1500, 'badge' => '⭐ Recommended'],
                ['id' => 'p_starter', 'name' => 'Starter Deal', 'amount' => 2000, 'commission' => 500, 'badge' => '⚡ Quick Start'],
                ['id' => 'p_vip', 'name' => 'VIP Premium Deal', 'amount' => 12000, 'commission' => 4000, 'badge' => '👑 High Value']
            ],
            'default_pitch' => $isNunno 
                ? "{Salam|AoA} {name}! Market wicks se liquid ho rahe hain? Nunno AI Liquidation Magnet se exact whale levels pehle dekhein."
                : "{Salam|AoA|Hello} {name}! Welcome to our company. Special limited-time offers available today."
        ];
        file_put_contents($file, json_encode($defaultConfig, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
    return $file;
}

function getTenantConfig(string $companyId): array {
    $file = getTenantConfigFile($companyId);
    $data = @json_decode(@file_get_contents($file), true);
    return is_array($data) ? $data : [];
}

// Scans tenant databases to locate which company owns an incoming customer phone number
function findCompanyIdForPhone(string $phone): string {
    $cleanPhone = preg_replace('/\D+/', '', $phone);
    if (!$cleanPhone) return 'COMP-001';

    $baseDir = getTenantBaseDir();
    $tenants = @scandir($baseDir);
    if (is_array($tenants)) {
        foreach ($tenants as $t) {
            if ($t === '.' || $t === '..') continue;
            $tenantLeadsFile = $baseDir . '/' . $t . '/leads.json';
            if (file_exists($tenantLeadsFile)) {
                $leads = @json_decode(@file_get_contents($tenantLeadsFile), true);
                if (is_array($leads)) {
                    foreach ($leads as $l) {
                        $lp = preg_replace('/\D+/', '', $l['phone'] ?? '');
                        if ($lp === $cleanPhone) {
                            return $t;
                        }
                    }
                }
            }
        }
    }
    return 'COMP-001';
}

// HTTP API Handler for Tenant Operations
if (php_sapi_name() !== 'cli' && basename($_SERVER['SCRIPT_FILENAME'] ?? '') === 'tenant_manager.php') {
    if (session_status() === PHP_SESSION_NONE) {
        @session_start();
    }
    header('Content-Type: application/json; charset=UTF-8');
    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $action = $_GET['action'] ?? $input['action'] ?? '';
    $companyId = resolveActiveCompanyId();

    if ($action === 'list_company_employees' || $action === 'get_employees' || $action === 'list_employees') {
        $usersFile = __DIR__ . '/../data/users.json';
        $allUsers = file_exists($usersFile) ? json_decode(file_get_contents($usersFile), true) : [];
        if (!is_array($allUsers)) $allUsers = [];

        $filtered = array_values(array_filter($allUsers, function($u) use ($companyId) {
            if (($u['role'] ?? '') === 'superadmin' || ($u['company_id'] ?? '') === 'GLOBAL') return false;
            if (($u['role'] ?? '') === 'admin') return false;
            return ($u['company_id'] ?? 'COMP-001') === $companyId;
        }));

        echo json_encode(['success' => true, 'employees' => $filtered]);
        exit;
    }

    if ($action === 'get_campaign_senders') {
        $usersFile = __DIR__ . '/../data/users.json';
        $allUsers = file_exists($usersFile) ? json_decode(file_get_contents($usersFile), true) : [];
        if (!is_array($allUsers)) $allUsers = [];

        // STRICT RULE: Only active TMs (member) and TLs (lead) with valid phone numbers
        // Admins and Superadmins are excluded from the campaign sender pool
        $matched = array_values(array_filter($allUsers, function($u) use ($companyId) {
            $uComp = $u['company_id'] ?? 'COMP-001';
            $status = strtolower($u['status'] ?? 'active');
            $phone = preg_replace('/\D+/', '', $u['phone'] ?? '');
            $role = strtolower($u['role'] ?? 'member');
            return $uComp === $companyId && $status === 'active' && strlen($phone) >= 10
                   && ($role === 'member' || $role === 'lead');
        }));

        // Sort: member (TM) first, lead (TL) next
        usort($matched, function($a, $b) {
            $order = ['member' => 1, 'lead' => 2];
            return ($order[$a['role'] ?? ''] ?? 99) <=> ($order[$b['role'] ?? ''] ?? 99);
        });

        // Deduplicate by clean phone
        $seenPhones = [];
        $uniqueMatched = [];
        foreach ($matched as $u) {
            $p = preg_replace('/\D+/', '', $u['phone'] ?? '');
            if (!isset($seenPhones[$p])) {
                $seenPhones[$p] = true;
                $uniqueMatched[] = $u;
            }
        }

        $senders = [];
        $idx = 1;
        foreach ($uniqueMatched as $u) {
            $cleanPhone = preg_replace('/\D+/', '', $u['phone'] ?? '');
            $roleLabel = 'TM Closer';
            if (($u['role'] ?? '') === 'lead') $roleLabel = 'Team Leader (TL)';
            elseif (($u['role'] ?? '') === 'admin') $roleLabel = 'Admin';

            $senders[] = [
                'index'         => $idx++,
                'id'            => $u['id'],
                'name'          => $u['name'],
                'username'      => $u['username'] ?? '',
                'role'          => $roleLabel,
                'roleType'      => $u['role'] ?? 'member',
                'phone'         => $cleanPhone,
                'display'       => str_starts_with($u['phone'] ?? '', '+') ? $u['phone'] : ('+' . $cleanPhone),
                'messages_sent' => (int)($u['messages_sent_today'] ?? 0),
                'status'        => 'ready'
            ];
        }

        if (empty($senders)) {
            $senders[] = [
                'index'         => 1,
                'id'            => 'SENDER-FALLBACK-01',
                'name'          => 'Primary WhatsApp Device',
                'username'      => 'primary',
                'role'          => 'TM Closer',
                'roleType'      => 'member',
                'phone'         => '923318036576',
                'display'       => '+92 331 8036576',
                'messages_sent' => 0,
                'status'        => 'ready'
            ];
        }

        echo json_encode([
            'success'    => true,
            'company_id' => $companyId,
            'count'      => count($senders),
            'senders'    => $senders
        ]);
        exit;
    }

    if ($action === 'get_company_config') {
        echo json_encode(['success' => true, 'config' => getTenantConfig($companyId)]);
        exit;
    }

    if ($action === 'update_company_config' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $config = getTenantConfig($companyId);
        if (isset($input['company_name'])) $config['company_name'] = trim($input['company_name']);
        if (isset($input['products']) && is_array($input['products'])) $config['products'] = $input['products'];
        if (isset($input['default_pitch'])) $config['default_pitch'] = trim($input['default_pitch']);
        if (isset($input['tl_commission_per_tm'])) {
            $config['tl_commission_per_tm'] = max(0, (int)$input['tl_commission_per_tm']);
        }
        $configFile = getTenantConfigFile($companyId);
        file_put_contents($configFile, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        echo json_encode(['success' => true, 'message' => 'Configuration saved successfully.', 'config' => $config]);
        exit;
    }
}
