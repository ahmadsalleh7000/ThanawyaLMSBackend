<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class AuditService
{
    public function record(Request $request, string $action, Model $model, ?array $before = null): void
    {
        AuditLog::create([
            'user_id' => $request->user()?->id,
            'action' => $action,
            'auditable_type' => $model->getMorphClass(),
            'auditable_id' => (string) $model->getKey(),
            'before' => $before,
            'after' => $model->exists ? $model->fresh()?->attributesToArray() : null,
            'ip_address' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 1000),
        ]);
    }
}
