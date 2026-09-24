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
