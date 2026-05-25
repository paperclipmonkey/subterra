<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCaveRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->is_admin ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'cave_system_id' => ['nullable', 'integer', 'exists:cave_systems,id'],
            'length' => ['nullable', 'numeric', 'min:0'],
            'depth' => ['nullable', 'numeric', 'min:0'],
            'location_lat' => ['sometimes', 'numeric', 'between:-90,90'],
            'location_lng' => ['sometimes', 'numeric', 'between:-180,180'],
            'location_alt' => ['sometimes', 'nullable', 'numeric'],
            'location_name' => ['sometimes', 'required', 'string', 'max:255'],
            'location_country' => ['sometimes', 'required', 'string', 'max:255'],
            'access_info' => ['nullable', 'string'],
            'hero_image' => ['nullable', 'array'],
            'hero_image.data' => ['nullable', 'file', 'max:512000'],
            'hero_image.title' => ['nullable', 'string', 'max:255'],
            'hero_image.photographer' => ['nullable', 'string', 'max:255'],
            'hero_image.copyright' => ['nullable', 'string', 'max:255'],
            'entrance_image' => ['nullable', 'array'],
            'entrance_image.data' => ['nullable', 'file', 'max:512000'],
            'entrance_image.title' => ['nullable', 'string', 'max:255'],
            'entrance_image.photographer' => ['nullable', 'string', 'max:255'],
            'entrance_image.copyright' => ['nullable', 'string', 'max:255'],
            'hero_video' => ['nullable', 'array'],
            'hero_video.data' => ['nullable', 'file', 'max:512000', 'mimetypes:video/mp4,video/quicktime,video/webm'],
            'hero_video.title' => ['nullable', 'string', 'max:255'],
            'hero_video.photographer' => ['nullable', 'string', 'max:255'],
            'hero_video.copyright' => ['nullable', 'string', 'max:255'],
            'tags' => ['nullable', 'array'],
            'tags.*.category' => ['required_with:tags', 'string'],
            'tags.*.tag' => ['required_with:tags', 'string'],
        ];
    }
}
