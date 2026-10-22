<?php

namespace App\Http\Requests\Tickets;

use App\Enums\TicketCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

/**
 * Abertura pelo cliente. `customer_id` e `priority` ficam fora das regras,
 * então nunca chegam ao `validated()` mesmo que venham no corpo.
 */
class StoreCustomerTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'category' => ['required', new Enum(TicketCategory::class)],
            'description' => ['required', 'string', 'max:20000'],
        ];
    }
}
