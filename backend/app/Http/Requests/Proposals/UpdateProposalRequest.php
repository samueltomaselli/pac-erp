<?php

namespace App\Http\Requests\Proposals;

use App\Enums\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class UpdateProposalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'issued_on' => ['sometimes', 'required', 'date'],
            'valid_until' => ['sometimes', 'required', 'date', 'after_or_equal:issued_on'],
            'payment_method' => ['nullable', new Enum(PaymentMethod::class)],
            'payment_notes' => ['nullable', 'string', 'max:255'],
            'observations' => ['nullable', 'string', 'max:20000'],
            'terms' => ['nullable', 'string', 'max:20000'],
        ];
    }
}
