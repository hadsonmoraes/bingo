<?php

namespace App\Http\Requests;

use App\Models\Game;
use Illuminate\Foundation\Http\FormRequest;

class UpdateGameRequest extends FormRequest
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
            'letters_word' => ['nullable', 'string', 'max:10', 'regex:/^[\pL]{5}$/u'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'letters_word.regex' => 'A palavra do tema deve ter exatamente 5 letras (sem números ou símbolos).',
        ];
    }
}
