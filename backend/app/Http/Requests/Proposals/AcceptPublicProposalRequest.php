<?php

namespace App\Http\Requests\Proposals;

use App\Enums\PublicProposalStatus;
use App\Rules\CpfOrCnpj;
use Illuminate\Foundation\Http\FormRequest;

class AcceptPublicProposalRequest extends FormRequest
{
    /**
     * Roda antes da validação: rascunho e recusada respondem 404 mesmo com o
     * corpo inválido, senão o 422 confirmaria que o link existe.
     */
    public function authorize(): bool
    {
        return PublicProposalStatus::for($this->route('proposal')) !== null;
    }

    protected function failedAuthorization(): void
    {
        abort(404);
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
            'document' => ['required', 'string', new CpfOrCnpj],
            'email' => ['required', 'string', 'email', 'max:255'],
        ];
    }
}
