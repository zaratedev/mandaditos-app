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

        <div
            v-if="orders.length === 0"
            class="border-sidebar-border/70 text-muted-foreground dark:border-sidebar-border rounded-xl border p-8 text-center"
        >
            No tienes pedidos asignados abiertos.
        </div>

        <Link
            v-for="order in orders"
            :key="order.id"
            :href="`/board/${order.id}`"
            class="border-sidebar-border/70 hover:bg-muted/50 dark:border-sidebar-border flex items-center justify-between gap-3 rounded-xl border p-4 transition"
        >
            <div class="min-w-0">
                <p class="font-medium">Pedido #{{ order.id }}</p>
                <p class="text-muted-foreground truncate text-sm">
                    {{ order.client ?? '—' }}
                </p>
                <p class="text-muted-foreground mt-1 text-xs">
                    {{ order.created_at ?? '' }}
                </p>
            </div>
            <div class="flex flex-col items-end gap-1">
                <span
                    class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium"
                    :class="statusBadgeClass(order.status)"
                >
                    {{ order.status_label }}
                </span>
                <span class="text-sm font-medium">{{
                    money(order.total)
                }}</span>
            </div>
        </Link>
    </div>
</template>
