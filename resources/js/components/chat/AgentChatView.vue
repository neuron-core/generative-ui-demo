<script setup lang="ts">
import { CopilotChat, useConfigureSuggestions } from '@copilotkit/vue/v2';
import type { CopilotChatLabels } from '@copilotkit/vue/v2';
import ChartCard from '@/components/chat/ChartCard.vue';
import DataTable from '@/components/chat/DataTable.vue';
import KpiCards from '@/components/chat/KpiCards.vue';
import ToolStatus from '@/components/chat/ToolStatus.vue';

type Props = {
    threadId: string;
};

defineProps<Props>();

useConfigureSuggestions({
    suggestions: [
        {
            title: 'Business overview',
            message:
                'Give me an overview of the business in the last 30 days with the main KPIs.',
        },
        {
            title: 'Revenue trend',
            message: 'Show me the monthly revenue trend of the last 12 months.',
        },
        {
            title: 'Top products',
            message: 'What are the 10 best selling products by revenue?',
        },
        {
            title: 'Sales by category',
            message: 'How is revenue distributed across product categories?',
        },
        {
            title: 'Low stock',
            message:
                'Which products are out of stock or running low, and how well do they sell?',
        },
    ],
});

// The library types its labels as the literal default strings, hence the cast.
const labels = {
    welcomeMessageText:
        'Ask me anything about your sales, products and customers',
    chatInputPlaceholder: 'e.g. Compare web and mobile sales this year',
} as unknown as Partial<CopilotChatLabels>;
</script>

<template>
    <CopilotChat :thread-id="threadId" :labels="labels" class="h-full">
        <!-- Arguments are streamed in pieces: render a component only once they are complete. -->
        <template #tool-call-render_chart="{ args, status }">
            <ToolStatus
                v-if="status === 'inProgress'"
                label="Preparing the chart"
                :done="false"
            />
            <ChartCard v-else-if="args?.datasets" v-bind="args" />
        </template>

        <template #tool-call-render_cards="{ args, status }">
            <ToolStatus
                v-if="status === 'inProgress'"
                label="Preparing the KPIs"
                :done="false"
            />
            <KpiCards v-else-if="args?.cards" :cards="args.cards" />
        </template>

        <template #tool-call-render_table="{ args, status }">
            <ToolStatus
                v-if="status === 'inProgress'"
                label="Preparing the table"
                :done="false"
            />
            <DataTable v-else-if="args?.rows" v-bind="args" />
        </template>

        <template #tool-call-analyze_mysql_database_schema="{ status }">
            <ToolStatus
                label="Reading the database schema"
                :done="status === 'complete'"
            />
        </template>

        <template #tool-call-mysql_select_query="{ args, status }">
            <ToolStatus
                label="Querying the database"
                :done="status === 'complete'"
                :detail="args?.query"
            />
        </template>
    </CopilotChat>
</template>
