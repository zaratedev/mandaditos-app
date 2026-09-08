<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

interface Courier {
    id: number;
    name: string;
    email: string;
    is_active: boolean;
    open_orders_count: number;
}

const props = defineProps<{ courier: Courier }>();

const form = useForm<{
    name: string;
    email: string;
    password: string;
    password_confirmation: string;
}>({
    name: props.courier.name,
    email: props.courier.email,
    password: '',
    password_confirmation: '',
});

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Repartidores', href: '/couriers' },
            { title: 'Editar repartidor', href: '#' },
        ],
    },
});

function submit(): void {
    form.put(`/couriers/${props.courier.id}`, {
        onError: () => form.reset('password', 'password_confirmation'),
    });
}

function toggleActive(): void {
    const action = props.courier.is_active ? 'deactivate' : 'activate';

    if (props.courier.is_active && props.courier.open_orders_count > 0) {
        const confirmed = window.confirm(
            `${props.courier.name} tiene ${props.courier.open_orders_count} pedido(s) en curso. ` +
                'Al desactivarlo ya no podrá entrar ni recibir pedidos nuevos, y tendrás que reasignar esos pedidos. ¿Continuar?',
        );

        if (!confirmed) {
            return;
        }
    }

    router.post(
        `/couriers/${props.courier.id}/${action}`,
        {},
        { preserveScroll: true },
    );
}
</script>

<template>
    <Head title="Editar repartidor" />

    <div class="mx-auto flex w-full max-w-2xl flex-col gap-6 p-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-xl font-semibold">Editar repartidor</h1>
            <span
                class="rounded-full px-2 py-0.5 text-xs font-medium"
                :class="
                    courier.is_active
                        ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300'
                        : 'bg-muted text-muted-foreground'
                "
            >
                {{ courier.is_active ? 'Activo' : 'Inactivo' }}
            </span>
        </div>

        <form class="space-y-6" @submit.prevent="submit">
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="grid gap-2">
                    <Label for="name">Nombre</Label>
                    <Input
                        id="name"
                        v-model="form.name"
                        autocomplete="off"
                        required
                    />
                    <InputError :message="form.errors.name" />
                </div>
                <div class="grid gap-2">
                    <Label for="email">Correo</Label>
                    <Input
                        id="email"
                        v-model="form.email"
                        type="email"
                        autocomplete="off"
                        required
                    />
                    <InputError :message="form.errors.email" />
                </div>
            </div>

            <div
                class="border-sidebar-border/70 dark:border-sidebar-border space-y-4 rounded-xl border p-4"
            >
                <div>
                    <h2 class="text-base font-semibold">Cambiar contraseña</h2>
                    <p class="text-muted-foreground text-sm">
                        Déjala en blanco para conservar la actual. Úsala si el
                        repartidor olvidó su contraseña.
                    </p>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="password">Nueva contraseña</Label>
                        <PasswordInput
                            id="password"
                            v-model="form.password"
                            autocomplete="new-password"
                        />
                        <InputError :message="form.errors.password" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="password_confirmation"
                            >Confirmar contraseña</Label
                        >
                        <PasswordInput
                            id="password_confirmation"
                            v-model="form.password_confirmation"
                            autocomplete="new-password"
                        />
                        <InputError
                            :message="form.errors.password_confirmation"
                        />
                    </div>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-3">
                <Button type="submit" :disabled="form.processing"
                    >Guardar cambios</Button
                >
                <Link href="/couriers" class="text-muted-foreground text-sm"
                    >Cancelar</Link
                >
                <button
                    type="button"
                    class="border-sidebar-border/70 hover:bg-muted dark:border-sidebar-border ml-auto rounded-lg border px-4 py-2 text-sm font-medium"
                    @click="toggleActive"
                >
                    {{ courier.is_active ? 'Desactivar' : 'Activar' }}
                </button>
            </div>
        </form>

        <p class="text-muted-foreground text-sm">
            Los repartidores no se eliminan: al desactivarlos pierden el acceso
            y dejan de recibir pedidos, pero su historial se conserva para los
            reportes.
        </p>
    </div>
</template>
