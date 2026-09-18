<?php

namespace App\Neuron;

use App\Models\ChatMessage;
use App\Neuron\Tools\DatabaseSchemaTool;
use App\Neuron\Tools\RenderCardsTool;
use App\Neuron\Tools\RenderChartTool;
use App\Neuron\Tools\RenderTableTool;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use NeuronAI\Agent\Agent;
use NeuronAI\Agent\SystemPrompt;
use NeuronAI\Chat\History\ChatHistoryInterface;
use NeuronAI\Chat\History\EloquentChatHistory;
use NeuronAI\Providers\AIProviderInterface;
use NeuronAI\Providers\Anthropic\Anthropic;
use NeuronAI\Providers\OpenAI\OpenAI;
use NeuronAI\Tools\ToolInterface;
use NeuronAI\Tools\Toolkits\MySQL\MySQLSchemaTool;
use NeuronAI\Tools\Toolkits\MySQL\MySQLToolkit;
use NeuronAI\Tools\Toolkits\MySQL\MySQLWriteTool;
use NeuronAI\Workflow\Persistence\DatabasePersistence;
use NeuronAI\Workflow\Persistence\PersistenceInterface;

class BIAgent extends Agent
{
    /**
     * The only tables the agent is told about.
     */
    protected const TABLES = ['users', 'categories', 'products', 'sales', 'sale_items'];

    /**
     * The LLM is selected by the AI_PROVIDER environment variable.
     */
    protected function provider(): AIProviderInterface
    {
        $provider = config('services.ai.provider');

        return match ($provider) {
            'anthropic' => new Anthropic(
                key: config('services.anthropic.key'),
                model: config('services.anthropic.model'),
            ),
            'openai' => new OpenAI(
                key: config('services.openai.key'),
                model: config('services.openai.model'),
            ),
            default => throw new InvalidArgumentException("Unsupported AI provider [{$provider}]. Use \"anthropic\" or \"openai\"."),
        };
    }

    protected function instructions(): string
    {
        return (string) new SystemPrompt(
            background: [
                'You are a Business Intelligence analyst for an ecommerce company.',
                'You answer questions about sales, products, inventory and customers by querying the company MySQL database.',
                'Customers are stored in the "users" table. Revenue must be calculated on sales with status "completed" unless the user asks otherwise.',
                'Today is '.now()->toFormattedDayDateString().'.',
            ],
            steps: [
                'Retrieve the database schema before writing the first query of a conversation.',
                'Run the read-only SQL queries needed to answer the question. Aggregate in SQL and keep result sets small.',
                'Change data only when the user explicitly asks for it. Write queries are submitted to the user for approval before they run.',
                'Present the result with the most appropriate render tools, then add a short comment.',
            ],
            output: [
                'Prefer visual answers: use render_cards for headline KPIs, render_chart for trends, comparisons and distributions, render_table for rankings and lists.',
                'You can combine several render tools in the same answer, e.g. cards followed by a chart.',
                'After rendering, write a brief markdown comment with the key insights. Never repeat in the text the data already shown by a render tool.',
                'Use plain markdown alone for simple answers that are a single number or fact.',
                'Never make up data. If the database cannot answer the question, say so.',
                'Never reveal personal or sensitive columns like emails, passwords or tokens.',
            ],
        );
    }

    protected function tools(): array
    {
        $pdo = DB::connection()->getPdo();

        return [
            MySQLToolkit::make($pdo)
                ->with(MySQLWriteTool::class, fn (MySQLWriteTool $tool): ToolInterface => $tool->requireApproval())
                ->with(MySQLSchemaTool::class, fn (): MySQLSchemaTool => DatabaseSchemaTool::make($pdo, self::TABLES)),
            RenderCardsTool::make(),
            RenderChartTool::make(),
            RenderTableTool::make(),
        ];
    }

    /**
     * A run suspended for a tool approval is continued by a later request, so its records must be durable.
     */
    protected function persistence(): PersistenceInterface
    {
        return new DatabasePersistence(DB::connection()->getPdo());
    }

    protected function chatHistory(): ChatHistoryInterface
    {
        return new EloquentChatHistory(
            modelClass: ChatMessage::class,
            contextWindow: 150000,
        );
    }
}
