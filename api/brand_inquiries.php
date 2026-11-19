<?php
/**
 * Chatly — Brand Product Promotion & Inquiries API
 * 
 * Handles:
 * 1. Public submission of product promotion inquiries from the homepage form.
 * 2. Super Admin (/ss) management, status tracking, notifications & 1-click company conversion.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 60 * 60 * 24 * 7,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');

$dataFile = __DIR__ . '/../data/brand_inquiries.json';

function getInquiries($file) {
    if (!file_exists($file)) {
        return [];
    }
    $raw = @file_get_contents($file);
    $data = @json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function saveInquiries($file, array $data) {
    @file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX);
}

$currentUser = $_SESSION['omnidesk_user'] ?? null;
$action = $_GET['action'] ?? ($_POST['action'] ?? '');

if (!$action && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw = file_get_contents('php://input');
    $body = json_decode($raw, true);
    if (!empty($body['action'])) {
        $action = $body['action'];
    }
}

switch ($action) {
    // 1. Submit New Product Promotion Inquiry (Public - Homepage Form)
    case 'submit':
        $raw = file_get_contents('php://input');
        $data = json_decode($raw, true) ?: $_POST;

        $brandName    = trim($data['brand_name'] ?? '');
        $productName  = trim($data['product_name'] ?? '');
        $category     = trim($data['category'] ?? 'E-Commerce & Retail');
        $price        = trim($data['price'] ?? '');
        $currency     = trim($data['currency'] ?? 'PKR');
        $description  = trim($data['description'] ?? ($data['pitch'] ?? ''));
        $contactName  = trim($data['contact_name'] ?? '');
        $phone        = trim($data['phone'] ?? ($data['whatsapp'] ?? ''));
        $email        = trim($data['email'] ?? '');
        $website      = trim($data['website'] ?? '');
        $targetVolume = trim($data['target_volume'] ?? '');
        $city         = trim($data['city'] ?? 'Pakistan');

        if (!$brandName || !$productName || !$contactName || !$phone) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error'   => 'Brand Name, Product Name, Contact Name, and WhatsApp Phone are required.'
            ]);
            exit;
        }

        $cleanPhone = preg_replace('/[^\d+]/', '', $phone);
        if (!$cleanPhone) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Please provide a valid WhatsApp phone number.']);
            exit;
        }

        $inquiries = getInquiries($dataFile);

        $newInquiry = [
            'id'            => 'INQ-' . time() . '-' . rand(100, 999),
            'brand_name'    => $brandName,
            'product_name'  => $productName,
            'category'      => $category,
            'price'         => $price ?: '5000',
            'currency'      => $currency,
            'description'   => $description,
            'contact_name'  => $contactName,
            'phone'         => $cleanPhone,
            'email'         => $email,
            'website'       => $website,
            'target_volume' => $targetVolume ?: '5-20 orders / day',
            'city'          => $city,
            'status'        => 'new', // new, contacted, converted, archived
            'is_read'       => false,
            'notes'         => '',
            'created_at'    => date('Y-m-d H:i:s'),
            'ip_address'    => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'
        ];

        // Add to beginning of array
        array_unshift($inquiries, $newInquiry);
        saveInquiries($dataFile, $inquiries);

        echo json_encode([
            'success' => true,
            'message' => 'Thank you! Your product promotion inquiry has been received. Our company will be in touch with you soon via WhatsApp.',
            'inquiry' => $newInquiry
        ]);
        exit;

    // 2. List Inquiries (Super Admin / Admin View in /ss)
    case 'list':
        if (!$currentUser || !in_array(($currentUser['role'] ?? ''), ['superadmin', 'admin'], true)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Super Administrator authentication required.']);
            exit;
        }

        $inquiries = getInquiries($dataFile);
        $total = count($inquiries);
        $newCount = 0;
        $contactedCount = 0;
        $convertedCount = 0;

        foreach ($inquiries as $inq) {
            $st = $inq['status'] ?? 'new';
            if ($st === 'new' || empty($inq['is_read'])) {
                $newCount++;
            }
            if ($st === 'contacted') {
                $contactedCount++;
            }
            if ($st === 'converted') {
                $convertedCount++;
            }
        }

        echo json_encode([
            'success'         => true,
            'inquiries'       => $inquiries,
            'total'           => $total,
            'new_count'       => $newCount,
            'contacted_count' => $contactedCount,
            'converted_count' => $convertedCount
        ]);
        exit;

    // 3. Update Status (Contacted / Converted / Archived)
    case 'update_status':
        if (!$currentUser || ($currentUser['role'] ?? '') !== 'superadmin') {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Super Admin access required.']);
            exit;
        }

        $raw = file_get_contents('php://input');
        $data = json_decode($raw, true) ?: $_POST;
        $id = trim($data['id'] ?? '');
        $newStatus = trim($data['status'] ?? '');
        $notes = trim($data['notes'] ?? '');

        if (!$id || !in_array($newStatus, ['new', 'contacted', 'converted', 'archived'], true)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Valid inquiry ID and status are required.']);
            exit;
        }

        $inquiries = getInquiries($dataFile);
        $found = false;

        foreach ($inquiries as &$inq) {
            if ($inq['id'] === $id) {
                $inq['status'] = $newStatus;
                $inq['is_read'] = true;
                if ($notes !== '') {
                    $inq['notes'] = $notes;
                }
                $inq['updated_at'] = date('Y-m-d H:i:s');
                $inq['updated_by'] = $currentUser['username'] ?? 'sadmin';
                $found = true;
                break;
            }
        }
        unset($inq);

        if (!$found) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Inquiry record not found.']);
            exit;
        }

        saveInquiries($dataFile, $inquiries);
        echo json_encode(['success' => true, 'message' => 'Inquiry status updated successfully.']);
        exit;

    // 4. Mark All Inquiries as Read
    case 'mark_all_read':
        if (!$currentUser || ($currentUser['role'] ?? '') !== 'superadmin') {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Super Admin access required.']);
            exit;
        }

        $inquiries = getInquiries($dataFile);
        foreach ($inquiries as &$inq) {
            $inq['is_read'] = true;
        }
        unset($inq);
        saveInquiries($dataFile, $inquiries);

        echo json_encode(['success' => true, 'message' => 'All inquiries marked as read.']);
        exit;

    // 5. Delete Inquiry
    case 'delete':
        if (!$currentUser || ($currentUser['role'] ?? '') !== 'superadmin') {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Super Admin access required.']);
            exit;
        }

        $raw = file_get_contents('php://input');
        $data = json_decode($raw, true) ?: $_POST;
        $id = trim($data['id'] ?? '');

        $inquiries = getInquiries($dataFile);
        $initialCount = count($inquiries);
        $inquiries = array_values(array_filter($inquiries, function($inq) use ($id) {
            return $inq['id'] !== $id;
        }));

        if (count($inquiries) === $initialCount) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Inquiry record not found.']);
            exit;
        }

        saveInquiries($dataFile, $inquiries);
        echo json_encode(['success' => true, 'message' => 'Inquiry deleted successfully.']);
        exit;

    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid action parameter.']);
        exit;
}
