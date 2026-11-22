<?php
/**
 * Supabase connection for the PHP side (number pool and chats).
 *
 * Server setup: data/supabase.php (not in Git) returns
 *     ['url' => 'https://<project>.supabase.co', 'key' => '<secret / service_role key>']
 * or set SUPABASE_URL and SUPABASE_SECRET_KEY.
 *
 * Chats (leads + messages) are stored in Supabase only while the switch file
 * data/leads_store_supabase.on exists; without it Chatly keeps using the leads.json files.
 */

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

/** True when chats are stored in Supabase (switch file present and Supabase configured). */
function chatlyLeadStoreOn(): bool {
    static $on = null;
    if ($on === null) $on = is_file(__DIR__ . '/../data/leads_store_supabase.on') && chatlySupabaseConfig() !== null;
    return $on;
}

/** Calls a Postgres function through the Supabase REST API. ['ok' => bool, 'data' | 'error'] */
function chatlySupabaseRpc(string $fn, array $args, int $timeout = 25): array {
    $cfg = chatlySupabaseConfig();
    if (!$cfg) return ['ok' => false, 'setup' => false, 'error' => 'Supabase is not connected on the server.'];
    if (!function_exists('curl_init')) return ['ok' => false, 'error' => 'PHP cURL is disabled on this server.'];
    static $ch = null;   // one connection reused for every call in this request (keep-alive)
    if ($ch === null) $ch = curl_init();
    $headers = ['apikey: ' . $cfg['key'], 'Content-Type: application/json', 'Accept: application/json'];
    // New-style secret keys (sb_secret_…) go in "apikey" only; legacy service_role keys are JWTs.
    if (!str_starts_with($cfg['key'], 'sb_')) $headers[] = 'Authorization: Bearer ' . $cfg['key'];
    curl_setopt_array($ch, [
        CURLOPT_URL            => $cfg['url'] . '/rest/v1/rpc/' . rawurlencode($fn),
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($args, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_CONNECTTIMEOUT => 6,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_ENCODING       => '',          // accept gzip: big lead lists travel compressed
    ]);
    $raw = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    if ($raw === false) return ['ok' => false, 'error' => 'Could not reach Supabase (' . $err . ').'];
    $data = json_decode((string) $raw, true);
    if ($code < 200 || $code >= 300) {
        $msg = is_array($data) ? ($data['message'] ?? $data['hint'] ?? ('HTTP ' . $code)) : ('HTTP ' . $code);
        return ['ok' => false, 'error' => 'Supabase: ' . $msg];
    }
    return ['ok' => true, 'data' => $data];
}
