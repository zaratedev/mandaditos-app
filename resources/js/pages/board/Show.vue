<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import Select from '@/components/Select.vue';
import { Button } from '@/components/ui/button';
import { money, paymentBadgeClass, statusBadgeClass } from '@/lib/format';

interface Item {
    id: number;
    name: string;
    quantity: string;
    unit_price: string | null;
    line_total: string | null;
}

interface Order {
    id: number;
    shopping_list: string;
    notes: string | null;
    items_subtotal: string | null;
    commission: string | null;
    total: string | null;
    status: string;
    status_label: string;
    payment_status: string;
    payment_status_label: string;
    client: { id: number; name: string; phone: string | null };
    address: { id: number; label: string | null; street: string; neighborhood: string | null; city: string | null; landmark: string | null };
    items: Item[];
}

const props = defineProps<{
    order: Order;
    statuses: { value: string; label: string }[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Mis pedidos', href: '/board' }],
    },
});

const statusForm = useForm<{ status: string }>({
    status: props.order.status,
});


function updateStatus(): void {
    statusForm.post(`/board/${props.order.id}/status`, { preserveScroll: true });
}
</script>

<template>
    <Head :title="`Pedido #${order.id}`" />

    <div class="mx-auto flex w-full max-w-2xl flex-col gap-4 p-4">
        <div class="flex items-center justify-between gap-3">
            <h1 class="text-xl font-semibold">Pedido #{{ order.id }}</h1>
            <div class="flex items-center gap-2">
                <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium" :class="statusBadgeClass(order.status)">
                    {{ order.status_label }}
                </span>
                <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium" :class="paymentBadgeClass(order.payment_status)">
                    {{ order.payment_status_label }}
                </span>
            </div>
        </div>

        <div class="rounded-xl border border-sidebar-border/70 p-4 text-sm dark:border-sidebar-border">
            <p class="font-medium">{{ order.client.name }}</p>
            <a v-if="order.client.phone" :href="`tel:${order.client.phone}`" class="text-primary-strong underline">
                {{ order.client.phone }}
            </a>
            <p class="mt-2 text-muted-foreground">
                {{ order.address.street }}<template v-if="order.address.neighborhood">, {{ order.address.neighborhood }}</template>
            </p>
            <p v-if="order.address.landmark" class="text-muted-foreground">Ref: {{ order.address.landmark }}</p>
        </div>

        <div class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
            <h2 class="mb-2 text-base font-semibold">Lista de compra</h2>
            <p class="whitespace-pre-line text-sm text-muted-foreground">{{ order.shopping_list }}</p>
        </div>

        <div v-if="order.items.length > 0" class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
            <h2 class="mb-3 text-base font-semibold">Productos</h2>
            <ul class="space-y-1 text-sm">
                <li v-for="item in order.items" :key="item.id" class="flex justify-between gap-3">
                    <span>{{ item.quantity }} × {{ item.name }}</span>
                    <span class="text-muted-foreground">{{ money(item.line_total) }}</span>
                </li>
            </ul>
            <p class="mt-3 text-right text-sm">Total: <span class="font-semibold">{{ money(order.total) }}</span></p>
        </div>

        <div class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
            <h2 class="mb-3 text-base font-semibold">Actualizar estado</h2>
            <div class="grid gap-2">
                <Select v-model="statusForm.status">
                    <option v-for="option in statuses" :key="option.value" :value="option.value">{{ option.label }}</option>
                </Select>
                <Button :disabled="statusForm.processing" @click="updateStatus">Guardar estado</Button>
            </div>
            <p class="mt-2 text-xs text-muted-foreground">El cobro lo registra el administrador.</p>
        </div>

        <Link href="/board" class="text-sm text-muted-foreground">← Volver a mis pedidos</Link>
    </div>
</template>
