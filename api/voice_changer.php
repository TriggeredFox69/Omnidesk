<?php
/**
 * Voice changer (ElevenLabs Voice Changer / speech-to-speech) for chosen companies' Team Inbox.
 * A voice note recorded in the Sales Desk is converted to the company's persona voice (e.g. "Alishba")
 * before it is sent: same words, timing and emotion, different voice.
 *
 * Settings live in data/voice_changer.json (not in Git). The ElevenLabs API key is never sent to a browser.
 *   { "api_key": "...", "opus_ok": true|false|null,
 *     "companies": { "COMP-005": { "enabled": true, "voice_id": "...", "persona": "Alishba",
 *                                  "stability": 0.45, "similarity_boost": 0.8, "style": 0.35 } } }
 *
 * Actions (?action=):
 *   status         GET   signed-in user: is the voice changer on for my company? (+ persona name)
 *   convert        POST  {audio: "data:audio/...;base64,..."} -> {audio: data URL, mimetype, needs_reencode}
 *   settings       GET   super admin: all settings (key masked) + companies list
 *   save_settings  POST  super admin: {api_key?, clear_key?, company_id, enabled, voice_id, persona, stability, similarity_boost, style}
 *   check_voice    POST  super admin: {voice_id} -> voice name from ElevenLabs (checks key + voice id)
 */

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/session_user.php';

const VC_FILE = __DIR__ . '/../data/voice_changer.json';
const VC_API = 'https://api.elevenlabs.io/v1';
const VC_MODEL = 'eleven_multilingual_sts_v2';
const VC_MAX_BYTES = 8 * 1024 * 1024;

function vcOut(array $d, int $code = 200): void { http_response_code($code); echo json_encode($d, JSON_UNESCAPED_SLASHES); exit; }
function vcLoad(): array {
    $d = json_decode((string) @file_get_contents(VC_FILE), true);
    if (!is_array($d)) $d = [];
    $d['companies'] = is_array($d['companies'] ?? null) ? $d['companies'] : [];
    return $d;
}
function vcSave(array $d): void {
    $tmp = VC_FILE . '.tmp.' . uniqid('', true);
    if (@file_put_contents($tmp, json_encode($d, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) !== false) @rename($tmp, VC_FILE);
}
function vcCompanyConf(array $cfg, string $companyId): ?array {
    $c = $cfg['companies'][$companyId] ?? null;
    return (is_array($c) && !empty($c['enabled']) && !empty($c['voice_id']) && !empty($cfg['api_key'])) ? $c : null;
}
/** One ElevenLabs request. Returns [httpCode, body, contentType]. */
function vcHttp(string $method, string $url, string $key, $postFields = null, int $timeout = 60): array {
    $ch = curl_init($url);
    $headers = ['xi-api-key: ' . $key, 'Accept: */*'];
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => $timeout, CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_HTTPHEADER => $headers, CURLOPT_CUSTOMREQUEST => $method]);
    if ($postFields !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
    $body = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $type = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    $err = curl_error($ch);
    curl_close($ch);
    if ($body === false) return [0, $err ?: 'Could not reach ElevenLabs.', ''];
    return [$code, (string) $body, $type];
}
function vcErr(string $body, int $code): string {
    $j = json_decode($body, true);
    $msg = is_array($j) ? ($j['detail']['message'] ?? (is_string($j['detail'] ?? null) ? $j['detail'] : ($j['message'] ?? ''))) : '';
    if ($code === 401) return 'ElevenLabs rejected the API key. Check it in Super admin -> Voice changer.';
    if ($code === 402 || stripos((string) $msg, 'quota') !== false || stripos((string) $msg, 'credit') !== false) return 'ElevenLabs credits are used up for this month.';
    if ($code === 404) return 'ElevenLabs does not know this voice ID (or it is not added to your account).';
    return 'ElevenLabs error' . ($code ? " ($code)" : '') . ($msg ? ': ' . mb_substr((string) $msg, 0, 160) : '.');
}

