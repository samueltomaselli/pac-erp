<?php

namespace App\Actions\Proposals;

use App\Actions\Action;
use App\Actions\Support\ActionResult;
use App\Enums\ProposalStatus;
use App\Models\Proposal;

class TransitionProposalStatus extends Action
{
    public function handle(Proposal $proposal, ProposalStatus $to): ActionResult
    {
        if (! $proposal->status->canTransitionTo($to)) {
            return ActionResult::fail(sprintf(
                'Não é possível mudar de %s para %s.',
                $proposal->status->label(),
                $to->label(),
            ));
        }

        if ($to === ProposalStatus::Sent && $proposal->items()->count() < 1) {
            return ActionResult::fail('Não é possível enviar uma proposta sem itens.');
        }

        $proposal->status = $to;

        if ($to === ProposalStatus::Sent) {
            $proposal->sent_at = now();
            $proposal->decided_at = null;
        }

        if ($to === ProposalStatus::Accepted || $to === ProposalStatus::Rejected) {
            $proposal->decided_at = now();
        }

        $proposal->save();

        return ActionResult::ok($proposal->fresh()->load(['customer', 'items']));
    }
}
