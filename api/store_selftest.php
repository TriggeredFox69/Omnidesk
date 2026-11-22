<?php
/**
 * Command-line check of the PHP chat store against Supabase (never reachable from the web).
 *   php /home/dailygol/chatly.pk/api/store_selftest.php
 * Uses a throwaway company (ZZ-PHP-SELFTEST) and deletes its leads afterwards.
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/lead_store.php';

$co = 'ZZ-PHP-SELFTEST';
$ok = 0; $fail = 0;
function check(string $what, bool $cond) { global $ok, $fail; if ($cond) { $ok++; echo "  ok   $what\n"; } else { $fail++; echo "  FAIL $what\n"; } }

echo "Supabase configured: " . (chatlySupabaseConfig() ? 'yes' : 'NO (data/supabase.php)') . "\n";
echo "Chats stored in Supabase (switch on): " . (chatlyLeadStoreOn() ? 'yes' : 'no') . "\n";
if (!chatlySupabaseConfig()) exit(1);
$t = microtime(true);
try {
    chatlyStoreSaveLeads($co, [[
        'id' => 'ST1', 'phone' => '03005550001', 'name' => 'Self Test', 'ownerId' => 'TM-ST', 'leadState' => 'fresh', 'unreadCount' => 0, 'lastMessageAt' => 100,
        'messages' => [['id' => 's1', 'waMessageId' => 'STWA1', 'sender' => 'agent', 'text' => 'hello', 'timestamp' => 100, 'deliveryStatus' => 'accepted']],
    ]]);
    $page = chatlyStoreLeadsPage($co, 0, 100, 12, true, '', '');
    check('save + first page', count($page['leads'] ?? []) === 1 && ($page['leads'][0]['messageCount'] ?? 0) === 1);
    $n = chatlyStoreAddMessages($co, [['phone' => '923005550001', 'msg' => ['id' => 's2', 'waMessageId' => 'STWA2', 'sender' => 'lead', 'text' => 'reply', 'timestamp' => 200], 'touch' => true]]);
    check('reply stored', $n === 1);
    $delta = chatlyStoreLeadsPage($co, (int) $page['rev'], 100, 12, true, '', '');
    $l = $delta['leads'][0] ?? [];
    check('delta has the change (unread 1, ongoing)', count($delta['leads']) === 1 && ($l['unreadCount'] ?? 0) === 1 && ($l['leadState'] ?? '') === 'ongoing');
    chatlyStoreSetStatus($co, 'STWA1', 'read');
    $msgs = chatlyStoreMessages($co, '923005550001');
    check('history + tick', count($msgs) === 2 && ($msgs[0]['deliveryStatus'] ?? '') === 'read');
    $p = chatlyStorePatchLead($co, '', '03005550001', ['unreadCount' => 0, 'notes' => 'patched']);
    check('patch by number', ($p['unreadCount'] ?? 1) === 0 && ($p['notes'] ?? '') === 'patched');
    check('find', (chatlyStoreFindLead($co, 'ST1')['name'] ?? '') === 'Self Test' && chatlyStoreFindLead($co, 'nope') === null);
    check('lead number index', isset(chatlyLeadKeyIndex($co)['005550001']));
    check('closer sees own lead only', count(chatlyStoreLeadsPage($co, 0, 100, 0, false, 'TM-ST', '')['leads']) === 1
        && count(chatlyStoreLeadsPage($co, 0, 100, 0, false, 'TM-OTHER', '')['leads']) === 0);
    check('delete', chatlyStoreDeleteLeads($co, ['ST1'], []) === 1 && chatlyStoreFindLead($co, 'ST1') === null);
} catch (Throwable $e) {
    $fail++;
    echo "  FAIL " . $e->getMessage() . "\n";
}
printf("%d ok, %d failed (%.0f ms, about %.0f ms per call)\n", $ok, $fail, (microtime(true) - $t) * 1000, (microtime(true) - $t) * 1000 / 13);
exit($fail ? 1 : 0);
