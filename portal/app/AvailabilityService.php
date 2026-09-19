<?php
declare(strict_types=1);

require_once __DIR__ . '/EasyAppointmentsClient.php';

final class AvailabilityService
{
    public const TIMEZONE = 'Asia/Karachi';
    public const DAYS = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];

    private PDO $pdo;
    private EasyAppointmentsClient $easyAppointments;

    public function __construct(PDO $pdo, array $config)
    {
        $this->pdo = $pdo;
        $this->easyAppointments = new EasyAppointmentsClient($config['easyappointments'] ?? []);
    }

    public static function normalizeWorkingPlan(array $input): array
    {
        $plan = [];
        foreach (self::DAYS as $day) {
            $row = is_array($input[$day] ?? null) ? $input[$day] : [];
            if (empty($row['enabled'])) {
                $plan[$day] = null;
                continue;
            }
            $start = self::validTime((string)($row['start'] ?? ''));
            $end = self::validTime((string)($row['end'] ?? ''));
            if ($start >= $end) {
                throw new InvalidArgumentException(ucfirst($day) . ' end time must be after its start time.');
            }
            $breaks = self::normalizeBreaks($row['break_start'] ?? [], $row['break_end'] ?? [], $start, $end);
            $plan[$day] = ['start' => $start, 'end' => $end, 'breaks' => $breaks];
        }
        return $plan;
    }

    public static function normalizeException(string $date, string $start, string $end): array
    {
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', trim($date), new DateTimeZone(self::TIMEZONE));
        $errors = DateTimeImmutable::getLastErrors();
        if (!$parsed || (is_array($errors) && ($errors['warning_count'] || $errors['error_count']))) {
            throw new InvalidArgumentException('Select a valid exception date.');
        }
        $start = self::validTime($start);
        $end = self::validTime($end);
        if ($start >= $end) {
            throw new InvalidArgumentException('Exception end time must be after its start time.');
        }
        return ['date' => $parsed->format('Y-m-d'), 'start' => $start, 'end' => $end, 'breaks' => []];
    }

    public static function normalizeUnavailability(string $start, string $end): array
    {
        $timezone = new DateTimeZone(self::TIMEZONE);
        $from = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', trim($start), $timezone);
        $to = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', trim($end), $timezone);
        if (!$from || !$to || $to <= $from) {
            throw new InvalidArgumentException('Leave end must be after its start.');
        }
        return [
            'local_start' => $from,
            'local_end' => $to,
            'utc_start' => $from->setTimezone(new DateTimeZone('UTC')),
            'utc_end' => $to->setTimezone(new DateTimeZone('UTC')),
        ];
    }

    public function doctor(int $doctorId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT u.id,u.name,u.status,dp.* FROM users u
             JOIN doctor_profiles dp ON dp.user_id=u.id
             WHERE u.id=? AND u.role='doctor'"
        );
        $stmt->execute([$doctorId]);
        $doctor = $stmt->fetch();
        if (!$doctor) {
            throw new RuntimeException('Doctor not found.');
        }
        return $doctor;
    }

    public function workingPlan(array $doctor): array
    {
        $decoded = json_decode((string)($doctor['working_plan_json'] ?? ''), true);
        if (is_array($decoded)) {
            return $decoded;
        }
        return array_fill_keys(self::DAYS, null);
    }

    public function services(int $doctorId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT s.*, COALESCE(ds.active,0) assigned FROM appointment_services s
             LEFT JOIN doctor_appointment_services ds ON ds.appointment_service_id=s.id AND ds.doctor_id=?
             WHERE s.active=1 ORDER BY s.duration_minutes'
        );
        $stmt->execute([$doctorId]);
        return $stmt->fetchAll();
    }

    public function exceptions(int $doctorId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM doctor_schedule_exceptions WHERE doctor_id=? ORDER BY exception_date');
        $stmt->execute([$doctorId]);
        return $stmt->fetchAll();
    }

    public function unavailability(int $doctorId): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM doctor_unavailability_periods WHERE doctor_id=? AND status='active' ORDER BY start_at");
        $stmt->execute([$doctorId]);
        return $stmt->fetchAll();
    }

    public function saveWorkingPlan(
        int $doctorId,
        array $schedule,
        array $serviceIds,
        array $serviceEaIds,
        ?int $providerId,
        bool $adminMayConfigure
    ): array {
        $this->doctor($doctorId);
        $plan = self::normalizeWorkingPlan($schedule);
        if ($adminMayConfigure) {
            $this->validateExternalMappings($serviceIds, $serviceEaIds, $providerId);
        }
        $this->pdo->beginTransaction();
        try {
            if ($adminMayConfigure) {
                $this->pdo->prepare(
                    "UPDATE doctor_profiles SET ea_provider_id=?, working_plan_json=?, availability_sync_status='pending', availability_sync_error=NULL WHERE user_id=?"
                )->execute([$providerId ?: null, json_encode($plan, JSON_THROW_ON_ERROR), $doctorId]);
                $allowed = $this->pdo->query('SELECT id FROM appointment_services WHERE active=1')->fetchAll(PDO::FETCH_COLUMN);
                $serviceMap = $this->pdo->prepare('UPDATE appointment_services SET ea_service_id=? WHERE id=?');
                foreach ($allowed as $allowedId) {
                    $externalId = (int)($serviceEaIds[(string)$allowedId] ?? $serviceEaIds[(int)$allowedId] ?? 0);
                    $serviceMap->execute([$externalId > 0 ? $externalId : null, (int)$allowedId]);
                }
                $selected = array_values(array_intersect(array_map('intval', $serviceIds), array_map('intval', $allowed)));
                $this->pdo->prepare('DELETE FROM doctor_appointment_services WHERE doctor_id=?')->execute([$doctorId]);
                $insert = $this->pdo->prepare('INSERT INTO doctor_appointment_services (doctor_id,appointment_service_id,active) VALUES (?,?,1)');
                foreach ($selected as $serviceId) {
                    $insert->execute([$doctorId, $serviceId]);
                }
            } else {
                $this->pdo->prepare(
                    "UPDATE doctor_profiles SET working_plan_json=?, availability_sync_status='pending', availability_sync_error=NULL WHERE user_id=?"
                )->execute([json_encode($plan, JSON_THROW_ON_ERROR), $doctorId]);
            }
            $this->pdo->commit();
        } catch (Throwable $error) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $error;
        }
        return $this->syncProvider($doctorId);
    }

    public function saveException(int $doctorId, string $date, string $start, string $end): array
    {
        $this->doctor($doctorId);
        $exception = self::normalizeException($date, $start, $end);
        $stmt = $this->pdo->prepare(
            'INSERT INTO doctor_schedule_exceptions (doctor_id,exception_date,start_time,end_time,breaks_json)
             VALUES (?,?,?,?,?) ON DUPLICATE KEY UPDATE start_time=VALUES(start_time),end_time=VALUES(end_time),breaks_json=VALUES(breaks_json)'
        );
        $stmt->execute([$doctorId, $exception['date'], $exception['start'], $exception['end'], '[]']);
        return $this->syncProvider($doctorId);
    }

    public function deleteException(int $doctorId, int $exceptionId): array
    {
        $stmt = $this->pdo->prepare('DELETE FROM doctor_schedule_exceptions WHERE id=? AND doctor_id=?');
        $stmt->execute([$exceptionId, $doctorId]);
        return $this->syncProvider($doctorId);
    }

    public function addUnavailability(int $doctorId, string $start, string $end, string $reason): array
    {
        $doctor = $this->doctor($doctorId);
        $period = self::normalizeUnavailability($start, $end);
        $stmt = $this->pdo->prepare(
            "INSERT INTO doctor_unavailability_periods
             (doctor_id,start_at,end_at,reason,status,sync_status) VALUES (?,?,?,?, 'active','pending')"
        );
        $stmt->execute([
            $doctorId,
            $period['utc_start']->format('Y-m-d H:i:s'),
            $period['utc_end']->format('Y-m-d H:i:s'),
            substr(trim($reason), 0, 255),
        ]);
        $id = (int)$this->pdo->lastInsertId();
        try {
            if (!$this->easyAppointments->isConfigured() || empty($doctor['ea_provider_id'])) {
                throw new RuntimeException('Easy!Appointments/provider mapping is not configured.');
            }
            $externalId = $this->easyAppointments->createUnavailability([
                'start' => $period['local_start']->format('Y-m-d H:i:s'),
                'end' => $period['local_end']->format('Y-m-d H:i:s'),
                'notes' => substr(trim($reason), 0, 255),
                'providerId' => (int)$doctor['ea_provider_id'],
            ]);
            $this->pdo->prepare("UPDATE doctor_unavailability_periods SET ea_unavailability_id=?,sync_status='synced',sync_error=NULL WHERE id=?")
                ->execute([$externalId, $id]);
            $this->logSync('unavailability', (string)$id, 'create', 'succeeded');
            return ['sync_status' => 'synced'];
        } catch (Throwable $error) {
            $message = substr($error->getMessage(), 0, 500);
            $this->pdo->prepare("UPDATE doctor_unavailability_periods SET sync_status='failed',sync_error=? WHERE id=?")
                ->execute([$message, $id]);
            $this->logSync('unavailability', (string)$id, 'create', 'failed', $message);
            return ['sync_status' => 'failed', 'sync_error' => $message];
        }
    }

    public function releaseUnavailability(int $doctorId, int $periodId): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM doctor_unavailability_periods WHERE id=? AND doctor_id=? AND status='active'");
        $stmt->execute([$periodId, $doctorId]);
        $period = $stmt->fetch();
        if (!$period) {
            throw new RuntimeException('Unavailable period not found.');
        }
        try {
            if (!empty($period['ea_unavailability_id'])) {
                $this->easyAppointments->deleteUnavailability((int)$period['ea_unavailability_id']);
            }
            $this->pdo->prepare("UPDATE doctor_unavailability_periods SET status='released',sync_status='synced',sync_error=NULL WHERE id=?")
                ->execute([$periodId]);
            $this->logSync('unavailability', (string)$periodId, 'delete', 'succeeded');
            return ['sync_status' => 'synced'];
        } catch (Throwable $error) {
            $message = substr($error->getMessage(), 0, 500);
            $this->pdo->prepare("UPDATE doctor_unavailability_periods SET sync_status='failed',sync_error=? WHERE id=?")
                ->execute([$message, $periodId]);
            $this->logSync('unavailability', (string)$periodId, 'delete', 'failed', $message);
            return ['sync_status' => 'failed', 'sync_error' => $message];
        }
    }

    public function syncProvider(int $doctorId): array
    {
        $doctor = $this->doctor($doctorId);
        try {
            if (!$this->easyAppointments->isConfigured()) {
                throw new RuntimeException('Easy!Appointments is not configured.');
            }
            $providerId = (int)($doctor['ea_provider_id'] ?? 0);
            if ($providerId < 1) {
                throw new RuntimeException('Easy!Appointments provider ID is missing.');
            }
            $services = $this->services($doctorId);
            $externalServiceIds = [];
            foreach ($services as $service) {
                if ((int)$service['assigned'] !== 1) {
                    continue;
                }
                if (empty($service['ea_service_id'])) {
                    throw new RuntimeException('A permitted duration is not mapped to an Easy!Appointments service.');
                }
                $externalServiceIds[] = (int)$service['ea_service_id'];
            }
            if (!$externalServiceIds) {
                throw new RuntimeException('Select at least one mapped duration for this doctor.');
            }
            $provider = $this->easyAppointments->getProvider($providerId);
            unset($provider['id']);
            $provider['services'] = $externalServiceIds;
            $provider['timezone'] = self::TIMEZONE;
            $settings = is_array($provider['settings'] ?? null) ? $provider['settings'] : [];
            $settings['workingPlan'] = $this->workingPlan($doctor);
            $exceptionPayload = [];
            foreach ($this->exceptions($doctorId) as $exception) {
                $exceptionPayload[$exception['exception_date']] = [
                    'start' => substr((string)$exception['start_time'], 0, 5),
                    'end' => substr((string)$exception['end_time'], 0, 5),
                    'breaks' => json_decode((string)$exception['breaks_json'], true) ?: [],
                ];
            }
            $settings['workingPlanExceptions'] = (object)$exceptionPayload;
            $provider['settings'] = $settings;
            $this->easyAppointments->updateProvider($providerId, $provider);
            $this->pdo->prepare("UPDATE doctor_profiles SET availability_sync_status='synced',availability_sync_error=NULL,availability_synced_at=UTC_TIMESTAMP() WHERE user_id=?")
                ->execute([$doctorId]);
            $this->logSync('doctor', (string)$doctorId, 'availability_update', 'succeeded');
            return ['sync_status' => 'synced'];
        } catch (Throwable $error) {
            $message = substr($error->getMessage(), 0, 500);
            $status = $this->easyAppointments->isConfigured() ? 'failed' : 'not_configured';
            $this->pdo->prepare('UPDATE doctor_profiles SET availability_sync_status=?,availability_sync_error=? WHERE user_id=?')
                ->execute([$status, $message, $doctorId]);
            $this->logSync('doctor', (string)$doctorId, 'availability_update', 'failed', $message);
            return ['sync_status' => $status, 'sync_error' => $message];
        }
    }

    private function validateExternalMappings(array $serviceIds, array $serviceEaIds, ?int $providerId): void
    {
        if (!$this->easyAppointments->isConfigured()) {
            throw new RuntimeException('Easy!Appointments API must be configured before saving provider/service mappings.');
        }
        $providerId = (int)($providerId ?? 0);
        if ($providerId < 1) {
            throw new RuntimeException('Select a valid Easy!Appointments provider.');
        }

        $providers = [];
        foreach ($this->easyAppointments->providers() as $provider) {
            $id = (int)($provider['id'] ?? 0);
            if ($id > 0) {
                $providers[$id] = true;
            }
        }
        if (!isset($providers[$providerId])) {
            throw new RuntimeException('Selected Easy!Appointments provider no longer exists.');
        }

        $externalServices = [];
        foreach ($this->easyAppointments->services() as $service) {
            $id = (int)($service['id'] ?? 0);
            if ($id > 0) {
                $externalServices[$id] = $service;
            }
        }

        $localRows = $this->pdo->query('SELECT id,duration_minutes FROM appointment_services WHERE active=1')->fetchAll();
        $localServices = [];
        foreach ($localRows as $row) {
            $localServices[(int)$row['id']] = (int)$row['duration_minutes'];
        }

        $selected = array_values(array_intersect(array_map('intval', $serviceIds), array_keys($localServices)));
        if (!$selected) {
            throw new RuntimeException('Select at least one permitted duration for this doctor.');
        }

        foreach ($localServices as $localId => $localDuration) {
            $externalId = (int)($serviceEaIds[(string)$localId] ?? $serviceEaIds[$localId] ?? 0);
            if ($externalId < 1) {
                if (in_array($localId, $selected, true)) {
                    throw new RuntimeException($localDuration . '-minute duration needs an Easy!Appointments service mapping.');
                }
                continue;
            }
            if (!isset($externalServices[$externalId])) {
                throw new RuntimeException('Selected Easy!Appointments service #' . $externalId . ' no longer exists.');
            }
            $externalDuration = isset($externalServices[$externalId]['duration'])
                ? (int)$externalServices[$externalId]['duration']
                : 0;
            if ($externalDuration > 0 && $externalDuration !== $localDuration) {
                throw new RuntimeException(
                    $localDuration . '-minute Better Talk duration cannot map to a ' . $externalDuration . '-minute Easy!Appointments service.'
                );
            }
        }
    }

    private static function validTime(string $time): string
    {
        $time = trim($time);
        if (!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $time)) {
            throw new InvalidArgumentException('Use a valid 24-hour time.');
        }
        return $time;
    }

    private static function normalizeBreaks($starts, $ends, string $workStart, string $workEnd): array
    {
        $starts = is_array($starts) ? $starts : [];
        $ends = is_array($ends) ? $ends : [];
        $breaks = [];
        foreach ($starts as $index => $rawStart) {
            $rawEnd = $ends[$index] ?? '';
            if (trim((string)$rawStart) === '' && trim((string)$rawEnd) === '') {
                continue;
            }
            $start = self::validTime((string)$rawStart);
            $end = self::validTime((string)$rawEnd);
            if ($start >= $end || $start < $workStart || $end > $workEnd) {
                throw new InvalidArgumentException('Breaks must be inside working hours and end after they start.');
            }
            foreach ($breaks as $existing) {
                if ($start < $existing['end'] && $end > $existing['start']) {
                    throw new InvalidArgumentException('Breaks cannot overlap.');
                }
            }
            $breaks[] = ['start' => $start, 'end' => $end];
        }
        usort($breaks, static fn(array $a, array $b): int => strcmp($a['start'], $b['start']));
        return $breaks;
    }

    private function logSync(string $type, string $id, string $operation, string $status, ?string $error = null): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO scheduling_sync_logs (entity_type,entity_id,operation,status,error_message) VALUES (?,?,?,?,?)'
        );
        $stmt->execute([$type, $id, $operation, $status, $error]);
    }
}
