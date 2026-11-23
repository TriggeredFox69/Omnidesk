<?php
/**
 * Ultimate Campaign (super admin only): one campaign that sends from EVERY company's linked
 * closer WhatsApps at once.
 *
 *  - It runs in the bridge as its own campaign, "CHATLY-HQ" (state file campaign_state_CHATLY-HQ.json),
 *    so it never touches a company's own campaign.
 *  - Each chat it starts lands in the sending closer's OWN company inbox, owned by that closer
 *    (bridge/campaign.js laneSender().companyId), so replies show up where they already work.
 *  - A WhatsApp already sending its company's campaign is left out (the bridge refuses to put
 *    one WhatsApp in two campaigns), and the usual protections apply: stopped closers, daily
 *    limits, the 24 h pause when WhatsApp refuses a number's messages.
 *  - Auto Data uses its own position in the number pool (no company's limits are used up) and
 *    skips numbers that already have a chat in any company.
 *
 * Actions (?action=...):
 *   senders           GET   every active TM / TL with their WhatsApp state
 *   auto_status       GET   Auto Data numbers left for the Ultimate Campaign
 *   status            GET   live campaign status
 *   start             POST  name, message, variation, media, delay, auto_data, auto_count, audience[], sender_ids[]
 *   pause|resume|stop|clear  POST
 */

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/session_user.php';
require_once __DIR__ . '/whatsapp_bridge.php';
require_once __DIR__ . '/quota.php';
require_once __DIR__ . '/auto_data.php';

function ucFail(int $code, string $msg): void {
    http_response_code($code);
    echo json_encode(['success' => false, 'message' => $msg]);
    exit;
}

$me = chatlySessionUser();
if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
if (!$me) ucFail(401, 'Please sign in.');
if (!chatlyIsSuperAdmin($me)) ucFail(403, 'Only the super admin can run the Ultimate Campaign.');

$raw = (string) file_get_contents('php://input');
$in = json_decode($raw, true);
if (!is_array($in)) $in = [];
$action = (string) ($_GET['action'] ?? ($in['action'] ?? ''));
$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
if (in_array($action, ['start', 'pause', 'resume', 'stop', 'clear'], true)) {
    // JSON POST only: a cross-site HTML form cannot send application/json.
    $ctype = strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? ($_SERVER['HTTP_CONTENT_TYPE'] ?? '')));
    if ($method !== 'POST' || strpos($ctype, 'application/json') === false) ucFail(405, 'Send this as a JSON POST.');
}

const UC_ID = CHATLY_ULTIMATE_ID;
const UC_NAME = 'Chatly';

/** company id => name */
function ucCompanyNames(): array {
    $out = [];
    $list = json_decode((string) @file_get_contents(__DIR__ . '/../data/companies.json'), true);
    foreach ((is_array($list) ? $list : []) as $c) if (!empty($c['id'])) $out[(string) $c['id']] = (string) ($c['name'] ?? $c['id']);
    return $out;
}

