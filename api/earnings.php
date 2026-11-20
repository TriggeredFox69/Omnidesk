<?php
/**
 * Earnings engine: the ONE place that decides what a Team Member has earned and
 * can withdraw. The workspace, the admin portals, /ss and the withdrawal API all
 * read from here, so the numbers always agree and nobody can withdraw money the
 * server has not credited.
 *
 * Nunno (COMP-001) special commission:
 *   - The first Rs. 10,000 of a TM's verified sales is paid to the TM in full
 *     (sell Rs. 10,000, earn Rs. 10,000). Counted rupee by rupee: a sale that
 *     crosses the line pays 100% up to 10,000 and the plan rate on the rest.
 *   - After that, every plan pays its fixed commission:
 *       Rs. 1,700 -> Rs. 700 · Rs. 5,000 -> Rs. 2,000 · Rs. 15,000 -> Rs. 10,000
 *     (a custom amount uses the rate of the largest plan it covers).
 * Other companies: the commission stored on the verified sale (from that company's
 * product list), else the matching product's commission.
 * Team Leaders also earn their recruiting bounty (tl_commission_balance).
 * Only verified (paid) sales count; sales still in review are shown but not payable.
 */

require_once __DIR__ . '/tenant_manager.php';

const NUNNO_COMPANY_ID     = 'COMP-001';
const NUNNO_FULL_PAY_LIMIT = 10000;
const NUNNO_PLAN_COMMISSION = [15000 => 10000, 5000 => 2000, 1700 => 700];   // price => commission
const MIN_WITHDRAWAL       = 500;

function earningsReadJson(string $file): array {
    if (!is_file($file)) return [];
    $d = json_decode((string) @file_get_contents($file), true);
    return is_array($d) ? $d : [];
}

function earningsUsers(): array {
    return earningsReadJson(__DIR__ . '/../data/users.json');
}

function earningsFindUser(string $userId): ?array {
    foreach (earningsUsers() as $u) if (($u['id'] ?? '') === $userId) return $u;
    return null;
}

/** All leads of a company (tenant file + the legacy global file for Nunno), de-duplicated. */
function earningsCompanyLeads(string $companyId): array {
    $files = [getTenantLeadsFile($companyId)];
    if ($companyId === NUNNO_COMPANY_ID) $files[] = __DIR__ . '/../data/leads.json';
    $seen = [];
    $out = [];
    foreach ($files as $f) {
        foreach (earningsReadJson($f) as $l) {
            if (!is_array($l)) continue;
            $lc = $l['company_id'] ?? $companyId;
            if ($lc !== $companyId && !($companyId === NUNNO_COMPANY_ID && strtolower((string) $lc) === 'nunno')) continue;
            $key = ($l['id'] ?? '') . '|' . preg_replace('/\D+/', '', (string) ($l['phone'] ?? ''));
            if (isset($seen[$key])) continue;
            $seen[$key] = true;
            $out[] = $l;
        }
    }
    return $out;
}

function earningsLeadState(array $l): string {
    $s = $l['leadState'] ?? '';
    $t = $l['status'] ?? '';
    if (in_array('pending_verification', [$s, $t], true) || in_array('pending', [$s, $t], true)) return 'pending_verification';
    if ($s === 'paid' || $t === 'paid') return 'paid';
    if ($s === 'useless' || $t === 'useless') return 'useless';
    if ($s === 'ongoing' || $t === 'ongoing') return 'ongoing';
    return 'fresh';
}

/** Who gets credit for a sale (an admin takeover keeps the original closer's credit). */
function earningsSaleOwner(array $l): string {
    return (string) ($l['paidDetails']['closedById'] ?? ($l['originalOwnerId'] ?? ($l['ownerId'] ?? '')));
}

function earningsSaleAmount(array $l): float {
    $amt = (float) ($l['paidDetails']['amount'] ?? 0);
    if ($amt > 0) return $amt;
    $interest = (string) ($l['interest'] ?? '');
    if (stripos($interest, 'whale') !== false) return 15000;
    if (stripos($interest, 'starter') !== false) return 1700;
    return 5000;
}

function earningsSaleTime(array $l): int {
    foreach ([$l['paidDetails']['closedAt'] ?? null, $l['verifiedAt'] ?? null, $l['lastMessageAt'] ?? null] as $v) {
        if (is_numeric($v)) return (int) ($v > 20000000000 ? $v / 1000 : $v);
        if (is_string($v) && ($t = strtotime($v))) return $t;
    }
    return 0;
}

