<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['user_id', 'route_name', 'method', 'status_code', 'duration_ms', 'memory_kb', 'occurred_at'])]
class RequestMetric extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return ['occurred_at' => 'datetime'];
    }
}
