<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['lesson_id', 'user_id', 'rating', 'comment'])]
class LessonFeedback extends Model
{
    protected function casts(): array
    {
        return ['rating' => 'integer'];
    }
}
