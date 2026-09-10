<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import Select from '@/components/Select.vue';
import { Button } from '@/components/ui/button';
import { confirm } from '@/lib/confirm';
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
    payment_method: string | null;
    payment_method_label: string | null;
    payment_status: string;
    payment_status_label: string;
    client: { id: number; name: string; phone: string | null };
    address: { id: number; label: string | null; street: string; neighborhood: string | null; city: string | null; landmark: string | null };
    courier: { id: number; name: string } | null;
    creator: { id: number; name: string } | null;
    items: Item[];
    created_at: string | null;
    confirmed_at: string | null;
    purchased_at: string | null;
    delivered_at: string | null;
    paid_at: string | null;
}

interface Option {
    value: string;
    label: string;
}

const props = defineProps<{
    order: Order;
    couriers: { id: number; name: string }[];
    statuses: Option[];
    paymentMethods: Option[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Pedidos', href: '/orders' }],
    },
});

const assignForm = useForm<{ courier_id: string }>({
    courier_id: props.order.courier?.id ? String(props.order.courier.id) : '',
});

const statusForm = useForm<{ status: string }>({
    status: props.order.status,
});

const paymentForm = useForm<{ payment_method: string }>({
    payment_method: props.order.payment_method ?? '',
});

const purchaseForm = useForm<{ items_subtotal: number | null; commission: number | null }>({
    items_subtotal: props.order.items_subtotal ? Number(props.order.items_subtotal) : null,
    commission: props.order.commission ? Number(props.order.commission) : null,
});

const selectClass =
    'rounded-lg border border-sidebar-border/70 bg-transparent px-3 py-2 text-sm dark:border-sidebar-border';

function assign(): void {
    assignForm.post(`/orders/${props.order.id}/assign`, { preserveScroll: true });
}

function changeStatus(): void {
    statusForm.post(`/orders/${props.order.id}/status`, { preserveScroll: true });
}

function registerPayment(): void {
    paymentForm.post(`/orders/${props.order.id}/payment`, { preserveScroll: true });
}

function savePurchase(): void {
    purchaseForm.post(`/orders/${props.order.id}/purchase`, { preserveScroll: true });
}

async function cancelOrder(): Promise<void> {
    const confirmed = await confirm({
        title: `¿Cancelar el pedido #${props.order.id}?`,
        description:
            'Saldrá de los pedidos abiertos y de la lista de pendientes del repartidor.',
        confirmLabel: 'Cancelar el pedido',
        cancelLabel: 'Volver',
        destructive: true,
    });

    if (confirmed) {
        router.post(`/orders/${props.order.id}/cancel`);
    }
}
</script>

