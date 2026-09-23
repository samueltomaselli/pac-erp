<?php

namespace App\Http\Requests\Proposals;

use App\Enums\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class StoreProposalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'title' => ['required', 'string', 'max:255'],
            'issued_on' => ['required', 'date'],
            'valid_until' => ['required', 'date', 'after_or_equal:issued_on'],
            'payment_method' => ['nullable', new Enum(PaymentMethod::class)],
            'payment_notes' => ['nullable', 'string', 'max:255'],
            'observations' => ['nullable', 'string'],
            'terms' => ['nullable', 'string'],
            'status' => ['prohibited'],
        ];
    }
}
