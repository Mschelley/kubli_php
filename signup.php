<?php
require __DIR__ . '/../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') respond(['error' => 'Method not allowed'], 405);

$data = json_input();
$name = trim($data['name'] ?? '');
$email = strtolower(trim($data['email'] ?? ''));
$password = (string) ($data['password'] ?? '');
$role = $data['role'] ?? 'User';
if (!in_array($role, ['User', 'Manager', 'Admin'], true)) $role = 'User';

if (!$name) respond(['error' => 'Enter your full name.'], 400);
if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) respond(['error' => 'Enter a valid email address.'], 400);
if (strlen($password) < 6) respond(['error' => 'Password must be at least 6 characters.'], 400);

$stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
$stmt->execute([$email]);
if ($stmt->fetch()) respond(['error' => 'An account with that email already exists.'], 400);

$hash = password_hash($password, PASSWORD_DEFAULT);
$stmt = $pdo->prepare('INSERT INTO users (name, email, password_hash, role, status) VALUES (?, ?, ?, ?, ?)');
$stmt->execute([$name, $email, $hash, $role, 'Active']);
$id = (int) $pdo->lastInsertId();

session_regenerate_id(true);
$_SESSION['user_id'] = $id;

respond(['profile' => [
    'id' => $id, 'name' => $name, 'email' => $email, 'role' => $role, 'status' => 'Active',
]]);
