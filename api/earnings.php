<?php
/**
 * Earnings engine: the ONE place that decides what a Team Member has earned and
 * can withdraw. The workspace, the admin portals, /ss and the withdrawal API all
 * read from here, so the numbers always agree and nobody can withdraw money the
 * server has not credited.
 *
 * Nunno (COMP-001) special commission:
 *   - The first Rs. 10,000 of a TM's verified sales is paid to the TM in full
 *     (sell Rs. 10,000, earn Rs. 10,000). The sale that crosses the line pays only up
 *     to 10,000 (6 x 1,700 = 10,200 -> 10,000).
 *   - A Nunno Team Leader earns Rs. 500 per recruited TM who bought the Rs. 1,000
 *     Nunno x Chatly plan (the TL marks that lead paid; the admin verifies it).
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
// Nunno Team Leaders recruit TMs: each recruited TM who buys the Nunno x Chatly plan (Rs. 1,000),
// marked paid by the TL and verified by the admin, earns the TL Rs. 500.
const NUNNO_TL_PLAN_NAME   = 'Nunno x Chatly Special Plan';
const NUNNO_TL_PLAN_PRICE  = 1000;
const NUNNO_TL_COMMISSION  = 500;

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
function earningsCompanyLeads(string $companyId, bool $withMessages = false): array {
    require_once __DIR__ . '/lead_store.php';
    if (chatlyLeadStoreOn()) return chatlyLeadsAll($companyId, $withMessages);   // chats in Supabase
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

/**
 * Revenue and earnings count from this moment: sales and withdrawals before it were the
 * testing period (owner: "no revenue is done, make it 0"). Saved in data/earnings_start.json
 * the first time this code runs (so: the moment it was deployed). To start counting again from
 * a new moment, delete that file.
 */
function earningsCountFrom(): int {
    static $from = null;
    if ($from !== null) return $from;
    $file = __DIR__ . '/../data/earnings_start.json';
    $d = earningsReadJson($file);
    if (!empty($d['from']) && is_numeric($d['from'])) return $from = (int) $d['from'];
    $from = time();
    @file_put_contents($file, json_encode(['from' => $from, 'from_text' => date('c', $from),
        'note' => 'Sales and withdrawals before this moment (testing period) are not counted.'], JSON_PRETTY_PRINT));
    return $from;
}

function earningsPhoneLast9(string $phone): string {
    $d = preg_replace('/\D+/', '', $phone);
    return strlen($d) >= 9 ? substr($d, -9) : $d;
}

function earningsNormEmail(string $email): string {
    return strtolower(trim($email));
}

function earningsNormName(string $name): string {
    $n = strtolower(trim((string) preg_replace('/\s+/', ' ', $name)));
    if ($n === '' || preg_match('/^\+?\d[\d\s-]*$/', $n)) return '';
    return $n;
}

/** Whether a verified Nunno sale is a Rs. 1,000 Nunno x Chatly activation (pays Rs. 500). */
function earningsIsNunnoActivation(array $l, ?array $ownerUser = null): bool {
    $planName = trim((string) ($l['paidDetails']['plan'] ?? ''));
    if (strcasecmp($planName, NUNNO_TL_PLAN_NAME) === 0) return true;
    $amt = (float) ($l['paidDetails']['amount'] ?? 0);
    if (abs($amt - NUNNO_TL_PLAN_PRICE) < 1) return true;
    // Plain Team Leaders (not Super Leads) only sell the Rs. 1,000 Nunno x Chatly plan
    // in normal inbox chats; Super Leads sell regular Nunno plans too.
    $isPlainTL = $ownerUser
        && strtolower((string) ($ownerUser['role'] ?? '')) === 'lead'
        && empty($ownerUser['super_lead']);
    return $isPlainTL && empty($l['unofficial']);
}

