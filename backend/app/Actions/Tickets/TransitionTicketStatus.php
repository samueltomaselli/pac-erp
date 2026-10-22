<?php

namespace App\Actions\Tickets;

use App\Actions\Action;
use App\Actions\Support\ActionResult;
use App\Enums\TicketStatus;
use App\Models\Ticket;

/**
 * Único lugar que escreve o status do chamado. Também é chamada na saída
 * automática do "aguardando cliente" quando o cliente comenta.
 */
class TransitionTicketStatus extends Action
{
    public function handle(Ticket $ticket, TicketStatus $to): ActionResult
    {
        if (! $ticket->status->canTransitionTo($to)) {
            return ActionResult::fail(sprintf(
                'Não é possível mudar de %s para %s.',
                $ticket->status->label(),
                $to->label(),
            ));
        }

        $ticket->status = $to;

        if ($to === TicketStatus::Resolved) {
            $ticket->resolved_at = now();
        }

        if ($to === TicketStatus::InProgress) {
            $ticket->resolved_at = null;
        }

        if ($to === TicketStatus::Closed) {
            $ticket->closed_at = now();
        }

        $ticket->save();

        return ActionResult::ok($ticket);
    }
}
