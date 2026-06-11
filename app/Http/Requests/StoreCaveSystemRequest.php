<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCaveSystemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * `length` and `vertical_range` are NOT NULL integer columns, but the form
     * may submit an empty value when a measurement is unknown. Default those to 0
     * so creation never trips the not-null constraint.
     */
    protected function prepareForValidation(): void
    {
        foreach (['length', 'vertical_range'] as $field) {
            if ($this->has($field) && in_array($this->input($field), [null, ''], true)) {
                $this->merge([$field => 0]);
            }
        }
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
            'catchment_id' => 'nullable|exists:catchments,id',
        ];
    }
}
