<script setup lang="ts">
import { computed } from 'vue';
import { money } from '@/lib/format';

interface DayPoint {
    day: string;
    orders: number;
    revenue: number;
}

const props = defineProps<{
    data: DayPoint[];
}>();

const width = 600;
const height = 200;
const padX = 10;
const padTop = 14;
const padBottom = 26;

const maxOrders = computed<number>(() => Math.max(1, ...props.data.map((point) => point.orders)));

const baselineY = height - padBottom;

const bars = computed(() => {
    const count = Math.max(1, props.data.length);
    const slot = (width - padX * 2) / count;
    const usableHeight = height - padTop - padBottom;

    return props.data.map((point, index) => {
        const barHeight = (point.orders / maxOrders.value) * usableHeight;

        return {
            ...point,
            x: padX + index * slot + slot * 0.18,
            w: slot * 0.64,
            y: baselineY - barHeight,
            h: barHeight,
            label: point.day.slice(8, 10),
        };
    });
});
</script>

<template>
    <svg
        :viewBox="`0 0 ${width} ${height}`"
        class="h-auto w-full"
        role="img"
        aria-label="Pedidos por día en los últimos 14 días"
    >
        <line
            :x1="padX"
            :x2="width - padX"
            :y1="baselineY"
            :y2="baselineY"
            class="stroke-current text-muted-foreground"
            stroke-opacity="0.35"
            stroke-width="1"
        />

        <g v-for="bar in bars" :key="bar.day">
            <rect
                :x="bar.x"
                :y="bar.y"
                :width="bar.w"
                :height="bar.h"
                rx="3"
                class="fill-[#2a78d6] dark:fill-[#3987e5]"
            >
                <title>{{ bar.day }} · {{ bar.orders }} pedidos · {{ money(bar.revenue) }}</title>
            </rect>
            <text
                :x="bar.x + bar.w / 2"
                :y="height - 8"
                text-anchor="middle"
                class="fill-current text-[9px] text-muted-foreground"
            >
                {{ bar.label }}
            </text>
        </g>
    </svg>
</template>
