<?php
require __DIR__ . '/../../src/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') respond(['error' => 'Method not allowed'], 405);
$adminId = require_admin($pdo);

$data = json_input();
$id = (int) ($data['id'] ?? 0);
if (!$id) respond(['error' => 'Missing user id.'], 400);
if ($id === $adminId) respond(['error' => "You can't change your own account."], 400);

$role = null;
if (isset($data['role'])) {
    if (!in_array($data['role'], ['User', 'Manager', 'Admin'], true)) respond(['error' => 'Invalid role.'], 400);
    $role = $data['role'];
}

$status = null;
if (isset($data['status'])) {
    if (!in_array($data['status'], ['Active', 'Suspended'], true)) respond(['error' => 'Invalid status.'], 400);
    $status = $data['status'];
}

if ($role === null && $status === null) respond(['error' => 'Nothing to update.'], 400);

UserRepository::updateRoleAndStatus($pdo, $id, $role, $status);

respond(['success' => true]);