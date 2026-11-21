<?php
/**
 * The signed-in Chatly user, or null.
 *
 * WhatsApp endpoints used to give every anonymous visitor an automatic
 * "System Administrator" session (flagged 'auto', no created_at). That let anyone on
 * the internet read every company's chats and start campaigns. Such sessions are
 * never treated as signed in, and are removed when seen.
 */
function chatlySessionUser(): ?array {
    if (PHP_SAPI === 'cli') return null;
    if (session_status() === PHP_SESSION_NONE) @session_start();
    $u = $_SESSION['omnidesk_user'] ?? null;
    if (!is_array($u) || empty($u['id'])) return null;
    if (!empty($u['auto']) || (($u['id'] ?? '') === 'USR-ADMIN-01' && !isset($u['created_at']) && empty($u['last_login']))) {
        unset($_SESSION['omnidesk_user']);
        return null;
    }
    if (strtolower((string) ($u['status'] ?? 'active')) === 'suspended') return $u;
    return $u;
}

function chatlyRole(?array $u): string {
    return strtolower((string) ($u['role'] ?? ''));
}

function chatlyIsSuperAdmin(?array $u): bool {
    return chatlyRole($u) === 'superadmin' || (($u['company_id'] ?? '') === 'GLOBAL');
}

function chatlyIsCompanyAdmin(?array $u): bool {
    return in_array(chatlyRole($u), ['admin', 'master admin'], true);
}

/** Company of a user; legacy accounts without one belong to Nunno (COMP-001). */
function chatlyUserCompany(?array $u): string {
    $c = preg_replace('/[^A-Za-z0-9_-]/', '', (string) ($u['company_id'] ?? ''));
    return ($c && $c !== 'GLOBAL') ? $c : 'COMP-001';
}

/** Stop with 401 unless someone is signed in. Returns the user. */
function chatlyRequireUser(): array {
    $u = chatlySessionUser();
    if (!$u) {
        if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Please sign in.', 'message' => 'Please sign in.', 'authenticated' => false]);
        exit;
    }
    return $u;
}
