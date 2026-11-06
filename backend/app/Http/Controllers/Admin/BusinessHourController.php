<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateBusinessHoursRequest;
use App\Services\Sla\SlaConfigurationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BusinessHourController extends Controller
{
    public function __construct(
        private readonly SlaConfigurationService $configurationService,
    ) {}

    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user === null || (! $user->isAdmin() && ! $user->isAgent()) || ! $user->is_active) {
            abort(403);
        }

        return response()->json($this->configurationService->getBusinessHours());
    }

    public function update(UpdateBusinessHoursRequest $request): JsonResponse
    {
        $updated = $this->configurationService->updateBusinessHours($request->validated('days'));

        return response()->json($updated);
    }
}

