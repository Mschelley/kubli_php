<?php
require __DIR__ . '/../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') respond(['error' => 'Method not allowed'], 405);
$adminId = require_admin($pdo);

$data = json_input();
$id = (int) ($data['id'] ?? 0);
if (!$id) respond(['error' => 'Missing user id.'], 400);
if ($id === $adminId) respond(['error' => "You can't change your own account."], 400);

$fields = [];
$params = [];
if (isset($data['role'])) {
    if (!in_array($data['role'], ['User', 'Manager', 'Admin'], true)) respond(['error' => 'Invalid role.'], 400);
    $fields[] = 'role = ?';
    $params[] = $data['role'];
}
if (isset($data['status'])) {
    if (!in_array($data['status'], ['Active', 'Suspended'], true)) respond(['error' => 'Invalid status.'], 400);
    $fields[] = 'status = ?';
    $params[] = $data['status'];
}
if (!$fields) respond(['error' => 'Nothing to update.'], 400);

$params[] = $id;
$stmt = $pdo->prepare('UPDATE users SET ' . implode(', ', $fields) . ' WHERE id = ?');
$stmt->execute($params);

respond(['success' => true]);
