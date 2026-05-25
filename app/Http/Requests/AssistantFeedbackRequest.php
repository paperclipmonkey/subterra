<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AssistantFeedbackRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Auth enforced at the route middleware level
    }

    public function rules(): array
    {
        return [
            'rating' => ['required', 'integer', 'in:-1,1'],
            'comment' => ['nullable', 'string', 'max:2000'],
            'messages' => ['required', 'array', 'min:1', 'max:50'],
            'messages.*.role' => ['required', 'string', 'in:user,assistant'],
            'messages.*.content' => ['required', 'string', 'max:8000'],
        ];
    }
}
