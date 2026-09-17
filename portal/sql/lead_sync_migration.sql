-- Better Talk Task 1: website lead synchronization migration.
-- Run once against the existing Better Talk portal database after taking the
-- normal HostBreak database backup. This preserves existing users, clients and cases.

ALTER TABLE patients
  ADD COLUMN client_code VARCHAR(32) NULL UNIQUE AFTER id,
  ADD COLUMN phone_normalized VARCHAR(24) NULL AFTER phone,
  ADD COLUMN email VARCHAR(160) NULL AFTER phone_normalized,
  ADD COLUMN consent_status VARCHAR(30) NOT NULL DEFAULT 'unknown' AFTER email;

ALTER TABLE cases
  ADD COLUMN lead_id INT UNSIGNED NULL AFTER patient_id,
  ADD INDEX idx_cases_lead_id (lead_id);

CREATE TABLE leads (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  lead_code VARCHAR(36) NOT NULL UNIQUE,
  patient_id INT UNSIGNED NOT NULL,
  case_id INT UNSIGNED NOT NULL,
  source_channel VARCHAR(40) NOT NULL,
  source_detail VARCHAR(120) NULL,
  form_answers JSON NULL,
  referrer_url VARCHAR(500) NULL,
  campaign_data JSON NULL,
  status VARCHAR(40) NOT NULL DEFAULT 'new',
  assigned_agent_id INT UNSIGNED NULL,
  dedupe_key CHAR(64) NOT NULL UNIQUE,
  received_at DATETIME NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (patient_id) REFERENCES patients(id),
  FOREIGN KEY (case_id) REFERENCES cases(id) ON DELETE CASCADE,
  FOREIGN KEY (assigned_agent_id) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_leads_patient_received (patient_id, received_at),
  INDEX idx_leads_status_received (status, received_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE lead_sync_failures (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  source_channel VARCHAR(40) NOT NULL,
  dedupe_key CHAR(64) NULL,
  error_message VARCHAR(500) NOT NULL,
  request_summary JSON NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_lead_sync_failures_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

UPDATE patients
SET phone_normalized = CASE
  WHEN REGEXP_REPLACE(phone, '[^0-9]', '') LIKE '0%' THEN
    CONCAT('92', SUBSTRING(REGEXP_REPLACE(phone, '[^0-9]', ''), 2))
  ELSE REGEXP_REPLACE(phone, '[^0-9]', '')
END
WHERE phone_normalized IS NULL;

UPDATE patients
SET client_code = CONCAT('BTC-', DATE_FORMAT(created_at, '%Y'), '-', LPAD(id, 6, '0'))
WHERE client_code IS NULL;

-- Deliberately non-unique: shared numbers and historical duplicate records must
-- be handled as ambiguous matches rather than causing a destructive migration.
ALTER TABLE patients
  ADD INDEX idx_patients_phone_normalized (phone_normalized);

ALTER TABLE cases
  ADD CONSTRAINT fk_cases_lead_id FOREIGN KEY (lead_id) REFERENCES leads(id) ON DELETE SET NULL;
