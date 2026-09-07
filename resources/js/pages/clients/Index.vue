<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';

interface Address {
    id: number;
    label: string | null;
    street: string;
    neighborhood: string | null;
    city: string | null;
}

interface Client {
    id: number;
    name: string;
    phone: string | null;
    notes: string | null;
    orders_count: number;
    addresses: Address[];
}

defineProps<{
    clients: Client[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Clientes', href: '/clients' }],
    },
});
</script>

<template>
    <Head title="Clientes" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-xl font-semibold">Clientes</h1>
            <Link
                href="/clients/create"
                class="rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:opacity-90"
            >
                Nuevo cliente
            </Link>
        </div>

        <div class="overflow-x-auto rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-sidebar-border/70 text-left text-muted-foreground dark:border-sidebar-border">
                        <th class="px-4 py-3 font-medium">Nombre</th>
                        <th class="px-4 py-3 font-medium">Teléfono</th>
                        <th class="px-4 py-3 font-medium">Dirección principal</th>
                        <th class="px-4 py-3 text-right font-medium">Pedidos</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="client in clients" :key="client.id" class="border-b border-sidebar-border/40 dark:border-sidebar-border/60">
                        <td class="px-4 py-3 font-medium">{{ client.name }}</td>
                        <td class="px-4 py-3">{{ client.phone ?? '—' }}</td>
                        <td class="px-4 py-3 text-muted-foreground">
                            {{ client.addresses[0]?.street ?? 'Sin dirección' }}
                        </td>
                        <td class="px-4 py-3 text-right">{{ client.orders_count }}</td>
                    </tr>
                    <tr v-if="clients.length === 0">
                        <td colspan="4" class="px-4 py-8 text-center text-muted-foreground">
                            Aún no hay clientes registrados.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