/** Nunno: commission on the part of a sale beyond the first Rs. 10,000. */
function nunnoPlanCommission(float $amount): float {
    foreach (NUNNO_PLAN_COMMISSION as $price => $comm) {
        if (abs($amount - $price) < 1) return (float) $comm;
    }
    $rate = 700 / 1700;
    foreach (NUNNO_PLAN_COMMISSION as $price => $comm) {
        if ($amount >= $price) { $rate = $comm / $price; break; }
    }
    return round($amount * $rate);
}

function earningsProductCommission(string $companyId, array $l, float $amount): float {
    if (isset($l['paidDetails']['commission']) && is_numeric($l['paidDetails']['commission'])) {
        return (float) $l['paidDetails']['commission'];
    }
    $cfg = function_exists('getTenantConfig') ? getTenantConfig($companyId) : [];
    $plan = strtolower((string) ($l['paidDetails']['plan'] ?? ''));
    foreach (($cfg['products'] ?? []) as $p) {
        if (($plan !== '' && strtolower((string) ($p['name'] ?? '')) === $plan) || abs((float) ($p['amount'] ?? -1) - $amount) < 1) {
            return (float) ($p['commission'] ?? 0);
        }
    }
    return 0.0;
}

function earningsWithdrawals(): array {
    return earningsReadJson(__DIR__ . '/../data/withdrawals.json');
}

/** Full earnings statement for one user. */
function computeEarnings(string $userId, ?array $user = null, ?array $leadsCache = null, ?array $wdCache = null): array {
    $user = $user ?: earningsFindUser($userId) ?: ['id' => $userId];
    $companyId = (string) ($user['company_id'] ?? NUNNO_COMPANY_ID);
    $username = strtolower((string) ($user['username'] ?? ''));
    $leads = $leadsCache ?? earningsCompanyLeads($companyId);
    $isNunno = ($companyId === NUNNO_COMPANY_ID);

    $mine = array_values(array_filter($leads, function ($l) use ($userId, $username) {
        $owner = earningsSaleOwner($l);
        return $owner === $userId || ($owner === '' && $username !== '' && strtolower((string) ($l['ownerUsername'] ?? '')) === $username);
    }));
    $paid = array_values(array_filter($mine, fn($l) => earningsLeadState($l) === 'paid'));
    if ($isNunno) {
        // Sales the super admin recorded by hand (with their real plan price) count too.
        $manual = earningsReadJson(__DIR__ . '/../data/nunno_sales.json')['manual_sales'] ?? [];
        foreach ($manual as $m) {
            if (($m['tm_id'] ?? '') !== $userId) continue;
            $paid[] = ['id' => $m['id'] ?? '', 'name' => 'Recorded sale', 'display' => 'Recorded by ' . ($m['by'] ?? 'admin'),
                'paidDetails' => ['amount' => (float) ($m['amount'] ?? 0), 'plan' => $m['plan'] ?? '', 'closedAt' => $m['created_at'] ?? null]];
        }
    }
    $inReview = array_values(array_filter($mine, fn($l) => earningsLeadState($l) === 'pending_verification'));
    usort($paid, fn($a, $b) => earningsSaleTime($a) <=> earningsSaleTime($b));

    $running = 0.0;
    $salesCommission = 0.0;
    $deals = [];
    foreach ($paid as $l) {
        $amount = earningsSaleAmount($l);
        $before = $running;
        $running += $amount;
        if ($isNunno) {
            $fullPart = max(0, min($amount, NUNNO_FULL_PAY_LIMIT - $before));
            $rest = $amount - $fullPart;
            $restComm = $rest > 0 ? round(nunnoPlanCommission($amount) * ($rest / $amount)) : 0;
            $comm = $fullPart + $restComm;
            $rule = $rest <= 0 ? 'First Rs. 10,000 (100%)' : ($fullPart > 0 ? 'Crossed Rs. 10,000: part 100%, part plan commission' : 'Plan commission');
        } else {
            $comm = earningsProductCommission($companyId, $l, $amount);
            $rule = 'Product commission';
        }
        $salesCommission += $comm;
        $deals[] = [
            'lead_id' => $l['id'] ?? '', 'name' => $l['name'] ?? '', 'phone' => $l['display'] ?? ($l['phone'] ?? ''),
            'plan' => $l['paidDetails']['plan'] ?? ($l['interest'] ?? ''), 'amount' => $amount,
            'commission' => $comm, 'rule' => $rule, 'closed_at' => earningsSaleTime($l) ?: null,
        ];
    }

    $tlBounty = (float) ($user['tl_commission_balance'] ?? 0);
    $earned = $salesCommission + $tlBounty;

    $wds = array_values(array_filter($wdCache ?? earningsWithdrawals(), fn($w) => ($w['userId'] ?? '') === $userId));
    $sum = fn($st) => array_sum(array_map(fn($w) => (float) ($w['amount'] ?? 0), array_filter($wds, fn($w) => ($w['status'] ?? '') === $st)));
    $paidOut = $sum('approved');
    $pending = $sum('pending');
    $available = max(0, $earned - $paidOut - $pending);

    return [
        'user_id' => $userId,
        'name' => $user['name'] ?? '',
        'role' => $user['role'] ?? 'member',
        'company_id' => $companyId,
        'scheme' => $isNunno ? 'nunno_first_10k' : 'product_commission',
        'total_sales' => $running,
        'deals_count' => count($paid),
        'in_review_count' => count($inReview),
        'in_review_amount' => array_sum(array_map('earningsSaleAmount', $inReview)),
        'full_pay_progress' => $isNunno ? min($running, NUNNO_FULL_PAY_LIMIT) : null,
        'full_pay_limit' => $isNunno ? NUNNO_FULL_PAY_LIMIT : null,
        'sales_commission' => $salesCommission,
        'tl_bounty' => $tlBounty,
        'total_earned' => $earned,
        'paid_out' => $paidOut,
        'pending_withdrawals' => $pending,
        'available' => $available,
        'min_withdrawal' => MIN_WITHDRAWAL,
        'deals' => array_reverse($deals),
    ];
}

