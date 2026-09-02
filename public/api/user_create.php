<?php
require __DIR__ . '/../../src/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') respond(['error' => 'Method not allowed'], 405);
require_admin($pdo);

$data = json_input();
$name = trim($data['name'] ?? '');
$email = strtolower(trim($data['email'] ?? ''));
$role = $data['role'] ?? 'User';
if (!in_array($role, ['User', 'Manager', 'Admin'], true)) $role = 'User';

if (!$name) respond(['error' => 'Enter a full name.'], 400);
if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) respond(['error' => 'Enter a valid email address.'], 400);

if (UserRepository::emailExists($pdo, $email)) {
    respond(['error' => 'An account with that email already exists.'], 400);
}

// Admin-created accounts get a random temporary password (shown once) since
// there's no password field in the "Add account" form.
$tempPassword = bin2hex(random_bytes(4));
$hash = password_hash($tempPassword, PASSWORD_DEFAULT);
$id = UserRepository::create($pdo, $name, $email, $hash, $role);

respond(['success' => true, 'id' => $id, 'temp_password' => $tempPassword]);