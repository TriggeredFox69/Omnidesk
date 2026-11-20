<?php
/**
 * Withdrawals: Team Members / Team Leaders cash out what they have EARNED.
 *
 * Rules (all enforced here, not in the browser):
 *  - You must be signed in; you can only request for yourself.
 *  - The amount must be at least Rs. 500 and no more than your available balance
 *    (earned − paid out − pending), worked out by api/earnings.php.
 *  - Only an admin of your company (or the super admin) can mark a request
 *    Paid (with the payment reference) or Rejected (with a reason). A rejected
 *    request's amount returns to the balance. Paid requests cannot be changed.
 *  - Members see only their own requests; company admins see their company.
 */

header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');

require_once __DIR__ . '/earnings.php';

$wdFile = __DIR__ . '/../data/withdrawals.json';

function readWithdrawalsLocked($file, &$handle) {
    $handle = fopen($file, 'c+');
    if (!$handle) return [];
    flock($handle, LOCK_EX);
    $raw = stream_get_contents($handle);
    $data = json_decode($raw ?: '[]', true);
    return is_array($data) ? $data : [];
}

function writeWithdrawalsLocked($file, $handle, array $data) {
    $json = json_encode(array_values($data), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    rewind($handle);
    ftruncate($handle, 0);
    fwrite($handle, $json);
    fflush($handle);
    flock($handle, LOCK_UN);
    fclose($handle);
    try {
        $backupDir = dirname($file) . '/backups';
        if (!is_dir($backupDir)) @mkdir($backupDir, 0755, true);
        $todayBackup = $backupDir . '/withdrawals_' . date('Y-m-d') . '.json';
        if (!file_exists($todayBackup) || (time() - filemtime($todayBackup) > 3600)) @copy($file, $todayBackup);
    } catch (Throwable $e) {}
}

function wdFail(int $code, string $msg) {
    http_response_code($code);
    echo json_encode(['success' => false, 'error' => $msg]);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true) ?: [];
$action = $_GET['action'] ?? ($data['action'] ?? 'get_withdrawals');

$me = earningsSessionUser();
if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
if (!$me) wdFail(401, 'Please sign in to manage payouts.');
if (($me['status'] ?? '') === 'suspended') wdFail(403, 'Account access suspended. Payout transactions are locked.');

$myRole = strtolower((string) ($me['role'] ?? 'member'));
$isSuper = $myRole === 'superadmin';
$isCompanyAdmin = $myRole === 'admin';
$myCompany = (string) ($me['company_id'] ?? NUNNO_COMPANY_ID);

if ($action === 'get_withdrawals') {
    $all = earningsReadJson($wdFile);
    if ($isSuper) {
        $comp = $_GET['company_id'] ?? ($data['company_id'] ?? null);
        $rows = ($comp && $comp !== 'GLOBAL') ? array_filter($all, fn($w) => ($w['company_id'] ?? NUNNO_COMPANY_ID) === $comp) : $all;
    } elseif ($isCompanyAdmin) {
        $rows = array_filter($all, fn($w) => ($w['company_id'] ?? NUNNO_COMPANY_ID) === $myCompany);
    } else {
        $rows = array_filter($all, fn($w) => ($w['userId'] ?? '') === $me['id']);
    }
    $out = ['success' => true, 'withdrawals' => array_values($rows)];
    if (!$isSuper && !$isCompanyAdmin) $out['earnings'] = array_diff_key(computeEarnings($me['id']), ['deals' => 1]);
    echo json_encode($out);
    exit;
}

if ($action === 'request_withdrawal') {
    if ($isSuper || $isCompanyAdmin) wdFail(400, 'Admins do not request payouts here.');
    $amount = round((float) ($data['amount'] ?? 0));
    $method = trim((string) ($data['method'] ?? 'EasyPaisa'));
    $institution = trim((string) ($data['institution'] ?? $method));
    $accountTitle = trim((string) ($data['accountTitle'] ?? ''));
    $accountNumber = trim((string) ($data['accountNumber'] ?? ''));
    $note = substr(trim((string) ($data['note'] ?? '')), 0, 300);

    if ($amount < MIN_WITHDRAWAL) wdFail(400, 'The minimum withdrawal is Rs. ' . number_format(MIN_WITHDRAWAL) . '.');
    if ($accountTitle === '' || $accountNumber === '') wdFail(400, 'Account title and account number are required.');

    $walletKeys = ['easypaisa', 'jazzcash', 'sadapay', 'nayapay', 'upaisa', 'zindigi', 'digital wallet'];
    if (in_array(strtolower($method), $walletKeys, true) || in_array(strtolower($institution), $walletKeys, true)) {
        $clean = preg_replace('/[^\d\+]/', '', $accountNumber);
        if (!preg_match('/^0[0-9]{10}$/', $clean) && !preg_match('/^\+?92[0-9]{10}$/', $clean)) {
            wdFail(400, 'For mobile wallets enter 11 digits starting with 0 (e.g. 03001234567) or +92 followed by 10 digits.');
        }
        $accountNumber = $clean;
    }

    $all = readWithdrawalsLocked($wdFile, $h);
    $earn = computeEarnings($me['id'], null, null, $all);   // balance checked against the locked file
    if ($amount > $earn['available']) {
        flock($h, LOCK_UN); fclose($h);
        wdFail(400, 'You can withdraw up to Rs. ' . number_format($earn['available']) . ' right now (earned Rs. ' . number_format($earn['total_earned']) . ', already paid Rs. ' . number_format($earn['paid_out']) . ', pending Rs. ' . number_format($earn['pending_withdrawals']) . ').');
    }
    $companyName = 'Company';
    foreach (earningsReadJson(__DIR__ . '/../data/companies.json') as $c) if (($c['id'] ?? '') === $myCompany) $companyName = $c['name'] ?? $companyName;

    $new = [
        'id' => 'WD-' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(3))),
        'userId' => $me['id'],
        'userName' => $me['name'] ?? '',
        'userRole' => $me['role'] ?? 'member',
        'company_id' => $myCompany,
        'company_name' => $companyName,
        'amount' => $amount,
        'method' => $method,
        'institution' => $institution,
        'accountTitle' => $accountTitle,
        'accountNumber' => $accountNumber,
        'note' => $note,
        'status' => 'pending',
        'balance_at_request' => $earn['available'],
        'created_at' => date('c'),
    ];
    array_unshift($all, $new);
    writeWithdrawalsLocked($wdFile, $h, $all);
    $after = computeEarnings($me['id']);
    unset($after['deals']);
    echo json_encode(['success' => true, 'withdrawal' => $new, 'earnings' => $after,
        'withdrawals' => array_values(array_filter($all, fn($w) => ($w['userId'] ?? '') === $me['id']))]);
    exit;
}

