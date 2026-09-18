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
        // Installation root, for example https://schedule.bettertalk.pk
        'base_url' => '',
        // Prefer an API key. Basic-auth credentials remain available as fallback.
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
