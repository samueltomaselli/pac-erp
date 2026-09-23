<?php

namespace App\Actions\Proposals;

use App\Actions\Action;
use App\Actions\Proposals\Concerns\GuardsProposalItems;
use App\Actions\Support\ActionResult;
use App\Models\Proposal;
use App\Models\ProposalItem;

class UpdateProposalItem extends Action
{
    use GuardsProposalItems;

    public function handle(Proposal $proposal, ProposalItem $item, array $data): ActionResult
    {
        if ($message = $this->lockedMessage($proposal)) {
            return ActionResult::fail($message);
        }

        $attributes = array_merge([
            'type' => $item->type->value,
            'quantity' => $item->quantity,
            'unit_amount_cents' => $item->unit_amount_cents,
            'discount_cents' => $item->discount_cents,
            'installments' => $item->installments,
        ], $data);

        if ($message = $this->invalidItemMessage($attributes)) {
            return ActionResult::fail($message);
        }

        $item->fill($data)->save();

        return ActionResult::ok($this->reloaded($proposal));
    }
}
