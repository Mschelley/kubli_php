<?php
require __DIR__ . '/../config.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $stmt = $pdo->query('SELECT * FROM reports ORDER BY created_at DESC, id DESC');
    $rows = $stmt->fetchAll();

    $out = array_map(function ($r) {
        return [
            'id' => (int) $r['id'],
            'species' => $r['species'],
            'description' => $r['description'],
            'context' => $r['context'],
            'symptoms' => json_decode($r['symptoms'] ?? '[]', true) ?: [],
            'base_level' => (int) $r['base_level'],
            'level' => (int) $r['level'],
            'coords' => $r['coords'],
            'lat' => $r['lat'] !== null ? (float) $r['lat'] : null,
            'lng' => $r['lng'] !== null ? (float) $r['lng'] : null,
            'photo_url' => $r['photo_url'],
            'status' => $r['status'],
            'scope' => $r['scope'],
            'submitted_by' => (int) $r['submitted_by'],
            'submitted_by_name' => $r['submitted_by_name'],
            'created_at' => $r['created_at'],
        ];
    }, $rows);

    respond($out);
}

if ($method === 'POST') {
    $userId = require_login();

    $species = trim($_POST['species'] ?? '') ?: 'Unidentified species';
    $description = trim($_POST['description'] ?? '');
    $context = trim($_POST['context'] ?? '');
    $symptoms = json_decode($_POST['symptoms'] ?? '[]', true);
    if (!is_array($symptoms)) $symptoms = [];
    $base_level = max(1, min(4, (int) ($_POST['base_level'] ?? 1)));
    $level = max(1, min(4, (int) ($_POST['level'] ?? 1)));
    $coords = trim($_POST['coords'] ?? '');
    $lat = isset($_POST['lat']) && $_POST['lat'] !== '' ? (float) $_POST['lat'] : null;
    $lng = isset($_POST['lng']) && $_POST['lng'] !== '' ? (float) $_POST['lng'] : null;

    if (!$description) respond(['error' => 'Add a short description of what you observed.'], 400);
    if ($lat === null || $lng === null) respond(['error' => "Set the tree's location — use GPS or tap the map."], 400);
    if (empty($symptoms)) respond(['error' => 'Select at least one observed condition so KUBLI can classify the risk.'], 400);
    if (!isset($_FILES['photo']) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
        respond(['error' => 'Take or upload a photo of the tree first.'], 400);
    }

    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $mime = mime_content_type($_FILES['photo']['tmp_name']);
    $ext = $allowed[$mime] ?? 'jpg';
    if (!isset($allowed[$mime])) respond(['error' => 'Photo must be a JPEG, PNG, or WEBP image.'], 400);

    $stmt = $pdo->prepare('SELECT name FROM users WHERE id = ?');
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    if (!$user) respond(['error' => 'Your session expired — please sign in again.'], 401);

    $uploadDir = __DIR__ . '/../uploads/reports/' . $userId . '/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

    $filename = time() . '-' . bin2hex(random_bytes(4)) . '.' . $ext;
    if (!move_uploaded_file($_FILES['photo']['tmp_name'], $uploadDir . $filename)) {
        respond(['error' => 'Could not save the uploaded photo.'], 500);
    }
    $photoUrl = 'uploads/reports/' . $userId . '/' . $filename;

    $stmt = $pdo->prepare('INSERT INTO reports
        (species, description, context, symptoms, base_level, level, coords, lat, lng, photo_url, status, scope, submitted_by, submitted_by_name)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([
        $species, $description, $context, json_encode($symptoms), $base_level, $level,
        $coords, $lat, $lng, $photoUrl, 'Pending Review', null, $userId, $user['name'],
    ]);

    respond(['success' => true, 'id' => (int) $pdo->lastInsertId(), 'level' => $level]);
}

respond(['error' => 'Method not allowed'], 405);
