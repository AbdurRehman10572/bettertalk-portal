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
    return db()->query("SELECT u.id,u.name,dp.specialties FROM users u LEFT JOIN doctor_profiles dp ON dp.user_id=u.id WHERE u.role='doctor' AND u.status='active' ORDER BY u.name")->fetchAll();
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
            $stmt = $pdo->prepare('INSERT INTO doctor_profiles (user_id, specialties, license_ref, ivr_extension, availability_note) VALUES (?, ?, ?, ?, ?)');
            $stmt->execute([$userId, trim($_POST['specialties']), trim($_POST['license_ref']), trim($_POST['ivr_extension']), trim($_POST['availability_note'])]);
        }
        $pdo->commit();
        audit($u['id'], 'user.create', 'user', (string)$userId);
        $created = '<p class="notice">Created. Temporary password: <b>' . h($password) . '</b></p>';
    }
    $body = '<header><p>ADMIN</p><h1>Add user / doctor</h1></header><section class="panel">' . $created . '<form method="post" class="form"><input type="hidden" name="csrf" value="' . csrf() . '"><label>Name<input name="name" required></label><label>Email<input name="email" type="email" required></label><label>Phone<input name="phone"></label><label>Role<select name="role"><option value="agent">Agent</option><option value="doctor">Doctor</option><option value="admin">Admin</option><option value="patient">Patient</option></select></label><label>Status<select name="status"><option value="active">Active</option><option value="inactive">Inactive</option></select></label><label>Password optional<input name="password" placeholder="Leave blank to generate"></label><h2>Doctor details</h2><label>Specialties<input name="specialties" placeholder="Marriage, anxiety, student counselling"></label><label>License/reference<input name="license_ref"></label><label>IVR extension<input name="ivr_extension"></label><label>Availability note<input name="availability_note" placeholder="Weekdays 10am-6pm"></label><button>Create user</button></form></section>';
    layout('Add user', $body);
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
    $stmt = db()->prepare('SELECT c.*, p.name patient_name, p.city, p.plan_name, p.notes, p.client_code, l.lead_code, l.source_channel lead_source, l.received_at lead_received_at, l.form_answers, d.name doctor_name FROM cases c JOIN patients p ON p.id=c.patient_id LEFT JOIN leads l ON l.id=c.lead_id LEFT JOIN users d ON d.id=c.assigned_doctor_id WHERE c.id=?');
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
        $doctorOptions = ['' => 'Unassigned'];
        foreach (doctors() as $doctor) {
            $doctorOptions[$doctor['id']] = $doctor['name'] . ($doctor['specialties'] ? ' - ' . $doctor['specialties'] : '');
        }
        $body .= '<section class="panel"><h2>Agent/admin controls</h2><form method="post" action="/case-update" class="form inline-form"><input type="hidden" name="csrf" value="' . csrf() . '"><input type="hidden" name="case_id" value="' . (int)$case['id'] . '"><label>Case status<select name="status">' . option_list(case_statuses_for($u), $case['status']) . '</select></label><label>Assign doctor<select name="doctor_id">' . option_list($doctorOptions, $case['assigned_doctor_id']) . '</select></label><label>Payment status<select name="payment_status"><option value="">No change</option><option value="pending">Pending</option><option value="paid">Paid</option><option value="failed">Failed</option><option value="refunded">Refunded</option></select></label><label>Amount PKR<input name="amount_pkr" type="number" step="1" min="0" placeholder="1200"></label><label>Payment ref<input name="payment_ref" placeholder="JazzCash / bank ref"></label><label>Appointment time<input name="scheduled_at" type="datetime-local"></label><label>Duration<input name="duration_minutes" type="number" min="10" value="30"></label><label>Internal note<textarea name="summary">' . h($case['summary']) . '</textarea></label><button>Save updates</button></form></section>';
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
        $doctorId = $_POST['doctor_id'] !== '' ? (int)$_POST['doctor_id'] : null;
        $stmt = $pdo->prepare('UPDATE cases SET status=?, assigned_doctor_id=?, summary=? WHERE id=?');
        $stmt->execute([$status, $doctorId, trim($_POST['summary'] ?? ''), $caseId]);
        if (!empty($_POST['payment_status'])) {
            $stmt = $pdo->prepare('INSERT INTO payments (case_id, provider, payment_ref, amount_pkr, status) VALUES (?, ?, ?, ?, ?)');
            $stmt->execute([$caseId, 'manual', trim($_POST['payment_ref'] ?? ''), (float)($_POST['amount_pkr'] ?? 0), $_POST['payment_status']]);
        }
        if (!empty($_POST['scheduled_at']) && $doctorId) {
            $stmt = $pdo->prepare('SELECT patient_id FROM cases WHERE id=?');
            $stmt->execute([$caseId]);
            $patientId = (int)$stmt->fetchColumn();
            $stmt = $pdo->prepare('INSERT INTO appointments (case_id, patient_id, doctor_id, agent_id, scheduled_at, duration_minutes, status) VALUES (?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([$caseId, $patientId, $doctorId, $u['id'], str_replace('T', ' ', $_POST['scheduled_at']) . ':00', (int)($_POST['duration_minutes'] ?: 30), 'confirmed']);
            $pdo->prepare("UPDATE cases SET status='scheduled' WHERE id=?")->execute([$caseId]);
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
    $body = '<header><p>SCHEDULE</p><h1>Appointments</h1></header><section class="panel"><table><tr><th>Time</th><th>Case</th><th>Patient</th><th>Doctor</th><th>Status</th></tr>';
    foreach ($rows as $row) {
        $body .= '<tr><td>' . h($row['scheduled_at']) . '</td><td>' . h($row['case_code']) . '</td><td>' . h($row['patient_name']) . '</td><td>' . h($row['doctor_name']) . '</td><td>' . status_badge($row['status']) . '</td></tr>';
    }
    $body .= '</table></section>';
    layout('Appointments', $body);
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
elseif ($r === 'cases') cases();
elseif ($r === 'case') case_detail();
elseif ($r === 'case-update') case_update();
elseif ($r === 'appointments') appointments();
elseif ($r === 'calls') calls();
elseif ($r === 'payments') payments();
elseif ($r === 'new-case') new_case();
elseif ($r === 'start-call') start_call();
else { http_response_code(404); layout('Not found', '<h1>Not found</h1>'); }
