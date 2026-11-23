<?php
/**
 * Auto Data: campaign numbers come from the Chatly number pool in Supabase instead of a
 * list the admin uploads.
 *
 * The pool (supabase/schema.sql) is one shuffled list of ~12.6 million Pakistani mobile
 * numbers. Every company has its own position in it, so each company gets numbers it has
 * never been given before, and a company can go through the whole list.
 *
 * Server setup: data/supabase.php (not in Git) returns
 *     ['url' => 'https://<project>.supabase.co', 'key' => '<secret / service_role key>']
 * or set the SUPABASE_URL and SUPABASE_SECRET_KEY environment variables.
 *
 * HTTP: GET api/auto_data.php?action=status  (company admins) -> numbers left for the company.
 * start_campaign in whatsapp_bridge.php uses chatlyAutoDataAudience().
 */

const CHATLY_AUTO_DATA_MAX = 50000;
// The super admin's Ultimate Campaign: its own position in the pool, no company limits.
if (!defined('CHATLY_ULTIMATE_ID')) define('CHATLY_ULTIMATE_ID', 'CHATLY-HQ');

require_once __DIR__ . '/supabase.php';

/** A company's record from data/companies.json, or null. */
function chatlyAutoDataCompany(string $companyId): ?array {
    $list = json_decode((string) @file_get_contents(__DIR__ . '/../data/companies.json'), true);
    foreach ((is_array($list) ? $list : []) as $c) if (($c['id'] ?? '') === $companyId) return $c;
    return null;
}

/** The super admin's Auto Data limits for a company: ['daily' => n, 'total' => n], 0 = unlimited. */
function chatlyAutoDataLimits(?array $company): array {
    return ['daily' => max(0, (int) ($company['auto_data_daily_limit'] ?? 0)), 'total' => max(0, (int) ($company['auto_data_total_limit'] ?? 0))];
}

/** total / used / remaining for a company, its limits, and how many it may take right now. */
function chatlyAutoDataStatus(string $companyId): array {
    $r = chatlySupabaseRpc('chatly_pool_status', ['p_company' => $companyId], 10);
    if (!$r['ok']) return ['success' => false, 'setup' => $r['setup'] ?? true,
        'message' => ($r['setup'] ?? true) ? $r['error'] : 'Auto Data is not set up on the server yet (Supabase is not connected).'];
    $d = is_array($r['data']) ? $r['data'] : [];
    if (empty($d['total'])) return ['success' => false, 'message' => 'The number pool is empty. The numbers have not been uploaded yet.'];
    $lim = chatlyAutoDataLimits(chatlyAutoDataCompany($companyId));
    $today = (int) ($d['today'] ?? 0);
    $claimed = (int) ($d['claimed_total'] ?? 0);
    $remaining = (int) ($d['remaining'] ?? 0);
    $allowed = $remaining;
    if ($lim['daily'] > 0) $allowed = min($allowed, max(0, $lim['daily'] - $today));
    if ($lim['total'] > 0) $allowed = min($allowed, max(0, $lim['total'] - $claimed));
    return ['success' => true, 'total' => (int) $d['total'], 'used' => (int) ($d['used'] ?? 0), 'remaining' => $remaining,
        'today' => $today, 'claimed_total' => $claimed, 'daily_limit' => $lim['daily'], 'total_limit' => $lim['total'],
        'allowed_now' => $allowed];
}

/** Takes the next $count numbers for a company. ['ok', 'phones' => [...], 'from', 'to'] */
function chatlyAutoDataClaim(string $companyId, int $count): array {
    $count = max(1, min(CHATLY_AUTO_DATA_MAX, $count));
    $r = chatlySupabaseRpc('chatly_claim_numbers', ['p_company' => $companyId, 'p_count' => $count], 40);
    if (!$r['ok']) return ['ok' => false, 'error' => $r['error']];
    $d = is_array($r['data']) ? $r['data'] : [];
    $phones = array_values(array_filter(array_map('strval', is_array($d['phones'] ?? null) ? $d['phones'] : []), fn($p) => preg_match('/^923\d{9}$/', $p)));
    return ['ok' => true, 'phones' => $phones, 'from' => (int) ($d['from'] ?? 0), 'to' => (int) ($d['to'] ?? 0), 'total' => (int) ($d['total'] ?? 0)];
}

/** Gives a claim back (only if nothing was claimed after it), e.g. when the campaign did not start. */
function chatlyAutoDataRelease(string $companyId, int $from, int $to): void {
    if ($to > $from) chatlySupabaseRpc('chatly_release_numbers', ['p_company' => $companyId, 'p_from' => $from, 'p_to' => $to], 10);
}

