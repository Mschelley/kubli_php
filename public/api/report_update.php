<?php
require __DIR__ . '/../../src/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') respond(['error' => 'Method not allowed'], 405);
require_login();

$data = json_input();
$id = (int) ($data['id'] ?? 0);
if (!$id) respond(['error' => 'Missing report id.'], 400);

$validStatuses = ['Pending Review', 'Field Validation', 'Permit Routed', 'Resolved'];
$status = null;
if (isset($data['status'])) {
    if (!in_array($data['status'], $validStatuses, true)) respond(['error' => 'Invalid status.'], 400);
    $status = $data['status'];
}

$touchScope = array_key_exists('scope', $data);
$scope = $touchScope ? ($data['scope'] ?: null) : null;

if ($status === null && !$touchScope) respond(['error' => 'Nothing to update.'], 400);

ReportRepository::updateStatusAndScope($pdo, $id, $status, $touchScope, $scope);

respond(['success' => true]);
