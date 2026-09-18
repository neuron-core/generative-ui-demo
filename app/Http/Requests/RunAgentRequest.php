<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;

/**
 * The AG-UI "RunAgentInput" payload sent by the CopilotKit chat.
 */
class RunAgentRequest extends FormRequest
{
    /**
     * A user can only run the agent on their own conversation threads.
     */
    public function authorize(): bool
    {
        return str_starts_with((string) $this->input('threadId'), "user-{$this->user()->id}-");
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'threadId' => ['required', 'string', 'max:255'],
            'runId' => ['required', 'string', 'max:255'],
            'messages' => ['required', 'array'],
            'messages.*.id' => ['required', 'string'],
            'state' => ['nullable', 'array'],
            'resume' => ['nullable', 'array'],
        ];
    }

    /**
     * The AG-UI messages displayed by the frontend. Laravel converts empty strings to null, while the
     * protocol requires a string content: the agent sends these messages back in its snapshots.
     *
     * @return list<array<string, mixed>>
     */
    public function messages(): array
    {
        return array_values(array_map(
            fn (array $message): array => array_key_exists('content', $message) && $message['content'] === null
                ? [...$message, 'content' => '']
                : $message,
            $this->array('messages'),
        ));
    }

    /**
     * Approval decisions continue the suspended run instead of starting a new turn.
     */
    public function isContinuation(): bool
    {
        return $this->array('resume') !== [];
    }

    /**
     * The text of the last user message. Previous messages are already in the agent chat history.
     */
    public function prompt(): string
    {
        $message = Arr::last($this->input('messages'), fn (mixed $message): bool => ($message['role'] ?? null) === 'user');

        $content = $message['content'] ?? '';

        if (is_string($content)) {
            return $content;
        }

        return collect((array) $content)->where('type', 'text')->pluck('text')->implode("\n");
    }
}
