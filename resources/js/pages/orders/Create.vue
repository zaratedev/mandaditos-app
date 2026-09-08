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
    client_id: number;
    label: string | null;
    street: string;
    neighborhood: string | null;
    city: string | null;
}

interface Client {
    id: number;
    name: string;
    phone: string | null;
    addresses: Address[];
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

const props = defineProps<{
    clients: Client[];
    couriers: Courier[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Pedidos', href: '/orders' },
            { title: 'Nuevo pedido', href: '/orders/create' },
        ],
    },
});

const form = useForm<{
    client_id: string;
    address_id: string;
    courier_id: string;
    shopping_list: string;
    commission: number | null;
    notes: string;
    items: ItemInput[];
}>({
    client_id: '',
    address_id: '',
    courier_id: '',
    shopping_list: '',
    commission: null,
    notes: '',
    items: [],
});

const selectedClient = computed<Client | undefined>(() =>
    props.clients.find((client) => client.id === Number(form.client_id)),
);

const addresses = computed<Address[]>(() => selectedClient.value?.addresses ?? []);

function onClientChange(): void {
    form.address_id = addresses.value.length === 1 ? String(addresses.value[0].id) : '';
}

function addItem(): void {
    form.items.push({ name: '', quantity: 1, unit_price: null });
}

function removeItem(index: number): void {
    form.items.splice(index, 1);
}

const estimatedSubtotal = computed<number>(() =>
    form.items.reduce((sum, item) => {
        const price = item.unit_price ?? 0;
        const quantity = item.quantity ?? 0;
        return sum + price * quantity;
    }, 0),
);

function submit(): void {
    form.post('/orders');
}

const fieldClass =
    'h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50';
const areaClass =
    'w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50';
</script>

<template>
    <Head title="Nuevo pedido" />

    <div class="mx-auto flex w-full max-w-3xl flex-col gap-6 p-4">
        <h1 class="text-xl font-semibold">Nuevo pedido</h1>

        <form class="space-y-6" @submit.prevent="submit">
            <div class="grid items-start gap-4 sm:grid-cols-2">
                <div class="grid gap-2">
                    <Label for="client_id">Cliente</Label>
                    <Select id="client_id" v-model="form.client_id" @change="onClientChange">
                        <option value="" disabled>Selecciona un cliente</option>
                        <option v-for="client in clients" :key="client.id" :value="client.id">
                            {{ client.name }}
                        </option>
                    </Select>
                    <InputError :message="form.errors.client_id" />
                    <Link href="/clients/create" class="text-xs text-muted-foreground underline">
                        ¿Cliente nuevo? Regístralo aquí
                    </Link>
                </div>

                <div class="grid gap-2">
                    <Label for="address_id">Dirección de entrega</Label>
                    <Select id="address_id" v-model="form.address_id" :disabled="!selectedClient">
                        <option value="" disabled>Selecciona una dirección</option>
                        <option v-for="address in addresses" :key="address.id" :value="address.id">
                            {{ address.label ? `${address.label} — ` : '' }}{{ address.street }}
                        </option>
                    </Select>
                    <InputError :message="form.errors.address_id" />
                </div>
            </div>

            <div class="grid gap-2">
                <Label for="shopping_list">Lista de compra (tal cual la mandó el cliente)</Label>
                <textarea
                    id="shopping_list"
                    v-model="form.shopping_list"
                    rows="4"
                    :class="areaClass"
                    placeholder="1 kg de tortillas, 2 litros de leche, ..."
                    required
                ></textarea>
                <InputError :message="form.errors.shopping_list" />
            </div>

            <div class="space-y-3">
                <div class="flex items-center justify-between">
                    <Label>Desglose de productos (opcional)</Label>
                    <Button type="button" variant="outline" size="sm" @click="addItem">
                        Agregar producto
                    </Button>
                </div>

                <div
                    v-for="(item, index) in form.items"
                    :key="index"
                    class="grid grid-cols-12 items-end gap-2"
                >
                    <div class="col-span-6 grid gap-1">
                        <Label :for="`item-name-${index}`" class="text-xs">Producto</Label>
                        <Input :id="`item-name-${index}`" v-model="item.name" placeholder="Nombre" />
                    </div>
                    <div class="col-span-2 grid gap-1">
                        <Label :for="`item-qty-${index}`" class="text-xs">Cant.</Label>
                        <input :id="`item-qty-${index}`" v-model.number="item.quantity" type="number" min="0.01" step="0.01" :class="fieldClass" />
                    </div>
                    <div class="col-span-3 grid gap-1">
                        <Label :for="`item-price-${index}`" class="text-xs">Precio</Label>
                        <input :id="`item-price-${index}`" v-model.number="item.unit_price" type="number" min="0" step="0.01" :class="fieldClass" />
                    </div>
                    <div class="col-span-1">
                        <Button type="button" variant="ghost" size="sm" @click="removeItem(index)">✕</Button>
                    </div>
                </div>

                <p v-if="form.items.length > 0" class="text-right text-sm text-muted-foreground">
                    Subtotal estimado: <span class="font-medium">{{ money(estimatedSubtotal) }}</span>
                </p>
            </div>

            <div class="grid items-start gap-4 sm:grid-cols-2">
                <div class="grid gap-2">
                    <Label for="commission">Comisión</Label>
                    <input id="commission" v-model.number="form.commission" type="number" min="0" step="0.01" placeholder="0.00" :class="fieldClass" />
                    <InputError :message="form.errors.commission" />
                </div>

                <div class="grid gap-2">
                    <Label for="courier_id">Repartidor (opcional)</Label>
                    <Select id="courier_id" v-model="form.courier_id">
                        <option value="">Sin asignar</option>
                        <option v-for="courier in couriers" :key="courier.id" :value="courier.id">
                            {{ courier.name }}
                        </option>
                    </Select>
                    <InputError :message="form.errors.courier_id" />
                </div>
            </div>

            <div class="grid gap-2">
                <Label for="notes">Notas (opcional)</Label>
                <textarea id="notes" v-model="form.notes" rows="2" :class="areaClass"></textarea>
                <InputError :message="form.errors.notes" />
            </div>

            <div class="flex items-center gap-3">
                <Button type="submit" :disabled="form.processing">Guardar pedido</Button>
                <Link href="/orders" class="text-sm text-muted-foreground">Cancelar</Link>
            </div>
        </form>
    </div>
</template>
