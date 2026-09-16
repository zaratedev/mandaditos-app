<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import DailyOrdersChart from '@/components/charts/DailyOrdersChart.vue';
import Datepicker from '@/components/Datepicker.vue';
import { duration, money, shortDate } from '@/lib/format';

interface DayRow {
    date: string;
    orders: number;
    revenue: number;
}

interface CourierRow {
    courier: string;
    orders: number;
    delivered: number;
    commission: number;
    moved: number;
    average: number | null;
}

interface ClientRow {
    client: string;
    orders: number;
    commission: number;
    moved: number;
    last: string;
}

interface MethodRow {
    method: string;
    orders: number;
    amount: number;
}

interface ProductRow {
    name: string;
    quantity: number;
    spent: number;
    orders: number;
}

const props = defineProps<{
    filters: { from: string; to: string; days: number };
    collected: {
        orders: number;
        commission: number;
        moved: number;
        cash: number;
        transfer: number;
        ticket: number;
        fee: number;
    };
    operations: {
        orders: number;
        delivered: number;
        cancelled: number;
        perDay: number;
    };
    change: {
        commission: number | null;
        moved: number | null;
        orders: number | null;
        delivered: number | null;
    };
    receivable: {
        orders: number;
        amount: number;
        commission: number;
        oldest: string | null;
    };
    delivery: {
        orders: number;
        average: number | null;
        slowest: number | null;
    };
    perDay: DayRow[];
    perCourier: CourierRow[];
    perClient: ClientRow[];
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

/**
 * The arrow carries the direction and the sign carries it again, so the colour is
 * never the only thing saying whether a number went the right way.
 */
function trend(value: number | null): string {
    if (value === null) {
        return '';
    }

    return `${value >= 0 ? '▲' : '▼'} ${Math.abs(value).toFixed(1)}%`;
}

function trendClass(value: number | null): string {
    if (value === null) {
        return '';
    }

    return value >= 0
        ? 'text-green-700 dark:text-green-300'
        : 'text-red-700 dark:text-red-300';
}
</script>

<template>
    <Head title="Reportes" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <h1 class="text-xl font-semibold">Reportes</h1>

            <form
                class="flex flex-wrap items-end gap-2"
                @submit.prevent="apply"
            >
                <div class="grid gap-1">
                    <label class="text-muted-foreground text-xs">Desde</label>
                    <Datepicker v-model="from" placeholder="Desde" />
                </div>
                <div class="grid gap-1">
                    <label class="text-muted-foreground text-xs">Hasta</label>
                    <Datepicker v-model="to" placeholder="Hasta" />
                </div>
                <button
                    type="submit"
                    class="bg-primary text-primary-foreground rounded-lg px-4 py-2 text-sm font-medium hover:opacity-90"
                >
                    Aplicar
                </button>
            </form>
        </div>

        <!-- Dinero -->
        <div>
            <p class="text-muted-foreground mb-2 text-xs">
                Cobrado entre el {{ shortDate(filters.from) }} y el
                {{ shortDate(filters.to) }}, comparado con los
                {{ filters.days }} días anteriores.
            </p>

            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div
                    class="border-sidebar-border/70 dark:border-sidebar-border rounded-xl border p-4"
                >
                    <p class="text-muted-foreground text-sm">
                        Comisiones cobradas
                    </p>
                    <p class="mt-1 text-3xl font-semibold">
                        {{ money(collected.commission) }}
                    </p>
                    <p
                        class="mt-1 min-h-4 text-xs"
                        :class="trendClass(change.commission)"
                    >
                        {{ trend(change.commission) }}
                    </p>
                    <p class="text-muted-foreground mt-1 text-xs">
                        {{ money(collected.fee) }} por pedido ·
                        {{ collected.orders }} pagos
                    </p>
                </div>

                <div
                    class="border-sidebar-border/70 dark:border-sidebar-border rounded-xl border p-4"
                >
                    <p class="text-muted-foreground text-sm">Dinero movido</p>
                    <p class="mt-1 text-3xl font-semibold">
                        {{ money(collected.moved) }}
                    </p>
                    <p
                        class="mt-1 min-h-4 text-xs"
                        :class="trendClass(change.moved)"
                    >
                        {{ trend(change.moved) }}
                    </p>
                    <p class="text-muted-foreground mt-1 text-xs">
                        Incluye el gasto del cliente · Efectivo
                        {{ money(collected.cash) }} · Transferencia
                        {{ money(collected.transfer) }}
                    </p>
                </div>

                <div
                    class="border-sidebar-border/70 dark:border-sidebar-border rounded-xl border p-4"
                >
                    <p class="text-muted-foreground text-sm">
                        Por cobrar (a hoy)
                    </p>
                    <p class="mt-1 text-3xl font-semibold">
                        {{ money(receivable.amount) }}
                    </p>
                    <p class="text-muted-foreground mt-1 text-xs">
                        {{ receivable.orders }} entrega(s) sin pago ·
                        {{ money(receivable.commission) }} de comisión
                    </p>
                    <p
                        v-if="receivable.oldest"
                        class="text-muted-foreground mt-1 text-xs"
                    >
                        La más vieja: {{ shortDate(receivable.oldest) }}
                    </p>
                </div>

                <div
                    class="border-sidebar-border/70 dark:border-sidebar-border rounded-xl border p-4"
                >
                    <p class="text-muted-foreground text-sm">Ticket promedio</p>
                    <p class="mt-1 text-3xl font-semibold">
                        {{ money(collected.ticket) }}
                    </p>
                    <p class="text-muted-foreground mt-1 text-xs">
                        {{ operations.perDay }} pedidos por día en el periodo
                    </p>
                </div>
            </div>
        </div>

        <!-- Operación -->
        <div>
            <p class="text-muted-foreground mb-2 text-xs">
                Pedidos creados entre el {{ shortDate(filters.from) }} y el
                {{ shortDate(filters.to) }}.
            </p>

            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div
                    class="border-sidebar-border/70 dark:border-sidebar-border rounded-xl border p-4"
                >
                    <p class="text-muted-foreground text-sm">Pedidos</p>
                    <p class="mt-1 text-3xl font-semibold">
                        {{ operations.orders }}
                    </p>
                    <p
                        class="mt-1 min-h-4 text-xs"
                        :class="trendClass(change.orders)"
                    >
                        {{ trend(change.orders) }}
                    </p>
                </div>
                <div
                    class="border-sidebar-border/70 dark:border-sidebar-border rounded-xl border p-4"
                >
                    <p class="text-muted-foreground text-sm">Entregados</p>
                    <p class="mt-1 text-3xl font-semibold">
                        {{ operations.delivered }}
                    </p>
                    <p
                        class="mt-1 min-h-4 text-xs"
                        :class="trendClass(change.delivered)"
                    >
                        {{ trend(change.delivered) }}
                    </p>
                </div>
                <div
                    class="border-sidebar-border/70 dark:border-sidebar-border rounded-xl border p-4"
                >
                    <p class="text-muted-foreground text-sm">Cancelados</p>
                    <p class="mt-1 text-3xl font-semibold">
                        {{ operations.cancelled }}
                    </p>
                </div>
                <div
                    class="border-sidebar-border/70 dark:border-sidebar-border rounded-xl border p-4"
                >
                    <p class="text-muted-foreground text-sm">
                        Tiempo de entrega
                    </p>
                    <p class="mt-1 text-3xl font-semibold">
                        {{ duration(delivery.average) }}
                    </p>
                    <p class="text-muted-foreground mt-1 text-xs">
                        Promedio de {{ delivery.orders }} entrega(s) · la más
                        lenta {{ duration(delivery.slowest) }}
                    </p>
                </div>
            </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-2">
            <!-- Pedidos por día -->
            <div
                class="border-sidebar-border/70 dark:border-sidebar-border rounded-xl border p-4"
            >
                <h2 class="text-base font-semibold">Pedidos por día</h2>
                <p class="text-muted-foreground mb-3 text-xs">
                    Por fecha de creación
                </p>
                <div
                    v-if="perDay.length === 0"
                    class="text-muted-foreground py-6 text-center text-sm"
                >
                    Sin pedidos en el periodo.
                </div>
                <DailyOrdersChart
                    v-else
                    :data="perDay"
                    unit="day"
                    revenue-label="Comisiones"
                />
            </div>

            <!-- Por método de pago -->
            <div
                class="border-sidebar-border/70 dark:border-sidebar-border rounded-xl border p-4"
            >
                <h2 class="text-base font-semibold">Por método de pago</h2>
                <p class="text-muted-foreground mb-3 text-xs">
                    De lo cobrado en el periodo
                </p>
                <table class="w-full text-sm">
                    <thead>
                        <tr
                            class="border-sidebar-border/70 text-muted-foreground dark:border-sidebar-border border-b text-left"
                        >
                            <th class="py-2 pr-4 font-medium">Método</th>
                            <th class="py-2 pr-4 text-right font-medium">
                                Pedidos
                            </th>
                            <th class="py-2 text-right font-medium">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="row in perMethod"
                            :key="row.method"
                            class="border-sidebar-border/40 dark:border-sidebar-border/60 border-b"
                        >
                            <td class="py-2 pr-4">{{ row.method }}</td>
                            <td class="py-2 pr-4 text-right">
                                {{ row.orders }}
                            </td>
                            <td class="py-2 text-right">
                                {{ money(row.amount) }}
                            </td>
                        </tr>
                        <tr v-if="perMethod.length === 0">
                            <td
                                colspan="3"
                                class="text-muted-foreground py-6 text-center"
                            >
                                Sin cobros en el periodo.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Mejores clientes -->
        <div
            class="border-sidebar-border/70 dark:border-sidebar-border rounded-xl border p-4"
        >
            <h2 class="text-base font-semibold">Mejores clientes</h2>
            <p class="text-muted-foreground mb-3 text-xs">
                Los 10 que más comisiones dejaron en el periodo
            </p>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr
                            class="border-sidebar-border/70 text-muted-foreground dark:border-sidebar-border border-b text-left"
                        >
                            <th class="py-2 pr-4 font-medium">Cliente</th>
                            <th class="py-2 pr-4 text-right font-medium">
                                Pedidos
                            </th>
                            <th class="py-2 pr-4 text-right font-medium">
                                Comisiones
                            </th>
                            <th class="py-2 pr-4 text-right font-medium">
                                Dinero movido
                            </th>
                            <th class="py-2 text-right font-medium">
                                Último pedido
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="row in perClient"
                            :key="row.client"
                            class="border-sidebar-border/40 dark:border-sidebar-border/60 border-b"
                        >
                            <td class="py-2 pr-4">{{ row.client }}</td>
                            <td class="py-2 pr-4 text-right">
                                {{ row.orders }}
                            </td>
                            <td class="py-2 pr-4 text-right font-medium">
                                {{ money(row.commission) }}
                            </td>
                            <td
                                class="text-muted-foreground py-2 pr-4 text-right"
                            >
                                {{ money(row.moved) }}
                            </td>
                            <td class="text-muted-foreground py-2 text-right">
                                {{ shortDate(row.last) }}
                            </td>
                        </tr>
                        <tr v-if="perClient.length === 0">
                            <td
                                colspan="5"
                                class="text-muted-foreground py-6 text-center"
                            >
                                Sin clientes con pedidos en el periodo.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Por repartidor -->
        <div
            class="border-sidebar-border/70 dark:border-sidebar-border rounded-xl border p-4"
        >
            <h2 class="text-base font-semibold">Desempeño por repartidor</h2>
            <p class="text-muted-foreground mb-3 text-xs">
                Ordenado por las comisiones que generaron sus entregas
            </p>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr
                            class="border-sidebar-border/70 text-muted-foreground dark:border-sidebar-border border-b text-left"
                        >
                            <th class="py-2 pr-4 font-medium">Repartidor</th>
                            <th class="py-2 pr-4 text-right font-medium">
                                Pedidos
                            </th>
                            <th class="py-2 pr-4 text-right font-medium">
                                Entregados
                            </th>
                            <th class="py-2 pr-4 text-right font-medium">
                                Tiempo promedio
                            </th>
                            <th class="py-2 pr-4 text-right font-medium">
                                Comisiones
                            </th>
                            <th class="py-2 text-right font-medium">
                                Dinero movido
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="row in perCourier"
                            :key="row.courier"
                            class="border-sidebar-border/40 dark:border-sidebar-border/60 border-b"
                        >
                            <td class="py-2 pr-4">{{ row.courier }}</td>
                            <td class="py-2 pr-4 text-right">
                                {{ row.orders }}
                            </td>
                            <td class="py-2 pr-4 text-right">
                                {{ row.delivered }}
                            </td>
                            <td class="py-2 pr-4 text-right">
                                {{ duration(row.average) }}
                            </td>
                            <td class="py-2 pr-4 text-right font-medium">
                                {{ money(row.commission) }}
                            </td>
                            <td class="text-muted-foreground py-2 text-right">
                                {{ money(row.moved) }}
                            </td>
                        </tr>
                        <tr v-if="perCourier.length === 0">
                            <td
                                colspan="6"
                                class="text-muted-foreground py-6 text-center"
                            >
                                Sin pedidos asignados en el periodo.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Productos más comprados -->
        <div
            class="border-sidebar-border/70 dark:border-sidebar-border rounded-xl border p-4"
        >
            <h2 class="text-base font-semibold">Productos más comprados</h2>
            <p class="text-muted-foreground mb-3 text-xs">
                Agrupados por el nombre que escribió el repartidor
            </p>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr
                            class="border-sidebar-border/70 text-muted-foreground dark:border-sidebar-border border-b text-left"
                        >
                            <th class="py-2 pr-4 font-medium">Producto</th>
                            <th class="py-2 pr-4 text-right font-medium">
                                Cantidad
                            </th>
                            <th class="py-2 pr-4 text-right font-medium">
                                Veces pedido
                            </th>
                            <th class="py-2 text-right font-medium">
                                Total gastado
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="row in topProducts"
                            :key="row.name"
                            class="border-sidebar-border/40 dark:border-sidebar-border/60 border-b"
                        >
                            <td class="py-2 pr-4">{{ row.name }}</td>
                            <td class="py-2 pr-4 text-right">
                                {{ row.quantity }}
                            </td>
                            <td class="py-2 pr-4 text-right">
                                {{ row.orders }}
                            </td>
                            <td class="py-2 text-right">
                                {{ money(row.spent) }}
                            </td>
                        </tr>
                        <tr v-if="topProducts.length === 0">
                            <td
                                colspan="4"
                                class="text-muted-foreground py-6 text-center"
                            >
                                Sin productos en el periodo.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>
