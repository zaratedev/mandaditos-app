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

Chart.register(...registerables);

interface Point {
    date: string;
    orders: number;
    revenue: number;
}

const props = defineProps<{
    data: Point[];
    unit: string;
}>();

const emit = defineEmits<{
    select: [date: string];
}>();

const canvas = ref<HTMLCanvasElement | null>(null);
let chart: Chart | null = null;
let observer: MutationObserver | null = null;

function isDark(): boolean {
    return document.documentElement.classList.contains('dark');
}

function axisLabel(date: string): string {
    return props.unit === 'month' ? shortMonth(date) : shortDayMonth(date);
}

function fullLabel(date: string): string {
    return props.unit === 'month' ? longMonth(date) : shortDate(date);
}

function render(): void {
    if (!canvas.value) {
        return;
    }

    chart?.destroy();

    const dark = isDark();
    // The bars carry the data, so they have to stay legible against the card. The
    // brand yellow is bright enough to almost disappear on white (1.4:1), so in light
    // mode it gets a darker amber outline that reads at 3.5:1; on the dark card the
    // fill alone is already at 14:1 and needs no help.
    const barColor = '#fed61c';
    const barBorder = dark ? 'transparent' : '#a38600';
    const muted = '#898781';
    const grid = dark ? 'rgba(255,255,255,0.08)' : 'rgba(11,11,11,0.06)';

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
                    backgroundColor: barColor,
                    borderColor: barBorder,
                    borderSkipped: false,
                    borderRadius: 4,
                    maxBarThickness: 28,
                },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            // Same idea as the outline above: a defined edge in light mode, none on
            // the dark card where the fill already separates itself.
            elements: { bar: { borderWidth: dark ? 0 : 1 } },
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
                                `Ventas: ${money(point?.revenue ?? 0)}`,
                            ];
                        },
                    },
                },
            },
            scales: {
                x: {
                    grid: { display: false },
                    ticks: {
                        color: muted,
                        font: { size: 10 },
                        // A year of months still fits; a year of days does not, so the
                        // axis drops labels instead of stacking them on top of each other.
                        maxRotation: 0,
                        autoSkipPadding: 12,
                    },
                },
                y: {
                    beginAtZero: true,
                    ticks: { color: muted, precision: 0 },
                    grid: { color: grid },
                    title: {
                        display: true,
                        text: 'Pedidos',
                        color: muted,
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

watch(() => [props.data, props.unit], render, { deep: true });

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
