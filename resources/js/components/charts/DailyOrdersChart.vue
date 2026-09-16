<script setup lang="ts">
import { Chart, registerables, type TooltipItem } from 'chart.js';
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';
import {
    longMonth,
    money,
    shortDate,
    shortDayMonth,
    shortMonth,
} from '@/lib/format';
import { chartTheme } from '@/lib/chartTheme';

Chart.register(...registerables);

interface Point {
    date: string;
    orders: number;
    revenue: number;
}

const props = defineProps<{
    data: Point[];
    unit: string;
    /** Today as the server counts it, so the current bar is never off by a timezone. */
    today?: string;
    /** What the money line means here: the dashboard plots sales, reports plot fees. */
    revenueLabel?: string;
}>();

const emit = defineEmits<{
    select: [date: string];
}>();

const canvas = ref<HTMLCanvasElement | null>(null);
let chart: Chart | null = null;
let observer: MutationObserver | null = null;

function axisLabel(date: string): string {
    return props.unit === 'month' ? shortMonth(date) : shortDayMonth(date);
}

function fullLabel(date: string): string {
    return props.unit === 'month' ? longMonth(date) : shortDate(date);
}

/**
 * Whether a bar covers today. In a row of otherwise identical bars, the eye looks
 * for now first, so now is the one bar that does not look like the others.
 */
function isCurrent(date: string): boolean {
    if (!props.today) {
        return false;
    }

    return props.unit === 'month'
        ? date.slice(0, 7) === props.today.slice(0, 7)
        : date === props.today;
}

function render(): void {
    if (!canvas.value) {
        return;
    }

    chart?.destroy();

    const theme = chartTheme();

    chart = new Chart(canvas.value, {
        type: 'bar',
        data: {
            labels: props.data.map((point) => axisLabel(point.date)),
            datasets: [
                {
                    label:
                        props.unit === 'month'
                            ? 'Pedidos por mes'
                            : 'Pedidos por día',
                    data: props.data.map((point) => point.orders),
                    backgroundColor: props.data.map((point) =>
                        isCurrent(point.date)
                            ? theme.seriesCurrent
                            : theme.series,
                    ),
                    hoverBackgroundColor: theme.seriesHover,
                    borderSkipped: false,
                    borderRadius: 4,
                    maxBarThickness: 28,
                },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            // Hovering anywhere in a column reads that column, which on a narrow bar
            // is the difference between a tooltip and a game of hit-the-pixel.
            interaction: { mode: 'index', intersect: false },
            // A bar is a slice of the order list, so clicking it opens that slice.
            onClick: (_event, elements) => {
                const point = props.data[elements[0]?.index ?? -1];

                if (point) {
                    emit('select', point.date);
                }
            },
            onHover: (event, elements) => {
                const target = event.native?.target as HTMLElement | null;

                if (target) {
                    target.style.cursor =
                        elements.length > 0 ? 'pointer' : 'default';
                }
            },
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        title: (items: TooltipItem<'bar'>[]): string =>
                            fullLabel(
                                props.data[items[0]?.dataIndex ?? 0]?.date ??
                                    '',
                            ),
                        label: (item: TooltipItem<'bar'>): string[] => {
                            const point = props.data[item.dataIndex];

                            return [
                                `${point?.orders ?? 0} pedidos`,
                                `${props.revenueLabel ?? 'Ventas'}: ${money(point?.revenue ?? 0)}`,
                            ];
                        },
                    },
                },
            },
            scales: {
                x: {
                    grid: { display: false },
                    ticks: {
                        color: theme.axis,
                        font: { size: 10 },
                        // A year of months still fits; a year of days does not, so the
                        // axis drops labels instead of stacking them on top of each other.
                        maxRotation: 0,
                        autoSkipPadding: 12,
                    },
                },
                y: {
                    beginAtZero: true,
                    ticks: { color: theme.axis, precision: 0 },
                    grid: { color: theme.grid },
                    title: {
                        display: true,
                        text: 'Pedidos',
                        color: theme.axis,
                        font: { size: 10 },
                    },
                },
            },
        },
    });
}

onMounted(() => {
    render();

    observer = new MutationObserver(render);
    observer.observe(document.documentElement, {
        attributes: true,
        attributeFilter: ['class'],
    });
});

watch(() => [props.data, props.unit, props.today], render, { deep: true });

onBeforeUnmount(() => {
    observer?.disconnect();
    chart?.destroy();
});
</script>

<template>
    <div class="h-64">
        <canvas ref="canvas"></canvas>
    </div>
</template>
