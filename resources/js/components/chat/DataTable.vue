<script setup lang="ts">
type Props = {
    title: string;
    columns: string[];
    rows: string[][];
};

defineProps<Props>();
</script>

<template>
    <div class="bg-card my-3 overflow-hidden rounded-xl border shadow-xs">
        <h3 class="border-b px-4 py-3 text-sm font-medium">{{ title }}</h3>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead
                    class="bg-muted/50 text-muted-foreground text-left text-xs"
                >
                    <tr>
                        <th
                            v-for="column in columns"
                            :key="column"
                            class="px-4 py-2 font-medium whitespace-nowrap"
                        >
                            {{ column }}
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <tr v-for="(row, index) in rows" :key="index">
                        <td
                            v-for="(cell, cellIndex) in row"
                            :key="cellIndex"
                            class="px-4 py-2 whitespace-nowrap"
                        >
                            {{ cell }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
