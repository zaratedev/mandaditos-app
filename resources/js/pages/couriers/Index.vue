<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { reactive } from 'vue';
import Select from '@/components/Select.vue';

interface CourierRow {
    id: number;
    name: string;
    email: string;
    is_active: boolean;
    orders_count: number;
    open_orders_count: number;
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
    status: string;
}

const props = defineProps<{
    couriers: Paginator<CourierRow>;
    filters: Filters;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Repartidores', href: '/couriers' }],
    },
});

const form = reactive<Filters>({ ...props.filters });

function apply(): void {
    const params = Object.fromEntries(
        Object.entries(form).filter(([, value]) => value !== ''),
    );

    router.get('/couriers', params, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

function clear(): void {
    (Object.keys(form) as (keyof Filters)[]).forEach((key) => {
        form[key] = '';
    });

    router.get('/couriers', {}, { preserveState: true, replace: true });
}

function toggleActive(courier: CourierRow): void {
    const action = courier.is_active ? 'deactivate' : 'activate';

    if (courier.is_active && courier.open_orders_count > 0) {
        const confirmed = window.confirm(
            `${courier.name} tiene ${courier.open_orders_count} pedido(s) en curso. ` +
                'Al desactivarlo ya no podrá entrar ni recibir pedidos nuevos, y tendrás que reasignar esos pedidos. ¿Continuar?',
        );

        if (!confirmed) {
            return;
        }
    }

    router.post(
        `/couriers/${courier.id}/${action}`,
        {},
        { preserveScroll: true },
    );
}

const fieldClass =
    'h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50';
</script>

<template>
    <Head title="Repartidores" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-xl font-semibold">Repartidores</h1>
            <Link
                href="/couriers/create"
                class="bg-primary text-primary-foreground rounded-lg px-4 py-2 text-sm font-medium hover:opacity-90"
            >
                Nuevo repartidor
            </Link>
        </div>

        <form
            class="border-sidebar-border/70 dark:border-sidebar-border rounded-xl border p-4"
            @submit.prevent="apply"
        >
            <div class="grid items-end gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <div class="grid gap-1 sm:col-span-2">
                    <label for="f-search" class="text-muted-foreground text-xs"
                        >Buscar (nombre o correo)</label
                    >
                    <input
                        id="f-search"
                        v-model="form.search"
                        type="search"
                        placeholder="Ej. Luis, luis@..."
                        :class="fieldClass"
                    />
                </div>

                <div class="grid gap-1">
                    <label for="f-status" class="text-muted-foreground text-xs"
                        >Estado</label
                    >
                    <Select id="f-status" v-model="form.status">
                        <option value="">Todos</option>
                        <option value="active">Activos</option>
                        <option value="inactive">Inactivos</option>
                    </Select>
                </div>

                <div
                    class="flex items-center gap-2 sm:col-span-2 lg:col-span-4"
                >
                    <button
                        type="submit"
                        class="bg-primary text-primary-foreground rounded-lg px-4 py-2 text-sm font-medium hover:opacity-90"
                    >
                        Filtrar
                    </button>
                    <button
                        type="button"
                        class="border-sidebar-border/70 hover:bg-muted dark:border-sidebar-border rounded-lg border px-4 py-2 text-sm font-medium"
                        @click="clear"
                    >
                        Limpiar
                    </button>
                </div>
            </div>
        </form>

        <p class="text-muted-foreground text-sm">
            {{ couriers.total }} repartidor(es)
        </p>

        <div
            class="border-sidebar-border/70 dark:border-sidebar-border overflow-x-auto rounded-xl border"
        >
            <table class="w-full text-sm">
                <thead>
                    <tr
                        class="border-sidebar-border/70 text-muted-foreground dark:border-sidebar-border border-b text-left"
                    >
                        <th class="px-4 py-3 font-medium">Nombre</th>
                        <th class="px-4 py-3 font-medium">Correo</th>
                        <th class="px-4 py-3 font-medium">Estado</th>
                        <th class="px-4 py-3 text-right font-medium">
                            En curso
                        </th>
                        <th class="px-4 py-3 text-right font-medium">
                            Pedidos
                        </th>
                        <th class="px-4 py-3 text-right font-medium">
                            Acciones
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="courier in couriers.data"
                        :key="courier.id"
                        class="border-sidebar-border/40 dark:border-sidebar-border/60 border-b"
                    >
                        <td class="px-4 py-3 font-medium">
                            {{ courier.name }}
                        </td>
                        <td class="text-muted-foreground px-4 py-3">
                            {{ courier.email }}
                        </td>
                        <td class="px-4 py-3">
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
                        </td>
                        <td class="px-4 py-3 text-right">
                            {{ courier.open_orders_count }}
                        </td>
                        <td class="px-4 py-3 text-right">
                            {{ courier.orders_count }}
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-end gap-3">
                                <Link
                                    :href="`/couriers/${courier.id}/edit`"
                                    class="text-primary text-sm hover:underline"
                                >
                                    Editar
                                </Link>
                                <button
                                    type="button"
                                    class="text-muted-foreground text-sm hover:underline"
                                    @click="toggleActive(courier)"
                                >
                                    {{
                                        courier.is_active
                                            ? 'Desactivar'
                                            : 'Activar'
                                    }}
                                </button>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="couriers.data.length === 0">
                        <td
                            colspan="6"
                            class="text-muted-foreground px-4 py-8 text-center"
                        >
                            No hay repartidores con estos filtros.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-if="couriers.links.length > 3" class="flex flex-wrap gap-1">
            <template v-for="(link, index) in couriers.links" :key="index">
                <Link
                    v-if="link.url"
                    :href="link.url"
                    class="border-sidebar-border/70 dark:border-sidebar-border rounded-md border px-3 py-1.5 text-sm"
                    :class="{
                        'bg-primary text-primary-foreground': link.active,
                    }"
                    v-html="link.label"
                />
                <span
                    v-else
                    class="border-sidebar-border/40 text-muted-foreground rounded-md border px-3 py-1.5 text-sm"
                    v-html="link.label"
                />
            </template>
        </div>
    </div>
</template>
