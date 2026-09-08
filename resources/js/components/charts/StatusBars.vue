<script setup lang="ts">
import { Chart, registerables } from 'chart.js';
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';

Chart.register(...registerables);

interface StatusPoint {
    status: string;
    label: string;
    count: number;
}

const props = defineProps<{
    data: StatusPoint[];
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
    const barColor = dark ? '#3987e5' : '#2a78d6';
    const muted = '#898781';
    const grid = dark ? 'rgba(255,255,255,0.08)' : 'rgba(11,11,11,0.06)';

    chart = new Chart(canvas.value, {
        type: 'bar',
        data: {
            labels: props.data.map((point) => point.label),
            datasets: [
                {
                    label: 'Pedidos',
                    data: props.data.map((point) => point.count),
                    backgroundColor: barColor,
                    borderRadius: 4,
                    maxBarThickness: 22,
                },
            ],
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
            },
            scales: {
                x: {
                    beginAtZero: true,
                    ticks: { color: muted, precision: 0, stepSize: 1 },
                    grid: { color: grid },
                },
                y: {
                    grid: { display: false },
                    ticks: { color: muted },
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
