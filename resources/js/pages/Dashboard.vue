<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { money } from '@/lib/format';

interface StatusRow {
    status: string;
    label: string;
    count: number;
}

interface CorteRow {
    courier: string;
    cash: number;
    transfer: number;
    total: number;
    commission: number;
    orders: number;
}

defineProps<{
    isAdmin: boolean;
    stats?: { today: number; open: number; deliveredToday: number; unpaidDelivered: number };
    statusBreakdown?: StatusRow[];
    corte?: CorteRow[];
    corteTotals?: { cash: number; transfer: number; total: number; commission: number };
    courierOpenOrders?: number;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Dashboard', href: '/dashboard' }],
    },
});
</script>

<template>
    <Head title="Dashboard" />

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
                <div class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                    <h2 class="mb-3 text-base font-semibold">Pedidos por estado</h2>
                    <ul class="space-y-2">
                        <li
                            v-for="row in statusBreakdown"
                            :key="row.status"
                            class="flex items-center justify-between text-sm"
                        >
                            <span class="text-muted-foreground">{{ row.label }}</span>
                            <span class="font-medium">{{ row.count }}</span>
                        </li>
                    </ul>
                </div>

                <div class="rounded-xl border border-sidebar-border/70 p-4 lg:col-span-2 dark:border-sidebar-border">
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
            <div class="rounded-xl border border-sidebar-border/70 p-6 dark:border-sidebar-border">
                <p class="text-sm text-muted-foreground">Tus pedidos asignados abiertos</p>
                <p class="mt-1 text-4xl font-semibold">{{ courierOpenOrders ?? 0 }}</p>
                <Link
                    href="/board"
                    class="mt-4 inline-flex rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:opacity-90"
                >
                    Ver mis pedidos
                </Link>
            </div>
        </template>
    </div>
</template>
