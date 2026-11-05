<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\AgentResource;
use App\Models\User;
use App\Services\Assignment\AgentCandidateRepository;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class AgentController extends Controller
{
    public function index(AgentCandidateRepository $repository): AnonymousResourceCollection
    {
        Gate::authorize('viewAgents', User::class);

        $agents = $repository->forOverview();

        return AgentResource::collection($agents);
    }
}

