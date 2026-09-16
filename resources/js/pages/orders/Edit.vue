<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import InputError from '@/components/InputError.vue';
import Select from '@/components/Select.vue';
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
    address_id: string;
    courier_id: string;
    shopping_list: string;
    commission: number | null;
    notes: string;
    items: ItemInput[];
}>({
    address_id: String(props.order.address_id),
    courier_id: props.order.courier_id ? String(props.order.courier_id) : '',
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
    form.items.reduce(
        (sum, item) => sum + (item.unit_price ?? 0) * (item.quantity ?? 0),
        0,
    ),
);

function submit(): void {
    form.put(`/orders/${props.order.id}`);
}

const fieldClass =
    'h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50';
const areaClass =
    'w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50';
</script>

<template>
    <Head :title="`Editar pedido #${order.id}`" />

    <div class="mx-auto flex w-full max-w-3xl flex-col gap-6 p-4">
        <h1 class="text-xl font-semibold">Editar pedido #{{ order.id }}</h1>

        <form class="space-y-6" @submit.prevent="submit">
            <div class="grid items-start gap-4 sm:grid-cols-2">
                <div class="grid gap-2">
                    <Label>Cliente</Label>
                    <p
                        class="border-input text-muted-foreground flex h-9 items-center rounded-md border bg-transparent px-3 text-sm"
                    >
                        {{ order.client.name }}
                    </p>
                </div>

                <div class="grid gap-2">
                    <Label for="address_id">Dirección de entrega</Label>
                    <Select id="address_id" v-model="form.address_id">
                        <option
                            v-for="address in addresses"
                            :key="address.id"
                            :value="address.id"
                        >
                            {{ address.label ? `${address.label} — ` : ''
                            }}{{ address.street }}
                        </option>
                    </Select>
                    <InputError :message="form.errors.address_id" />
                </div>
            </div>

            <div class="grid gap-2">
                <Label for="shopping_list">Lista de compra</Label>
                <textarea
                    id="shopping_list"
                    v-model="form.shopping_list"
                    rows="4"
                    :class="areaClass"
                    required
                ></textarea>
                <InputError :message="form.errors.shopping_list" />
            </div>

            <div class="space-y-3">
                <div class="flex items-center justify-between">
                    <Label>Desglose de productos</Label>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        @click="addItem"
                    >
                        Agregar producto
                    </Button>
                </div>

                <div
                    v-for="(item, index) in form.items"
                    :key="index"
                    class="grid grid-cols-12 items-end gap-2"
                >
                    <div class="col-span-6 grid gap-1">
                        <Label :for="`item-name-${index}`" class="text-xs"
                            >Producto</Label
                        >
                        <Input
                            :id="`item-name-${index}`"
                            v-model="item.name"
                            placeholder="Nombre"
                        />
                    </div>
                    <div class="col-span-2 grid gap-1">
                        <Label :for="`item-qty-${index}`" class="text-xs"
                            >Cant.</Label
                        >
                        <input
                            :id="`item-qty-${index}`"
                            v-model.number="item.quantity"
                            type="number"
                            min="0.01"
                            step="0.01"
                            :class="fieldClass"
                        />
                    </div>
                    <div class="col-span-3 grid gap-1">
                        <Label :for="`item-price-${index}`" class="text-xs"
                            >Precio</Label
                        >
                        <input
                            :id="`item-price-${index}`"
                            v-model.number="item.unit_price"
                            type="number"
                            min="0"
                            step="0.01"
                            :class="fieldClass"
                        />
                    </div>
                    <div class="col-span-1">
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            @click="removeItem(index)"
                            >✕</Button
                        >
                    </div>
                </div>

                <p
                    v-if="form.items.length > 0"
                    class="text-muted-foreground text-right text-sm"
                >
                    Subtotal estimado:
                    <span class="font-medium">{{
                        money(estimatedSubtotal)
                    }}</span>
                </p>
            </div>

            <div class="grid items-start gap-4 sm:grid-cols-2">
                <div class="grid gap-2">
                    <Label for="commission">Comisión</Label>
                    <input
                        id="commission"
                        v-model.number="form.commission"
                        type="number"
                        min="0"
                        step="0.01"
                        placeholder="0.00"
                        :class="fieldClass"
                    />
                    <InputError :message="form.errors.commission" />
                </div>

                <div class="grid gap-2">
                    <Label for="courier_id">Repartidor</Label>
                    <Select id="courier_id" v-model="form.courier_id">
                        <option value="">Sin asignar</option>
                        <option
                            v-for="courier in couriers"
                            :key="courier.id"
                            :value="courier.id"
                        >
                            {{ courier.name }}
                        </option>
                    </Select>
                    <InputError :message="form.errors.courier_id" />
                </div>
            </div>

            <div class="grid gap-2">
                <Label for="notes">Notas</Label>
                <textarea
                    id="notes"
                    v-model="form.notes"
                    rows="2"
                    :class="areaClass"
                ></textarea>
                <InputError :message="form.errors.notes" />
            </div>

            <div class="flex items-center gap-3">
                <Button type="submit" :disabled="form.processing"
                    >Guardar cambios</Button
                >
                <Link
                    :href="`/orders/${order.id}`"
                    class="text-muted-foreground text-sm"
                    >Cancelar</Link
                >
            </div>
        </form>
    </div>
</template>
