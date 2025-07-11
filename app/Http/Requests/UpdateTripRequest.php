<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTripRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Check if the user was a trip participant or is an admin
        if($this->user()->is_admin) {
            return true;
        }
        return $this->user()->trips()->where('trip_id', $this->route('trip')->id)->exists();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'cave_system_id' => ['sometimes', 'required', 'exists:cave_systems,id'],
            'entrance_cave_id' => ['sometimes', 'required', 'exists:caves,id'],
            'exit_cave_id' => ['sometimes', 'required', 'exists:caves,id'],
            'start_time' => ['sometimes', 'required', 'date_format:Y-m-d H:i:s'],
            'end_time' => ['sometimes', 'required', 'date_format:Y-m-d H:i:s', 'after_or_equal:start_time'],
            'visibility' => ['sometimes', 'in:public,private,club'],
            'participants' => ['sometimes', 'array'],
            'participants.*' => ['integer', 'exists:users,id'],
            'media' => ['nullable', 'array'],
            'media.*.data' => ['required', 'string'],
            'existing_media' => ['nullable', 'array'],
        ];
    }
}
