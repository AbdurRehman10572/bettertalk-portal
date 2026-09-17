INSERT IGNORE INTO users (id, role, name, email, phone, password_hash) VALUES
(1, 'admin', 'Better Talk Admin', 'admin@bettertalk.pk', NULL, '__ADMIN_HASH__'),
(2, 'agent', 'Care Agent', 'agent@bettertalk.pk', NULL, '__AGENT_HASH__'),
(3, 'doctor', 'Dr. Ayesha Khan', 'doctor@bettertalk.pk', NULL, '__DOCTOR_HASH__');

INSERT IGNORE INTO doctor_profiles (user_id, specialties, availability_note, ivr_extension) VALUES
(3, 'Stress, anxiety, relationship counselling', 'Weekdays 10am-6pm', 'DOC-3');

INSERT IGNORE INTO patients (id, name, phone, city, plan_name, notes) VALUES
(1, 'Sara A.', '+923001234567', 'Lahore', 'Intro counselling plan', 'Number is stored in backend only; doctor view remains masked.'),
(2, 'Ali H.', '+923111234567', 'Karachi', 'Work stress plan', 'Awaiting payment confirmation.');

INSERT IGNORE INTO cases (id, case_code, patient_id, assigned_doctor_id, assigned_agent_id, service_type, status, created_from_call_id, summary) VALUES
(1, 'BT-20260916-0001', 1, 3, 2, 'Relationship & Breakup', 'scheduled', 'IVR-DEMO-1001', 'Client needs a first counselling session.'),
(2, 'BT-20260916-0002', 2, NULL, 2, 'Work & Earning Stress', 'awaiting_payment', 'IVR-DEMO-1002', 'Agent to follow payment and schedule doctor.');

INSERT IGNORE INTO appointments (case_id, patient_id, doctor_id, agent_id, scheduled_at, duration_minutes, status, notes) VALUES
(1, 1, 3, 2, DATE_ADD(NOW(), INTERVAL 1 DAY), 30, 'confirmed', 'Doctor will initiate masked call from portal.');

INSERT IGNORE INTO calls (provider, provider_call_id, case_id, patient_id, doctor_id, agent_id, direction, from_role, to_role, status, started_at, duration_seconds) VALUES
('mock', 'IVR-DEMO-1001', 1, 1, NULL, 2, 'inbound', 'patient', 'agent', 'completed', DATE_SUB(NOW(), INTERVAL 1 DAY), 240);
