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
use NeuronAI\Chat\History\MessageStoreInterface;
use NeuronAI\Chat\Messages\UserMessage;
use NeuronAI\Exceptions\InputTranslationException;
use NeuronAI\Exceptions\WorkflowException;
use NeuronAI\Workflow\Streaming\ProtocolEvent;
use NeuronAI\Workflow\Streaming\SSEEncoder;
use NeuronAI\Workflow\WorkflowEngine;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class ChatController extends Controller
{
    /**
     * Show the chat page, restoring the messages of the current conversation.
     */
    public function show(Request $request, MessageStoreInterface $messageStore, WorkflowEngine $workflowEngine): Response
    {
        $threadId = $this->currentThreadId($request);

        // The whole conversation, archived messages included, and the pending interruption of the thread's run.
        $page = (new AGUIAdapter($threadId))->hydrate($messageStore->loadAll($threadId), $workflowEngine->inspect($threadId));

        return Inertia::render('Chat', [
            'threadId' => $threadId,
            'messages' => $page['messages'],
            'pendingApprovals' => $page['interrupts'],
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

        $agent = BIAgent::make(workflowId: $request->string('threadId')->toString())
            ->setStreamAdapter(fn (): AGUIAdapter => $adapter);

        // Tools and answers are validated here, before the first frame, so a stale or malformed request is a plain HTTP error.
        $continuation = null;

        try {
            $agent->addFrontendTools($request->frontendTools());

            if ($request->isContinuation()) {
                $continuation = $agent->submitInputs([...$request->all(), 'messages' => $request->messages()], new AGUIInputTranslator);
            }
        } catch (InputTranslationException $exception) {
            abort(400, $exception->getMessage());
        } catch (WorkflowException $exception) {
            abort(409, $exception->getMessage());
        }

        $message = new UserMessage($request->prompt());

        return response()->stream(function () use ($continuation, $agent, $adapter, $message): void {
            try {
                $events = $continuation?->events() ?? $agent->stream($message);

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
}
