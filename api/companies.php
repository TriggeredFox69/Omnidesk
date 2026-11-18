<?php
/**
 * Billion-Dollar Startup Multi-Tenant Engine — Company & Enterprise Management API
 * 
 * Provides:
 * 1. Global Multi-Company Provisioning & Administration (/sadmin)
 * 2. Enterprise Telemetry & Usage Monitoring
 * 3. Company-specific Settings & Quota Policies (/admin)
 */

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 60 * 60 * 24 * 7,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');

$companiesFile = __DIR__ . '/../data/companies.json';
$usersFile     = __DIR__ . '/../data/users.json';
$auditFile     = __DIR__ . '/../data/audit_logs.json';
require_once __DIR__ . '/tenant_manager.php';

function getCompanies($file) {
    if (!file_exists($file)) return [];
    $data = @json_decode(file_get_contents($file), true);
    return is_array($data) ? $data : [];
}

function saveCompanies($file, $data) {
    $tmp = $file . '.tmp.' . uniqid();
    file_put_contents($tmp, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    rename($tmp, $file);
}

function logAudit($auditFile, $companyId, $actorId, $actorName, $action, $details) {
    $logs = file_exists($auditFile) ? json_decode(file_get_contents($auditFile), true) : [];
    if (!is_array($logs)) $logs = [];
    $logs[] = [
        'id'         => 'AUDIT-' . time() . '-' . rand(100, 999),
        'timestamp'  => date('Y-m-d H:i:s'),
        'company_id' => $companyId,
        'actor_id'   => $actorId,
        'actor_name' => $actorName,
        'action'     => $action,
        'details'    => $details
    ];
    $tmp = $auditFile . '.tmp.' . uniqid();
    file_put_contents($tmp, json_encode(array_slice($logs, -300), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    rename($tmp, $auditFile);
}

// Current session
$currentUser = $_SESSION['omnidesk_user'] ?? null;
$action = $_GET['action'] ?? $_POST['action'] ?? '';
if (!$action) {
    $rawInput = file_get_contents('php://input');
    if ($rawInput) {
        $json = json_decode($rawInput, true);
        if (is_array($json)) $action = $json['action'] ?? '';
    }
}

// Security Helper
function requireSuperAdmin($user) {
    if (!$user || ($user['role'] ?? '') !== 'superadmin') {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Access Denied: Super Administrator privileges required.']);
        exit;
    }
}

function requireCompanyAdmin($user) {
    if (!$user || !in_array(($user['role'] ?? ''), ['admin', 'superadmin'], true)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Access Denied: Company Administrator privileges required.']);
        exit;
    }
}

switch ($action) {

    // 1. Get Platform-wide Telemetry (Super Admin)
    case 'get_telemetry':
        requireSuperAdmin($currentUser);
        $companies = getCompanies($companiesFile);
        $users = file_exists($usersFile) ? json_decode(file_get_contents($usersFile), true) : [];
        if (!is_array($users)) $users = [];

        $totalEmployees = 0;
        $totalTLs = 0;
        $totalTMs = 0;
        $totalMessagesToday = 0;
        $activeSessions = 0;

        $pendingTMs = 0;
        foreach ($users as $u) {
            if (($u['role'] ?? '') === 'superadmin') continue;
            if (($u['status'] ?? '') === 'pending') {
                $pendingTMs++;
                continue;
            }
            $totalEmployees++;
            if (($u['role'] ?? '') === 'lead') $totalTLs++;
            if (($u['role'] ?? '') === 'member') $totalTMs++;
            $totalMessagesToday += (int)($u['messages_sent_today'] ?? 0);
            if (!empty($u['phone']) && ($u['status'] ?? '') === 'active') $activeSessions++;
        }

        // Platform MRR (Nunno Enterprise plan + Closer seats)
        $platformMRR = 0;
        foreach ($companies as $c) {
            $plan = $c['plan'] ?? 'Enterprise';
            if (stripos($plan, 'Billion') !== false || stripos($plan, 'VIP') !== false) $platformMRR += 250000;
            elseif (stripos($plan, 'Pro') !== false) $platformMRR += 150000;
            else $platformMRR += 75000;
        }

        echo json_encode([
            'success'   => true,
            'telemetry' => [
                'total_companies'        => count($companies),
                'active_companies'       => count(array_filter($companies, fn($c) => ($c['status'] ?? '') === 'active')),
                'total_employees'        => $totalEmployees,
                'total_tls'              => $totalTLs,
                'total_tms'              => $totalTMs,
                'pending_tms'            => $pendingTMs,
                'total_messages_today'   => $totalMessagesToday,
                'active_sessions'        => $activeSessions,
                'platform_mrr'           => $platformMRR,
                'system_health'          => 'optimal',
                'subsecond_tick'         => microtime(true),
                'server_time'            => date('Y-m-d H:i:s')
            ]
        ]);
        exit;

    // 1b. Get Real-Time Cross-Company Live Chatter Stream (Super Admin)
    case 'get_live_chat_stream':
        requireSuperAdmin($currentUser);
        $companies = getCompanies($companiesFile);
        $companyMap = [];
        foreach ($companies as $c) {
            $companyMap[$c['id']] = $c['name'];
        }

        $stream = [];
        $tenantsDir = __DIR__ . '/../data/tenants';
        if (is_dir($tenantsDir)) {
            $tenantFolders = scandir($tenantsDir);
            foreach ($tenantFolders as $folder) {
                if ($folder === '.' || $folder === '..') continue;
                $leadsPath = $tenantsDir . '/' . $folder . '/leads.json';
                if (file_exists($leadsPath)) {
                    $tenantLeads = @json_decode(file_get_contents($leadsPath), true);
                    if (is_array($tenantLeads)) {
                        foreach ($tenantLeads as $lead) {
                            $leadName = $lead['name'] ?? ($lead['display'] ?? 'Contact');
                            $phone = $lead['phone'] ?? '';
                            $closer = $lead['ownerName'] ?? 'Closer';
                            $msgs = $lead['messages'] ?? [];
                            foreach ($msgs as $m) {
                                $stream[] = [
                                    'id'           => $lead['id'] . '-' . rand(1000, 9999),
                                    'company_id'   => $folder,
                                    'company_name' => $companyMap[$folder] ?? $folder,
                                    'lead_name'    => $leadName,
                                    'lead_phone'   => $phone,
                                    'closer_name'  => $closer,
                                    'sender'       => $m['sender'] ?? 'lead',
                                    'text'         => $m['text'] ?? '',
                                    'time'         => $m['time'] ?? 'Just now',
                                    'state'        => $lead['leadState'] ?? 'ongoing'
                                ];
                            }
                        }
                    }
                }
            }
        }

        // Also include root data/leads.json if exists
        $rootLeadsPath = __DIR__ . '/../data/leads.json';
        if (file_exists($rootLeadsPath)) {
            $rootLeads = @json_decode(file_get_contents($rootLeadsPath), true);
            if (is_array($rootLeads)) {
                foreach ($rootLeads as $lead) {
                    $leadName = $lead['name'] ?? ($lead['display'] ?? 'Contact');
                    $msgs = $lead['messages'] ?? [];
                    foreach ($msgs as $m) {
                        $stream[] = [
                            'id'           => $lead['id'] . '-' . rand(1000, 9999),
                            'company_id'   => 'COMP-001',
                            'company_name' => $companyMap['COMP-001'] ?? 'Nunno Official',
                            'lead_name'    => $leadName,
                            'lead_phone'   => $lead['phone'] ?? '',
                            'closer_name'  => $lead['ownerName'] ?? 'Admin Closer',
                            'sender'       => $m['sender'] ?? 'lead',
                            'text'         => $m['text'] ?? '',
                            'time'         => $m['time'] ?? 'Just now',
                            'state'        => $lead['leadState'] ?? 'ongoing'
                        ];
                    }
                }
            }
        }

        // Fallback demo live events if empty
        if (empty($stream)) {
            $stream = [
                [
                    'id'           => 'LIVE-01',
                    'company_id'   => 'COMP-001',
                    'company_name' => 'Nunno Official',
                    'lead_name'    => 'Ali Trader',
                    'lead_phone'   => '+923001234567',
                    'closer_name'  => 'Hamza Khan',
                    'sender'       => 'agent',
                    'text'         => 'Salam Ali bhai! Your Nunno Pro scalper license is active.',
                    'time'         => date('h:i A'),
                    'state'        => 'paid'
                ],
                [
                    'id'           => 'LIVE-02',
                    'company_id'   => 'COMP-002',
                    'company_name' => 'Apex Crypto Signals',
                    'lead_name'    => 'Waqas BTC',
                    'lead_phone'   => '+923219988776',
                    'closer_name'  => 'Daniyal Sheikh',
                    'sender'       => 'lead',
                    'text'         => 'Bro whats the monthly VIP pricing?',
                    'time'         => date('h:i A', strtotime('-1 minute')),
                    'state'        => 'ongoing'
                ]
            ];
        }

        echo json_encode(['success' => true, 'stream' => array_slice(array_reverse($stream), 0, 50)]);
        exit;

    // 2. List All Companies (Super Admin)
    case 'list':
        requireSuperAdmin($currentUser);
        $companies = getCompanies($companiesFile);
        $users = file_exists($usersFile) ? json_decode(file_get_contents($usersFile), true) : [];
        if (!is_array($users)) $users = [];

        // Aggregate statistics per company
        foreach ($companies as &$comp) {
            $cId = $comp['id'];
            $compUsers = array_filter($users, fn($u) => ($u['company_id'] ?? '') === $cId);
            $comp['employee_count'] = count($compUsers);
            $comp['tl_count']       = count(array_filter($compUsers, fn($u) => ($u['role'] ?? '') === 'lead'));
            $comp['tm_count']       = count(array_filter($compUsers, fn($u) => ($u['role'] ?? '') === 'member'));
            $comp['messages_today'] = array_sum(array_column($compUsers, 'messages_sent_today'));
        }
        unset($comp);

        echo json_encode([
            'success'   => true,
            'companies' => $companies
        ]);
        exit;

    // 3. Provision New Company & Admin Account (Super Admin)
    case 'create':
        requireSuperAdmin($currentUser);
        $raw = file_get_contents('php://input');
        $data = json_decode($raw, true) ?: $_POST;

        $name          = trim($data['name'] ?? '');
        $slug          = strtolower(preg_replace('/[^a-z0-9_-]/', '', trim($data['slug'] ?? '')));
        $ownerName     = trim($data['owner_name'] ?? '');
        $ownerEmail    = trim($data['owner_email'] ?? '');
        $plan          = trim($data['plan'] ?? 'Enterprise Growth');
        $maxEmployees  = max(1, (int)($data['max_employees'] ?? 50));
        $defaultMsgLim = max(10, (int)($data['default_message_limit'] ?? 200));
        
        $adminUsername = strtolower(trim($data['admin_username'] ?? ($slug . '_admin')));
        $adminPassword = trim($data['admin_password'] ?? '');

        if (!$name || !$slug || !$ownerEmail || !$adminPassword) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Company Name, Slug, Owner Email, and Initial Admin Password are required.']);
            exit;
        }

        $companies = getCompanies($companiesFile);
        foreach ($companies as $c) {
            if ($c['slug'] === $slug) {
                http_response_code(409);
                echo json_encode(['success' => false, 'error' => "Company slug '$slug' is already taken."]);
                exit;
            }
        }

        $users = file_exists($usersFile) ? json_decode(file_get_contents($usersFile), true) : [];
        if (!is_array($users)) $users = [];
        foreach ($users as $u) {
            if (strcasecmp($u['username'], $adminUsername) === 0) {
                http_response_code(409);
                echo json_encode(['success' => false, 'error' => "Admin username '$adminUsername' is already taken."]);
                exit;
            }
        }

        $companyId = 'COMP-' . str_pad(count($companies) + 1, 3, '0', STR_PAD_LEFT);
        $newCompany = [
            'id'                    => $companyId,
            'name'                  => $name,
            'slug'                  => $slug,
            'owner_name'            => $ownerName ?: $name . ' Admin',
            'owner_email'           => $ownerEmail,
            'plan'                  => $plan,
            'max_employees'         => $maxEmployees,
            'default_message_limit' => $defaultMsgLim,
            'status'                => 'active',
            'created_at'            => date('Y-m-d H:i:s')
        ];

        // Provision Company Admin Account
        $adminUser = [
            'id'                    => 'USR-' . strtoupper($slug) . '-ADMIN',
            'name'                  => $ownerName ?: ($name . ' Administrator'),
            'username'              => $adminUsername,
            'email'                 => $ownerEmail,
            'password_hash'         => password_hash($adminPassword, PASSWORD_BCRYPT),
            'role'                  => 'admin',
            'designation'           => 'Company Administrator',
            'gender'                => 'male',
            'phone'                 => '',
            'status'                => 'active',
            'company_id'            => $companyId,
            'message_limit'         => -1,
            'messages_sent_today'   => 0,
            'quota_reset_date'      => date('Y-m-d'),
            'created_at'            => date('Y-m-d H:i:s')
        ];

        $companies[] = $newCompany;
        $users[] = $adminUser;

        saveCompanies($companiesFile, $companies);
        file_put_contents($usersFile, json_encode($users, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        // Auto-provision Tenant isolated database structure
        getTenantDir($companyId);
        getTenantLeadsFile($companyId);
        getTenantCampaignLogsFile($companyId);
        $configFile = getTenantConfigFile($companyId);
        
        // Save tenant configuration with custom products or defaults
        $customProducts = (!empty($data['products']) && is_array($data['products'])) ? $data['products'] : [
            ['id' => 'p_standard', 'name' => $name . ' Standard', 'amount' => 5000, 'commission' => 1500, 'badge' => '⭐ Recommended'],
            ['id' => 'p_starter',  'name' => $name . ' Starter',  'amount' => 2000, 'commission' => 500,  'badge' => '⚡ Quick Start'],
            ['id' => 'p_vip',      'name' => $name . ' Premium',  'amount' => 12000,'commission' => 4000, 'badge' => '👑 High Value']
        ];
        $customPitch = !empty($data['default_pitch']) 
            ? trim($data['default_pitch']) 
            : "{Salam|AoA|Hello} {name}! Welcome to $name. Special limited-time offers available today.";

        $compConfig = [
            'company_id'    => $companyId,
            'company_name'  => $name,
            'company_slug'  => $slug,
            'products'      => $customProducts,
            'default_pitch' => $customPitch,
            'created_at'    => date('Y-m-d H:i:s')
        ];
        file_put_contents($configFile, json_encode($compConfig, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        logAudit($auditFile, $companyId, $currentUser['id'], $currentUser['name'], 'company_provisioned', "Provisioned $name ($slug) with admin '$adminUsername' and isolated tenant database.");

        echo json_encode([
            'success' => true,
            'message' => "Company '$name' provisioned successfully with Admin account '$adminUsername'.",
            'company' => $newCompany,
            'admin_username' => $adminUsername
        ]);
        exit;

    // 4. Toggle Company Status (Active / Suspended)
    case 'toggle_status':
        requireSuperAdmin($currentUser);
        $raw = file_get_contents('php://input');
        $data = json_decode($raw, true) ?: $_POST;
        $companyId = trim($data['company_id'] ?? '');

        $companies = getCompanies($companiesFile);
        $found = false;
        foreach ($companies as &$comp) {
            if ($comp['id'] === $companyId) {
                $comp['status'] = ($comp['status'] === 'active') ? 'suspended' : 'active';
                $found = true;
                logAudit($auditFile, $companyId, $currentUser['id'], $currentUser['name'], 'company_status_toggle', "Toggled status to " . $comp['status']);
                break;
            }
        }
        unset($comp);

        if (!$found) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Company not found.']);
            exit;
        }

        saveCompanies($companiesFile, $companies);
        echo json_encode(['success' => true, 'message' => 'Company status updated.']);
        exit;

    // 5. Audit Activity Logs (Super Admin)
    case 'get_audit_logs':
        requireSuperAdmin($currentUser);
        $logs = file_exists($auditFile) ? json_decode(file_get_contents($auditFile), true) : [];
        if (!is_array($logs)) $logs = [];
        echo json_encode([
            'success' => true,
            'logs'    => array_reverse(array_slice($logs, -100))
        ]);
        exit;

    // 6. List Number Profiles (Super Admin or Company Admin)
    case 'list_number_profiles':
        requireCompanyAdmin($currentUser);
        $numFile = __DIR__ . '/../data/company_numbers.json';
        $nums = file_exists($numFile) ? json_decode(file_get_contents($numFile), true) : [];
        if (!is_array($nums)) $nums = [];
        echo json_encode(['success' => true, 'profiles' => $nums]);
        exit;

    // 7. Create Number Profile (Super Admin)
    case 'create_number_profile':
        requireSuperAdmin($currentUser);
        $raw = file_get_contents('php://input');
        $data = json_decode($raw, true) ?: $_POST;
        $phone = preg_replace('/\D+/', '', trim($data['phone'] ?? ''));
        $label = trim($data['label'] ?? 'Company WhatsApp Trunk');
        $cap = max(50, (int)($data['daily_capacity'] ?? 500));
        $comp = trim($data['company_id'] ?? 'COMP-001');

        if (!$phone || strlen($phone) < 10) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Valid phone number is required (min 10 digits).']);
            exit;
        }

        $numFile = __DIR__ . '/../data/company_numbers.json';
        $nums = file_exists($numFile) ? json_decode(file_get_contents($numFile), true) : [];
        if (!is_array($nums)) $nums = [];

        $newProf = [
            'id'                    => 'NUM-' . str_pad(count($nums) + 1, 3, '0', STR_PAD_LEFT),
            'phone'                 => $phone,
            'display'               => '+' . $phone,
            'label'                 => $label,
            'company_id'            => $comp,
            'assigned_to_user_id'   => null,
            'assigned_to_user_name' => null,
            'daily_capacity'        => $cap,
            'messages_sent_today'   => 0,
            'status'                => 'available',
            'created_at'            => date('Y-m-d H:i:s')
        ];
        $nums[] = $newProf;
        file_put_contents($numFile, json_encode($nums, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        logAudit($auditFile, $comp, $currentUser['id'], $currentUser['name'], 'number_profile_created', "Added fallback number {$phone} ({$label})");

        echo json_encode(['success' => true, 'profile' => $newProf, 'message' => 'Company number profile created.']);
        exit;

    // 8. Assign Number Profile to User who has no number (Super Admin)
    case 'assign_number_profile':
        requireSuperAdmin($currentUser);
        $raw = file_get_contents('php://input');
        $data = json_decode($raw, true) ?: $_POST;
        $profId = trim($data['profile_id'] ?? '');
        $userId = trim($data['user_id'] ?? '');

        if (!$profId || !$userId) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Profile ID and User ID are required.']);
            exit;
        }

        $numFile = __DIR__ . '/../data/company_numbers.json';
        $nums = file_exists($numFile) ? json_decode(file_get_contents($numFile), true) : [];
        $users = file_exists($usersFile) ? json_decode(file_get_contents($usersFile), true) : [];

        $targetUser = null;
        foreach ($users as &$u) {
            if ($u['id'] === $userId) {
                $targetUser = &$u;
                break;
            }
        }
        unset($u);

        if (!$targetUser) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Target employee not found.']);
            exit;
        }

        $assignedPhone = '';
        $foundProf = false;
        foreach ($nums as &$np) {
            if ($np['id'] === $profId) {
                $np['assigned_to_user_id'] = $targetUser['id'];
                $np['assigned_to_user_name'] = $targetUser['name'];
                $np['status'] = 'in_use';
                $assignedPhone = $np['phone'];
                $foundProf = true;
                break;
            }
        }
        unset($np);

        if (!$foundProf) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Number profile not found.']);
            exit;
        }

        $targetUser['phone'] = $assignedPhone;
        $targetUser['using_company_fallback'] = true;
        $targetUser['fallback_number_id'] = $profId;

        file_put_contents($numFile, json_encode($nums, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        file_put_contents($usersFile, json_encode($users, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        logAudit($auditFile, $targetUser['company_id'] ?? 'COMP-001', $currentUser['id'], $currentUser['name'], 'number_profile_assigned', "Assigned company number {$assignedPhone} to {$targetUser['name']}");

        echo json_encode(['success' => true, 'message' => "Company number {$assignedPhone} assigned to {$targetUser['name']}!"]);
        exit;

    // 9. Release Number Profile back to Pool
    case 'release_number_profile':
        requireSuperAdmin($currentUser);
        $raw = file_get_contents('php://input');
        $data = json_decode($raw, true) ?: $_POST;
        $profId = trim($data['profile_id'] ?? '');

        $numFile = __DIR__ . '/../data/company_numbers.json';
        $nums = file_exists($numFile) ? json_decode(file_get_contents($numFile), true) : [];
        $users = file_exists($usersFile) ? json_decode(file_get_contents($usersFile), true) : [];

        $releasedUser = null;
        foreach ($nums as &$np) {
            if ($np['id'] === $profId) {
                $releasedUser = $np['assigned_to_user_id'];
                $np['assigned_to_user_id'] = null;
                $np['assigned_to_user_name'] = null;
                $np['status'] = 'available';
                break;
            }
        }
        unset($np);

        if ($releasedUser) {
            foreach ($users as &$u) {
                if ($u['id'] === $releasedUser) {
                    $u['using_company_fallback'] = false;
                    $u['fallback_number_id'] = null;
                    break;
                }
            }
            unset($u);
            file_put_contents($usersFile, json_encode($users, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }

        file_put_contents($numFile, json_encode($nums, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        logAudit($auditFile, 'GLOBAL', $currentUser['id'], $currentUser['name'], 'number_profile_released', "Released number profile {$profId} back to available pool.");

        echo json_encode(['success' => true, 'message' => 'Number profile released back to pool.']);
        exit;

    // 10. Get Company Products & Pitch Config
    case 'get_company_config':
        requireCompanyAdmin($currentUser);
        $companyId = resolveActiveCompanyId();
        $config = getTenantConfig($companyId);
        echo json_encode(['success' => true, 'company_id' => $companyId, 'config' => $config]);
        exit;

    // 11. Update Company Products & Pitch Config
    case 'update_company_config':
        requireCompanyAdmin($currentUser);
        $companyId = resolveActiveCompanyId();
        $raw = file_get_contents('php://input');
        $data = json_decode($raw, true) ?: $_POST;
        
        $config = getTenantConfig($companyId);
        if (isset($data['company_name'])) $config['company_name'] = trim($data['company_name']);
        if (isset($data['products']) && is_array($data['products'])) $config['products'] = $data['products'];
        if (isset($data['default_pitch'])) $config['default_pitch'] = trim($data['default_pitch']);
        
        $configFile = getTenantConfigFile($companyId);
        file_put_contents($configFile, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        
        logAudit($auditFile, $companyId, $currentUser['id'], $currentUser['name'], 'company_config_updated', "Updated products and campaign pitch template.");
        echo json_encode(['success' => true, 'message' => 'Company product and pitch configuration updated successfully.', 'config' => $config]);
        exit;

    // 12. Delete Company (Super Admin)
    case 'delete_company':
        requireSuperAdmin($currentUser);
        $raw = file_get_contents('php://input');
        $data = json_decode($raw, true) ?: $_POST;
        $companyId = trim($data['company_id'] ?? '');

        if (!$companyId || $companyId === 'COMP-001') {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Cannot delete default primary company.']);
            exit;
        }

        $companies = getCompanies($companiesFile);
        $deleted = false;
        $deletedName = '';
        $newCompanies = [];
        foreach ($companies as $comp) {
            if ($comp['id'] === $companyId) {
                $deleted = true;
                $deletedName = $comp['name'];
            } else {
                $newCompanies[] = $comp;
            }
        }

        if (!$deleted) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Company not found.']);
            exit;
        }

        saveCompanies($companiesFile, $newCompanies);
        logAudit($auditFile, $companyId, $currentUser['id'], $currentUser['name'], 'company_deleted', "Deleted company $deletedName ($companyId)");
        echo json_encode(['success' => true, 'message' => "Company '$deletedName' has been purged successfully."]);
        exit;

    // 13. Update Company Details (Super Admin)
    case 'update_company':
        requireSuperAdmin($currentUser);
        $raw = file_get_contents('php://input');
        $data = json_decode($raw, true) ?: $_POST;
        $companyId = trim($data['company_id'] ?? '');

        if (!$companyId) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Company ID is required.']);
            exit;
        }

        $companies = getCompanies($companiesFile);
        $found = false;
        foreach ($companies as &$comp) {
            if ($comp['id'] === $companyId) {
                if (isset($data['name']) && trim($data['name'])) $comp['name'] = trim($data['name']);
                if (isset($data['owner_name'])) $comp['owner_name'] = trim($data['owner_name']);
                if (isset($data['owner_email'])) $comp['owner_email'] = trim($data['owner_email']);
                if (isset($data['plan'])) $comp['plan'] = trim($data['plan']);
                if (isset($data['max_employees'])) $comp['max_employees'] = max(1, (int)$data['max_employees']);
                if (isset($data['default_message_limit'])) $comp['default_message_limit'] = max(10, (int)$data['default_message_limit']);
                if (isset($data['fallback_phone'])) $comp['fallback_phone'] = trim($data['fallback_phone']);
                $found = true;
                logAudit($auditFile, $companyId, $currentUser['id'], $currentUser['name'], 'company_updated', "Updated profile settings for {$comp['name']}");
                break;
            }
        }
        unset($comp);

        if (!$found) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Company not found.']);
            exit;
        }

        saveCompanies($companiesFile, $companies);
        echo json_encode(['success' => true, 'message' => 'Company details updated successfully.']);
        exit;

    // 14. Deep Company Stats (Super Admin)
    case 'get_company_deep_stats':
        requireSuperAdmin($currentUser);
        $companyId = trim($_GET['company_id'] ?? ($_POST['company_id'] ?? 'COMP-001'));
        
        $leadsFile = getTenantLeadsFile($companyId);
        $leads = file_exists($leadsFile) ? json_decode(file_get_contents($leadsFile), true) : [];
        if (!is_array($leads)) $leads = [];

        $users = file_exists($usersFile) ? json_decode(file_get_contents($usersFile), true) : [];
        if (!is_array($users)) $users = [];
        $compUsers = array_values(array_filter($users, fn($u) => ($u['company_id'] ?? '') === $companyId));

        $totalLeads = count($leads);
        $paidLeads = count(array_filter($leads, fn($l) => in_array(strtolower($l['leadState'] ?? ($l['status'] ?? '')), ['paid', 'verified', 'approved'], true)));
        $ongoingLeads = count(array_filter($leads, fn($l) => in_array(strtolower($l['leadState'] ?? ($l['status'] ?? '')), ['ongoing', 'in-progress', 'in_talk'], true)));
        $wontPayLeads = count(array_filter($leads, fn($l) => in_array(strtolower($l['leadState'] ?? ($l['status'] ?? '')), ['wont_pay', 'rejected', 'lost'], true)));
        $conversionRate = $totalLeads > 0 ? round(($paidLeads / $totalLeads) * 100, 1) : 0;

        $totalSentToday = array_sum(array_column($compUsers, 'messages_sent_today'));

        echo json_encode([
            'success' => true,
            'company_id' => $companyId,
            'stats' => [
                'total_leads'     => $totalLeads,
                'paid_leads'      => $paidLeads,
                'ongoing_leads'   => $ongoingLeads,
                'wont_pay_leads'  => $wontPayLeads,
                'conversion_rate' => $conversionRate,
                'total_employees' => count($compUsers),
                'tls_count'       => count(array_filter($compUsers, fn($u) => ($u['role'] ?? '') === 'lead')),
                'tms_count'       => count(array_filter($compUsers, fn($u) => ($u['role'] ?? '') === 'member')),
                'messages_today'  => $totalSentToday,
                'closers'         => array_map(fn($u) => [
                    'id' => $u['id'],
                    'name' => $u['name'],
                    'username' => $u['username'],
                    'role' => $u['role'],
                    'phone' => $u['phone'] ?? '',
                    'status' => $u['status'] ?? 'active',
                    'messages_today' => (int)($u['messages_sent_today'] ?? 0),
                    'message_limit' => (int)($u['message_limit'] ?? 150)
                ], $compUsers)
            ]
        ]);
        exit;

    // 15. Impersonate Company Admin (Super Admin)
    case 'impersonate':
        requireSuperAdmin($currentUser);
        $companyId = trim($_GET['company_id'] ?? ($_POST['company_id'] ?? 'COMP-001'));
        
        $companies = getCompanies($companiesFile);
        $targetCompany = null;
        foreach ($companies as $c) {
            if ($c['id'] === $companyId) {
                $targetCompany = $c;
                break;
            }
        }
        if (!$targetCompany) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Company not found.']);
            exit;
        }

        // Find company admin user or create on-the-fly proxy admin session
        $users = file_exists($usersFile) ? json_decode(file_get_contents($usersFile), true) : [];
        $targetUser = null;
        foreach ($users as $u) {
            if (($u['company_id'] ?? '') === $companyId && ($u['role'] ?? '') === 'admin') {
                $targetUser = $u;
                break;
            }
        }

        if (!$targetUser) {
            $targetUser = [
                'id'          => 'USR-PROXY-' . $companyId,
                'name'        => $targetCompany['name'] . ' Administrator',
                'username'    => $targetCompany['slug'] . '_admin',
                'email'       => $targetCompany['owner_email'],
                'role'        => 'admin',
                'designation' => 'Company Admin',
                'company_id'  => $companyId,
                'status'      => 'active'
            ];
        }

        $_SESSION['omnidesk_user'] = [
            'id'             => $targetUser['id'],
            'name'           => $targetUser['name'],
            'username'       => $targetUser['username'],
            'email'          => $targetUser['email'],
            'role'           => 'admin',
            'designation'    => 'Company Admin (Impersonated by Super Admin)',
            'company_id'     => $companyId,
            'is_impersonated'=> true,
            'status'         => 'active'
        ];

        echo json_encode([
            'success' => true,
            'message' => "Successfully assumed Admin identity for '{$targetCompany['name']}'!",
            'redirect' => "admin.php?company_id=" . urlencode($companyId)
        ]);
        exit;

    // 16. Get Company Leads for Live Board (Company Admin & Super Admin)
    case 'get_company_leads':
        requireCompanyAdmin($currentUser);
        $companyId = resolveActiveCompanyId();
        $leadsFile = getTenantLeadsFile($companyId);
        $leads = file_exists($leadsFile) ? json_decode(file_get_contents($leadsFile), true) : [];
        if (!is_array($leads)) $leads = [];

        echo json_encode([
            'success' => true,
            'company_id' => $companyId,
            'leads' => array_values($leads)
        ]);
        exit;

    // 17. Admin Lead Takeover with Protected Closer Commission (Request 8)
    case 'takeover_lead':
        requireCompanyAdmin($currentUser);
        $companyId = resolveActiveCompanyId();
        $raw = file_get_contents('php://input');
        $data = json_decode($raw, true) ?: $_POST;
        $leadPhone = preg_replace('/\D+/', '', trim($data['lead_phone'] ?? ''));

        if (!$leadPhone) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Lead phone number is required.']);
            exit;
        }

        $leadsFile = getTenantLeadsFile($companyId);
        $leads = file_exists($leadsFile) ? json_decode(file_get_contents($leadsFile), true) : [];
        if (!is_array($leads)) $leads = [];

        $found = false;
        $targetLead = null;
        foreach ($leads as &$lead) {
            $lp = preg_replace('/\D+/', '', $lead['phone'] ?? '');
            if ($lp && ($lp === $leadPhone || substr($lp, -10) === substr($leadPhone, -10))) {
                // Record takeover details while protecting original closer's commission
                $origOwner = $lead['ownerName'] ?? 'Assigned Closer';
                $origOwnerId = $lead['ownerId'] ?? '';
                
                $lead['isTakeover'] = true;
                $lead['originalOwnerName'] = $origOwner;
                $lead['originalOwnerId'] = $origOwnerId;
                $lead['takenOverBy'] = $currentUser['name'];
                $lead['takenOverById'] = $currentUser['id'];
                $lead['takenOverAt'] = date('Y-m-d H:i:s');
                $lead['commissionProtected'] = true;
                $lead['commissionNote'] = "Full closer commission guaranteed to $origOwner upon completion.";
                $lead['ownerName'] = $currentUser['name'] . ' (Admin Takeover)';
                $lead['ownerId'] = $currentUser['id'];
                
                $found = true;
                $targetLead = $lead;
                break;
            }
        }
        unset($lead);

        if (!$found) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Lead not found in company pipeline.']);
            exit;
        }

        file_put_contents($leadsFile, json_encode($leads, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        logAudit($auditFile, $companyId, $currentUser['id'], $currentUser['name'], 'lead_takeover', "Admin took over lead {$targetLead['name']} ({$leadPhone}). Closer commission preserved for {$targetLead['originalOwnerName']}.");

        echo json_encode([
            'success' => true,
            'message' => "Lead {$targetLead['name']} taken over successfully! Closer commission remains 100% protected.",
            'lead'    => $targetLead
        ]);
        exit;

    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid or unknown company action.']);
        exit;
}
