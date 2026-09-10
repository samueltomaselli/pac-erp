<?php

namespace App\Http\Requests\Tasks;

use App\Enums\TaskPriority;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class UpdateTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'due_date' => ['sometimes', 'required', 'date'],
            'priority' => ['sometimes', 'required', new Enum(TaskPriority::class)],
        ];
    }
}
