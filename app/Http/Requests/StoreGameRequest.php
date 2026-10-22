<?php

namespace App\Http\Requests;

use App\Models\Game;
use Illuminate\Foundation\Http\FormRequest;

class StoreGameRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:120'],
            'organization_name' => ['nullable', 'string', 'max:150'],
            'event_date' => ['nullable', 'date'],
            'card_price' => ['nullable', 'numeric', 'min:0'],
            'theme_color' => ['nullable', 'in:'.implode(',', array_keys(Game::THEME_COLORS))],
            'stub_code_type' => ['nullable', 'in:'.implode(',', array_keys(Game::STUB_CODE_TYPES))],
            'logo' => ['nullable', 'image', 'max:2048'],
            'card_format' => ['required', 'in:75,custom'],
            'number_min' => ['required', 'integer', 'min:1', 'max:200'],
            'number_max' => ['required', 'integer', 'gt:number_min', 'max:200'],
            'use_letters' => ['nullable', 'boolean'],
            'letters_word' => ['nullable', 'string', 'max:10', 'regex:/^[\pL]{5}$/u'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Informe o nome da partida.',
            'number_max.gt' => 'O número máximo deve ser maior que o mínimo.',
            'letters_word.regex' => 'A palavra do tema deve ter exatamente 5 letras (sem números ou símbolos).',
        ];
    }
}
