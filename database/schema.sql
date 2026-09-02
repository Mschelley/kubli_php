-- KUBLI — MySQL schema (normalized)
-- Run against an empty database:
--   CREATE DATABASE kubli CHARACTER SET utf8mb4;
--   mysql -u root -p kubli < database/schema.sql

-- Lookup tables. Each replaces a free-text or ENUM column from the
-- original design, so a fixed vocabulary lives in one place instead
-- of being duplicated as a string on every row.
CREATE TABLE roles (
    id   TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(32) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE account_statuses (
    id   TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(32) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE location_contexts (
    id   TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(64) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE report_statuses (
    id   TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(32) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE permit_scopes (
    id          TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(64) NOT NULL UNIQUE,
    description VARCHAR(190) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE symptom_types (
    id    TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code  VARCHAR(32) NOT NULL UNIQUE,
    label VARCHAR(120) NOT NULL,
    level TINYINT UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Core tables

CREATE TABLE users (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name          VARCHAR(190) NOT NULL,
    email         VARCHAR(190) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role_id       TINYINT UNSIGNED NOT NULL,
    status_id     TINYINT UNSIGNED NOT NULL,
    created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (role_id) REFERENCES roles(id),
    FOREIGN KEY (status_id) REFERENCES account_statuses(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE reports (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    species       VARCHAR(190) NOT NULL,
    description   TEXT NOT NULL,
    context_id    TINYINT UNSIGNED NOT NULL,
    base_level    TINYINT UNSIGNED NOT NULL,
    level         TINYINT UNSIGNED NOT NULL,
    coords        VARCHAR(64),
    lat           DOUBLE,
    lng           DOUBLE,
    photo_url     VARCHAR(255) NOT NULL,
    status_id     TINYINT UNSIGNED NOT NULL,
    scope_id      TINYINT UNSIGNED NULL,
    submitted_by  INT UNSIGNED NOT NULL,
    created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (context_id) REFERENCES location_contexts(id),
    FOREIGN KEY (status_id) REFERENCES report_statuses(id),
    FOREIGN KEY (scope_id) REFERENCES permit_scopes(id),
    FOREIGN KEY (submitted_by) REFERENCES users(id) ON DELETE CASCADE,
    INDEX (level),
    INDEX (status_id),
    INDEX (submitted_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Which observed conditions were checked on a report — a many-to-many
-- junction table, replacing the old `symptoms` JSON array column.
CREATE TABLE report_symptoms (
    report_id       INT UNSIGNED NOT NULL,
    symptom_type_id TINYINT UNSIGNED NOT NULL,
    PRIMARY KEY (report_id, symptom_type_id),
    FOREIGN KEY (report_id) REFERENCES reports(id) ON DELETE CASCADE,
    FOREIGN KEY (symptom_type_id) REFERENCES symptom_types(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed data for the lookup tables — this is the fixed vocabulary the
-- UI in index.html/script.js already assumes.


INSERT INTO roles (name) VALUES ('User'), ('Manager'), ('Admin');

INSERT INTO account_statuses (name) VALUES ('Active'), ('Suspended');

INSERT INTO location_contexts (name) VALUES
    ('Sidewalk/Pathway'), ('School Zone'), ('Hospital Zone'),
    ('Park'), ('Residential Area'), ('Roadside');

INSERT INTO report_statuses (name) VALUES
    ('Pending Review'), ('Field Validation'), ('Permit Routed'), ('Resolved');

INSERT INTO permit_scopes (name, description) VALUES
    ('Branch/Limb Trimming', 'Simplified pruning clearance (subject to DAO 2021-11 thresholds)'),
    ('Trunk-Level Cutting', 'TCP - standard processing'),
    ('Full Tree Removal', 'TCP (standard), or STCP if imminent hazard present'),
    ('Emergency Removal', 'STCP - expedited; may allow removal ahead of full paperwork');

INSERT INTO symptom_types (code, label, level) VALUES
    ('dead_branches',      'Small dead branches or minor leaf discoloration', 1),
    ('cracks',             'Visible cracks or minor lean', 2),
    ('canopy_dieback',     'Partial canopy dieback', 2),
    ('root_heaving',       'Root heaving / lifted pavement', 3),
    ('cavities',           'Large trunk cavities', 3),
    ('sig_lean',           'Significant lean', 3),
    ('rupture',            'Visible trunk rupture', 4),
    ('uprooting',          'Uprooting in progress', 4),
    ('leaning_structure',  'Leaning on a structure or power lines', 4),
    ('storm',              'Storm-damaged', 4);

