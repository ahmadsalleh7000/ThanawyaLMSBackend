<?php

namespace App\Http\Controllers;

use App\Services\ProductionReadinessService;
use Illuminate\Http\JsonResponse;

class HealthController extends Controller
{
    public function ready(ProductionReadinessService $readiness): JsonResponse
    {
        $ready = $readiness->ready();

        return response()
            ->json(['status' => $ready ? 'ready' : 'not_ready'], $ready ? 200 : 503)
            ->header('Cache-Control', 'no-store, max-age=0');
    }
}
