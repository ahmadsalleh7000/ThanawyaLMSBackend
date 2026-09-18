<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['training_id', 'prerequisite_training_id', 'minimum_score'])]
class TrainingPrerequisite extends Model
{
    protected function casts(): array
    {
        return ['minimum_score' => 'integer'];
    }

    public function training()
    {
        return $this->belongsTo(Training::class);
    }

    public function prerequisiteTraining()
    {
        return $this->belongsTo(Training::class, 'prerequisite_training_id');
    }
}
