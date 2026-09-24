<?php

namespace App\Http\Requests\Proposals;

use App\Enums\ProposalTemplateType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class UpdateProposalTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:150'],
            'type' => ['sometimes', 'required', new Enum(ProposalTemplateType::class)],
            'content' => ['sometimes', 'required', 'string', 'max:20000'],
        ];
    }
}
