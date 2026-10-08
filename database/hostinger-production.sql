-- ELIA: fresh Hostinger installation only.
-- Import ONCE into an EMPTY database selected in phpMyAdmin.
-- Includes all five migrations; do not import them again afterwards.
-- Contains schema and request types only, no accounts or local records.
SET NAMES utf8mb4;

-- Source: schema.sql
CREATE TABLE IF NOT EXISTS users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(150) NOT NULL,
    email VARCHAR(254) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin', 'client') NOT NULL DEFAULT 'client',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    failed_login_attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
    locked_until DATETIME NULL,
    last_login_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY users_email_unique (email),
    KEY users_role_active_index (role, is_active),
    CONSTRAINT users_role_valid CHECK (role IN ('admin', 'client')),
    CONSTRAINT users_active_valid CHECK (is_active IN (0, 1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Source: request-management.sql
CREATE TABLE IF NOT EXISTS request_types (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 name VARCHAR(150) NOT NULL UNIQUE,
 description TEXT NULL,
 status ENUM('active','inactive') NOT NULL DEFAULT 'active',
 checklist_ready TINYINT(1) NOT NULL DEFAULT 0,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS requirement_templates (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 request_type_id BIGINT UNSIGNED NOT NULL,
 requirement_name VARCHAR(180) NOT NULL,
 description TEXT NULL,
 is_required TINYINT(1) NOT NULL DEFAULT 1,
 sort_order INT UNSIGNED NOT NULL DEFAULT 0,
 status ENUM('active','inactive') NOT NULL DEFAULT 'active',
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 UNIQUE KEY template_name (request_type_id, requirement_name),
 FOREIGN KEY (request_type_id) REFERENCES request_types(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS requests (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 reference_no VARCHAR(40) NULL UNIQUE,
 user_id BIGINT UNSIGNED NOT NULL,
 request_type_id BIGINT UNSIGNED NOT NULL,
 title VARCHAR(200) NOT NULL,
 purpose TEXT NOT NULL,
 destination VARCHAR(200) NOT NULL DEFAULT '',
 country VARCHAR(100) NOT NULL DEFAULT '',
 start_date DATE NULL,
 end_date DATE NULL,
 remarks TEXT NULL,
 status ENUM('draft','submitted','under_review','for_revision','resubmitted','approved','in_progress','post_travel','completed','rejected','cancelled') NOT NULL DEFAULT 'draft',
 revision INT UNSIGNED NOT NULL DEFAULT 1,
 submitted_at DATETIME NULL,
 approved_at DATETIME NULL,
 completed_at DATETIME NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 FOREIGN KEY (user_id) REFERENCES users(id),
 FOREIGN KEY (request_type_id) REFERENCES request_types(id),
 KEY client_requests (user_id, created_at),
 KEY request_queue (status, submitted_at),
 KEY submitted_date (submitted_at),
 CHECK (end_date IS NULL OR start_date IS NULL OR end_date >= start_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- A snapshot preserves the checklist agreed at creation when templates change.
CREATE TABLE IF NOT EXISTS request_requirements (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 request_id BIGINT UNSIGNED NOT NULL,
 requirement_template_id BIGINT UNSIGNED NOT NULL,
 requirement_name VARCHAR(180) NOT NULL,
 description TEXT NULL,
 is_required TINYINT(1) NOT NULL,
 sort_order INT UNSIGNED NOT NULL,
 UNIQUE KEY request_template (request_id, requirement_template_id),
 UNIQUE KEY requirement_request (id, request_id),
 FOREIGN KEY (request_id) REFERENCES requests(id),
 FOREIGN KEY (requirement_template_id) REFERENCES requirement_templates(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS request_documents (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 request_id BIGINT UNSIGNED NOT NULL,
 request_requirement_id BIGINT UNSIGNED NOT NULL,
 version INT UNSIGNED NOT NULL,
 original_filename VARCHAR(255) NOT NULL,
 stored_filename VARCHAR(80) NOT NULL UNIQUE,
 mime_type VARCHAR(100) NOT NULL,
 file_size INT UNSIGNED NOT NULL,
 status ENUM('pending','verified','for_revision','rejected') NOT NULL DEFAULT 'pending',
 admin_remarks TEXT NULL,
 uploaded_by BIGINT UNSIGNED NOT NULL,
 uploaded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 verified_by BIGINT UNSIGNED NULL,
 verified_at DATETIME NULL,
 UNIQUE KEY document_version (request_requirement_id, version),
 KEY request_files (request_id, id),
 FOREIGN KEY (request_requirement_id, request_id) REFERENCES request_requirements(id, request_id),
 FOREIGN KEY (uploaded_by) REFERENCES users(id),
 FOREIGN KEY (verified_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS request_status_history (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 request_id BIGINT UNSIGNED NOT NULL,
 old_status ENUM('draft','submitted','under_review','for_revision','resubmitted','approved','in_progress','post_travel','completed','rejected','cancelled') NULL,
 new_status ENUM('draft','submitted','under_review','for_revision','resubmitted','approved','in_progress','post_travel','completed','rejected','cancelled') NOT NULL,
 remarks TEXT NULL,
 changed_by BIGINT UNSIGNED NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY (request_id) REFERENCES requests(id),
 FOREIGN KEY (changed_by) REFERENCES users(id),
 KEY request_timeline (request_id, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS notifications (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 user_id BIGINT UNSIGNED NOT NULL,
 request_id BIGINT UNSIGNED NOT NULL,
 message VARCHAR(500) NOT NULL,
 read_at DATETIME NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY (user_id) REFERENCES users(id),
 FOREIGN KEY (request_id) REFERENCES requests(id),
 KEY unread_notifications (user_id, read_at, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO request_types (name, description) VALUES
 ('Conference / Meeting Assessment', 'Conference and meeting assessment requests.'),
 ('Referendum / BOT', 'Referendum and Board of Trustees transactions.'),
 ('Pre-Departure', 'Pre-departure requirements and review.'),
 ('Post-Travel', 'Post-travel requirements and review.'),
 ('Other ELIA Transaction', 'Other transactions handled by the ELIA Office.')
ON DUPLICATE KEY UPDATE name = VALUES(name);


-- Source: travel-monitoring.sql
-- Apply once after request-management.sql. Existing checklists remain submission checklists.
ALTER TABLE request_types
 ADD COLUMN pre_departure_ready TINYINT(1) NOT NULL DEFAULT 0,
 ADD COLUMN post_travel_ready TINYINT(1) NOT NULL DEFAULT 0;

ALTER TABLE requirement_templates
 ADD COLUMN stage ENUM('submission','pre_departure','post_travel') NOT NULL DEFAULT 'submission',
 DROP INDEX template_name,
 ADD UNIQUE KEY template_name (request_type_id, stage, requirement_name);

ALTER TABLE request_requirements
 ADD COLUMN stage ENUM('submission','pre_departure','post_travel') NOT NULL DEFAULT 'submission',
 DROP INDEX request_template,
 ADD UNIQUE KEY request_template (request_id, stage, requirement_template_id);

ALTER TABLE requests
 ADD COLUMN pre_departure_started_at DATETIME NULL,
 ADD COLUMN pre_departure_due_date DATE NULL,
 ADD COLUMN post_travel_started_at DATETIME NULL,
 ADD COLUMN post_travel_due_date DATE NULL,
 ADD INDEX pre_departure_due (status, pre_departure_due_date),
 ADD INDEX post_travel_due (status, post_travel_due_date);


-- Source: password-recovery.sql
-- Apply once after schema.sql. Also required for new installations.
ALTER TABLE users ADD COLUMN auth_version INT UNSIGNED NOT NULL DEFAULT 0;

CREATE TABLE password_resets (
    user_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
    token_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    email VARCHAR(254) NOT NULL,
    auth_version INT UNSIGNED NOT NULL,
    requested_at DATETIME NOT NULL,
    expires_at DATETIME NOT NULL,
    UNIQUE KEY password_resets_token_unique (token_hash),
    CONSTRAINT password_resets_user_fk FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE password_reset_limits (
    bucket CHAR(64) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
    attempts INT UNSIGNED NOT NULL,
    expires_at DATETIME NOT NULL,
    KEY password_reset_limits_expiry (expires_at)
) ENGINE=InnoDB;


-- Source: partnerships.sql
CREATE TABLE partners (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 name VARCHAR(200) NOT NULL,
 country VARCHAR(100) NOT NULL,
 address VARCHAR(500) NOT NULL DEFAULT '',
 contact_name VARCHAR(150) NOT NULL DEFAULT '',
 contact_email VARCHAR(254) NOT NULL DEFAULT '',
 website VARCHAR(500) NOT NULL DEFAULT '',
 notes TEXT NOT NULL,
 is_archived TINYINT(1) NOT NULL DEFAULT 0,
 revision INT UNSIGNED NOT NULL DEFAULT 1,
 created_by BIGINT UNSIGNED NOT NULL,
 updated_by BIGINT UNSIGNED NOT NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 UNIQUE KEY partner_name_country (name, country),
 FOREIGN KEY (created_by) REFERENCES users(id),
 FOREIGN KEY (updated_by) REFERENCES users(id),
 CHECK (is_archived IN (0,1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE partnership_agreements (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 partner_id BIGINT UNSIGNED NOT NULL,
 reference_no VARCHAR(100) NOT NULL UNIQUE,
 title VARCHAR(200) NOT NULL,
 agreement_type ENUM('MOU','MOA') NOT NULL,
 status ENUM('draft','signed','terminated') NOT NULL DEFAULT 'draft',
 signed_date DATE NULL,
 start_date DATE NULL,
 end_date DATE NULL,
 notes TEXT NOT NULL,
 is_archived TINYINT(1) NOT NULL DEFAULT 0,
 revision INT UNSIGNED NOT NULL DEFAULT 1,
 created_by BIGINT UNSIGNED NOT NULL,
 updated_by BIGINT UNSIGNED NOT NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 FOREIGN KEY (partner_id) REFERENCES partners(id),
 FOREIGN KEY (created_by) REFERENCES users(id),
 FOREIGN KEY (updated_by) REFERENCES users(id),
 KEY agreement_dates (status, end_date),
 CHECK (is_archived IN (0,1)),
 CHECK (end_date IS NULL OR (start_date IS NOT NULL AND end_date >= start_date))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE partnership_documents (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 agreement_id BIGINT UNSIGNED NOT NULL,
 version INT UNSIGNED NOT NULL,
 description VARCHAR(200) NOT NULL,
 original_filename VARCHAR(255) NOT NULL,
 stored_filename VARCHAR(80) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,
 mime_type VARCHAR(150) NOT NULL,
 file_size BIGINT UNSIGNED NOT NULL,
 uploaded_by BIGINT UNSIGNED NOT NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY agreement_upload_version (agreement_id, version),
 FOREIGN KEY (agreement_id) REFERENCES partnership_agreements(id),
 FOREIGN KEY (uploaded_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


