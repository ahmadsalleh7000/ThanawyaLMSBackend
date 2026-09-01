<?php

return [
    'invitation_expires_hours' => (int) env('GUARDIAN_INVITATION_EXPIRES_HOURS', 48),
    'resend_cooldown_minutes' => (int) env('GUARDIAN_RESEND_COOLDOWN_MINUTES', 10),
    'max_delivery_attempts' => (int) env('GUARDIAN_MAX_DELIVERY_ATTEMPTS', 5),
    'temporary_password_expires_hours' => (int) env('GUARDIAN_TEMPORARY_PASSWORD_EXPIRES_HOURS', 48),
    'delivery' => [
        'driver' => env('GUARDIAN_DELIVERY_DRIVER', 'log'),
        'endpoint' => env('GUARDIAN_DELIVERY_ENDPOINT'),
        'token' => env('GUARDIAN_DELIVERY_TOKEN'),
        'timeout_seconds' => (int) env('GUARDIAN_DELIVERY_TIMEOUT_SECONDS', 10),
    ],
];
