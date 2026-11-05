<?php

namespace App\Http\Controllers;

use App\Enums\TicketActivityType;
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

        $query = $ticket->activities()
            ->with(['actor:id,name'])
            ->orderBy('id', 'asc');

        if ($request->user()->isCustomer()) {
            $query->whereNotIn('type', [
                TicketActivityType::InternalNoteAdded,
                TicketActivityType::SlaWarning,
                TicketActivityType::Escalated,
            ]);
        }

        $activities = $query->get();

        return TicketActivityResource::collection($activities);
    }
}

