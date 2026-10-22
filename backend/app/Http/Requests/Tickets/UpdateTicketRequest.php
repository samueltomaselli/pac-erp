<?php

namespace App\Http\Requests\Tickets;

use App\Enums\TicketCategory;
use App\Enums\TicketPriority;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class UpdateTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'category' => ['sometimes', 'required', new Enum(TicketCategory::class)],
            'description' => ['sometimes', 'required', 'string', 'max:20000'],
            'priority' => ['sometimes', 'required', new Enum(TicketPriority::class)],
        ];
    }
}
