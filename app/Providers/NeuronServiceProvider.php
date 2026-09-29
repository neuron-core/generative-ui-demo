<?php

namespace App\Providers;

use App\Models\ChatMessage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use NeuronAI\Chat\History\EloquentMessageStore;
use NeuronAI\Chat\History\MessageStoreInterface;
use NeuronAI\Workflow\Persistence\DatabasePersistence;
use NeuronAI\Workflow\Persistence\PersistenceInterface;

class NeuronServiceProvider extends ServiceProvider
{
    /**
     * The storage shared by the agent and the application, which reads the conversations without instantiating the agent.
     */
    public function register(): void
    {
        $this->app->bind(MessageStoreInterface::class, fn (): MessageStoreInterface => new EloquentMessageStore(ChatMessage::class));

        // The WorkflowEngine is autowired from this persistence, to inspect the agent runs.
        $this->app->bind(PersistenceInterface::class, fn (): PersistenceInterface => new DatabasePersistence(DB::connection()->getPdo()));
    }
}