$me = chatlySessionUser();
if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
if (!$me) vcOut(['success' => false, 'error' => 'Please sign in.'], 401);
$isSuper = chatlyIsSuperAdmin($me);
$myCompany = chatlyUserCompany($me);
$action = (string) ($_GET['action'] ?? 'status');
$in = json_decode((string) file_get_contents('php://input'), true);
if (!is_array($in)) $in = [];
if (in_array($action, ['convert', 'save_settings', 'check_voice'], true)) {
    $ctype = strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? ''));
    if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST' || strpos($ctype, 'application/json') === false) {
        vcOut(['success' => false, 'error' => 'Send this as a JSON POST.'], 405);
    }
}
$cfg = vcLoad();

if ($action === 'status') {
    $c = vcCompanyConf($cfg, $myCompany);
    vcOut(['success' => true, 'enabled' => (bool) $c, 'persona' => $c ? (string) ($c['persona'] ?? '') : '']);
}

if ($action === 'convert') {
    $c = vcCompanyConf($cfg, $myCompany);
    if (!$c) vcOut(['success' => false, 'error' => 'The voice changer is not switched on for your company.'], 403);
    if (!function_exists('curl_init')) vcOut(['success' => false, 'error' => 'PHP cURL is missing on the server.'], 500);
    if (!preg_match('#^data:(audio/[a-z0-9.+-]+)(?:;[^,]*)?;base64,(.+)$#is', (string) ($in['audio'] ?? ''), $m)) {
        vcOut(['success' => false, 'error' => 'No recording received.'], 400);
    }
    $bin = base64_decode($m[2], true);
    if ($bin === false || strlen($bin) < 500) vcOut(['success' => false, 'error' => 'The recording is empty.'], 400);
    if (strlen($bin) > VC_MAX_BYTES) vcOut(['success' => false, 'error' => 'The recording is too long (max about 3 minutes).'], 413);
    $ext = stripos($m[1], 'ogg') !== false ? 'ogg' : (stripos($m[1], 'mp4') !== false ? 'm4a' : 'webm');
    $tmp = tempnam(sys_get_temp_dir(), 'vc_') . '.' . $ext;
    file_put_contents($tmp, $bin);
    $settings = json_encode(['stability' => (float) ($c['stability'] ?? 0.45), 'similarity_boost' => (float) ($c['similarity_boost'] ?? 0.8),
        'style' => (float) ($c['style'] ?? 0.35), 'use_speaker_boost' => true]);
    // Ogg Opus is exactly what WhatsApp voice notes use, so it can go out as it is. If this account's
    // Opus output turns out not to be Ogg, MP3 is used from then on and the browser re-encodes it.
    $tryOpus = ($cfg['opus_ok'] ?? null) !== false;
    $format = $tryOpus ? 'opus_48000_64' : 'mp3_44100_128';
    $fields = ['audio' => new CURLFile($tmp, $m[1], 'voice.' . $ext), 'model_id' => VC_MODEL, 'voice_settings' => $settings, 'remove_background_noise' => 'true'];
    [$code, $body] = vcHttp('POST', VC_API . '/voice-changer/' . rawurlencode((string) $c['voice_id']) . '?output_format=' . $format, (string) $cfg['api_key'], $fields, 90);
    if ($code === 200 && $tryOpus && substr($body, 0, 4) !== 'OggS') {
        $cfg['opus_ok'] = false; vcSave($cfg);   // not Ogg: switch this account to MP3 and convert again
        $format = 'mp3_44100_128';
        [$code, $body] = vcHttp('POST', VC_API . '/voice-changer/' . rawurlencode((string) $c['voice_id']) . '?output_format=' . $format, (string) $cfg['api_key'], $fields, 90);
    } elseif ($code === 200 && $tryOpus && !isset($cfg['opus_ok'])) {
        $cfg['opus_ok'] = true; vcSave($cfg);
    }
    @unlink($tmp);
    if ($code !== 200 || strlen($body) < 200) vcOut(['success' => false, 'error' => vcErr($body, $code)], 502);
    $isOgg = substr($body, 0, 4) === 'OggS';
    $mime = $isOgg ? 'audio/ogg; codecs=opus' : 'audio/mpeg';
    vcOut(['success' => true, 'mimetype' => $mime, 'needs_reencode' => !$isOgg,
        'audio' => 'data:' . ($isOgg ? 'audio/ogg' : 'audio/mpeg') . ';base64,' . base64_encode($body)]);
}

