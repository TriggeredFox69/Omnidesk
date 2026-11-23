<?php
/**
 * Email center: the composer + sender settings behind two UIs.
 *
 *   Nunno composer (admin.php, company COMP-001, "Email" tab)  -> profile "nunno"
 *     who:  a company admin of COMP-001, or the super admin
 *     to:   only COMP-001 Team Members / Team Leaders (resolved here from users.json)
 *   Chatly composer (sadmin.php, "Emails" section)             -> profile "chatly"
 *     who:  the super admin only
 *     to:   company owners (owner_email) and company admin accounts
 *
 * Actions (GET or JSON POST, ?action=...):
 *   settings      GET  profile                       -> masked sender settings (never the App Password)
 *   save_settings POST profile, sender_name, sender_email, reply_to, enabled, daily_cap, app_password, clear_password
 *   test          POST profile, to?                  -> test email to you (or "to")
 *   preview       POST profile, subject, message     -> branded HTML for an iframe preview
 *   logs          GET  profile                       -> recent sends of that profile
 *   send_nunno    POST audience (tm|tl|all|pick), user_ids[], subject, message, preset
 *   send_chatly   POST audience (all|pick), company_ids[], subject, message, preset
 *
 * Limits: at most 500 recipients per request (the UI sends chunks of 20), the profile's
 * daily cap (default 450, Gmail allows ~500/day), 120 send requests and 3 test emails
 * per user per window. Everything that goes into HTML is escaped by the mailer.
 */

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/session_user.php';
require_once __DIR__ . '/mailer.php';

function ecFail(int $code, string $msg, array $extra = []): void {
    http_response_code($code);
    echo json_encode(array_merge(['success' => false, 'error' => $msg], $extra));
    exit;
}

function ecOut(array $data): void {
    echo json_encode(array_merge(['success' => true], $data), JSON_UNESCAPED_UNICODE);
    exit;
}

$me = chatlySessionUser();
if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
if (!$me) ecFail(401, 'Please sign in.');
if (strtolower((string) ($me['status'] ?? 'active')) === 'suspended') ecFail(403, 'Account suspended.');

$myRole = chatlyRole($me);
$isSuper = $myRole === 'superadmin';
$isNunnoAdmin = in_array($myRole, ['admin', 'master admin'], true) && chatlyUserCompany($me) === 'COMP-001';
if (!$isSuper && !$isNunnoAdmin) ecFail(403, 'Only the Nunno company admin or the super admin can use email.');

$raw = (string) file_get_contents('php://input');
$in = json_decode($raw, true);
if (!is_array($in)) $in = [];
$action = (string) ($_GET['action'] ?? ($in['action'] ?? ''));
$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));

$writeActions = ['save_settings', 'test', 'preview', 'send_nunno', 'send_chatly'];
if (in_array($action, $writeActions, true)) {
    // JSON POST only: a cross-site HTML form cannot send application/json.
    $ctype = strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? ($_SERVER['HTTP_CONTENT_TYPE'] ?? '')));
    if ($method !== 'POST' || strpos($ctype, 'application/json') === false) ecFail(405, 'Send this as a JSON POST.');
}

function ecCanProfile(string $profile, bool $isSuper, bool $isNunnoAdmin): bool {
    if ($profile === 'nunno') return $isSuper || $isNunnoAdmin;
    if ($profile === 'chatly') return $isSuper;
    return false;
}

function ecProfileParam(array $in, bool $isSuper, bool $isNunnoAdmin): string {
    $p = strtolower(trim((string) ($_GET['profile'] ?? ($in['profile'] ?? ''))));
    if ($p === '') $p = $isSuper && !$isNunnoAdmin ? 'chatly' : 'nunno';
    if (!in_array($p, chatlyMailProfileNames(), true)) ecFail(400, 'Unknown sender profile.');
    if (!ecCanProfile($p, $isSuper, $isNunnoAdmin)) ecFail(403, 'You cannot use the "' . $p . '" sender.');
    return $p;
}

