<?php
require __DIR__ . '/../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') respond(['error' => 'Method not allowed'], 405);

$data = json_input();
$email = strtolower(trim($data['email'] ?? ''));
$password = (string) ($data['password'] ?? '');

if (!$email || !$password) respond(['error' => 'Enter email and password.'], 400);

$stmt = $pdo->prepare('SELECT * FROM users WHERE email = ?');
$stmt->execute([$email]);
$user = $stmt->fetch();

if (!$user || !password_verify($password, $user['password_hash'])) {
    respond(['error' => 'Incorrect email or password.'], 401);
}
if ($user['status'] === 'Suspended') {
    respond(['error' => 'This account has been suspended. Contact an Admin.'], 403);
}

session_regenerate_id(true);
$_SESSION['user_id'] = (int) $user['id'];

respond(['profile' => [
    'id' => (int) $user['id'],
    'name' => $user['name'],
    'email' => $user['email'],
    'role' => $user['role'],
    'status' => $user['status'],
]]);
