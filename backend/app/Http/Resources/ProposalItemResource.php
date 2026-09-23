<?php

namespace App\Http\Resources;

use App\Models\ProposalItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ProposalItem */
class ProposalItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $lineTotal = $this->line_total_cents;
        $installment = intdiv($lineTotal, $this->installments);

        return [
            'id' => $this->id,
            'catalog_item_id' => $this->catalog_item_id,
            'type' => $this->type->value,
            'type_label' => $this->type->label(),
            'description' => $this->description,
            'quantity' => $this->quantity,
            'unit_amount_cents' => $this->unit_amount_cents,
            'discount_cents' => $this->discount_cents,
            'installments' => $this->installments,
            'line_total_cents' => $lineTotal,
            'installment_amount_cents' => $installment,
            'last_installment_cents' => $lineTotal - ($installment * ($this->installments - 1)),
        ];
    }
}
