<?php

namespace App\Jobs;

use App\Neuron\BIAgent;
use App\Neuron\RedisStream;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use NeuronAI\Agent\Adapters\AGUIAdapter;
use NeuronAI\Agent\Frontend\AGUIInputTranslator;
use NeuronAI\Chat\Messages\UserMessage;
use NeuronAI\Workflow\Streaming\Channel\RedisChannel;
use Throwable;

class RunBIAgent implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 300;

    /**
     * @param  list<array<string, mixed>>  $messages  The AG-UI messages currently displayed by the frontend
     * @param  array<string, mixed>  $state
     * @param  list<array<string, mixed>>  $resume  The approval decisions continuing a suspended run
     * @param  list<array<string, mixed>>  $tools  The AG-UI declarations of the tools the frontend executes
     * @param  bool  $continuation  Whether approval decisions or frontend tool results continue a suspended run
     */
    public function __construct(
        public string $threadId,
        public string $runId,
        public string $prompt,
        public array $messages = [],
        public array $state = [],
        public array $resume = [],
        public array $tools = [],
        public bool $continuation = false,
    ) {}

    /**
     * Run the agent in the background, pushing its AG-UI events to Redis instead of an HTTP response.
     */
    public function handle(): void
    {
        Log::debug('AI Agent Running In a Background Job!');

        $redis = RedisStream::connect();
        $channel = RedisStream::channel($this->threadId, $this->runId);

        // Redis Pub/Sub does not replay missed messages: the first publish waits for the HTTP request to listen.
        // The events are delivered to the channel while they are consumed: stream() is lazy, run() consumes eagerly.
        $agent = BIAgent::make(workflowId: $this->threadId)
            ->setStreamAdapter(fn (): AGUIAdapter => new AGUIAdapter($this->threadId, $this->runId, $this->messages, $this->state))
            ->setChannel(fn (): RedisChannel => new RedisChannel($redis, $channel, awaitListener: 10))
            ->addFrontendTools((new AGUIInputTranslator)->tools(['tools' => $this->tools]));

        if ($this->continuation) {
            // The translator reads any "resume" key as approval decisions, so frontend tool results must be sent without it.
            $payload = $this->resume === [] ? ['messages' => $this->messages] : ['messages' => $this->messages, 'resume' => $this->resume];

            $agent->submitInputs($payload, new AGUIInputTranslator)->run();

            return;
        }

        $agent->chat(new UserMessage($this->prompt), true);
    }

    /**
     * Failures during the run are published by the agent itself. This covers the ones
     * happening around it (e.g. the job timeout), so the browser is never left waiting.
     */
    public function failed(?Throwable $exception): void
    {
        $redis = RedisStream::connect();
        $channel = RedisStream::channel($this->threadId, $this->runId);

        // The envelope RedisChannelReader expects. If the run had already published, the reader
        // refuses this second segment and the controller answers with its own RUN_ERROR.
        $streamId = (string) Str::uuid();

        $redis->publish($channel, json_encode(['streamId' => $streamId, 'sequence' => 0, 'type' => 'RUN_ERROR', 'data' => ['message' => 'The run failed.']], JSON_THROW_ON_ERROR));
        $redis->publish($channel, json_encode(['streamId' => $streamId, 'sequence' => 1, 'type' => 'stream.failed', 'data' => []], JSON_THROW_ON_ERROR));
    }
}
