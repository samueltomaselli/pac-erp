<?php

namespace App\Http\Requests\Customers;

use App\Enums\CustomerSegment;
use App\Enums\CustomerStatus;
use App\Rules\CpfOrCnpj;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class StoreCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('document')) {
            $this->merge(['document' => CpfOrCnpj::digits($this->input('document'))]);
        }
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'document' => ['required', 'string', new CpfOrCnpj, Rule::unique('customers', 'document')],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')],
            'phone' => ['nullable', 'string', 'max:30'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'segment' => ['required', new Enum(CustomerSegment::class)],
            'status' => ['nullable', new Enum(CustomerStatus::class)],
        ];
    }
}
