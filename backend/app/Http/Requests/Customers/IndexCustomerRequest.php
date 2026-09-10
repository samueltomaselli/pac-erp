<?php

namespace App\Http\Requests\Customers;

use App\Enums\CustomerSegment;
use App\Enums\CustomerStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class IndexCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', new Enum(CustomerStatus::class)],
            'segment' => ['nullable', new Enum(CustomerSegment::class)],
            'trashed' => ['nullable', Rule::in(['with', 'only'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
