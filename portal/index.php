<?php
declare(strict_types=1);

session_start();

$configPath = __DIR__ . '/app/config.php';
if (!is_file($configPath)) {
    http_response_code(500);
    echo 'Portal configuration is missing.';
    exit;
}
$config = require $configPath;
require_once __DIR__ . '/app/AppointmentService.php';
require_once __DIR__ . '/app/AvailabilityService.php';

function db(): PDO {
    static $pdo = null;
    global $config;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    $db = $config['db'];
    $dsn = 'mysql:host=' . $db['host'] . ';dbname=' . $db['name'] . ';charset=utf8mb4';
    $pdo = new PDO($dsn, $db['user'], $db['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    return $pdo;
}

function appointment_service(): AppointmentService {
    static $service = null;
    global $config;
    if (!$service instanceof AppointmentService) {
        $service = new AppointmentService(db(), $config);
    }
    return $service;
}

function availability_service(): AvailabilityService {
    static $service = null;
    global $config;
    if (!$service instanceof AvailabilityService) {
        $service = new AvailabilityService(db(), $config);
    }
    return $service;
}

function h(?string $value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function user(): ?array {
    return $_SESSION['user'] ?? null;
}

function require_user(): array {
    $user = user();
    if (!$user) {
        header('Location: /login');
        exit;
    }
    return $user;
}

function can(array $user, array $roles): bool {
    return in_array($user['role'], $roles, true);
}

function csrf(): string {
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf'];
}

function check_csrf(): void {
    if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
        http_response_code(419);
        exit('Security check failed.');
    }
}

function audit(?int $actorId, string $action, string $entityType = null, string $entityId = null, array $details = []): void {
    $stmt = db()->prepare('INSERT INTO audit_logs (actor_user_id, action, entity_type, entity_id, ip_address, details) VALUES (?, ?, ?, ?, ?, ?)');
    $stmt->execute([$actorId, $action, $entityType, $entityId, $_SERVER['REMOTE_ADDR'] ?? null, json_encode($details)]);
}

function route(): string {
    return trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/') ?: 'dashboard';
}

function layout(string $title, string $body): void {
    $u = user();
    $nav = $u ? nav($u) : '';
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
    echo '<title>' . h($title) . '</title><style>';
    echo file_get_contents(__DIR__ . '/app/styles.css');
    echo '</style></head><body><aside><div class="brand"><b>B</b><span>bettertalk</span><small>CARE PORTAL</small></div>' . $nav . '</aside><main>' . $body . '</main></body></html>';
}

function nav(array $u): string {
    $items = [
        'dashboard' => 'Overview',
        'cases' => 'Cases',
        'appointments' => 'Appointments',
        'calls' => 'Calls & IVR',
        'payments' => 'Payments',
    ];
    if (can($u, ['admin', 'agent'])) {
        $items['new-case'] = 'New Case';
        $items['leads'] = 'Website Leads';
    }
    if (can($u, ['admin'])) {
        $items['users'] = 'Users';
        $items['scheduler-health'] = 'Scheduler Health';
    }
    if (can($u, ['admin', 'doctor'])) {
        $items['doctor-availability'] = 'Availability';
    }
    $out = '<nav>';
    foreach ($items as $path => $label) {
        $out .= '<a href="/' . $path . '">' . h($label) . '</a>';
    }
    $out .= '<a href="/logout">Logout</a></nav><p class="muted">Signed in as ' . h($u['name']) . '<br>' . h(strtoupper($u['role'])) . '</p>';
    return $out;
}

function metric(string $label, string $value): string {
    return '<section class="metric"><strong>' . h($value) . '</strong><span>' . h($label) . '</span></section>';
}

function status_badge(string $status): string {
    return '<span class="badge ' . h(str_replace('_', '-', $status)) . '">' . h(str_replace('_', ' ', ucfirst($status))) . '</span>';
}

function doctors(): array {
    return db()->query("SELECT u.id,u.name,dp.doctor_code,dp.pseudonym,dp.specialties FROM users u LEFT JOIN doctor_profiles dp ON dp.user_id=u.id WHERE u.role='doctor' AND u.status='active' ORDER BY u.name")->fetchAll();
}

function case_statuses_for(array $u): array {
    if ($u['role'] === 'doctor') {
        return ['in_session' => 'In session', 'completed' => 'Completed', 'missed' => 'Missed'];
    }
    return [
        'new' => 'New',
        'awaiting_payment' => 'Awaiting payment',
        'paid' => 'Paid',
        'scheduled' => 'Scheduled',
        'in_session' => 'In session',
        'completed' => 'Completed',
        'cancelled' => 'Cancelled',
    ];
}

function option_list(array $items, $selected = null): string {
    $html = '';
    foreach ($items as $value => $label) {
        $sel = (string)$value === (string)$selected ? ' selected' : '';
        $html .= '<option value="' . h((string)$value) . '"' . $sel . '>' . h((string)$label) . '</option>';
    }
    return $html;
}

function dashboard(): void {
    $u = require_user();
    $pdo = db();
    if ($u['role'] === 'doctor') {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM cases WHERE assigned_doctor_id=? AND status NOT IN ('completed','cancelled')");
        $stmt->execute([$u['id']]);
        $cases = (int)$stmt->fetchColumn();
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE doctor_id=? AND DATE(scheduled_at)=CURDATE()");
        $stmt->execute([$u['id']]);
        $appts = (int)$stmt->fetchColumn();
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM calls WHERE doctor_id=?");
        $stmt->execute([$u['id']]);
        $calls = (int)$stmt->fetchColumn();
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM payments py JOIN cases c ON c.id=py.case_id WHERE py.status='pending' AND c.assigned_doctor_id=?");
        $stmt->execute([$u['id']]);
        $pending = (int)$stmt->fetchColumn();
        $stmt = $pdo->prepare("SELECT c.*, p.name patient_name, d.name doctor_name FROM cases c JOIN patients p ON p.id=c.patient_id LEFT JOIN users d ON d.id=c.assigned_doctor_id WHERE c.assigned_doctor_id=? ORDER BY c.id DESC LIMIT 6");
        $stmt->execute([$u['id']]);
        $recent = $stmt->fetchAll();
    } else {
        $cases = (int)$pdo->query("SELECT COUNT(*) FROM cases WHERE status NOT IN ('completed','cancelled')")->fetchColumn();
        $appts = (int)$pdo->query("SELECT COUNT(*) FROM appointments WHERE DATE(scheduled_at)=CURDATE()")->fetchColumn();
        $calls = (int)$pdo->query("SELECT COUNT(*) FROM calls")->fetchColumn();
        $pending = (int)$pdo->query("SELECT COUNT(*) FROM payments WHERE status='pending'")->fetchColumn();
        $recent = $pdo->query("SELECT c.*, p.name patient_name, d.name doctor_name FROM cases c JOIN patients p ON p.id=c.patient_id LEFT JOIN users d ON d.id=c.assigned_doctor_id ORDER BY c.id DESC LIMIT 6")->fetchAll();
    }
    $body = '<header><p>TODAY’S WORKSPACE</p><h1>Overview</h1><span>' . h($u['name']) . '</span></header><div class="grid metrics">' .
        metric('Active cases', (string)$cases) . metric('Appointments today', (string)$appts) . metric('Call records', (string)$calls) . metric('Pending payments', (string)$pending) . '</div>';
    $body .= '<section class="panel"><div class="panel-head"><h2>Recent cases</h2><a href="/cases">All cases</a></div><table><tr><th>Case</th><th>Client</th><th>Service</th><th>Doctor</th><th>Status</th></tr>';
    foreach ($recent as $row) {
        $body .= '<tr><td><a href="/case?id=' . (int)$row['id'] . '">' . h($row['case_code']) . '</a></td><td>' . h($row['patient_name']) . '</td><td>' . h($row['service_type']) . '</td><td>' . h($row['doctor_name'] ?: 'Unassigned') . '</td><td>' . status_badge($row['status']) . '</td></tr>';
    }
    $body .= '</table></section><section class="panel privacy"><h2>Masked calling model</h2><p>Doctors see case details and a call button. Patient phone numbers stay server-side and calls are routed by Case ID through the IVR provider adapter.</p></section>';
    layout('Better Talk Portal', $body);
}

function users_page(): void {
    $u = require_user();
    if (!can($u, ['admin'])) {
        http_response_code(403);
        exit('Forbidden');
    }
    $rows = db()->query("SELECT u.*, dp.specialties, dp.license_ref, dp.availability_note, dp.ivr_extension FROM users u LEFT JOIN doctor_profiles dp ON dp.user_id=u.id ORDER BY FIELD(u.role,'admin','agent','doctor','patient'), u.name")->fetchAll();
    $body = '<header><p>ADMIN</p><h1>User management</h1><a class="buttonlink" href="/user-new">Add user / doctor</a></header><section class="panel"><table><tr><th>Name</th><th>Email</th><th>Role</th><th>Phone</th><th>Doctor details</th><th>Status</th></tr>';
    foreach ($rows as $row) {
        $doctorDetails = $row['role'] === 'doctor'
            ? h(trim(($row['specialties'] ?: 'No specialty') . ' | ' . ($row['availability_note'] ?: 'No availability') . ' | Ext: ' . ($row['ivr_extension'] ?: '-')))
            : '-';
        $body .= '<tr><td>' . h($row['name']) . '</td><td>' . h($row['email']) . '</td><td>' . h($row['role']) . '</td><td>' . h($row['phone']) . '</td><td>' . $doctorDetails . '</td><td>' . h($row['status']) . '</td></tr>';
    }
    $body .= '</table></section>';
    layout('Users', $body);
}

function user_new(): void {
    $u = require_user();
    if (!can($u, ['admin'])) {
        http_response_code(403);
        exit('Forbidden');
    }
    $created = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        check_csrf();
        $role = $_POST['role'] ?? 'agent';
        if (!in_array($role, ['admin','agent','doctor','patient'], true)) {
            $role = 'agent';
        }
        $password = $_POST['password'] ?: bin2hex(random_bytes(5));
        $pdo = db();
        $pdo->beginTransaction();
        $stmt = $pdo->prepare('INSERT INTO users (role, name, email, phone, password_hash, status) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->execute([$role, trim($_POST['name']), strtolower(trim($_POST['email'])), trim($_POST['phone']), password_hash($password, PASSWORD_DEFAULT), $_POST['status'] ?? 'active']);
        $userId = (int)$pdo->lastInsertId();
        if ($role === 'doctor') {
            $doctorCode = 'DR-' . str_pad((string)$userId, 6, '0', STR_PAD_LEFT);
            $stmt = $pdo->prepare('INSERT INTO doctor_profiles (user_id,doctor_code,pseudonym,specialties,license_ref,public_qualifications,public_experience,ivr_extension,availability_note,timezone,working_plan_json) VALUES (?,?,?,?,?,?,?,?,?,?,?)');
            $stmt->execute([$userId, $doctorCode, trim($_POST['pseudonym']), trim($_POST['specialties']), trim($_POST['license_ref']), trim($_POST['public_qualifications']), trim($_POST['public_experience']), trim($_POST['ivr_extension']), trim($_POST['availability_note']), AvailabilityService::TIMEZONE, json_encode(array_fill_keys(AvailabilityService::DAYS, null))]);
        }
        $pdo->commit();
        audit($u['id'], 'user.create', 'user', (string)$userId);
        $created = '<p class="notice">Created. Temporary password: <b>' . h($password) . '</b></p>';
    }
    $body = '<header><p>ADMIN</p><h1>Add user / doctor</h1></header><section class="panel">' . $created . '<form method="post" class="form"><input type="hidden" name="csrf" value="' . csrf() . '"><label>Name<input name="name" required></label><label>Email<input name="email" type="email" required></label><label>Phone<input name="phone"></label><label>Role<select name="role"><option value="agent">Agent</option><option value="doctor">Doctor</option><option value="admin">Admin</option><option value="patient">Patient</option></select></label><label>Status<select name="status"><option value="active">Active</option><option value="inactive">Inactive</option></select></label><label>Password optional<input name="password" placeholder="Leave blank to generate"></label><h2>Doctor details</h2><label>Customer-facing pseudonym<input name="pseudonym"></label><label>Specialties<input name="specialties" placeholder="Marriage, anxiety, student counselling"></label><label>Public qualifications<input name="public_qualifications"></label><label>Public experience<input name="public_experience"></label><label>License/reference<input name="license_ref"></label><label>IVR extension<input name="ivr_extension"></label><label>Availability note<input name="availability_note" placeholder="Weekdays 10am-6pm"></label><button>Create user</button></form></section>';
    layout('Add user', $body);
}

function availability_target(array $u): int {
    if (!can($u, ['admin', 'doctor'])) {
        http_response_code(403);
        exit('Forbidden');
    }
    if ($u['role'] === 'doctor') {
        return (int)$u['id'];
    }
    $doctorId = (int)($_REQUEST['doctor_id'] ?? 0);
    if ($doctorId < 1) {
        $doctorId = (int)(db()->query("SELECT id FROM users WHERE role='doctor' ORDER BY name LIMIT 1")->fetchColumn() ?: 0);
    }
    return $doctorId;
}

function availability_result_notice(array $result): string {
    if (($result['sync_status'] ?? '') === 'synced') {
        return '<p class="notice">Saved and synchronized with Easy!Appointments.</p>';
    }
    return '<p class="error">Saved locally, but scheduling sync needs attention: ' . h((string)($result['sync_error'] ?? 'not configured')) . '</p>';
}

function scheduler_mapping_catalog(): array {
    global $config;
    $client = new EasyAppointmentsClient($config['easyappointments'] ?? []);
    if (!$client->isConfigured()) {
        return ['providers' => [], 'services' => [], 'error' => 'Easy!Appointments API is not configured yet.'];
    }
    try {
        return ['providers' => $client->providers(), 'services' => $client->services(), 'error' => null];
    } catch (Throwable $error) {
        return ['providers' => [], 'services' => [], 'error' => $error->getMessage()];
    }
}

function doctor_availability(): void {
    $u = require_user();
    $doctorId = availability_target($u);
    if ($doctorId < 1) {
        layout('Availability', '<section class="panel"><h1>No doctor accounts found</h1><p>Create a doctor before configuring availability.</p></section>');
        return;
    }
    try {
        $service = availability_service();
        $doctor = $service->doctor($doctorId);
        $plan = $service->workingPlan($doctor);
        $services = $service->services($doctorId);
        $exceptions = $service->exceptions($doctorId);
        $unavailability = $service->unavailability($doctorId);
    } catch (Throwable $error) {
        http_response_code(404);
        layout('Availability', '<section class="panel"><h1>Availability unavailable</h1><p class="error">' . h($error->getMessage()) . '</p></section>');
        return;
    }

    $body = '<header><div><p>SCHEDULING</p><h1>Doctor availability</h1></div><span>' . h($doctor['name']) . '</span></header>';
    if (isset($_SESSION['availability_notice'])) {
        $body .= $_SESSION['availability_notice'];
        unset($_SESSION['availability_notice']);
    }
    if ($u['role'] === 'admin') {
        $doctorOptions = [];
        foreach (db()->query("SELECT id,name FROM users WHERE role='doctor' ORDER BY name")->fetchAll() as $row) {
            $doctorOptions[(int)$row['id']] = $row['name'];
        }
        $body .= '<section class="panel"><form method="get" action="/doctor-availability" class="form inline-form"><label>Doctor<select name="doctor_id">' . option_list($doctorOptions, $doctorId) . '</select></label><button>Open availability</button></form></section>';
    }
    $syncStatus = (string)($doctor['availability_sync_status'] ?? 'not_configured');
    $body .= '<section class="panel"><div class="panel-head"><div><h2>Weekly working plan</h2><p>Times are shown in Pakistan Standard Time. Two break windows can be stored per day.</p></div>' . status_badge($syncStatus) . '</div>';
    if (!empty($doctor['availability_sync_error'])) {
        $body .= '<p class="error">' . h($doctor['availability_sync_error']) . '</p>';
    }
    $body .= '<form method="post" action="/availability-save" class="form"><input type="hidden" name="csrf" value="' . csrf() . '"><input type="hidden" name="doctor_id" value="' . $doctorId . '"><div class="availability-table"><table><tr><th>Day</th><th>Available</th><th>Start</th><th>End</th><th>Break 1</th><th>Break 2</th></tr>';
    foreach (AvailabilityService::DAYS as $day) {
        $row = is_array($plan[$day] ?? null) ? $plan[$day] : [];
        $breaks = is_array($row['breaks'] ?? null) ? $row['breaks'] : [];
        $checked = $row ? ' checked' : '';
        $body .= '<tr><td><b>' . h(ucfirst($day)) . '</b></td><td><input type="checkbox" name="schedule[' . h($day) . '][enabled]" value="1"' . $checked . '></td>' .
            '<td><input type="time" name="schedule[' . h($day) . '][start]" value="' . h($row['start'] ?? '09:00') . '"></td>' .
            '<td><input type="time" name="schedule[' . h($day) . '][end]" value="' . h($row['end'] ?? '17:00') . '"></td>' .
            '<td><div class="time-pair"><input type="time" name="schedule[' . h($day) . '][break_start][]" value="' . h($breaks[0]['start'] ?? '') . '"><input type="time" name="schedule[' . h($day) . '][break_end][]" value="' . h($breaks[0]['end'] ?? '') . '"></div></td>' .
            '<td><div class="time-pair"><input type="time" name="schedule[' . h($day) . '][break_start][]" value="' . h($breaks[1]['start'] ?? '') . '"><input type="time" name="schedule[' . h($day) . '][break_end][]" value="' . h($breaks[1]['end'] ?? '') . '"></div></td></tr>';
    }
    $body .= '</table></div>';
    if ($u['role'] === 'admin') {
        $catalog = scheduler_mapping_catalog();
        if (!empty($catalog['error'])) {
            $body .= '<p class="error">Scheduler mapping discovery unavailable: ' . h((string)$catalog['error']) . '</p>';
        }
        $providerOptions = ['' => 'Select Easy!Appointments provider'];
        foreach ($catalog['providers'] as $provider) {
            $providerName = trim((string)(($provider['firstName'] ?? '') . ' ' . ($provider['lastName'] ?? '')));
            if ($providerName === '') {
                $providerName = (string)($provider['email'] ?? ('Provider ' . (int)$provider['id']));
            }
            $providerOptions[(int)$provider['id']] = $providerName . ' (#' . (int)$provider['id'] . ')';
        }
        $body .= '<div class="inline-form"><label>Easy!Appointments provider<select name="ea_provider_id" required>' . option_list($providerOptions, $doctor['ea_provider_id'] ?? null) . '</select></label><fieldset><legend>Permitted durations</legend><div class="check-row">';
        foreach ($services as $duration) {
            $checked = (int)$duration['assigned'] === 1 ? ' checked' : '';
            $mapped = $duration['ea_service_id'] ? '' : ' (mapping needed)';
            $serviceOptions = ['' => 'Select EA service'];
            foreach ($catalog['services'] as $eaService) {
                $label = trim((string)($eaService['name'] ?? ('Service ' . (int)$eaService['id'])));
                if (isset($eaService['duration'])) {
                    $label .= ' — ' . (int)$eaService['duration'] . ' min';
                }
                $serviceOptions[(int)$eaService['id']] = $label . ' (#' . (int)$eaService['id'] . ')';
            }
            $body .= '<label><input type="checkbox" name="service_ids[]" value="' . (int)$duration['id'] . '"' . $checked . '> ' . (int)$duration['duration_minutes'] . ' min' . h($mapped) . '<select name="service_ea_ids[' . (int)$duration['id'] . ']">' . option_list($serviceOptions, $duration['ea_service_id'] ?? null) . '</select></label>';
        }
        $body .= '</div></fieldset></div>';
    } else {
        $labels = [];
        foreach ($services as $duration) {
            if ((int)$duration['assigned'] === 1) {
                $labels[] = (int)$duration['duration_minutes'] . ' minutes';
            }
        }
        $body .= '<p><b>Permitted durations:</b> ' . h($labels ? implode(', ', $labels) : 'Admin has not assigned a duration') . '</p>';
    }
    $body .= '<button>Save weekly availability</button></form></section>';

    $body .= '<div class="two-column"><section class="panel"><h2>Date exceptions</h2><p>Use this when one date has different working hours.</p><form method="post" action="/availability-exception-save" class="form"><input type="hidden" name="csrf" value="' . csrf() . '"><input type="hidden" name="doctor_id" value="' . $doctorId . '"><label>Date<input type="date" name="date" required></label><div class="inline-form"><label>Start<input type="time" name="start" required></label><label>End<input type="time" name="end" required></label></div><button>Save date exception</button></form><table><tr><th>Date</th><th>Hours</th><th></th></tr>';
    foreach ($exceptions as $exception) {
        $body .= '<tr><td>' . h($exception['exception_date']) . '</td><td>' . h(substr($exception['start_time'], 0, 5) . '–' . substr($exception['end_time'], 0, 5)) . '</td><td><form method="post" action="/availability-exception-delete" class="compact-form"><input type="hidden" name="csrf" value="' . csrf() . '"><input type="hidden" name="doctor_id" value="' . $doctorId . '"><input type="hidden" name="exception_id" value="' . (int)$exception['id'] . '"><button>Remove</button></form></td></tr>';
    }
    $body .= '</table></section>';

    $body .= '<section class="panel"><h2>Leave / unavailable period</h2><p>Unavailable periods block matching slots locally and in Easy!Appointments.</p><form method="post" action="/availability-leave-save" class="form"><input type="hidden" name="csrf" value="' . csrf() . '"><input type="hidden" name="doctor_id" value="' . $doctorId . '"><label>From (PKT)<input type="datetime-local" name="start" required></label><label>To (PKT)<input type="datetime-local" name="end" required></label><label>Reason<input name="reason" maxlength="255"></label><button>Add unavailable period</button></form><table><tr><th>Period (PKT)</th><th>Reason</th><th>Sync</th><th></th></tr>';
    foreach ($unavailability as $period) {
        $body .= '<tr><td>' . h(AppointmentService::utcToPakistan($period['start_at']) . ' – ' . AppointmentService::utcToPakistan($period['end_at'])) . '</td><td>' . h($period['reason']) . '</td><td>' . status_badge($period['sync_status']) . (!empty($period['sync_error']) ? '<small class="error-text">' . h($period['sync_error']) . '</small>' : '') . '</td><td><form method="post" action="/availability-leave-delete" class="compact-form"><input type="hidden" name="csrf" value="' . csrf() . '"><input type="hidden" name="doctor_id" value="' . $doctorId . '"><input type="hidden" name="period_id" value="' . (int)$period['id'] . '"><button>Release</button></form></td></tr>';
    }
    $body .= '</table></section></div>';
    layout('Doctor availability', $body);
}

function availability_save(): void {
    $u = require_user();
    check_csrf();
    $doctorId = availability_target($u);
    try {
        $result = availability_service()->saveWorkingPlan(
            $doctorId,
            is_array($_POST['schedule'] ?? null) ? $_POST['schedule'] : [],
            is_array($_POST['service_ids'] ?? null) ? $_POST['service_ids'] : [],
            is_array($_POST['service_ea_ids'] ?? null) ? $_POST['service_ea_ids'] : [],
            isset($_POST['ea_provider_id']) ? (int)$_POST['ea_provider_id'] : null,
            $u['role'] === 'admin'
        );
        audit((int)$u['id'], 'doctor.availability.update', 'doctor', (string)$doctorId, ['sync_status' => $result['sync_status']]);
        $_SESSION['availability_notice'] = availability_result_notice($result);
    } catch (Throwable $error) {
        $_SESSION['availability_notice'] = '<p class="error">' . h($error->getMessage()) . '</p>';
    }
    header('Location: /doctor-availability?doctor_id=' . $doctorId);
    exit;
}

function availability_exception_save(): void {
    $u = require_user();
    check_csrf();
    $doctorId = availability_target($u);
    try {
        $result = availability_service()->saveException($doctorId, (string)($_POST['date'] ?? ''), (string)($_POST['start'] ?? ''), (string)($_POST['end'] ?? ''));
        audit((int)$u['id'], 'doctor.availability.exception.save', 'doctor', (string)$doctorId, ['date' => $_POST['date'] ?? null]);
        $_SESSION['availability_notice'] = availability_result_notice($result);
    } catch (Throwable $error) {
        $_SESSION['availability_notice'] = '<p class="error">' . h($error->getMessage()) . '</p>';
    }
    header('Location: /doctor-availability?doctor_id=' . $doctorId);
    exit;
}

function availability_exception_delete(): void {
    $u = require_user();
    check_csrf();
    $doctorId = availability_target($u);
    $result = availability_service()->deleteException($doctorId, (int)($_POST['exception_id'] ?? 0));
    audit((int)$u['id'], 'doctor.availability.exception.delete', 'doctor', (string)$doctorId);
    $_SESSION['availability_notice'] = availability_result_notice($result);
    header('Location: /doctor-availability?doctor_id=' . $doctorId);
    exit;
}

function availability_leave_save(): void {
    $u = require_user();
    check_csrf();
    $doctorId = availability_target($u);
    try {
        $result = availability_service()->addUnavailability($doctorId, (string)($_POST['start'] ?? ''), (string)($_POST['end'] ?? ''), (string)($_POST['reason'] ?? ''));
        audit((int)$u['id'], 'doctor.unavailability.create', 'doctor', (string)$doctorId, ['sync_status' => $result['sync_status']]);
        $_SESSION['availability_notice'] = availability_result_notice($result);
    } catch (Throwable $error) {
        $_SESSION['availability_notice'] = '<p class="error">' . h($error->getMessage()) . '</p>';
    }
    header('Location: /doctor-availability?doctor_id=' . $doctorId);
    exit;
}

function availability_leave_delete(): void {
    $u = require_user();
    check_csrf();
    $doctorId = availability_target($u);
    try {
        $result = availability_service()->releaseUnavailability($doctorId, (int)($_POST['period_id'] ?? 0));
        audit((int)$u['id'], 'doctor.unavailability.release', 'doctor', (string)$doctorId, ['sync_status' => $result['sync_status']]);
        $_SESSION['availability_notice'] = availability_result_notice($result);
    } catch (Throwable $error) {
        $_SESSION['availability_notice'] = '<p class="error">' . h($error->getMessage()) . '</p>';
    }
    header('Location: /doctor-availability?doctor_id=' . $doctorId);
    exit;
}

function login(): void {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        check_csrf();
        $stmt = db()->prepare("SELECT * FROM users WHERE email=? AND status='active'");
        $stmt->execute([strtolower(trim($_POST['email'] ?? ''))]);
        $u = $stmt->fetch();
        if ($u && password_verify($_POST['password'] ?? '', $u['password_hash'])) {
            $_SESSION['user'] = ['id' => (int)$u['id'], 'role' => $u['role'], 'name' => $u['name'], 'email' => $u['email']];
            audit((int)$u['id'], 'login', 'user', (string)$u['id']);
            header('Location: /dashboard');
            exit;
        }
        $error = '<p class="error">Invalid login.</p>';
    }
    $body = '<section class="login"><h1>Better Talk Portal</h1><p>Secure care operations workspace.</p>' . ($error ?? '') .
        '<form method="post"><input type="hidden" name="csrf" value="' . csrf() . '"><label>Email<input name="email" type="email" required></label><label>Password<input name="password" type="password" required></label><button>Sign in</button></form></section>';
    layout('Login', $body);
}

function cases(): void {
    $u = require_user();
    $where = '';
    $params = [];
    if ($u['role'] === 'doctor') {
        $where = 'WHERE c.assigned_doctor_id=?';
        $params[] = $u['id'];
    }
    $stmt = db()->prepare("SELECT c.*, p.name patient_name, p.plan_name, d.name doctor_name FROM cases c JOIN patients p ON p.id=c.patient_id LEFT JOIN users d ON d.id=c.assigned_doctor_id $where ORDER BY c.id DESC");
    $stmt->execute($params);
    $body = '<header><p>CASE MANAGEMENT</p><h1>Cases</h1></header><section class="panel"><table><tr><th>Case ID</th><th>Customer</th><th>Plan</th><th>Service</th><th>Doctor</th><th>Status</th></tr>';
    foreach ($stmt->fetchAll() as $row) {
        $body .= '<tr><td><a href="/case?id=' . (int)$row['id'] . '">' . h($row['case_code']) . '</a></td><td>' . h($row['patient_name']) . '</td><td>' . h($row['plan_name']) . '</td><td>' . h($row['service_type']) . '</td><td>' . h($row['doctor_name'] ?: 'Unassigned') . '</td><td>' . status_badge($row['status']) . '</td></tr>';
    }
    $body .= '</table></section>';
    layout('Cases', $body);
}

function case_detail(): void {
    $u = require_user();
    $id = (int)($_GET['id'] ?? 0);
    $stmt = db()->prepare("SELECT c.*, p.name patient_name, p.city, p.plan_name, p.notes, p.client_code,
        l.lead_code, l.source_channel lead_source, l.received_at lead_received_at, l.form_answers,
        d.name doctor_name,
        (SELECT py.id FROM payments py WHERE py.case_id=c.id AND py.status='paid' ORDER BY py.id DESC LIMIT 1) paid_payment_id
        FROM cases c
        JOIN patients p ON p.id=c.patient_id
        LEFT JOIN leads l ON l.id=c.lead_id
        LEFT JOIN users d ON d.id=c.assigned_doctor_id
        WHERE c.id=?");
    $stmt->execute([$id]);
    $case = $stmt->fetch();
    if (!$case || ($u['role'] === 'doctor' && (int)$case['assigned_doctor_id'] !== $u['id'])) {
        http_response_code(404);
        layout('Not found', '<h1>Case not found</h1>');
        return;
    }
    $calls = db()->prepare('SELECT * FROM calls WHERE case_id=? ORDER BY id DESC');
    $calls->execute([$id]);
    $body = '<header><p>CASE</p><h1>' . h($case['case_code']) . '</h1></header><section class="panel split"><div><h2>' . h($case['patient_name']) . '</h2><p><b>Client ID:</b> ' . h($case['client_code']) . '</p><p><b>Lead ID:</b> ' . h($case['lead_code'] ?: 'Manual / IVR case') . '</p><p><b>Lead source:</b> ' . h($case['lead_source'] ?: $case['source_channel']) . '</p><p><b>Received:</b> ' . h($case['lead_received_at'] ?: $case['created_at']) . '</p><p><b>Plan:</b> ' . h($case['plan_name']) . '</p><p><b>Service:</b> ' . h($case['service_type']) . '</p><p><b>Doctor:</b> ' . h($case['doctor_name'] ?: 'Unassigned') . '</p><p><b>Status:</b> ' . status_badge($case['status']) . '</p><p>' . h($case['summary']) . '</p></div><div class="callbox"><h2>Masked call</h2><p>Phone number is hidden. The backend calls the IVR API using this Case ID.</p>';
    if (can($u, ['admin','doctor','agent']) && $case['assigned_doctor_id']) {
        $body .= '<form method="post" action="/start-call"><input type="hidden" name="csrf" value="' . csrf() . '"><input type="hidden" name="case_id" value="' . (int)$case['id'] . '"><button>Start masked call</button></form>';
    }
    $body .= '</div></section>';
    if (can($u, ['admin','agent'])) {
        if ($case['paid_payment_id']) {
            $body .= '<section class="panel"><div class="panel-head"><div><h2>Appointment scheduling</h2><p>Payment verified. Select a doctor, permitted duration and live Easy!Appointments slot.</p></div><a class="buttonlink" href="/appointment-book?case_id=' . (int)$case['id'] . '">Schedule appointment</a></div></section>';
        } else {
            $body .= '<section class="panel"><h2>Appointment scheduling</h2><p class="muted">Verify payment before allocating a doctor or holding a slot.</p></section>';
        }
        $body .= '<section class="panel"><h2>Agent/admin controls</h2><form method="post" action="/case-update" class="form inline-form"><input type="hidden" name="csrf" value="' . csrf() . '"><input type="hidden" name="case_id" value="' . (int)$case['id'] . '"><label>Case status<select name="status">' . option_list(case_statuses_for($u), $case['status']) . '</select></label><label>Payment status<select name="payment_status"><option value="">No change</option><option value="pending">Pending</option><option value="paid">Paid</option><option value="failed">Failed</option><option value="refunded">Refunded</option></select></label><label>Amount PKR<input name="amount_pkr" type="number" step="1" min="0" placeholder="1200"></label><label>Payment ref<input name="payment_ref" placeholder="JazzCash / bank ref"></label><label>Internal note<textarea name="summary">' . h($case['summary']) . '</textarea></label><button>Save updates</button></form></section>';
    } elseif ($u['role'] === 'doctor') {
        $body .= '<section class="panel"><h2>Doctor status</h2><form method="post" action="/case-update" class="form inline-form"><input type="hidden" name="csrf" value="' . csrf() . '"><input type="hidden" name="case_id" value="' . (int)$case['id'] . '"><label>Status<select name="status">' . option_list(case_statuses_for($u), $case['status']) . '</select></label><button>Update status</button></form></section>';
    }
    $body .= '<section class="panel"><h2>Call history</h2><table><tr><th>Call ID</th><th>Provider</th><th>Status</th><th>Direction</th><th>Duration</th><th>Started</th></tr>';
    foreach ($calls->fetchAll() as $call) {
        $body .= '<tr><td>' . h($call['provider_call_id']) . '</td><td>' . h($call['provider']) . '</td><td>' . h($call['status']) . '</td><td>' . h($call['direction']) . '</td><td>' . h((string)$call['duration_seconds']) . ' sec</td><td>' . h($call['started_at']) . '</td></tr>';
    }
    $body .= '</table></section>';
    layout($case['case_code'], $body);
}

function case_update(): void {
    $u = require_user();
    check_csrf();
    $caseId = (int)($_POST['case_id'] ?? 0);
    $stmt = db()->prepare('SELECT * FROM cases WHERE id=?');
    $stmt->execute([$caseId]);
    $case = $stmt->fetch();
    if (!$case || ($u['role'] === 'doctor' && (int)$case['assigned_doctor_id'] !== $u['id'])) {
        http_response_code(403);
        exit('Forbidden');
    }
    $allowed = case_statuses_for($u);
    $status = $_POST['status'] ?? $case['status'];
    if (!isset($allowed[$status])) {
        $status = $case['status'];
    }
    $pdo = db();
    $pdo->beginTransaction();
    if (can($u, ['admin','agent'])) {
        $doctorId = $case['assigned_doctor_id'] ? (int)$case['assigned_doctor_id'] : null;
        $stmt = $pdo->prepare('UPDATE cases SET status=?, assigned_doctor_id=?, summary=? WHERE id=?');
        $stmt->execute([$status, $doctorId, trim($_POST['summary'] ?? ''), $caseId]);
        if (!empty($_POST['payment_status'])) {
            $stmt = $pdo->prepare('INSERT INTO payments (case_id, provider, payment_ref, amount_pkr, status) VALUES (?, ?, ?, ?, ?)');
            $stmt->execute([$caseId, 'manual', trim($_POST['payment_ref'] ?? ''), (float)($_POST['amount_pkr'] ?? 0), $_POST['payment_status']]);
        }
    } else {
        $stmt = $pdo->prepare('UPDATE cases SET status=? WHERE id=?');
        $stmt->execute([$status, $caseId]);
    }
    $pdo->commit();
    audit($u['id'], 'case.update', 'case', (string)$caseId, $_POST);
    header('Location: /case?id=' . $caseId);
    exit;
}

function appointments(): void {
    $u = require_user();
    if ($u['role'] === 'doctor') {
        $stmt = db()->prepare('SELECT a.*, c.case_code, p.name patient_name, d.name doctor_name FROM appointments a JOIN cases c ON c.id=a.case_id JOIN patients p ON p.id=a.patient_id JOIN users d ON d.id=a.doctor_id WHERE a.doctor_id=? ORDER BY a.scheduled_at DESC LIMIT 100');
        $stmt->execute([$u['id']]);
        $rows = $stmt->fetchAll();
    } else {
        $rows = db()->query('SELECT a.*, c.case_code, p.name patient_name, d.name doctor_name FROM appointments a JOIN cases c ON c.id=a.case_id JOIN patients p ON p.id=a.patient_id JOIN users d ON d.id=a.doctor_id ORDER BY a.scheduled_at DESC LIMIT 100')->fetchAll();
    }
    $notice = isset($_GET['created']) ? '<p class="notice">Appointment ' . h($_GET['created']) . ' scheduled.</p>' : '';
    $body = '<header><p>SCHEDULE</p><h1>Appointments</h1></header>' . $notice . '<section class="panel"><table><tr><th>Appointment</th><th>Time (PKT)</th><th>Duration</th><th>Case</th><th>Patient</th><th>Doctor</th><th>Status</th><th>Sync</th></tr>';
    foreach ($rows as $row) {
        $sync = status_badge($row['sync_status'] ?? 'not_configured');
        if ($u['role'] === 'admin' && ($row['sync_status'] ?? '') === 'failed') {
            $sync .= '<form method="post" action="/appointment-sync" class="compact-form"><input type="hidden" name="csrf" value="' . csrf() . '"><input type="hidden" name="appointment_id" value="' . (int)$row['id'] . '"><button>Retry</button></form>';
        }
        $body .= '<tr><td>' . h($row['appointment_code'] ?? ('#' . $row['id'])) . '</td><td>' . h(AppointmentService::utcToPakistan($row['scheduled_at'])) . '</td><td>' . h((string)$row['duration_minutes']) . ' min</td><td><a href="/case?id=' . (int)$row['case_id'] . '">' . h($row['case_code']) . '</a></td><td>' . h($row['patient_name']) . '</td><td>' . h($row['doctor_name']) . '</td><td>' . status_badge($row['status']) . '</td><td>' . $sync . '</td></tr>';
    }
    $body .= '</table></section>';
    layout('Appointments', $body);
}

function appointment_book(): void {
    $u = require_user();
    if (!can($u, ['admin', 'agent'])) {
        http_response_code(403);
        exit('Forbidden');
    }
    $caseId = (int)($_GET['case_id'] ?? 0);
    $stmt = db()->prepare("SELECT c.id,c.case_code,p.name patient_name,
        (SELECT py.id FROM payments py WHERE py.case_id=c.id AND py.status='paid' ORDER BY py.id DESC LIMIT 1) paid_payment_id
        FROM cases c JOIN patients p ON p.id=c.patient_id WHERE c.id=?");
    $stmt->execute([$caseId]);
    $case = $stmt->fetch();
    if (!$case) {
        http_response_code(404);
        layout('Not found', '<h1>Case not found</h1>');
        return;
    }
    if (!$case['paid_payment_id']) {
        http_response_code(409);
        layout('Payment required', '<section class="panel"><h1>Payment required</h1><p>Verify payment before allocating a doctor or holding a slot.</p><a href="/case?id=' . $caseId . '">Return to case</a></section>');
        return;
    }

    $rows = db()->query("SELECT u.id doctor_id,u.name,dp.doctor_code,dp.specialties,
        s.id service_id,s.duration_minutes
        FROM users u
        JOIN doctor_profiles dp ON dp.user_id=u.id
        JOIN doctor_appointment_services ds ON ds.doctor_id=u.id AND ds.active=1
        JOIN appointment_services s ON s.id=ds.appointment_service_id AND s.active=1
        WHERE u.role='doctor' AND u.status='active'
        ORDER BY u.name,s.duration_minutes")->fetchAll();
    $choices = [];
    foreach ($rows as $row) {
        $key = (int)$row['doctor_id'] . ':' . (int)$row['service_id'];
        $choices[$key] = $row['name'] . ' (' . $row['doctor_code'] . ') — ' . $row['duration_minutes'] . ' minutes' . ($row['specialties'] ? ' — ' . $row['specialties'] : '');
    }
    $selection = (string)($_GET['selection'] ?? '');
    $date = (string)($_GET['date'] ?? (new DateTimeImmutable('tomorrow', new DateTimeZone(AppointmentService::DISPLAY_TIMEZONE)))->format('Y-m-d'));
    $body = '<header><p>APPOINTMENT</p><h1>Schedule ' . h($case['case_code']) . '</h1></header><section class="panel"><p><b>Client:</b> ' . h($case['patient_name']) . '</p><form method="get" action="/appointment-book" class="form inline-form"><input type="hidden" name="case_id" value="' . $caseId . '"><label>Doctor and duration<select name="selection" required><option value="">Select</option>' . option_list($choices, $selection) . '</select></label><label>Date (PKT)<input type="date" name="date" value="' . h($date) . '" required></label><button>Show available slots</button></form></section>';

    if ($selection !== '' && preg_match('/^(\d+):(\d+)$/', $selection, $match)) {
        try {
            $slots = appointment_service()->availability((int)$match[1], (int)$match[2], $date);
            $body .= '<section class="panel"><h2>Available slots</h2><div class="slot-grid">';
            foreach ($slots as $slot) {
                $body .= '<form method="post" action="/appointment-hold"><input type="hidden" name="csrf" value="' . csrf() . '"><input type="hidden" name="case_id" value="' . $caseId . '"><input type="hidden" name="doctor_id" value="' . (int)$match[1] . '"><input type="hidden" name="service_id" value="' . (int)$match[2] . '"><input type="hidden" name="date" value="' . h($date) . '"><input type="hidden" name="time" value="' . h($slot) . '"><button>' . h($slot) . '</button></form>';
            }
            if (!$slots) {
                $body .= '<p>No available slots for this date.</p>';
            }
            $body .= '</div></section>';
        } catch (Throwable $error) {
            $body .= '<p class="error">' . h($error->getMessage()) . '</p>';
        }
    }
    layout('Schedule appointment', $body);
}

function appointment_hold(): void {
    $u = require_user();
    if (!can($u, ['admin', 'agent'])) {
        http_response_code(403);
        exit('Forbidden');
    }
    check_csrf();
    try {
        $hold = appointment_service()->createHold(
            (int)($_POST['case_id'] ?? 0),
            (int)($_POST['doctor_id'] ?? 0),
            (int)($_POST['service_id'] ?? 0),
            (int)$u['id'],
            (string)($_POST['date'] ?? ''),
            (string)($_POST['time'] ?? '')
        );
        audit((int)$u['id'], 'appointment.hold.created', 'case', (string)($_POST['case_id'] ?? ''), [
            'expires_at' => $hold['expires_at'],
            'start_at' => $hold['start_at'],
        ]);
        $body = '<header><p>15-MINUTE HOLD</p><h1>Confirm appointment</h1></header><section class="panel"><p class="notice">This slot is held until ' . h(AppointmentService::utcToPakistan($hold['expires_at'])) . ' PKT.</p><p><b>Time:</b> ' . h(AppointmentService::utcToPakistan($hold['start_at'])) . ' PKT</p><p><b>Duration:</b> ' . (int)$hold['duration_minutes'] . ' minutes</p><form method="post" action="/appointment-schedule"><input type="hidden" name="csrf" value="' . csrf() . '"><input type="hidden" name="hold_token" value="' . h($hold['token']) . '"><button>Schedule</button></form></section>';
        layout('Confirm appointment', $body);
    } catch (Throwable $error) {
        http_response_code(409);
        layout('Slot unavailable', '<section class="panel"><h1>Could not hold slot</h1><p class="error">' . h($error->getMessage()) . '</p><a href="/case?id=' . (int)($_POST['case_id'] ?? 0) . '">Return to case</a></section>');
    }
}

function appointment_schedule(): void {
    $u = require_user();
    if (!can($u, ['admin', 'agent'])) {
        http_response_code(403);
        exit('Forbidden');
    }
    check_csrf();
    try {
        $result = appointment_service()->confirmHold((string)($_POST['hold_token'] ?? ''), (int)$u['id'], (string)$u['role']);
        audit((int)$u['id'], 'appointment.scheduled', 'appointment', (string)$result['appointment_id'], $result);
        header('Location: /appointments?created=' . rawurlencode((string)$result['appointment_code']));
        exit;
    } catch (Throwable $error) {
        http_response_code(409);
        layout('Scheduling failed', '<section class="panel"><h1>Scheduling failed</h1><p class="error">' . h($error->getMessage()) . '</p><a href="/appointments">Appointments</a></section>');
    }
}

function appointment_sync(): void {
    $u = require_user();
    if (!can($u, ['admin'])) {
        http_response_code(403);
        exit('Forbidden');
    }
    check_csrf();
    $appointmentId = (int)($_POST['appointment_id'] ?? 0);
    $result = appointment_service()->syncAppointment($appointmentId);
    audit((int)$u['id'], 'appointment.sync.retry', 'appointment', (string)$appointmentId, $result);
    header('Location: /appointments');
    exit;
}

function scheduler_health(): void {
    $u = require_user();
    if (!can($u, ['admin'])) {
        http_response_code(403);
        exit('Forbidden');
    }
    global $config;
    $client = new EasyAppointmentsClient($config['easyappointments'] ?? []);
    $body = '<header><p>ADMIN</p><h1>Scheduler Health</h1></header><section class="panel">';
    if (!$client->isConfigured()) {
        $body .= '<p class="error">Easy!Appointments API credentials are not configured in the Better Talk portal yet.</p>';
        $body .= '<p>Expected scheduler: <code>' . h((string)($config['easyappointments']['base_url'] ?? '')) . '</code></p>';
    } else {
        try {
            $result = $client->healthCheck();
            $body .= '<p class="notice">Scheduler API connection and authentication succeeded.</p>';
            $body .= '<p><b>API authenticated:</b> ' . (!empty($result['api_authenticated']) ? 'Yes' : 'No') . '</p>';
            $body .= '<p><b>Sample services returned:</b> ' . (int)($result['sample_service_count'] ?? 0) . '</p>';
        } catch (Throwable $error) {
            http_response_code(502);
            $body .= '<p class="error">Scheduler API check failed: ' . h($error->getMessage()) . '</p>';
        }
    }
    $body .= '</section>';
    layout('Scheduler Health', $body);
}

function calls(): void {
    $u = require_user();
    if ($u['role'] === 'doctor') {
        $stmt = db()->prepare('SELECT cl.*, c.case_code, p.name patient_name, d.name doctor_name FROM calls cl LEFT JOIN cases c ON c.id=cl.case_id LEFT JOIN patients p ON p.id=cl.patient_id LEFT JOIN users d ON d.id=cl.doctor_id WHERE cl.doctor_id=? ORDER BY cl.id DESC LIMIT 100');
        $stmt->execute([$u['id']]);
        $rows = $stmt->fetchAll();
    } else {
        $rows = db()->query('SELECT cl.*, c.case_code, p.name patient_name, d.name doctor_name FROM calls cl LEFT JOIN cases c ON c.id=cl.case_id LEFT JOIN patients p ON p.id=cl.patient_id LEFT JOIN users d ON d.id=cl.doctor_id ORDER BY cl.id DESC LIMIT 100')->fetchAll();
    }
    $body = '<header><p>IVR</p><h1>Calls & IVR</h1></header><section class="panel"><table><tr><th>Call ID</th><th>Case ID</th><th>Customer</th><th>Doctor</th><th>Provider</th><th>Status</th></tr>';
    foreach ($rows as $row) {
        $body .= '<tr><td>' . h($row['provider_call_id']) . '</td><td>' . h($row['case_code']) . '</td><td>' . h($row['patient_name']) . '</td><td>' . h($row['doctor_name'] ?: '-') . '</td><td>' . h($row['provider']) . '</td><td>' . h($row['status']) . '</td></tr>';
    }
    $body .= '</table></section><section class="panel"><h2>Provider adapter</h2><p>Current mode: mock. Jazz/Zong API keys can be added in config without changing the portal workflow.</p></section>';
    layout('Calls', $body);
}

function payments(): void {
    $u = require_user();
    if ($u['role'] === 'doctor') {
        $stmt = db()->prepare('SELECT py.*, c.case_code FROM payments py JOIN cases c ON c.id=py.case_id WHERE c.assigned_doctor_id=? ORDER BY py.id DESC');
        $stmt->execute([$u['id']]);
        $rows = $stmt->fetchAll();
    } else {
        $rows = db()->query('SELECT py.*, c.case_code FROM payments py JOIN cases c ON c.id=py.case_id ORDER BY py.id DESC')->fetchAll();
    }
    $body = '<header><p>PAYMENTS</p><h1>Payments</h1></header><section class="panel"><table><tr><th>Case</th><th>Provider</th><th>Reference</th><th>Amount</th><th>Status</th></tr>';
    foreach ($rows as $row) {
        $body .= '<tr><td>' . h($row['case_code']) . '</td><td>' . h($row['provider']) . '</td><td>' . h($row['payment_ref']) . '</td><td>Rs. ' . h((string)$row['amount_pkr']) . '</td><td>' . status_badge($row['status']) . '</td></tr>';
    }
    $body .= '</table></section>';
    layout('Payments', $body);
}

function new_case(): void {
    $u = require_user();
    if (!can($u, ['admin','agent'])) {
        http_response_code(403);
        exit('Forbidden');
    }
    $pdo = db();
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        check_csrf();
        $pdo->beginTransaction();
        $stmt = $pdo->prepare('INSERT INTO patients (name, phone, city, plan_name, notes) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([trim($_POST['name']), trim($_POST['phone']), trim($_POST['city']), trim($_POST['plan_name']), trim($_POST['notes'])]);
        $patientId = (int)$pdo->lastInsertId();
        $caseCode = 'BT-' . date('Ymd') . '-' . str_pad((string)$patientId, 4, '0', STR_PAD_LEFT);
        $stmt = $pdo->prepare('INSERT INTO cases (case_code, patient_id, assigned_doctor_id, assigned_agent_id, service_type, status, summary) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $doctorId = $_POST['doctor_id'] ? (int)$_POST['doctor_id'] : null;
        $stmt->execute([$caseCode, $patientId, $doctorId, $u['id'], trim($_POST['service_type']), 'new', trim($_POST['summary'])]);
        $caseId = (int)$pdo->lastInsertId();
        $pdo->commit();
        audit($u['id'], 'case.create', 'case', (string)$caseId);
        header('Location: /case?id=' . $caseId);
        exit;
    }
    $doctors = $pdo->query("SELECT id,name FROM users WHERE role='doctor' AND status='active' ORDER BY name")->fetchAll();
    $options = '<option value="">Assign later</option>';
    foreach ($doctors as $doctor) {
        $options .= '<option value="' . (int)$doctor['id'] . '">' . h($doctor['name']) . '</option>';
    }
    $body = '<header><p>INTAKE</p><h1>New Case</h1></header><section class="panel"><form method="post" class="form"><input type="hidden" name="csrf" value="' . csrf() . '"><label>Customer name<input name="name" required></label><label>Customer phone<input name="phone" required></label><label>City<input name="city"></label><label>Plan<input name="plan_name"></label><label>Service type<input name="service_type" required></label><label>Assign doctor<select name="doctor_id">' . $options . '</select></label><label>Case summary<textarea name="summary"></textarea></label><label>Private notes<textarea name="notes"></textarea></label><button>Create case</button></form></section>';
    layout('New Case', $body);
}

function start_call(): void {
    $u = require_user();
    check_csrf();
    $caseId = (int)($_POST['case_id'] ?? 0);
    $stmt = db()->prepare('SELECT c.*, p.phone, p.id patient_id FROM cases c JOIN patients p ON p.id=c.patient_id WHERE c.id=?');
    $stmt->execute([$caseId]);
    $case = $stmt->fetch();
    if (!$case || ($u['role'] === 'doctor' && (int)$case['assigned_doctor_id'] !== $u['id'])) {
        http_response_code(403);
        exit('Forbidden');
    }
    $providerCallId = 'MOCK-' . date('YmdHis') . '-' . $caseId;
    $stmt = db()->prepare("INSERT INTO calls (provider, provider_call_id, case_id, patient_id, doctor_id, agent_id, direction, from_role, to_role, status, raw_payload) VALUES ('mock', ?, ?, ?, ?, ?, 'bridge', ?, 'patient', 'queued', ?)");
    $doctorId = (int)($case['assigned_doctor_id'] ?: $u['id']);
    $stmt->execute([$providerCallId, $caseId, (int)$case['patient_id'], $doctorId, $case['assigned_agent_id'], $u['role'], json_encode(['case_code' => $case['case_code'], 'masked' => true])]);
    audit($u['id'], 'call.start.masked', 'case', (string)$caseId, ['provider_call_id' => $providerCallId]);
    header('Location: /case?id=' . $caseId);
    exit;
}

function normalize_phone(string $phone): string {
    $digits = preg_replace('/\D+/', '', $phone) ?: '';
    if (str_starts_with($digits, '00')) {
        $digits = substr($digits, 2);
    }
    if (str_starts_with($digits, '0')) {
        $digits = '92' . substr($digits, 1);
    }
    return $digits;
}

function lead_code(int $leadId): string {
    return 'LD-' . date('Ymd') . '-' . str_pad((string)$leadId, 6, '0', STR_PAD_LEFT);
}

function client_code(int $clientId): string {
    return 'BTC-' . date('Y') . '-' . str_pad((string)$clientId, 6, '0', STR_PAD_LEFT);
}

function lead_sync_failure(string $dedupeKey, string $message, array $summary): void {
    try {
        $stmt = db()->prepare('INSERT INTO lead_sync_failures (source_channel, dedupe_key, error_message, request_summary) VALUES (?, ?, ?, ?)');
        $stmt->execute(['website', $dedupeKey, substr($message, 0, 500), json_encode($summary)]);
    } catch (Throwable $ignored) {
        error_log('Better Talk lead sync failure: ' . $message);
    }
}

function leads_page(): void {
    $u = require_user();
    if (!can($u, ['admin', 'agent'])) {
        http_response_code(403);
        exit('Forbidden');
    }
    $stmt = db()->query('SELECT l.*, p.client_code, p.name patient_name, p.phone, c.case_code FROM leads l JOIN patients p ON p.id=l.patient_id JOIN cases c ON c.id=l.case_id ORDER BY l.received_at DESC LIMIT 200');
    $body = '<header><p>WEBSITE INTAKE</p><h1>Leads</h1></header><section class="panel"><table><tr><th>Lead ID</th><th>Client ID</th><th>Client</th><th>Case</th><th>Source</th><th>Status</th><th>Received (PKT)</th></tr>';
    foreach ($stmt->fetchAll() as $row) {
        $body .= '<tr><td>' . h($row['lead_code']) . '</td><td>' . h($row['client_code']) . '</td><td><a href="/case?id=' . (int)$row['case_id'] . '">' . h($row['patient_name']) . '</a></td><td>' . h($row['case_code']) . '</td><td>' . h($row['source_channel']) . '</td><td>' . status_badge($row['status']) . '</td><td>' . h($row['received_at']) . '</td></tr>';
    }
    $body .= '</table></section>';
    layout('Website Leads', $body);
}

function lead_intake(): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        exit('Method not allowed');
    }
    $name = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $message = trim($_POST['message'] ?? '');
    $normalizedPhone = normalize_phone($phone);
    if ($name === '' || strlen($normalizedPhone) < 10) {
        header('Location: https://www.bettertalk.pk/contact?lead=missing');
        exit;
    }
    $receivedAt = date('Y-m-d H:i:s');
    $campaign = array_filter([
        'utm_source' => trim($_POST['utm_source'] ?? ''),
        'utm_medium' => trim($_POST['utm_medium'] ?? ''),
        'utm_campaign' => trim($_POST['utm_campaign'] ?? ''),
        'utm_content' => trim($_POST['utm_content'] ?? ''),
    ]);
    $referrer = substr(trim($_POST['referrer'] ?? ($_SERVER['HTTP_REFERER'] ?? '')), 0, 500);
    $answers = ['name' => $name, 'phone' => $phone, 'email' => $email, 'message' => $message];
    // Same source, contact details and message are one accidental submission for five minutes.
    // A changed enquiry or a later enquiry still creates its own Lead ID.
    $dedupeKey = hash('sha256', implode('|', ['website', $normalizedPhone, strtolower($email), $message, (string)floor(time() / 300)]));
    $pdo = db();
    try {
        $pdo->beginTransaction();
        $existing = $pdo->prepare('SELECT lead_code FROM leads WHERE dedupe_key=? LIMIT 1 FOR UPDATE');
        $existing->execute([$dedupeKey]);
        if ($existing->fetch()) {
            $pdo->commit();
            header('Location: https://www.bettertalk.pk/contact?lead=sent');
            exit;
        }
        $match = $pdo->prepare('SELECT id, client_code FROM patients WHERE phone_normalized=? ORDER BY id ASC LIMIT 2 FOR UPDATE');
        $match->execute([$normalizedPhone]);
        $matches = $match->fetchAll();
        if (count($matches) === 1) {
            $patientId = (int)$matches[0]['id'];
            if (!$matches[0]['client_code']) {
                $pdo->prepare('UPDATE patients SET client_code=? WHERE id=?')->execute([client_code($patientId), $patientId]);
            }
        } else {
            $note = 'Website contact email: ' . $email;
            if (count($matches) > 1) {
                $note .= "\nExisting shared/ambiguous phone record retained; new client created for safe review.";
            }
            $stmt = $pdo->prepare('INSERT INTO patients (name, phone, phone_normalized, email, city, plan_name, notes) VALUES (?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([$name, $phone, $normalizedPhone, $email, '', 'Website lead', $note]);
            $patientId = (int)$pdo->lastInsertId();
            $pdo->prepare('UPDATE patients SET client_code=? WHERE id=?')->execute([client_code($patientId), $patientId]);
        }
        $caseCode = 'BT-' . date('Ymd') . '-' . str_pad((string)$patientId, 4, '0', STR_PAD_LEFT) . '-' . date('His');
        $stmt = $pdo->prepare('INSERT INTO cases (case_code, patient_id, service_type, source_channel, status, summary) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->execute([$caseCode, $patientId, 'Website inquiry', 'website', 'new', $message]);
        $caseId = (int)$pdo->lastInsertId();
        $stmt = $pdo->prepare('INSERT INTO leads (lead_code, patient_id, case_id, source_channel, source_detail, form_answers, referrer_url, campaign_data, dedupe_key, received_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute(['PENDING', $patientId, $caseId, 'website', 'public contact form', json_encode($answers), $referrer ?: null, json_encode($campaign), $dedupeKey, $receivedAt]);
        $leadId = (int)$pdo->lastInsertId();
        $code = lead_code($leadId);
        $pdo->prepare('UPDATE leads SET lead_code=? WHERE id=?')->execute([$code, $leadId]);
        $pdo->prepare('UPDATE cases SET lead_id=? WHERE id=?')->execute([$leadId, $caseId]);
        $pdo->commit();
        audit(null, 'lead.website.created', 'lead', (string)$leadId, ['case_id' => $caseId, 'source' => 'website', 'received_at' => $receivedAt, 'existing_client' => count($matches) === 1]);
        header('Location: https://www.bettertalk.pk/contact?lead=sent');
        exit;
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        lead_sync_failure($dedupeKey, $error->getMessage(), ['name' => $name, 'phone' => $normalizedPhone, 'email' => $email]);
        http_response_code(503);
        echo 'We could not save your request. Please try again shortly.';
        exit;
    }
}

$r = route();
if ($r === 'login') login();
elseif ($r === 'logout') { session_destroy(); header('Location: /login'); }
elseif ($r === 'lead-intake') lead_intake();
elseif ($r === 'leads') leads_page();
elseif ($r === 'dashboard') dashboard();
elseif ($r === 'users') users_page();
elseif ($r === 'user-new') user_new();
elseif ($r === 'doctor-availability') doctor_availability();
elseif ($r === 'availability-save') availability_save();
elseif ($r === 'availability-exception-save') availability_exception_save();
elseif ($r === 'availability-exception-delete') availability_exception_delete();
elseif ($r === 'availability-leave-save') availability_leave_save();
elseif ($r === 'availability-leave-delete') availability_leave_delete();
elseif ($r === 'cases') cases();
elseif ($r === 'case') case_detail();
elseif ($r === 'case-update') case_update();
elseif ($r === 'appointments') appointments();
elseif ($r === 'appointment-book') appointment_book();
elseif ($r === 'appointment-hold') appointment_hold();
elseif ($r === 'appointment-schedule') appointment_schedule();
elseif ($r === 'appointment-sync') appointment_sync();
elseif ($r === 'scheduler-health') scheduler_health();
elseif ($r === 'calls') calls();
elseif ($r === 'payments') payments();
elseif ($r === 'new-case') new_case();
elseif ($r === 'start-call') start_call();
else { http_response_code(404); layout('Not found', '<h1>Not found</h1>'); }
