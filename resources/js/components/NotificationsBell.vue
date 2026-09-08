<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { Bell } from '@lucide/vue';
import { computed } from 'vue';

const page = usePage();
const count = computed<number>(() => (page.props.unreadNotifications as number) ?? 0);
</script>

<template>
    <Link
        href="/notifications"
        class="relative inline-flex h-9 w-9 items-center justify-center rounded-lg hover:bg-muted"
        aria-label="Notificaciones"
    >
        <Bell class="h-5 w-5" />
        <span
            v-if="count > 0"
            class="absolute -right-0.5 -top-0.5 inline-flex min-w-4 items-center justify-center rounded-full bg-red-500 px-1 text-[10px] font-medium text-white"
        >
            {{ count > 9 ? '9+' : count }}
        </span>
    </Link>
</template>
