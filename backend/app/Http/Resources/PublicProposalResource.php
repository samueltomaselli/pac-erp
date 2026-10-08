<?php

namespace App\Http\Resources;

use App\Enums\PublicProposalStatus;
use App\Models\Proposal;
use App\Support\ProposalTotals;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Payload do link público. Tudo aqui é visível para qualquer pessoa com o link:
 * nenhum id sequencial, nenhum dado interno, do cliente só o nome.
 *
 * @mixin Proposal
 */
class PublicProposalResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $status = PublicProposalStatus::for($this->resource);

        return [
            'title' => $this->title,
            'issued_on' => $this->issued_on?->toDateString(),
            'valid_until' => $this->valid_until?->toDateString(),
            'payment_method' => $this->payment_method?->value,
            'payment_method_label' => $this->payment_method?->label(),
            'payment_notes' => $this->payment_notes,
            'observations' => $this->observations,
            'terms' => $this->terms,
            'status' => $status->value,
            'status_label' => $status->label(),
            'can_accept' => $status->canAccept(),
            'customer' => [
                'name' => $this->customer?->name,
            ],
            'items' => $this->items->map(fn ($item) => [
                'type' => $item->type->value,
                'type_label' => $item->type->label(),
                'description' => $item->description,
                'quantity' => $item->quantity,
                'unit_amount_cents' => $item->unit_amount_cents,
                'discount_cents' => $item->discount_cents,
                'installments' => $item->installments,
                'line_total_cents' => $item->line_total_cents,
                'installment_amount_cents' => $item->installment_amount_cents,
                'last_installment_cents' => $item->last_installment_cents,
            ])->all(),
            'totals' => ProposalTotals::summarize($this->items),
            'acceptance' => $this->acceptance ? [
                'name' => $this->acceptance->name,
                'accepted_at' => $this->acceptance->accepted_at->toIso8601String(),
            ] : null,
        ];
    }
}
