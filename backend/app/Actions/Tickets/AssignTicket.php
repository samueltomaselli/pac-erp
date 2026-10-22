<?php

namespace App\Actions\Tickets;

use App\Actions\Action;
use App\Actions\Support\ActionResult;
use App\Enums\UserRole;
use App\Models\Ticket;
use App\Models\User;

/**
 * Define ou remove o responsável. Não mexe no status.
 */
class AssignTicket extends Action
{
    public function handle(Ticket $ticket, ?User $assignee): ActionResult
    {
        if ($assignee && $assignee->role !== UserRole::Admin) {
            return ActionResult::fail('O responsável precisa ser um usuário da equipe.');
        }

        $ticket->assigned_to = $assignee?->id;
        $ticket->save();

        return ActionResult::ok($ticket);
    }
}
