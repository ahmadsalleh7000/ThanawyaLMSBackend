<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'name_en', 'description', 'description_en', 'price', 'is_active'])]
class CourseBundle extends Model
{
    protected function casts(): array
    {
        return ['price' => 'decimal:2', 'is_active' => 'boolean'];
    }

    public function courses()
    {
        return $this->belongsToMany(Course::class, 'course_bundle_items')
            ->withPivot(['position', 'prerequisite_course_id'])->orderByPivot('position');
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }
}
