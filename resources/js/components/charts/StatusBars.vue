<script setup lang="ts">
import { Chart, registerables, type Plugin, type TooltipItem } from 'chart.js';
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { chartTheme } from '@/lib/chartTheme';

Chart.register(...registerables);

interface StatusPoint {
    status: string;
    label: string;
    count: number;
}

const props = defineProps<{
    data: StatusPoint[];
}>();

const emit = defineEmits<{
    select: [status: string];
}>();

const canvas = ref<HTMLCanvasElement | null>(null);
let chart: Chart | null = null;
let observer: MutationObserver | null = null;

/**
 * Writes each count at the end of its bar. Reading a horizontal bar against the
 * axis is guesswork at these sizes, and the exact number is the whole point of the
 * panel: how many orders are sitting in this status right now.
 */
function countLabels(color: string): Plugin<'bar'> {
    return {
        id: 'countLabels',
        afterDatasetsDraw(instance): void {
            const { ctx } = instance;

            ctx.save();
            ctx.fillStyle = color;
            ctx.font = `600 11px ${Chart.defaults.font.family}`;
            ctx.textBaseline = 'middle';

            instance.getDatasetMeta(0).data.forEach((bar, index) => {
                ctx.fillText(
                    String(props.data[index]?.count ?? 0),
                    bar.x + 6,
                    bar.y,
                );
            });

            ctx.restore();
        },
    };
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
            labels: props.data.map((point) => point.label),
            datasets: [
                {
                    label: 'Pedidos abiertos',
                    data: props.data.map((point) => point.count),
                    backgroundColor: theme.series,
                    hoverBackgroundColor: theme.seriesHover,
                    borderSkipped: false,
                    borderRadius: 4,
                    maxBarThickness: 22,
                },
            ],
        },
        plugins: [countLabels(theme.label)],
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            // The whole row answers to the pointer, not just the bar: a status with
            // one order draws a sliver nobody can hit.
            interaction: { mode: 'index', intersect: false },
            // A bar is a filtered slice of the order list, so clicking it should open
            // that slice. The cursor is the only hint the canvas can give.
            onClick: (_event, elements) => {
                const point = props.data[elements[0]?.index ?? -1];

                if (point) {
                    emit('select', point.status);
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
                        label: (item: TooltipItem<'bar'>): string =>
                            `${props.data[item.dataIndex]?.count ?? 0} pedidos`,
                    },
                },
            },
            scales: {
                x: {
                    beginAtZero: true,
                    // Room at the end of the longest bar for its count to sit in.
                    grace: '12%',
                    ticks: { color: theme.axis, precision: 0 },
                    grid: { color: theme.grid },
                },
                y: {
                    grid: { display: false },
                    ticks: { color: theme.axis },
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

watch(() => props.data, render, { deep: true });

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
