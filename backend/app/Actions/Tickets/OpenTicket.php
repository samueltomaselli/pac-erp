<?php

namespace App\Actions\Tickets;

use App\Actions\Action;
use App\Actions\Support\ActionResult;
use App\Enums\TicketPriority;
use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\User;

/**
 * Abre o chamado sempre no cadastro recebido. Quem chama decide o cliente:
 * o admin pelo formulário, o cliente pelo próprio acesso. Nada do corpo da
 * requisição troca o cliente aqui dentro.
 */
class OpenTicket extends Action
{
    public function handle(Customer $customer, array $data, User $openedBy): ActionResult
    {
        if ($customer->trashed()) {
            return ActionResult::fail('Não é possível abrir chamado para um cliente inativo.');
        }

        $priority = $openedBy->role === UserRole::Admin && isset($data['priority'])
            ? TicketPriority::from($data['priority'])
            : TicketPriority::Medium;

        $ticket = $customer->tickets()->create([
            'opened_by' => $openedBy->id,
            'title' => $data['title'],
            'category' => $data['category'],
            'description' => $data['description'],
            'priority' => $priority,
        ]);

        return ActionResult::ok($ticket);
    }
}