/** Every active TM / TL of every company, with their WhatsApp, limits and campaign state. */
function ucSenders(): array {
    $companies = ucCompanyNames();
    $users = chatlyUsersById();
    $devices = [];
    $dev = whatsappBridgeRequest('/devices');
    foreach (($dev['devices'] ?? []) as $d) {
        if (!empty($d['user_id'])) $devices[(string) $d['user_id']] = $d;
    }
    $busyRes = whatsappBridgeRequest('/campaign/busy');
    $busy = is_array($busyRes['busy'] ?? null) ? $busyRes['busy'] : [];
    $out = [];
    foreach ($users as $id => $u) {
        $role = strtolower((string) ($u['role'] ?? ''));
        if (!in_array($role, ['member', 'lead'], true)) continue;
        if (strtolower((string) ($u['status'] ?? 'active')) !== 'active') continue;
        $cid = chatlyUserCompany($u);
        if (!isset($companies[$cid])) continue;   // company deleted
        $d = $devices[$id] ?? null;
        $q = chatlyQuotaMetrics($u);
        $busyIn = (string) ($busy[$id] ?? '');
        $out[] = [
            'id' => (string) $id,
            'name' => (string) ($u['name'] ?? ($u['username'] ?? $id)),
            'username' => (string) ($u['username'] ?? ''),
            'role' => $role === 'lead' ? 'TL' : 'TM',
            'company_id' => $cid,
            'company_name' => $companies[$cid],
            'connected' => !empty($d['is_connected']),
            'number' => preg_replace('/\D+/', '', (string) ($d['connected_number'] ?? '')),
            'state' => (string) ($d['state'] ?? 'not linked'),
            'stopped' => !empty($u['campaign_paused']),
            'plan_locked' => $cid === 'COMP-001' && $role === 'member' && empty($u['has_nunno_plan']),   // Nunno TMs only
            'sent_today' => $q['sent'],
            'limit' => $q['limit'],
            'limit_reached' => $q['reached'],
            // which campaign this WhatsApp is sending for right now ('' = free)
            'busy_in' => $busyIn === UC_ID ? 'ultimate' : ($busyIn !== '' ? ($companies[$busyIn] ?? $busyIn) : ''),
        ];
    }
    usort($out, fn($a, $b) => [$b['connected'], $a['company_name'], $a['name']] <=> [$a['connected'], $b['company_name'], $b['name']]);
    return $out;
}

/** Normalises one pasted / uploaded number to 92XXXXXXXXXX, or ''. */
function ucPhone($p): string {
    $d = preg_replace('/\D+/', '', (string) $p);
    if (str_starts_with($d, '0092')) $d = substr($d, 2);
    elseif (str_starts_with($d, '03') && strlen($d) === 11) $d = '92' . substr($d, 1);
    elseif (str_starts_with($d, '3') && strlen($d) === 10) $d = '92' . $d;
    return (strlen($d) >= 10 && strlen($d) <= 15) ? $d : '';
}

