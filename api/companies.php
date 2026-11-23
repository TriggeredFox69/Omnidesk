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
require_once __DIR__ . '/quota.php';
require_once __DIR__ . '/lead_store.php';

function getCompanies($file) {
    $comps = [];
    if (file_exists($file)) {
        $data = @json_decode(file_get_contents($file), true);
        if (is_array($data) && !empty($data)) $comps = $data;
    }
    if (empty($comps)) {
        $comps = [
            [
                'id'                    => 'COMP-001',
                'name'                  => 'Nunno Official',
                'short_name'            => 'Nunno',
                'slug'                  => 'nunno',
                'owner_name'            => 'Askar Ali',
                'owner_email'           => 'numeniousnexus@gmail.com',
                'plan'                  => 'Enterprise Billion',
                'badge'                 => 'Scalper Alpha & SaaS',
                'logo'                  => 'assets/images/nunno-logo.png',
                'brand_color'           => '#8b5cf6',
                'max_employees'         => 100,
                'default_message_limit' => 250,
                'status'                => 'active',
                'created_at'            => date('Y-m-d H:i:s')
            ],
            [
                'id'                    => 'COMP-004',
                'name'                  => 'Metalink',
                'short_name'            => 'Metalink',
                'slug'                  => 'metalink',
                'owner_name'            => 'Zeeshan',
                'owner_email'           => 'admin@metalink.pk',
                'plan'                  => 'Enterprise Growth',
                'badge'                 => 'Digital Trading & Closer Desk',
                'logo'                  => 'assets/images/metalink-logo.webp',
                'brand_color'           => '#06b6d4',
                'max_employees'         => 50,
                'default_message_limit' => 250,
                'status'                => 'active',
                'created_at'            => date('Y-m-d H:i:s')
            ]
        ];
        saveCompanies($file, $comps);
        return $comps;
    }

    // The two demo companies from the early build (Apex Crypto Signals, Pak Trade Academy) are
    // removed for good, with their admin logins. Only the untouched demo entries match.
    $demo = ['COMP-002' => 'admin@apexsignals.pk', 'COMP-003' => 'bilal@paktrade.edu.pk'];
    $demoGone = [];
    $comps = array_values(array_filter($comps, function ($c) use ($demo, &$demoGone) {
        $id = $c['id'] ?? '';
        if (isset($demo[$id]) && strtolower($c['owner_email'] ?? '') === $demo[$id]) { $demoGone[] = $id; return false; }
        return true;
    }));
    if ($demoGone) {
        saveCompanies($file, $comps);
        chatlyRemoveCompanyUsers($demoGone);
    }

    $hasMetalink = false;
    foreach ($comps as $c) {
        if (($c['id'] ?? '') === 'COMP-004' || ($c['slug'] ?? '') === 'metalink') {
            $hasMetalink = true;
            break;
        }
    }
    if (!$hasMetalink) {
        $comps[] = [
            'id'                    => 'COMP-004',
            'name'                  => 'Metalink',
            'short_name'            => 'Metalink',
            'slug'                  => 'metalink',
            'owner_name'            => 'Zeeshan',
            'owner_email'           => 'admin@metalink.pk',
            'plan'                  => 'Enterprise Growth',
            'badge'                 => 'Digital Trading & Closer Desk',
            'logo'                  => 'assets/images/metalink-logo.webp',
            'brand_color'           => '#06b6d4',
            'max_employees'         => 50,
            'default_message_limit' => 250,
            'status'                => 'active',
            'created_at'            => date('Y-m-d H:i:s')
        ];
        saveCompanies($file, $comps);
    }
    return $comps;
}

