<?php

namespace App\Http\Requests\Tickets;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssignTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'assigned_to' => [
                'present',
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where('role', UserRole::Admin->value),
            ],
        ];
    }
}
