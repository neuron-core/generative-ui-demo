<?php

namespace App\Http\Controllers;

use App\Http\Requests\RunAgentRequest;
use App\Jobs\RunBIAgent;
use App\Models\ChatMessage;
use App\Neuron\BIAgent;
use App\Neuron\RedisStream;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use NeuronAI\Chat\Messages\Message;
use NeuronAI\Chat\Messages\ToolCallMessage;
use NeuronAI\Chat\Messages\ToolResultMessage;
use NeuronAI\Tools\ToolCall;
use NeuronAI\Workflow\Streaming\ProtocolEvent;
use NeuronAI\Workflow\Streaming\SSEEncoder;
use Redis;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class ChatBackgroundController extends Controller
{
    /**
     * Show the chat page, restoring the messages of the current conversation.
     */
    public function show(Request $request): Response
    {
        $threadId = $this->currentThreadId($request);

        return Inertia::render('ChatBackground', [
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

        return to_route('chat-background');
    }

    /**
     * Queue the agent run, then relay to the browser the AG-UI events the worker publishes on Redis.
     */
    public function stream(RunAgentRequest $request): StreamedResponse
    {
        $threadId = $request->string('threadId')->toString();
        $runId = $request->string('runId')->toString();

        $job = new RunBIAgent(
            $threadId,
            $runId,
            $request->prompt(),
            array_values($request->array('messages')),
            $request->array('state'),
        );

        return response()->stream(function () use ($job, $threadId, $runId): void {
            try {
                $redis = RedisStream::connect(readTimeout: 120);

                // The job waits for the subscription below before running the agent, so no event is missed.
                dispatch($job);

                $redis->subscribe([RedisStream::channel($threadId, $runId)], function (Redis $redis, string $channel, string $payload): void {
                    // Neuron wraps every protocol event in a {streamId, sequence, type, data} envelope.
                    $envelope = json_decode($payload, true);

                    if (in_array($envelope['type'], ['stream.completed', 'stream.interrupted', 'stream.failed'])) {
                        $redis->unsubscribe([$channel]);

                        return;
                    }

                    $this->send(SSEEncoder::frame(new ProtocolEvent($envelope['type'], $envelope['data'])));
                });
            } catch (Throwable $exception) {
                report($exception);

                $this->send(SSEEncoder::frame(new ProtocolEvent('RUN_ERROR', ['message' => 'The run failed.'])));
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'X-Accel-Buffering' => 'no',
        ]);
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