// ---------------- super admin ----------------
if (!$isSuper) vcOut(['success' => false, 'error' => 'Only the super admin can change the voice changer.'], 403);

if ($action === 'settings') {
    $companies = [];
    foreach ((json_decode((string) @file_get_contents(__DIR__ . '/../data/companies.json'), true) ?: []) as $co) {
        if (!empty($co['id'])) $companies[] = ['id' => $co['id'], 'name' => $co['name'] ?? $co['id']];
    }
    $key = (string) ($cfg['api_key'] ?? '');
    vcOut(['success' => true, 'has_key' => $key !== '', 'key_hint' => $key !== '' ? '••••' . substr($key, -4) : '',
        'companies' => $companies, 'settings' => $cfg['companies']]);
}

if ($action === 'save_settings') {
    if (!empty($in['clear_key'])) unset($cfg['api_key'], $cfg['opus_ok']);
    $newKey = trim((string) ($in['api_key'] ?? ''));
    if ($newKey !== '') {
        if (!preg_match('/^[A-Za-z0-9_\-]{20,200}$/', $newKey)) vcOut(['success' => false, 'error' => 'That does not look like an ElevenLabs API key.'], 400);
        $cfg['api_key'] = $newKey; unset($cfg['opus_ok']);
    }
    $cid = preg_replace('/[^A-Za-z0-9_-]/', '', (string) ($in['company_id'] ?? ''));
    if ($cid !== '') {
        $voice = trim((string) ($in['voice_id'] ?? ''));
        if ($voice !== '' && !preg_match('/^[A-Za-z0-9]{10,40}$/', $voice)) vcOut(['success' => false, 'error' => 'That voice ID does not look right (letters and numbers only).'], 400);
        $num = fn($k, $d) => max(0.0, min(1.0, (float) ($in[$k] ?? $d)));
        $cfg['companies'][$cid] = ['enabled' => !empty($in['enabled']) && $voice !== '', 'voice_id' => $voice,
            'persona' => mb_substr(trim((string) ($in['persona'] ?? '')), 0, 40),
            'stability' => $num('stability', 0.45), 'similarity_boost' => $num('similarity_boost', 0.8), 'style' => $num('style', 0.35),
            'updated_at' => date('c')];
    }
    vcSave($cfg);
    vcOut(['success' => true, 'message' => 'Voice changer settings saved.']);
}

if ($action === 'check_voice') {
    if (empty($cfg['api_key'])) vcOut(['success' => false, 'error' => 'Save your ElevenLabs API key first.'], 400);
    $voice = trim((string) ($in['voice_id'] ?? ''));
    if (!preg_match('/^[A-Za-z0-9]{10,40}$/', $voice)) vcOut(['success' => false, 'error' => 'Enter a voice ID.'], 400);
    [$code, $body] = vcHttp('GET', VC_API . '/voices/' . rawurlencode($voice), (string) $cfg['api_key'], null, 20);
    if ($code !== 200) vcOut(['success' => false, 'error' => vcErr($body, $code)], 502);
    $j = json_decode($body, true) ?: [];
    $labels = is_array($j['labels'] ?? null) ? implode(', ', array_filter(array_map('strval', $j['labels']))) : '';
    vcOut(['success' => true, 'name' => (string) ($j['name'] ?? ''), 'labels' => $labels]);
}

vcOut(['success' => false, 'error' => 'Unknown action.'], 400);
