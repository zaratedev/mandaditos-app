<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { onMounted, ref } from 'vue';
import { Button } from '@/components/ui/button';

const page = usePage();

const supported = ref(false);
const subscribed = ref(false);
const busy = ref(false);
const denied = ref(false);

function getCsrfToken(): string {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
}

function urlBase64ToUint8Array(base64String: string): Uint8Array {
    const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
    const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
    const raw = atob(base64);
    const output = new Uint8Array(raw.length);

    for (let i = 0; i < raw.length; i += 1) {
        output[i] = raw.charCodeAt(i);
    }

    return output;
}

async function currentSubscription(): Promise<PushSubscription | null> {
    const registration = await navigator.serviceWorker.ready;

    return registration.pushManager.getSubscription();
}

onMounted(async () => {
    supported.value =
        'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window;

    if (!supported.value) {
        return;
    }

    denied.value = Notification.permission === 'denied';

    try {
        subscribed.value = (await currentSubscription()) !== null;
    } catch {
        subscribed.value = false;
    }
});

async function enable(): Promise<void> {
    if (busy.value) {
        return;
    }

    busy.value = true;

    try {
        const permission = await Notification.requestPermission();

        if (permission !== 'granted') {
            denied.value = permission === 'denied';
            return;
        }

        const registration = await navigator.serviceWorker.ready;
        const key = (page.props.vapidPublicKey as string | null) ?? '';

        const subscription = await registration.pushManager.subscribe({
            userVisibleOnly: true,
            applicationServerKey: urlBase64ToUint8Array(key) as BufferSource,
        });

        await fetch('/push-subscriptions', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-XSRF-TOKEN': getCsrfToken(),
            },
            body: JSON.stringify(subscription.toJSON()),
        });

        subscribed.value = true;
    } finally {
        busy.value = false;
    }
}

async function disable(): Promise<void> {
    if (busy.value) {
        return;
    }

    busy.value = true;

    try {
        const subscription = await currentSubscription();

        if (subscription) {
            await fetch('/push-subscriptions', {
                method: 'DELETE',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-XSRF-TOKEN': getCsrfToken(),
                },
                body: JSON.stringify({ endpoint: subscription.endpoint }),
            });

            await subscription.unsubscribe();
        }

        subscribed.value = false;
    } finally {
        busy.value = false;
    }
}
</script>

<template>
    <div class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <p class="text-sm font-medium">Notificaciones push</p>
                <p class="text-xs text-muted-foreground">
                    Recibe avisos en este dispositivo aunque no tengas la app abierta.
                </p>
            </div>

            <p v-if="!supported" class="text-xs text-muted-foreground">
                Este navegador no las soporta.
            </p>
            <p v-else-if="denied" class="text-xs text-muted-foreground">
                Bloqueadas en el navegador.
            </p>
            <Button v-else-if="subscribed" variant="outline" size="sm" :disabled="busy" @click="disable">
                Desactivar
            </Button>
            <Button v-else size="sm" :disabled="busy" @click="enable">Activar</Button>
        </div>
    </div>
</template>
