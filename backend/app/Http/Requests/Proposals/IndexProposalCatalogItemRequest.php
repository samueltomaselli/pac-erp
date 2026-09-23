<?php

namespace App\Http\Requests\Proposals;

use Illuminate\Foundation\Http\FormRequest;

class IndexProposalCatalogItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'active' => ['nullable', 'boolean'],
        ];
    }
}
