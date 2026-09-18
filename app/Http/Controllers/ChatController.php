<?php

namespace App\Http\Controllers;

use App\Http\Requests\RunAgentRequest;
use App\Models\ChatMessage;
use App\Neuron\BIAgent;
use Generator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use NeuronAI\Agent\Adapters\AGUIAdapter;
use NeuronAI\Chat\Messages\Message;
use NeuronAI\Chat\Messages\ToolCallMessage;
use NeuronAI\Chat\Messages\ToolResultMessage;
use NeuronAI\Chat\Messages\UserMessage;
use NeuronAI\Tools\ToolCall;
use NeuronAI\Workflow\Streaming\ProtocolEvent;
use NeuronAI\Workflow\Streaming\SSEEncoder;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class ChatController extends Controller
{
    /**
     * Show the chat page, restoring the messages of the current conversation.
     */
    public function show(Request $request): Response
    {
        $threadId = $this->currentThreadId($request);

        return Inertia::render('Chat', [
            'threadId' => $threadId,
            'messages' => $this->toAGUIMessages(
                BIAgent::make(threadId: $threadId)->getChatHistory()->getMessages()
            ),
        ]);
    }

    /**
     * Start a new conversation.
     */
    public function store(Request $request): RedirectResponse
    {
        $request->session()->put('chat_thread_id', $this->newThreadId($request));

        return to_route('chat');
    }

    /**
     * Run the agent and stream its response as AG-UI protocol events.
     */
    public function stream(RunAgentRequest $request): StreamedResponse
    {
        Log::debug("AI Agent Running In The HTTP Request Lifecycle!");

        $adapter = new AGUIAdapter(
            $request->string('threadId')->toString(),
            $request->string('runId')->toString(),
            array_values($request->array('messages')),
            $request->array('state'),
        );

        $agent = BIAgent::make(threadId: $request->string('threadId')->toString())->setStreamAdapter($adapter);
        $message = new UserMessage($request->prompt());

        return response()->stream(function () use ($agent, $adapter, $message): void {
            try {
                $events = $agent->stream($message);

                foreach ($events instanceof Generator ? $events : [] as $event) {
                    if ($event instanceof ProtocolEvent) {
                        $this->send(SSEEncoder::frame($event));
                    }
                }
            } catch (Throwable $exception) {
                report($exception);

                // Yields nothing when the workflow has already sent the RUN_ERROR event.
                foreach ($adapter->error($exception) as $event) {
                    $this->send(SSEEncoder::frame($event));
                }
            }
        }, 200, $adapter->getHeaders());
    }

    /**
     * Write a chunk to the response and push it to the client immediately.
     */
    protected function send(string $chunk): void
    {
        echo $chunk;

        if (ob_get_level() > 0) {
            ob_flush();
        }

        flush();
    }

    /**
     * The conversation in progress: the one in session, otherwise the last one the user had.
     */
    protected function currentThreadId(Request $request): string
    {
        $threadId = $request->session()->get('chat_thread_id')
            ?? ChatMessage::query()
                ->where('thread_id', 'like', "user-{$request->user()->id}-%")
                ->latest('id')
                ->value('thread_id')
            ?? $this->newThreadId($request);

        $request->session()->put('chat_thread_id', $threadId);

        return $threadId;
    }

    protected function newThreadId(Request $request): string
    {
        return "user-{$request->user()->id}-".Str::uuid();
    }

    /**
     * Convert the agent chat history to AG-UI messages.
     *
     * @param  Message[]  $messages
     * @return array<int, array<string, mixed>>
     */
    protected function toAGUIMessages(array $messages): array
    {
        return collect($messages)->flatMap(fn (Message $message): array => match (true) {
            $message instanceof ToolResultMessage => array_map(fn (ToolCall $call): array => [
                'id' => (string) Str::uuid(),
                'role' => 'tool',
                'toolCallId' => $call->getCallId(),
                'content' => (string) $call->getResult(),
            ], $message->getToolCalls()),
            $message instanceof ToolCallMessage => [[
                'id' => (string) Str::uuid(),
                'role' => 'assistant',
                'content' => $message->getContent() ?? '',
                'toolCalls' => array_map(fn (ToolCall $call): array => [
                    'id' => $call->getCallId(),
                    'type' => 'function',
                    'function' => [
                        'name' => $call->getName(),
                        'arguments' => json_encode($call->getInputs() ?: (object) []),
                    ],
                ], $message->getToolCalls()),
            ]],
            default => [[
                'id' => (string) Str::uuid(),
                'role' => $message->getRole(),
                'content' => $message->getContent() ?? '',
            ]],
        })->values()->all();
    }
}
