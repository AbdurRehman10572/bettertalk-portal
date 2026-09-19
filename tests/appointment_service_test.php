<?php
declare(strict_types=1);

require_once __DIR__ . '/../portal/app/AppointmentService.php';
require_once __DIR__ . '/../portal/app/AvailabilityService.php';

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

$plan = AvailabilityService::normalizeWorkingPlan([
    'monday' => [
        'enabled' => '1',
        'start' => '09:00',
        'end' => '18:00',
        'break_start' => ['13:00', '16:00'],
        'break_end' => ['14:00', '16:15'],
    ],
]);
expect_true($plan['monday']['start'] === '09:00', 'Working day start must be retained.');
expect_true(count($plan['monday']['breaks']) === 2, 'Multiple breaks must be retained.');
expect_true($plan['tuesday'] === null, 'Disabled days must be unavailable.');

$invalidBreakFailed = false;
try {
    AvailabilityService::normalizeWorkingPlan([
        'monday' => [
            'enabled' => '1',
            'start' => '09:00',
            'end' => '17:00',
            'break_start' => ['08:30'],
            'break_end' => ['09:15'],
        ],
    ]);
} catch (InvalidArgumentException $error) {
    $invalidBreakFailed = true;
}
expect_true($invalidBreakFailed, 'Breaks outside working hours must be rejected.');

$exception = AvailabilityService::normalizeException('2026-09-25', '12:00', '16:00');
expect_true($exception['date'] === '2026-09-25', 'Date exceptions must retain the selected date.');

$leave = AvailabilityService::normalizeUnavailability('2026-09-25T12:00', '2026-09-25T16:00');
expect_true($leave['utc_start']->format('Y-m-d H:i:s') === '2026-09-25 07:00:00', 'PKT leave must be stored in UTC.');

AvailabilityService::validateDurationPair(30, 30);

$durationMismatchFailed = false;
try {
    AvailabilityService::validateDurationPair(30, 60);
} catch (RuntimeException $error) {
    $durationMismatchFailed = true;
}
expect_true($durationMismatchFailed, 'Mismatched scheduler service durations must be rejected.');

$invalidDurationFailed = false;
try {
    AvailabilityService::validateDurationPair(0, 30);
} catch (InvalidArgumentException $error) {
    $invalidDurationFailed = true;
}
expect_true($invalidDurationFailed, 'Non-positive service durations must be rejected.');

$invalidLeaveFailed = false;
try {
    AvailabilityService::normalizeUnavailability('2026-09-25T16:00', '2026-09-25T12:00');
} catch (InvalidArgumentException $error) {
    $invalidLeaveFailed = true;
}
expect_true($invalidLeaveFailed, 'Leave with an invalid range must be rejected.');

echo "appointment_service_test: OK\n";
