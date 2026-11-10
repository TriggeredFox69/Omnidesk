<?php
/**
 * Nunno WhatsApp — Centralized Withdrawal & Payout Management
 * Handles payout requests for Team Leads (TL) and Team Members (TM)
 */

header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');

session_start();

$wdFile = __DIR__ . '/../data/withdrawals.json';

function getStoredWithdrawals($file) {
    if (file_exists($file)) {
        $content = file_get_contents($file);
        $decoded = json_decode($content, true);
        if (is_array($decoded)) return $decoded;
    }
    return [];
}

function saveStoredWithdrawals($file, $data) {
    $temp = $file . '.tmp.' . uniqid();
    file_put_contents($temp, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    rename($temp, $file);

    try {
        $backupDir = dirname($file) . '/backups';
        if (!is_dir($backupDir)) {
            @mkdir($backupDir, 0755, true);
        }
        $todayBackup = $backupDir . '/withdrawals_' . date('Y-m-d') . '.json';
        if (!file_exists($todayBackup) || (time() - filemtime($todayBackup) > 3600)) {
            @copy($file, $todayBackup);
        }
    } catch (Exception $e) {}
}

$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true) ?: [];
$action = isset($_GET['action']) ? $_GET['action'] : (isset($data['action']) ? $data['action'] : 'get_withdrawals');

$currentUser = $_SESSION['omnidesk_user'] ?? $_SESSION['user'] ?? null;
$isAdmin = $currentUser && isset($currentUser['role']) && $currentUser['role'] === 'admin';

// Guard: Suspended closer cannot perform withdrawal transactions
if ($currentUser && isset($currentUser['status']) && $currentUser['status'] === 'suspended') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Account access suspended. Payout transactions are locked.']);
    exit;
}

if ($action === 'get_withdrawals') {
    $withdrawals = getStoredWithdrawals($wdFile);
    $targetUser = isset($_GET['user_id']) ? $_GET['user_id'] : (isset($data['user_id']) ? $data['user_id'] : null);
    
    // If not admin, restrict to their own requests
    if (!$isAdmin && $targetUser) {
        $withdrawals = array_values(array_filter($withdrawals, function($w) use ($targetUser) {
            return isset($w['userId']) && $w['userId'] === $targetUser;
        }));
    }
    
    echo json_encode([
        'success' => true,
        'withdrawals' => $withdrawals
    ]);
    exit;
}

if ($action === 'request_withdrawal') {
    $amount = isset($data['amount']) ? floatval($data['amount']) : 0;
    if ($amount <= 0) {
        echo json_encode(['success' => false, 'error' => 'Please enter a valid withdrawal amount.']);
        exit;
    }
    
    $method = isset($data['method']) ? trim($data['method']) : 'EasyPaisa';
    $institution = isset($data['institution']) ? trim($data['institution']) : $method;
    $accountTitle = isset($data['accountTitle']) ? trim($data['accountTitle']) : '';
    $accountNumber = isset($data['accountNumber']) ? trim($data['accountNumber']) : '';
    $note = isset($data['note']) ? trim($data['note']) : '';
    
    if (empty($accountTitle) || empty($accountNumber)) {
        echo json_encode(['success' => false, 'error' => 'Account title and account number are required.']);
        exit;
    }

    // Pakistani Digital Wallet validation (11 digits starting with 0, or 12 digits with +92)
    $walletKeys = ['easypaisa', 'jazzcash', 'sadapay', 'nayapay', 'upaisa', 'zindigi', 'digital wallet'];
    $isWallet = in_array(strtolower($method), $walletKeys) || in_array(strtolower($institution), $walletKeys);
    if ($isWallet) {
        $cleanPhone = preg_replace('/[^\d\+]/', '', $accountNumber);
        $isValidWallet = false;
        if (preg_match('/^0[0-9]{10}$/', $cleanPhone)) {
            $isValidWallet = true;
        } elseif (preg_match('/^\+?92[0-9]{10}$/', $cleanPhone)) {
            $isValidWallet = true;
        }
        if (!$isValidWallet) {
            echo json_encode([
                'success' => false,
                'error' => 'Technical Validation Error: For digital wallets (' . htmlspecialchars($institution) . '), enter 11 digits starting with 0 (e.g. 03001234567) or 12 digits with country code (+923001234567).'
            ]);
            exit;
        }
        $accountNumber = $cleanPhone;
    }
    
    $userId = isset($data['userId']) ? $data['userId'] : ($currentUser ? $currentUser['id'] : 'EMP-TL-01');
    $userName = isset($data['userName']) ? $data['userName'] : ($currentUser ? $currentUser['name'] : 'Team Member');
    $userRole = isset($data['userRole']) ? $data['userRole'] : ($currentUser ? $currentUser['role'] : 'lead');
    
    $withdrawals = getStoredWithdrawals($wdFile);
    
    $newWd = [
        'id' => 'WD-' . substr(strval(time()), -5) . rand(10, 99),
        'userId' => $userId,
        'userName' => $userName,
        'userRole' => $userRole,
        'amount' => $amount,
        'method' => $method,
        'institution' => $institution,
        'accountTitle' => $accountTitle,
        'accountNumber' => $accountNumber,
        'note' => $note,
        'status' => 'pending',
        'created_at' => date('c')
    ];
    
    array_unshift($withdrawals, $newWd);
    saveStoredWithdrawals($wdFile, $withdrawals);
    
    echo json_encode([
        'success' => true,
        'withdrawal' => $newWd,
        'withdrawals' => $withdrawals
    ]);
    exit;
}

if ($action === 'update_status') {
    $id = isset($data['id']) ? $data['id'] : '';
    $status = isset($data['status']) ? $data['status'] : '';
    $txRef = isset($data['txRef']) ? trim($data['txRef']) : '';
    
    if (empty($id) || !in_array($status, ['approved', 'rejected', 'pending'])) {
        echo json_encode(['success' => false, 'error' => 'Invalid parameters.']);
        exit;
    }
    
    $withdrawals = getStoredWithdrawals($wdFile);
    $found = false;
    
    foreach ($withdrawals as &$w) {
        if (isset($w['id']) && $w['id'] === $id) {
            $w['status'] = $status;
            if (!empty($txRef)) {
                $w['txRef'] = $txRef;
            }
            $w['updated_at'] = date('c');
            $found = true;
            break;
        }
    }
    
    if ($found) {
        saveStoredWithdrawals($wdFile, $withdrawals);
        echo json_encode(['success' => true, 'withdrawals' => $withdrawals]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Withdrawal request not found.']);
    }
    exit;
}

echo json_encode(['success' => false, 'error' => 'Invalid action.']);

