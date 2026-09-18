-- Better Talk appointment module foundation.
-- Target: MySQL/MariaDB on HostBreak shared hosting.
-- Run once after a database backup and after lead_sync_migration.sql.

ALTER TABLE doctor_profiles
  ADD COLUMN doctor_code VARCHAR(32) NULL AFTER id,
  ADD COLUMN pseudonym VARCHAR(120) NULL AFTER doctor_code,
  ADD COLUMN public_qualifications VARCHAR(255) NULL AFTER specialties,
  ADD COLUMN public_experience VARCHAR(255) NULL AFTER public_qualifications,
  ADD COLUMN timezone VARCHAR(64) NOT NULL DEFAULT 'Asia/Karachi' AFTER availability_note,
  ADD COLUMN ea_provider_id INT UNSIGNED NULL AFTER timezone,
  ADD COLUMN calling_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER ea_provider_id,
  ADD UNIQUE INDEX uq_doctor_profiles_code (doctor_code),
  ADD UNIQUE INDEX uq_doctor_profiles_ea_provider (ea_provider_id);

UPDATE doctor_profiles
SET doctor_code = CONCAT('DR-', LPAD(id, 6, '0'))
WHERE doctor_code IS NULL;

ALTER TABLE doctor_profiles
  MODIFY doctor_code VARCHAR(32) NOT NULL;

