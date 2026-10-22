<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Tickets\TransitionTicketStatus;
use App\Enums\TicketStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tickets\TransitionTicketStatusRequest;
use App\Http\Resources\TicketResource;
use App\Models\Ticket;
use Illuminate\Http\JsonResponse;

class TicketStatusController extends Controller
{
    public function store(TransitionTicketStatusRequest $request, Ticket $ticket, TransitionTicketStatus $transition): JsonResponse
    {
        $result = $transition->handle($ticket, TicketStatus::from($request->validated('status')));

        if (! $result->successful()) {
            return response()->json(['message' => $result->message], 422);
        }

        $ticket = $result->data
            ->load(['customer', 'assignee', 'openedBy'])
            ->loadCount('comments');

        return (new TicketResource($ticket))->response();
    }
}
