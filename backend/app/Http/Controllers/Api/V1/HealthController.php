<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Health\HealthCheckService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class HealthController extends Controller
{
    public function __invoke(HealthCheckService $healthCheck): JsonResponse
    {
        $report = $healthCheck->check();

        return response()->json(
            $report->toArray(),
            $report->isHealthy() ? Response::HTTP_OK : Response::HTTP_SERVICE_UNAVAILABLE,
        );
    }
}
