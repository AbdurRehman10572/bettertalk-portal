<?php
declare(strict_types=1);

require_once __DIR__ . '/EasyAppointmentsClient.php';

final class AppointmentService
{
    public const HOLD_MINUTES = 15;
    public const DISPLAY_TIMEZONE = 'Asia/Karachi';

    private PDO $pdo;
    private EasyAppointmentsClient $easyAppointments;

    public function __construct(PDO $pdo, array $config)
    {
        $this->pdo = $pdo;
        $this->easyAppointments = new EasyAppointmentsClient($config['easyappointments'] ?? []);
    }

    public static function intervalsOverlap(
        DateTimeImmutable $firstStart,
        DateTimeImmutable $firstEnd,
        DateTimeImmutable $secondStart,
        DateTimeImmutable $secondEnd
    ): bool {
        return $firstStart < $secondEnd && $firstEnd > $secondStart;
    }

    public static function pakistanToUtc(string $date, string $time): DateTimeImmutable
    {
        $local = DateTimeImmutable::createFromFormat(
            '!Y-m-d H:i',
            trim($date) . ' ' . trim($time),
            new DateTimeZone(self::DISPLAY_TIMEZONE)
        );
        $errors = DateTimeImmutable::getLastErrors();
        if (!$local || (is_array($errors) && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
            throw new InvalidArgumentException('Select a valid appointment date and time.');
        }
        return $local->setTimezone(new DateTimeZone('UTC'));
    }

    public static function utcToPakistan(string $dateTime): string
    {
        return (new DateTimeImmutable($dateTime, new DateTimeZone('UTC')))
            ->setTimezone(new DateTimeZone(self::DISPLAY_TIMEZONE))
            ->format('d M Y, h:i A');
    }

    public function availability(int $doctorId, int $serviceId, string $date): array
    {
        $context = $this->doctorServiceContext($doctorId, $serviceId);
        if (!$this->easyAppointments->isConfigured()) {
            throw new RuntimeException('Easy!Appointments is not configured yet.');
        }
        if (!$context['ea_provider_id'] || !$context['ea_service_id']) {
            throw new RuntimeException('This doctor/duration is not mapped to Easy!Appointments.');
        }

        $slots = $this->easyAppointments->availabilities(
            (int)$context['ea_provider_id'],
            (int)$context['ea_service_id'],
            $date
        );
        $this->expireHolds();
        $available = [];
        foreach ($slots as $time) {
            $start = self::pakistanToUtc($date, $time);
            $end = $start->modify('+' . (int)$context['duration_minutes'] . ' minutes');
            if ($start <= new DateTimeImmutable('now', new DateTimeZone('UTC'))) {
                continue;
            }
            if (!$this->hasConflict($doctorId, $start, $end)) {
                $available[] = $time;
            }
        }
        return $available;
    }

    public function createHold(
        int $caseId,
        int $doctorId,
        int $serviceId,
        int $agentId,
        string $date,
        string $time
    ): array {
        $context = $this->doctorServiceContext($doctorId, $serviceId);
        $start = self::pakistanToUtc($date, $time);
        $end = $start->modify('+' . (int)$context['duration_minutes'] . ' minutes');
        if ($start <= new DateTimeImmutable('now', new DateTimeZone('UTC'))) {
            throw new RuntimeException('The appointment time must be in the future.');
        }

        $available = $this->availability($doctorId, $serviceId, $date);
        if (!in_array($time, $available, true)) {
            throw new RuntimeException('Slot no longer available. Select another time.');
        }

        $lockName = $this->lockName($doctorId, $start);
        $this->acquireLock($lockName);
        try {
            $this->pdo->beginTransaction();
            $this->expireHolds();
            $case = $this->paidCaseForUpdate($caseId);
            if ($this->hasConflict($doctorId, $start, $end, true)) {
                throw new RuntimeException('Slot no longer available. Select another time.');
            }
            $token = bin2hex(random_bytes(32));
            $expiresAt = (new DateTimeImmutable('now', new DateTimeZone('UTC')))
                ->modify('+' . self::HOLD_MINUTES . ' minutes');
            $stmt = $this->pdo->prepare(
                "INSERT INTO appointment_holds
                 (hold_token, case_id, patient_id, doctor_id, appointment_service_id, agent_id, start_at, end_at, expires_at, status)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')"
            );
            $stmt->execute([
                $token,
                $caseId,
                (int)$case['patient_id'],
                $doctorId,
                $serviceId,
                $agentId,
                $start->format('Y-m-d H:i:s'),
                $end->format('Y-m-d H:i:s'),
                $expiresAt->format('Y-m-d H:i:s'),
            ]);
            $this->pdo->prepare('UPDATE cases SET assigned_doctor_id=? WHERE id=?')->execute([$doctorId, $caseId]);
            $this->pdo->commit();
            return [
                'token' => $token,
                'expires_at' => $expiresAt->format('Y-m-d H:i:s'),
                'start_at' => $start->format('Y-m-d H:i:s'),
                'end_at' => $end->format('Y-m-d H:i:s'),
                'duration_minutes' => (int)$context['duration_minutes'],
            ];
        } catch (Throwable $error) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $error;
        } finally {
            $this->releaseLock($lockName);
        }
    }