/** Light per-user rate limit: at most $max hits per $window seconds for $key. */
function ecRateOk(string $key, int $max, int $window): bool {
    $allowed = true;
    $now = time();
    chatlyMailJsonUpdate(__DIR__ . '/../data/email_rate.json', function (array &$all) use ($key, $max, $window, $now, &$allowed) {
        foreach ($all as $k => $hits) {
            $all[$k] = array_values(array_filter(is_array($hits) ? $hits : [], function ($t) use ($now) { return (int) $t > $now - 3600; }));
            if (!$all[$k]) unset($all[$k]);
        }
        $recent = array_filter($all[$key] ?? [], function ($t) use ($now, $window) { return (int) $t > $now - $window; });
        if (count($recent) >= $max) { $allowed = false; return; }
        $all[$key] = array_values(array_merge($all[$key] ?? [], [$now]));
    });
    return $allowed;
}

function ecMyEmail(array $me): string {
    $email = trim((string) ($me['email'] ?? ''));
    if (filter_var($email, FILTER_VALIDATE_EMAIL)) return $email;
    foreach (chatlyMailReadJson(__DIR__ . '/../data/users.json') as $u) {
        if (is_array($u) && ($u['id'] ?? '') === ($me['id'] ?? '')) return trim((string) ($u['email'] ?? ''));
    }
    return '';
}

function ecCleanSubject($s): string {
    $s = trim(preg_replace('/[\r\n\t]+/', ' ', (string) $s));
    return mb_substr($s, 0, 200);
}

function ecCleanMessage($s): string {
    $s = str_replace(["\r\n", "\r"], "\n", (string) $s);
    $s = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $s);
    return trim(mb_substr((string) $s, 0, 20000));
}

function ecFill(string $s, array $vars): string {
    $map = [];
    foreach ($vars as $k => $v) $map['{' . $k . '}'] = (string) $v;
    return strtr($s, $map);
}

/** Branded HTML of one composer email for one recipient. */
function ecBuildEmail(string $profile, string $subject, string $message, array $vars): array {
    $b = chatlyMailBrand($profile);
    $subj = ecFill($subject, $vars);
    $body = chatlyMailTextToHtml(ecFill($message, $vars), $b['link']);
    $opts = $profile === 'nunno'
        ? ['cta_text' => 'Open Chatly', 'cta_url' => CHATLY_MAIL_PORTAL_URL]
        : ['cta_text' => 'Open your admin portal', 'cta_url' => CHATLY_MAIL_PORTAL_URL . '/cc'];
    $opts['preheader'] = mb_substr(preg_replace('/\s+/', ' ', ecFill($message, $vars)), 0, 120);
    return ['subject' => $subj, 'html' => chatlyMailTemplate($profile, $subj, $body, $opts)];
}

function ecFirstName(string $name): string { return chatlyMailFirstName($name); }

/** COMP-001 Team Members (member) and Team Leaders (lead). */
function ecNunnoPeople(): array {
    $out = [];
    foreach (chatlyMailReadJson(__DIR__ . '/../data/users.json') as $u) {
        if (!is_array($u) || empty($u['id'])) continue;
        $role = strtolower((string) ($u['role'] ?? ''));
        if (!in_array($role, ['member', 'lead'], true)) continue;
        if (chatlyUserCompany($u) !== 'COMP-001') continue;
        $out[(string) $u['id']] = $u;
    }
    return $out;
}

function ecCompanies(): array {
    $list = chatlyMailReadJson(__DIR__ . '/../data/companies.json');
    $out = [];
    foreach ($list as $c) if (is_array($c) && !empty($c['id'])) $out[(string) $c['id']] = $c;
    return $out;
}

