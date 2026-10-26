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
        // A backstop only: the controller enforces the per-mode user-turn limit
        // with a friendlier response, and the service trims what reaches the model.
        $messagesRule = ['required', 'array', 'min:1'];
        $maxTurns = $this->input('mode') === 'data' ? null : config('assistant.limits.max_user_turns');
        if ($maxTurns !== null) {
            $messagesRule[] = 'max:'.((int) $maxTurns * 2 + 2);
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
            'messages.max' => 'This conversation is too long. Start a new conversation to carry on.',
            'messages.*.role.in' => 'Message role must be "user" or "assistant".',
            'messages.*.content.max' => 'Each message may not exceed 4000 characters.',
        ];
    }
}
