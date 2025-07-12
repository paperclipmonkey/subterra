<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCaveRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->admin ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'cave_system_id' => ['nullable', 'integer', 'exists:cave_systems,id'],
            'length' => ['nullable', 'numeric', 'min:0'],
            'depth' => ['nullable', 'numeric', 'min:0'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'hero_image' => ['nullable', 'array'],
            'entrance_image' => ['nullable', 'array'],
        ];
    }
}
