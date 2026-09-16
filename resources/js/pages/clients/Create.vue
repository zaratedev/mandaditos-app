<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

const form = useForm<{
    name: string;
    phone: string;
    notes: string;
    address: {
        label: string;
        street: string;
        neighborhood: string;
        city: string;
        landmark: string;
    };
}>({
    name: '',
    phone: '',
    notes: '',
    address: {
        label: '',
        street: '',
        neighborhood: '',
        city: '',
        landmark: '',
    },
});

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Clientes', href: '/clients' },
            { title: 'Nuevo cliente', href: '/clients/create' },
        ],
    },
});

const inputClass =
    'rounded-lg border border-sidebar-border/70 bg-transparent px-3 py-2 text-sm dark:border-sidebar-border';

function submit(): void {
    form.post('/clients');
}
</script>

<template>
    <Head title="Nuevo cliente" />

    <div class="mx-auto flex w-full max-w-2xl flex-col gap-6 p-4">
        <h1 class="text-xl font-semibold">Nuevo cliente</h1>

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

            <div
                class="border-sidebar-border/70 dark:border-sidebar-border space-y-4 rounded-xl border p-4"
            >
                <h2 class="text-base font-semibold">Dirección</h2>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="label">Etiqueta (opcional)</Label>
                        <Input
                            id="label"
                            v-model="form.address.label"
                            placeholder="Casa, Trabajo..."
                        />
                        <InputError :message="form.errors['address.label']" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="street">Calle y número</Label>
                        <Input
                            id="street"
                            v-model="form.address.street"
                            required
                        />
                        <InputError :message="form.errors['address.street']" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="neighborhood">Colonia</Label>
                        <Input
                            id="neighborhood"
                            v-model="form.address.neighborhood"
                        />
                        <InputError
                            :message="form.errors['address.neighborhood']"
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label for="city">Ciudad</Label>
                        <Input id="city" v-model="form.address.city" />
                        <InputError :message="form.errors['address.city']" />
                    </div>
                    <div class="grid gap-2 sm:col-span-2">
                        <Label for="landmark">Referencias para llegar</Label>
                        <Input id="landmark" v-model="form.address.landmark" />
                        <InputError
                            :message="form.errors['address.landmark']"
                        />
                    </div>
                </div>
            </div>

            <div class="flex items-center gap-3">
                <Button type="submit" :disabled="form.processing"
                    >Guardar cliente</Button
                >
                <Link href="/clients" class="text-muted-foreground text-sm"
                    >Cancelar</Link
                >
            </div>
        </form>
    </div>
</template>
