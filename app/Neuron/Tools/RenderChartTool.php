<?php

declare(strict_types=1);

namespace App\Neuron\Tools;

use NeuronAI\Tools\ArrayProperty;
use NeuronAI\Tools\ObjectProperty;
use NeuronAI\Tools\PropertyType;
use NeuronAI\Tools\Tool;
use NeuronAI\Tools\ToolProperty;

class RenderChartTool extends Tool
{
    protected string $name = 'render_chart';

    protected ?string $description = 'Display a chart to the user in the chat. Use it for trends over time (line, area), '
        .'comparisons between groups (bar) and parts of a whole (pie, doughnut).';

    protected function properties(): array
    {
        return [
            new ToolProperty('title', PropertyType::STRING, 'Short title of the chart', true),
            new ToolProperty('type', PropertyType::STRING, 'The chart type', true, ['bar', 'line', 'area', 'pie', 'doughnut']),
            new ArrayProperty(
                name: 'labels',
                description: 'Labels of the X axis (or of the slices for pie and doughnut)',
                required: true,
                items: new ToolProperty('label', PropertyType::STRING, 'A single label'),
            ),
            new ArrayProperty(
                name: 'datasets',
                description: 'One or more data series. Each series must have one value per label.',
                required: true,
                items: new ObjectProperty(
                    name: 'dataset',
                    description: 'A data series',
                    properties: [
                        new ToolProperty('label', PropertyType::STRING, 'Name of the series, e.g. "Revenue"', true),
                        new ArrayProperty(
                            name: 'data',
                            description: 'Numeric values, in the same order as the labels',
                            required: true,
                            items: new ToolProperty('value', PropertyType::NUMBER, 'A single value'),
                        ),
                    ],
                ),
                minItems: 1,
            ),
            new ToolProperty('unit', PropertyType::STRING, 'Optional unit prefix or suffix of the values, e.g. "$" or "%"', false),
        ];
    }

    /**
     * The chart is drawn by the frontend from the tool call arguments.
     *
     * @param  list<string>  $labels
     * @param  list<array{label: string, data: list<int|float>}>  $datasets
     */
    public function __invoke(string $title, string $type, array $labels, array $datasets, ?string $unit = null): string
    {
        return "The chart \"{$title}\" has been displayed to the user.";
    }
}