/** Owner email + company admin accounts of each company, de-duplicated by email. */
function ecChatlyRecipients(array $companyIds): array {
    $companies = ecCompanies();
    $admins = [];
    foreach (chatlyMailReadJson(__DIR__ . '/../data/users.json') as $u) {
        if (!is_array($u) || strtolower((string) ($u['role'] ?? '')) !== 'admin') continue;
        if (strtolower((string) ($u['status'] ?? 'active')) === 'suspended') continue;
        $admins[chatlyUserCompany($u)][] = $u;
    }
    $seen = [];
    $out = [];
    $missing = [];
    foreach ($companyIds as $cid) {
        $c = $companies[$cid] ?? null;
        if (!$c) { $missing[] = $cid; continue; }
        $cands = [['email' => (string) ($c['owner_email'] ?? ''), 'name' => (string) ($c['owner_name'] ?? '')]];
        foreach ($admins[$cid] ?? [] as $a) $cands[] = ['email' => (string) ($a['email'] ?? ''), 'name' => (string) ($a['name'] ?? '')];
        $found = false;
        foreach ($cands as $x) {
            $e = trim($x['email']);
            if (!filter_var($e, FILTER_VALIDATE_EMAIL)) continue;
            $key = strtolower($e);
            $found = true;
            if (isset($seen[$key])) continue;
            $seen[$key] = true;
            $out[] = ['email' => $e, 'name' => $x['name'], 'company' => (string) ($c['name'] ?? $cid), 'company_id' => $cid];
        }
        if (!$found) $missing[] = $cid;
    }
    return ['recipients' => $out, 'missing' => $missing];
}

/** Refuse a batch that would go over the profile's daily cap. */
function ecCheckDailyCap(string $profile, int $n): array {
    $p = chatlyMailProfile($profile);
    $cap = (int) ($p['daily_cap'] ?? CHATLY_MAIL_DEFAULT_DAILY);
    $used = chatlyMailSentLast24h($profile);
    if ($used + $n > $cap) {
        ecFail(429, 'Daily sending limit reached for this sender: ' . $used . ' of ' . $cap . ' emails used in the last 24 hours, this batch needs ' . $n . '. Gmail blocks accounts that send too much; try again later or raise the limit in sender settings.', ['sent_last_24h' => $used, 'daily_cap' => $cap]);
    }
    return ['used' => $used, 'cap' => $cap];
}

$actorName = (string) ($me['name'] ?? ($me['username'] ?? 'Admin'));

