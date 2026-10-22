<?php

namespace App\Http\Requests\Tickets;

use App\Enums\TicketStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class IndexTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'customer_id' => ['nullable', 'integer'],
            'status' => ['nullable', new Enum(TicketStatus::class)],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
