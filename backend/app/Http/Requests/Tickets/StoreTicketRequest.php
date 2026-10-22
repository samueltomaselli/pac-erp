<?php

namespace App\Http\Requests\Tickets;

use App\Enums\TicketCategory;
use App\Enums\TicketPriority;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

/**
 * Abertura pelo admin. O `customer_id` aceita cliente inativado de propósito:
 * quem recusa é a OpenTicket, com a mensagem de regra de negócio.
 */
class StoreTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'customer_id' => ['required', 'integer', Rule::exists('customers', 'id')],
            'title' => ['required', 'string', 'max:255'],
            'category' => ['required', new Enum(TicketCategory::class)],
            'description' => ['required', 'string', 'max:20000'],
            'priority' => ['nullable', new Enum(TicketPriority::class)],
        ];
    }
}
