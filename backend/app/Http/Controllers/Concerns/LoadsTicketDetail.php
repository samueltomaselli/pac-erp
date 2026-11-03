<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Ticket;

trait LoadsTicketDetail
{
    /**
     * Relações que o TicketResource espera no detalhe do chamado.
     *
     * @var list<string>
     */
    private const TICKET_DETAIL = ['customer', 'assignee', 'openedBy', 'comments.author', 'comments.attachments', 'attachments'];

    private function loadTicketDetail(Ticket $ticket): Ticket
    {
        return $ticket->load(self::TICKET_DETAIL)->loadCount('comments');
    }
}
