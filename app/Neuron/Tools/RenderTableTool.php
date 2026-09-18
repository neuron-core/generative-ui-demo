<?php

declare(strict_types=1);

namespace App\Neuron\Tools;

use NeuronAI\Tools\ArrayProperty;
use NeuronAI\Tools\PropertyType;
use NeuronAI\Tools\Tool;
use NeuronAI\Tools\ToolProperty;

class RenderTableTool extends Tool
{
    protected string $name = 'render_table';

    protected ?string $description = 'Display a data table to the user in the chat. '
        .'Use it for rankings and lists of records like top products, best customers or low stock items.';

    protected function properties(): array
    {
        return [
            new ToolProperty('title', PropertyType::STRING, 'Short title of the table', true),
            new ArrayProperty(
                name: 'columns',
                description: 'The column headers',
                required: true,
                items: new ToolProperty('column', PropertyType::STRING, 'A column header'),
            ),
            new ArrayProperty(
                name: 'rows',
                description: 'The table rows (max 25). Each row is a list of formatted cell values, in the same order as the columns.',
                required: true,
                items: new ArrayProperty(
                    name: 'row',
                    description: 'The cells of a row',
                    items: new ToolProperty('cell', PropertyType::STRING, 'A formatted cell value'),
                ),
                maxItems: 25,
            ),
        ];
    }

    /**
     * The table is drawn by the frontend from the tool call arguments.
     *
     * @param  list<string>  $columns
     * @param  list<list<string>>  $rows
     */
    public function __invoke(string $title, array $columns, array $rows): string
    {
        return "The table \"{$title}\" has been displayed to the user.";
    }
}
