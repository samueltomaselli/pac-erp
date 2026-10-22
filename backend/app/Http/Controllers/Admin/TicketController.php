<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Tickets\OpenTicket;
use App\Actions\Tickets\UpdateTicket;
use App\Http\Controllers\Concerns\LoadsTicketDetail;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tickets\IndexTicketRequest;
use App\Http\Requests\Tickets\StoreTicketRequest;
use App\Http\Requests\Tickets\UpdateTicketRequest;
use App\Http\Resources\TicketResource;
use App\Models\Customer;
use App\Models\Ticket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TicketController extends Controller
{
    use LoadsTicketDetail;

    public function index(IndexTicketRequest $request): AnonymousResourceCollection
    {
        $tickets = Ticket::query()
            ->with(['customer', 'assignee', 'openedBy'])
            ->withCount('comments')
            ->when($request->filled('customer_id'), fn ($query) => $query->where('customer_id', $request->integer('customer_id')))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->input('status')))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 15))
            ->withQueryString();

        return TicketResource::collection($tickets);
    }

    public function store(StoreTicketRequest $request, OpenTicket $openTicket): JsonResponse
    {
        $customer = Customer::withTrashed()->findOrFail($request->integer('customer_id'));
        $result = $openTicket->handle($customer, $request->validated(), $request->user());

        if (! $result->successful()) {
            return response()->json(['message' => $result->message], 422);
        }

        return (new TicketResource($this->loadTicketDetail($result->data)))->response()->setStatusCode(201);
    }

    public function show(Ticket $ticket): TicketResource
    {
        return new TicketResource($this->loadTicketDetail($ticket));
    }

    public function update(UpdateTicketRequest $request, Ticket $ticket, UpdateTicket $updateTicket): JsonResponse|TicketResource
    {
        $result = $updateTicket->handle($ticket, $request->validated());

        if (! $result->successful()) {
            return response()->json(['message' => $result->message], 422);
        }

        return new TicketResource($this->loadTicketDetail($result->data));
    }
}
