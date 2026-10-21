<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Motivo obrigatório das correções (desfazer pedra / desfazer ganhador) —
 * toda correção fica registrada no histórico da partida.
 */
class RevertActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:3', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'reason.required' => 'Informe o motivo da correção.',
            'reason.min' => 'Descreva o motivo da correção com mais detalhes.',
        ];
    }
}
