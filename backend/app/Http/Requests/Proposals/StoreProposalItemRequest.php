<?php

namespace App\Http\Requests\Proposals;

use App\Enums\ProposalItemType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class StoreProposalItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'catalog_item_id' => ['nullable', 'integer', 'exists:proposal_catalog_items,id'],
            'type' => ['required_without:catalog_item_id', new Enum(ProposalItemType::class)],
            'description' => ['required_without:catalog_item_id', 'string', 'max:255'],
            'quantity' => ['required_without:catalog_item_id', 'integer', 'min:1', 'max:999999'],
            'unit_amount_cents' => ['required_without:catalog_item_id', 'integer', 'min:0'],
            'discount_cents' => ['nullable', 'integer', 'min:0'],
            'installments' => ['nullable', 'integer', 'min:1', 'max:12'],
        ];
    }
}
