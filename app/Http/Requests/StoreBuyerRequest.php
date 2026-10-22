<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBuyerRequest extends FormRequest
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
            'buyer_name' => ['nullable', 'string', 'max:150'],
            'buyer_phone' => ['nullable', 'string', 'max:40'],
        ];
    }
}
