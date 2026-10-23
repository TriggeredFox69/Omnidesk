<?php
/**
 * Nunno Omnidesk — Role-Based Authentication & Employee Provisioning System
 * 
 * Rules:
 * 1. Only authenticated Administrators can register/provision new Employee accounts.
 * 2. Public signups are strictly prohibited.
 * 3. Employee sessions are tracked so lead handling & outreach are attributed to the active closer.
 */

if (session_status() === PHP_SESSION_NONE) {
    // Set secure cookie parameters where possible
    $lifetime = 60 * 60 * 24 * 7; // 7 days
    session_set_cookie_params([
        'lifetime' => $lifetime,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');

$usersFile = __DIR__ . '/../data/users.json';

// Initialize users storage with Master Admin & sample Closer if file does not exist
function initUsersStorage($usersFile) {
    if (!file_exists($usersFile)) {
        $defaultUsers = [
            [
                'id'            => 'USR-ADMIN-01',
                'name'          => 'System Administrator',
                'username'      => 'admin',
                'email'         => 'admin@nunno.cloud',
                'password_hash' => password_hash('Admin@Omnidesk2026!', PASSWORD_BCRYPT),
                'role'          => 'admin', // 'admin' | 'employee'
                'designation'   => 'Master Administrator',
                'phone'         => '',
                'status'        => 'active', // 'active' | 'suspended'
                'created_at'    => date('Y-m-d H:i:s'),
                'last_login'    => null
            ],
            [
                'id'            => 'EMP-101',
                'name'          => 'Hamza Khan',
                'username'      => 'hamza',
                'email'         => 'hamza@nunno.cloud',
                'password_hash' => password_hash('Agent@123', PASSWORD_BCRYPT),
                'role'          => 'employee',
                'designation'   => 'Senior Closer',
                'phone'         => '+923001234567',
                'status'        => 'active',
                'created_at'    => date('Y-m-d H:i:s'),
                'last_login'    => null
            ]
        ];

        $dir = dirname($usersFile);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        file_put_contents($usersFile, json_encode($defaultUsers, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
}

// Read all users
function getUsers($usersFile) {
    initUsersStorage($usersFile);
    $content = @file_get_contents($usersFile);
    $decoded = json_decode($content, true);
    return is_array($decoded) ? $decoded : [];
}

// Save users safely
function saveUsers($usersFile, $users) {
    $tempFile = $usersFile . '.tmp.' . uniqid();
    file_put_contents($tempFile, json_encode($users, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    rename($tempFile, $usersFile);
}

// Strip password_hash before returning to client
function sanitizeUser($user) {
    if (!is_array($user)) return null;
    unset($user['password_hash']);
    return $user;
}

// Retrieve currently authenticated user
function getCurrentUser($usersFile) {
    if (isset($_SESSION['omnidesk_user']) && is_array($_SESSION['omnidesk_user'])) {
        $userId = $_SESSION['omnidesk_user']['id'] ?? '';
        $users = getUsers($usersFile);
        foreach ($users as $u) {
            if ($u['id'] === $userId && $u['status'] === 'active') {
                return sanitizeUser($u);
            }
        }
    }
    return null;
}

// Process request
$action = $_GET['action'] ?? $_POST['action'] ?? '';
if (!$action) {
    $rawInput = file_get_contents('php://input');
    if ($rawInput) {
        $json = json_decode($rawInput, true);
        if (is_array($json)) {
            $action = $json['action'] ?? '';
        }
    }
}

$currentUser = getCurrentUser($usersFile);

switch ($action) {

    // 1. Check current session
    case 'check':
        if ($currentUser) {
            echo json_encode([
                'authenticated' => true,
                'user'          => $currentUser
            ]);
        } else {
            echo json_encode([
                'authenticated' => false,
                'user'          => null
            ]);
        }
        exit;

    // 2. Login (Admin or Employee)
    case 'login':
        $raw = file_get_contents('php://input');
        $data = json_decode($raw, true) ?: $_POST;

        $username = trim($data['username'] ?? '');
        $password = trim($data['password'] ?? '');

        if (!$username || !$password) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error'   => 'Please enter both username and password.'
            ]);
            exit;
        }

        $users = getUsers($usersFile);
        $matchedUser = null;
        $userIndex = -1;

        foreach ($users as $idx => $u) {
            if (strcasecmp($u['username'], $username) === 0 || strcasecmp($u['email'] ?? '', $username) === 0) {
                $matchedUser = $u;
                $userIndex = $idx;
                break;
            }
        }

        if (!$matchedUser || !password_verify($password, $matchedUser['password_hash'])) {
            http_response_code(401);
            echo json_encode([
                'success' => false,
                'error'   => 'Invalid credentials. Please verify username and password.'
            ]);
            exit;
        }

        if ($matchedUser['status'] !== 'active') {
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'error'   => 'Your account has been deactivated. Please contact the Administrator.'
            ]);
            exit;
        }

        // Update last login timestamp
        $matchedUser['last_login'] = date('Y-m-d H:i:s');
        $users[$userIndex] = $matchedUser;
        saveUsers($usersFile, $users);

        $safeUser = sanitizeUser($matchedUser);
        session_regenerate_id(true);
        $_SESSION['omnidesk_user'] = $safeUser;

        echo json_encode([
            'success' => true,
            'message' => 'Logged in successfully.',
            'user'    => $safeUser
        ]);
        exit;

    // 3. Logout
    case 'logout':
        $_SESSION = [];
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        session_destroy();
        echo json_encode([
            'success' => true,
            'message' => 'Logged out successfully.'
        ]);
        exit;

    // 4. Create Employee (ADMIN ONLY)
    case 'create_employee':
        if (!$currentUser || $currentUser['role'] !== 'admin') {
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'error'   => 'Access Denied: Only the Administrator can create employee accounts. Public signups are prohibited.'
            ]);
            exit;
        }

        $raw = file_get_contents('php://input');
        $data = json_decode($raw, true) ?: $_POST;

        $name = trim($data['name'] ?? '');
        $empUsername = strtolower(trim($data['username'] ?? ''));
        $empPassword = trim($data['password'] ?? '');
        $designation = trim($data['designation'] ?? 'Sales Closer');
        $empRole = ($data['role'] ?? 'employee') === 'admin' ? 'admin' : 'employee';
        $email = trim($data['email'] ?? ($empUsername . '@nunno.cloud'));
        $phone = trim($data['phone'] ?? '');

        if (!$name || !$empUsername || !$empPassword) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error'   => 'Full Name, Username, and Password are required.'
            ]);
            exit;
        }

        if (strlen($empPassword) < 6) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error'   => 'Password must be at least 6 characters.'
            ]);
            exit;
        }

        $users = getUsers($usersFile);
        foreach ($users as $u) {
            if (strcasecmp($u['username'], $empUsername) === 0) {
                http_response_code(409);
                echo json_encode([
                    'success' => false,
                    'error'   => 'Username "' . $empUsername . '" is already taken.'
                ]);
                exit;
            }
        }

        // Generate clean ID
        $newId = 'EMP-' . (100 + count($users) + 1);

        $newEmployee = [
            'id'            => $newId,
            'name'          => $name,
            'username'      => $empUsername,
            'email'         => $email,
            'password_hash' => password_hash($empPassword, PASSWORD_BCRYPT),
            'role'          => $empRole,
            'designation'   => $designation,
            'phone'         => $phone,
            'status'        => 'active',
            'created_at'    => date('Y-m-d H:i:s'),
            'last_login'    => null
        ];

        $users[] = $newEmployee;
        saveUsers($usersFile, $users);

        echo json_encode([
            'success'  => true,
            'message'  => 'Employee account created successfully for ' . $name . ' (' . $empUsername . ').',
            'employee' => sanitizeUser($newEmployee)
        ]);
        exit;

    // 5. List Employees (ADMIN ONLY)
    case 'list_employees':
        if (!$currentUser || $currentUser['role'] !== 'admin') {
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'error'   => 'Access Denied: Only Administrator can view employee roster.'
            ]);
            exit;
        }

        $users = getUsers($usersFile);
        $cleanList = array_map('sanitizeUser', $users);

        echo json_encode([
            'success'   => true,
            'employees' => $cleanList
        ]);
        exit;

    // 6. Toggle Employee Status (ADMIN ONLY)
    case 'toggle_status':
        if (!$currentUser || $currentUser['role'] !== 'admin') {
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'error'   => 'Access Denied: Administrator rights required.'
            ]);
            exit;
        }

        $raw = file_get_contents('php://input');
        $data = json_decode($raw, true) ?: $_POST;
        $targetId = trim($data['id'] ?? '');

        if (!$targetId) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Employee ID is required.']);
            exit;
        }

        if ($targetId === 'USR-ADMIN-01') {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Cannot deactivate Master Administrator account.']);
            exit;
        }

        $users = getUsers($usersFile);
        $found = false;
        $newStatus = 'active';

        foreach ($users as &$u) {
            if ($u['id'] === $targetId) {
                $u['status'] = ($u['status'] === 'active') ? 'suspended' : 'active';
                $newStatus = $u['status'];
                $found = true;
                break;
            }
        }

        if (!$found) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Employee not found.']);
            exit;
        }

        saveUsers($usersFile, $users);

        echo json_encode([
            'success'    => true,
            'message'    => 'Employee status updated to ' . $newStatus . '.',
            'new_status' => $newStatus
        ]);
        exit;

    // 7. Delete Employee (ADMIN ONLY)
    case 'delete_employee':
        if (!$currentUser || $currentUser['role'] !== 'admin') {
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'error'   => 'Access Denied: Administrator rights required.'
            ]);
            exit;
        }

        $raw = file_get_contents('php://input');
        $data = json_decode($raw, true) ?: $_POST;
        $targetId = trim($data['id'] ?? '');

        if (!$targetId) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Employee ID is required.']);
            exit;
        }

        if ($targetId === 'USR-ADMIN-01' || $targetId === $currentUser['id']) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Cannot delete Administrator account.']);
            exit;
        }

        $users = getUsers($usersFile);
        $filtered = array_values(array_filter($users, function($u) use ($targetId) {
            return $u['id'] !== $targetId;
        }));

        saveUsers($usersFile, $filtered);

        echo json_encode([
            'success' => true,
            'message' => 'Employee removed successfully.'
        ]);
        exit;

    default:
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error'   => 'Unknown authentication action.'
        ]);
        exit;
}
