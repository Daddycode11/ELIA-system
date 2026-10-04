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
