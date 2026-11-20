<?php
/**
 * Maintenance endpoints (deploy, restart, wipe WhatsApp session) used to be
 * open to anyone with the URL, so a crawler or a stranger could kill the
 * WhatsApp bridge or delete its login. They now need a signed-in admin
 * (or the command line).
 */
function requireBridgeAdmin(): void {
    if (PHP_SAPI === 'cli') return;
    if (session_status() === PHP_SESSION_NONE) {
        @session_start();
    }
    $user = $_SESSION['omnidesk_user'] ?? null;
    $role = strtolower((string) ($user['role'] ?? ''));
    // Anonymous visitors get an automatic "System Administrator" session in the
    // WhatsApp endpoints (flagged 'auto', and it has no created_at); that never counts.
    $autoSession = !empty($user['auto']) || (($user['id'] ?? '') === 'USR-ADMIN-01' && !isset($user['created_at']));
    $ok = is_array($user) && !empty($user['id']) && !$autoSession
        && in_array($role, ['admin', 'superadmin', 'master admin'], true);
    if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
    if (!$ok) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Administrator sign-in required.']);
        exit;
    }
}
