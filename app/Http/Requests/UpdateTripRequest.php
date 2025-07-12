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
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'start_time' => ['required', 'date'],
            'end_time' => ['required', 'date', 'after_or_equal:start_time'],
            'entrance_id' => ['required', 'integer', 'exists:caves,id'],
            'participants' => ['array'],
            'participants.*' => ['integer', 'exists:users,id'],
            'media' => ['array'],
            'media.*.data' => ['required', 'string'],
            'existing_media' => ['array'],
        ];
    }
}