/** Earnings for every TM/TL of a company (admin ledgers). */
function computeCompanyEarnings(string $companyId): array {
    $leads = earningsCompanyLeads($companyId);
    $wds = earningsWithdrawals();
    $rows = [];
    foreach (earningsUsers() as $u) {
        if (($u['company_id'] ?? NUNNO_COMPANY_ID) !== $companyId) continue;
        if (!in_array($u['role'] ?? 'member', ['member', 'lead'], true)) continue;
        $e = computeEarnings($u['id'], $u, $leads, $wds);
        unset($e['deals']);
        $e['username'] = $u['username'] ?? '';
        $e['phone'] = $u['phone'] ?? '';
        $e['status'] = $u['status'] ?? 'active';
        $rows[] = $e;
    }
    return $rows;
}

function earningsSessionUser(): ?array {
    if (session_status() === PHP_SESSION_NONE) @session_start();
    $u = $_SESSION['omnidesk_user'] ?? null;
    if (!is_array($u) || empty($u['id'])) return null;
    // The automatic "System Administrator" fallback session is not a real login.
    if (!empty($u['auto']) || (($u['id'] ?? '') === 'USR-ADMIN-01' && !isset($u['created_at']))) return null;
    return $u;
}

function earningsCanSeeCompany(array $viewer, string $companyId): bool {
    $role = strtolower((string) ($viewer['role'] ?? ''));
    if ($role === 'superadmin') return true;
    return $role === 'admin' && ($viewer['company_id'] ?? NUNNO_COMPANY_ID) === $companyId;
}

// ---------------- HTTP endpoint (only when this file is requested directly) ----------------
if (php_sapi_name() !== 'cli' && basename($_SERVER['SCRIPT_FILENAME'] ?? '') === 'earnings.php') {
    header('Content-Type: application/json');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    $viewer = earningsSessionUser();
    if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
    if (!$viewer) { http_response_code(401); echo json_encode(['success' => false, 'error' => 'Please sign in.']); exit; }
    $action = $_GET['action'] ?? 'my_earnings';

    if ($action === 'my_earnings') {
        echo json_encode(['success' => true, 'earnings' => computeEarnings($viewer['id'])]);
        exit;
    }
    if ($action === 'user_earnings') {
        $target = earningsFindUser((string) ($_GET['user_id'] ?? ''));
        if (!$target || !earningsCanSeeCompany($viewer, $target['company_id'] ?? NUNNO_COMPANY_ID)) {
            http_response_code(403); echo json_encode(['success' => false, 'error' => 'Not allowed.']); exit;
        }
        echo json_encode(['success' => true, 'earnings' => computeEarnings($target['id'], $target)]);
        exit;
    }
    if ($action === 'company_earnings') {
        $companyId = (string) ($_GET['company_id'] ?? ($viewer['company_id'] ?? NUNNO_COMPANY_ID));
        if (!earningsCanSeeCompany($viewer, $companyId)) { http_response_code(403); echo json_encode(['success' => false, 'error' => 'Not allowed.']); exit; }
        echo json_encode(['success' => true, 'company_id' => $companyId, 'closers' => computeCompanyEarnings($companyId)]);
        exit;
    }
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Unknown action.']);
}
