<?php

namespace App\Services;

use App\Models\ApplicationIncident;

class IncidentService
{
    public function record(string $type, string $severity, string $title, string $message, array $context = [], ?string $fingerprint = null): ApplicationIncident
    {
        $fingerprint ??= hash('sha256', $type.'|'.$title.'|'.$message);
        $incident = ApplicationIncident::where('fingerprint', $fingerprint)->whereNull('resolved_at')->first();
        if ($incident) {
            $incident->update(['occurrences' => $incident->occurrences + 1, 'last_seen_at' => now(), 'severity' => $severity, 'message' => $message, 'context' => $context]);

            return $incident->refresh();
        }

        return ApplicationIncident::create(['fingerprint' => $fingerprint, 'type' => $type, 'severity' => $severity, 'title' => $title, 'message' => $message, 'context' => $context, 'first_seen_at' => now(), 'last_seen_at' => now()]);
    }
}
