<?php
/**
 * Chatly mailer: a small, dependency-free Gmail SMTP client.
 *
 * Why: Gmail rejects or spam-folders mail that only claims to be From a @gmail.com
 * address when PHP mail() sends it from another server. Real delivery needs Gmail's
 * own SMTP server with an App Password for that Gmail account.
 *
 * Two sender PROFILES with separate credentials, stored server-side only in
 * data/email_config.json (data/ is web-denied and git-ignored) under "profiles":
 *   nunno  = numeniousnexus@gmail.com  (Nunno company admin portal, company COMP-001)
 *   chatly = chatly.pk@gmail.com       (Chatly super admin, other companies)
 * Each profile: sender_email, sender_name, reply_to, app_password, enabled, daily_cap.
 * The App Password is never returned to the browser (see chatlyMailProfilePublic()).
 * Without an App Password the mailer falls back to PHP mail() and says so.
 *
 * Transport: implicit TLS to smtp.gmail.com:465, falling back to STARTTLS on 587.
 * Every send is logged to data/email_logs.json (newest first, capped at 2,000).
 *
 * Main entry points:
 *   chatlySendMail($profile, $toEmail, $toName, $subject, $html, $text = null, $opts = [])
 *   chatlyMailBatch($profile, $messages, $opts = [])      (one SMTP connection for many)
 *   chatlyMailTemplate($profile, $heading, $bodyHtml, $opts = [])
 *   chatlyMailAuto*() automatic emails (approval, withdrawals, company welcome)
 */

const CHATLY_MAIL_LOG_CAP        = 2000;
const CHATLY_MAIL_MAX_PER_REQ    = 500;
const CHATLY_MAIL_DEFAULT_DAILY  = 450;   // Gmail (free) allows ~500 messages a day
const CHATLY_MAIL_CONNECT_TIMEOUT = 10;
const CHATLY_MAIL_READ_TIMEOUT   = 20;
const CHATLY_MAIL_PER_CONNECTION = 40;    // reconnect after this many messages
const CHATLY_MAIL_PORTAL_URL     = 'https://chatly.pk';
const CHATLY_MAIL_NUNNO_PLAN_URL = 'https://nunno.cloud/partner/chatly-c7x4k2';

function chatlyMailConfigFile(): string { return __DIR__ . '/../data/email_config.json'; }
function chatlyMailLogFile(): string { return __DIR__ . '/../data/email_logs.json'; }

