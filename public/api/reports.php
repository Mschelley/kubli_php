<?php
require __DIR__ . '/../../src/bootstrap.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    respond(ReportRepository::listAll($pdo));
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

    $user = UserRepository::findById($pdo, $userId);
    if (!$user) respond(['error' => 'Your session expired — please sign in again.'], 401);

    $uploadDir = UPLOAD_DIR . $userId . '/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

    $filename = time() . '-' . bin2hex(random_bytes(4)) . '.' . $ext;
    if (!move_uploaded_file($_FILES['photo']['tmp_name'], $uploadDir . $filename)) {
        respond(['error' => 'Could not save the uploaded photo.'], 500);
    }
    $photoUrl = 'uploads/reports/' . $userId . '/' . $filename;

    $id = ReportRepository::create($pdo, [
        'species' => $species,
        'description' => $description,
        'context' => $context,
        'symptoms' => $symptoms,
        'base_level' => $base_level,
        'level' => $level,
        'coords' => $coords,
        'lat' => $lat,
        'lng' => $lng,
        'photo_url' => $photoUrl,
        'submitted_by' => $userId,
    ]);

    respond(['success' => true, 'id' => $id, 'level' => $level]);
}

respond(['error' => 'Method not allowed'], 405);
