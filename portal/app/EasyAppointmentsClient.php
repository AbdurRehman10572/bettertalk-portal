<?php
declare(strict_types=1);

final class EasyAppointmentsClient
{
    private string $baseUrl;
    private string $apiKey;
    private string $username;
    private string $password;
    private int $timeoutSeconds;

    public function __construct(array $config)
    {
        $this->baseUrl = rtrim((string)($config['base_url'] ?? ''), '/');
        $this->apiKey = trim((string)($config['api_key'] ?? ''));
        $this->username = trim((string)($config['username'] ?? ''));
        $this->password = (string)($config['password'] ?? '');
        $this->timeoutSeconds = max(3, min(30, (int)($config['timeout_seconds'] ?? 10)));
    }

    public function isConfigured(): bool
    {
        return $this->baseUrl !== '' && ($this->apiKey !== '' || ($this->username !== '' && $this->password !== ''));
    }

    public function healthCheck(): array
    {
        $result = $this->request('GET', 'services', ['page' => 1, 'length' => 1]);
        return [
            'configured' => true,
            'api_authenticated' => true,
            'sample_service_count' => count($result),
        ];
    }

    public function availabilities(int $providerId, int $serviceId, string $date): array
    {
        $result = $this->request('GET', 'availabilities', [
            'providerId' => $providerId,
            'serviceId' => $serviceId,
            'date' => $date,
        ]);
        if (!is_array($result)) {
            throw new RuntimeException('Scheduling engine returned invalid availability data.');
        }
        return array_values(array_filter($result, static fn($time): bool => is_string($time)));
    }

    public function createCustomer(array $customer): int
    {
        $result = $this->request('POST', 'customers', [], $customer);
        $id = (int)($result['id'] ?? 0);
        if ($id < 1) {
            throw new RuntimeException('Scheduling engine did not return a customer ID.');
        }
        return $id;
    }

    public function createAppointment(array $appointment): int
    {
        $result = $this->request('POST', 'appointments', [], $appointment);
        $id = (int)($result['id'] ?? 0);
        if ($id < 1) {
            throw new RuntimeException('Scheduling engine did not return an appointment ID.');
        }
        return $id;
    }

    public function updateProvider(int $providerId, array $provider): array
    {
        $result = $this->request('PUT', 'providers/' . $providerId, [], $provider);
        if (!is_array($result)) {
            throw new RuntimeException('Scheduling engine returned invalid provider data.');
        }
        return $result;
    }

    public function getProvider(int $providerId): array
    {
        $result = $this->request('GET', 'providers/' . $providerId);
        if (!is_array($result) || (int)($result['id'] ?? 0) !== $providerId) {
            throw new RuntimeException('Scheduling engine returned invalid provider data.');
        }
        return $result;
    }

    public function createUnavailability(array $unavailability): int
    {
        $result = $this->request('POST', 'unavailabilities', [], $unavailability);
        $id = (int)($result['id'] ?? 0);
        if ($id < 1) {
            throw new RuntimeException('Scheduling engine did not return an unavailability ID.');
        }
        return $id;
    }

    public function deleteUnavailability(int $unavailabilityId): void
    {
        $this->request('DELETE', 'unavailabilities/' . $unavailabilityId);
    }

    private function request(string $method, string $resource, array $query = [], ?array $payload = null): array
    {
        if (!$this->isConfigured()) {
            throw new RuntimeException('Easy!Appointments is not configured.');
        }
        if (!function_exists('curl_init')) {
            throw new RuntimeException('The PHP cURL extension is required for Easy!Appointments integration.');
        }

        $url = $this->baseUrl . '/index.php/api/v1/' . ltrim($resource, '/');
        if ($query) {
            $url .= '?' . http_build_query($query);
        }
        $headers = ['Accept: application/json'];
        if ($this->apiKey !== '') {
            $headers[] = 'Authorization: Bearer ' . $this->apiKey;
        }
        if ($payload !== null) {
            $headers[] = 'Content-Type: application/json';
        }

        $curl = curl_init($url);
        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_CONNECTTIMEOUT => $this->timeoutSeconds,
            CURLOPT_TIMEOUT => $this->timeoutSeconds,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);
        if ($this->apiKey === '') {
            curl_setopt($curl, CURLOPT_USERPWD, $this->username . ':' . $this->password);
        }
        if ($payload !== null) {
            curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($payload, JSON_THROW_ON_ERROR));
        }

        $raw = curl_exec($curl);
        $status = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $curlError = curl_error($curl);
        curl_close($curl);

        if ($raw === false || $curlError !== '') {
            throw new RuntimeException('Scheduling engine connection failed: ' . $curlError);
        }
        $decoded = json_decode((string)$raw, true);
        if ($status < 200 || $status >= 300) {
            $message = is_array($decoded) ? (string)($decoded['message'] ?? 'request failed') : 'request failed';
            throw new RuntimeException('Scheduling engine error (' . $status . '): ' . $message);
        }
        if (!is_array($decoded)) {
            throw new RuntimeException('Scheduling engine returned non-JSON data.');
        }
        return $decoded;
    }
}
