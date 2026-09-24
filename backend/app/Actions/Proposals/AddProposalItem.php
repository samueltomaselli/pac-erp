<?php

namespace App\Actions\Proposals;

use App\Actions\Action;
use App\Actions\Proposals\Concerns\GuardsProposalItems;
use App\Actions\Support\ActionResult;
use App\Models\Proposal;
use App\Models\ProposalCatalogItem;

class AddProposalItem extends Action
{
    use GuardsProposalItems;

    public function handle(Proposal $proposal, array $data): ActionResult
    {
        if ($message = $this->lockedMessage($proposal)) {
            return ActionResult::fail($message);
        }

        $catalogItem = isset($data['catalog_item_id'])
            ? ProposalCatalogItem::findOrFail($data['catalog_item_id'])
            : null;

        if ($catalogItem && ! $catalogItem->is_active) {
            return ActionResult::fail('Este item do catálogo está inativo.');
        }

        $attributes = array_merge(
            ['discount_cents' => 0, 'installments' => 1],
            $catalogItem ? [
                'catalog_item_id' => $catalogItem->id,
                'type' => $catalogItem->type->value,
                'description' => $catalogItem->name,
                'quantity' => $catalogItem->default_quantity,
                'unit_amount_cents' => $catalogItem->default_unit_amount_cents,
            ] : [],
            array_filter($data, fn ($value) => $value !== null),
        );

        if ($catalogItem && ! $catalogItem->allows_installments && $attributes['installments'] > 1) {
            return ActionResult::fail('Este item do catálogo não permite parcelamento.');
        }

        if ($message = $this->invalidItemMessage($attributes)) {
            return ActionResult::fail($message);
        }

        $proposal->items()->create($attributes);

        return ActionResult::ok($this->reloaded($proposal));
    }
}
