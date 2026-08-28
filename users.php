<?php
require __DIR__ . '/../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') respond(['error' => 'Method not allowed'], 405);
require_admin($pdo);

$stmt = $pdo->query('SELECT id, name, email, role, status, created_at FROM users ORDER BY created_at ASC, id ASC');
$rows = $stmt->fetchAll();
$out = array_map(fn($r) => [
    'id' => (int) $r['id'],
    'name' => $r['name'],
    'email' => $r['email'],
    'role' => $r['role'],
    'status' => $r['status'],
], $rows);

respond($out);
