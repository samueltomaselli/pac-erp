<?php

namespace App\Http\Resources;

use App\Models\ProposalCatalogItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ProposalCatalogItem
 */
class CatalogItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'type' => $this->type->value,
            'type_label' => $this->type->label(),
            'default_quantity' => $this->default_quantity,
            'default_unit_amount_cents' => $this->default_unit_amount_cents,
            'allows_installments' => $this->allows_installments,
            'is_active' => $this->is_active,
            'sort_order' => $this->sort_order,
        ];
    }
}