    public function confirmHold(string $token, int $actorId, string $actorRole): array
    {
        $lookup = $this->pdo->prepare('SELECT doctor_id, start_at FROM appointment_holds WHERE hold_token=? LIMIT 1');
        $lookup->execute([$token]);
        $seed = $lookup->fetch();
        if (!$seed) {
            throw new RuntimeException('Appointment hold not found.');
        }
        $start = new DateTimeImmutable($seed['start_at'], new DateTimeZone('UTC'));
        $lockName = $this->lockName((int)$seed['doctor_id'], $start);
        $this->acquireLock($lockName);
        try {
            $this->pdo->beginTransaction();
            $this->expireHolds();
            $stmt = $this->pdo->prepare('SELECT * FROM appointment_holds WHERE hold_token=? FOR UPDATE');
            $stmt->execute([$token]);
            $hold = $stmt->fetch();
            if (!$hold || $hold['status'] !== 'active') {
                throw new RuntimeException('This hold expired or is no longer active.');
            }
            if ($actorRole !== 'admin' && (int)$hold['agent_id'] !== $actorId) {
                throw new RuntimeException('This hold belongs to another agent.');
            }
            $case = $this->paidCaseForUpdate((int)$hold['case_id']);
            $start = new DateTimeImmutable($hold['start_at'], new DateTimeZone('UTC'));
            $end = new DateTimeImmutable($hold['end_at'], new DateTimeZone('UTC'));
            if ($this->hasConflict((int)$hold['doctor_id'], $start, $end, true, (int)$hold['id'])) {
                throw new RuntimeException('Slot no longer available. Select another time.');
            }
            $paymentId = (int)$case['paid_payment_id'];
            $appointmentCode = 'AP-' . gmdate('Ymd') . '-' . strtoupper(bin2hex(random_bytes(4)));
            $stmt = $this->pdo->prepare(
                "INSERT INTO appointments
                 (appointment_code, case_id, payment_id, appointment_service_id, patient_id, doctor_id, agent_id,
                  scheduled_at, end_at, timezone, duration_minutes, status, sync_status)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'scheduled', 'pending')"
            );
            $stmt->execute([
                $appointmentCode,
                (int)$hold['case_id'],
                $paymentId,
                (int)$hold['appointment_service_id'],
                (int)$hold['patient_id'],
                (int)$hold['doctor_id'],
                (int)$hold['agent_id'],
                $hold['start_at'],
                $hold['end_at'],
                self::DISPLAY_TIMEZONE,
                (int)((strtotime($hold['end_at']) - strtotime($hold['start_at'])) / 60),
            ]);
            $appointmentId = (int)$this->pdo->lastInsertId();
            $this->pdo->prepare("UPDATE appointment_holds SET status='converted', converted_appointment_id=? WHERE id=?")
                ->execute([$appointmentId, (int)$hold['id']]);
            $this->pdo->prepare("UPDATE cases SET status='scheduled', assigned_doctor_id=? WHERE id=?")
                ->execute([(int)$hold['doctor_id'], (int)$hold['case_id']]);
            $this->pdo->commit();

            $sync = $this->syncAppointment($appointmentId);
            return ['appointment_id' => $appointmentId, 'appointment_code' => $appointmentCode] + $sync;
        } catch (Throwable $error) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $error;
        } finally {
            $this->releaseLock($lockName);
        }
    }

    public function syncAppointment(int $appointmentId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT a.*, p.name patient_name, p.phone, p.email, p.city, p.client_code, p.ea_customer_id,
                    dp.ea_provider_id, s.ea_service_id
             FROM appointments a
             JOIN patients p ON p.id=a.patient_id
             JOIN doctor_profiles dp ON dp.user_id=a.doctor_id
             JOIN appointment_services s ON s.id=a.appointment_service_id
             WHERE a.id=?'
        );
        $stmt->execute([$appointmentId]);
        $appointment = $stmt->fetch();
        if (!$appointment) {
            throw new RuntimeException('Appointment not found.');
        }
        try {
            if (!$this->easyAppointments->isConfigured()) {
                throw new RuntimeException('Easy!Appointments is not configured.');
            }
            if (!$appointment['ea_provider_id'] || !$appointment['ea_service_id']) {
                throw new RuntimeException('Doctor or duration is not mapped to Easy!Appointments.');
            }
            $customerId = (int)($appointment['ea_customer_id'] ?? 0);
            if ($customerId < 1) {
                [$firstName, $lastName] = $this->splitName((string)$appointment['patient_name']);
                $email = trim((string)$appointment['email']);
                if ($email === '') {
                    $email = strtolower((string)$appointment['client_code']) . '@noemail.invalid';
                }
                $customerId = $this->easyAppointments->createCustomer([
                    'firstName' => $firstName,
                    'lastName' => $lastName,
                    'email' => $email,
                    'phone' => (string)$appointment['phone'],
                    'city' => (string)$appointment['city'],
                    'timezone' => self::DISPLAY_TIMEZONE,
                    'language' => 'english',
                    'notes' => 'Better Talk Client ID: ' . (string)$appointment['client_code'],
                ]);
                $this->pdo->prepare('UPDATE patients SET ea_customer_id=? WHERE id=?')
                    ->execute([$customerId, (int)$appointment['patient_id']]);
            }
            $start = (new DateTimeImmutable($appointment['scheduled_at'], new DateTimeZone('UTC')))
                ->setTimezone(new DateTimeZone(self::DISPLAY_TIMEZONE));
            $end = (new DateTimeImmutable($appointment['end_at'], new DateTimeZone('UTC')))
                ->setTimezone(new DateTimeZone(self::DISPLAY_TIMEZONE));
            $externalId = $this->easyAppointments->createAppointment([
                'start' => $start->format('Y-m-d H:i:s'),
                'end' => $end->format('Y-m-d H:i:s'),
                'notes' => 'Better Talk ' . (string)$appointment['appointment_code'],
                'serviceId' => (int)$appointment['ea_service_id'],
                'providerId' => (int)$appointment['ea_provider_id'],
                'customerId' => $customerId,
            ]);
            $this->pdo->prepare("UPDATE appointments SET ea_appointment_id=?, sync_status='synced', sync_error=NULL WHERE id=?")
                ->execute([$externalId, $appointmentId]);
            $this->logSync('appointment', (string)$appointmentId, 'create', 'succeeded', ['ea_appointment_id' => $externalId]);
            return ['sync_status' => 'synced', 'ea_appointment_id' => $externalId];
        } catch (Throwable $error) {
            $message = substr($error->getMessage(), 0, 500);
            $this->pdo->prepare("UPDATE appointments SET sync_status='failed', sync_error=? WHERE id=?")
                ->execute([$message, $appointmentId]);
            $this->logSync('appointment', (string)$appointmentId, 'create', 'failed', [], $message);
            return ['sync_status' => 'failed', 'sync_error' => $message];
        }
    }

    public function expireHolds(): void
    {
        $this->pdo->exec("UPDATE appointment_holds SET status='expired' WHERE status='active' AND expires_at <= UTC_TIMESTAMP()");
    }

    private function doctorServiceContext(int $doctorId, int $serviceId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT u.id doctor_id, dp.ea_provider_id, s.id service_id, s.duration_minutes, s.ea_service_id
             FROM users u
             JOIN doctor_profiles dp ON dp.user_id=u.id
             JOIN doctor_appointment_services ds ON ds.doctor_id=u.id AND ds.active=1
             JOIN appointment_services s ON s.id=ds.appointment_service_id AND s.active=1
             WHERE u.id=? AND s.id=? AND u.role='doctor' AND u.status='active'"
        );
        $stmt->execute([$doctorId, $serviceId]);
        $row = $stmt->fetch();
        if (!$row || !in_array((int)$row['duration_minutes'], [15, 30, 45, 60], true)) {
            throw new RuntimeException('This doctor does not offer the selected duration.');
        }
        return $row;
    }

    private function paidCaseForUpdate(int $caseId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT c.*, (
                SELECT p.id FROM payments p
                WHERE p.case_id=c.id AND p.status='paid'
                ORDER BY p.id DESC LIMIT 1
             ) paid_payment_id
             FROM cases c WHERE c.id=? FOR UPDATE"
        );
        $stmt->execute([$caseId]);
        $case = $stmt->fetch();
        if (!$case) {
            throw new RuntimeException('Case not found.');
        }
        if (empty($case['paid_payment_id'])) {
            throw new RuntimeException('Payment must be verified before scheduling.');
        }
        return $case;
    }

    private function hasConflict(
        int $doctorId,
        DateTimeImmutable $start,
        DateTimeImmutable $end,
        bool $forUpdate = false,
        ?int $excludeHoldId = null
    ): bool {
        $lock = $forUpdate ? ' FOR UPDATE' : '';
        $stmt = $this->pdo->prepare(
            "SELECT id FROM appointments
             WHERE doctor_id=? AND scheduled_at < ? AND end_at > ?
               AND status NOT IN ('cancelled','cancelled_by_client','cancelled_by_better_talk','rescheduled')
             LIMIT 1" . $lock
        );
        $stmt->execute([$doctorId, $end->format('Y-m-d H:i:s'), $start->format('Y-m-d H:i:s')]);
        if ($stmt->fetch()) {
            return true;
        }
        $sql = "SELECT id FROM appointment_holds
                WHERE doctor_id=? AND status='active' AND expires_at > UTC_TIMESTAMP()
                  AND start_at < ? AND end_at > ?";
        $params = [$doctorId, $end->format('Y-m-d H:i:s'), $start->format('Y-m-d H:i:s')];
        if ($excludeHoldId !== null) {
            $sql .= ' AND id<>?';
            $params[] = $excludeHoldId;
        }
        $sql .= ' LIMIT 1' . $lock;
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (bool)$stmt->fetch();
    }

    private function acquireLock(string $lockName): void
    {
        $stmt = $this->pdo->prepare('SELECT GET_LOCK(?, 5)');
        $stmt->execute([$lockName]);
        if ((int)$stmt->fetchColumn() !== 1) {
            throw new RuntimeException('The schedule is busy. Please try again.');
        }
    }

    private function releaseLock(string $lockName): void
    {
        try {
            $stmt = $this->pdo->prepare('SELECT RELEASE_LOCK(?)');
            $stmt->execute([$lockName]);
        } catch (Throwable $ignored) {
        }
    }

    private function lockName(int $doctorId, DateTimeImmutable $start): string
    {
        return 'bt-appt-' . $doctorId . '-' . $start->format('Ymd');
    }

    private function splitName(string $name): array
    {
        $parts = preg_split('/\s+/', trim($name), 2) ?: [];
        return [$parts[0] ?? 'Better Talk', $parts[1] ?? 'Client'];
    }

    private function logSync(
        string $entityType,
        string $entityId,
        string $operation,
        string $status,
        array $response = [],
        ?string $error = null
    ): void {
        $stmt = $this->pdo->prepare(
            'INSERT INTO scheduling_sync_logs (entity_type, entity_id, operation, status, response_payload, error_message)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$entityType, $entityId, $operation, $status, json_encode($response), $error]);
    }
}
