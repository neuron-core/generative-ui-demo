<?php

namespace App\Neuron;

use Redis;

/**
 * The Redis Pub/Sub link between the queue worker running the agent and the HTTP request streaming to the browser.
 */
class RedisStream
{
    /**
     * A dedicated phpredis client: Neuron's RedisChannel publishes on it, its RedisChannelReader subscribes with it.
     */
    public static function connect(): Redis
    {
        $config = config('database.redis.default');

        $redis = new Redis;
        $redis->connect($config['host'], (int) $config['port']);

        if (filled($config['password'] ?? null)) {
            $redis->auth($config['password']);
        }

        return $redis;
    }

    /**
     * Every agent run is published on its own channel.
     */
    public static function channel(string $threadId, string $runId): string
    {
        return "agent-stream:{$threadId}:{$runId}";
    }
}
