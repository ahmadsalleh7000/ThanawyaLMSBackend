<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['code', 'name_ar', 'name_en', 'starts_on', 'ends_on', 'is_active'])]
class AcademicYear extends Model
{
    protected function casts(): array
    {
        return ['starts_on' => 'date', 'ends_on' => 'date', 'is_active' => 'boolean'];
    }

    public function terms()
    {
        return $this->hasMany(AcademicTerm::class);
    }
}