if ($action === 'update_status') {
    if (!$isSuper && !$isCompanyAdmin) wdFail(403, 'Only company administrators can process payouts.');
    $id = (string) ($data['id'] ?? '');
    $status = (string) ($data['status'] ?? '');
    $txRef = trim((string) ($data['txRef'] ?? ''));
    $reason = substr(trim((string) ($data['reason'] ?? '')), 0, 300);
    if ($id === '' || !in_array($status, ['approved', 'rejected'], true)) wdFail(400, 'Invalid parameters.');
    if ($status === 'approved' && $txRef === '') wdFail(400, 'Enter the payment reference (transaction ID) you sent the money with.');

    $all = readWithdrawalsLocked($wdFile, $h);
    $found = false;
    foreach ($all as &$w) {
        if (($w['id'] ?? '') !== $id) continue;
        $found = true;
        if (!$isSuper && ($w['company_id'] ?? NUNNO_COMPANY_ID) !== $myCompany) { flock($h, LOCK_UN); fclose($h); wdFail(403, 'This request belongs to another company.'); }
        if (($w['status'] ?? '') !== 'pending') { flock($h, LOCK_UN); fclose($h); wdFail(409, 'This request was already ' . ($w['status'] === 'approved' ? 'paid' : $w['status']) . '.'); }
        $w['status'] = $status;
        if ($txRef !== '') $w['txRef'] = $txRef;
        if ($reason !== '') $w['reason'] = $reason;
        $w['processed_by'] = $me['name'] ?? $me['id'];
        $w['processed_at'] = date('c');
        $w['updated_at'] = date('c');
        break;
    }
    unset($w);
    if (!$found) { flock($h, LOCK_UN); fclose($h); wdFail(404, 'Withdrawal request not found.'); }
    writeWithdrawalsLocked($wdFile, $h, $all);
    $visible = $isSuper ? $all : array_values(array_filter($all, fn($w) => ($w['company_id'] ?? NUNNO_COMPANY_ID) === $myCompany));
    echo json_encode(['success' => true, 'withdrawals' => $visible]);
    exit;
}

wdFail(400, 'Invalid action.');
