<script setup lang="ts">
import type { HTMLAttributes } from 'vue';

defineOptions({
    inheritAttrs: false,
});

type Props = {
    className?: HTMLAttributes['class'];
};

defineProps<Props>();
</script>

<template>
    <svg
        xmlns="http://www.w3.org/2000/svg"
        viewBox="0 0 48 48"
        :class="className"
        v-bind="$attrs"
        stroke="currentColor"
        stroke-width="3"
        stroke-linecap="round"
        stroke-linejoin="round"
    >
        <!-- wheels -->
        <circle cx="13" cy="35" r="6" fill="none" />
        <circle cx="35" cy="35" r="6" fill="none" />
        <!-- motorcycle frame -->
        <path d="M13 35 L21 30 L30 30 L35 35" fill="none" />
        <!-- steering column + handlebar -->
        <path d="M30 30 L33 22" fill="none" />
        <path d="M30 21 L37 24" fill="none" />
        <!-- rider: helmet, torso, back and leg -->
        <circle cx="20" cy="14" r="4" fill="none" />
        <path d="M22 17 L32 23" fill="none" />
        <path d="M22 17 L22 30" fill="none" />
        <path d="M22 30 L25 36" fill="none" />
    </svg>
</template>