switch ($action) {

    case 'settings': {
        $profile = ecProfileParam($in, $isSuper, $isNunnoAdmin);
        ecOut(['settings' => chatlyMailProfilePublic($profile), 'my_email' => ecMyEmail($me)]);
    }

    case 'save_settings': {
        $profile = ecProfileParam($in, $isSuper, $isNunnoAdmin);
        $fields = [];
        foreach (['sender_name', 'sender_email', 'reply_to', 'enabled', 'daily_cap', 'app_password', 'clear_password'] as $k) {
            if (array_key_exists($k, $in)) $fields[$k] = $in[$k];
        }
        $r = chatlyMailSaveProfile($profile, $fields, $actorName);
        if (!$r['ok']) ecFail(400, (string) $r['error']);
        ecOut(['message' => !empty($r['changed']) ? 'Sender settings saved.' : 'Nothing to change.', 'settings' => chatlyMailProfilePublic($profile)]);
    }

    case 'test': {
        $profile = ecProfileParam($in, $isSuper, $isNunnoAdmin);
        if (!ecRateOk('test:' . ($me['id'] ?? ''), 3, 60)) ecFail(429, 'Please wait a minute before sending another test email.');
        $to = trim((string) ($in['to'] ?? ''));
        if ($to === '') $to = ecMyEmail($me);
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) ecFail(400, 'Your account has no valid email address. Type one in the test box.');
        $p = chatlyMailProfile($profile);
        $body = chatlyMailP('This is a test email from the <strong>' . chatlyMailEsc($profile) . '</strong> sender (' . chatlyMailEsc((string) $p['sender_email']) . ').')
            . chatlyMailP('If you can read this in your inbox, emails from this sender are working.')
            . chatlyMailInfoBox(['Sent at' => chatlyMailPkTime(date('c')), 'Sent by' => $actorName], $profile);
        $html = chatlyMailTemplate($profile, 'Test email: it works!', $body, ['preheader' => 'Sender test from ' . $p['sender_email']]);
        $r = chatlySendMail($profile, $to, $actorName, 'Test email from ' . $p['sender_name'], $html, null, [
            'template_id' => 'test', 'sent_by' => $actorName, 'company_id' => $profile === 'nunno' ? 'COMP-001' : '',
        ]);
        if (!$r['ok']) ecFail(502, 'Test email failed: ' . $r['error'], ['transport' => $r['transport'], 'note' => $r['note']]);
        ecOut(['message' => 'Test email sent to ' . $to . '.', 'transport' => $r['transport'], 'note' => $r['note']]);
    }

    case 'preview': {
        $profile = ecProfileParam($in, $isSuper, $isNunnoAdmin);
        $subject = ecCleanSubject($in['subject'] ?? '');
        $message = ecCleanMessage($in['message'] ?? '');
        if ($subject === '') $subject = '(subject)';
        $vars = $profile === 'nunno'
            ? ['name' => 'Ali Raza', 'first_name' => 'Ali', 'username' => 'ali', 'email' => 'ali@example.com', 'company' => 'Nunno']
            : ['name' => 'Sara Khan', 'first_name' => 'Sara', 'username' => 'company_admin', 'email' => 'owner@example.com', 'company' => 'Example Traders'];
        $mail = ecBuildEmail($profile, $subject, $message, $vars);
        ecOut(['subject' => $mail['subject'], 'html' => $mail['html']]);
    }

    case 'logs': {
        $profile = ecProfileParam($in, $isSuper, $isNunnoAdmin);
        $limit = max(1, min(300, (int) ($_GET['limit'] ?? 150)));
        $p = chatlyMailProfile($profile);
        ecOut(['logs' => chatlyMailRecentLogs($profile, $limit), 'sent_last_24h' => chatlyMailSentLast24h($profile), 'daily_cap' => (int) $p['daily_cap']]);
    }

    case 'send_nunno': {
        if (!$isSuper && !$isNunnoAdmin) ecFail(403, 'Not allowed.');
        $subject = ecCleanSubject($in['subject'] ?? '');
        $message = ecCleanMessage($in['message'] ?? '');
        if ($subject === '' || $message === '') ecFail(400, 'Write a subject and a message.');
        $people = ecNunnoPeople();
        $audience = (string) ($in['audience'] ?? 'pick');
        $ids = (isset($in['user_ids']) && is_array($in['user_ids'])) ? array_values(array_unique(array_map('strval', $in['user_ids']))) : [];
        $targets = [];
        $skipped = [];
        if ($ids) {
            foreach ($ids as $id) {
                if (!isset($people[$id])) { $skipped[] = ['to' => $id, 'name' => '', 'ok' => false, 'error' => 'Not a Nunno TM/TL.']; continue; }
                $targets[] = $people[$id];
            }
        } elseif (in_array($audience, ['tm', 'tl', 'all'], true)) {
            foreach ($people as $u) {
                $role = strtolower((string) ($u['role'] ?? ''));
                if ($audience === 'tm' && $role !== 'member') continue;
                if ($audience === 'tl' && $role !== 'lead') continue;
                if (strtolower((string) ($u['status'] ?? 'active')) === 'suspended') continue;
                $targets[] = $u;
            }
        } else {
            ecFail(400, 'Choose who should get this email.');
        }
        $messages = [];
        foreach ($targets as $u) {
            $email = trim((string) ($u['email'] ?? ''));
            $name = (string) ($u['name'] ?? '');
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { $skipped[] = ['to' => $email !== '' ? $email : (string) ($u['username'] ?? $u['id']), 'name' => $name, 'ok' => false, 'error' => 'No valid email address on file.']; continue; }
            $mail = ecBuildEmail('nunno', $subject, $message, [
                'name' => $name !== '' ? $name : 'there', 'first_name' => ecFirstName($name), 'username' => (string) ($u['username'] ?? ''),
                'email' => $email, 'company' => 'Nunno',
            ]);
            $messages[] = ['to' => $email, 'name' => $name, 'subject' => $mail['subject'], 'html' => $mail['html']];
        }
        if (count($messages) > CHATLY_MAIL_MAX_PER_REQ) ecFail(400, 'Too many recipients in one request (max ' . CHATLY_MAIL_MAX_PER_REQ . ').');
        if (!$messages) ecOut(['sent' => 0, 'failed' => count($skipped), 'results' => $skipped, 'transport' => null, 'note' => null]);
        if (!ecRateOk('send:' . ($me['id'] ?? ''), 120, 600)) ecFail(429, 'Too many sends in a short time. Please wait a few minutes.');
        ecCheckDailyCap('nunno', count($messages));
        $preset = preg_replace('/[^a-z0-9_]/', '', strtolower((string) ($in['preset'] ?? 'custom')));
        $r = chatlyMailBatch('nunno', $messages, ['template_id' => 'composer_' . ($preset ?: 'custom'), 'sent_by' => $actorName, 'company_id' => 'COMP-001']);
        $p = chatlyMailProfile('nunno');
        ecOut([
            'sent' => $r['sent'], 'failed' => $r['failed'] + count($skipped),
            'results' => array_merge($r['results'], $skipped),
            'transport' => $r['transport'], 'note' => $r['note'],
            'sent_last_24h' => chatlyMailSentLast24h('nunno'), 'daily_cap' => (int) $p['daily_cap'],
        ]);
    }

    case 'send_chatly': {
        if (!$isSuper) ecFail(403, 'Only the super admin can email companies.');
        $subject = ecCleanSubject($in['subject'] ?? '');
        $message = ecCleanMessage($in['message'] ?? '');
        if ($subject === '' || $message === '') ecFail(400, 'Write a subject and a message.');
        $audience = (string) ($in['audience'] ?? 'pick');
        $ids = (isset($in['company_ids']) && is_array($in['company_ids'])) ? array_values(array_unique(array_map('strval', $in['company_ids']))) : [];
        if (!$ids && $audience === 'all') $ids = array_keys(ecCompanies());
        if (!$ids) ecFail(400, 'Choose at least one company.');
        $res = ecChatlyRecipients($ids);
        $skipped = [];
        foreach ($res['missing'] as $cid) $skipped[] = ['to' => $cid, 'name' => '', 'ok' => false, 'error' => 'Company not found or has no valid owner/admin email.'];
        $messages = [];
        foreach ($res['recipients'] as $x) {
            $mail = ecBuildEmail('chatly', $subject, $message, [
                'name' => $x['name'] !== '' ? $x['name'] : 'there', 'first_name' => ecFirstName($x['name']), 'username' => '',
                'email' => $x['email'], 'company' => $x['company'],
            ]);
            $messages[] = ['to' => $x['email'], 'name' => $x['name'] !== '' ? $x['name'] : $x['company'], 'subject' => $mail['subject'], 'html' => $mail['html']];
        }
        if (count($messages) > CHATLY_MAIL_MAX_PER_REQ) ecFail(400, 'Too many recipients in one request (max ' . CHATLY_MAIL_MAX_PER_REQ . ').');
        if (!$messages) ecOut(['sent' => 0, 'failed' => count($skipped), 'results' => $skipped, 'transport' => null, 'note' => null]);
        if (!ecRateOk('send:' . ($me['id'] ?? ''), 120, 600)) ecFail(429, 'Too many sends in a short time. Please wait a few minutes.');
        ecCheckDailyCap('chatly', count($messages));
        $preset = preg_replace('/[^a-z0-9_]/', '', strtolower((string) ($in['preset'] ?? 'custom')));
        $r = chatlyMailBatch('chatly', $messages, ['template_id' => 'composer_' . ($preset ?: 'custom'), 'sent_by' => $actorName]);
        $p = chatlyMailProfile('chatly');
        ecOut([
            'sent' => $r['sent'], 'failed' => $r['failed'] + count($skipped),
            'results' => array_merge($r['results'], $skipped),
            'transport' => $r['transport'], 'note' => $r['note'],
            'sent_last_24h' => chatlyMailSentLast24h('chatly'), 'daily_cap' => (int) $p['daily_cap'],
        ]);
    }

    default:
        ecFail(400, 'Unknown action.');
}
