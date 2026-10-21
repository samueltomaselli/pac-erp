<?php

namespace App\Http\Requests\Tickets;

use Illuminate\Foundation\Http\FormRequest;

class StoreTicketCommentRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'body' => ['required', 'string'],
            'attachments' => ['sometimes', 'array', 'max:5'],
            'attachments.*' => ['file', 'max:10240', 'mimes:pdf,png,jpg,jpeg,webp,txt,csv,doc,docx,xls,xlsx'],
        ];
    }

    public function messages(): array
    {
        return [
            'attachments.max' => 'Você pode enviar no máximo 5 arquivos.',
            'attachments.*.max' => 'Cada arquivo pode ter no máximo 10 MB.',
            'attachments.*.mimes' => 'O tipo de arquivo enviado não é aceito.',
        ];
    }
}
