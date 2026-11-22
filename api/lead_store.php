<?php
/**
 * Chats (leads + messages) in Supabase: the operations the PHP side needs.
 * See supabase/schema_leads.sql. Used only while chatlyLeadStoreOn() is true, except
 * chatlyLeadsAll(), which reads whichever store is active.
 */

require_once __DIR__ . '/supabase.php';

function chatlyStoreCompany(string $companyId): string {
    return preg_replace('/[^A-Za-z0-9_-]/', '', $companyId) ?: 'COMP-001';
}

function chatlyStoreDigits(string $p): string {
    $c = preg_replace('/\D+/', '', $p);
    if (str_starts_with($c, '0092')) $c = substr($c, 2);
    elseif (str_starts_with($c, '03') && strlen($c) === 11) $c = '92' . substr($c, 1);
    elseif (str_starts_with($c, '3') && strlen($c) === 10) $c = '92' . $c;
    return $c;
}

/** Calls a chat function; throws on failure so callers answer with a clear error. */
function chatlyStoreCall(string $fn, array $args, int $timeout = 25) {
    $r = chatlySupabaseRpc($fn, $args, $timeout);
    if (!$r['ok']) throw new RuntimeException($r['error'] ?? 'Supabase error');
    return $r['data'];
}

/** Sales Desk page: first load (since 0) or what changed after revision $since. */
function chatlyStoreLeadsPage(string $companyId, int $since, int $limit, int $msgs, bool $admin, string $owner, string $username): array {
    $d = chatlyStoreCall('chatly_leads_page', [
        'p_company' => chatlyStoreCompany($companyId), 'p_since' => max(0, $since), 'p_limit' => $limit,
        'p_msgs' => $msgs, 'p_admin' => $admin, 'p_owner' => $owner, 'p_username' => strtolower($username)], 40);
    return is_array($d) ? $d : ['rev' => $since, 'leads' => []];
}

function chatlyStoreFindLead(string $companyId, string $id, string $phone = ''): ?array {
    $d = chatlyStoreCall('chatly_find_lead', ['p_company' => chatlyStoreCompany($companyId), 'p_id' => $id, 'p_phone' => chatlyStoreDigits($phone)]);
    return is_array($d) ? $d : null;
}

/** Changes some fields of one lead. Returns the updated lead, or null when there is none. */
function chatlyStorePatchLead(string $companyId, string $id, string $phone, array $patch): ?array {
    $d = chatlyStoreCall('chatly_patch_lead', ['p_company' => chatlyStoreCompany($companyId), 'p_id' => $id,
        'p_phone' => chatlyStoreDigits($phone), 'p_patch' => (object) $patch]);
    return is_array($d) ? $d : null;
}

/** Saves whole leads (messages inside are stored as messages). */
function chatlyStoreSaveLeads(string $companyId, array $leads, string $mode = 'replace'): void {
    $company = chatlyStoreCompany($companyId);
    $items = [];
    foreach ($leads as &$l) {
        if (!is_array($l) || empty($l['id'])) continue;
        $phone = chatlyStoreDigits((string) ($l['phone'] ?? ''));
        foreach ((is_array($l['messages'] ?? null) ? $l['messages'] : []) as $m) {
            if (is_array($m)) $items[] = ['phone' => $phone, 'msg' => chatlyStoreMessageWithKey($m, $phone), 'touch' => false];
        }
        unset($l['messages'], $l['messageCount'], $l['summary']);
    }
    unset($l);
    if ($leads) chatlyStoreCall('chatly_upsert_leads', ['p_company' => $company, 'p_leads' => array_values($leads), 'p_mode' => $mode], 40);
    if ($items) chatlyStoreAddMessages($companyId, $items);
}

/** A message needs a stable key: WhatsApp id, else app id, else a hash of its content. */
function chatlyStoreMessageWithKey(array $m, string $phone): array {
    if (empty($m['waMessageId']) && empty($m['id'])) {
        $m['id'] = 'h_' . substr(sha1($phone . '|' . ($m['timestamp'] ?? '') . '|' . ($m['time'] ?? '') . '|' . ($m['sender'] ?? '') . '|' . ($m['text'] ?? '')), 0, 20);
    }
    return $m;
}

