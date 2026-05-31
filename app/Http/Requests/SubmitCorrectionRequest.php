<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SubmitCorrectionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
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
            'correction' => ['required', 'string', 'min:10'],
            'entity_type' => ['required', 'string'],
            'entity_id' => ['required'],
            'entity_name' => ['required', 'string'],
            'url' => ['required', 'string'],
        ];
    }
}