/**
 * Direct Nunno x Chatly plan purchases (Rs. 1,000 each) that were really paid: the admin verified
 * a user's submitted payment (auth.php activate_tm_plan), or the owner confirmed it (Erij, below).
 * "Force upgrade" unlocks without payment and is not counted.
 *
 * Recruited TMs whose Rs. 1,000 plan purchase already appears as a verified activation lead under a
 * TL / Super Lead (who earns Rs. 500 on it) are excluded here so that Rs. 1,000 is never counted
 * twice in settlement or invoice totals.
 */
function nunnoPlanSales(?array $leadsCache = null, ?array $usersCache = null): array {
    if ($usersCache === null) nunnoApplyErijPlan();
    $from = earningsCountFrom();
    $users = $usersCache ?? earningsUsers();
    $leads = $leadsCache ?? earningsCompanyLeads(NUNNO_COMPANY_ID);

    $usersById = [];
    foreach ($users as $u) {
        if (!empty($u['id'])) $usersById[(string) $u['id']] = $u;
    }

    $activationLeads = [];
    foreach ($leads as $l) {
        if (earningsLeadState($l) !== 'paid' || earningsSaleTime($l) < $from) continue;
        $ownerId = earningsSaleOwner($l);
        $ownerUser = $ownerId !== '' ? ($usersById[$ownerId] ?? null) : null;
        if (!earningsIsNunnoActivation($l, $ownerUser)) continue;

        $emails = [];
        foreach ([$l['paidDetails']['email'] ?? '', $l['email'] ?? ''] as $em) {
            $em = earningsNormEmail((string) $em);
            if ($em !== '') $emails[$em] = true;
        }
        $trx = strtolower((string) preg_replace('/\s+/', '', (string) ($l['paidDetails']['trxId'] ?? '')));
        if (str_starts_with($trx, 'email:')) {
            $em = earningsNormEmail(substr($trx, 6));
            if ($em !== '') $emails[$em] = true;
        }
        $activationLeads[] = [
            'owner_id' => $ownerId,
            'phone9'   => earningsPhoneLast9((string) ($l['phone'] ?? '')),
            'emails'   => $emails,
            'trx'      => $trx,
            'name'     => earningsNormName((string) ($l['name'] ?? '')),
            'used'     => false,
        ];
    }

    $list = [];
    foreach ($users as $u) {
        if ((($u['company_id'] ?? NUNNO_COMPANY_ID) ?: NUNNO_COMPANY_ID) !== NUNNO_COMPANY_ID || empty($u['nunno_plan_paid'])) continue;
        $t = strtotime((string) ($u['nunno_plan_paid_at'] ?? '')) ?: 0;
        if ($t && $t < $from) continue;

        $uid = (string) ($u['id'] ?? '');
        $uPhone9 = earningsPhoneLast9((string) ($u['phone'] ?? ''));
        $uEmails = [];
        foreach ([$u['nunno_plan_email'] ?? '', $u['email'] ?? ''] as $em) {
            $em = earningsNormEmail((string) $em);
            if ($em !== '') $uEmails[$em] = true;
        }
        $uTrx = strtolower((string) preg_replace('/\s+/', '', (string) ($u['nunno_plan_trx'] ?? '')));
        if (str_starts_with($uTrx, 'email:')) {
            $em = earningsNormEmail(substr($uTrx, 6));
            if ($em !== '') $uEmails[$em] = true;
        }
        $uName = earningsNormName((string) ($u['name'] ?? ''));
        $uSponsor = (string) ($u['joined_via_tl'] ?? ($u['tl_id'] ?? ''));

        $matchedIdx = null;
        foreach ($activationLeads as $idx => $al) {
            if ($al['used'] || ($uid !== '' && $al['owner_id'] === $uid)) continue;
            if ($uPhone9 !== '' && $uPhone9 === $al['phone9']) { $matchedIdx = $idx; break; }
            if ($uEmails && $al['emails'] && array_intersect_key($uEmails, $al['emails'])) { $matchedIdx = $idx; break; }
            if ($uTrx !== '' && strlen($uTrx) >= 4 && !str_starts_with($uTrx, 'email:') && $uTrx === $al['trx']) { $matchedIdx = $idx; break; }
        }
        if ($matchedIdx === null && $uName !== '') {
            foreach ($activationLeads as $idx => $al) {
                if ($al['used'] || ($uid !== '' && $al['owner_id'] === $uid) || $al['name'] === '') continue;
                if ($al['name'] === $uName && ($uSponsor === $al['owner_id'] || str_contains($uName, ' '))) {
                    $matchedIdx = $idx;
                    break;
                }
                if ($uSponsor !== '' && $uSponsor === $al['owner_id'] && strlen($al['name']) >= 3
                    && (str_contains($uName, $al['name']) || str_contains($al['name'], $uName))) {
                    $matchedIdx = $idx;
                    break;
                }
            }
        }
        if ($matchedIdx !== null) {
            $activationLeads[$matchedIdx]['used'] = true;
            continue;
        }

        $list[] = ['user_id' => $uid, 'name' => $u['name'] ?? '', 'amount' => NUNNO_TL_PLAN_PRICE, 'paid_at' => $u['nunno_plan_paid_at'] ?? null];
    }
    return ['count' => count($list), 'amount' => count($list) * NUNNO_TL_PLAN_PRICE, 'list' => $list];
}

