<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, reactive, ref, watch } from 'vue';
import DailyOrdersChart from '@/components/charts/DailyOrdersChart.vue';
import StatusBars from '@/components/charts/StatusBars.vue';
import Datepicker from '@/components/Datepicker.vue';
import { money, shortDate } from '@/lib/format';

interface StatusRow {
    status: string;
    label: string;
    count: number;
}

interface ChartPoint {
    date: string;
    orders: number;
    revenue: number;
}

interface OrdersChart {
    period: string;
    unit: string;
    from: string;
    to: string;
    points: ChartPoint[];
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

const props = defineProps<{
    isAdmin: boolean;
    today?: string;
    stats?: {
        today: number;
        open: number;
        deliveredToday: number;
        unpaidDelivered: number;
    };
    openByStatus?: StatusRow[];
    ordersChart?: OrdersChart;
    corte?: CorteRow[];
    corteTotals?: {
        cash: number;
        transfer: number;
        total: number;
        commission: number;
    };
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
    router.post(
        `/board/${orderId}/status`,
        { status },
        { preserveScroll: true },
    );
}

function ordersUrl(params: Record<string, string>): string {
    return `/orders?${new URLSearchParams(params).toString()}`;
}

/**
 * Each stat card opens the order list showing exactly the orders it counted, so the
 * filters here have to mirror the ones the dashboard query used.
 */
const statLinks = computed(() => {
    const day = props.today ?? '';

    return {
        today: ordersUrl({ from: day, to: day }),
        open: ordersUrl({ status: 'open' }),
        deliveredToday: ordersUrl({
            status: 'delivered',
            date_field: 'delivered',
            from: day,
            to: day,
        }),
        unpaidDelivered: ordersUrl({
            status: 'delivered',
            payment_status: 'pending',
        }),
    };
});

function openStatus(status: string): void {
    router.get(ordersUrl({ status }));
}

const periodOptions = [
    { value: 'day', label: 'Día' },
    { value: 'month', label: 'Mes' },
    { value: 'custom', label: 'Personalizado' },
];

const period = ref(props.ordersChart?.period ?? 'day');

const range = reactive({
    from: props.ordersChart?.from ?? '',
    to: props.ordersChart?.to ?? '',
});

/**
 * Only the chart changes, so only the chart is asked for: the stats, the cash cut
 * and the status breakdown stay on screen untouched while the range is reworked.
 */
function reloadChart(): void {
    router.get(
        '/dashboard',
        period.value === 'custom'
            ? { period: period.value, from: range.from, to: range.to }
            : { period: period.value },
        {
            only: ['ordersChart'],
            preserveState: true,
            preserveScroll: true,
            replace: true,
        },
    );
}

function selectPeriod(value: string): void {
    period.value = value;
    reloadChart();
}

function setRange(key: 'from' | 'to', value: string): void {
    range[key] = value;
    reloadChart();
}

// Whatever window is on screen becomes the starting point of a custom range, so
// picking "Personalizado" opens on the dates the admin was already looking at.
watch(
    () => props.ordersChart,
    (chart) => {
        if (chart && period.value !== 'custom') {
            range.from = chart.from;
            range.to = chart.to;
        }
    },
);

const chartTitle = computed((): string =>
    props.ordersChart?.unit === 'month' ? 'Pedidos por mes' : 'Pedidos por día',
);

const chartCaption = computed((): string => {
    const chart = props.ordersChart;

    if (!chart) {
        return '';
    }

    return `Del ${shortDate(chart.from)} al ${shortDate(chart.to)}`;
});

/**
 * A bar opens the orders it counted. A monthly bar covers its whole month, so the
 * range has to be closed at the last day rather than at the first.
 */
function openChartPoint(date: string): void {
    if (props.ordersChart?.unit !== 'month') {
        router.get(ordersUrl({ from: date, to: date }));

        return;
    }

    const [year, month] = date.split('-').map(Number);
    const lastDay = new Date(year ?? 0, month ?? 0, 0).getDate();

    router.get(
        ordersUrl({
            from: date,
            to: `${date.slice(0, 7)}-${String(lastDay).padStart(2, '0')}`,
        }),
    );
}
</script>

<template>
    <Head title="Panel" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4">
        <template v-if="isAdmin">
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <Link
                    :href="statLinks.today"
                    class="border-sidebar-border/70 hover:bg-muted/50 dark:border-sidebar-border rounded-xl border p-4 transition"
                >
                    <p class="text-muted-foreground text-sm">Pedidos hoy</p>
                    <p class="mt-1 text-3xl font-semibold">
                        {{ stats?.today ?? 0 }}
                    </p>
                </Link>
                <Link
                    :href="statLinks.open"
                    class="border-sidebar-border/70 hover:bg-muted/50 dark:border-sidebar-border rounded-xl border p-4 transition"
                >
                    <p class="text-muted-foreground text-sm">
                        Pedidos abiertos
                    </p>
                    <p class="mt-1 text-3xl font-semibold">
                        {{ stats?.open ?? 0 }}
                    </p>
                </Link>
                <Link
                    :href="statLinks.deliveredToday"
                    class="border-sidebar-border/70 hover:bg-muted/50 dark:border-sidebar-border rounded-xl border p-4 transition"
                >
                    <p class="text-muted-foreground text-sm">Entregados hoy</p>
                    <p class="mt-1 text-3xl font-semibold">
                        {{ stats?.deliveredToday ?? 0 }}
                    </p>
                </Link>
                <Link
                    :href="statLinks.unpaidDelivered"
                    class="border-sidebar-border/70 hover:bg-muted/50 dark:border-sidebar-border rounded-xl border p-4 transition"
                >
                    <p class="text-muted-foreground text-sm">
                        Entregados sin pagar
                    </p>
                    <p class="mt-1 text-3xl font-semibold">
                        {{ stats?.unpaidDelivered ?? 0 }}
                    </p>
                </Link>
            </div>

