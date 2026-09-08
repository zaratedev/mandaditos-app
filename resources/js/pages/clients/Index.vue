<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { reactive } from 'vue';
import Select from '@/components/Select.vue';

interface ClientRow {
    id: number;
    name: string;
    phone: string | null;
    orders_count: number;
    is_active: boolean;
    address: string | null;
}

interface PageLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface Paginator<T> {
    data: T[];
    links: PageLink[];
    total: number;
}

interface Filters {
    search: string;
    has_orders: string;
    sort: string;
    status: string;
}

const props = defineProps<{
    clients: Paginator<ClientRow>;
    filters: Filters;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Clientes', href: '/clients' }],
    },
});

const form = reactive<Filters>({ ...props.filters });

function apply(): void {
    const params = Object.fromEntries(
        Object.entries(form).filter(([, value]) => value !== ''),
    );

    router.get('/clients', params, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

function clear(): void {
    (Object.keys(form) as (keyof Filters)[]).forEach((key) => {
        form[key] = '';
    });

    router.get('/clients', {}, { preserveState: true, replace: true });
}

function toggleArchive(client: ClientRow): void {
    const action = client.is_active ? 'archive' : 'restore';

    router.post(`/clients/${client.id}/${action}`, {}, { preserveScroll: true });
}

const fieldClass =
    'h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50';
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

        <form
            class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border"
            @submit.prevent="apply"
        >
            <div class="grid items-end gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <div class="grid gap-1 sm:col-span-2 lg:col-span-2">
                    <label for="f-search" class="text-xs text-muted-foreground">Buscar (nombre o teléfono)</label>
                    <input
                        id="f-search"
                        v-model="form.search"
                        type="search"
                        placeholder="Ej. María, 55..."
                        :class="fieldClass"
                    />
                </div>

                <div class="grid gap-1">
                    <label for="f-has-orders" class="text-xs text-muted-foreground">Pedidos</label>
                    <Select id="f-has-orders" v-model="form.has_orders">
                        <option value="">Todos</option>
                        <option value="with">Con pedidos</option>
                        <option value="without">Sin pedidos</option>
                    </Select>
                </div>

                <div class="grid gap-1">
                    <label for="f-status" class="text-xs text-muted-foreground">Estado</label>
                    <Select id="f-status" v-model="form.status">
                        <option value="">Activos</option>
                        <option value="archived">Archivados</option>
                        <option value="all">Todos</option>
                    </Select>
                </div>

                <div class="grid gap-1">
                    <label for="f-sort" class="text-xs text-muted-foreground">Ordenar por</label>
                    <Select id="f-sort" v-model="form.sort">
                        <option value="">Nombre</option>
                        <option value="orders">Más pedidos</option>
                    </Select>
                </div>

                <div class="flex items-center gap-2 sm:col-span-2 lg:col-span-4">
                    <button
                        type="submit"
                        class="rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:opacity-90"
                    >
                        Filtrar
                    </button>
                    <button
                        type="button"
                        class="rounded-lg border border-sidebar-border/70 px-4 py-2 text-sm font-medium hover:bg-muted dark:border-sidebar-border"
                        @click="clear"
                    >
                        Limpiar
                    </button>
                </div>
            </div>
        </form>

        <p class="text-sm text-muted-foreground">{{ clients.total }} cliente(s)</p>

        <div class="overflow-x-auto rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-sidebar-border/70 text-left text-muted-foreground dark:border-sidebar-border">
                        <th class="px-4 py-3 font-medium">Nombre</th>
                        <th class="px-4 py-3 font-medium">Teléfono</th>
                        <th class="px-4 py-3 font-medium">Dirección principal</th>
                        <th class="px-4 py-3 text-right font-medium">Pedidos</th>
                        <th class="px-4 py-3 text-right font-medium">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="client in clients.data" :key="client.id" class="border-b border-sidebar-border/40 dark:border-sidebar-border/60">
                        <td class="px-4 py-3 font-medium">
                            {{ client.name }}
                            <span v-if="!client.is_active" class="ml-2 rounded-full bg-muted px-2 py-0.5 text-xs font-medium text-muted-foreground">
                                Archivado
                            </span>
                        </td>
                        <td class="px-4 py-3">{{ client.phone ?? '—' }}</td>
                        <td class="px-4 py-3 text-muted-foreground">
                            {{ client.address ?? 'Sin dirección' }}
                        </td>
                        <td class="px-4 py-3 text-right">{{ client.orders_count }}</td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-end gap-3">
                                <Link :href="`/clients/${client.id}/edit`" class="text-sm text-primary-strong hover:underline">
                                    Editar
                                </Link>
                                <button
                                    type="button"
                                    class="text-sm text-muted-foreground hover:underline"
                                    @click="toggleArchive(client)"
                                >
                                    {{ client.is_active ? 'Archivar' : 'Restaurar' }}
                                </button>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="clients.data.length === 0">
                        <td colspan="5" class="px-4 py-8 text-center text-muted-foreground">
                            No hay clientes con estos filtros.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-if="clients.links.length > 3" class="flex flex-wrap gap-1">
            <template v-for="(link, index) in clients.links" :key="index">
                <Link
                    v-if="link.url"
                    :href="link.url"
                    class="rounded-md border border-sidebar-border/70 px-3 py-1.5 text-sm dark:border-sidebar-border"
                    :class="{ 'bg-primary text-primary-foreground': link.active }"
                    v-html="link.label"
                />
                <span
                    v-else
                    class="rounded-md border border-sidebar-border/40 px-3 py-1.5 text-sm text-muted-foreground"
                    v-html="link.label"
                />
            </template>
        </div>
    </div>
</template>
