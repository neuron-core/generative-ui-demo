<script setup lang="ts">
import {
    ArcElement,
    BarController,
    BarElement,
    CategoryScale,
    Chart as ChartJS,
    DoughnutController,
    Filler,
    Legend,
    LinearScale,
    LineController,
    LineElement,
    PieController,
    PointElement,
    Tooltip,
} from 'chart.js';
import type { ChartData, ChartOptions } from 'chart.js';
import { computed } from 'vue';
import { Chart } from 'vue-chartjs';

ChartJS.register(
    ArcElement,
    BarController,
    BarElement,
    CategoryScale,
    DoughnutController,
    Filler,
    Legend,
    LinearScale,
    LineController,
    LineElement,
    PieController,
    PointElement,
    Tooltip,
);

type ChartType = 'bar' | 'line' | 'area' | 'pie' | 'doughnut';

type Props = {
    title: string;
    type: ChartType;
    labels: string[];
    datasets: { label: string; data: number[] }[];
    unit?: string | null;
};

const props = defineProps<Props>();

const palette = [
    '#6366f1',
    '#14b8a6',
    '#f59e0b',
    '#ef4444',
    '#0ea5e9',
    '#a855f7',
    '#84cc16',
    '#ec4899',
];

const isCircular = computed(
    () => props.type === 'pie' || props.type === 'doughnut',
);

// An area chart is a filled line chart.
const chartType = computed(() => (props.type === 'area' ? 'line' : props.type));

const data = computed<ChartData>(() => ({
    labels: props.labels,
    datasets: props.datasets.map((dataset, index) => {
        const color = palette[index % palette.length];

        return {
            label: dataset.label,
            data: dataset.data,
            backgroundColor: isCircular.value
                ? props.labels.map((_, i) => palette[i % palette.length])
                : props.type === 'area'
                  ? `${color}33`
                  : color,
            borderColor: isCircular.value ? 'transparent' : color,
            borderWidth: props.type === 'bar' ? 0 : 2,
            borderRadius: 4,
            fill: props.type === 'area',
            tension: 0.35,
            pointRadius: 2,
        };
    }),
}));

function format(value: number | string): string {
    const formatted = Number(value).toLocaleString();

    if (!props.unit) {
        return formatted;
    }

    return props.unit === '%'
        ? `${formatted}${props.unit}`
        : `${props.unit}${formatted}`;
}

const options = computed<ChartOptions>(() => ({
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: {
            display: isCircular.value || props.datasets.length > 1,
            position: isCircular.value ? 'right' : 'top',
            labels: { color: '#888', boxWidth: 12 },
        },
        tooltip: {
            callbacks: {
                label: (context) => {
                    const name = isCircular.value
                        ? context.label
                        : context.dataset.label;

                    return `${name}: ${format(context.raw as number)}`;
                },
            },
        },
    },
    scales: isCircular.value
        ? {}
        : {
              x: { grid: { display: false }, ticks: { color: '#888' } },
              y: {
                  beginAtZero: true,
                  grid: { color: '#8882' },
                  ticks: { color: '#888', callback: (value) => format(value) },
              },
          },
}));
</script>

<template>
    <div class="bg-card my-3 rounded-xl border p-4 shadow-xs">
        <h3 class="mb-3 text-sm font-medium">{{ title }}</h3>
        <div class="h-72">
            <Chart :type="chartType" :data="data" :options="options" />
        </div>
    </div>
</template>
