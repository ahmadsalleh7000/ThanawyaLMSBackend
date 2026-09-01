<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['user_id', 'email', 'guard', 'successful', 'failure_reason', 'ip_address', 'user_agent', 'occurred_at'])]
class LoginEvent extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return ['successful' => 'boolean', 'occurred_at' => 'datetime'];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
