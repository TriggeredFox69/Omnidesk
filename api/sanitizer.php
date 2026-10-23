<?php
/**
 * Nunno WhatsApp Number Sanitizer & Validator
 * Optimized specifically for Pakistani Mobile Networks
 */

header('Content-Type: application/json');
$config = require __DIR__ . '/config.php';

// Helper: Normalize single phone number
function normalize_pk_number($raw) {
    // Remove all non-digits
    $clean = preg_replace('/\D+/', '', (string)$raw);
    
    // Check if empty
    if (empty($clean)) return null;

    // Handle leading zeros (e.g., 0092300... -> 92300...)
    if (strpos($clean, '0092') === 0) {
        $clean = substr($clean, 2);
    }
    // Handle local format (e.g., 03001234567 -> 923001234567)
    elseif (strpos($clean, '03') === 0 && strlen($clean) === 11) {
        $clean = '92' . substr($clean, 1);
    }
    // Handle format without country code or zero (e.g., 3001234567 -> 923001234567)
    elseif (strpos($clean, '3') === 0 && strlen($clean) === 10) {
        $clean = '92' . $clean;
    }
    // Handle international without plus (e.g., 923001234567 -> 12 digits)
    elseif (strpos($clean, '923') === 0 && strlen($clean) === 12) {
        // Valid length
    } else {
        // Unrecognized format
        return null;
    }

    // Must be exactly 12 digits and start with 923
    if (strlen($clean) === 12 && strpos($clean, '923') === 0) {
        return $clean;
    }

    return null;
}

// Helper: Detect Network Operator
function detect_network($cleanNumber, $networksMap) {
    if (!$cleanNumber || strlen($cleanNumber) !== 12) return 'Unknown';
    // Local prefix e.g. 92300 -> 0300
    $localPrefix = '0' . substr($cleanNumber, 2, 3);
    foreach ($networksMap as $network => $prefixes) {
        if (in_array($localPrefix, $prefixes)) {
            return $network;
        }
    }
    return 'Other';
}

// Load Blacklist
$blacklistFile = __DIR__ . '/../data/blacklist.json';
$blacklist = [];
if (file_exists($blacklistFile)) {
    $rawBl = file_get_contents($blacklistFile);
    $decodedBl = json_decode($rawBl, true);
    if (is_array($decodedBl)) {
        $blacklist = $decodedBl;
    }
}

// Read Input
$inputData = file_get_contents('php://input');
$data = json_decode($inputData, true);

if (!$data) {
    $data = $_POST;
}

$rawContacts = $data['contacts'] ?? [];
$rawText = $data['raw_text'] ?? '';

// If raw_text provided, parse line by line or comma-separated
if (!empty($rawText) && empty($rawContacts)) {
    $lines = preg_split('/[\r\n,]+/', $rawText);
    foreach ($lines as $line) {
        $line = trim($line);
        if (!empty($line)) {
            $rawContacts[] = ['phone' => $line];
        }
    }
}

if (empty($rawContacts) && !is_array($rawContacts)) {
    echo json_encode([
        'success' => false,
        'message' => 'No contacts provided for sanitization.'
    ]);
    exit;
}

$sanitized = [];
$duplicatesCount = 0;
$blacklistedCount = 0;
$invalidCount = 0;
$seenNumbers = [];
$networkStats = [
    'Jazz'    => 0,
    'Telenor' => 0,
    'Zong'    => 0,
    'Ufone'   => 0,
    'SCOM'    => 0,
    'Other'   => 0
];

foreach ($rawContacts as $item) {
    $rawPhone = is_array($item) ? ($item['phone'] ?? $item['number'] ?? '') : (string)$item;
    $name     = is_array($item) ? ($item['name'] ?? 'Trader') : 'Trader';
    $city     = is_array($item) ? ($item['city'] ?? 'Pakistan') : 'Pakistan';
    $custom   = is_array($item) ? ($item['custom'] ?? '') : '';

    $clean = normalize_pk_number($rawPhone);
    if (!$clean) {
        $invalidCount++;
        continue;
    }

    if (isset($seenNumbers[$clean])) {
        $duplicatesCount++;
        continue;
    }

    if (in_array($clean, $blacklist)) {
        $blacklistedCount++;
        continue;
    }

    $seenNumbers[$clean] = true;
    $network = detect_network($clean, $config['pakistan_networks']);
    if (isset($networkStats[$network])) {
        $networkStats[$network]++;
    } else {
        $networkStats['Other']++;
    }

    $sanitized[] = [
        'id'          => 'c_' . substr(md5($clean . microtime()), 0, 8),
        'phone'       => $clean,
        'local_phone' => '0' . substr($clean, 2),
        'display'     => '+92 ' . substr($clean, 2, 3) . ' ' . substr($clean, 5),
        'name'        => trim($name),
        'city'        => trim($city),
        'network'     => $network,
        'status'      => 'ready', // ready, sent, failed, skipped
        'sent_at'     => null,
        'custom'      => $custom
    ];
}

// Optionally save to contacts storage
if (!empty($data['save_to_session']) || !empty($data['save_to_file'])) {
    $contactsFile = __DIR__ . '/../data/active_audience.json';
    file_put_contents($contactsFile, json_encode($sanitized, JSON_PRETTY_PRINT));
}

echo json_encode([
    'success' => true,
    'summary' => [
        'total_submitted' => count($rawContacts),
        'valid_unique'    => count($sanitized),
        'duplicates'      => $duplicatesCount,
        'blacklisted'     => $blacklistedCount,
        'invalid'         => $invalidCount,
        'network_breakdown' => $networkStats
    ],
    'contacts' => $sanitized
], JSON_PRETTY_PRINT);
