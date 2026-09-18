<script setup lang="ts">
import {
    CopilotChat,
    buildResumeArray,
    useAgent,
    useConfigureSuggestions,
    useCopilotKit,
    useInterrupt,
} from '@copilotkit/vue/v2';
import type { CopilotChatLabels, Interrupt } from '@copilotkit/vue/v2';
import { computed, onMounted, shallowRef } from 'vue';
import ChartCard from '@/components/chat/ChartCard.vue';
import DataTable from '@/components/chat/DataTable.vue';
import KpiCards from '@/components/chat/KpiCards.vue';
import ToolApproval from '@/components/chat/ToolApproval.vue';
import ToolStatus from '@/components/chat/ToolStatus.vue';

type Props = {
    threadId: string;
    pendingApprovals: Interrupt[];
};

const props = defineProps<Props>();

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

// A tool requiring approval suspends the run: the pending decisions are rendered by the "interrupt" slot.
// The slot is typed with the first interrupt only, while a run can wait for several decisions at once.
const { slotProps: liveApproval } = useInterrupt();

const { copilotkit } = useCopilotKit();
const { agent } = useAgent();

// After a page reload no run has delivered the interrupts: they come from the chat history instead.
const restoredApproval = shallowRef<{
    interrupts: Interrupt[];
    resolve: (payload?: unknown, interruptId?: string) => Promise<void>;
} | null>(null);

const approval = computed(() => liveApproval.value ?? restoredApproval.value);

onMounted(() => {
    if (props.pendingApprovals.length === 0) {
        return;
    }

    const interrupts: Interrupt[] = JSON.parse(
        JSON.stringify(props.pendingApprovals),
    );
    const responses: Parameters<typeof buildResumeArray>[1] = {};

    const resolve = async (
        payload?: unknown,
        interruptId?: string,
    ): Promise<void> => {
        responses[interruptId ?? interrupts[0].id] = {
            status: 'resolved',
            payload,
        };

        if (!agent.value || interrupts.some(({ id }) => !(id in responses))) {
            return;
        }

        restoredApproval.value = null;
        copilotkit.value.setInterruptState(null);

        await copilotkit.value.runAgent({
            agent: agent.value,
            resume: buildResumeArray(interrupts, responses),
        });
    };

    restoredApproval.value = { interrupts, resolve };

    // CopilotChat renders its "interrupt" slot only while an interrupt state is set.
    copilotkit.value.setInterruptState({
        event: { name: 'on_interrupt', value: interrupts[0] },
        interrupt: interrupts[0],
        interrupts,
        result: null,
        resolve,
        cancel: async (): Promise<void> => {},
    });
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
        <template #tool-call-mysql_write_query="{ args, status }">
            <ToolStatus
                label="Modifying the database"
                :done="status === 'complete'"
                :detail="args?.query"
            />
        </template>

        <template #interrupt>
            <ToolApproval
                v-if="approval"
                :interrupts="approval.interrupts"
                :resolve="approval.resolve"
            />
        </template>
    </CopilotChat>
</template>
