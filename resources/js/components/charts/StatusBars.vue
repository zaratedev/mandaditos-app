<script setup lang="ts">
import { computed } from 'vue';

interface StatusPoint {
    status: string;
    label: string;
    count: number;
}

const props = defineProps<{
    data: StatusPoint[];
}>();

const max = computed<number>(() => Math.max(1, ...props.data.map((point) => point.count)));
</script>

<template>
    <ul class="space-y-2.5">
        <li v-for="row in data" :key="row.status" class="flex items-center gap-3 text-sm">
            <span class="w-24 shrink-0 text-muted-foreground">{{ row.label }}</span>
            <span class="relative h-4 flex-1 overflow-hidden rounded bg-muted/40">
                <span
                    class="absolute inset-y-0 left-0 rounded bg-[#2a78d6] dark:bg-[#3987e5]"
                    :style="{ width: `${(row.count / max) * 100}%` }"
                ></span>
            </span>
            <span class="w-6 shrink-0 text-right font-medium tabular-nums">{{ row.count }}</span>
        </li>
    </ul>
</template>
