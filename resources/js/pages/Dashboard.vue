<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import DailyOrdersChart from '@/components/charts/DailyOrdersChart.vue';
import StatusBars from '@/components/charts/StatusBars.vue';
import { money } from '@/lib/format';

interface StatusRow {
    status: string;
    label: string;
    count: number;
}

interface DayRow {
    day: string;
    orders: number;
    revenue: number;
}

interface CorteRow {
    courier: string;
    cash: number;
    transfer: number;
    total: number;
    commission: number;
    orders: number;
}

interface NextOrder {
    id: number;
    client: string | null;
    status_label: string;
    address: string;
    next_status: string | null;
    next_label: string | null;
}

defineProps<{
    isAdmin: boolean;
    stats?: { today: number; open: number; deliveredToday: number; unpaidDelivered: number };
    openByStatus?: StatusRow[];
    ordersPerDay?: DayRow[];
    corte?: CorteRow[];
    corteTotals?: { cash: number; transfer: number; total: number; commission: number };
    courierOpenOrders?: number;
    nextOrder?: NextOrder | null;
    buckets?: { to_buy: number; buying: number; to_deliver: number };
    deliveredToday?: number;
    cashCollectedToday?: number;
    awaitingPayment?: number;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Panel', href: '/dashboard' }],
    },
});

function advance(orderId: number, status: string): void {
    router.post(`/board/${orderId}/status`, { status }, { preserveScroll: true });
}
</script>

