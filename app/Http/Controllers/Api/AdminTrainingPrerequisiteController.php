<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Training;
use App\Models\TrainingPrerequisite;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminTrainingPrerequisiteController extends Controller
{
    public function store(Request $request, Training $training, AuditService $audit): JsonResponse
    {
        $data = $request->validate(['prerequisite_training_id' => ['required', 'exists:trainings,id'], 'minimum_score' => ['sometimes', 'integer', 'min:1', 'max:100']]);
        $prerequisite = Training::findOrFail($data['prerequisite_training_id']);
        abort_unless($prerequisite->trainable_type === $training->trainable_type && (int) $prerequisite->trainable_id === (int) $training->trainable_id, 422, 'Quiz prerequisites must belong to the same lesson.');
        abort_if($prerequisite->id === $training->id, 422, 'A quiz cannot require itself.');
        $rule = TrainingPrerequisite::updateOrCreate(['training_id' => $training->id, 'prerequisite_training_id' => $prerequisite->id], ['minimum_score' => $data['minimum_score'] ?? 70]);
        $audit->record($request, 'training.prerequisite.updated', $rule);

        return response()->json(['data' => ['trainingId' => $rule->training_id, 'prerequisiteTrainingId' => $rule->prerequisite_training_id, 'minimumScore' => $rule->minimum_score]], 201);
    }
}
