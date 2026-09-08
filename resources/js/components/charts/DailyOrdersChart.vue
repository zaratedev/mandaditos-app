<script setup lang="ts">
import { Chart, registerables, type TooltipItem } from 'chart.js';
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { money } from '@/lib/format';

Chart.register(...registerables);

interface DayPoint {
    day: string;
    orders: number;
    revenue: number;
}

const props = defineProps<{
    data: DayPoint[];
}>();

const canvas = ref<HTMLCanvasElement | null>(null);
let chart: Chart | null = null;
let observer: MutationObserver | null = null;

function isDark(): boolean {
    return document.documentElement.classList.contains('dark');
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
    const barBorderWidth = dark ? 0 : 1;
    const muted = '#898781';
    const grid = dark ? 'rgba(255,255,255,0.08)' : 'rgba(11,11,11,0.06)';

    chart = new Chart(canvas.value, {
        type: 'bar',
        data: {
            labels: props.data.map((point) => point.day.slice(5)),
            datasets: [
                {
                    label: 'Pedidos',
                    data: props.data.map((point) => point.orders),
                    backgroundColor: barColor,
                    borderColor: barBorder,
                    borderWidth: barBorderWidth,
                    borderSkipped: false,
                    borderRadius: 4,
                    maxBarThickness: 28,
                },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        title: (items: TooltipItem<'bar'>[]): string =>
                            props.data[items[0]?.dataIndex ?? 0]?.day ?? '',
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
                    ticks: { color: muted, font: { size: 10 } },
                },
                y: {
                    beginAtZero: true,
                    ticks: { color: muted, precision: 0, stepSize: 1 },
                    grid: { color: grid },
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
