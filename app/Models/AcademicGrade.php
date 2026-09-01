<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['code', 'name_ar', 'name_en', 'display_order', 'is_active'])]
class AcademicGrade extends Model
{
    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function terms()
    {
        return $this->hasMany(AcademicTerm::class);
    }
}