CREATE TABLE appointment_services (
  id SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  service_code VARCHAR(32) NOT NULL UNIQUE,
  name VARCHAR(120) NOT NULL,
  duration_minutes SMALLINT UNSIGNED NOT NULL,
  ea_service_id INT UNSIGNED NULL UNIQUE,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_appointment_services_duration (duration_minutes)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO appointment_services (service_code, name, duration_minutes)
VALUES
  ('BT-15', 'Better Talk 15-minute session', 15),
  ('BT-30', 'Better Talk 30-minute session', 30),
  ('BT-45', 'Better Talk 45-minute session', 45),
  ('BT-60', 'Better Talk 60-minute session', 60)
ON DUPLICATE KEY UPDATE name=VALUES(name), active=1;

CREATE TABLE doctor_appointment_services (
  doctor_id INT UNSIGNED NOT NULL,
  appointment_service_id SMALLINT UNSIGNED NOT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (doctor_id, appointment_service_id),
  FOREIGN KEY (doctor_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (appointment_service_id) REFERENCES appointment_services(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE appointments
  ADD COLUMN appointment_code VARCHAR(36) NULL AFTER id,
  ADD COLUMN payment_id INT UNSIGNED NULL AFTER case_id,
  ADD COLUMN appointment_service_id SMALLINT UNSIGNED NULL AFTER payment_id,
  ADD COLUMN end_at DATETIME NULL AFTER scheduled_at,
  ADD COLUMN timezone VARCHAR(64) NOT NULL DEFAULT 'Asia/Karachi' AFTER end_at,
  ADD COLUMN ea_appointment_id INT UNSIGNED NULL AFTER timezone,
  ADD COLUMN sync_status ENUM('not_configured','pending','synced','failed') NOT NULL DEFAULT 'not_configured' AFTER ea_appointment_id,
  ADD COLUMN sync_error VARCHAR(500) NULL AFTER sync_status,
  ADD COLUMN confirmed_at DATETIME NULL AFTER notes,
  ADD COLUMN started_at DATETIME NULL AFTER confirmed_at,
  ADD COLUMN completed_at DATETIME NULL AFTER started_at,
  ADD COLUMN updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP AFTER created_at,
  MODIFY status ENUM(
    'pending','scheduled','confirmed','in_progress','completed',
    'reschedule_requested','cancellation_requested','rescheduled',
    'cancelled_by_client','cancelled_by_better_talk',
    'doctor_no_show','client_no_answer','payment_hold','cancelled','missed'
  ) NOT NULL DEFAULT 'scheduled',
  ADD UNIQUE INDEX uq_appointments_code (appointment_code),
  ADD UNIQUE INDEX uq_appointments_ea_id (ea_appointment_id),
  ADD INDEX idx_appointments_doctor_window (doctor_id, scheduled_at, end_at, status),
  ADD CONSTRAINT fk_appointments_payment FOREIGN KEY (payment_id) REFERENCES payments(id) ON DELETE RESTRICT,
  ADD CONSTRAINT fk_appointments_service FOREIGN KEY (appointment_service_id) REFERENCES appointment_services(id) ON DELETE RESTRICT;

UPDATE appointments
SET appointment_code = CONCAT('AP-', DATE_FORMAT(created_at, '%Y%m%d'), '-', LPAD(id, 6, '0')),
    end_at = DATE_ADD(scheduled_at, INTERVAL duration_minutes MINUTE)
WHERE appointment_code IS NULL OR end_at IS NULL;

ALTER TABLE appointments
  MODIFY appointment_code VARCHAR(36) NOT NULL,
  MODIFY end_at DATETIME NOT NULL;

CREATE TABLE appointment_holds (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  hold_token CHAR(64) NOT NULL UNIQUE,
  case_id INT UNSIGNED NOT NULL,
  patient_id INT UNSIGNED NOT NULL,
  doctor_id INT UNSIGNED NOT NULL,
  appointment_service_id SMALLINT UNSIGNED NOT NULL,
  agent_id INT UNSIGNED NOT NULL,
  start_at DATETIME NOT NULL,
  end_at DATETIME NOT NULL,
  expires_at DATETIME NOT NULL,
  status ENUM('active','converted','expired','released') NOT NULL DEFAULT 'active',
  converted_appointment_id INT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (case_id) REFERENCES cases(id) ON DELETE CASCADE,
  FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
  FOREIGN KEY (doctor_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (appointment_service_id) REFERENCES appointment_services(id) ON DELETE RESTRICT,
  FOREIGN KEY (agent_id) REFERENCES users(id) ON DELETE RESTRICT,
  FOREIGN KEY (converted_appointment_id) REFERENCES appointments(id) ON DELETE SET NULL,
  INDEX idx_holds_doctor_window (doctor_id, start_at, end_at, status, expires_at),
  INDEX idx_holds_agent_active (agent_id, status, expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE calls
  ADD COLUMN call_code VARCHAR(36) NULL AFTER id,
  ADD COLUMN call_kind ENUM('agent','doctor') NULL AFTER call_code,
  ADD COLUMN appointment_id INT UNSIGNED NULL AFTER case_id,
  ADD UNIQUE INDEX uq_calls_code (call_code),
  ADD INDEX idx_calls_appointment (appointment_id),
  ADD CONSTRAINT fk_calls_appointment FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE SET NULL;

UPDATE calls
SET call_kind = CASE WHEN doctor_id IS NULL THEN 'agent' ELSE 'doctor' END,
    call_code = CONCAT(CASE WHEN doctor_id IS NULL THEN 'AC-' ELSE 'DC-' END, DATE_FORMAT(created_at, '%Y%m%d'), '-', LPAD(id, 7, '0'))
WHERE call_code IS NULL OR call_kind IS NULL;

ALTER TABLE calls
  MODIFY call_code VARCHAR(36) NOT NULL,
  MODIFY call_kind ENUM('agent','doctor') NOT NULL;

ALTER TABLE patients
  ADD COLUMN login_mobile_normalized VARCHAR(24) NULL AFTER phone_normalized,
  ADD COLUMN password_hash VARCHAR(255) NULL AFTER login_mobile_normalized,
  ADD COLUMN password_changed_at DATETIME NULL AFTER password_hash,
  ADD COLUMN mobile_verified_at DATETIME NULL AFTER password_changed_at,
  ADD COLUMN ea_customer_id INT UNSIGNED NULL AFTER mobile_verified_at,
  ADD UNIQUE INDEX uq_patients_ea_customer (ea_customer_id),
  ADD INDEX idx_patients_login_mobile (login_mobile_normalized);

UPDATE patients
SET login_mobile_normalized = phone_normalized
WHERE login_mobile_normalized IS NULL AND phone_normalized IS NOT NULL;

CREATE TABLE customer_password_otps (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  patient_id INT UNSIGNED NOT NULL,
  otp_hash CHAR(64) NOT NULL,
  expires_at DATETIME NOT NULL,
  attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  consumed_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
  INDEX idx_customer_otp_lookup (patient_id, expires_at, consumed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE appointment_change_requests (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  appointment_id INT UNSIGNED NOT NULL,
  patient_id INT UNSIGNED NOT NULL,
  request_type ENUM('reschedule','cancellation') NOT NULL,
  requested_start_at DATETIME NULL,
  reason TEXT NULL,
  status ENUM('pending','approved','rejected','withdrawn') NOT NULL DEFAULT 'pending',
  reviewed_by_user_id INT UNSIGNED NULL,
  reviewed_at DATETIME NULL,
  review_note TEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE CASCADE,
  FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
  FOREIGN KEY (reviewed_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_change_requests_status (status, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE scheduling_sync_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  entity_type ENUM('doctor','service','customer','appointment','unavailability') NOT NULL,
  entity_id VARCHAR(80) NOT NULL,
  operation VARCHAR(40) NOT NULL,
  status ENUM('pending','succeeded','failed') NOT NULL,
  request_payload JSON NULL,
  response_payload JSON NULL,
  error_message VARCHAR(500) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_scheduling_sync_entity (entity_type, entity_id, created_at),
  INDEX idx_scheduling_sync_status (status, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