            <div class="grid gap-6 lg:grid-cols-3">
                <div
                    class="border-sidebar-border/70 dark:border-sidebar-border rounded-xl border p-4 lg:col-span-2"
                >
                    <div
                        class="mb-3 flex flex-wrap items-start justify-between gap-3"
                    >
                        <div>
                            <h2 class="text-base font-semibold">
                                {{ chartTitle }}
                            </h2>
                            <p class="text-muted-foreground text-xs">
                                {{ chartCaption }}
                            </p>
                        </div>

                        <div class="flex flex-wrap items-center gap-2">
                            <div
                                class="border-sidebar-border/70 dark:border-sidebar-border flex rounded-lg border p-0.5"
                            >
                                <button
                                    v-for="option in periodOptions"
                                    :key="option.value"
                                    type="button"
                                    class="rounded-md px-3 py-1 text-xs font-medium transition"
                                    :class="
                                        period === option.value
                                            ? 'bg-primary text-primary-foreground'
                                            : 'text-muted-foreground hover:bg-muted'
                                    "
                                    @click="selectPeriod(option.value)"
                                >
                                    {{ option.label }}
                                </button>
                            </div>

                            <template v-if="period === 'custom'">
                                <div class="w-36">
                                    <Datepicker
                                        :model-value="range.from"
                                        placeholder="Desde"
                                        @update:model-value="
                                            (value) => setRange('from', value)
                                        "
                                    />
                                </div>
                                <div class="w-36">
                                    <Datepicker
                                        :model-value="range.to"
                                        placeholder="Hasta"
                                        @update:model-value="
                                            (value) => setRange('to', value)
                                        "
                                    />
                                </div>
                            </template>
                        </div>
                    </div>

                    <DailyOrdersChart
                        :data="ordersChart?.points ?? []"
                        :unit="ordersChart?.unit ?? 'day'"
                        @select="openChartPoint"
                    />
                </div>

