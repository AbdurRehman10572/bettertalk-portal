<?php
declare(strict_types=1);

require_once __DIR__ . '/../portal/app/AppointmentService.php';

function expect_true(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$utc = new DateTimeZone('UTC');
$firstStart = new DateTimeImmutable('2026-09-20 10:00:00', $utc);
$firstEnd = new DateTimeImmutable('2026-09-20 10:30:00', $utc);

expect_true(
    AppointmentService::intervalsOverlap(
        $firstStart,
        $firstEnd,
        new DateTimeImmutable('2026-09-20 10:15:00', $utc),
        new DateTimeImmutable('2026-09-20 10:45:00', $utc)
    ),
    'Overlapping appointments must conflict.'
);

expect_true(
    !AppointmentService::intervalsOverlap(
        $firstStart,
        $firstEnd,
        new DateTimeImmutable('2026-09-20 10:30:00', $utc),
        new DateTimeImmutable('2026-09-20 11:00:00', $utc)
    ),
    'Adjacent appointments must not conflict.'
);

$converted = AppointmentService::pakistanToUtc('2026-09-20', '15:00');
expect_true($converted->format('Y-m-d H:i:s') === '2026-09-20 10:00:00', 'PKT must convert to UTC correctly.');

$failed = false;
try {
    AppointmentService::pakistanToUtc('not-a-date', '15:00');
} catch (InvalidArgumentException $error) {
    $failed = true;
}
expect_true($failed, 'Invalid appointment dates must be rejected.');

$unconfigured = new EasyAppointmentsClient([]);
expect_true(!$unconfigured->isConfigured(), 'An empty Easy!Appointments configuration must be disabled.');

$apiKeyConfigured = new EasyAppointmentsClient(['base_url' => 'https://schedule.example.test', 'api_key' => 'test-key']);
expect_true($apiKeyConfigured->isConfigured(), 'API-key configuration must be accepted.');

$basicConfigured = new EasyAppointmentsClient([
    'base_url' => 'https://schedule.example.test',
    'username' => 'admin',
    'password' => 'secret',
]);
expect_true($basicConfigured->isConfigured(), 'Basic-auth configuration must be accepted.');

echo "appointment_service_test: OK\n";
