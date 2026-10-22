<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Tickets\AssignTicket;
use App\Http\Controllers\Concerns\LoadsTicketDetail;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tickets\AssignTicketRequest;
use App\Http\Resources\TicketResource;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class TicketAssigneeController extends Controller
{
    use LoadsTicketDetail;

    public function update(AssignTicketRequest $request, Ticket $ticket, AssignTicket $assignTicket): JsonResponse
    {
        $assignee = $request->filled('assigned_to')
            ? User::findOrFail($request->integer('assigned_to'))
            : null;

        $result = $assignTicket->handle($ticket, $assignee);

        if (! $result->successful()) {
            return response()->json(['message' => $result->message], 422);
        }

        return (new TicketResource($this->loadTicketDetail($result->data)))->response();
    }
}
