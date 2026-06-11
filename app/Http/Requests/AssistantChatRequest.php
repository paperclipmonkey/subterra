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
        // Data-steward mode supports long curation sessions, so the history
        // cap is lifted there (the service trims per config/assistant.php).
        $messagesRule = ['required', 'array', 'min:1'];
        if ($this->input('mode') !== 'data') {
            $messagesRule[] = 'max:20';
        }

        return [
            'messages' => $messagesRule,
            'messages.*.role' => ['required', 'string', 'in:user,assistant'],
            'messages.*.content' => ['required', 'string', 'max:4000'],
            'mode' => ['sometimes', 'string', 'in:default,data'],
        ];
    }

    public function messages(): array
    {
        return [
            'messages.required' => 'At least one message is required.',
            'messages.max' => 'Conversation history is limited to 20 messages.',
            'messages.*.role.in' => 'Message role must be "user" or "assistant".',
            'messages.*.content.max' => 'Each message may not exceed 4000 characters.',
        ];
    }
}
