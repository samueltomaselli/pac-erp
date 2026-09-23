<?php

namespace App\Http\Requests\Proposals;

use App\Enums\ProposalItemType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class StoreProposalCatalogItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'type' => ['required', new Enum(ProposalItemType::class)],
            'default_quantity' => ['sometimes', 'required', 'integer', 'min:1', 'max:999999'],
            'default_unit_amount_cents' => ['required', 'integer', 'min:0'],
            'allows_installments' => ['sometimes', 'required', 'boolean'],
            'is_active' => ['sometimes', 'required', 'boolean'],
            'sort_order' => ['sometimes', 'required', 'integer', 'min:0', 'max:65535'],
        ];
    }
}
