<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTripRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Check if the user was a trip participant or is an admin
        if ($this->user()->is_admin) {
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
        // Compare end_time against the incoming start_time, falling back to the
        // trip's stored start_time when the request doesn't change it, so a
        // negative duration can never be saved.
        $startTime = $this->input('start_time')
            ?? $this->route('trip')?->start_time?->toIso8601String();

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'cave_system_id' => ['sometimes', 'required', 'exists:cave_systems,id'],
            'entrance_cave_id' => ['sometimes', 'required', 'exists:caves,id'],
            'exit_cave_id' => ['sometimes', 'required', 'exists:caves,id'],
            'start_time' => ['sometimes', 'required', 'date'],
            'end_time' => array_merge(
                ['sometimes', 'required', 'date'],
                $this->filled('start_time')
                    ? ['after_or_equal:start_time']
                    : ($startTime ? ['after_or_equal:'.$startTime] : [])
            ),
            'visibility' => ['sometimes', 'in:public,private,club'],
            'participants' => ['sometimes', 'array'],
            'participants.*' => ['string', 'exists:users,id'],
            'media' => ['nullable', 'array'],
            'media.*.data' => ['required', 'file', 'max:512000', 'mimes:jpeg,jpg,png,gif,webp,bmp,tiff,tif,heic,heif'],
            'existing_media' => ['nullable', 'array'],
            'existing_media.*.id' => ['required', 'exists:trip_media,id'],
            'existing_media.*.title' => ['nullable', 'string', 'max:255'],
            'existing_media.*.copyright' => ['nullable', 'string', 'max:255'],
            'existing_media.*.photographer' => ['nullable', 'string', 'max:255'],
        ];
    }
}
