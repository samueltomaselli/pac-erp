<?php

namespace App\Actions\Proposals\Concerns;

use App\Enums\ProposalItemType;
use App\Models\Proposal;

trait GuardsProposalItems
{
    protected function lockedMessage(Proposal $proposal): ?string
    {
        return $proposal->status->isEditable()
            ? null
            : 'Somente propostas em rascunho podem ter itens alterados.';
    }

    /**
     * @param  array{type: string, quantity: int, unit_amount_cents: int, discount_cents: int, installments: int}  $attributes
     */
    protected function invalidItemMessage(array $attributes): ?string
    {
        if ($attributes['discount_cents'] > $attributes['quantity'] * $attributes['unit_amount_cents']) {
            return 'O desconto não pode ser maior que o valor do item.';
        }

        if ($attributes['type'] === ProposalItemType::Recurring->value && $attributes['installments'] > 1) {
            return 'Item recorrente não pode ser parcelado.';
        }

        return null;
    }

    protected function reloaded(Proposal $proposal): Proposal
    {
        return $proposal->refresh()->load(['customer', 'items'])->loadCount('items');
    }
}
