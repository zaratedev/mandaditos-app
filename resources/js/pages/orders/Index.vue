<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { reactive } from 'vue';
import Datepicker from '@/components/Datepicker.vue';
import Select from '@/components/Select.vue';
import { money, paymentBadgeClass, statusBadgeClass } from '@/lib/format';

interface OrderRow {
    id: number;
    client: string | null;
    courier: string | null;
    status: string;
    status_label: string;
    total: string | null;
    payment_status: string;
    payment_status_label: string;
    created_at: string | null;
}

interface PageLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface Paginator<T> {
    data: T[];
    links: PageLink[];
    from: number | null;
    to: number | null;
    total: number;
}

interface Option {
    value: string;
    label: string;
}

interface Filters {
    status: string;
    courier_id: string;
    client_id: string;
    payment_status: string;
    payment_method: string;
    from: string;
    to: string;
}

const props = defineProps<{
    orders: Paginator<OrderRow>;
    filters: Filters;
    statuses: Option[];
    couriers: { id: number; name: string }[];
    clients: { id: number; name: string }[];
    paymentStatuses: Option[];
    paymentMethods: Option[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Pedidos', href: '/orders' }],
    },
});

const form = reactive<Filters>({ ...props.filters });

function apply(): void {
    const params = Object.fromEntries(
        Object.entries(form).filter(([, value]) => value !== ''),
    );

    router.get('/orders', params, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

function clear(): void {
    (Object.keys(form) as (keyof Filters)[]).forEach((key) => {
        form[key] = '';
    });

    router.get('/orders', {}, { preserveState: true, replace: true });
}
</script>

<template>
    <Head title="Pedidos" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-xl font-semibold">Pedidos</h1>
            <Link
                href="/orders/create"
                class="rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:opacity-90"
            >
                Nuevo pedido
            </Link>
        </div>

        <form
            class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border"
            @submit.prevent="apply"
        >
            <div class="grid items-end gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                <div class="grid gap-1">
                    <label for="f-status" class="text-xs text-muted-foreground">Estado</label>
                    <Select id="f-status" v-model="form.status">
                        <option value="">Todos</option>
                        <option v-for="option in statuses" :key="option.value" :value="option.value">
                            {{ option.label }}
                        </option>
                    </Select>
                </div>

                <div class="grid gap-1">
                    <label for="f-courier" class="text-xs text-muted-foreground">Repartidor</label>
                    <Select id="f-courier" v-model="form.courier_id">
                        <option value="">Todos</option>
                        <option value="unassigned">Sin asignar</option>
                        <option v-for="courier in couriers" :key="courier.id" :value="String(courier.id)">
                            {{ courier.name }}
                        </option>
                    </Select>
                </div>

                <div class="grid gap-1">
                    <label for="f-client" class="text-xs text-muted-foreground">Cliente</label>
                    <Select id="f-client" v-model="form.client_id">
                        <option value="">Todos</option>
                        <option v-for="client in clients" :key="client.id" :value="String(client.id)">
                            {{ client.name }}
                        </option>
                    </Select>
                </div>

                <div class="grid gap-1">
                    <label for="f-pay-status" class="text-xs text-muted-foreground">Pago</label>
                    <Select id="f-pay-status" v-model="form.payment_status">
                        <option value="">Todos</option>
                        <option v-for="option in paymentStatuses" :key="option.value" :value="option.value">
                            {{ option.label }}
                        </option>
                    </Select>
                </div>

                <div class="grid gap-1">
                    <label for="f-pay-method" class="text-xs text-muted-foreground">Método de pago</label>
                    <Select id="f-pay-method" v-model="form.payment_method">
                        <option value="">Todos</option>
                        <option v-for="option in paymentMethods" :key="option.value" :value="option.value">
                            {{ option.label }}
                        </option>
                    </Select>
                </div>

                <div class="grid gap-1">
                    <label class="text-xs text-muted-foreground">Desde</label>
                    <Datepicker v-model="form.from" placeholder="Desde" />
                </div>

                <div class="grid gap-1">
                    <label class="text-xs text-muted-foreground">Hasta</label>
                    <Datepicker v-model="form.to" placeholder="Hasta" />
                </div>

                <div class="flex items-center gap-2">
                    <button
                        type="submit"
                        class="rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:opacity-90"
                    >
                        Filtrar
                    </button>
                    <button
                        type="button"
                        class="rounded-lg border border-sidebar-border/70 px-4 py-2 text-sm font-medium hover:bg-muted dark:border-sidebar-border"
                        @click="clear"
                    >
                        Limpiar
                    </button>
                </div>
            </div>
        </form>

        <p class="text-sm text-muted-foreground">{{ orders.total }} pedido(s)</p>

        <div class="overflow-x-auto rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-sidebar-border/70 text-left text-muted-foreground dark:border-sidebar-border">
                        <th class="px-4 py-3 font-medium">#</th>
                        <th class="px-4 py-3 font-medium">Cliente</th>
                        <th class="px-4 py-3 font-medium">Repartidor</th>
                        <th class="px-4 py-3 font-medium">Estado</th>
                        <th class="px-4 py-3 text-right font-medium">Total</th>
                        <th class="px-4 py-3 font-medium">Pago</th>
                        <th class="px-4 py-3 font-medium">Creado</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="order in orders.data"
                        :key="order.id"
                        class="cursor-pointer border-b border-sidebar-border/40 hover:bg-muted/50 dark:border-sidebar-border/60"
                        @click="router.visit(`/orders/${order.id}`)"
                    >
                        <td class="px-4 py-3 font-medium">#{{ order.id }}</td>
                        <td class="px-4 py-3">{{ order.client ?? '—' }}</td>
                        <td class="px-4 py-3">{{ order.courier ?? 'Sin asignar' }}</td>
                        <td class="px-4 py-3">
                            <span
                                class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium"
                                :class="statusBadgeClass(order.status)"
                            >
                                {{ order.status_label }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right">{{ money(order.total) }}</td>
                        <td class="px-4 py-3">
                            <span
                                class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium"
                                :class="paymentBadgeClass(order.payment_status)"
                            >
                                {{ order.payment_status_label }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-muted-foreground">{{ order.created_at ?? '—' }}</td>
                    </tr>
                    <tr v-if="orders.data.length === 0">
                        <td colspan="7" class="px-4 py-8 text-center text-muted-foreground">
                            No hay pedidos con estos filtros.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-if="orders.links.length > 3" class="flex flex-wrap gap-1">
            <template v-for="(link, index) in orders.links" :key="index">
                <Link
                    v-if="link.url"
                    :href="link.url"
                    class="rounded-md border border-sidebar-border/70 px-3 py-1.5 text-sm dark:border-sidebar-border"
                    :class="{ 'bg-primary text-primary-foreground': link.active }"
                    v-html="link.label"
                />
                <span
                    v-else
                    class="rounded-md border border-sidebar-border/40 px-3 py-1.5 text-sm text-muted-foreground"
                    v-html="link.label"
                />
            </template>
        </div>
    </div>
</template>
