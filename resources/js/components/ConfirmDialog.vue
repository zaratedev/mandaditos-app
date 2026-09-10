<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { onMounted, onUnmounted } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { confirmState, settleConfirm } from '@/lib/confirm';

/**
 * Renders whatever confirm() is asking. One instance lives in the layout, so pages
 * ask their questions from script without carrying dialog state of their own.
 */
function onOpenChange(open: boolean): void {
    // Escape, the close button and a click outside all mean "no".
    if (!open) {
        settleConfirm(false);
    }
}

// This dialog belongs to the layout, which survives page changes: without this a
// question left open during a visit (a browser back, say) would hang over the page
// that comes next. Answering an already answered question does nothing.
let stopListening: (() => void) | null = null;

onMounted(() => {
    stopListening = router.on('start', () => settleConfirm(false));
});

onUnmounted(() => stopListening?.());
</script>

<template>
    <Dialog :open="confirmState.open" @update:open="onOpenChange">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>{{ confirmState.title }}</DialogTitle>
                <DialogDescription>{{
                    confirmState.description
                }}</DialogDescription>
            </DialogHeader>

            <DialogFooter class="gap-2">
                <Button variant="secondary" @click="settleConfirm(false)">
                    {{ confirmState.cancelLabel }}
                </Button>
                <Button
                    :variant="
                        confirmState.destructive ? 'destructive' : 'default'
                    "
                    @click="settleConfirm(true)"
                >
                    {{ confirmState.confirmLabel }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
