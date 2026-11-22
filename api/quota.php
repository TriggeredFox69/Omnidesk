<?php
/**
 * Daily message counters ("messages_sent_today" + "quota_reset_date" on each user).
 *
 * One day = one calendar day in Pakistan (Asia/Karachi), so every closer's count and
 * daily limit start again at 12:00 AM PKT, whatever timezone the server runs in.
 *
 * Rules every reader and writer follows:
 *  - a count whose quota_reset_date is not today belongs to an earlier day and counts as 0;
 *  - a writer first rolls such a count over to today (0), then adds to it.
 * (Before, a count was only reset when someone opened a certain admin page, and that reset
 * also wiped messages already sent that day, so some closers showed far fewer sends.)
 */

const CHATLY_QUOTA_TZ = 'Asia/Karachi';

function chatlyQuotaNow(): DateTimeImmutable {
    return new DateTimeImmutable('now', new DateTimeZone(CHATLY_QUOTA_TZ));
}

/** Today's date in Pakistan, Y-m-d. */
function chatlyQuotaToday(): string {
    return chatlyQuotaNow()->format('Y-m-d');
}

/** Seconds until the next 12:00 AM in Pakistan, when every daily count starts again. */
function chatlyQuotaSecondsToReset(): int {
    $now = chatlyQuotaNow();
    return max(0, $now->modify('tomorrow')->getTimestamp() - $now->getTimestamp());
}

/** Today's sends for a user (0 when the stored count is from an earlier day). */
function chatlyQuotaSentToday(array $u): int {
    return (($u['quota_reset_date'] ?? '') === chatlyQuotaToday()) ? (int) ($u['messages_sent_today'] ?? 0) : 0;
}

/** Moves a count from an earlier day to today (0). Returns true when it changed. */
function chatlyQuotaRoll(array &$u): bool {
    $today = chatlyQuotaToday();
    if (($u['quota_reset_date'] ?? '') === $today) return false;
    $u['messages_sent_today'] = 0;
    $u['quota_reset_date'] = $today;
    return true;
}

/** limit / sent / remaining / percent / resets_in for one user. */
function chatlyQuotaMetrics(array $u): array {
    $limit = (int) ($u['message_limit'] ?? 100);
    $sent = chatlyQuotaSentToday($u);
    return [
        'limit'     => $limit,
        'sent'      => $sent,
        'remaining' => ($limit < 0) ? 'Unlimited' : max(0, $limit - $sent),
        'percent'   => ($limit <= 0) ? 0 : min(100, (int) round(($sent / $limit) * 100)),
        'reached'   => $limit > 0 && $sent >= $limit,
        'resets_in' => chatlyQuotaSecondsToReset(),
    ];
}

/**
 * Locked read-modify-write of users.json, so two requests saving at the same time can't
 * overwrite each other's changes. $fn gets the users array by reference and returns false to
 * skip saving. Returns $fn's result, or null when users.json could not be read (it is then
 * left untouched).
 */
function chatlyUsersUpdate(string $usersFile, callable $fn) {
    $lock = @fopen($usersFile . '.lock', 'c');
    if ($lock) flock($lock, LOCK_EX);
    try {
        $users = json_decode((string) @file_get_contents($usersFile), true);
        if (!is_array($users)) return null;
        $result = $fn($users);
        if ($result !== false) {
            $tmp = $usersFile . '.tmp.' . uniqid('', true);
            if (@file_put_contents($tmp, json_encode($users, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) !== false) {
                if (!@rename($tmp, $usersFile)) @unlink($tmp);
            }
        }
        return $result;
    } finally {
        if ($lock) { flock($lock, LOCK_UN); fclose($lock); }
    }
}

/** Adds $n to a user's count for today. Returns the user's new metrics, or null if not found. */
function chatlyQuotaAdd(string $usersFile, string $userId, int $n = 1): ?array {
    $out = null;
    chatlyUsersUpdate($usersFile, function (array &$users) use ($userId, $n, &$out) {
        foreach ($users as &$u) {
            if (($u['id'] ?? '') !== $userId) continue;
            chatlyQuotaRoll($u);
            $u['messages_sent_today'] = (int) ($u['messages_sent_today'] ?? 0) + $n;
            $out = chatlyQuotaMetrics($u);
            return true;
        }
        return false;
    });
    return $out;
}
