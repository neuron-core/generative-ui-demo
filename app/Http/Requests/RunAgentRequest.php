<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use NeuronAI\Agent\Frontend\AGUIInputTranslator;
use NeuronAI\Exceptions\InputTranslationException;
use NeuronAI\Tools\FrontendTool;

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
            'tools' => ['nullable', 'array'],
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
     * The tools the frontend executes itself, declared by the CopilotKit chat on every request.
     *
     * @return FrontendTool[]
     *
     * @throws InputTranslationException
     */
    public function frontendTools(): array
    {
        return (new AGUIInputTranslator)->tools($this->all());
    }

    /**
     * Approval decisions and frontend tool results continue the suspended run instead of starting a new turn.
     * CopilotKit inserts a tool result right after the assistant message that made the call, so it is not
     * necessarily the last message: any tool result after the last user message answers the pending calls.
     */
    public function isContinuation(): bool
    {
        if ($this->array('resume') !== []) {
            return true;
        }

        $messages = $this->messages();
        $lastUserIndex = Arr::last(array_keys($messages), fn (int $index): bool => ($messages[$index]['role'] ?? null) === 'user') ?? -1;

        return collect($messages)
            ->slice($lastUserIndex + 1)
            ->contains(fn (array $message): bool => ($message['role'] ?? null) === 'tool');
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
