<?php
/**
 * KUBLI — shared config & DB bootstrap.
 *
 * DB_DRIVER = 'sqlite' works with zero setup (great for trying the app locally —
 * it auto-creates data/kubli.sqlite and the tables on first request).
 *
 * DB_DRIVER = 'mysql' is what you'd point at a real MySQL/MariaDB server for
 * an actual deployment. Fill in DB_HOST/DB_NAME/DB_USER/DB_PASS below and run
 * schema.sql against that database first.
 */

define('DB_DRIVER', 'sqlite'); // 'sqlite' or 'mysql'

// ---- MySQL settings (only used when DB_DRIVER === 'mysql') ----
define('DB_HOST', 'localhost');
define('DB_NAME', 'kubli');
define('DB_USER', 'root');
define('DB_PASS', '');

// ---- SQLite settings (only used when DB_DRIVER === 'sqlite') ----
define('DB_SQLITE_PATH', __DIR__ . '/data/kubli.sqlite');

// ---------------------------------------------------------------

session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'samesite' => 'Lax',
]);
session_start();

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

function respond($data, int $code = 200): never {
    http_response_code($code);
    echo json_encode($data);
    exit;
}

function json_input(): array {
    $raw = file_get_contents('php://input');
    if (!$raw) return [];
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

function require_login(): int {
    if (!isset($_SESSION['user_id'])) {
        respond(['error' => 'Your session expired — please sign in again.'], 401);
    }
    return (int) $_SESSION['user_id'];
}

function require_admin(PDO $pdo): int {
    $uid = require_login();
    $stmt = $pdo->prepare('SELECT role FROM users WHERE id = ?');
    $stmt->execute([$uid]);
    $me = $stmt->fetch();
    if (!$me || $me['role'] !== 'Admin') {
        respond(['error' => 'Admins only.'], 403);
    }
    return $uid;
}

function init_sqlite_schema(PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        email TEXT NOT NULL UNIQUE,
        password_hash TEXT NOT NULL,
        role TEXT NOT NULL DEFAULT 'User',
        status TEXT NOT NULL DEFAULT 'Active',
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS reports (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        species TEXT,
        description TEXT,
        context TEXT,
        symptoms TEXT,
        base_level INTEGER,
        level INTEGER,
        coords TEXT,
        lat REAL,
        lng REAL,
        photo_url TEXT,
        status TEXT NOT NULL DEFAULT 'Pending Review',
        scope TEXT,
        submitted_by INTEGER NOT NULL,
        submitted_by_name TEXT,
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (submitted_by) REFERENCES users(id)
    )");
}

try {
    if (DB_DRIVER === 'mysql') {
        $pdo = new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]
        );
    } else {
        $dataDir = dirname(DB_SQLITE_PATH);
        if (!is_dir($dataDir)) mkdir($dataDir, 0755, true);
        $pdo = new PDO('sqlite:' . DB_SQLITE_PATH, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $pdo->exec('PRAGMA foreign_keys = ON');
        init_sqlite_schema($pdo);
    }
} catch (PDOException $e) {
    respond(['error' => 'Database connection failed: ' . $e->getMessage()], 500);
}