switch ($action) {

    case 'senders': {
        ensureBridgeCurrent();
        $list = ucSenders();
        $online = count(array_filter($list, fn($s) => $s['connected']));
        echo json_encode(['success' => true, 'senders' => $list, 'connected' => $online, 'total' => count($list),
            'resets_in' => chatlyQuotaSecondsToReset()]);
        exit;
    }

    case 'auto_status': {
        echo json_encode(chatlyAutoDataStatus(UC_ID));
        exit;
    }

    case 'status': {
        echo json_encode(whatsappBridgeRequest('/campaign/status?company_id=' . rawurlencode(UC_ID)));
        exit;
    }

    case 'pause':
    case 'resume':
    case 'stop':
    case 'clear': {
        $r = whatsappBridgeRequest('/campaign/' . $action, ['company_id' => UC_ID]);
        if (empty($r['success']) && empty($r['message'])) $r['message'] = 'The WhatsApp bridge did not answer. Try again.';
        echo json_encode($r);
        exit;
    }

    case 'start': {
        $check = whatsappBridgeRequest('/status');
        ensureBridgeCurrent($check);
        $message = trim((string) ($in['message'] ?? ''));
        $variation = in_array(($in['variation'] ?? 'text'), ['text', 'text_image', 'audio'], true) ? $in['variation'] : 'text';
        $media = is_array($in['media'] ?? null) ? $in['media'] : null;
        if ($variation === 'text') $media = null;
        if ($media) {
            $mime = strtolower(trim(explode(';', (string) ($media['mimetype'] ?? ''))[0]));
            $ok = $variation === 'text_image' ? str_starts_with($mime, 'image/') : str_starts_with($mime, 'audio/');
            if (!$ok || empty($media['data']) || strlen((string) $media['data']) > 14 * 1024 * 1024) ucFail(400, 'That file is not a supported image / voice note, or it is bigger than 10 MB.');
            $media = ['mimetype' => (string) $media['mimetype'], 'data' => (string) $media['data'],
                'filename' => basename((string) ($media['filename'] ?? 'file')), 'voice' => $variation === 'audio'];
        }
        if ($variation !== 'text' && !$media) ucFail(400, $variation === 'audio' ? 'Upload the voice note first.' : 'Upload the image first.');
        if ($message === '' && $variation !== 'audio') ucFail(400, 'Write the message first.');

        // Senders: only ids the super admin ticked, re-checked here (active TM / TL of an
        // existing company, WhatsApp connected right now). Company and name come from the server.
        $want = array_flip(array_map('strval', is_array($in['sender_ids'] ?? null) ? $in['sender_ids'] : []));
        $senders = [];
        foreach (ucSenders() as $s) {
            if (!isset($want[$s['id']]) || !$s['connected']) continue;
            $senders[] = [
                'id' => $s['id'], 'name' => $s['name'], 'username' => $s['username'],
                'role' => $s['role'] === 'TL' ? 'Team Leader (TL)' : 'TM Closer', 'roleType' => $s['role'] === 'TL' ? 'lead' : 'member',
                'company_id' => $s['company_id'], 'company_name' => $s['company_name'],
                'phone' => $s['number'], 'device_number' => $s['number'],
            ];
        }
        if (!$senders) ucFail(400, 'None of the ticked WhatsApps is connected right now. Tick at least one connected WhatsApp.');

        $payload = [
            'company_id' => UC_ID, 'company_name' => UC_NAME,
            'name' => mb_substr(trim((string) ($in['name'] ?? '')) ?: 'Ultimate Campaign', 0, 120),
            'message' => mb_substr($message, 0, 4000),
            'variation' => $variation, 'media' => $media,
            'delay' => max(30, min(900, (int) ($in['delay'] ?? 60))),   // 30 s - 15 min, like every campaign
            'senders' => $senders, 'closers' => $senders,
            'auto_data' => !empty($in['auto_data']),
        ];
        $payload['minDelay'] = $payload['maxDelay'] = $payload['delay'];

        $autoClaim = null;
        $autoNote = '';
        if ($payload['auto_data']) {
            $count = (int) ($in['auto_count'] ?? 0);
            if ($count < 1) ucFail(400, 'Enter how many messages to send.');
            $leftover = whatsappBridgeRequest('/campaign/leftover?company_id=' . rawurlencode(UC_ID));
            $built = chatlyAutoDataAudience(UC_ID, $count, is_array($leftover['phones'] ?? null) ? $leftover['phones'] : []);
            if (empty($built['ok'])) ucFail(400, (string) ($built['error'] ?? 'Auto Data could not pick numbers.'));
            $payload['audience'] = $built['audience'];
            $autoClaim = $built['claim'];
            $autoNote = (string) ($built['note'] ?? '');
        } else {
            $seen = [];
            $aud = [];
            foreach ((is_array($in['audience'] ?? null) ? $in['audience'] : []) as $c) {
                $p = ucPhone(is_array($c) ? ($c['phone'] ?? '') : $c);
                if ($p === '' || isset($seen[$p])) continue;
                $seen[$p] = true;
                $aud[] = ['phone' => $p, 'display' => '+' . $p, 'name' => is_array($c) ? mb_substr((string) ($c['name'] ?? ''), 0, 80) : '',
                    'city' => 'Pakistan', 'network' => 'WhatsApp'];
                if (count($aud) >= CHATLY_AUTO_DATA_MAX) break;
            }
            if (!$aud) ucFail(400, 'Add the numbers to message (paste them or upload a .txt file), or turn on Auto Data.');
            $payload['audience'] = $aud;
        }

        $r = whatsappBridgeRequest('/campaign/start', $payload);
        if (empty($r['success']) && $autoClaim) chatlyAutoDataRelease(UC_ID, $autoClaim['from'], $autoClaim['to']);
        if (!empty($r['success'])) {
            unset($r['allocations']);
            if ($autoNote !== '') $r['auto_data_note'] = $autoNote;
        } elseif (empty($r['message'])) {
            $r['message'] = 'The WhatsApp bridge did not accept the campaign. Check that the bridge is online.';
        }
        echo json_encode($r);
        exit;
    }

    default:
        ucFail(400, 'Unknown action.');
}
