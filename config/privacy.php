<?php

return [
    'deletion_cooling_off_days' => (int) env('PRIVACY_DELETION_COOLING_OFF_DAYS', 30),
    'financial_record_retention_years' => (int) env('PRIVACY_FINANCIAL_RETENTION_YEARS', 7),
    'policy_version' => env('PRIVACY_POLICY_VERSION', '2026-08'),
];
