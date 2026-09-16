<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import PushNotificationsToggle from '@/components/PushNotificationsToggle.vue';
import { Button } from '@/components/ui/button';

interface NotificationRow {
    id: string;
    message: string;
    url: string | null;
    read: boolean;
    created_at: string | null;
}

defineProps<{
    notifications: NotificationRow[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Notificaciones', href: '/notifications' }],
    },
});

function open(notification: NotificationRow): void {
    router.post(`/notifications/${notification.id}/read`);
}

function markAll(): void {
    router.post('/notifications/read-all', {}, { preserveScroll: true });
}
</script>

<template>
    <Head title="Notificaciones" />

    <div class="mx-auto flex w-full max-w-2xl flex-col gap-4 p-4">
        <div class="flex items-center justify-between gap-3">
            <h1 class="text-xl font-semibold">Notificaciones</h1>
            <Button variant="outline" size="sm" @click="markAll"
                >Marcar todo como leído</Button
            >
        </div>

        <PushNotificationsToggle />

        <div
            v-if="notifications.length === 0"
            class="border-sidebar-border/70 text-muted-foreground dark:border-sidebar-border rounded-xl border p-8 text-center"
        >
            No tienes notificaciones.
        </div>

        <button
            v-for="notification in notifications"
            :key="notification.id"
            type="button"
            class="border-sidebar-border/70 hover:bg-muted/50 dark:border-sidebar-border flex items-start justify-between gap-3 rounded-xl border p-4 text-left transition"
            :class="notification.read ? 'opacity-60' : ''"
            @click="open(notification)"
        >
            <div>
                <p
                    class="text-sm"
                    :class="notification.read ? '' : 'font-medium'"
                >
                    {{ notification.message }}
                </p>
                <p class="text-muted-foreground mt-1 text-xs">
                    {{ notification.created_at }}
                </p>
            </div>
            <span
                v-if="!notification.read"
                class="mt-1 h-2 w-2 shrink-0 rounded-full bg-red-500"
                aria-hidden="true"
            ></span>
        </button>
    </div>
</template>
