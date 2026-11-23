<?php
/**
 * Unofficial leads (Nunno only): a sale a Nunno TM / TL closed OUTSIDE Chatly (their own
 * WhatsApp, a call, in person) can be claimed here for commission.
 *
 * The claim becomes a normal "In review" sale in Nunno's chats, flagged unofficial, so the
 * existing pipeline does the rest: the admin portal lists it under deals awaiting
 * verification (tagged "Unofficial lead"), Verify credits it, and the earnings engine
 * (api/earnings.php) pays the commission of its plan. Reject drops it (Won't pay).
 *
 * Anti-fraud: the number must not already be a chat in Nunno, the transaction id must not
 * have been used on any other sale, and each person may have at most 20 claims in review.
 *
 *   GET  ?action=plans  -> the plans that can be claimed (Nunno's products + Nunno x Chatly plan)
 *   POST ?action=claim  {customer_name, phone, email, plan, method, trx_id, note}
 */

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/session_user.php';
require_once __DIR__ . '/tenant_manager.php';
require_once __DIR__ . '/lead_store.php';
require_once __DIR__ . '/earnings.php';

const UL_COMPANY = 'COMP-001';
const UL_MAX_IN_REVIEW = 20;

function ulOut(array $d, int $code = 200): void {
    http_response_code($code);
    echo json_encode($d, JSON_UNESCAPED_UNICODE);
    exit;
}

$me = chatlySessionUser();
if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
if (!$me) ulOut(['success' => false, 'error' => 'Please sign in.'], 401);
// the live record (role / status may have changed since sign-in)
$fresh = earningsFindUser((string) $me['id']);
if ($fresh) $me = array_merge($me, $fresh);
$role = strtolower((string) ($me['role'] ?? ''));
if (!in_array($role, ['member', 'lead'], true) || chatlyUserCompany($me) !== UL_COMPANY) {
    ulOut(['success' => false, 'error' => 'Unofficial lead claims are for Nunno Team Members and Team Leaders.'], 403);
}
if (strtolower((string) ($me['status'] ?? 'active')) !== 'active') {
    ulOut(['success' => false, 'error' => 'Your account is not active yet.'], 403);
}

/** name => [amount, commission, badge]: Nunno's products plus the Nunno x Chatly plan. */
function ulPlans(): array {
    $plans = [];
    $cfg = getTenantConfig(UL_COMPANY);
    foreach ((is_array($cfg['products'] ?? null) ? $cfg['products'] : []) as $p) {
        $name = trim((string) ($p['name'] ?? ''));
        $amount = (float) ($p['amount'] ?? 0);
        if ($name === '' || $amount <= 0) continue;
        $plans[$name] = ['name' => $name, 'amount' => $amount, 'commission' => (float) ($p['commission'] ?? 0), 'badge' => (string) ($p['badge'] ?? '📦')];
    }
    if (!$plans) {
        foreach ([['Nunno Starter', 1700, 700, '⚡'], ['Nunno Pro', 5000, 2000, '💎'], ['Whale VIP', 15000, 10000, '🐋']] as [$n, $a, $c, $b]) {
            $plans[$n] = ['name' => $n, 'amount' => $a, 'commission' => $c, 'badge' => $b];
        }
    }
    $plans[NUNNO_TL_PLAN_NAME] = ['name' => NUNNO_TL_PLAN_NAME, 'amount' => NUNNO_TL_PLAN_PRICE, 'commission' => NUNNO_TL_COMMISSION, 'badge' => '🤝'];
    return $plans;
}

function ulPhone(string $p): string {
    $d = chatlyStoreDigits($p);
    return (strlen($d) >= 10 && strlen($d) <= 15) ? $d : '';
}

$raw = (string) file_get_contents('php://input');
$in = json_decode($raw, true);
if (!is_array($in)) $in = [];
$action = (string) ($_GET['action'] ?? ($in['action'] ?? ''));

if ($action === 'plans') {
    ulOut(['success' => true, 'plans' => array_values(ulPlans())]);
}

if ($action !== 'claim') ulOut(['success' => false, 'error' => 'Unknown action.'], 400);
$ctype = strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? ($_SERVER['HTTP_CONTENT_TYPE'] ?? '')));
if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST' || strpos($ctype, 'application/json') === false) {
    ulOut(['success' => false, 'error' => 'Send this as a JSON POST.'], 405);
}

$name = mb_substr(trim(preg_replace('/\s+/', ' ', (string) ($in['customer_name'] ?? ''))), 0, 80);
$phone = ulPhone((string) ($in['phone'] ?? ''));
$email = strtolower(trim((string) ($in['email'] ?? '')));
$planName = trim((string) ($in['plan'] ?? ''));
$method = mb_substr(trim((string) ($in['method'] ?? 'EasyPaisa')), 0, 40) ?: 'EasyPaisa';
$trx = mb_substr(trim(preg_replace('/\s+/', '', (string) ($in['trx_id'] ?? ''))), 0, 64);
$note = mb_substr(trim((string) ($in['note'] ?? '')), 0, 500);

