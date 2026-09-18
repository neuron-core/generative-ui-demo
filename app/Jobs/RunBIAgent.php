<?php

namespace App\Jobs;

use App\Neuron\BIAgent;
use App\Neuron\RedisStream;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use NeuronAI\Agent\Adapters\AGUIAdapter;
use NeuronAI\Chat\Messages\UserMessage;
use NeuronAI\Workflow\Streaming\Channel\RedisChannel;
use Redis;
use Throwable;

class RunBIAgent implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 300;

    /**
     * @param  list<array<string, mixed>>  $messages  The AG-UI messages currently displayed by the frontend
     * @param  array<string, mixed>  $state
     */
    public function __construct(
        public string $threadId,
        public string $runId,
        public string $prompt,
        public array $messages = [],
        public array $state = [],
    ) {}

    /**
     * Run the agent in the background, pushing its AG-UI events to Redis instead of an HTTP response.
     */
    public function handle(): void
    {
        Log::debug("AI Agent Running In a Background Job!");

        $redis = RedisStream::connect();
        $channel = RedisStream::channel($this->threadId, $this->runId);

        $this->waitForSubscriber($redis, $channel);

        // With both an adapter and a channel the agent streams eagerly to the channel and returns the final state.
        BIAgent::make(threadId: $this->threadId)
            ->setStreamAdapter(new AGUIAdapter($this->threadId, $this->runId, $this->messages, $this->state))
            ->setChannel(new RedisChannel($redis, $channel))
            ->stream(new UserMessage($this->prompt));
    }

    /**
     * Failures during the run are published by the agent itself. This covers the ones
     * happening around it (e.g. the job timeout), so the browser is never left waiting.
     */
    public function failed(?Throwable $exception): void
    {
        $redis = RedisStream::connect();
        $channel = RedisStream::channel($this->threadId, $this->runId);

        $redis->publish($channel, json_encode(['type' => 'RUN_ERROR', 'data' => ['message' => 'The run failed.']], JSON_THROW_ON_ERROR));
        $redis->publish($channel, json_encode(['type' => 'stream.failed', 'data' => []], JSON_THROW_ON_ERROR));
    }

    /**
     * Redis Pub/Sub does not replay missed messages: start only when the HTTP request is listening.
     */
    protected function waitForSubscriber(Redis $redis, string $channel): void
    {
        $deadline = now()->addSeconds(10);

        while (($redis->pubsub('numsub', [$channel])[$channel] ?? 0) < 1 && now()->lessThan($deadline)) {
            usleep(50_000);
        }
    }
}
