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

function chatlySupabaseConfig(): ?array {
    static $cfg = false;
    if ($cfg !== false) return $cfg;
    $url = (string) (getenv('SUPABASE_URL') ?: '');
    $key = (string) (getenv('SUPABASE_SECRET_KEY') ?: (getenv('SUPABASE_SERVICE_ROLE_KEY') ?: ''));
    $file = __DIR__ . '/../data/supabase.php';
    if (($url === '' || $key === '') && is_readable($file)) {
        $c = require $file;
        if (is_array($c)) {
            if ($url === '') $url = (string) ($c['url'] ?? '');
            if ($key === '') $key = (string) ($c['key'] ?? '');
        }
    }
    $url = rtrim(trim($url), '/');
    $key = trim($key);
    $cfg = (preg_match('#^https://[a-z0-9-]+\.supabase\.co$#i', $url) && $key !== '') ? ['url' => $url, 'key' => $key] : null;
    return $cfg;
}

/** Calls a Postgres function through the Supabase REST API. */
function chatlySupabaseRpc(string $fn, array $args, int $timeout = 25): array {
    $cfg = chatlySupabaseConfig();
    if (!$cfg) return ['ok' => false, 'setup' => false, 'error' => 'Auto Data is not set up on the server yet (Supabase is not connected).'];
    if (!function_exists('curl_init')) return ['ok' => false, 'error' => 'PHP cURL is disabled on this server.'];
    $headers = ['apikey: ' . $cfg['key'], 'Content-Type: application/json', 'Accept: application/json'];
    // New-style secret keys (sb_secret_…) go in "apikey" only; legacy service_role keys are JWTs.
    if (!str_starts_with($cfg['key'], 'sb_')) $headers[] = 'Authorization: Bearer ' . $cfg['key'];
    $ch = curl_init($cfg['url'] . '/rest/v1/rpc/' . rawurlencode($fn));
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($args),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_CONNECTTIMEOUT => 6,
        CURLOPT_TIMEOUT        => $timeout,
    ]);
    $raw = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    if ($raw === false) return ['ok' => false, 'error' => 'Could not reach the number pool (' . $err . ').'];
    $data = json_decode((string) $raw, true);
    if ($code < 200 || $code >= 300) {
        $msg = is_array($data) ? ($data['message'] ?? $data['hint'] ?? ('HTTP ' . $code)) : ('HTTP ' . $code);
        return ['ok' => false, 'error' => 'Number pool error: ' . $msg];
    }
    return ['ok' => true, 'data' => $data];
}

/** total / used / remaining for a company. */
function chatlyAutoDataStatus(string $companyId): array {
    $r = chatlySupabaseRpc('chatly_pool_status', ['p_company' => $companyId], 10);
    if (!$r['ok']) return ['success' => false, 'setup' => $r['setup'] ?? true, 'message' => $r['error']];
    $d = is_array($r['data']) ? $r['data'] : [];
    if (empty($d['total'])) return ['success' => false, 'message' => 'The number pool is empty. The numbers have not been uploaded yet.'];
    return ['success' => true, 'total' => (int) $d['total'], 'used' => (int) ($d['used'] ?? 0), 'remaining' => (int) ($d['remaining'] ?? 0)];
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
    $add($leftover);

    $claim = null;
    for ($round = 0; $round < 3 && count($picked) < $count; $round++) {
        $c = chatlyAutoDataClaim($companyId, $count - count($picked));
        if (!$c['ok']) {
            if ($claim) chatlyAutoDataRelease($companyId, $claim['from'], $claim['to']);
            return ['ok' => false, 'error' => $c['error']];
        }
        if (!$c['phones']) break;                     // the company has used the whole pool
        $claim = $claim ? ['from' => $claim['from'], 'to' => $c['to']] : ['from' => $c['from'], 'to' => $c['to']];
        $add($c['phones']);
    }
    if (!$picked) {
        if ($claim) chatlyAutoDataRelease($companyId, $claim['from'], $claim['to']);
        return ['ok' => false, 'error' => 'No fresh numbers are left in the pool for your company.'];
    }
    shuffle($picked);
    $audience = array_map(fn($p) => ['phone' => $p, 'display' => '+' . $p, 'name' => '', 'city' => 'Pakistan', 'network' => 'Auto Data'], $picked);
    return ['ok' => true, 'audience' => $audience, 'claim' => $claim];
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
