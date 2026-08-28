-- KUBLI — MySQL schema
-- Run this against an empty database (e.g. `mysql -u root -p kubli < schema.sql`)
-- after creating the database: CREATE DATABASE kubli CHARACTER SET utf8mb4;

CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(190) NOT NULL,
    email VARCHAR(190) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('User','Manager','Admin') NOT NULL DEFAULT 'User',
    status ENUM('Active','Suspended') NOT NULL DEFAULT 'Active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS reports (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    species VARCHAR(190),
    description TEXT,
    context VARCHAR(64),
    symptoms JSON,
    base_level TINYINT UNSIGNED,
    level TINYINT UNSIGNED,
    coords VARCHAR(64),
    lat DOUBLE,
    lng DOUBLE,
    photo_url VARCHAR(255),
    status VARCHAR(32) NOT NULL DEFAULT 'Pending Review',
    scope VARCHAR(64) NULL,
    submitted_by INT UNSIGNED NOT NULL,
    submitted_by_name VARCHAR(190),
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX (level),
    INDEX (status),
    INDEX (submitted_by),
    FOREIGN KEY (submitted_by) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
