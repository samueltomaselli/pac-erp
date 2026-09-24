<?php

namespace App\Http\Requests\Proposals;

use App\Enums\ProposalTemplateType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class StoreProposalTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'type' => ['required', new Enum(ProposalTemplateType::class)],
            'content' => ['required', 'string', 'max:20000'],
        ];
    }
}
