<?php

namespace App\Actions\Tickets;

use App\Actions\Action;
use App\Actions\Support\ActionResult;
use App\Models\Ticket;
use Illuminate\Support\Arr;

/**
 * Edição do admin. Status e responsável têm Actions próprias.
 */
class UpdateTicket extends Action
{
    public function handle(Ticket $ticket, array $data): ActionResult
    {
        $ticket->update(Arr::only($data, ['title', 'category', 'description', 'priority']));

        return ActionResult::ok($ticket);
    }
}
