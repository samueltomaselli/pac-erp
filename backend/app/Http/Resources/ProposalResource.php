<?php

namespace App\Http\Resources;

use App\Models\Proposal;
use App\Support\ProposalTotals;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Proposal
 */
class ProposalResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'customer_id' => $this->customer_id,
            'title' => $this->title,
            'issued_on' => $this->issued_on?->toDateString(),
            'valid_until' => $this->valid_until?->toDateString(),
            'payment_method' => $this->payment_method?->value,
            'payment_method_label' => $this->payment_method?->label(),
            'payment_notes' => $this->payment_notes,
            'observations' => $this->observations,
            'terms' => $this->terms,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'is_editable' => $this->is_editable,
            'allowed_transitions' => array_map(fn ($status) => [
                'status' => $status->value,
                'label' => $status->label(),
            ], $this->status->allowedTransitions()),
            'totals' => ProposalTotals::summarize($this->items),
            'items_count' => $this->whenCounted('items'),
            'sent_at' => $this->sent_at?->toIso8601String(),
            'decided_at' => $this->decided_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'customer' => $this->whenLoaded('customer', fn () => [
                'id' => $this->customer->id,
                'name' => $this->customer->name,
            ]),
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($item) => [
                'id' => $item->id,
                'catalog_item_id' => $item->catalog_item_id,
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
            ])->all()),
        ];
    }
}
