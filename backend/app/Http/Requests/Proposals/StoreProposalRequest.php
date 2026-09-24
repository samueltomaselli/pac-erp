<?php

namespace App\Http\Requests\Proposals;

use App\Enums\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
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
            'customer_id' => ['required', 'integer', Rule::exists('customers', 'id')->whereNull('deleted_at')],
            'title' => ['required', 'string', 'max:255'],
            'issued_on' => ['required', 'date'],
            'valid_until' => ['required', 'date', 'after_or_equal:issued_on'],
            'payment_method' => ['nullable', new Enum(PaymentMethod::class)],
            'payment_notes' => ['nullable', 'string', 'max:255'],
            'observations' => ['nullable', 'string', 'max:20000'],
            'terms' => ['nullable', 'string', 'max:20000'],
        ];
    }
}
