<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['code', 'name_ar', 'name_en', 'is_active'])]
class Subject extends Model
{
    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function offerings()
    {
        return $this->hasMany(AcademicSubject::class);
    }
}
