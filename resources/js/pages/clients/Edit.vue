<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { confirm } from '@/lib/confirm';

interface AddressRow {
    id: number | null;
    label: string;
    street: string;
    neighborhood: string;
    city: string;
    landmark: string;
    in_use: boolean;
}

interface Client {
    id: number;
    name: string;
    phone: string | null;
    notes: string | null;
    is_active: boolean;
    orders_count: number;
    addresses: AddressRow[];
}

const props = defineProps<{ client: Client }>();

const form = useForm<{
    name: string;
    phone: string;
    notes: string;
    addresses: AddressRow[];
}>({
    name: props.client.name,
    phone: props.client.phone ?? '',
    notes: props.client.notes ?? '',
    addresses: props.client.addresses.map((address) => ({
        id: address.id,
        label: address.label ?? '',
        street: address.street ?? '',
        neighborhood: address.neighborhood ?? '',
        city: address.city ?? '',
        landmark: address.landmark ?? '',
        in_use: address.in_use,
    })),
});

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Clientes', href: '/clients' },
            { title: 'Editar cliente', href: '#' },
        ],
    },
});

const inputClass =
    'rounded-lg border border-sidebar-border/70 bg-transparent px-3 py-2 text-sm dark:border-sidebar-border';

function addAddress(): void {
    form.addresses.push({
        id: null,
        label: '',
        street: '',
        neighborhood: '',
        city: '',
        landmark: '',
        in_use: false,
    });
}

function removeAddress(index: number): void {
    form.addresses.splice(index, 1);
}

function submit(): void {
    form.put(`/clients/${props.client.id}`);
}

function toggleArchive(): void {
    const action = props.client.is_active ? 'archive' : 'restore';

    router.post(
        `/clients/${props.client.id}/${action}`,
        {},
        { preserveScroll: true },
    );
}

async function destroy(): Promise<void> {
    const confirmed = await confirm({
        title: `¿Eliminar a ${props.client.name}?`,
        description:
            'Se eliminarán también sus direcciones. Esta acción no se puede deshacer.',
        confirmLabel: 'Eliminar',
        destructive: true,
    });

    if (confirmed) {
        router.delete(`/clients/${props.client.id}`);
    }
}
</script>

<template>
    <Head title="Editar cliente" />

    <div class="mx-auto flex w-full max-w-2xl flex-col gap-6 p-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-xl font-semibold">Editar cliente</h1>
            <span
                v-if="!client.is_active"
                class="bg-muted text-muted-foreground rounded-full px-2 py-0.5 text-xs font-medium"
            >
                Archivado
            </span>
        </div>

        <form class="space-y-6" @submit.prevent="submit">
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="grid gap-2">
                    <Label for="name">Nombre</Label>
                    <Input id="name" v-model="form.name" required />
                    <InputError :message="form.errors.name" />
                </div>
                <div class="grid gap-2">
                    <Label for="phone">Teléfono (WhatsApp)</Label>
                    <Input id="phone" v-model="form.phone" />
                    <InputError :message="form.errors.phone" />
                </div>
            </div>

            <div class="grid gap-2">
                <Label for="notes">Notas (opcional)</Label>
                <textarea
                    id="notes"
                    v-model="form.notes"
                    rows="2"
                    :class="inputClass"
                ></textarea>
                <InputError :message="form.errors.notes" />
            </div>

            <div class="space-y-4">
                <div class="flex items-center justify-between">
                    <h2 class="text-base font-semibold">Direcciones</h2>
                    <button
                        type="button"
                        class="border-sidebar-border/70 hover:bg-muted dark:border-sidebar-border rounded-lg border px-3 py-1.5 text-sm font-medium"
                        @click="addAddress"
                    >
                        Agregar dirección
                    </button>
                </div>

                <InputError :message="form.errors.addresses" />

                <div
                    v-for="(address, index) in form.addresses"
                    :key="address.id ?? `nueva-${index}`"
                    class="border-sidebar-border/70 dark:border-sidebar-border space-y-4 rounded-xl border p-4"
                >
                    <div class="flex items-center justify-between">
                        <p class="text-sm font-medium">
                            {{ address.label || `Dirección ${index + 1}` }}
                        </p>
                        <button
                            v-if="!address.in_use && form.addresses.length > 1"
                            type="button"
                            class="text-muted-foreground text-sm hover:underline"
                            @click="removeAddress(index)"
                        >
                            Quitar
                        </button>
                        <span
                            v-else-if="address.in_use"
                            class="text-muted-foreground text-xs"
                        >
                            Usada en pedidos, no se puede quitar
                        </span>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label :for="`label-${index}`"
                                >Etiqueta (opcional)</Label
                            >
                            <Input
                                :id="`label-${index}`"
                                v-model="address.label"
                                placeholder="Casa, Trabajo..."
                            />
                            <InputError
                                :message="
                                    form.errors[`addresses.${index}.label`]
                                "
                            />
                        </div>
                        <div class="grid gap-2">
                            <Label :for="`street-${index}`"
                                >Calle y número</Label
                            >
                            <Input
                                :id="`street-${index}`"
                                v-model="address.street"
                                required
                            />
                            <InputError
                                :message="
                                    form.errors[`addresses.${index}.street`]
                                "
                            />
                        </div>
                        <div class="grid gap-2">
                            <Label :for="`neighborhood-${index}`"
                                >Colonia</Label
                            >
                            <Input
                                :id="`neighborhood-${index}`"
                                v-model="address.neighborhood"
                            />
                            <InputError
                                :message="
                                    form.errors[
                                        `addresses.${index}.neighborhood`
                                    ]
                                "
                            />
                        </div>
                        <div class="grid gap-2">
                            <Label :for="`city-${index}`">Ciudad</Label>
                            <Input
                                :id="`city-${index}`"
                                v-model="address.city"
                            />
                            <InputError
                                :message="
                                    form.errors[`addresses.${index}.city`]
                                "
                            />
                        </div>
                        <div class="grid gap-2 sm:col-span-2">
                            <Label :for="`landmark-${index}`"
                                >Referencias para llegar</Label
                            >
                            <Input
                                :id="`landmark-${index}`"
                                v-model="address.landmark"
                            />
                            <InputError
                                :message="
                                    form.errors[`addresses.${index}.landmark`]
                                "
                            />
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex items-center gap-3">
                <Button type="submit" :disabled="form.processing"
                    >Guardar cambios</Button
                >
                <Link href="/clients" class="text-muted-foreground text-sm"
                    >Cancelar</Link
                >
            </div>
        </form>
    </div>
</template>
