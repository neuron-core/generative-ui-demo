<?php

declare(strict_types=1);

namespace App\Neuron\Tools;

use NeuronAI\Tools\ArrayProperty;
use NeuronAI\Tools\ObjectProperty;
use NeuronAI\Tools\PropertyType;
use NeuronAI\Tools\Tool;
use NeuronAI\Tools\ToolProperty;

class RenderCardsTool extends Tool
{
    protected string $name = 'render_cards';

    protected ?string $description = 'Display a row of KPI cards to the user in the chat. '
        .'Use it to highlight a few headline numbers like total revenue, number of orders or average order value.';

    protected function properties(): array
    {
        return [
            new ArrayProperty(
                name: 'cards',
                description: 'The KPI cards to display',
                required: true,
                items: new ObjectProperty(
                    name: 'card',
                    description: 'A single KPI',
                    properties: [
                        new ToolProperty('label', PropertyType::STRING, 'Name of the metric, e.g. "Total revenue"', true),
                        new ToolProperty('value', PropertyType::STRING, 'Formatted value, e.g. "$12,450" or "1,204"', true),
                        new ToolProperty('hint', PropertyType::STRING, 'Optional short context, e.g. "Last 30 days"', false),
                        new ToolProperty('trend', PropertyType::STRING, 'Optional change versus the previous period, e.g. "+12.4%" or "-3%"', false),
                    ],
                ),
                minItems: 1,
                maxItems: 6,
            ),
        ];
    }

    /**
     * The cards are drawn by the frontend from the tool call arguments.
     *
     * @param  list<array{label: string, value: string, hint?: string, trend?: string}>  $cards
     */
    public function __invoke(array $cards): string
    {
        return count($cards).' KPI cards have been displayed to the user.';
    }
}