/** New messages: [['phone' => …, 'msg' => […], 'touch' => bool], …]. Returns how many were new. */
function chatlyStoreAddMessages(string $companyId, array $items): int {
    $n = 0;
    foreach (array_chunk($items, 500) as $chunk) {
        $n += (int) chatlyStoreCall('chatly_add_messages', ['p_company' => chatlyStoreCompany($companyId), 'p_items' => $chunk], 40);
    }
    return $n;
}

/** One message's tick / error by WhatsApp id. */
function chatlyStoreSetStatus(string $companyId, string $key, string $status, ?string $error = null): void {
    chatlyStoreCall('chatly_set_statuses', ['p_items' => [['company' => chatlyStoreCompany($companyId), 'key' => $key, 'status' => $status, 'error' => $error]]]);
}

function chatlyStoreMessages(string $companyId, string $phone): array {
    $d = chatlyStoreCall('chatly_lead_messages', ['p_company' => chatlyStoreCompany($companyId), 'p_phone' => chatlyStoreDigits($phone)], 30);
    return is_array($d) ? $d : [];
}

function chatlyStoreDeleteLeads(string $companyId, array $ids, array $phones): int {
    return (int) chatlyStoreCall('chatly_delete_leads', ['p_company' => chatlyStoreCompany($companyId),
        'p_ids' => array_values(array_map('strval', $ids)), 'p_phones' => array_values(array_map('strval', $phones))]);
}

/**
 * phone_key (last 9 digits) => [ownerId, ownerUsername] for every lead of a company.
 * Kept in a small temp file and brought up to date with only the changes, at most every
 * 4 seconds, so the 2-second chat feed never waits on the database.
 */
function chatlyLeadKeyIndex(string $companyId): array {
    $company = chatlyStoreCompany($companyId);
    $cfg = chatlySupabaseConfig();
    $file = sys_get_temp_dir() . '/chatly_leadkeys_' . md5(($cfg['url'] ?? '') . '|' . $company) . '.json';
    $cache = is_file($file) ? json_decode((string) @file_get_contents($file), true) : null;
    if (!is_array($cache) || !isset($cache['keys']) || !is_array($cache['keys'])) $cache = ['rev' => 0, 'at' => 0, 'keys' => []];
    if (time() - (int) $cache['at'] < 4) return $cache['keys'];
    $r = chatlySupabaseRpc('chatly_lead_keys', ['p_company' => $company, 'p_since' => (int) $cache['rev']], 20);
    if (!$r['ok'] || !is_array($r['data'])) return $cache['keys'];   // database slow: use what we have
    if ((int) $cache['rev'] === 0) $cache['keys'] = [];
    foreach ((array) ($r['data']['rows'] ?? []) as $row) {
        [$k, $owner, $user, $deleted] = array_pad((array) $row, 4, null);
        if ($k === null || $k === '') continue;
        if ($deleted) unset($cache['keys'][$k]); else $cache['keys'][$k] = [(string) $owner, (string) $user];
    }
    $cache['rev'] = (int) ($r['data']['rev'] ?? $cache['rev']);
    $cache['at'] = time();
    $tmp = $file . '.' . getmypid() . '.tmp';
    if (@file_put_contents($tmp, json_encode($cache)) !== false) @rename($tmp, $file);
    return $cache['keys'];
}

/** Every lead of a company from whichever store is active (stats and admin screens). */
function chatlyLeadsAll(string $companyId, bool $withMessages = false): array {
    if (!chatlyLeadStoreOn()) {
        require_once __DIR__ . '/tenant_manager.php';
        $file = getTenantLeadsFile($companyId);
        $leads = json_decode((string) @file_get_contents($file), true);
        return is_array($leads) ? $leads : [];
    }
    $out = [];
    $page = $withMessages ? 1000 : 5000;
    for ($offset = 0; $offset < 2000000; $offset += $page) {
        $r = chatlySupabaseRpc('chatly_leads_dump', ['p_company' => chatlyStoreCompany($companyId), 'p_offset' => $offset,
            'p_limit' => $page, 'p_with_messages' => $withMessages], 60);
        if (!$r['ok'] || !is_array($r['data'])) break;
        foreach ($r['data'] as $l) $out[] = $l;
        if (count($r['data']) < $page) break;
    }
    return $out;
}
