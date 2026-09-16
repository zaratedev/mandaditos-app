<script setup lang="ts">
import { CalendarDays, ChevronLeft, ChevronRight } from '@lucide/vue';
import { onClickOutside } from '@vueuse/core';
import { computed, ref, watch } from 'vue';

const props = withDefaults(
    defineProps<{
        modelValue: string;
        placeholder?: string;
        id?: string;
    }>(),
    {
        placeholder: 'Selecciona fecha',
    },
);

const emit = defineEmits<{
    (e: 'update:modelValue', value: string): void;
}>();

const open = ref(false);
const root = ref<HTMLElement | null>(null);

onClickOutside(root, () => {
    open.value = false;
    view.value = 'days';
});

function parseISO(value: string): Date | null {
    if (!value) {
        return null;
    }

    const [year, month, day] = value.split('-').map(Number);

    if (!year || !month || !day) {
        return null;
    }

    return new Date(year, month - 1, day);
}

function toISO(date: Date): string {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');

    return `${year}-${month}-${day}`;
}

const selected = computed<Date | null>(() => parseISO(props.modelValue));

const viewDate = ref<Date>(selected.value ?? new Date());

// The calendar has two faces: the day grid, and a month grid for jumping across
// the year without stepping one month at a time. It always opens on the days.
const view = ref<'days' | 'months'>('days');

watch(
    () => props.modelValue,
    (value) => {
        const parsed = parseISO(value);

        if (parsed) {
            viewDate.value = new Date(parsed.getFullYear(), parsed.getMonth(), 1);
        }
    },
);

const monthLabel = computed<string>(() =>
    new Intl.DateTimeFormat('es-MX', { month: 'long', year: 'numeric' }).format(viewDate.value),
);

const yearLabel = computed<string>(() => String(viewDate.value.getFullYear()));

const weekdays = ['Lu', 'Ma', 'Mi', 'Ju', 'Vi', 'Sá', 'Do'];

const months = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];

const days = computed<Date[]>(() => {
    const year = viewDate.value.getFullYear();
    const month = viewDate.value.getMonth();
    const first = new Date(year, month, 1);
    const startOffset = (first.getDay() + 6) % 7; // Monday-first grid
    const start = new Date(year, month, 1 - startOffset);

    return Array.from({ length: 42 }, (_, index) =>
        new Date(start.getFullYear(), start.getMonth(), start.getDate() + index),
    );
});

const displayValue = computed<string>(() => {
    const date = selected.value;

    return date
        ? new Intl.DateTimeFormat('es-MX', { day: '2-digit', month: '2-digit', year: 'numeric' }).format(date)
        : props.placeholder;
});

function isSameDay(a: Date, b: Date | null): boolean {
    return (
        b !== null &&
        a.getFullYear() === b.getFullYear() &&
        a.getMonth() === b.getMonth() &&
        a.getDate() === b.getDate()
    );
}

function isToday(date: Date): boolean {
    return isSameDay(date, new Date());
}

function inCurrentMonth(date: Date): boolean {
    return date.getMonth() === viewDate.value.getMonth();
}

function selectDay(date: Date): void {
    emit('update:modelValue', toISO(date));
    open.value = false;
    view.value = 'days';
}

function clear(): void {
    emit('update:modelValue', '');
    open.value = false;
    view.value = 'days';
}

function prevMonth(): void {
    viewDate.value = new Date(viewDate.value.getFullYear(), viewDate.value.getMonth() - 1, 1);
}

function nextMonth(): void {
    viewDate.value = new Date(viewDate.value.getFullYear(), viewDate.value.getMonth() + 1, 1);
}

function prevYear(): void {
    viewDate.value = new Date(viewDate.value.getFullYear() - 1, viewDate.value.getMonth(), 1);
}

function nextYear(): void {
    viewDate.value = new Date(viewDate.value.getFullYear() + 1, viewDate.value.getMonth(), 1);
}

// One pair of chevrons serves both faces: months on the day grid, years on the
// month grid.
function goPrev(): void {
    view.value === 'days' ? prevMonth() : prevYear();
}

function goNext(): void {
    view.value === 'days' ? nextMonth() : nextYear();
}

