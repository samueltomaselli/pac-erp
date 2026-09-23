<?php

namespace App\Http\Requests\Proposals;

use App\Enums\ProposalItemType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class UpdateProposalItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['sometimes', 'required', new Enum(ProposalItemType::class)],
            'description' => ['sometimes', 'required', 'string', 'max:255'],
            'quantity' => ['sometimes', 'required', 'integer', 'min:1', 'max:999999'],
            'unit_amount_cents' => ['sometimes', 'required', 'integer', 'min:0'],
            'discount_cents' => ['sometimes', 'required', 'integer', 'min:0'],
            'installments' => ['sometimes', 'required', 'integer', 'min:1', 'max:12'],
        ];
    }
}