/** Last-9-digit keys of a company's existing leads (to skip numbers that already have a chat). */
function chatlyAutoDataLeadKeys(string $companyId): array {
    // The super admin's Ultimate Campaign sends from every company's closers, and each chat
    // lands in the sender's own company, so it skips numbers that have a chat in ANY company.
    if ($companyId === CHATLY_ULTIMATE_ID) {
        $all = [];
        $list = json_decode((string) @file_get_contents(__DIR__ . '/../data/companies.json'), true);
        foreach ((is_array($list) ? $list : []) as $c) {
            $id = preg_replace('/[^A-Za-z0-9_-]/', '', (string) ($c['id'] ?? ''));
            if ($id !== '' && $id !== CHATLY_ULTIMATE_ID) $all += chatlyAutoDataLeadKeys($id);
        }
        return $all;
    }
    if (chatlyLeadStoreOn()) {
        require_once __DIR__ . '/lead_store.php';
        return array_fill_keys(array_keys(chatlyLeadKeyIndex($companyId)), true);
    }
    $file = __DIR__ . '/../data/tenants/' . $companyId . '/leads.json';
    if (!is_file($file) && $companyId === 'COMP-001') $file = __DIR__ . '/../data/leads.json';
    $leads = is_file($file) ? json_decode((string) @file_get_contents($file), true) : [];
    $keys = [];
    foreach ((is_array($leads) ? $leads : []) as $l) {
        $d = preg_replace('/\D+/', '', (string) ($l['phone'] ?? ''));
        if (strlen($d) >= 9) $keys[substr($d, -9)] = true;
    }
    return $keys;
}

/**
 * The audience for an Auto Data campaign of $count numbers:
 *  1. numbers a previous Auto Data campaign of this company never got to (stopped early),
 *  2. then fresh numbers from the pool.
 * Numbers that already have a chat with the company are skipped. The list is shuffled so
 * every team WhatsApp gets a mix. Returns ['ok', 'audience', 'claim' => [from, to] | null].
 */
function chatlyAutoDataAudience(string $companyId, int $count, array $leftover = []): array {
    $count = max(1, min(CHATLY_AUTO_DATA_MAX, $count));
    $skip = chatlyAutoDataLeadKeys($companyId);
    $picked = [];
    $add = function (array $phones) use (&$picked, &$skip, $count) {
        foreach ($phones as $p) {
            if (count($picked) >= $count) break;
            $p = preg_replace('/\D+/', '', (string) $p);
            if (!preg_match('/^923\d{9}$/', $p)) continue;
            $k = substr($p, -9);
            if (isset($skip[$k])) continue;
            $skip[$k] = true;
            $picked[] = $p;
        }
    };
    $add($leftover);   // already given to this company earlier: does not count against its limits again

    // The super admin's daily / total limits for this company (0 = unlimited).
    $st = chatlyAutoDataStatus($companyId);
    $allowance = !empty($st['success']) ? (int) $st['allowed_now'] : PHP_INT_MAX;
    $taken = 0;
    $limitHit = false;

    $claim = null;
    for ($round = 0; $round < 3 && count($picked) < $count; $round++) {
        $want = min($count - count($picked), $allowance - $taken);
        if ($want <= 0) { $limitHit = true; break; }
        $c = chatlyAutoDataClaim($companyId, $want);
        if (!$c['ok']) {
            if ($claim) chatlyAutoDataRelease($companyId, $claim['from'], $claim['to']);
            return ['ok' => false, 'error' => $c['error']];
        }
        if (!$c['phones']) break;                     // the company has used the whole pool
        $taken += $c['to'] - $c['from'];
        $claim = $claim ? ['from' => $claim['from'], 'to' => $c['to']] : ['from' => $c['from'], 'to' => $c['to']];
        $add($c['phones']);
    }
    $limitText = '';
    if ($limitHit && !empty($st['success'])) {
        $dailyLeft = $st['daily_limit'] > 0 ? max(0, $st['daily_limit'] - $st['today']) : PHP_INT_MAX;
        $totalLeft = $st['total_limit'] > 0 ? max(0, $st['total_limit'] - $st['claimed_total']) : PHP_INT_MAX;
        $limitText = $dailyLeft <= $totalLeft
            ? "your daily Auto Data limit is {$st['daily_limit']} numbers (it resets at 12:00 AM)"
            : "your total Auto Data limit is {$st['total_limit']} numbers";
    }
    if (!$picked) {
        if ($claim) chatlyAutoDataRelease($companyId, $claim['from'], $claim['to']);
        return ['ok' => false, 'error' => $limitText !== '' ? "No more Auto Data numbers right now: {$limitText}. Contact Chatly to raise it."
            : 'No fresh numbers are left in the pool for your company.'];
    }
    shuffle($picked);
    $audience = array_map(fn($p) => ['phone' => $p, 'display' => '+' . $p, 'name' => '', 'city' => 'Pakistan', 'network' => 'Auto Data'], $picked);
    $note = ($limitText !== '' && count($picked) < $count) ? 'Only ' . count($picked) . " numbers: {$limitText}." : '';
    return ['ok' => true, 'audience' => $audience, 'claim' => $claim, 'note' => $note];
}

// ---- HTTP: status for the Campaign page ----
if (PHP_SAPI !== 'cli' && basename($_SERVER['SCRIPT_FILENAME'] ?? '') === 'auto_data.php') {
    header('Content-Type: application/json; charset=UTF-8');
    header('Cache-Control: no-store');
    require_once __DIR__ . '/session_user.php';
    $user = chatlyRequireUser();
    if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
    if (!chatlyIsCompanyAdmin($user) && !chatlyIsSuperAdmin($user)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Only company admins can use Auto Data.']);
        exit;
    }
    $action = $_GET['action'] ?? 'status';
    if ($action === 'status') {
        echo json_encode(chatlyAutoDataStatus(chatlyUserCompany($user)));
        exit;
    }
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Unknown action.']);
}
