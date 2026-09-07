<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { money } from '@/lib/format';

interface Address {
    id: number;
    label: string | null;
    street: string;
    neighborhood: string | null;
    city: string | null;
}

interface Courier {
    id: number;
    name: string;
}

interface ItemInput {
    name: string;
    quantity: number | null;
    unit_price: number | null;
}

interface OrderData {
    id: number;
    client: { id: number; name: string };
    address_id: number;
    courier_id: number | null;
    shopping_list: string;
    commission: string | null;
    notes: string | null;
    items: { name: string; quantity: string; unit_price: string | null }[];
}

const props = defineProps<{
    order: OrderData;
    addresses: Address[];
    couriers: Courier[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Pedidos', href: '/orders' },
            { title: 'Editar pedido', href: '/orders' },
        ],
    },
});

const form = useForm<{
    address_id: number | null;
    courier_id: number | null;
    shopping_list: string;
    commission: number | null;
    notes: string;
    items: ItemInput[];
}>({
    address_id: props.order.address_id,
    courier_id: props.order.courier_id,
    shopping_list: props.order.shopping_list,
    commission: props.order.commission ? Number(props.order.commission) : null,
    notes: props.order.notes ?? '',
    items: props.order.items.map((item) => ({
        name: item.name,
        quantity: item.quantity ? Number(item.quantity) : 1,
        unit_price: item.unit_price ? Number(item.unit_price) : null,
    })),
});

function addItem(): void {
    form.items.push({ name: '', quantity: 1, unit_price: null });
}

function removeItem(index: number): void {
    form.items.splice(index, 1);
}

const estimatedSubtotal = computed<number>(() =>
    form.items.reduce((sum, item) => sum + (item.unit_price ?? 0) * (item.quantity ?? 0), 0),
);

function submit(): void {
    form.put(`/orders/${props.order.id}`);
}

const selectClass =
    'rounded-lg border border-sidebar-border/70 bg-transparent px-3 py-2 text-sm dark:border-sidebar-border';
</script>

<template>
    <Head :title="`Editar pedido #${order.id}`" />

    <div class="mx-auto flex w-full max-w-3xl flex-col gap-6 p-4">
        <h1 class="text-xl font-semibold">Editar pedido #{{ order.id }}</h1>

        <form class="space-y-6" @submit.prevent="submit">
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="grid gap-2">
                    <Label>Cliente</Label>
                    <p class="rounded-lg border border-sidebar-border/70 px-3 py-2 text-sm text-muted-foreground dark:border-sidebar-border">
                        {{ order.client.name }}
                    </p>
                </div>

                <div class="grid gap-2">
                    <Label for="address_id">Dirección de entrega</Label>
                    <select id="address_id" v-model="form.address_id" :class="selectClass" required>
                        <option v-for="address in addresses" :key="address.id" :value="address.id">
                            {{ address.label ? `${address.label} — ` : '' }}{{ address.street }}
                        </option>
                    </select>
                    <InputError :message="form.errors.address_id" />
                </div>
            </div>

            <div class="grid gap-2">
                <Label for="shopping_list">Lista de compra</Label>
                <textarea
                    id="shopping_list"
                    v-model="form.shopping_list"
                    rows="4"
                    :class="selectClass"
                    required
                ></textarea>
                <InputError :message="form.errors.shopping_list" />
            </div>

            <div class="space-y-3">
                <div class="flex items-center justify-between">
                    <Label>Desglose de productos</Label>
                    <Button type="button" variant="outline" size="sm" @click="addItem">
                        Agregar producto
                    </Button>
                </div>

                <div v-for="(item, index) in form.items" :key="index" class="grid grid-cols-12 items-end gap-2">
                    <div class="col-span-6 grid gap-1">
                        <Label :for="`item-name-${index}`" class="text-xs">Producto</Label>
                        <Input :id="`item-name-${index}`" v-model="item.name" placeholder="Nombre" />
                    </div>
                    <div class="col-span-2 grid gap-1">
                        <Label :for="`item-qty-${index}`" class="text-xs">Cant.</Label>
                        <input :id="`item-qty-${index}`" v-model.number="item.quantity" type="number" min="0.01" step="0.01" class="w-full" :class="selectClass" />
                    </div>
                    <div class="col-span-3 grid gap-1">
                        <Label :for="`item-price-${index}`" class="text-xs">Precio</Label>
                        <input :id="`item-price-${index}`" v-model.number="item.unit_price" type="number" min="0" step="0.01" class="w-full" :class="selectClass" />
                    </div>
                    <div class="col-span-1">
                        <Button type="button" variant="ghost" size="sm" @click="removeItem(index)">✕</Button>
                    </div>
                </div>

                <p v-if="form.items.length > 0" class="text-right text-sm text-muted-foreground">
                    Subtotal estimado: <span class="font-medium">{{ money(estimatedSubtotal) }}</span>
                </p>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div class="grid gap-2">
                    <Label for="commission">Comisión</Label>
                    <input id="commission" v-model.number="form.commission" type="number" min="0" step="0.01" placeholder="0.00" class="w-full" :class="selectClass" />
                    <InputError :message="form.errors.commission" />
                </div>

                <div class="grid gap-2">
                    <Label for="courier_id">Repartidor</Label>
                    <select id="courier_id" v-model="form.courier_id" :class="selectClass">
                        <option :value="null">Sin asignar</option>
                        <option v-for="courier in couriers" :key="courier.id" :value="courier.id">
                            {{ courier.name }}
                        </option>
                    </select>
                    <InputError :message="form.errors.courier_id" />
                </div>
            </div>

            <div class="grid gap-2">
                <Label for="notes">Notas</Label>
                <textarea id="notes" v-model="form.notes" rows="2" :class="selectClass"></textarea>
                <InputError :message="form.errors.notes" />
            </div>

            <div class="flex items-center gap-3">
                <Button type="submit" :disabled="form.processing">Guardar cambios</Button>
                <Link :href="`/orders/${order.id}`" class="text-sm text-muted-foreground">Cancelar</Link>
            </div>
        </form>
    </div>
</template>
