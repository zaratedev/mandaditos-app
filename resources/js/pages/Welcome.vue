<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { dashboard, login } from '@/routes';

const page = usePage();
</script>

<template>
    <Head title="Mandaditos app" />

    <div class="flex min-h-screen flex-col bg-background text-foreground">
        <header class="flex items-center justify-between p-6">
            <div class="flex items-center gap-2">
                <div class="flex size-9 items-center justify-center rounded-lg bg-primary text-primary-foreground">
                    <AppLogoIcon class="size-6" />
                </div>
                <span class="font-semibold">Mandaditos app</span>
            </div>
            <Link
                v-if="page.props.auth.user"
                :href="dashboard()"
                class="rounded-lg border border-sidebar-border/70 px-4 py-2 text-sm font-medium hover:bg-muted dark:border-sidebar-border"
            >
                Ir al panel
            </Link>
            <Link
                v-else
                :href="login()"
                class="rounded-lg border border-sidebar-border/70 px-4 py-2 text-sm font-medium hover:bg-muted dark:border-sidebar-border"
            >
                Iniciar sesión
            </Link>
        </header>

        <main class="flex flex-1 flex-col items-center justify-center gap-8 p-6 text-center">
            <div class="flex size-20 items-center justify-center rounded-2xl bg-primary text-primary-foreground shadow-sm">
                <AppLogoIcon class="size-12" />
            </div>

            <div class="flex max-w-xl flex-col gap-3">
                <h1 class="text-4xl font-bold tracking-tight">Mandaditos app</h1>
                <p class="text-lg text-muted-foreground">
                    Administra tus pedidos, repartidores, clientes y cobros — con corte
                    del día, reportes y notificaciones, todo en un solo lugar.
                </p>
            </div>

            <div class="flex flex-wrap items-center justify-center gap-3">
                <Link
                    v-if="page.props.auth.user"
                    :href="dashboard()"
                    class="rounded-lg bg-primary px-6 py-2.5 text-sm font-medium text-primary-foreground hover:opacity-90"
                >
                    Ir al panel
                </Link>
                <Link
                    v-else
                    :href="login()"
                    class="rounded-lg bg-primary px-6 py-2.5 text-sm font-medium text-primary-foreground hover:opacity-90"
                >
                    Iniciar sesión
                </Link>
            </div>
        </main>

        <footer class="p-6 text-center text-xs text-muted-foreground">
            © 2026 Mandaditos app
        </footer>
    </div>
</template>
