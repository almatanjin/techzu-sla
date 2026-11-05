<?php

namespace App\Http\Controllers;

use App\Http\Requests\PostMessageRequest;
use App\Http\Resources\TicketMessageResource;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Services\Tickets\TicketMessageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class TicketMessageController extends Controller
{
    public function __construct(
        private readonly TicketMessageService $messageService,
    ) {}

    public function index(Request $request, Ticket $ticket): AnonymousResourceCollection
    {
        Gate::authorize('view', $ticket);

        $query = $ticket->messages()
            ->visibleTo($request->user())
            ->with(['author:id,name,role'])
            ->orderBy('id', 'desc');

        if (\Illuminate\Support\Facades\Schema::hasTable('attachments')) {
            $query->with('attachments');
        }

        $messages = $query->cursorPaginate(30);

        return TicketMessageResource::collection($messages);
    }

    public function store(PostMessageRequest $request, Ticket $ticket): JsonResponse
    {
        $data = $request->toData();

        Gate::authorize('create', [TicketMessage::class, $ticket, $data->type]);

        $message = $this->messageService->post($ticket, $data, $request->user());

        return (new TicketMessageResource($message))->response()->setStatusCode(201);
    }
}
