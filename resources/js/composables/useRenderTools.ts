import { useFrontendTool } from '@copilotkit/vue/v2';

type JsonSchema = Record<string, unknown>;

type ToolParameters = NonNullable<
    Parameters<typeof useFrontendTool>[0]['parameters']
>;

type RenderTool = {
    name: string;
    description: string;
    parameters: JsonSchema;
    // The text the model reads once the component is on screen.
    result: (args: Record<string, any>) => string;
};

/**
 * CopilotKit expects a Standard Schema: this one hands over a plain JSON schema (Standard JSON Schema V1)
 * and accepts any arguments. The cast is needed because the hook types only the base Standard Schema.
 */
function jsonSchema(schema: JsonSchema): ToolParameters {
    const standard = {
        version: 1,
        vendor: 'json-schema',
        validate: (value: unknown) => ({ value }),
        jsonSchema: {
            input: () => schema,
            output: () => schema,
        },
    };

    return { '~standard': standard } as ToolParameters;
}

/**
 * The components are drawn by the "tool-call-*" slots of the chat from the call arguments,
 * so executing a render tool only tells the model that the user can see the result.
 */
export const renderTools: RenderTool[] = [
    {
        name: 'render_cards',
        description:
            'Display a row of KPI cards to the user in the chat. ' +
            'Use it to highlight a few headline numbers like total revenue, number of orders or average order value.',
        parameters: {
            type: 'object',
            properties: {
                cards: {
                    type: 'array',
                    description: 'The KPI cards to display',
                    minItems: 1,
                    maxItems: 6,
                    items: {
                        type: 'object',
                        description: 'A single KPI',
                        properties: {
                            label: {
                                type: 'string',
                                description:
                                    'Name of the metric, e.g. "Total revenue"',
                            },
                            value: {
                                type: 'string',
                                description:
                                    'Formatted value, e.g. "$12,450" or "1,204"',
                            },
                            hint: {
                                type: 'string',
                                description:
                                    'Optional short context, e.g. "Last 30 days"',
                            },
                            trend: {
                                type: 'string',
                                description:
                                    'Optional change versus the previous period, e.g. "+12.4%" or "-3%"',
                            },
                        },
                        required: ['label', 'value'],
                    },
                },
            },
            required: ['cards'],
        },
        result: ({ cards }) =>
            `${cards.length} KPI cards have been displayed to the user.`,
    },
    {
        name: 'render_chart',
        description:
            'Display a chart to the user in the chat. Use it for trends over time (line, area), ' +
            'comparisons between groups (bar) and parts of a whole (pie, doughnut).',
        parameters: {
            type: 'object',
            properties: {
                title: {
                    type: 'string',
                    description: 'Short title of the chart',
                },
                type: {
                    type: 'string',
                    description: 'The chart type',
                    enum: ['bar', 'line', 'area', 'pie', 'doughnut'],
                },
                labels: {
                    type: 'array',
                    description:
                        'Labels of the X axis (or of the slices for pie and doughnut)',
                    items: { type: 'string', description: 'A single label' },
                },
                datasets: {
                    type: 'array',
                    description:
                        'One or more data series. Each series must have one value per label.',
                    minItems: 1,
                    items: {
                        type: 'object',
                        description: 'A data series',
                        properties: {
                            label: {
                                type: 'string',
                                description:
                                    'Name of the series, e.g. "Revenue"',
                            },
                            data: {
                                type: 'array',
                                description:
                                    'Numeric values, in the same order as the labels',
                                items: {
                                    type: 'number',
                                    description: 'A single value',
                                },
                            },
                        },
                        required: ['label', 'data'],
                    },
                },
                unit: {
                    type: 'string',
                    description:
                        'Optional unit prefix or suffix of the values, e.g. "$" or "%"',
                },
            },
            required: ['title', 'type', 'labels', 'datasets'],
        },
        result: ({ title }) =>
            `The chart "${title}" has been displayed to the user.`,
    },
    {
        name: 'render_table',
        description:
            'Display a data table to the user in the chat. ' +
            'Use it for rankings and lists of records like top products, best customers or low stock items.',
        parameters: {
            type: 'object',
            properties: {
                title: {
                    type: 'string',
                    description: 'Short title of the table',
                },
                columns: {
                    type: 'array',
                    description: 'The column headers',
                    items: { type: 'string', description: 'A column header' },
                },
                rows: {
                    type: 'array',
                    description:
                        'The table rows (max 25). Each row is a list of formatted cell values, in the same order as the columns.',
                    maxItems: 25,
                    items: {
                        type: 'array',
                        description: 'The cells of a row',
                        items: {
                            type: 'string',
                            description: 'A formatted cell value',
                        },
                    },
                },
            },
            required: ['title', 'columns', 'rows'],
        },
        result: ({ title }) =>
            `The table "${title}" has been displayed to the user.`,
    },
];

/**
 * The result of a render tool call, also used to answer the calls a page reload left pending.
 */
export function renderToolResult(
    name: string,
    args: Record<string, any>,
): string {
    const tool = renderTools.find((tool) => tool.name === name);

    return tool ? tool.result(args) : `Unknown tool "${name}".`;
}

/**
 * Register the render tools: CopilotKit sends them to the agent with every request and runs them when called.
 */
export function useRenderTools(): void {
    for (const tool of renderTools) {
        useFrontendTool({
            name: tool.name,
            description: tool.description,
            parameters: jsonSchema(tool.parameters),
            handler: async (args) => tool.result(args),
        });
    }
}
