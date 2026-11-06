<?php

namespace App\Http\Controllers\Admin;

use App\Enums\TicketPriority;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateSlaPolicyRequest;
use App\Http\Resources\SlaPolicyResource;
use App\Services\Sla\SlaConfigurationService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SlaPolicyController extends Controller
{
    public function __construct(
        private readonly SlaConfigurationService $configurationService,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();
        if ($user === null || (! $user->isAdmin() && ! $user->isAgent()) || ! $user->is_active) {
            abort(403);
        }

        $policies = $this->configurationService->listPolicies();

        return SlaPolicyResource::collection($policies);
    }

    public function update(UpdateSlaPolicyRequest $request, string $priority): SlaPolicyResource
    {
        $priorityEnum = TicketPriority::tryFrom($priority);
        if ($priorityEnum === null) {
            abort(404);
        }

        $policy = $this->configurationService->updatePolicy($priorityEnum, $request->toData());

        return new SlaPolicyResource($policy);
    }
}