/** Deletes every account (admin, TL, TM) that belongs to these companies. Never the super admin. */
function chatlyRemoveCompanyUsers(array $companyIds): int {
    $removed = 0;
    chatlyUsersUpdate(__DIR__ . '/../data/users.json', function (array &$users) use ($companyIds, &$removed) {
        $before = count($users);
        $users = array_values(array_filter($users, function ($u) use ($companyIds) {
            return ($u['role'] ?? '') === 'superadmin' || !in_array($u['company_id'] ?? '', $companyIds, true);
        }));
        $removed = $before - count($users);
        return $removed > 0 ? null : false;
    });
    return $removed;
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
$rawInput = file_get_contents('php://input');
$jsonInput = $rawInput ? json_decode($rawInput, true) : [];
if (!is_array($jsonInput)) $jsonInput = [];
$action = $_GET['action'] ?? $_POST['action'] ?? ($jsonInput['action'] ?? '');

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

// Read-only lookups don't touch $_SESSION: release the lock so parallel requests don't queue.
if (in_array($action, ['list', 'public_list', 'get_company_config'], true) && session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

switch ($action) {

    // Auto Data limits (Super Admin): how many pool numbers each company may take per day and
    // in total, set according to what the company pays. 0 = unlimited.
    case 'auto_data_usage':
        requireSuperAdmin($currentUser);
        if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
        require_once __DIR__ . '/auto_data.php';
        $limits = [];
        foreach (getCompanies($companiesFile) as $c) if (!empty($c['id'])) $limits[$c['id']] = chatlyAutoDataLimits($c);
        $r = chatlySupabaseRpc('chatly_pool_usage_all', [], 15);
        $poolUsage = ($r['ok'] && is_array($r['data'])) ? (array) ($r['data']['usage'] ?? []) : [];
        $usage = [];
        foreach ($limits as $cid => $lim) {
            $u = (array) ($poolUsage[$cid] ?? []);
            $usage[$cid] = ['today' => (int) ($u['today'] ?? 0), 'total' => (int) ($u['total'] ?? 0),
                'daily_limit' => $lim['daily'], 'total_limit' => $lim['total']];
        }
        echo json_encode($r['ok']
            ? ['success' => true, 'usage' => $usage, 'pool_total' => (int) ($r['data']['pool_total'] ?? 0)]
            : ['success' => false, 'message' => 'Usage is unavailable: ' . ($r['error'] ?? 'Supabase error'), 'usage' => $usage]);
        exit;

    case 'set_auto_data_limits':
        requireSuperAdmin($currentUser);
        $cid = trim((string) ($jsonInput['company_id'] ?? ''));
        $daily = max(0, (int) ($jsonInput['daily_limit'] ?? 0));
        $total = max(0, (int) ($jsonInput['total_limit'] ?? 0));
        $companies = getCompanies($companiesFile);
        $found = false;
        foreach ($companies as &$c) {
            if (($c['id'] ?? '') !== $cid) continue;
            $c['auto_data_daily_limit'] = $daily;
            $c['auto_data_total_limit'] = $total;
            $found = $c['name'] ?? $cid;
            break;
        }
        unset($c);
        if ($found === false) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Company not found.']);
            exit;
        }
        saveCompanies($companiesFile, $companies);
        logAudit($auditFile, $cid, $currentUser['id'] ?? '', $currentUser['name'] ?? 'Super Admin', 'auto_data_limits',
            "Auto Data limits for {$found}: " . ($daily ?: 'unlimited') . ' per day, ' . ($total ?: 'unlimited') . ' in total.');
        echo json_encode(['success' => true]);
        exit;

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
            $totalMessagesToday += chatlyQuotaSentToday($u);
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

    // 1a. Nunno Pure Commission Settlement Ledger (Exclusive to Nunno & Chatly)
    case 'get_nunno_settlement':
        requireSuperAdmin($currentUser);
        require_once __DIR__ . '/earnings.php';
        // Same engine as the closers' wallets and payouts (api/earnings.php):
        // first Rs. 10,000 of each TM's verified sales paid 100%, then plan commission.
        $rows = computeCompanyEarnings(NUNNO_COMPANY_ID);
        $closersSettlement = [];
        $sumRevenue = 0; $sumOwed = 0; $sumDeals = 0; $sumPaidOut = 0; $sumPending = 0;
        foreach ($rows as $r) {
            if (($r['role'] ?? 'member') !== 'member') continue;
            $owed = (float) $r['sales_commission'];
            $sumRevenue += $r['total_sales']; $sumOwed += $owed; $sumDeals += $r['deals_count'];
            $sumPaidOut += $r['paid_out']; $sumPending += $r['pending_withdrawals'];
            $closersSettlement[] = [
                'id'                      => $r['user_id'],
                'name'                    => $r['name'],
                'username'                => $r['username'],
                'phone'                   => $r['phone'],
                'status'                  => $r['status'],
                'deals'                   => $r['deals_count'],
                'gross_revenue'           => $r['total_sales'],
                'full_pay_progress'       => $r['full_pay_progress'],
                'amount_payable_by_nunno' => $owed,
                'nunno_retained_profit'   => max(0, $r['total_sales'] - $owed),
                'paid_out'                => $r['paid_out'],
                'pending_withdrawals'     => $r['pending_withdrawals'],
                'available'               => $r['available'],
                'in_review_count'         => $r['in_review_count'],
            ];
        }
        usort($closersSettlement, fn($a, $b) => $b['gross_revenue'] <=> $a['gross_revenue']);
        echo json_encode([
            'success' => true,
            'summary' => [
                'total_tms'               => count($closersSettlement),
                'total_plans_sold'        => $sumDeals,
                'total_confirmed_revenue' => $sumRevenue,
                'total_payable_by_nunno'  => $sumOwed,
                'total_nunno_profit'      => max(0, $sumRevenue - $sumOwed),
                'total_paid_out'          => $sumPaidOut,
                'total_pending_payouts'   => $sumPending,
                'full_pay_limit'          => NUNNO_FULL_PAY_LIMIT,
                'plan_commission'         => NUNNO_PLAN_COMMISSION,
            ],
            'closers' => $closersSettlement
        ]);
        exit;

    // Nunno invoice (Super Admin): what Nunno collected through Chatly's TMs / TLs and what it
    // owes Chatly for them (TM payouts: first Rs. 10,000 then plan commission; TL Rs. 500 per
    // activated TM). Only these two totals and their lines; Nunno's own margin is not shown.
    case 'get_nunno_invoice':
        requireSuperAdmin($currentUser);
        if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
        require_once __DIR__ . '/earnings.php';
        $lines = [];
        $revenue = 0.0; $payable = 0.0; $tmPayable = 0.0; $tlPayable = 0.0; $tmCount = 0; $tlCount = 0; $activations = 0;
        foreach (computeCompanyEarnings(NUNNO_COMPANY_ID) as $r) {
            $isTL = ($r['role'] ?? 'member') === 'lead';
            $owed = (float) $r['total_earned'];
            if ($owed <= 0 && (float) $r['total_sales'] <= 0) continue;
            $revenue += (float) $r['total_sales'];
            $payable += $owed;
            if ($isTL) { $tlPayable += $owed; $tlCount++; $activations += (int) $r['deals_count']; }
            else { $tmPayable += $owed; $tmCount++; }
            $lines[] = ['name' => $r['name'], 'role' => $isTL ? 'TL' : 'TM', 'deals' => (int) $r['deals_count'],
                'revenue' => (float) $r['total_sales'], 'payable' => $owed, 'paid_out' => (float) $r['paid_out']];
        }
        usort($lines, fn($a, $b) => $b['payable'] <=> $a['payable']);
        echo json_encode(['success' => true, 'generated_at' => date('c'), 'invoice' => [
            'total_revenue_collected' => $revenue,
            'total_payable_to_chatly' => $payable,
            'tm_payable' => $tmPayable, 'tl_payable' => $tlPayable,
            'tm_count' => $tmCount, 'tl_count' => $tlCount, 'tl_activations' => $activations,
            'lines' => $lines,
        ]]);
        exit;

    // Record a confirmed Nunno sale that did not go through the inbox (counts toward earnings)
    case 'record_nunno_sale':
        requireSuperAdmin($currentUser);
        require_once __DIR__ . '/earnings.php';
        $data = $jsonInput ?: $_POST;
        $userId = trim($data['user_id'] ?? ($data['id'] ?? ''));
        $price = (int) ($data['plan_price'] ?? 1700);
        $count = max(1, min(50, (int) ($data['plans'] ?? 1)));
        if (!$userId || !array_key_exists($price, NUNNO_PLAN_COMMISSION)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Choose a closer and a plan (Rs. 1,700, 5,000 or 15,000).']);
            exit;
        }
        $salesFile = __DIR__ . '/../data/nunno_sales.json';
        $fh = fopen($salesFile, 'c+');
        flock($fh, LOCK_EX);
        $salesData = json_decode(stream_get_contents($fh) ?: '[]', true);
        if (!is_array($salesData)) $salesData = [];
        $salesData['manual_sales'] = $salesData['manual_sales'] ?? [];
        for ($i = 0; $i < $count; $i++) {
            $salesData['manual_sales'][] = [
                'id'         => 'MSALE-' . date('ymdHis') . '-' . strtoupper(bin2hex(random_bytes(2))),
                'tm_id'      => $userId,
                'amount'     => $price,
                'plan'       => 'Nunno Rs. ' . number_format($price),
                'created_at' => date('c'),
                'by'         => $currentUser['name'] ?? 'Super Admin',
            ];
        }
        $salesData['updated_at'] = date('Y-m-d H:i:s');
        rewind($fh); ftruncate($fh, 0);
        fwrite($fh, json_encode($salesData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        flock($fh, LOCK_UN); fclose($fh);
        echo json_encode(['success' => true, 'message' => "Recorded {$count} × Rs. " . number_format($price) . " sale(s)."]);
        exit;

    case 'set_nunno_tm_sales':
        requireSuperAdmin($currentUser);
        http_response_code(410);
        echo json_encode(['success' => false, 'error' => 'Plan counts are no longer set by hand: earnings come from verified sales and recorded sales with their real plan price.']);
        exit;

    // 1c. Number Intelligence: every number across every company, tagged for filtering in /ss
    case 'sadmin_numbers':
        requireSuperAdmin($currentUser);
        require_once __DIR__ . '/earnings.php';
        session_write_close();
        $userNames = [];
        foreach (earningsUsers() as $u) $userNames[$u['id'] ?? ''] = $u['name'] ?? ($u['username'] ?? '');
        $rows = [];
        foreach (getCompanies($companiesFile) as $comp) {
            $cid = $comp['id'] ?? '';
            if ($cid === '') continue;
            foreach (earningsCompanyLeads($cid, true) as $l) {
                $phone = preg_replace('/\D+/', '', (string) ($l['phone'] ?? ''));
                if (strlen($phone) < 8) continue;
                $replies = 0; $sent = 0; $failed = 0; $delivered = 0; $lastReply = 0; $lastAny = 0; $firstAt = 0;
                foreach (($l['messages'] ?? []) as $m) {
                    if (!is_array($m)) continue;
                    $ts = (int) ($m['timestamp'] ?? 0);
                    if ($ts > 20000000000) $ts = (int) ($ts / 1000);
                    if ($ts) { $lastAny = max($lastAny, $ts); $firstAt = $firstAt ? min($firstAt, $ts) : $ts; }
                    if (($m['sender'] ?? '') === 'lead') { $replies++; if ($ts) $lastReply = max($lastReply, $ts); }
                    elseif (($m['sender'] ?? '') === 'agent') {
                        $sent++;
                        $ds = $m['deliveryStatus'] ?? '';
                        if ($ds === 'failed') $failed++;
                        if (in_array($ds, ['delivered', 'read'], true)) $delivered++;
                    }
                }
                $lastAny = max($lastAny, (int) ($l['lastMessageAt'] ?? 0));
                $state = earningsLeadState($l);
                $ownerId = (string) ($l['ownerId'] ?? '');
                $rows[] = [
                    'p'  => $phone,
                    'n'  => (string) ($l['name'] ?? ''),
                    'c'  => $cid,
                    's'  => $state,
                    'r'  => $replies,
                    'sn' => $sent,
                    'f'  => $failed,
                    'd'  => $delivered,
                    // Dead number: we messaged it, nothing was ever delivered, and they never replied.
                    'dead' => ($sent > 0 && $replies === 0 && $delivered === 0 && $failed > 0) ? 1 : 0,
                    'lr' => $lastReply,
                    'la' => $lastAny,
                    'fa' => $firstAt,
                    'o'  => $ownerId,
                    'on' => $userNames[$ownerId] ?? (string) ($l['ownerName'] ?? ''),
                    'ci' => (string) ($l['city'] ?? ''),
                    'nw' => (string) ($l['network'] ?? ''),
                    'amt'=> $state === 'paid' || $state === 'pending_verification' ? (float) ($l['paidDetails']['amount'] ?? 0) : 0,
                    'pl' => (string) ($l['paidDetails']['plan'] ?? ''),
                    'it' => (string) ($l['interest'] ?? ''),
                ];
            }
        }
        $companiesOut = array_map(fn($c) => ['id' => $c['id'] ?? '', 'name' => $c['name'] ?? ''], getCompanies($companiesFile));
        echo json_encode(['success' => true, 'generated_at' => time(), 'companies' => $companiesOut, 'rows' => $rows], JSON_UNESCAPED_UNICODE);
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
        if (chatlyLeadStoreOn()) {
            // chats in Supabase: same stream, read from there (no tenant files to scan)
            foreach ($companyMap as $cid => $cname) {
                foreach (chatlyLeadsAll((string) $cid, true) as $lead) {
                    foreach (($lead['messages'] ?? []) as $m) {
                        $stream[] = ['id' => ($lead['id'] ?? '') . '-' . rand(1000, 9999), 'company_id' => $cid, 'company_name' => $cname,
                            'lead_name' => $lead['name'] ?? ($lead['display'] ?? 'Contact'), 'lead_phone' => $lead['phone'] ?? '',
                            'closer_name' => $lead['ownerName'] ?? 'Closer', 'sender' => $m['sender'] ?? 'lead', 'text' => $m['text'] ?? '',
                            'time' => $m['time'] ?? 'Just now', 'state' => $lead['leadState'] ?? 'ongoing'];
                    }
                }
            }
            $tenantsDir = '';   // skip the file scan below
        }
        if ($tenantsDir !== '' && is_dir($tenantsDir)) {
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
        if (!chatlyLeadStoreOn() && file_exists($rootLeadsPath)) {
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

        // (No made-up demo lines: an empty stream means there are no messages yet.)
        echo json_encode(['success' => true, 'stream' => array_slice(array_reverse($stream), 0, 50)]);
        exit;

    // 2. List All Companies (Public / Super Admin)
    case 'public_list':
    case 'list':
        $companies = getCompanies($companiesFile);
        $users = file_exists($usersFile) ? json_decode(file_get_contents($usersFile), true) : [];
        if (!is_array($users)) $users = [];

        $isSuper = ($currentUser && ($currentUser['role'] ?? '') === 'superadmin');
        $result = [];
        foreach ($companies as $comp) {
            if (!$isSuper && ($comp['status'] ?? 'active') !== 'active') continue;
            $cId = $comp['id'];
            $compUsers = array_filter($users, fn($u) => ($u['company_id'] ?? '') === $cId);
            $comp['employee_count'] = count($compUsers);
            $comp['tl_count']       = count(array_filter($compUsers, fn($u) => ($u['role'] ?? '') === 'lead'));
            $comp['tm_count']       = count(array_filter($compUsers, fn($u) => ($u['role'] ?? '') === 'member'));
            $comp['messages_today'] = array_sum(array_map('chatlyQuotaSentToday', $compUsers));
            $result[] = $comp;
        }

        echo json_encode([
            'success'   => true,
            'companies' => $result
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
        $logo          = trim($data['logo'] ?? '');
        $fallbackPhone = trim($data['fallback_phone'] ?? '');
        $brandColor    = trim($data['brand_color'] ?? '#10b981');
        
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

        // Next number after the highest ever used (never count + 1: after a delete that would hand
        // out an existing company's id, or a deleted company's old chats and Auto Data usage).
        $maxNum = 4; // COMP-002 / COMP-003 were the removed demo companies
        foreach ($companies as $c) {
            if (preg_match('/^COMP-(\d+)$/', $c['id'] ?? '', $m)) $maxNum = max($maxNum, (int) $m[1]);
        }
        $companyId = 'COMP-' . str_pad($maxNum + 1, 3, '0', STR_PAD_LEFT);
        $newCompany = [
            'id'                    => $companyId,
            'name'                  => $name,
            'short_name'            => $data['short_name'] ?? $name,
            'slug'                  => $slug,
            'owner_name'            => $ownerName ?: $name . ' Admin',
            'owner_email'           => $ownerEmail,
            'plan'                  => $plan,
            'logo'                  => $logo ?: 'assets/images/chatly-icon.png',
            'brand_color'           => $brandColor,
            'fallback_phone'        => $fallbackPhone ?: '+923318036576',
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
        // Welcome email to the owner from the Chatly Gmail sender (login pointer only, never the
        // password). Sent after the response; a failed email never affects the new company.
        try {
            require_once __DIR__ . '/mailer.php';
            chatlyMailFinishResponse();
            chatlyMailAutoCompanyWelcome($newCompany, $adminUsername, (string) ($currentUser['name'] ?? 'Chatly'));
        } catch (Throwable $mailErr) {
            // best effort only
        }
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

    // 4b. Update Company Logo (Super Admin)
    case 'update_logo':
        requireSuperAdmin($currentUser);
        $raw = file_get_contents('php://input');
        $data = json_decode($raw, true) ?: $_POST;
        $companyId = trim($data['company_id'] ?? '');
        $logo = trim($data['logo'] ?? '');

        if (!$companyId || !$logo) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Company ID and Logo are required.']);
            exit;
        }

        $companies = getCompanies($companiesFile);
        $found = false;
        foreach ($companies as &$comp) {
            if ($comp['id'] === $companyId || ($comp['slug'] ?? '') === $companyId) {
                $comp['logo'] = $logo;
                $found = true;
                logAudit($auditFile, $companyId, $currentUser['id'], $currentUser['name'], 'company_logo_updated', "Updated logo for company {$comp['name']}");
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
        echo json_encode(['success' => true, 'message' => 'Company logo updated successfully!', 'logo' => $logo]);
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
        
        // Enrich with company-level TL commission
        $companies = getCompanies($companiesFile);
        foreach ($companies as $comp) {
            if ($comp['id'] === $companyId) {
                $config['tl_commission_per_tm'] = (int)($comp['tl_commission_per_tm'] ?? ($config['tl_commission_per_tm'] ?? 500));
                break;
            }
        }
        echo json_encode(['success' => true, 'company_id' => $companyId, 'config' => $config]);
        exit;

    // 11. Update Company Products, Pitch & TL Commission Config
    case 'update_company_config':
        requireCompanyAdmin($currentUser);
        $companyId = resolveActiveCompanyId();
        $raw = file_get_contents('php://input');
        $data = json_decode($raw, true) ?: $_POST;
        
        $config = getTenantConfig($companyId);
        if (isset($data['company_name'])) $config['company_name'] = trim($data['company_name']);
        if (isset($data['products']) && is_array($data['products'])) $config['products'] = $data['products'];
        if (isset($data['default_pitch'])) $config['default_pitch'] = trim($data['default_pitch']);
        if (isset($data['tl_commission_per_tm'])) {
            $config['tl_commission_per_tm'] = max(0, (int)$data['tl_commission_per_tm']);
        }
        // The company's own link for {link} in campaigns. Never Chatly's domain: cold campaign
        // messages carrying chatly.pk got it listed as "Spam URLs" by web filters (FortiGuard).
        if (isset($data['campaign_link'])) {
            $lk = trim((string) $data['campaign_link']);
            if ($lk !== '' && !preg_match('#^https?://#i', $lk)) $lk = 'https://' . $lk;
            if ($lk !== '' && (!filter_var($lk, FILTER_VALIDATE_URL) || preg_match('#^https?://([a-z0-9-]+\.)*chatly\.pk(/|$)#i', $lk))) {
                echo json_encode(['success' => false, 'error' => 'Campaign link: enter your own website or WhatsApp link (not chatly.pk).']);
                exit;
            }
            $config['campaign_link'] = mb_substr($lk, 0, 300);
        }

        $configFile = getTenantConfigFile($companyId);
        file_put_contents($configFile, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        // Also sync to companies.json
        $companies = getCompanies($companiesFile);
        foreach ($companies as &$comp) {
            if ($comp['id'] === $companyId) {
                if (isset($data['tl_commission_per_tm'])) {
                    $comp['tl_commission_per_tm'] = max(0, (int)$data['tl_commission_per_tm']);
                }
                break;
            }
        }
        unset($comp);
        saveCompanies($companiesFile, $companies);
        
        logAudit($auditFile, $companyId, $currentUser['id'], $currentUser['name'], 'company_config_updated', "Updated products, campaign pitch, and TL commission rate.");
        echo json_encode(['success' => true, 'message' => 'Company product, pitch, and TL commission configuration updated successfully.', 'config' => $config]);
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
        chatlyRemoveCompanyUsers([$companyId]); // no orphan admin / closer logins left behind
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
        
        $leads = chatlyLeadsAll($companyId);   // leads.json, or Supabase when chats are stored there

        $users = file_exists($usersFile) ? json_decode(file_get_contents($usersFile), true) : [];
        if (!is_array($users)) $users = [];
        $compUsers = array_values(array_filter($users, fn($u) => ($u['company_id'] ?? '') === $companyId));

        $totalLeads = count($leads);
        $paidLeads = count(array_filter($leads, fn($l) => in_array(strtolower($l['leadState'] ?? ($l['status'] ?? '')), ['paid', 'verified', 'approved'], true)));
        $ongoingLeads = count(array_filter($leads, fn($l) => in_array(strtolower($l['leadState'] ?? ($l['status'] ?? '')), ['ongoing', 'in-progress', 'in_talk'], true)));
        $wontPayLeads = count(array_filter($leads, fn($l) => in_array(strtolower($l['leadState'] ?? ($l['status'] ?? '')), ['wont_pay', 'rejected', 'lost'], true)));
        $conversionRate = $totalLeads > 0 ? round(($paidLeads / $totalLeads) * 100, 1) : 0;

        $totalSentToday = array_sum(array_map('chatlyQuotaSentToday', $compUsers));

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
                    'messages_today' => chatlyQuotaSentToday($u),
                    'message_limit' => (int)($u['message_limit'] ?? 150)
                ], $compUsers)
            ]
        ]);
        exit;

    // 15. Impersonate Company Admin (Super Admin)
    case 'impersonate':
        requireSuperAdmin($currentUser);
        $companyId = trim($_GET['company_id'] ?? ($_POST['company_id'] ?? ($jsonInput['company_id'] ?? '')));
        if (!$companyId) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Company ID is required for impersonation.']);
            exit;
        }
        
        $companies = getCompanies($companiesFile);
        $targetCompany = null;
        foreach ($companies as $c) {
            if ($c['id'] === $companyId || ($c['slug'] ?? '') === $companyId) {
                $targetCompany = $c;
                break;
            }
        }
        if (!$targetCompany) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => "Company '$companyId' not found."]);
            exit;
        }

        // Save original superadmin identity so they can return anytime
        if (empty($_SESSION['omnidesk_superadmin_original']) && ($currentUser['role'] ?? '') === 'superadmin') {
            $_SESSION['omnidesk_superadmin_original'] = $currentUser;
        }

        // Find company admin user or create on-the-fly proxy admin session
        $users = file_exists($usersFile) ? json_decode(file_get_contents($usersFile), true) : [];
        if (!is_array($users)) $users = [];
        $targetUser = null;
        foreach ($users as $u) {
            if (($u['company_id'] ?? '') === $targetCompany['id'] && in_array(($u['role'] ?? ''), ['admin'], true)) {
                $targetUser = $u;
                break;
            }
        }

        if (!$targetUser) {
            $targetUser = [
                'id'          => 'USR-PROXY-' . $targetCompany['id'],
                'name'        => $targetCompany['name'] . ' Administrator',
                'username'    => $targetCompany['slug'] . '_admin',
                'email'       => $targetCompany['owner_email'],
                'role'        => 'admin',
                'designation' => 'Company Admin',
                'company_id'  => $targetCompany['id'],
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
            'company_id'     => $targetCompany['id'],
            'company_name'   => $targetCompany['name'],
            'is_impersonated'=> true,
            'status'         => 'active'
        ];

        echo json_encode([
            'success'  => true,
            'message'  => "Successfully assumed Admin identity for '{$targetCompany['name']}'!",
            'redirect' => "/cc?portal=1&company_id=" . urlencode($targetCompany['id'])
        ]);
        exit;

    // 16. Get Company Leads for Live Board (Company Admin & Super Admin)
    case 'get_company_leads':
        requireCompanyAdmin($currentUser);
        $companyId = resolveActiveCompanyId();
        $leads = chatlyLeadsAll($companyId);   // leads.json, or Supabase when chats are stored there

        // The lead board only shows name, phone, owner and state: send no chat history
        // (it was every message of every lead, megabytes on each load).
        $board = [];
        foreach ($leads as $l) {
            if (!is_array($l)) continue;
            $l['messageCount'] = is_array($l['messages'] ?? null) ? count($l['messages']) : (int) ($l['messageCount'] ?? 0);
            unset($l['messages']);
            $board[] = $l;
        }

        echo json_encode([
            'success' => true,
            'company_id' => $companyId,
            'leads' => $board
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

        if (chatlyLeadStoreOn()) {
            // chats in Supabase: change just this lead
            require_once __DIR__ . '/lead_store.php';
            try {
                $cur = chatlyStoreFindLead($companyId, '', $leadPhone);
                if (!$cur) {
                    http_response_code(404);
                    echo json_encode(['success' => false, 'error' => 'Lead not found in company pipeline.']);
                    exit;
                }
                $origOwner = $cur['ownerName'] ?? 'Assigned Closer';
                $targetLead = chatlyStorePatchLead($companyId, (string) $cur['id'], '', [
                    'isTakeover' => true, 'originalOwnerName' => $origOwner, 'originalOwnerId' => $cur['ownerId'] ?? '',
                    'takenOverBy' => $currentUser['name'], 'takenOverById' => $currentUser['id'], 'takenOverAt' => date('Y-m-d H:i:s'),
                    'commissionProtected' => true, 'commissionNote' => "Full closer commission guaranteed to $origOwner upon completion.",
                    'ownerName' => $currentUser['name'] . ' (Admin Takeover)', 'ownerId' => $currentUser['id'],
                ]) ?: $cur;
            } catch (Throwable $e) {
                http_response_code(503);
                echo json_encode(['success' => false, 'error' => 'Chats are temporarily unavailable. Please try again.']);
                exit;
            }
            logAudit($auditFile, $companyId, $currentUser['id'], $currentUser['name'], 'lead_takeover', "Admin took over lead {$targetLead['name']} ({$leadPhone}). Closer commission preserved for {$targetLead['originalOwnerName']}.");
            echo json_encode(['success' => true, 'message' => "Lead {$targetLead['name']} taken over successfully! Closer commission remains 100% protected.", 'lead' => $targetLead]);
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
