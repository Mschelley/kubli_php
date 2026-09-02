<?php
// Every endpoint under public/api/ starts with:
//     require __DIR__ . '/../../src/bootstrap.php';
// This sets up the session, connects to MySQL, and loads the helpers/
// repositories so the endpoint itself only has to handle its own request.

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/Http.php';
require_once __DIR__ . '/Auth.php';
require_once __DIR__ . '/Lookup.php';
require_once __DIR__ . '/Repositories/UserRepository.php';
require_once __DIR__ . '/Repositories/ReportRepository.php';

session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'samesite' => 'Lax',
]);
session_start();

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
} catch (PDOException $e) {
    respond(['error' => 'Database connection failed: ' . $e->getMessage()], 500);
}
