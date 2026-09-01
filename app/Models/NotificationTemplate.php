<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['key', 'name', 'subject_ar', 'subject_en', 'body_ar', 'body_en', 'is_active'])]
class NotificationTemplate extends Model
{
    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function deliveries()
    {
        return $this->hasMany(NotificationDelivery::class, 'template_id');
    }
}