function selectMonth(month: number): void {
    viewDate.value = new Date(viewDate.value.getFullYear(), month, 1);
    view.value = 'days';
}

function isSelectedMonth(month: number): boolean {
    const date = selected.value;

    return (
        date !== null &&
        date.getFullYear() === viewDate.value.getFullYear() &&
        date.getMonth() === month
    );
}

function toggleOpen(): void {
    open.value = !open.value;

    if (open.value) {
        view.value = 'days';
    }
}
</script>

<template>
    <div ref="root" class="relative" @keydown.escape="open = false">
        <button
            :id="id"
            type="button"
            class="flex h-9 w-full items-center gap-2 rounded-md border border-input bg-transparent px-3 text-left text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
            @click="toggleOpen"
        >
            <CalendarDays class="size-4 shrink-0 text-muted-foreground" />
            <span :class="selected ? '' : 'text-muted-foreground'">{{ displayValue }}</span>
        </button>

        <div
            v-if="open"
            class="absolute z-50 mt-1 w-64 rounded-lg border border-sidebar-border/70 bg-background p-3 shadow-md dark:border-sidebar-border"
        >
            <div class="mb-2 flex items-center justify-between">
                <button
                    type="button"
                    class="inline-flex size-7 items-center justify-center rounded-md hover:bg-muted"
                    :aria-label="view === 'days' ? 'Mes anterior' : 'Año anterior'"
                    @click="goPrev"
                >
                    <ChevronLeft class="size-4" />
                </button>
                <button
                    v-if="view === 'days'"
                    type="button"
                    class="rounded-md px-2 py-1 text-sm font-medium capitalize hover:bg-muted"
                    aria-label="Elegir mes"
                    @click="view = 'months'"
                >
                    {{ monthLabel }}
                </button>
                <button
                    v-else
                    type="button"
                    class="rounded-md px-2 py-1 text-sm font-medium hover:bg-muted"
                    aria-label="Volver a los días"
                    @click="view = 'days'"
                >
                    {{ yearLabel }}
                </button>
                <button
                    type="button"
                    class="inline-flex size-7 items-center justify-center rounded-md hover:bg-muted"
                    :aria-label="view === 'days' ? 'Mes siguiente' : 'Año siguiente'"
                    @click="goNext"
                >
                    <ChevronRight class="size-4" />
                </button>
            </div>

            <template v-if="view === 'days'">
                <div class="mb-1 grid grid-cols-7 gap-1 text-center text-xs text-muted-foreground">
                    <span v-for="weekday in weekdays" :key="weekday">{{ weekday }}</span>
                </div>

                <div class="grid grid-cols-7 gap-1">
                    <button
                        v-for="day in days"
                        :key="toISO(day)"
                        type="button"
                        class="inline-flex size-8 items-center justify-center rounded-md text-sm hover:bg-muted"
                        :class="[
                            inCurrentMonth(day) ? '' : 'text-muted-foreground/40',
                            isSameDay(day, selected)
                                ? 'bg-primary text-primary-foreground hover:bg-primary hover:opacity-90'
                                : '',
                            isToday(day) && !isSameDay(day, selected) ? 'font-semibold text-primary-strong' : '',
                        ]"
                        @click="selectDay(day)"
                    >
                        {{ day.getDate() }}
                    </button>
                </div>
            </template>

            <div v-else class="grid grid-cols-3 gap-1">
                <button
                    v-for="(name, index) in months"
                    :key="name"
                    type="button"
                    class="inline-flex items-center justify-center rounded-md py-2 text-sm hover:bg-muted"
                    :class="
                        isSelectedMonth(index)
                            ? 'bg-primary text-primary-foreground hover:bg-primary hover:opacity-90'
                            : ''
                    "
                    @click="selectMonth(index)"
                >
                    {{ name }}
                </button>
            </div>

            <div class="mt-2 flex justify-end">
                <button
                    type="button"
                    class="rounded-md px-2 py-1 text-xs text-muted-foreground hover:bg-muted"
                    @click="clear"
                >
                    Limpiar
                </button>
            </div>
        </div>
    </div>
</template>
