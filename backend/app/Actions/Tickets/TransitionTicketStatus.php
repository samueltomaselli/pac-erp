<?php

namespace App\Actions\Tickets;

use App\Enums\TicketStatus;
use App\Models\Ticket;
use Illuminate\Validation\ValidationException;

class TransitionTicketStatus
{
    public function handle(Ticket $ticket, TicketStatus $status): Ticket
    {
        $allowed = match ($ticket->status) {
            TicketStatus::Open => [TicketStatus::InProgress],
            TicketStatus::InProgress => [TicketStatus::WaitingCustomer, TicketStatus::Resolved],
            TicketStatus::WaitingCustomer => [TicketStatus::InProgress, TicketStatus::Resolved],
            TicketStatus::Resolved => [TicketStatus::Closed, TicketStatus::InProgress],
            TicketStatus::Closed => [],
        };

        if (! in_array($status, $allowed, true)) {
            throw ValidationException::withMessages(['status' => 'Transição de status inválida.']);
        }

        $ticket->status = $status;
        $ticket->resolved_at = $status === TicketStatus::Resolved ? now() : ($status === TicketStatus::InProgress ? null : $ticket->resolved_at);
        $ticket->closed_at = $status === TicketStatus::Closed ? now() : $ticket->closed_at;
        $ticket->save();

        return $ticket;
    }
}
