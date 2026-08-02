<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;

class StoreTripRequest extends FormRequest
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
            'cave_system_id' => 'required|exists:cave_systems,id',
            'entrance_cave_id' => 'required|exists:caves,id',
            'exit_cave_id' => 'required|exists:caves,id',
            'start_time' => 'nullable|date',
            'end_time' => 'nullable|date|after_or_equal:start_time',
            'visibility' => 'in:public,private,club',
            'media' => 'nullable|array',
            'media.*.data' => 'required|file|max:512000|mimes:jpeg,jpg,png,gif,webp,bmp,tiff,tif,heic,heif', // Images only, 512MB max
            'participants' => 'required|array|min:1',
            'participants.*' => 'string|exists:users,id',
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'media.*.data.max' => 'One or more of the selected photos is too large. The maximum size is 512MB.',
            'media.*.data.file' => 'One or more of the selected photos failed to upload correctly.',
            'media.*.data.required' => 'An empty file was provided.',
        ];
    }

    /**
     * Handle a failed validation attempt.
     *
     * @param  \Illuminate\Contracts\Validation\Validator  $validator
     * @return void
     *
     * @throws \Illuminate\Http\Exceptions\HttpResponseException
     */
    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(
            new JsonResponse([
                'message' => 'The given data was invalid.',
                'errors' => $validator->errors(),
            ], JsonResponse::HTTP_UNPROCESSABLE_ENTITY)
        );
    }
}
