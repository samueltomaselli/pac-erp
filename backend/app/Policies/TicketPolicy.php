<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class TicketPolicy
{
    /**
     * Admin vê qualquer chamado; cliente só os do próprio cadastro.
     * Chamado de outro cliente responde 404 para não confirmar que o id existe.
     */
    public function view(User $user, Ticket $ticket): Response
    {
        if ($user->role === UserRole::Admin) {
            return Response::allow();
        }

        $customerId = $user->customer?->id;

        return $customerId !== null && $ticket->customer_id === $customerId
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}
