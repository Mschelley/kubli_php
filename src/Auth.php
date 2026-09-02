<?php
// Session guards used by endpoints that require a signed-in user or an Admin.

require_once __DIR__ . '/Http.php';

function require_login(): int {
    if (!isset($_SESSION['user_id'])) {
        respond(['error' => 'Your session expired — please sign in again.'], 401);
    }
    return (int) $_SESSION['user_id'];
}

// Account creation/role changes are an Admin responsibility — Manager only
// handles the report queue and permit routing, so it never touches user accounts.
function require_admin(PDO $pdo): int {
    $uid = require_login();
    $role = UserRepository::roleOf($pdo, $uid);
    if ($role !== 'Admin') {
        respond(['error' => 'Admins only.'], 403);
    }
    return $uid;
}