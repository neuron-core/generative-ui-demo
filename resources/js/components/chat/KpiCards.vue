<script setup lang="ts">
type Props = {
    cards: { label: string; value: string; hint?: string; trend?: string }[];
};

defineProps<Props>();
</script>

<template>
    <div class="my-3 grid grid-cols-2 gap-3 lg:grid-cols-3">
        <div
            v-for="card in cards"
            :key="card.label"
            class="bg-card rounded-xl border p-4 shadow-xs"
        >
            <p class="text-muted-foreground text-xs">{{ card.label }}</p>
            <p class="mt-1 text-2xl font-semibold tracking-tight">
                {{ card.value }}
            </p>
            <p class="mt-1 flex items-center gap-2 text-xs">
                <span
                    v-if="card.trend"
                    class="font-medium"
                    :class="
                        card.trend.trim().startsWith('-')
                            ? 'text-red-600 dark:text-red-400'
                            : 'text-emerald-600 dark:text-emerald-400'
                    "
                >
                    {{ card.trend }}
                </span>
                <span v-if="card.hint" class="text-muted-foreground">
                    {{ card.hint }}
                </span>
            </p>
        </div>
    </div>
</template>
