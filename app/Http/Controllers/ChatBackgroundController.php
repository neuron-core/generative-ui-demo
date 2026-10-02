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
use NeuronAI\Agent\Adapters\AGUIAdapter;
use NeuronAI\Chat\History\MessageStoreInterface;
use NeuronAI\Exceptions\InputTranslationException;
use NeuronAI\Workflow\Streaming\Channel\RedisChannelReader;
use NeuronAI\Workflow\Streaming\ProtocolEvent;
use NeuronAI\Workflow\Streaming\SSEEncoder;
use NeuronAI\Workflow\WorkflowEngine;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class ChatBackgroundController extends Controller
{
    /**
     * Show the chat page, restoring the messages of the current conversation.
     */
    public function show(Request $request, MessageStoreInterface $messageStore, WorkflowEngine $workflowEngine): Response
    {
        $threadId = $this->currentThreadId($request);

        // The whole conversation, archived messages included, and the pending interruption of the thread's run.
        $page = (new AGUIAdapter($threadId))->hydrate($messageStore->loadAll($threadId), $workflowEngine->inspect($threadId));

        return Inertia::render('ChatBackground', [
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

        return to_route('chat-background');
    }

    /**
     * Queue the agent run, then relay to the browser the AG-UI events the worker publishes on Redis.
     */
    public function stream(RunAgentRequest $request): StreamedResponse
    {
        $threadId = $request->string('threadId')->toString();
        $runId = $request->string('runId')->toString();

        // The frontend tools are validated here, so a malformed declaration is a plain HTTP error instead of a failed job.
        try {
            BIAgent::make(workflowId: $threadId)->addFrontendTools($request->frontendTools());
        } catch (InputTranslationException $exception) {
            abort(400, $exception->getMessage());
        }

        $job = new RunBIAgent(
            $threadId,
            $runId,
            $request->prompt(),
            $request->messages(),
            $request->array('state'),
            array_values($request->array('resume')),
            array_values($request->array('tools')),
            $request->isContinuation(),
        );

        return response()->stream(function () use ($job, $threadId, $runId): void {
            try {
                // The job is dispatched first: its channel holds the first event until the reader below is listening.
                dispatch($job);

                (new RedisChannelReader(RedisStream::connect(), RedisStream::channel($threadId, $runId), timeout: 120))
                    ->listen(fn (ProtocolEvent $event) => $this->send(SSEEncoder::frame($event)));
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
}