                <div
                    class="border-sidebar-border/70 dark:border-sidebar-border rounded-xl border p-4"
                >
                    <h2 class="mb-1 text-base font-semibold">
                        Pedidos abiertos por estado
                    </h2>
                    <p class="text-muted-foreground mb-3 text-xs">
                        Toca una barra para ver esos pedidos
                    </p>
                    <StatusBars
                        v-if="openByStatus && openByStatus.length > 0"
                        :data="openByStatus"
                        @select="openStatus"
                    />
                    <p v-else class="text-muted-foreground text-sm">
                        Sin pedidos abiertos.
                    </p>
                </div>
            </div>

            <div
                class="border-sidebar-border/70 dark:border-sidebar-border rounded-xl border p-4"
            >
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="text-base font-semibold">Corte del día</h2>
                    <span class="text-muted-foreground text-xs"
                        >Pagos registrados hoy</span
                    >
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr
                                class="border-sidebar-border/70 text-muted-foreground dark:border-sidebar-border border-b text-left"
                            >
                                <th class="py-2 pr-4 font-medium">
                                    Repartidor
                                </th>
                                <th class="py-2 pr-4 text-right font-medium">
                                    Efectivo
                                </th>
                                <th class="py-2 pr-4 text-right font-medium">
                                    Transferencia
                                </th>
                                <th class="py-2 pr-4 text-right font-medium">
                                    Total
                                </th>
                                <th class="py-2 pr-4 text-right font-medium">
                                    Comisiones
                                </th>
                                <th class="py-2 text-right font-medium">
                                    Pedidos
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="row in corte"
                                :key="row.courier"
                                class="border-sidebar-border/40 dark:border-sidebar-border/60 border-b"
                            >
                                <td class="py-2 pr-4">{{ row.courier }}</td>
                                <td class="py-2 pr-4 text-right">
                                    {{ money(row.cash) }}
                                </td>
                                <td class="py-2 pr-4 text-right">
                                    {{ money(row.transfer) }}
                                </td>
                                <td class="py-2 pr-4 text-right font-medium">
                                    {{ money(row.total) }}
                                </td>
                                <td class="py-2 pr-4 text-right">
                                    {{ money(row.commission) }}
                                </td>
                                <td class="py-2 text-right">
                                    {{ row.orders }}
                                </td>
                            </tr>
                            <tr v-if="!corte || corte.length === 0">
                                <td
                                    colspan="6"
                                    class="text-muted-foreground py-6 text-center"
                                >
                                    Aún no hay pagos registrados hoy.
                                </td>
                            </tr>
                        </tbody>
                        <tfoot v-if="corte && corte.length > 0">
                            <tr class="font-semibold">
                                <td class="py-2 pr-4">Total</td>
                                <td class="py-2 pr-4 text-right">
                                    {{ money(corteTotals?.cash) }}
                                </td>
                                <td class="py-2 pr-4 text-right">
                                    {{ money(corteTotals?.transfer) }}
                                </td>
                                <td class="py-2 pr-4 text-right">
                                    {{ money(corteTotals?.total) }}
                                </td>
                                <td class="py-2 pr-4 text-right">
                                    {{ money(corteTotals?.commission) }}
                                </td>
                                <td class="py-2"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <div class="flex gap-3">
                <Link
                    href="/orders/create"
                    class="bg-primary text-primary-foreground rounded-lg px-4 py-2 text-sm font-medium hover:opacity-90"
                >
                    Nuevo pedido
                </Link>
                <Link
                    href="/orders"
                    class="border-sidebar-border/70 hover:bg-muted dark:border-sidebar-border rounded-lg border px-4 py-2 text-sm font-medium"
                >
                    Ver pedidos
                </Link>
            </div>
        </template>

