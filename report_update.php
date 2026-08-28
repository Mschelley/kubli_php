<?php
require __DIR__ . '/../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') respond(['error' => 'Method not allowed'], 405);
require_login();

$data = json_input();
$id = (int) ($data['id'] ?? 0);
if (!$id) respond(['error' => 'Missing report id.'], 400);

$validStatuses = ['Pending Review', 'Field Validation', 'Permit Routed', 'Resolved'];
$fields = [];
$params = [];

if (isset($data['status'])) {
    if (!in_array($data['status'], $validStatuses, true)) respond(['error' => 'Invalid status.'], 400);
    $fields[] = 'status = ?';
    $params[] = $data['status'];
}
if (array_key_exists('scope', $data)) {
    $fields[] = 'scope = ?';
    $params[] = $data['scope'] ?: null;
}
if (!$fields) respond(['error' => 'Nothing to update.'], 400);

$params[] = $id;
$stmt = $pdo->prepare('UPDATE reports SET ' . implode(', ', $fields) . ' WHERE id = ?');
$stmt->execute($params);

respond(['success' => true]);
