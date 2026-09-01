<?php

namespace App\Services;

class ManualPaymentSettingsService
{
    public function __construct(private readonly PlatformSettingsService $settings) {}

    public function channels(): array
    {
        $stored = $this->settings->all();
        $keys = ['instapay' => 'instapay_destination', 'vodafone_cash' => 'vodafone_cash_number', 'orange_cash' => 'orange_cash_number', 'etisalat_cash' => 'etisalat_cash_number'];

        return collect(config('payments.manual_transfer.channels'))->map(function ($channel, $key) use ($stored, $keys) {
            $channel['destination'] = $stored[$keys[$key]] ?: $channel['destination'];

            return $channel;
        })->all();
    }

    public function expiresMinutes(): int
    {
        return (int) ($this->settings->get('manual_payment_expires_minutes') ?: config('payments.manual_transfer.expires_minutes'));
    }
}