<template>
    <Head title="Panel" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4">
        <template v-if="isAdmin">
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                    <p class="text-sm text-muted-foreground">Pedidos hoy</p>
                    <p class="mt-1 text-3xl font-semibold">{{ stats?.today ?? 0 }}</p>
                </div>
                <div class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                    <p class="text-sm text-muted-foreground">Pedidos abiertos</p>
                    <p class="mt-1 text-3xl font-semibold">{{ stats?.open ?? 0 }}</p>
                </div>
                <div class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                    <p class="text-sm text-muted-foreground">Entregados hoy</p>
                    <p class="mt-1 text-3xl font-semibold">{{ stats?.deliveredToday ?? 0 }}</p>
                </div>
                <div class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                    <p class="text-sm text-muted-foreground">Entregados sin pagar</p>
                    <p class="mt-1 text-3xl font-semibold">{{ stats?.unpaidDelivered ?? 0 }}</p>
                </div>
            </div>

            <div class="grid gap-6 lg:grid-cols-3">
                <div class="rounded-xl border border-sidebar-border/70 p-4 lg:col-span-2 dark:border-sidebar-border">
                    <h2 class="mb-3 text-base font-semibold">Pedidos por día</h2>
                    <p class="mb-2 text-xs text-muted-foreground">Últimos 14 días</p>
                    <DailyOrdersChart :data="ordersPerDay ?? []" />
                </div>

                <div class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                    <h2 class="mb-4 text-base font-semibold">Pedidos abiertos por estado</h2>
                    <StatusBars v-if="openByStatus && openByStatus.length > 0" :data="openByStatus" />
                    <p v-else class="text-sm text-muted-foreground">Sin pedidos abiertos.</p>
                </div>
            </div>

            <div class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="text-base font-semibold">Corte del día</h2>
                    <span class="text-xs text-muted-foreground">Pagos registrados hoy</span>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-sidebar-border/70 text-left text-muted-foreground dark:border-sidebar-border">
                                <th class="py-2 pr-4 font-medium">Repartidor</th>
                                <th class="py-2 pr-4 text-right font-medium">Efectivo</th>
                                <th class="py-2 pr-4 text-right font-medium">Transferencia</th>
                                <th class="py-2 pr-4 text-right font-medium">Total</th>
                                <th class="py-2 pr-4 text-right font-medium">Comisiones</th>
                                <th class="py-2 text-right font-medium">Pedidos</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="row in corte"
                                :key="row.courier"
                                class="border-b border-sidebar-border/40 dark:border-sidebar-border/60"
                            >
                                <td class="py-2 pr-4">{{ row.courier }}</td>
                                <td class="py-2 pr-4 text-right">{{ money(row.cash) }}</td>
                                <td class="py-2 pr-4 text-right">{{ money(row.transfer) }}</td>
                                <td class="py-2 pr-4 text-right font-medium">{{ money(row.total) }}</td>
                                <td class="py-2 pr-4 text-right">{{ money(row.commission) }}</td>
                                <td class="py-2 text-right">{{ row.orders }}</td>
                            </tr>
                            <tr v-if="!corte || corte.length === 0">
                                <td colspan="6" class="py-6 text-center text-muted-foreground">
                                    Aún no hay pagos registrados hoy.
                                </td>
                            </tr>
                        </tbody>
                        <tfoot v-if="corte && corte.length > 0">
                            <tr class="font-semibold">
                                <td class="py-2 pr-4">Total</td>
                                <td class="py-2 pr-4 text-right">{{ money(corteTotals?.cash) }}</td>
                                <td class="py-2 pr-4 text-right">{{ money(corteTotals?.transfer) }}</td>
                                <td class="py-2 pr-4 text-right">{{ money(corteTotals?.total) }}</td>
                                <td class="py-2 pr-4 text-right">{{ money(corteTotals?.commission) }}</td>
                                <td class="py-2"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <div class="flex gap-3">
                <Link
                    href="/orders/create"
                    class="rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:opacity-90"
                >
                    Nuevo pedido
                </Link>
                <Link
                    href="/orders"
                    class="rounded-lg border border-sidebar-border/70 px-4 py-2 text-sm font-medium hover:bg-muted dark:border-sidebar-border"
                >
                    Ver pedidos
                </Link>
            </div>
        </template>

        <template v-else>
            <section v-if="nextOrder" class="rounded-xl border border-sidebar-border/70 p-5 dark:border-sidebar-border">
                <p class="text-sm text-muted-foreground">Lo que sigue</p>
                <p class="mt-1 text-2xl font-semibold">{{ nextOrder.client ?? 'Sin cliente' }}</p>
                <p class="text-sm text-muted-foreground">Pedido #{{ nextOrder.id }} · {{ nextOrder.status_label }}</p>
                <p v-if="nextOrder.address" class="mt-2 text-sm">{{ nextOrder.address }}</p>

                <div class="mt-4 flex flex-wrap items-center gap-3">
                    <button
                        v-if="nextOrder.next_status"
                        type="button"
                        class="rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:opacity-90"
                        @click="advance(nextOrder.id, nextOrder.next_status)"
                    >
                        {{ nextOrder.next_label }}
                    </button>
                    <Link
                        :href="`/board/${nextOrder.id}`"
                        class="rounded-lg border border-sidebar-border/70 px-4 py-2 text-sm font-medium hover:bg-muted dark:border-sidebar-border"
                    >
                        Ver el pedido
                    </Link>
                </div>
            </section>

            <section v-else class="rounded-xl border border-sidebar-border/70 p-6 text-center dark:border-sidebar-border">
                <p class="text-lg font-medium">No tienes pedidos pendientes</p>
                <p class="mt-1 text-sm text-muted-foreground">
                    Cuando el administrador te asigne uno, aparecerá aquí.
                </p>
            </section>

            <div class="grid gap-3 sm:grid-cols-3">
                <Link href="/board" class="rounded-xl border border-sidebar-border/70 p-4 hover:bg-muted dark:border-sidebar-border">
                    <p class="text-sm text-muted-foreground">Por comprar</p>
                    <p class="mt-1 text-3xl font-semibold">{{ buckets?.to_buy ?? 0 }}</p>
                </Link>
                <Link href="/board" class="rounded-xl border border-sidebar-border/70 p-4 hover:bg-muted dark:border-sidebar-border">
                    <p class="text-sm text-muted-foreground">Comprando</p>
                    <p class="mt-1 text-3xl font-semibold">{{ buckets?.buying ?? 0 }}</p>
                </Link>
                <Link href="/board" class="rounded-xl border border-sidebar-border/70 p-4 hover:bg-muted dark:border-sidebar-border">
                    <p class="text-sm text-muted-foreground">Por entregar</p>
                    <p class="mt-1 text-3xl font-semibold">{{ buckets?.to_deliver ?? 0 }}</p>
                </Link>
            </div>

            <div class="grid gap-3 sm:grid-cols-2">
                <div class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                    <p class="text-sm text-muted-foreground">Entregados hoy</p>
                    <p class="mt-1 text-3xl font-semibold">{{ deliveredToday ?? 0 }}</p>
                </div>
                <div class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                    <p class="text-sm text-muted-foreground">Efectivo cobrado hoy</p>
                    <p class="mt-1 text-3xl font-semibold">{{ money(cashCollectedToday) }}</p>
                    <p class="mt-1 text-xs text-muted-foreground">
                        Según lo que el administrador ya registró como cobrado.
                    </p>
                </div>
            </div>

            <p v-if="(awaitingPayment ?? 0) > 0" class="text-sm text-muted-foreground">
                Tienes {{ awaitingPayment }} entrega(s) sin cobro registrado todavía.
            </p>
        </template>
    </div>
</template>
