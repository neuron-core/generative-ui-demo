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
use NeuronAI\Agent\Frontend\AGUIInputTranslator;
use NeuronAI\Chat\Messages\Message;
use NeuronAI\Chat\Messages\ToolCallMessage;
use NeuronAI\Chat\Messages\ToolResultMessage;
use NeuronAI\Chat\Messages\UserMessage;
use NeuronAI\Exceptions\InputTranslationException;
use NeuronAI\Exceptions\WorkflowException;
use NeuronAI\Tools\ToolCall;
use NeuronAI\Workflow\Interrupt\Action;
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

        $agent = BIAgent::make(threadId: $threadId);
        $messages = $agent->getChatHistory()->getMessages();
        $pendingApprovals = $this->toAGUIInterrupts($agent->pendingApprovals());

        return Inertia::render('Chat', [
            'threadId' => $threadId,
            // Like in the live run, the tool call waiting for approval is shown by the approval card only.
            'messages' => $this->toAGUIMessages($pendingApprovals === [] ? $messages : array_slice($messages, 0, -1)),
            'pendingApprovals' => $pendingApprovals,
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
        Log::debug('AI Agent Running In The HTTP Request Lifecycle!');

        $adapter = new AGUIAdapter(
            $request->string('threadId')->toString(),
            $request->string('runId')->toString(),
            $request->messages(),
            $request->array('state'),
        );

        $agent = BIAgent::make(threadId: $request->string('threadId')->toString())->setStreamAdapter($adapter);

        // The decisions are validated here, before the first frame, so a stale or malformed answer is a plain HTTP error.
        if ($request->isContinuation()) {
            try {
                $agent->submitInputs($request->all(), new AGUIInputTranslator);
            } catch (InputTranslationException $exception) {
                abort(400, $exception->getMessage());
            } catch (WorkflowException $exception) {
                abort(409, $exception->getMessage());
            }
        }

        $message = new UserMessage($request->prompt());

        return response()->stream(function () use ($request, $agent, $adapter, $message): void {
            try {
                $events = $request->isContinuation() ? $agent->events() : $agent->stream($message);

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
     * Convert the approvals a suspended run is waiting for to AG-UI "confirmation" interrupts,
     * the same the stream adapter sends when the run is suspended.
     *
     * @param  Action[]  $actions
     * @return list<array{id: string, reason: string, message: string, metadata: array<string, mixed>}>
     */
    protected function toAGUIInterrupts(array $actions): array
    {
        return array_map(fn (Action $action): array => [
            'id' => $action->id,
            'reason' => 'confirmation',
            'message' => $action->reason ?? 'This tool call requires approval before execution',
            'metadata' => $action->jsonSerialize(),
        ], $actions);
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