        <template v-else>
            <section
                v-if="nextOrder"
                class="border-sidebar-border/70 dark:border-sidebar-border rounded-xl border p-5"
            >
                <p class="text-muted-foreground text-sm">Lo que sigue</p>
                <p class="mt-1 text-2xl font-semibold">
                    {{ nextOrder.client ?? 'Sin cliente' }}
                </p>
                <p class="text-muted-foreground text-sm">
                    Pedido #{{ nextOrder.id }} · {{ nextOrder.status_label }}
                </p>
                <p v-if="nextOrder.address" class="mt-2 text-sm">
                    {{ nextOrder.address }}
                </p>

                <div class="mt-4 flex flex-wrap items-center gap-3">
                    <button
                        v-if="nextOrder.next_status"
                        type="button"
                        class="bg-primary text-primary-foreground rounded-lg px-4 py-2 text-sm font-medium hover:opacity-90"
                        @click="advance(nextOrder.id, nextOrder.next_status)"
                    >
                        {{ nextOrder.next_label }}
                    </button>
                    <Link
                        :href="`/board/${nextOrder.id}`"
                        class="border-sidebar-border/70 hover:bg-muted dark:border-sidebar-border rounded-lg border px-4 py-2 text-sm font-medium"
                    >
                        Ver el pedido
                    </Link>
                </div>
            </section>

            <section
                v-else
                class="border-sidebar-border/70 dark:border-sidebar-border rounded-xl border p-6 text-center"
            >
                <p class="text-lg font-medium">No tienes pedidos pendientes</p>
                <p class="text-muted-foreground mt-1 text-sm">
                    Cuando el administrador te asigne uno, aparecerá aquí.
                </p>
            </section>

            <div class="grid gap-3 sm:grid-cols-3">
                <Link
                    href="/board"
                    class="border-sidebar-border/70 hover:bg-muted dark:border-sidebar-border rounded-xl border p-4"
                >
                    <p class="text-muted-foreground text-sm">Por comprar</p>
                    <p class="mt-1 text-3xl font-semibold">
                        {{ buckets?.to_buy ?? 0 }}
                    </p>
                </Link>
                <Link
                    href="/board"
                    class="border-sidebar-border/70 hover:bg-muted dark:border-sidebar-border rounded-xl border p-4"
                >
                    <p class="text-muted-foreground text-sm">Comprando</p>
                    <p class="mt-1 text-3xl font-semibold">
                        {{ buckets?.buying ?? 0 }}
                    </p>
                </Link>
                <Link
                    href="/board"
                    class="border-sidebar-border/70 hover:bg-muted dark:border-sidebar-border rounded-xl border p-4"
                >
                    <p class="text-muted-foreground text-sm">Por entregar</p>
                    <p class="mt-1 text-3xl font-semibold">
                        {{ buckets?.to_deliver ?? 0 }}
                    </p>
                </Link>
            </div>

            <div class="grid gap-3 sm:grid-cols-2">
                <div
                    class="border-sidebar-border/70 dark:border-sidebar-border rounded-xl border p-4"
                >
                    <p class="text-muted-foreground text-sm">Entregados hoy</p>
                    <p class="mt-1 text-3xl font-semibold">
                        {{ deliveredToday ?? 0 }}
                    </p>
                </div>
                <div
                    class="border-sidebar-border/70 dark:border-sidebar-border rounded-xl border p-4"
                >
                    <p class="text-muted-foreground text-sm">
                        Efectivo cobrado hoy
                    </p>
                    <p class="mt-1 text-3xl font-semibold">
                        {{ money(cashCollectedToday) }}
                    </p>
                    <p class="text-muted-foreground mt-1 text-xs">
                        Según lo que el administrador ya registró como cobrado.
                    </p>
                </div>
            </div>

            <p
                v-if="(awaitingPayment ?? 0) > 0"
                class="text-muted-foreground text-sm"
            >
                Tienes {{ awaitingPayment }} entrega(s) sin cobro registrado
                todavía.
            </p>
        </template>
    </div>
</template>
