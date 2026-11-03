<?php

namespace App\Http\Requests\Tickets;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Rules\In;

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
            'active' => ['nullable', 'in:1'],
            'priority' => ['nullable', new Enum(TicketPriority::class)],
            'assigned_to' => [
                'nullable',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if ($value !== 'none' && filter_var($value, FILTER_VALIDATE_INT) === false) {
                        $fail('O campo responsável deve ser um número inteiro ou none.');
                    }
                },
            ],
            'q' => ['nullable', 'string'],
            'sort' => ['nullable', new In(['recent', 'updated', 'priority'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'priority.enum' => 'A prioridade selecionada é inválida.',
            'sort.in' => 'A ordenação selecionada é inválida.',
        ];
    }
}
