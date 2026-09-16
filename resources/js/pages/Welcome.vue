<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { dashboard, login } from '@/routes';

const page = usePage();
</script>

<template>
    <Head title="Mandaditos app" />

    <div class="bg-background text-foreground flex min-h-screen flex-col">
        <header class="flex items-center justify-between p-6">
            <div class="flex items-center gap-2">
                <div
                    class="bg-primary text-primary-foreground flex size-9 items-center justify-center rounded-lg"
                >
                    <AppLogoIcon class="size-6" />
                </div>
                <span class="font-semibold">Mandaditos app</span>
            </div>
            <Link
                v-if="page.props.auth.user"
                :href="dashboard()"
                class="border-sidebar-border/70 hover:bg-muted dark:border-sidebar-border rounded-lg border px-4 py-2 text-sm font-medium"
            >
                Ir al panel
            </Link>
            <Link
                v-else
                :href="login()"
                class="border-sidebar-border/70 hover:bg-muted dark:border-sidebar-border rounded-lg border px-4 py-2 text-sm font-medium"
            >
                Iniciar sesión
            </Link>
        </header>

        <main
            class="flex flex-1 flex-col items-center justify-center gap-8 p-6 text-center"
        >
            <div
                class="bg-primary text-primary-foreground flex size-20 items-center justify-center rounded-2xl shadow-sm"
            >
                <AppLogoIcon class="size-12" />
            </div>

            <div class="flex max-w-xl flex-col gap-3">
                <h1 class="text-4xl font-bold tracking-tight">
                    Mandaditos app
                </h1>
                <p class="text-muted-foreground text-lg">
                    Administra tus pedidos, repartidores, clientes y cobros —
                    con corte del día, reportes y notificaciones, todo en un
                    solo lugar.
                </p>
            </div>

            <div class="flex flex-wrap items-center justify-center gap-3">
                <Link
                    v-if="page.props.auth.user"
                    :href="dashboard()"
                    class="bg-primary text-primary-foreground rounded-lg px-6 py-2.5 text-sm font-medium hover:opacity-90"
                >
                    Ir al panel
                </Link>
                <Link
                    v-else
                    :href="login()"
                    class="bg-primary text-primary-foreground rounded-lg px-6 py-2.5 text-sm font-medium hover:opacity-90"
                >
                    Iniciar sesión
                </Link>
            </div>
        </main>

        <footer class="text-muted-foreground p-6 text-center text-xs">
            © 2026 Mandaditos app
        </footer>
    </div>
</template>
