<?php
// KUBLI configuration — MySQL connection settings.
//
// This file lives outside public/, so it is never reachable over HTTP —
// only PHP files under src/ and public/api/ read it via require.
//
// Before running the app, create the database and load the schema:
//   mysql -u root -p -e "CREATE DATABASE kubli CHARACTER SET utf8mb4;"
//   mysql -u root -p kubli < database/schema.sql

define('DB_HOST', 'localhost');
define('DB_NAME', 'kubli');
define('DB_USER', 'root');
define('DB_PASS', '');

// ---- Uploaded photos ----
define('UPLOAD_DIR', __DIR__ . '/public/uploads/reports/');
