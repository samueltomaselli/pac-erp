<?php

namespace App\Actions\Proposals;

use App\Actions\Action;
use App\Actions\Proposals\Concerns\GuardsProposalItems;
use App\Actions\Support\ActionResult;
use App\Models\Proposal;
use App\Models\ProposalItem;

class RemoveProposalItem extends Action
{
    use GuardsProposalItems;

    public function handle(Proposal $proposal, ProposalItem $item): ActionResult
    {
        if ($message = $this->lockedMessage($proposal)) {
            return ActionResult::fail($message);
        }

        $item->delete();

        return ActionResult::ok($this->reloaded($proposal));
    }
}