/**
 * Owner, 2026-11-24: "Erij actually bought the Nunno x Chatly plan worth Rs. 1,000; don't lock her
 * chats". Her account was deleted and re-created, so she is found by name, once: plan verified and
 * paid (Rs. 1,000 of Nunno revenue), Super Lead as she asked. Returns true when it changed something.
 */
function nunnoApplyErijPlan(): bool {
    $flag = __DIR__ . '/../data/erij_plan_applied.json';
    if (is_file($flag)) return false;
    require_once __DIR__ . '/quota.php';
    $done = chatlyUsersUpdate(__DIR__ . '/../data/users.json', function (array &$all) {
        foreach ($all as &$x) {
            if ((($x['company_id'] ?? NUNNO_COMPANY_ID) ?: NUNNO_COMPANY_ID) !== NUNNO_COMPANY_ID) continue;
            if (stripos((string) ($x['name'] ?? ''), 'erij') === false) continue;
            if (!in_array(strtolower((string) ($x['role'] ?? '')), ['member', 'lead'], true)) continue;
            $x['has_nunno_plan'] = true; $x['nunno_plan_status'] = 'active'; $x['plan_locked'] = false;
            $x['nunno_plan_activated_at'] = $x['nunno_plan_activated_at'] ?? date('Y-m-d H:i:s');
            $x['nunno_plan_success_seen'] = true;
            $x['nunno_plan_paid'] = true; $x['nunno_plan_paid_at'] = $x['nunno_plan_paid_at'] ?? date('c');
            $x['super_lead'] = true; $x['super_lead_seeded'] = true;
            return (string) ($x['id'] ?? 'yes');
        }
        return false;   // she has not signed up again yet: try again later
    });
    if (!$done) return false;
    @file_put_contents($flag, json_encode(['user_id' => $done, 'at' => date('c')]));
    return true;
}

