<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import Datepicker from '@/components/Datepicker.vue';
import { money, shortDate } from '@/lib/format';

interface DayRow {
    day: string;
    orders: number;
    revenue: number;
}

interface CourierRow {
    courier: string;
    orders: number;
    delivered: number;
    revenue: number;
    commission: number;
}

interface MethodRow {
    method: string;
    orders: number;
    revenue: number;
}

interface ProductRow {
    name: string;
    quantity: number;
    spent: number;
    orders: number;
}

const props = defineProps<{
    filters: { from: string; to: string };
    summary: { orders: number; delivered: number; cancelled: number; unpaid: number };
    totals: { revenue: number; commission: number; cash: number; transfer: number; paidOrders: number };
    perDay: DayRow[];
    perCourier: CourierRow[];
    perMethod: MethodRow[];
    topProducts: ProductRow[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Reportes', href: '/reports' }],
    },
});

const from = ref(props.filters.from);
const to = ref(props.filters.to);

function apply(): void {
    router.get(
        '/reports',
        { from: from.value, to: to.value },
        { preserveState: true, preserveScroll: true },
    );
}

const maxDayOrders = computed<number>(() =>
    Math.max(1, ...props.perDay.map((day) => day.orders)),
);
</script>

<template>
    <Head title="Reportes" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <h1 class="text-xl font-semibold">Reportes</h1>
            <form class="flex flex-wrap items-end gap-2" @submit.prevent="apply">
                <div class="grid gap-1">
                    <label class="text-xs text-muted-foreground">Desde</label>
                    <Datepicker v-model="from" placeholder="Desde" />
                </div>
                <div class="grid gap-1">
                    <label class="text-xs text-muted-foreground">Hasta</label>
                    <Datepicker v-model="to" placeholder="Hasta" />
                </div>
                <button
                    type="submit"
                    class="rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:opacity-90"
                >
                    Aplicar
                </button>
            </form>
        </div>

        <!-- Resumen -->
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                <p class="text-sm text-muted-foreground">Pedidos</p>
                <p class="mt-1 text-3xl font-semibold">{{ summary.orders }}</p>
            </div>
            <div class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                <p class="text-sm text-muted-foreground">Entregados</p>
                <p class="mt-1 text-3xl font-semibold">{{ summary.delivered }}</p>
            </div>
            <div class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                <p class="text-sm text-muted-foreground">Cancelados</p>
                <p class="mt-1 text-3xl font-semibold">{{ summary.cancelled }}</p>
            </div>
            <div class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                <p class="text-sm text-muted-foreground">Pendientes de pago</p>
                <p class="mt-1 text-3xl font-semibold">{{ summary.unpaid }}</p>
            </div>
        </div>

        <!-- Dinero -->
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                <p class="text-sm text-muted-foreground">Total cobrado</p>
                <p class="mt-1 text-2xl font-semibold">{{ money(totals.revenue) }}</p>
            </div>
            <div class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                <p class="text-sm text-muted-foreground">Comisiones</p>
                <p class="mt-1 text-2xl font-semibold">{{ money(totals.commission) }}</p>
            </div>
            <div class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                <p class="text-sm text-muted-foreground">Efectivo</p>
                <p class="mt-1 text-2xl font-semibold">{{ money(totals.cash) }}</p>
            </div>
            <div class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                <p class="text-sm text-muted-foreground">Transferencia</p>
                <p class="mt-1 text-2xl font-semibold">{{ money(totals.transfer) }}</p>
            </div>
        </div>
        <p class="-mt-2 text-xs text-muted-foreground">
            Los montos consideran los {{ totals.paidOrders }} pedidos pagados del periodo.
        </p>

        <div class="grid gap-6 lg:grid-cols-2">
            <!-- Pedidos por día -->
            <div class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                <h2 class="mb-3 text-base font-semibold">Pedidos por día</h2>
                <div v-if="perDay.length === 0" class="py-6 text-center text-sm text-muted-foreground">
                    Sin pedidos en el periodo.
                </div>
                <ul v-else class="space-y-2">
                    <li v-for="day in perDay" :key="day.day" class="flex items-center gap-3 text-sm">
                        <span class="w-24 shrink-0 text-muted-foreground">{{ shortDate(day.day) }}</span>
                        <span class="flex-1">
                            <span
                                class="inline-block h-3 rounded bg-primary/70"
                                :style="{ width: `${Math.max(6, (day.orders / maxDayOrders) * 100)}%` }"
                            ></span>
                        </span>
                        <span class="w-8 shrink-0 text-right font-medium">{{ day.orders }}</span>
                        <span class="w-24 shrink-0 text-right text-muted-foreground">{{ money(day.revenue) }}</span>
                    </li>
                </ul>
            </div>

            <!-- Por método de pago -->
            <div class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                <h2 class="mb-3 text-base font-semibold">Por método de pago</h2>
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-sidebar-border/70 text-left text-muted-foreground dark:border-sidebar-border">
                            <th class="py-2 pr-4 font-medium">Método</th>
                            <th class="py-2 pr-4 text-right font-medium">Pedidos</th>
                            <th class="py-2 text-right font-medium">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in perMethod" :key="row.method" class="border-b border-sidebar-border/40 dark:border-sidebar-border/60">
                            <td class="py-2 pr-4">{{ row.method }}</td>
                            <td class="py-2 pr-4 text-right">{{ row.orders }}</td>
                            <td class="py-2 text-right">{{ money(row.revenue) }}</td>
                        </tr>
                        <tr v-if="perMethod.length === 0">
                            <td colspan="3" class="py-6 text-center text-muted-foreground">Sin pagos en el periodo.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Por repartidor -->
        <div class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
            <h2 class="mb-3 text-base font-semibold">Desempeño por repartidor</h2>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-sidebar-border/70 text-left text-muted-foreground dark:border-sidebar-border">
                            <th class="py-2 pr-4 font-medium">Repartidor</th>
                            <th class="py-2 pr-4 text-right font-medium">Pedidos</th>
                            <th class="py-2 pr-4 text-right font-medium">Entregados</th>
                            <th class="py-2 pr-4 text-right font-medium">Total cobrado</th>
                            <th class="py-2 text-right font-medium">Comisiones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in perCourier" :key="row.courier" class="border-b border-sidebar-border/40 dark:border-sidebar-border/60">
                            <td class="py-2 pr-4">{{ row.courier }}</td>
                            <td class="py-2 pr-4 text-right">{{ row.orders }}</td>
                            <td class="py-2 pr-4 text-right">{{ row.delivered }}</td>
                            <td class="py-2 pr-4 text-right">{{ money(row.revenue) }}</td>
                            <td class="py-2 text-right">{{ money(row.commission) }}</td>
                        </tr>
                        <tr v-if="perCourier.length === 0">
                            <td colspan="5" class="py-6 text-center text-muted-foreground">Sin pedidos asignados en el periodo.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Productos más comprados -->
        <div class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
            <h2 class="mb-3 text-base font-semibold">Productos más comprados</h2>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-sidebar-border/70 text-left text-muted-foreground dark:border-sidebar-border">
                            <th class="py-2 pr-4 font-medium">Producto</th>
                            <th class="py-2 pr-4 text-right font-medium">Cantidad</th>
                            <th class="py-2 pr-4 text-right font-medium">Veces pedido</th>
                            <th class="py-2 text-right font-medium">Total gastado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in topProducts" :key="row.name" class="border-b border-sidebar-border/40 dark:border-sidebar-border/60">
                            <td class="py-2 pr-4">{{ row.name }}</td>
                            <td class="py-2 pr-4 text-right">{{ row.quantity }}</td>
                            <td class="py-2 pr-4 text-right">{{ row.orders }}</td>
                            <td class="py-2 text-right">{{ money(row.spent) }}</td>
                        </tr>
                        <tr v-if="topProducts.length === 0">
                            <td colspan="4" class="py-6 text-center text-muted-foreground">Sin productos en el periodo.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>
