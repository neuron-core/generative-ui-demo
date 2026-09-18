<script setup lang="ts">
import { Check, LoaderCircle } from '@lucide/vue';

type Props = {
    label: string;
    done: boolean;
    detail?: string;
};

defineProps<Props>();
</script>

<template>
    <details class="text-muted-foreground my-2 text-xs">
        <summary class="flex cursor-pointer list-none items-center gap-2">
            <Check v-if="done" class="size-3.5 text-emerald-500" />
            <LoaderCircle v-else class="size-3.5 animate-spin" />
            {{ label }}
        </summary>
        <pre
            v-if="detail"
            class="bg-muted mt-2 overflow-x-auto rounded-md p-3 whitespace-pre-wrap"
            >{{ detail }}</pre>
    </details>
</template>