if ($name === '') ulOut(['success' => false, 'error' => "Enter the customer's name."], 400);
if ($phone === '') ulOut(['success' => false, 'error' => "Enter the customer's WhatsApp number (e.g. 03001234567)."], 400);
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) ulOut(['success' => false, 'error' => 'Enter the email the customer subscribed with.'], 400);
if (strlen($trx) < 4) ulOut(['success' => false, 'error' => 'Enter the transaction ID (TRX ID) of the payment.'], 400);
$plans = ulPlans();
if (!isset($plans[$planName])) ulOut(['success' => false, 'error' => 'Pick one of the plans in the list.'], 400);
$plan = $plans[$planName];

// Checks against every Nunno chat: the number is new to Chatly, the TRX id was never used,
// and this person does not have too many claims waiting.
$key9 = substr($phone, -9);
$myId = (string) $me['id'];
$inReview = 0;
foreach (chatlyLeadsAll(UL_COMPANY) as $l) {
    $lp = preg_replace('/\D+/', '', (string) ($l['phone'] ?? ''));
    if ($lp !== '' && substr($lp, -9) === $key9) {
        $mineToo = (string) ($l['ownerId'] ?? '') === $myId;
        ulOut(['success' => false, 'error' => $mineToo
            ? 'This number is already one of your chats in Chatly. Open the chat and use Mark Paid there instead.'
            : 'This number is already a chat in Chatly (another closer or a campaign). It cannot be claimed as an unofficial lead.'], 409);
    }
    $lt = strtolower(preg_replace('/\s+/', '', (string) ($l['paidDetails']['trxId'] ?? '')));
    if ($lt !== '' && $lt === strtolower($trx)) {
        ulOut(['success' => false, 'error' => 'This transaction ID was already used for another sale.'], 409);
    }
    if (!empty($l['unofficial']) && earningsSaleOwner($l) === $myId && earningsLeadState($l) === 'pending_verification') $inReview++;
}
if ($inReview >= UL_MAX_IN_REVIEW) {
    ulOut(['success' => false, 'error' => 'You already have ' . UL_MAX_IN_REVIEW . ' unofficial leads waiting for review. Wait for the admin to check them first.'], 429);
}

$now = time();
$closerName = (string) ($me['name'] ?? ($me['username'] ?? 'Closer'));
$lead = [
    'id' => 'lead_unoff_' . $now . '_' . $phone,
    'company_id' => UL_COMPANY,
    'name' => $name,
    'phone' => $phone,
    'display' => '+' . $phone,
    'email' => $email,
    'city' => 'Pakistan',
    'network' => 'Unofficial',
    'ownerId' => $myId,
    'ownerName' => $closerName,
    'ownerUsername' => (string) ($me['username'] ?? ''),
    'leadState' => 'pending_verification',
    'status' => 'pending_verification',
    'verificationStatus' => 'pending',
    'unofficial' => true,
    'interest' => 'Unofficial lead',
    'unreadCount' => 0,
    'lastActive' => 'Just now',
    'lastMessageAt' => $now,
    'notes' => 'Unofficial lead: closed outside Chatly and claimed by ' . $closerName . '.',
    'paidDetails' => [
        'plan' => $plan['name'], 'product' => $plan['name'], 'amount' => $plan['amount'], 'commission' => $plan['commission'],
        'method' => $method, 'email' => $email, 'trxId' => $trx, 'note' => $note, 'unofficial' => true,
        'closedBy' => $closerName, 'closedById' => $myId, 'closedAt' => date('c', $now),
    ],
    'messages' => [[
        'id' => 'unoff_' . $now, 'sender' => 'agent', 'agentName' => $closerName, 'timestamp' => $now, 'time' => date('H:i', $now),
        'text' => "📝 UNOFFICIAL LEAD CLAIMED (closed outside Chatly)\n• Customer: {$name} (+{$phone})\n• Subscriber email: {$email}\n• Plan: {$plan['name']} (Rs. " . number_format($plan['amount']) . ")\n• Method: {$method}\n• TRX ID: {$trx}" . ($note !== '' ? "\n• Note: {$note}" : '') . "\n• Claimed by: {$closerName}\n⏳ Waiting for the admin to verify the payment.",
    ]],
];

try {
    if (chatlyLeadStoreOn()) {
        chatlyStoreSaveLeads(UL_COMPANY, [$lead], 'insert');
    } else {
        $file = getTenantLeadsFile(UL_COMPANY);
        $fp = fopen($file . '.lock', 'c');
        if ($fp) flock($fp, LOCK_EX);
        try {
            $leads = json_decode((string) @file_get_contents($file), true);
            if (!is_array($leads)) $leads = [];
            array_unshift($leads, $lead);
            $json = json_encode($leads, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            $tmp = $file . '.tmp.' . uniqid('', true);
            if (@file_put_contents($tmp, $json) === false || !@rename($tmp, $file)) throw new RuntimeException('Could not save the claim.');
            $legacy = __DIR__ . '/../data/leads.json';
            if (is_file($legacy)) @file_put_contents($legacy, $json);   // Nunno's legacy mirror (see leads_sync.php)
        } finally {
            if ($fp) { flock($fp, LOCK_UN); fclose($fp); }
        }
    }
} catch (Throwable $e) {
    ulOut(['success' => false, 'error' => 'Could not save the claim: ' . $e->getMessage()], 502);
}

ulOut(['success' => true, 'message' => 'Unofficial lead submitted. The admin will verify the payment; once approved, the commission is added to your earnings.',
    'lead' => ['id' => $lead['id'], 'name' => $name, 'plan' => $plan['name'], 'amount' => $plan['amount'], 'commission' => $plan['commission']]]);