function earningsWithdrawals(): array {
    $from = earningsCountFrom();
    return array_values(array_filter(earningsReadJson(__DIR__ . '/../data/withdrawals.json'), function ($w) use ($from) {
        $t = is_array($w) ? strtotime((string) ($w['created_at'] ?? '')) : false;
        return $t === false || $t >= $from;   // no date: keep (cannot tell)
    }));
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
    // the testing period's sales do not count (see earningsCountFrom)
    $countFrom = earningsCountFrom();
    $paid = array_values(array_filter($paid, fn($l) => earningsSaleTime($l) >= $countFrom));
    usort($paid, fn($a, $b) => earningsSaleTime($a) <=> earningsSaleTime($b));

    $running = 0.0;      // Nunno: sales counted toward the TM's basic (breakeven)
    $totalSales = 0.0;
    // A Nunno TM's basic / breakeven: 100% of sales up to it (default Rs. 10,000; the admin can set
    // it per person, e.g. Rs. 100,000), then the plan commission on every sale after it.
    $fullPayLimit = $isNunno ? max(0, (int) ($user['nunno_full_pay_limit'] ?? NUNNO_FULL_PAY_LIMIT)) : 0;
    $salesCommission = 0.0;
    $activationCount = 0;
    $deals = [];
    foreach ($paid as $l) {
        // The Rs. 1,000 Nunno x Chatly plan always pays Rs. 500, whoever sold it (a TL's recruit,
        // a Super Lead, or an unofficial lead). A plain TL's inbox deals are that plan;
        // Super Leads and TMs earn normal Nunno commission on regular plans (1,700 / 5,000 / 15,000).
        $isActivation = $isNunno && earningsIsNunnoActivation($l, $user);
        $amount = $isActivation ? NUNNO_TL_PLAN_PRICE : earningsSaleAmount($l);
        $totalSales += $amount;
        $before = $running;
        if ($isActivation) {
            $activationCount++;
            $comm = NUNNO_TL_COMMISSION;
            $rule = 'Nunno x Chatly plan activated: Rs. ' . number_format(NUNNO_TL_COMMISSION);
        } elseif ($isNunno) {
            $running += $amount;
            // The first Rs. 10,000 of sales is the TM's in full. The plan that crosses the line
            // pays only up to 10,000 (6 x 1,700 = 10,200 -> 10,000; one 15,000 plan -> 10,000);
            // every plan after that pays its plan commission.
            $fullPart = max(0, min($amount, $fullPayLimit - $before));
            $comm = $before >= $fullPayLimit ? nunnoPlanCommission($amount) : $fullPart;
            $lim = 'Rs. ' . number_format($fullPayLimit);
            $rule = $before >= $fullPayLimit ? 'Plan commission'
                : ($fullPart < $amount ? "Reached {$lim} (capped)" : "First {$lim} (100%)");
        } else {
            $comm = earningsProductCommission($companyId, $l, $amount);
            $rule = 'Product commission';
        }
        $salesCommission += $comm;
        $deals[] = [
            'lead_id' => $l['id'] ?? '', 'name' => $l['name'] ?? '', 'phone' => $l['display'] ?? ($l['phone'] ?? ''),
            'plan' => $l['paidDetails']['plan'] ?? ($l['interest'] ?? ''), 'amount' => $amount,
            'commission' => $comm, 'rule' => $rule . (!empty($l['unofficial']) ? ' · unofficial lead' : ''),
            'unofficial' => !empty($l['unofficial']), 'closed_at' => earningsSaleTime($l) ?: null,
        ];
    }

    // Nunno TLs are paid per verified Nunno x Chatly plan (above). The old tl_commission_balance was
    // credited automatically during testing and is not real money, so Nunno ignores it.
    $tlBounty = $isNunno ? 0.0 : (float) ($user['tl_commission_balance'] ?? 0);
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
        'super_lead' => !empty($user['super_lead']),
        'company_id' => $companyId,
        'scheme' => $isNunno ? 'nunno_first_10k' : 'product_commission',
        'total_sales' => $totalSales,
        'deals_count' => count($paid),
        'activation_count' => $activationCount,
        'in_review_count' => count($inReview),
        'in_review_amount' => array_sum(array_map('earningsSaleAmount', $inReview)),
        'full_pay_progress' => $isNunno ? min($running, $fullPayLimit) : null,
        'full_pay_limit' => $isNunno ? $fullPayLimit : null,
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
function computeCompanyEarnings(string $companyId, ?array $leadsCache = null, ?array $usersCache = null): array {
    if ($companyId === NUNNO_COMPANY_ID && $usersCache === null) nunnoApplyErijPlan();
    $leads = $leadsCache ?? earningsCompanyLeads($companyId);
    $users = $usersCache ?? earningsUsers();
    $wds = earningsWithdrawals();
    $rows = [];
    foreach ($users as $u) {
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
