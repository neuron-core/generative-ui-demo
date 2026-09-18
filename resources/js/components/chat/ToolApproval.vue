<script setup lang="ts">
import type { Interrupt } from '@copilotkit/vue/v2';
import { ShieldAlert } from '@lucide/vue';
import { ref } from 'vue';
import { Button } from '@/components/ui/button';

type Props = {
    // One "confirmation" interrupt per tool call waiting for a decision.
    interrupts: Interrupt[];
    resolve: (payload?: unknown, interruptId?: string) => unknown;
};

const props = defineProps<Props>();

// The run continues only once every interrupt has a decision.
const decided = ref<Record<string, boolean>>({});

function decide(interrupt: Interrupt, approved: boolean): void {
    decided.value[interrupt.id] = approved;

    props.resolve(
        approved
            ? { approved: true }
            : { approved: false, reason: 'The user denied this query' },
        interrupt.id,
    );
}
</script>

<template>
    <div class="my-3 flex flex-col gap-3">
        <div
            v-for="interrupt in interrupts"
            :key="interrupt.id"
            class="rounded-lg border border-amber-500/40 bg-amber-500/5 p-4 text-sm"
            data-testid="tool-approval"
        >
            <p class="flex items-center gap-2 font-medium">
                <ShieldAlert class="size-4 text-amber-500" />
                The agent wants to modify the database
            </p>
            <p
                v-if="interrupt.message"
                class="text-muted-foreground mt-1 text-xs"
            >
                {{ interrupt.message }}
            </p>
            <pre
                class="bg-muted mt-3 overflow-x-auto rounded-md p-3 text-xs whitespace-pre-wrap"
                >{{
                    JSON.stringify(interrupt.metadata?.inputs ?? {}, null, 2)
                }}</pre
            >
            <p
                v-if="interrupt.id in decided"
                class="text-muted-foreground mt-3 text-xs"
            >
                {{ decided[interrupt.id] ? 'Approved' : 'Denied' }}
            </p>
            <div v-else class="mt-3 flex gap-2">
                <Button size="sm" @click="decide(interrupt, true)">
                    Approve
                </Button>
                <Button
                    size="sm"
                    variant="outline"
                    @click="decide(interrupt, false)"
                >
                    Deny
                </Button>
            </div>
        </div>
    </div>
</template>
