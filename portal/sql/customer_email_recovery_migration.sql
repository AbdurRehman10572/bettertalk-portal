-- Better Talk customer email recovery update.
-- Run once after appointment_module_migration.sql.
-- This does not remove the legacy OTP table; OTP recovery is no longer used.

ALTER TABLE patients
  ADD COLUMN password_reset_required TINYINT(1) NOT NULL DEFAULT 0 AFTER password_changed_at;