function chatlyMailEsc($v): string {
    return htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function chatlyMailProfileNames(): array { return ['nunno', 'chatly']; }

function chatlyMailProfileDefaults(string $profile): array {
    if ($profile === 'nunno') {
        return ['sender_email' => 'numeniousnexus@gmail.com', 'sender_name' => 'Nunno', 'reply_to' => '',
            'app_password' => '', 'enabled' => true, 'daily_cap' => CHATLY_MAIL_DEFAULT_DAILY, 'updated_at' => null];
    }
    return ['sender_email' => 'chatly.pk@gmail.com', 'sender_name' => 'Chatly', 'reply_to' => '',
        'app_password' => '', 'enabled' => true, 'daily_cap' => CHATLY_MAIL_DEFAULT_DAILY, 'updated_at' => null];
}

/** Profile for a company: Nunno (COMP-001, and legacy accounts without a company) or Chatly. */
function chatlyMailProfileForCompany(?string $companyId): string {
    $c = strtolower(trim((string) $companyId));
    return ($c === '' || $c === 'comp-001' || $c === 'nunno') ? 'nunno' : 'chatly';
}

function chatlyMailReadJson(string $file): array {
    if (!is_file($file)) return [];
    $d = json_decode((string) @file_get_contents($file), true);
    return is_array($d) ? $d : [];
}

/**
 * Locked read-modify-write of a JSON file. $fn receives the decoded array by reference.
 * A file that exists but does not decode is left untouched (never wiped).
 */
function chatlyMailJsonUpdate(string $file, callable $fn): bool {
    $dir = dirname($file);
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    $h = @fopen($file, 'c+');
    if (!$h) return false;
    @flock($h, LOCK_EX);
    $raw = (string) stream_get_contents($h);
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        if (trim($raw) !== '') { @flock($h, LOCK_UN); fclose($h); return false; }
        $data = [];
    }
    $fn($data);
    rewind($h);
    ftruncate($h, 0);
    fwrite($h, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    fflush($h);
    @flock($h, LOCK_UN);
    fclose($h);
    return true;
}

/** Full profile settings, including the App Password (server-side use only). */
function chatlyMailProfile(string $profile): ?array {
    if (!in_array($profile, chatlyMailProfileNames(), true)) return null;
    $cfg = chatlyMailReadJson(chatlyMailConfigFile());
    $saved = (isset($cfg['profiles'][$profile]) && is_array($cfg['profiles'][$profile])) ? $cfg['profiles'][$profile] : [];
    $p = array_merge(chatlyMailProfileDefaults($profile), $saved);
    // Legacy single-account setting (email_service.php "SMTP" fields) for the same Gmail address.
    if (trim((string) $p['app_password']) === '' && !empty($cfg['smtp_pass'])
        && strcasecmp(trim((string) ($cfg['smtp_user'] ?? '')), (string) $p['sender_email']) === 0) {
        $p['app_password'] = (string) $cfg['smtp_pass'];
    }
    $p['enabled'] = !empty($p['enabled']);
    $p['daily_cap'] = max(1, (int) ($p['daily_cap'] ?: CHATLY_MAIL_DEFAULT_DAILY));
    return $p;
}

/** Profile settings safe for the browser: the App Password is never included. */
function chatlyMailProfilePublic(string $profile): ?array {
    $p = chatlyMailProfile($profile);
    if (!$p) return null;
    $pass = preg_replace('/\s+/', '', (string) $p['app_password']);
    return [
        'profile'           => $profile,
        'sender_email'      => (string) $p['sender_email'],
        'sender_name'       => (string) $p['sender_name'],
        'reply_to'          => (string) $p['reply_to'],
        'enabled'           => (bool) $p['enabled'],
        'daily_cap'         => (int) $p['daily_cap'],
        'app_password_set'  => $pass !== '',
        'app_password_mask' => $pass !== '' ? str_repeat('•', 4) . ' ' . str_repeat('•', 4) . ' ' . str_repeat('•', 4) . ' ' . str_repeat('•', 4) : '',
        'updated_at'        => $p['updated_at'] ?? null,
        'transport'         => $pass !== '' ? 'Gmail SMTP (smtp.gmail.com)' : 'PHP mail() fallback (no App Password saved)',
        'sent_last_24h'     => chatlyMailSentLast24h($profile),
    ];
}

/**
 * Save profile changes. Accepts: sender_name, sender_email, reply_to, enabled, daily_cap,
 * app_password (replaces; whitespace removed), clear_password (bool).
 */
function chatlyMailSaveProfile(string $profile, array $in, string $by = ''): array {
    if (!in_array($profile, chatlyMailProfileNames(), true)) return ['ok' => false, 'error' => 'Unknown sender profile.'];
    $changes = [];
    if (array_key_exists('sender_name', $in)) {
        $n = trim(preg_replace('/[\r\n\t<>"]+/', ' ', (string) $in['sender_name']));
        if ($n === '' || mb_strlen($n) > 60) return ['ok' => false, 'error' => 'Sender name must be 1-60 characters.'];
        $changes['sender_name'] = $n;
    }
    if (array_key_exists('sender_email', $in)) {
        $e = trim((string) $in['sender_email']);
        if (!filter_var($e, FILTER_VALIDATE_EMAIL)) return ['ok' => false, 'error' => 'Sender email is not a valid address.'];
        $changes['sender_email'] = $e;
    }
    if (array_key_exists('reply_to', $in)) {
        $r = trim((string) $in['reply_to']);
        if ($r !== '' && !filter_var($r, FILTER_VALIDATE_EMAIL)) return ['ok' => false, 'error' => 'Reply-To is not a valid address.'];
        $changes['reply_to'] = $r;
    }
    if (array_key_exists('enabled', $in)) $changes['enabled'] = (bool) $in['enabled'];
    if (array_key_exists('daily_cap', $in) && $in['daily_cap'] !== '' && $in['daily_cap'] !== null) {
        $changes['daily_cap'] = max(1, min(2000, (int) $in['daily_cap']));
    }
    if (!empty($in['clear_password'])) {
        $changes['app_password'] = '';
    } elseif (isset($in['app_password']) && trim((string) $in['app_password']) !== '') {
        $pw = preg_replace('/\s+/', '', (string) $in['app_password']);
        if (strpos($pw, '•') !== false) {
            // the masked placeholder came back: keep the saved password
        } elseif (strlen($pw) < 8 || strlen($pw) > 64) {
            return ['ok' => false, 'error' => 'That does not look like a Gmail App Password (16 letters, spaces optional).'];
        } else {
            $changes['app_password'] = $pw;
        }
    }
    if (!$changes) return ['ok' => true, 'changed' => false];
    $changes['updated_at'] = date('c');
    if ($by !== '') $changes['updated_by'] = $by;
    $ok = chatlyMailJsonUpdate(chatlyMailConfigFile(), function (array &$cfg) use ($profile, $changes) {
        if (!isset($cfg['profiles']) || !is_array($cfg['profiles'])) $cfg['profiles'] = [];
        $cur = (isset($cfg['profiles'][$profile]) && is_array($cfg['profiles'][$profile])) ? $cfg['profiles'][$profile] : chatlyMailProfileDefaults($profile);
        $cfg['profiles'][$profile] = array_merge($cur, $changes);
    });
    return $ok ? ['ok' => true, 'changed' => true] : ['ok' => false, 'error' => 'Could not save data/email_config.json (file locked or unreadable).'];
}

/* =========================================================
   Logging
   ========================================================= */

function chatlyMailLogMany(array $rows): void {
    if (!$rows) return;
    chatlyMailJsonUpdate(chatlyMailLogFile(), function (array &$logs) use ($rows) {
        $logs = array_slice(array_merge(array_reverse($rows), array_values($logs)), 0, CHATLY_MAIL_LOG_CAP);
    });
}

function chatlyMailLogRow(string $profile, string $to, string $toName, string $subject, bool $ok, ?string $error, string $transport, string $senderEmail, array $opts): array {
    return [
        'id'              => 'ML-' . strtoupper(bin2hex(random_bytes(4))),
        'timestamp'       => date('c'),
        'profile'         => $profile,
        'recipient_email' => $to,
        'recipient_name'  => $toName,
        'subject'         => mb_substr($subject, 0, 200),
        'template_id'     => (string) ($opts['template_id'] ?? 'custom'),
        'status'          => $ok ? 'delivered' : 'failed',
        'error'           => $ok ? null : mb_substr((string) $error, 0, 300),
        'transport'       => $transport,
        'sent_by'         => (string) ($opts['sent_by'] ?? 'System'),
        'sender_email'    => $senderEmail,
        'company_id'      => (string) ($opts['company_id'] ?? ''),
    ];
}

/** Messages a profile delivered in the last 24 hours (counts toward the Gmail daily limit). */
function chatlyMailSentLast24h(string $profile): int {
    $since = time() - 86400;
    $n = 0;
    foreach (chatlyMailReadJson(chatlyMailLogFile()) as $l) {
        if (!is_array($l) || ($l['profile'] ?? '') !== $profile || ($l['status'] ?? '') !== 'delivered') continue;
        $t = strtotime((string) ($l['timestamp'] ?? ''));
        if ($t === false || $t < $since) break;   // newest first: everything after is older
        $n++;
    }
    return $n;
}

/** Recent log rows of one profile (newest first), without anything sensitive. */
function chatlyMailRecentLogs(string $profile, int $limit = 100): array {
    $out = [];
    foreach (chatlyMailReadJson(chatlyMailLogFile()) as $l) {
        if (!is_array($l) || ($l['profile'] ?? '') !== $profile) continue;
        $out[] = [
            'id'        => (string) ($l['id'] ?? ''),
            'time'      => (string) ($l['timestamp'] ?? ''),
            'to'        => (string) ($l['recipient_email'] ?? ''),
            'name'      => (string) ($l['recipient_name'] ?? ''),
            'subject'   => (string) ($l['subject'] ?? ''),
            'kind'      => (string) ($l['template_id'] ?? ''),
            'ok'        => ($l['status'] ?? '') === 'delivered',
            'error'     => $l['error'] ?? null,
            'transport' => (string) ($l['transport'] ?? ''),
            'by'        => (string) ($l['sent_by'] ?? ''),
        ];
        if (count($out) >= $limit) break;
    }
    return $out;
}

/* =========================================================
   MIME helpers
   ========================================================= */

/** RFC 2047 encoding (UTF-8, base64 words of at most 75 chars) for non-ASCII header text. */
function chatlyMailEncodeHeader(string $s): string {
    $s = trim(preg_replace('/[\r\n\t]+/', ' ', $s));
    if (!preg_match('/[^\x20-\x7E]/', $s)) return $s;
    $chars = preg_split('//u', $s, -1, PREG_SPLIT_NO_EMPTY);
    if (!is_array($chars)) $chars = str_split($s);   // not valid UTF-8: split bytes
    $parts = [];
    $buf = '';
    foreach ($chars as $ch) {
        if ($buf !== '' && strlen($buf) + strlen($ch) > 45) { $parts[] = $buf; $buf = ''; }
        $buf .= $ch;
    }
    if ($buf !== '') $parts[] = $buf;
    $words = [];
    foreach ($parts as $p) $words[] = '=?UTF-8?B?' . base64_encode($p) . '?=';
    return implode("\r\n ", $words);
}

function chatlyMailAddr(string $email, string $name = ''): string {
    $name = trim(preg_replace('/[\r\n\t"<>\\\\]+/', ' ', $name));
    if ($name === '') return '<' . $email . '>';
    if (preg_match('/[^\x20-\x7E]/', $name)) return chatlyMailEncodeHeader($name) . ' <' . $email . '>';
    return '"' . $name . '" <' . $email . '>';
}

function chatlyMailQp(string $s): string {
    $s = str_replace(["\r\n", "\r"], "\n", $s);
    return quoted_printable_encode(str_replace("\n", "\r\n", $s));
}

/** Headers (without To/Subject) and body of a multipart/alternative (text + HTML) message. */
function chatlyMailBuildParts(string $fromEmail, string $fromName, string $replyTo, string $html, string $text): array {
    $boundary = '=_chatly_' . bin2hex(random_bytes(12));
    $at = strrchr($fromEmail, '@');
    $domain = $at !== false ? substr($at, 1) : 'chatly.pk';
    $headers = [
        'Date: ' . date('r'),
        'From: ' . chatlyMailAddr($fromEmail, $fromName),
        'Reply-To: ' . chatlyMailAddr($replyTo !== '' ? $replyTo : $fromEmail, $fromName),
        'Message-ID: <' . bin2hex(random_bytes(10)) . '.' . time() . '@' . $domain . '>',
        'MIME-Version: 1.0',
        'X-Mailer: Chatly Mailer 1.0',
        'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
    ];
    $body = "This is a multi-part message in MIME format.\r\n\r\n"
        . '--' . $boundary . "\r\n"
        . "Content-Type: text/plain; charset=UTF-8\r\n"
        . "Content-Transfer-Encoding: quoted-printable\r\n\r\n"
        . chatlyMailQp($text) . "\r\n\r\n"
        . '--' . $boundary . "\r\n"
        . "Content-Type: text/html; charset=UTF-8\r\n"
        . "Content-Transfer-Encoding: quoted-printable\r\n\r\n"
        . chatlyMailQp($html) . "\r\n\r\n"
        . '--' . $boundary . "--\r\n";
    return ['headers' => $headers, 'body' => $body];
}

/** Plain-text alternative from an HTML body. */
function chatlyMailHtmlToText(string $html): string {
    $t = preg_replace('#<(style|script|head|title)\b[^>]*>.*?</\1>#is', '', $html);
    $t = preg_replace('#<div[^>]*display:\s*none[^>]*>.*?</div>#is', '', $t);
    $t = preg_replace_callback('#<a\s[^>]*href=["\']([^"\']+)["\'][^>]*>(.*?)</a>#is', function ($m) {
        $label = trim(html_entity_decode(strip_tags($m[2]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $href = html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return ($label === '' || $label === $href) ? $href : $label . ' (' . $href . ')';
    }, $t);
    $t = preg_replace('#<br\s*/?>#i', "\n", $t);
    $t = preg_replace('#<li[^>]*>#i', "\n- ", $t);
    $t = preg_replace('#</(p|div|h[1-6]|tr|ul|ol|table)>#i', "\n\n", $t);
    $t = html_entity_decode(strip_tags($t), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $t = str_replace("\xC2\xA0", ' ', $t);
    $t = preg_replace('/[ \t]+/', ' ', $t);
    $t = preg_replace('/ *\n */', "\n", $t);
    $t = preg_replace('/\n{3,}/', "\n\n", $t);
    return trim($t);
}

/**
 * Plain text typed by an admin -> safe HTML: everything escaped, blank lines make
 * paragraphs, single line breaks stay, lines starting with "- " or "• " become a list,
 * and http(s) links become clickable.
 */
function chatlyMailTextToHtml(string $text, string $linkColor = '#059669'): string {
    $text = str_replace(["\r\n", "\r"], "\n", trim($text));
    if ($text === '') return '';
    $link = function (string $escaped) use ($linkColor): string {
        // Text is already escaped: stop a link at an escaped quote (&quot; / &#039;).
        return preg_replace_callback('#\bhttps?://(?:(?!&quot;|&\#039;)[^\s<])+#i', function ($m) use ($linkColor) {
            $url = $m[0];
            $trail = '';
            while ($url !== '' && strpos('.,!?;:)', substr($url, -1)) !== false) { $trail = substr($url, -1) . $trail; $url = substr($url, 0, -1); }
            if (substr($url, -4) === '&amp') { $url = substr($url, 0, -4); $trail = '&amp' . $trail; }
            return '<a href="' . $url . '" style="color:' . $linkColor . ';font-weight:600;" target="_blank">' . $url . '</a>' . $trail;
        }, $escaped);
    };
    $html = '';
    foreach (preg_split('/\n{2,}/', $text) as $block) {
        $lines = explode("\n", trim($block));
        $para = [];
        $items = [];
        $flushPara = function () use (&$para, &$html, $link) {
            if ($para) $html .= '<p style="margin:0 0 14px;">' . implode('<br>', array_map($link, $para)) . '</p>';
            $para = [];
        };
        $flushList = function () use (&$items, &$html, $link) {
            if ($items) {
                $html .= '<ul style="margin:0 0 14px;padding-left:20px;">';
                foreach ($items as $it) $html .= '<li style="margin:0 0 6px;">' . $link($it) . '</li>';
                $html .= '</ul>';
            }
            $items = [];
        };
        foreach ($lines as $line) {
            $line = rtrim($line);
            if (preg_match('/^\s*(?:[-*]|•)\s+(.+)$/u', $line, $m)) {
                $flushPara();
                $items[] = chatlyMailEsc($m[1]);
            } else {
                $flushList();
                $para[] = chatlyMailEsc($line);
            }
        }
        $flushPara();
        $flushList();
    }
    return $html;
}

function chatlyMailBrand(string $profile): array {
    if ($profile === 'nunno') {
        return ['name' => 'Nunno', 'tag' => 'Nunno x Chatly sales team', 'header' => '#047857', 'header2' => '#10b981',
            'accent' => '#a7f3d0', 'button' => '#059669', 'link' => '#059669', 'soft' => '#ecfdf5',
            'footer' => 'You are receiving this because you work with Nunno through Chatly.'];
    }
    return ['name' => 'Chatly', 'tag' => 'WhatsApp sales platform', 'header' => '#0b1a14', 'header2' => '#123326',
        'accent' => '#1fe08a', 'button' => '#0c9f61', 'link' => '#0c9f61', 'soft' => '#effaf5',
        'footer' => 'You are receiving this because your company uses Chatly.'];
}

/**
 * The one branded email layout (inline CSS, table based, mobile friendly).
 * $bodyHtml must already be safe HTML. Options: cta_text, cta_url, preheader, note.
 */
function chatlyMailTemplate(string $profile, string $heading, string $bodyHtml, array $opts = []): string {
    $b = chatlyMailBrand($profile);
    $p = chatlyMailProfile($profile) ?: chatlyMailProfileDefaults($profile);
    $e = 'chatlyMailEsc';
    $font = "-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif";
    $pre = trim((string) ($opts['preheader'] ?? ''));
    $ctaText = trim((string) ($opts['cta_text'] ?? ''));
    $ctaUrl = trim((string) ($opts['cta_url'] ?? ''));
    if ($ctaUrl !== '' && !preg_match('#^https?://#i', $ctaUrl)) $ctaUrl = '';
    $cta = '';
    if ($ctaText !== '' && $ctaUrl !== '') {
        $cta = '<tr><td style="padding:4px 28px 26px;">'
            . '<table role="presentation" cellpadding="0" cellspacing="0" border="0"><tr>'
            . '<td style="border-radius:10px;background:' . $b['button'] . ';">'
            . '<a href="' . $e($ctaUrl) . '" target="_blank" style="display:inline-block;padding:13px 26px;font:700 15px ' . $font . ';color:#ffffff;text-decoration:none;border-radius:10px;">' . $e($ctaText) . ' &rarr;</a>'
            . '</td></tr></table></td></tr>';
    }
    $note = trim((string) ($opts['note'] ?? ''));
    $noteHtml = $note !== '' ? '<div style="margin-top:8px;">' . $e($note) . '</div>' : '';
    $year = date('Y');

    return '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8">'
        . '<meta name="viewport" content="width=device-width, initial-scale=1.0">'
        . '<meta name="color-scheme" content="light"><meta name="supported-color-schemes" content="light">'
        . '<title>' . $e($heading) . '</title></head>'
        . '<body style="margin:0;padding:0;background:#f1f5f4;-webkit-text-size-adjust:100%;">'
        . ($pre !== '' ? '<div style="display:none;max-height:0;overflow:hidden;opacity:0;color:transparent;">' . $e($pre) . '</div>' : '')
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#f1f5f4;">'
        . '<tr><td align="center" style="padding:24px 12px;">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:600px;width:100%;background:#ffffff;border-radius:16px;overflow:hidden;border:1px solid #e2e8f0;">'
        . '<tr><td style="background:' . $b['header'] . ';background-image:linear-gradient(135deg,' . $b['header'] . ',' . $b['header2'] . ');padding:22px 28px;">'
        . '<div style="font:800 24px/1.1 ' . $font . ';color:#ffffff;letter-spacing:-0.02em;">' . $e($b['name']) . '<span style="color:' . $b['accent'] . ';">.</span></div>'
        . '<div style="font:600 12px/1.4 ' . $font . ';color:#d1fae5;margin-top:6px;letter-spacing:0.02em;">' . $e($b['tag']) . '</div>'
        . '</td></tr>'
        . '<tr><td style="padding:28px 28px 10px;font:15px/1.65 ' . $font . ';color:#334155;">'
        . '<h1 style="margin:0 0 16px;font:800 21px/1.3 ' . $font . ';color:#0f172a;letter-spacing:-0.01em;">' . $e($heading) . '</h1>'
        . $bodyHtml
        . '</td></tr>'
        . $cta
        . '<tr><td style="padding:18px 28px 24px;border-top:1px solid #eef2f1;background:#fafcfb;font:12px/1.6 ' . $font . ';color:#64748b;">'
        . $e($b['footer']) . ' Questions? Just reply to this email.'
        . $noteHtml
        . '<div style="margin-top:8px;color:#94a3b8;">&copy; ' . $year . ' ' . $e($b['name']) . ' &middot; sent from ' . $e((string) $p['sender_email']) . '</div>'
        . '</td></tr>'
        . '</table></td></tr></table></body></html>';
}

/* =========================================================
   SMTP client
   ========================================================= */

function chatlySmtpShort(string $text): string {
    $t = trim(preg_replace('/\s+/', ' ', $text));
    return mb_substr($t, 0, 220);
}

/** Read one (possibly multi-line) SMTP reply. Returns [code, text]; code 0 = no reply. */
function chatlySmtpRead($fp): array {
    $text = '';
    while (true) {
        $line = @fgets($fp, 2048);
        if ($line === false || $line === '') {
            $meta = @stream_get_meta_data($fp);
            $why = !empty($meta['timed_out']) ? 'timed out waiting for the mail server' : 'connection closed by the mail server';
            return [0, $text !== '' ? trim($text) . ' (' . $why . ')' : $why];
        }
        $text .= $line;
        if (!isset($line[3]) || $line[3] !== '-') {
            return [(int) substr($line, 0, 3), trim($text)];
        }
    }
}

function chatlySmtpWrite($fp, string $data): bool {
    $len = strlen($data);
    $done = 0;
    $stalls = 0;
    while ($done < $len) {
        $n = @fwrite($fp, substr($data, $done, 8192));
        if ($n === false) return false;
        if ($n === 0) {
            if (++$stalls > 50) return false;
            usleep(20000);
            continue;
        }
        $stalls = 0;
        $done += $n;
    }
    return true;
}

/** Send one command and check the reply code. $label is used in errors (never the command itself). */
function chatlySmtpCmd($fp, string $cmd, array $expect, string $label): array {
    if (!chatlySmtpWrite($fp, $cmd . "\r\n")) {
        return ['ok' => false, 'code' => 0, 'error' => $label . ': could not write to the mail server'];
    }
    $reply = chatlySmtpRead($fp);
    $code = $reply[0];
    if (!in_array($code, $expect, true)) {
        return ['ok' => false, 'code' => $code, 'error' => $label . ' failed: ' . chatlySmtpShort($reply[1])];
    }
    return ['ok' => true, 'code' => $code, 'text' => $reply[1]];
}

function chatlySmtpAuth($fp, string $user, string $pass): array {
    $r = chatlySmtpCmd($fp, 'AUTH LOGIN', [334], 'AUTH LOGIN');
    if (!$r['ok']) return $r;
    $r = chatlySmtpCmd($fp, base64_encode($user), [334], 'AUTH username');
    if (!$r['ok']) return $r;
    $r = chatlySmtpCmd($fp, base64_encode($pass), [235], 'AUTH password');
    if (!$r['ok']) {
        $r['error'] = 'Gmail rejected the login for ' . $user . ' (wrong or revoked App Password? Create one at Google Account > Security > 2-Step Verification > App passwords). ' . $r['error'];
    }
    return $r;
}

function chatlyMailEhloName(): string {
    $h = (string) ($_SERVER['SERVER_NAME'] ?? '');
    if ($h === '' || !preg_match('/^[A-Za-z0-9.-]+$/', $h)) $h = (string) @gethostname();
    if ($h === '' || !preg_match('/^[A-Za-z0-9.-]+$/', $h)) $h = 'chatly.pk';
    return $h;
}

function chatlySmtpContext() {
    return stream_context_create(['ssl' => [
        'verify_peer'       => true,
        'verify_peer_name'  => true,
        'peer_name'         => 'smtp.gmail.com',
        'SNI_enabled'       => true,
        'allow_self_signed' => false,
    ]]);
}

/**
 * Connect + authenticate to Gmail. Tries implicit TLS on 465, then STARTTLS on 587.
 * Returns ['ok'=>true,'fp'=>resource,'port'=>int] or ['ok'=>false,'error'=>string,'auth'=>bool].
 */
function chatlySmtpOpen(string $user, string $pass): array {
    $host = 'smtp.gmail.com';
    $ehlo = chatlyMailEhloName();
    $errors = [];

    // 1) Implicit TLS on port 465
    $errno = 0;
    $errstr = '';
    $fp = @stream_socket_client('ssl://' . $host . ':465', $errno, $errstr, CHATLY_MAIL_CONNECT_TIMEOUT, STREAM_CLIENT_CONNECT, chatlySmtpContext());
    if ($fp) {
        stream_set_timeout($fp, CHATLY_MAIL_READ_TIMEOUT);
        $greet = chatlySmtpRead($fp);
        if ($greet[0] === 220) {
            $r = chatlySmtpCmd($fp, 'EHLO ' . $ehlo, [250], 'EHLO');
            if ($r['ok']) {
                $a = chatlySmtpAuth($fp, $user, $pass);
                if ($a['ok']) return ['ok' => true, 'fp' => $fp, 'port' => 465];
                @fclose($fp);
                // A rejected password will not work on 587 either.
                if ((int) $a['code'] >= 500) return ['ok' => false, 'error' => $a['error'], 'auth' => true];
                $errors[] = '465: ' . $a['error'];
            } else {
                $errors[] = '465: ' . $r['error'];
                @fclose($fp);
            }
        } else {
            $errors[] = '465: unexpected greeting: ' . chatlySmtpShort($greet[1]);
            @fclose($fp);
        }
    } else {
        $errors[] = '465: ' . ($errstr !== '' ? $errstr : 'could not connect') . ($errno ? ' (' . $errno . ')' : '');
    }

    // 2) STARTTLS on port 587
    $errno = 0;
    $errstr = '';
    $fp = @stream_socket_client('tcp://' . $host . ':587', $errno, $errstr, CHATLY_MAIL_CONNECT_TIMEOUT, STREAM_CLIENT_CONNECT, chatlySmtpContext());
    if (!$fp) {
        $errors[] = '587: ' . ($errstr !== '' ? $errstr : 'could not connect') . ($errno ? ' (' . $errno . ')' : '');
        return ['ok' => false, 'error' => 'Could not reach smtp.gmail.com (' . implode('; ', $errors) . '). The host may block outgoing SMTP ports.', 'auth' => false];
    }
    stream_set_timeout($fp, CHATLY_MAIL_READ_TIMEOUT);
    $greet = chatlySmtpRead($fp);
    if ($greet[0] !== 220) {
        @fclose($fp);
        $errors[] = '587: unexpected greeting: ' . chatlySmtpShort($greet[1]);
        return ['ok' => false, 'error' => 'SMTP connection failed (' . implode('; ', $errors) . ').', 'auth' => false];
    }
    $r = chatlySmtpCmd($fp, 'EHLO ' . $ehlo, [250], 'EHLO');
    if ($r['ok']) $r = chatlySmtpCmd($fp, 'STARTTLS', [220], 'STARTTLS');
    if (!$r['ok']) {
        @fclose($fp);
        $errors[] = '587: ' . $r['error'];
        return ['ok' => false, 'error' => 'SMTP connection failed (' . implode('; ', $errors) . ').', 'auth' => false];
    }
    $method = STREAM_CRYPTO_METHOD_TLS_CLIENT;
    if (defined('STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT')) $method |= STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;
    if (defined('STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT')) $method |= STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT;
    $tls = @stream_socket_enable_crypto($fp, true, $method);
    if ($tls !== true) {
        @fclose($fp);
        $errors[] = '587: TLS handshake failed';
        return ['ok' => false, 'error' => 'SMTP connection failed (' . implode('; ', $errors) . ').', 'auth' => false];
    }
    $r = chatlySmtpCmd($fp, 'EHLO ' . $ehlo, [250], 'EHLO');
    if (!$r['ok']) {
        @fclose($fp);
        $errors[] = '587: ' . $r['error'];
        return ['ok' => false, 'error' => 'SMTP connection failed (' . implode('; ', $errors) . ').', 'auth' => false];
    }
    $a = chatlySmtpAuth($fp, $user, $pass);
    if (!$a['ok']) {
        @fclose($fp);
        return ['ok' => false, 'error' => $a['error'], 'auth' => true];
    }
    return ['ok' => true, 'fp' => $fp, 'port' => 587];
}

function chatlySmtpClose($fp, bool $quit = true): void {
    if (!is_resource($fp)) return;
    if ($quit) {
        @stream_set_timeout($fp, 5);
        if (chatlySmtpWrite($fp, "QUIT\r\n")) chatlySmtpRead($fp);
    }
    @fclose($fp);
}

/** One message over an open, authenticated connection (with dot-stuffing). */
function chatlySmtpSendOne($fp, string $from, string $to, string $mime): array {
    $r = chatlySmtpCmd($fp, 'MAIL FROM:<' . $from . '>', [250], 'MAIL FROM');
    if (!$r['ok']) {
        if ($r['code'] !== 0) chatlySmtpCmd($fp, 'RSET', [250], 'RSET');
        return $r;
    }
    $r = chatlySmtpCmd($fp, 'RCPT TO:<' . $to . '>', [250, 251], 'RCPT TO');
    if (!$r['ok']) {
        if ($r['code'] !== 0) chatlySmtpCmd($fp, 'RSET', [250], 'RSET');
        return $r;
    }
    $r = chatlySmtpCmd($fp, 'DATA', [354], 'DATA');
    if (!$r['ok']) {
        if ($r['code'] !== 0) chatlySmtpCmd($fp, 'RSET', [250], 'RSET');
        return $r;
    }
    $data = str_replace(["\r\n", "\r"], "\n", $mime);
    $data = str_replace("\n", "\r\n", $data);
    $data = preg_replace('/^\./m', '..', $data);          // dot-stuffing (RFC 5321 4.5.2)
    if (substr($data, -2) !== "\r\n") $data .= "\r\n";
    if (!chatlySmtpWrite($fp, $data . ".\r\n")) {
        return ['ok' => false, 'code' => 0, 'error' => 'Connection lost while sending the message'];
    }
    $reply = chatlySmtpRead($fp);
    if ($reply[0] !== 250) {
        return ['ok' => false, 'code' => $reply[0], 'error' => 'Message not accepted: ' . chatlySmtpShort($reply[1])];
    }
    return ['ok' => true, 'code' => 250, 'text' => $reply[1]];
}

/* =========================================================
   Sending
   ========================================================= */

/** From address for the PHP mail() fallback: our own domain, never @gmail.com. */
function chatlyMailFallbackFrom(): string {
    $host = strtolower(preg_replace('/^www\./i', '', (string) ($_SERVER['SERVER_NAME'] ?? '')));
    if (!preg_match('/^[a-z0-9-]+(\.[a-z0-9-]+)+$/', $host)) $host = 'chatly.pk';
    return 'no-reply@' . $host;
}

/**
 * Send many messages from one profile over one SMTP connection (reconnects every
 * CHATLY_MAIL_PER_CONNECTION messages). Each message: to, name, subject, html, text (optional).
 * Options: template_id, sent_by, company_id, log (default true).
 * Returns ['ok','sent','failed','results'=>[['to','name','ok','error']],'transport','note','error'].
 */
function chatlyMailBatch(string $profile, array $messages, array $opts = []): array {
    $out = ['ok' => false, 'sent' => 0, 'failed' => 0, 'results' => [], 'transport' => 'smtp', 'note' => null, 'error' => null];
    $p = chatlyMailProfile($profile);
    $doLog = !array_key_exists('log', $opts) || !empty($opts['log']);
    $fromEmail = $p ? trim((string) $p['sender_email']) : '';
    $fromName = $p ? (string) $p['sender_name'] : '';
    $replyTo = $p ? trim((string) $p['reply_to']) : '';
    $pass = $p ? preg_replace('/\s+/', '', (string) $p['app_password']) : '';
    $useSmtp = $pass !== '';
    $fatal = null;
    if (!$p) $fatal = 'Unknown sender profile "' . $profile . '".';
    elseif (!filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) $fatal = 'The sender email of profile "' . $profile . '" is not valid.';
    elseif (empty($p['enabled'])) $fatal = 'Sending from "' . $profile . '" (' . $fromEmail . ') is turned off in sender settings.';
    if (!$useSmtp) {
        $out['transport'] = 'mail';
        $out['note'] = 'No Gmail App Password is saved for "' . $profile . '" (' . ($fromEmail ?: '?') . '), so PHP mail() was used instead. That mail often lands in spam or is rejected. Save the App Password to send through Gmail.';
    }
    $fallbackFrom = chatlyMailFallbackFrom();
    @set_time_limit(30 + count($messages) * 10);

    $fp = null;
    $onConn = 0;
    $logRows = [];
    foreach ($messages as $m) {
        $to = trim((string) ($m['to'] ?? ''));
        $name = trim(preg_replace('/[\r\n\t]+/', ' ', (string) ($m['name'] ?? '')));
        $subject = trim(preg_replace('/[\r\n\t]+/', ' ', (string) ($m['subject'] ?? '')));
        if ($subject === '') $subject = '(no subject)';
        $html = (string) ($m['html'] ?? '');
        $text = (isset($m['text']) && $m['text'] !== null && $m['text'] !== '') ? (string) $m['text'] : chatlyMailHtmlToText($html);
        $err = null;

        if ($fatal !== null) {
            $err = $fatal;
        } elseif (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            $err = 'Invalid email address.';
        } elseif ($useSmtp) {
            if ($fp && $onConn >= CHATLY_MAIL_PER_CONNECTION) { chatlySmtpClose($fp); $fp = null; }
            if (!$fp) {
                $c = chatlySmtpOpen($fromEmail, $pass);
                if ($c['ok']) {
                    $fp = $c['fp'];
                    $onConn = 0;
                } else {
                    $err = $c['error'];
                    $fatal = $c['error'];   // do not retry a failing login/connection for every recipient
                }
            }
            if ($err === null) {
                $parts = chatlyMailBuildParts($fromEmail, $fromName, $replyTo, $html, $text);
                $mime = implode("\r\n", array_merge(
                    ['To: ' . chatlyMailAddr($to, $name), 'Subject: ' . chatlyMailEncodeHeader($subject)],
                    $parts['headers']
                )) . "\r\n\r\n" . $parts['body'];
                $r = chatlySmtpSendOne($fp, $fromEmail, $to, $mime);
                $onConn++;
                if (!$r['ok']) {
                    $err = $r['error'];
                    if ((int) $r['code'] === 0 || (int) $r['code'] === 421) { chatlySmtpClose($fp, false); $fp = null; }
                }
            }
        } else {
            $parts = chatlyMailBuildParts($fallbackFrom, $fromName, $replyTo !== '' ? $replyTo : $fromEmail, $html, $text);
            $hdr = implode("\r\n", $parts['headers']);
            $encSubject = chatlyMailEncodeHeader($subject);
            $sent = @mail($to, $encSubject, $parts['body'], $hdr, '-f' . $fallbackFrom);
            if (!$sent) $sent = @mail($to, $encSubject, $parts['body'], $hdr);
            if (!$sent) $err = 'PHP mail() refused the message (no App Password saved, and the server mailer failed).';
        }

        $ok = ($err === null);
        if ($ok) $out['sent']++; else $out['failed']++;
        $out['results'][] = ['to' => $to, 'name' => $name, 'ok' => $ok, 'error' => $err];
        if ($doLog) $logRows[] = chatlyMailLogRow($profile, $to, $name, $subject, $ok, $err, $useSmtp ? 'smtp' : 'mail', $fromEmail, $opts);
    }
    if ($fp) chatlySmtpClose($fp);
    if ($doLog) chatlyMailLogMany($logRows);
    $out['ok'] = $out['sent'] > 0 && $out['failed'] === 0;
    if ($out['failed'] > 0) {
        foreach ($out['results'] as $r) { if (!$r['ok']) { $out['error'] = $r['error']; break; } }
    }
    return $out;
}

/**
 * Send one email. Returns ['ok'=>bool,'error'=>?string,'transport'=>'smtp'|'mail','note'=>?string].
 */
function chatlySendMail(string $profile, string $toEmail, string $toName, string $subject, string $html, ?string $text = null, array $opts = []): array {
    $r = chatlyMailBatch($profile, [['to' => $toEmail, 'name' => $toName, 'subject' => $subject, 'html' => $html, 'text' => $text]], $opts);
    $one = $r['results'][0] ?? ['ok' => false, 'error' => 'Nothing was sent.'];
    return ['ok' => (bool) $one['ok'], 'error' => $one['ok'] ? null : $one['error'], 'transport' => $r['transport'], 'note' => $r['note']];
}

/**
 * Send the HTTP response now and keep running (so an automatic email never slows the
 * action down). Also releases the session lock.
 */
function chatlyMailFinishResponse(): void {
    if (session_status() === PHP_SESSION_ACTIVE) @session_write_close();
    @ignore_user_abort(true);
    if (function_exists('fastcgi_finish_request')) {
        @fastcgi_finish_request();
    } elseif (function_exists('litespeed_finish_request')) {
        @litespeed_finish_request();
    } else {
        @flush();
    }
}

/* =========================================================
   Automatic emails (all best effort: callers wrap them in try/catch)
   ========================================================= */

function chatlyMailFirstName(string $name): string {
    $name = trim(preg_replace('/\s*\(.*?\)\s*/', ' ', $name));
    $parts = preg_split('/\s+/', $name);
    $first = $parts[0] ?? '';
    return $first !== '' ? $first : 'there';
}

function chatlyMailP(string $html): string {
    return '<p style="margin:0 0 14px;">' . $html . '</p>';
}

function chatlyMailInfoBox(array $rows, string $profile): string {
    $b = chatlyMailBrand($profile);
    $h = '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:' . $b['soft'] . ';border-radius:12px;margin:4px 0 18px;">';
    foreach ($rows as $label => $value) {
        $h .= '<tr><td style="padding:9px 14px;font-size:13px;color:#64748b;width:42%;vertical-align:top;">' . chatlyMailEsc($label) . '</td>'
            . '<td style="padding:9px 14px;font-size:14px;color:#0f172a;font-weight:700;vertical-align:top;word-break:break-word;">' . chatlyMailEsc($value) . '</td></tr>';
    }
    return $h . '</table>';
}

/** Nunno (COMP-001) TL/TM approved. */
function chatlyMailAutoApproval(array $user, string $approvedBy = ''): array {
    $email = trim((string) ($user['email'] ?? ''));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return ['ok' => false, 'error' => 'No valid email on file.'];
    $e = 'chatlyMailEsc';
    $name = trim((string) ($user['name'] ?? ''));
    $first = chatlyMailFirstName($name);
    $username = (string) ($user['username'] ?? '');
    $isLead = strtolower((string) ($user['role'] ?? '')) === 'lead';
    $link = '<a href="' . CHATLY_MAIL_PORTAL_URL . '" style="color:#059669;font-weight:700;">chatly.pk</a>';
    if ($isLead) {
        $subject = 'Congratulations ' . $first . ', you are approved as a Nunno Team Leader';
        $heading = 'Congratulations, ' . $first . '! You are approved.';
        $body = chatlyMailP('Great news: your application to join <strong>Nunno x Chatly</strong> as a <strong>Team Leader (recruiter)</strong> has been approved.')
            . chatlyMailP('As a Team Leader you bring new sales closers (TMs) to Nunno. You earn <strong>Rs. 500</strong> for every TM you recruit whose Nunno x Chatly plan is verified.')
            . chatlyMailInfoBox(['Sign in at' => 'chatly.pk', 'Username' => $username !== '' ? $username : $email, 'Role' => 'Team Leader (recruiter)'], 'nunno')
            . '<p style="margin:0 0 8px;font-weight:700;color:#0f172a;">Next steps</p>'
            . '<ol style="margin:0 0 14px;padding-left:20px;">'
            . '<li style="margin:0 0 6px;">Sign in at ' . $link . ' with your username and password.</li>'
            . '<li style="margin:0 0 6px;">Use your outreach chats to recruit new TMs.</li>'
            . '<li style="margin:0 0 6px;">Track your earnings and request payouts from your workspace.</li>'
            . '</ol>';
        $pre = 'Your Nunno Team Leader account is active.';
    } else {
        $subject = 'Congratulations ' . $first . ', you are approved as a Nunno sales closer';
        $heading = 'Congratulations, ' . $first . '! You are approved.';
        $body = chatlyMailP('Great news: your application to join <strong>Nunno x Chatly</strong> as a <strong>Sales Closer (TM)</strong> has been approved.')
            . chatlyMailP('You can now sign in, link your WhatsApp and start closing sales for Nunno. Your first <strong>Rs. 10,000</strong> of verified sales is paid to you in full; after that you earn a commission on every plan you sell.')
            . chatlyMailInfoBox(['Sign in at' => 'chatly.pk', 'Username' => $username !== '' ? $username : $email, 'Role' => 'Sales Closer (TM)'], 'nunno')
            . '<p style="margin:0 0 8px;font-weight:700;color:#0f172a;">Next steps</p>'
            . '<ol style="margin:0 0 14px;padding-left:20px;">'
            . '<li style="margin:0 0 6px;">Sign in at ' . $link . ' with your username and password.</li>'
            . '<li style="margin:0 0 6px;">To work and earn on Chatly, buy the Nunno x Chatly plan: <a href="' . CHATLY_MAIL_NUNNO_PLAN_URL . '" style="color:#059669;font-weight:700;">' . $e(CHATLY_MAIL_NUNNO_PLAN_URL) . '</a></li>'
            . '<li style="margin:0 0 6px;">Link your WhatsApp in the Sales Desk and start replying to leads.</li>'
            . '</ol>';
        $pre = 'Your Nunno sales closer account is active.';
    }
    $html = chatlyMailTemplate('nunno', $heading, $body, ['cta_text' => 'Sign in to Chatly', 'cta_url' => CHATLY_MAIL_PORTAL_URL, 'preheader' => $pre]);
    return chatlySendMail('nunno', $email, $name, $subject, $html, null, [
        'template_id' => $isLead ? 'auto_approval_tl' : 'auto_approval_tm', 'sent_by' => $approvedBy !== '' ? $approvedBy : 'System', 'company_id' => 'COMP-001',
    ]);
}

function chatlyMailUserEmail(string $userId): array {
    foreach (chatlyMailReadJson(__DIR__ . '/../data/users.json') as $u) {
        if (is_array($u) && ($u['id'] ?? '') === $userId) return [(string) ($u['email'] ?? ''), (string) ($u['name'] ?? '')];
    }
    return ['', ''];
}

function chatlyMailJoin(array $parts): string {
    $keep = [];
    foreach ($parts as $p) { $p = trim((string) $p); if ($p !== '') $keep[] = $p; }
    return implode(' · ', $keep);
}

function chatlyMailPkTime(?string $iso): string {
    if (!$iso) return '';
    try {
        $d = new DateTime($iso);
        $d->setTimezone(new DateTimeZone('Asia/Karachi'));
        return $d->format('D j M Y, g:i A') . ' PKT';
    } catch (Throwable $ex) {
        return '';
    }
}

/** "We received your withdrawal request" (Nunno users via nunno, others via chatly). */
function chatlyMailAutoWithdrawalRequested(array $w): array {
    list($email, $uName) = chatlyMailUserEmail((string) ($w['userId'] ?? ''));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return ['ok' => false, 'error' => 'No valid email on file.'];
    $profile = chatlyMailProfileForCompany((string) ($w['company_id'] ?? ''));
    $name = (string) ($w['userName'] ?? $uName);
    $amount = number_format((float) ($w['amount'] ?? 0));
    $rows = [
        'Amount'      => 'PKR ' . $amount,
        'Send to'     => chatlyMailJoin([$w['institution'] ?? ($w['method'] ?? ''), $w['accountTitle'] ?? '', $w['accountNumber'] ?? '']),
        'Request ID'  => (string) ($w['id'] ?? ''),
    ];
    $by = chatlyMailPkTime($w['expected_by'] ?? null);
    if ($by !== '') $rows['Expected by'] = $by;
    $body = chatlyMailP('Hello ' . chatlyMailEsc(chatlyMailFirstName($name)) . ', we have received your withdrawal request.')
        . chatlyMailInfoBox($rows, $profile)
        . chatlyMailP('Payouts arrive within <strong>24&ndash;48 hours</strong> on working days. There are <strong>no payouts on Saturday and Sunday</strong>, so a request made near the weekend arrives on Monday or Tuesday.')
        . chatlyMailP('We will email you again as soon as the money is sent.');
    $html = chatlyMailTemplate($profile, 'Withdrawal request received', $body, ['preheader' => 'PKR ' . $amount . ' is on its way within 24-48 working hours.']);
    return chatlySendMail($profile, $email, $name, 'Withdrawal request received: PKR ' . $amount, $html, null, [
        'template_id' => 'auto_withdrawal_requested', 'sent_by' => 'System', 'company_id' => (string) ($w['company_id'] ?? ''),
    ]);
}

/** "Your payout has been sent" (Nunno users via nunno, others via chatly). */
function chatlyMailAutoWithdrawalPaid(array $w): array {
    list($email, $uName) = chatlyMailUserEmail((string) ($w['userId'] ?? ''));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return ['ok' => false, 'error' => 'No valid email on file.'];
    $profile = chatlyMailProfileForCompany((string) ($w['company_id'] ?? ''));
    $name = (string) ($w['userName'] ?? $uName);
    $amount = number_format((float) ($w['amount'] ?? 0));
    $rows = [
        'Amount sent'  => 'PKR ' . $amount,
        'Sent to'      => chatlyMailJoin([$w['institution'] ?? ($w['method'] ?? 'account'), $w['accountTitle'] ?? '', $w['accountNumber'] ?? '']),
    ];
    if (!empty($w['txRef'])) $rows['Payment reference'] = (string) $w['txRef'];
    $rows['Request ID'] = (string) ($w['id'] ?? '');
    $body = chatlyMailP('Hello ' . chatlyMailEsc(chatlyMailFirstName($name)) . ', good news: your payout has been sent.')
        . chatlyMailInfoBox($rows, $profile)
        . chatlyMailP('Depending on your bank or wallet it can take a little while to show. If it has not arrived within a few hours, reply to this email with the payment reference.')
        . chatlyMailP('Thank you for your hard work. Keep closing!');
    $html = chatlyMailTemplate($profile, 'Your payout has been sent', $body, ['cta_text' => 'Open your workspace', 'cta_url' => CHATLY_MAIL_PORTAL_URL, 'preheader' => 'PKR ' . $amount . ' has been sent to you.']);
    return chatlySendMail($profile, $email, $name, 'Payout sent: PKR ' . $amount, $html, null, [
        'template_id' => 'auto_withdrawal_paid', 'sent_by' => (string) ($w['processed_by'] ?? 'System'), 'company_id' => (string) ($w['company_id'] ?? ''),
    ]);
}

/** Chatly: welcome email to the owner of a newly created company (no password is emailed). */
function chatlyMailAutoCompanyWelcome(array $company, string $adminUsername, string $createdBy = ''): array {
    $email = trim((string) ($company['owner_email'] ?? ''));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return ['ok' => false, 'error' => 'No valid owner email.'];
    $name = (string) ($company['owner_name'] ?? '');
    $cName = (string) ($company['name'] ?? 'your company');
    $loginUrl = CHATLY_MAIL_PORTAL_URL . '/cc';
    $body = chatlyMailP('Hello ' . chatlyMailEsc(chatlyMailFirstName($name)) . ', welcome to <strong>Chatly</strong>! Your company workspace for <strong>' . chatlyMailEsc($cName) . '</strong> is ready.')
        . chatlyMailInfoBox([
            'Company'          => $cName,
            'Admin portal'     => 'chatly.pk/cc',
            'Admin username'   => $adminUsername,
            'Password'         => 'The one the Chatly team set up with you',
        ], 'chatly')
        . chatlyMailP('For your security we never send passwords by email. If you do not have yours, reply to this email and we will help you.')
        . '<p style="margin:0 0 8px;font-weight:700;color:#0f172a;">Get started</p>'
        . '<ol style="margin:0 0 14px;padding-left:20px;">'
        . '<li style="margin:0 0 6px;">Sign in to your admin portal and add your products and prices.</li>'
        . '<li style="margin:0 0 6px;">Invite your sales closers (TMs) and team leaders (TLs), or approve the ones who apply.</li>'
        . '<li style="margin:0 0 6px;">Each closer links their own WhatsApp and starts replying to leads.</li>'
        . '</ol>';
    $html = chatlyMailTemplate('chatly', 'Welcome to Chatly, ' . $cName, $body, ['cta_text' => 'Open your admin portal', 'cta_url' => $loginUrl, 'preheader' => 'Your Chatly company workspace is ready.']);
    return chatlySendMail('chatly', $email, $name, 'Welcome to Chatly: ' . $cName . ' is ready', $html, null, [
        'template_id' => 'auto_company_welcome', 'sent_by' => $createdBy !== '' ? $createdBy : 'System', 'company_id' => (string) ($company['id'] ?? ''),
    ]);
}
