<?php

namespace App\Http\Requests\Customers;

use App\Enums\CustomerSegment;
use App\Enums\CustomerStatus;
use App\Models\Customer;
use App\Rules\CpfOrCnpj;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class UpdateCustomerRequest extends FormRequest
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
        /** @var Customer $customer */
        $customer = $this->route('customer');

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'document' => [
                'sometimes', 'required', 'string', new CpfOrCnpj,
                Rule::unique('customers', 'document')->ignore($customer->id),
            ],
            'email' => [
                'sometimes', 'required', 'string', 'email', 'max:255',
                Rule::unique('users', 'email')->ignore($customer->user_id),
            ],
            'phone' => ['nullable', 'string', 'max:30'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'segment' => ['sometimes', 'required', new Enum(CustomerSegment::class)],
            'status' => ['sometimes', 'required', new Enum(CustomerStatus::class)],
        ];
    }
}
