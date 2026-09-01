<?php

namespace App\Services;

use App\Jobs\SendNotificationEmail;
use App\Models\NotificationDelivery;
use App\Models\NotificationTemplate;
use App\Models\User;

class EmailDeliveryService
{
    public function __construct(private readonly PlatformSettingsService $settings) {}

    public function queue(User $user, string $templateKey, array $variables = [], ?string $locale = null): ?NotificationDelivery
    {
        $template = NotificationTemplate::where('key', $templateKey)->where('is_active', true)->first();
        if (! $template || ! filter_var($user->email, FILTER_VALIDATE_EMAIL)) {
            return null;
        }
        $locale ??= (string) $this->settings->get('default_locale');
        $locale = $locale === 'en' ? 'en' : 'ar';
        $variables = array_merge(['name' => $user->name], $variables);
        $delivery = NotificationDelivery::create([
            'user_id' => $user->id, 'template_id' => $template->id, 'template_key' => $templateKey,
            'recipient' => $user->email, 'locale' => $locale,
            'subject' => $this->render($template->{'subject_'.$locale}, $variables),
            'body' => nl2br(e($this->render($template->{'body_'.$locale}, $variables))),
            'payload' => $variables, 'status' => 'queued',
        ]);
        SendNotificationEmail::dispatch($delivery->id)->afterCommit();

        return $delivery;
    }

    public function retry(NotificationDelivery $delivery): void
    {
        $delivery->update(['status' => 'queued', 'attempts' => 0, 'last_error' => null, 'failed_at' => null]);
        SendNotificationEmail::dispatch($delivery->id);
    }

    private function render(string $content, array $variables): string
    {
        return strtr($content, collect($variables)->mapWithKeys(fn ($value, $key) => ['{{'.$key.'}}' => (string) $value])->all());
    }
}
