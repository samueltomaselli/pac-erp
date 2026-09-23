<?php

namespace App\Http\Requests\Proposals;

use App\Enums\ProposalTemplateType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class IndexProposalTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['nullable', new Enum(ProposalTemplateType::class)],
        ];
    }
}
