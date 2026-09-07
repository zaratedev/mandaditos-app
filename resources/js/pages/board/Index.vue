<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { money, statusBadgeClass } from '@/lib/format';

interface OrderRow {
    id: number;
    client: string | null;
    status: string;
    status_label: string;
    payment_status: string;
    total: string | null;
    created_at: string | null;
}

defineProps<{
    orders: OrderRow[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Mis pedidos', href: '/board' }],
    },
});
</script>

<template>
    <Head title="Mis pedidos" />

    <div class="mx-auto flex w-full max-w-2xl flex-col gap-4 p-4">
        <h1 class="text-xl font-semibold">Mis pedidos</h1>

        <div v-if="orders.length === 0" class="rounded-xl border border-sidebar-border/70 p-8 text-center text-muted-foreground dark:border-sidebar-border">
            No tienes pedidos asignados abiertos.
        </div>

        <Link
            v-for="order in orders"
            :key="order.id"
            :href="`/board/${order.id}`"
            class="flex items-center justify-between gap-3 rounded-xl border border-sidebar-border/70 p-4 transition hover:bg-muted/50 dark:border-sidebar-border"
        >
            <div class="min-w-0">
                <p class="font-medium">Pedido #{{ order.id }}</p>
                <p class="truncate text-sm text-muted-foreground">{{ order.client ?? '—' }}</p>
                <p class="mt-1 text-xs text-muted-foreground">{{ order.created_at ?? '' }}</p>
            </div>
            <div class="flex flex-col items-end gap-1">
                <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium" :class="statusBadgeClass(order.status)">
                    {{ order.status_label }}
                </span>
                <span class="text-sm font-medium">{{ money(order.total) }}</span>
            </div>
        </Link>
    </div>
</template>
