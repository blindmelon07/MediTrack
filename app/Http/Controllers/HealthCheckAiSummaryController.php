<?php

namespace App\Http\Controllers;

use App\Actions\Ai\AiUnavailableException;
use App\Actions\Ai\DraftHealthCheckSummary;
use App\Models\HealthCheck;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class HealthCheckAiSummaryController extends Controller
{
    /**
     * Draft an AI summary and suggested follow-ups for the health check. Nothing is saved.
     */
    public function __invoke(HealthCheck $healthCheck, DraftHealthCheckSummary $draftSummary): JsonResponse
    {
        Gate::authorize('update', $healthCheck);

        try {
            return response()->json($draftSummary->handle($healthCheck));
        } catch (AiUnavailableException $exception) {
            return response()->json(['message' => $exception->getMessage()], 503);
        }
    }
}
