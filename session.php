<?php
require __DIR__ . '/../config.php';

if (!isset($_SESSION['user_id'])) respond(['profile' => null]);

$stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

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

respond(['profile' => [
    'id' => (int) $user['id'],
    'name' => $user['name'],
    'email' => $user['email'],
    'role' => $user['role'],
    'status' => $user['status'],
]]);
