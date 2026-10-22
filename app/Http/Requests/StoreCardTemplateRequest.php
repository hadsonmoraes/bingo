<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCardTemplateRequest extends FormRequest
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
            'art' => ['required', 'file', 'max:4096'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Informe um nome para o modelo.',
            'art.required' => 'Selecione o arquivo de arte (SVG, PNG ou PDF).',
            'art.max' => 'O arquivo deve ter no máximo 4 MB.',
        ];
    }
}
