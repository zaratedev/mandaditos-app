<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

const form = useForm<{
    name: string;
    email: string;
    password: string;
    password_confirmation: string;
}>({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
});

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Repartidores', href: '/couriers' },
            { title: 'Nuevo repartidor', href: '/couriers/create' },
        ],
    },
});

function submit(): void {
    form.post('/couriers', {
        onError: () => form.reset('password', 'password_confirmation'),
    });
}
</script>

<template>
    <Head title="Nuevo repartidor" />

    <div class="mx-auto flex w-full max-w-2xl flex-col gap-6 p-4">
        <h1 class="text-xl font-semibold">Nuevo repartidor</h1>

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
                    <h2 class="text-base font-semibold">Contraseña</h2>
                    <p class="text-muted-foreground text-sm">
                        Tú defines la contraseña con la que entrará el
                        repartidor. Compártesela en persona; él podrá cambiarla
                        después desde su perfil.
                    </p>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="password">Contraseña</Label>
                        <PasswordInput
                            id="password"
                            v-model="form.password"
                            autocomplete="new-password"
                            required
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
                            required
                        />
                        <InputError
                            :message="form.errors.password_confirmation"
                        />
                    </div>
                </div>
            </div>

            <div class="flex items-center gap-3">
                <Button type="submit" :disabled="form.processing"
                    >Guardar repartidor</Button
                >
                <Link href="/couriers" class="text-muted-foreground text-sm"
                    >Cancelar</Link
                >
            </div>
        </form>
    </div>
</template>
