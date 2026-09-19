<?php
return [
    'app_name' => 'Better Talk Portal',
    'base_url' => 'https://portal.bettertalk.pk',
    'db' => [
        'host' => 'localhost',
        'name' => 'catalogs_btportal',
        'user' => 'catalogs_btportal',
        'pass' => 'CHANGE_ME',
    ],
    'ivr' => [
        'provider' => 'mock',
        'api_base' => '',
        'api_key' => '',
        'caller_id' => '',
    ],
    'easyappointments' => [
        // Production scheduler installation root. Keep the trailing slash off.
        'base_url' => 'https://portal.bettertalk.pk/scheduler',
        // Easy!Appointments v1 API supports a configured Bearer API key or administrator Basic Auth.
        // Prefer a dedicated API key in production; never commit live credentials.
        'api_key' => '',
        'username' => '',
        'password' => '',
        'timeout_seconds' => 10,
    ],
    'sms' => [
        // Required for forgotten-password OTP delivery. Appointment reminders are disabled in V1.
        'provider' => 'disabled',
        'api_base' => '',
        'api_key' => '',
        'sender_id' => '',
    ],
];
