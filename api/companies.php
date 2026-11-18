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

        foreach ($users as $u) {
            if (($u['role'] ?? '') === 'superadmin') continue;
            $totalEmployees++;
            if (($u['role'] ?? '') === 'lead') $totalTLs++;
            if (($u['role'] ?? '') === 'member') $totalTMs++;
            $totalMessagesToday += (int)($u['messages_sent_today'] ?? 0);
            if (!empty($u['phone']) && ($u['status'] ?? '') === 'active') $activeSessions++;
        }

        echo json_encode([
            'success'   => true,
            'telemetry' => [
                'total_companies'        => count($companies),
                'active_companies'       => count(array_filter($companies, fn($c) => ($c['status'] ?? '') === 'active')),
                'total_employees'        => $totalEmployees,
                'total_tls'              => $totalTLs,
                'total_tms'              => $totalTMs,
                'total_messages_today'   => $totalMessagesToday,
                'active_sessions'        => $activeSessions,
                'system_health'          => 'optimal',
                'server_time'            => date('Y-m-d H:i:s')
            ]
        ]);
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

        logAudit($auditFile, $companyId, $currentUser['id'], $currentUser['name'], 'company_provisioned', "Provisioned $name ($slug) with admin '$adminUsername'.");

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

    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid or unknown company action.']);
        exit;
}
