<?php
require __DIR__ . '/../../src/bootstrap.php';

if (!isset($_SESSION['user_id'])) respond(['profile' => null]);

$user = UserRepository::findById($pdo, (int) $_SESSION['user_id']);

if (!$user) {
    $_SESSION = [];
    session_destroy();
    respond(['profile' => null]);
}
if ($user['status'] === 'Suspended') {
    $_SESSION = [];
    session_destroy();
    respond(['profile' => null, 'error' => 'This account has been suspended. Contact an Admin.']);
}

respond(['profile' => UserRepository::profile($user)]);