<template>
    <Head :title="`Pedido #${order.id}`" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
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

        <div class="flex flex-wrap gap-2">
            <Link
                :href="`/orders/${order.id}/edit`"
                class="rounded-lg border border-sidebar-border/70 px-3 py-1.5 text-sm font-medium hover:bg-muted dark:border-sidebar-border"
            >
                Editar
            </Link>
            <button
                v-if="order.status !== 'cancelled'"
                type="button"
                class="rounded-lg border border-red-300 px-3 py-1.5 text-sm font-medium text-red-600 hover:bg-red-50 dark:border-red-900/60 dark:text-red-400 dark:hover:bg-red-950/40"
                @click="cancelOrder"
            >
                Cancelar pedido
            </button>
        </div>

        <div class="grid gap-6 lg:grid-cols-3">
            <div class="space-y-6 lg:col-span-2">
                <div class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                    <h2 class="mb-2 text-base font-semibold">Lista de compra</h2>
                    <p class="whitespace-pre-line text-sm text-muted-foreground">{{ order.shopping_list }}</p>
                </div>

                <div class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                    <h2 class="mb-3 text-base font-semibold">Productos</h2>
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-sidebar-border/70 text-left text-muted-foreground dark:border-sidebar-border">
                                <th class="py-2 pr-4 font-medium">Producto</th>
                                <th class="py-2 pr-4 text-right font-medium">Cant.</th>
                                <th class="py-2 pr-4 text-right font-medium">Precio</th>
                                <th class="py-2 text-right font-medium">Importe</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="item in order.items" :key="item.id" class="border-b border-sidebar-border/40 dark:border-sidebar-border/60">
                                <td class="py-2 pr-4">{{ item.name }}</td>
                                <td class="py-2 pr-4 text-right">{{ item.quantity }}</td>
                                <td class="py-2 pr-4 text-right">{{ money(item.unit_price) }}</td>
                                <td class="py-2 text-right">{{ money(item.line_total) }}</td>
                            </tr>
                            <tr v-if="order.items.length === 0">
                                <td colspan="4" class="py-4 text-center text-muted-foreground">Sin desglose de productos.</td>
                            </tr>
                        </tbody>
                    </table>

                    <div class="mt-4 space-y-1 text-right text-sm">
                        <p>Subtotal: <span class="font-medium">{{ money(order.items_subtotal) }}</span></p>
                        <p>Comisión: <span class="font-medium">{{ money(order.commission) }}</span></p>
                        <p class="text-base">Total: <span class="font-semibold">{{ money(order.total) }}</span></p>
                    </div>
                </div>

                <div v-if="order.notes" class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                    <h2 class="mb-2 text-base font-semibold">Notas</h2>
                    <p class="text-sm text-muted-foreground">{{ order.notes }}</p>
                </div>
            </div>

            <div class="space-y-6">
                <div class="rounded-xl border border-sidebar-border/70 p-4 text-sm dark:border-sidebar-border">
                    <h2 class="mb-2 text-base font-semibold">Cliente</h2>
                    <p class="font-medium">{{ order.client.name }}</p>
                    <p class="text-muted-foreground">{{ order.client.phone ?? 'Sin teléfono' }}</p>
                    <p class="mt-2 text-muted-foreground">
                        {{ order.address.street }}<template v-if="order.address.neighborhood">, {{ order.address.neighborhood }}</template>
                    </p>
                    <p v-if="order.address.landmark" class="text-muted-foreground">Ref: {{ order.address.landmark }}</p>
                </div>

                <div class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                    <h2 class="mb-3 text-base font-semibold">Repartidor</h2>
                    <div class="grid gap-2">
                        <Select v-model="assignForm.courier_id">
                            <option value="" disabled>Selecciona repartidor</option>
                            <option v-for="courier in couriers" :key="courier.id" :value="courier.id">{{ courier.name }}</option>
                        </Select>
                        <InputError :message="assignForm.errors.courier_id" />
                        <Button size="sm" :disabled="assignForm.processing || !assignForm.courier_id" @click="assign">
                            {{ order.courier ? 'Reasignar' : 'Asignar' }}
                        </Button>
                    </div>
                </div>

                <div class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                    <h2 class="mb-3 text-base font-semibold">Estado</h2>
                    <div class="grid gap-2">
                        <Select v-model="statusForm.status">
                            <option v-for="option in statuses" :key="option.value" :value="option.value">{{ option.label }}</option>
                        </Select>
                        <Button size="sm" :disabled="statusForm.processing" @click="changeStatus">Actualizar estado</Button>
                    </div>
                </div>

                <div class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                    <h2 class="mb-3 text-base font-semibold">Registrar compra</h2>
                    <div class="grid gap-2">
                        <label for="items_subtotal" class="text-sm text-muted-foreground">Total de la compra</label>
                        <input
                            id="items_subtotal"
                            v-model.number="purchaseForm.items_subtotal"
                            type="number"
                            min="0"
                            step="0.01"
                            class="w-full"
                            :class="selectClass"
                        />
                        <InputError :message="purchaseForm.errors.items_subtotal" />

                        <label for="commission_amount" class="text-sm text-muted-foreground">Comisión</label>
                        <input
                            id="commission_amount"
                            v-model.number="purchaseForm.commission"
                            type="number"
                            min="0"
                            step="0.01"
                            class="w-full"
                            :class="selectClass"
                        />
                        <InputError :message="purchaseForm.errors.commission" />

                        <Button size="sm" :disabled="purchaseForm.processing" @click="savePurchase">Guardar montos</Button>
                        <p class="text-xs text-muted-foreground">Total a cobrar = compra + comisión.</p>
                    </div>
                </div>

                <div class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                    <h2 class="mb-3 text-base font-semibold">Cobro</h2>
                    <p v-if="order.payment_status === 'paid'" class="text-sm text-muted-foreground">
                        Pagado con {{ order.payment_method_label }}<template v-if="order.paid_at"> el {{ order.paid_at }}</template>.
                    </p>
                    <div v-else class="grid gap-2">
                        <Select v-model="paymentForm.payment_method">
                            <option value="" disabled>Método de pago</option>
                            <option v-for="method in paymentMethods" :key="method.value" :value="method.value">{{ method.label }}</option>
                        </Select>
                        <InputError :message="paymentForm.errors.payment_method" />
                        <Button size="sm" :disabled="paymentForm.processing || !paymentForm.payment_method" @click="registerPayment">
                            Registrar cobro
                        </Button>
                    </div>
                </div>

                <div class="rounded-xl border border-sidebar-border/70 p-4 text-xs text-muted-foreground dark:border-sidebar-border">
                    <p>Creado: {{ order.created_at ?? '—' }}<template v-if="order.creator"> por {{ order.creator.name }}</template></p>
                    <p v-if="order.confirmed_at">Confirmado: {{ order.confirmed_at }}</p>
                    <p v-if="order.purchased_at">Comprado: {{ order.purchased_at }}</p>
                    <p v-if="order.delivered_at">Entregado: {{ order.delivered_at }}</p>
                    <p v-if="order.paid_at">Pagado: {{ order.paid_at }}</p>
                </div>
            </div>
        </div>
    </div>
</template>
