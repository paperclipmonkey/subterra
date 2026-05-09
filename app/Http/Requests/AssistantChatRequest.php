<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AssistantChatRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Auth enforced at the route middleware level
    }

    public function rules(): array
    {
        return [
            'messages'              => ['required', 'array', 'min:1', 'max:20'],
            'messages.*.role'       => ['required', 'string', 'in:user,assistant'],
            'messages.*.content'    => ['required', 'string', 'max:4000'],
        ];
    }

    public function messages(): array
    {
        return [
            'messages.required'           => 'At least one message is required.',
            'messages.max'                => 'Conversation history is limited to 20 messages.',
            'messages.*.role.in'          => 'Message role must be "user" or "assistant".',
            'messages.*.content.max'      => 'Each message may not exceed 4000 characters.',
        ];
    }
}
