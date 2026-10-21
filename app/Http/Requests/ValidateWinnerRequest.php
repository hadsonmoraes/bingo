<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ValidateWinnerRequest extends FormRequest
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
            'card_code' => ['required', 'string'],
            'prize_id' => ['required', 'integer', 'exists:prizes,id'],
            'winner_name' => ['nullable', 'string', 'max:120'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'card_code.required' => 'Informe o código da cartela.',
            'prize_id.exists' => 'Prêmio inválido.',
        ];
    }
}
