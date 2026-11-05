<?php

namespace App\Http\Controllers;

use App\Http\Resources\TicketActivityResource;
use App\Models\Ticket;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class TicketActivityController extends Controller
{
    public function index(Request $request, Ticket $ticket): AnonymousResourceCollection
    {
        Gate::authorize('view', $ticket);

        $activities = $ticket->activities()
            ->with(['actor:id,name'])
            ->orderBy('id', 'asc')
            ->get();

        return TicketActivityResource::collection($activities);
    }
}

