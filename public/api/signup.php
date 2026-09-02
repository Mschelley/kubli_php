<?php
require __DIR__ . '/../../src/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') respond(['error' => 'Method not allowed'], 405);

$data = json_input();
$name = trim($data['name'] ?? '');
$email = strtolower(trim($data['email'] ?? ''));
$password = (string) ($data['password'] ?? '');
// Self-registration always creates a Citizen account — a Manager promotes
// it later if the person needs Manager or Admin access. Keeps account
// escalation out of an unauthenticated endpoint's hands.
$role = 'User';

if (!$name) respond(['error' => 'Enter your full name.'], 400);
if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) respond(['error' => 'Enter a valid email address.'], 400);
if (strlen($password) < 6) respond(['error' => 'Password must be at least 6 characters.'], 400);

if (UserRepository::emailExists($pdo, $email)) {
    respond(['error' => 'An account with that email already exists.'], 400);
}

$hash = password_hash($password, PASSWORD_DEFAULT);
$id = UserRepository::create($pdo, $name, $email, $hash, $role);

session_regenerate_id(true);
$_SESSION['user_id'] = $id;

respond(['profile' => [
    'id' => $id, 'name' => $name, 'email' => $email, 'role' => $role, 'status' => 'Active',
]]);
