<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCaveSystemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'length' => 'nullable|integer|min:0',
            'vertical_range' => 'nullable|integer',
            'slug' => 'nullable|string|max:255|unique:cave_systems,slug',
            'references' => 'nullable|string',
        ];
    }
}
