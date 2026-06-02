CREATE DATABASE IF NOT EXISTS was_telecom
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE was_telecom;

CREATE TABLE IF NOT EXISTS form_submissions (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  form_type VARCHAR(40) NOT NULL,
  first_name VARCHAR(120) NULL,
  last_name VARCHAR(120) NULL,
  email VARCHAR(180) NOT NULL,
  phone VARCHAR(60) NULL,
  request_type VARCHAR(180) NULL,
  job_position VARCHAR(180) NULL,
  message TEXT NOT NULL,
  email_sent TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_form_type (form_type),
  KEY idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE USER IF NOT EXISTS 'was_telecom'@'localhost' IDENTIFIED BY 'replace-with-strong-password';
CREATE USER IF NOT EXISTS 'was_telecom'@'127.0.0.1' IDENTIFIED BY 'replace-with-strong-password';
GRANT INSERT ON was_telecom.form_submissions TO 'was_telecom'@'localhost';
GRANT INSERT ON was_telecom.form_submissions TO 'was_telecom'@'127.0.0.1';
FLUSH PRIVILEGES;
