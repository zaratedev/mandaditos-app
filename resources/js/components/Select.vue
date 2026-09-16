<script setup lang="ts">
import { ChevronDown } from '@lucide/vue';

interface Option {
    value: string | number;
    label: string;
}

withDefaults(
    defineProps<{
        modelValue: string | number | null;
        options?: Option[];
        placeholder?: string;
        disabled?: boolean;
        id?: string;
    }>(),
    {
        options: () => [],
        placeholder: '',
        disabled: false,
    },
);

const emit = defineEmits<{
    (e: 'update:modelValue', value: string): void;
    (e: 'change', value: string): void;
}>();

function onChange(event: Event): void {
    const value = (event.target as HTMLSelectElement).value;
    emit('update:modelValue', value);
    emit('change', value);
}
</script>

<template>
    <div class="relative">
        <select
            :id="id"
            :value="modelValue ?? ''"
            :disabled="disabled"
            class="border-input focus-visible:border-ring focus-visible:ring-ring/50 h-9 w-full appearance-none rounded-md border bg-transparent py-1 pr-9 pl-3 text-sm shadow-xs outline-none focus-visible:ring-[3px] disabled:cursor-not-allowed disabled:opacity-50"
            @change="onChange"
        >
            <option v-if="placeholder" value="" disabled>
                {{ placeholder }}
            </option>
            <option
                v-for="option in options"
                :key="option.value"
                :value="option.value"
            >
                {{ option.label }}
            </option>
            <slot />
        </select>
        <ChevronDown
            class="text-muted-foreground pointer-events-none absolute top-1/2 right-3 size-4 -translate-y-1/2"
        />
    </div>
</template>
