<?php

return [
    'provider' => env('PAYMENT_PROVIDER', in_array(env('APP_ENV'), ['local', 'testing'], true) ? 'local' : 'stripe'),
    'currency' => strtoupper(env('PAYMENT_CURRENCY', 'EGP')),
    'frontend_url' => rtrim(env('FRONTEND_URL', 'http://localhost:3000'), '/'),
    'stripe' => [
        'secret' => env('STRIPE_SECRET'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
        'api_url' => env('STRIPE_API_URL', 'https://api.stripe.com/v1'),
    ],
    'manual_transfer' => [
        'expires_minutes' => (int) env('MANUAL_PAYMENT_EXPIRES_MINUTES', 1440),
        'review_eta_hours' => (int) env('MANUAL_PAYMENT_REVIEW_ETA_HOURS', 24),
        'channels' => [
            'instapay' => ['label' => 'InstaPay', 'destination' => env('INSTAPAY_DESTINATION')],
            'vodafone_cash' => ['label' => 'Vodafone Cash', 'destination' => env('VODAFONE_CASH_NUMBER')],
            'orange_cash' => ['label' => 'Orange Cash', 'destination' => env('ORANGE_CASH_NUMBER')],
            'etisalat_cash' => ['label' => 'e& Cash', 'destination' => env('ETISALAT_CASH_NUMBER')],
        ],
    ],
];
