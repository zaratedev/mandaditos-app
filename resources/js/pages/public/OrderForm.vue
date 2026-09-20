<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed, reactive, ref, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

interface OrderForm {
    customer_name: string;
    phone: string;
    street: string;
    neighborhood: string;
    city: string;
    landmark: string;
    items: string[];
    notes: string;
    /** Honeypot: a real person never sees it, so anything in it came from a bot. */
    company: string;
}

const props = defineProps<{
    business: { name: string; slug: string };
    submitUrl: string;
    success: { orderId: number } | null;
}>();

function blankForm(): OrderForm {
    return {
        customer_name: '',
        phone: '',
        street: '',
        neighborhood: '',
        city: '',
        landmark: '',
        items: [''],
        notes: '',
        company: '',
    };
}

const form = reactive<OrderForm>(blankForm());
const processing = ref(false);

// The page is rendered again with the order number after a successful post, and
// again without it when the customer starts over.
const sent = ref(props.success);

const page = usePage();

/**
 * Laravel sends one message per field, including keys like items.0 for a single
 * bad line, so the bag is read flat and looked up by key.
 */
const errors = computed(
    (): Record<string, string> =>
        (page.props.errors ?? {}) as Record<string, string>,
);

const fieldKeys = [
    'customer_name',
    'phone',
    'street',
    'neighborhood',
    'city',
    'landmark',
    'notes',
    'items',
];

/**
 * Anything the server rejected that has no input on screen to hang off — the
 * honeypot above all. Without this the form would bounce back and simply sit
 * there, refusing to send and never saying why.
 */
const generalErrors = computed((): string[] =>
    Object.entries(errors.value)
        .filter(
            ([key]) => !fieldKeys.includes(key) && !key.startsWith('items.'),
        )
        .map(([, message]) => message),
);

// A password manager can autofill the hidden field and lock a real customer out
// of a form whose error they cannot see. Clearing it lets the retry go through;
// a bot filling it again just gets rejected again.
watch(errors, (bag) => {
    if (bag.company !== undefined) {
        form.company = '';
    }
});

function addItem(): void {
    form.items.push('');
}

function removeItem(index: number): void {
    // The form always keeps one line: an order with nothing in it is not an order.
    if (form.items.length > 1) {
        form.items.splice(index, 1);
    }
}

function submit(): void {
    router.post(
        props.submitUrl,
        { ...form },
        {
            preserveScroll: true,
            onStart: () => {
                processing.value = true;
            },
            onFinish: () => {
                processing.value = false;
            },
        },
    );
}

function startOver(): void {
    Object.assign(form, blankForm());
    sent.value = null;
}

watch(
    () => props.success,
    (success) => {
        sent.value = success;

        // Nothing of the finished order stays behind to be sent twice.
        if (success !== null) {
            Object.assign(form, blankForm());
        }
    },
);

const fieldClass =
    'w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50';
</script>

<template>
    <Head :title="`Pedir en ${business.name}`" />

    <div class="bg-background min-h-screen px-4 py-8">
        <div
            class="border-sidebar-border/70 dark:border-sidebar-border mx-auto w-full max-w-md rounded-xl border p-6"
        >
            <header class="mb-6">
                <h1 class="text-xl font-semibold">{{ business.name }}</h1>
                <p class="text-muted-foreground mt-1 text-sm">
                    Haz tu pedido y te contactamos por WhatsApp.
                </p>
            </header>

            <section v-if="sent" class="flex flex-col gap-4">
                <p class="text-base">
                    Gracias, recibimos tu pedido #{{ sent.orderId }}. El negocio
                    te contactará por WhatsApp.
                </p>
                <Button type="button" @click="startOver">
                    Hacer otro pedido
                </Button>
            </section>

            <form v-else class="flex flex-col gap-4" @submit.prevent="submit">
                <div
                    v-if="generalErrors.length > 0"
                    class="rounded-md border border-red-200 bg-red-50 p-3 dark:border-red-200/20 dark:bg-red-700/10"
                >
                    <p
                        class="text-sm font-medium text-red-600 dark:text-red-500"
                    >
                        No pudimos enviar tu pedido.
                    </p>
                    <p
                        v-for="(message, index) in generalErrors"
                        :key="index"
                        class="mt-1 text-sm text-red-600 dark:text-red-500"
                    >
                        {{ message }}
                    </p>
                </div>

                <div class="grid gap-2">
                    <Label for="customer_name">Tu nombre</Label>
                    <Input
                        id="customer_name"
                        v-model="form.customer_name"
                        name="customer_name"
                        autocomplete="name"
                        required
                    />
                    <InputError :message="errors.customer_name" />
                </div>

                <div class="grid gap-2">
                    <Label for="phone">WhatsApp</Label>
                    <Input
                        id="phone"
                        v-model="form.phone"
                        name="phone"
                        type="tel"
                        inputmode="tel"
                        autocomplete="tel"
                        placeholder="55 1234 5678"
                        required
                    />
                    <InputError :message="errors.phone" />
                </div>

                <div class="grid gap-2">
                    <Label for="street">Calle y número</Label>
                    <Input
                        id="street"
                        v-model="form.street"
                        name="street"
                        autocomplete="street-address"
                        required
                    />
                    <InputError :message="errors.street" />
                </div>

                <div class="grid gap-2">
                    <Label for="neighborhood">Colonia (opcional)</Label>
                    <Input
                        id="neighborhood"
                        v-model="form.neighborhood"
                        name="neighborhood"
                    />
                    <InputError :message="errors.neighborhood" />
                </div>

                <div class="grid gap-2">
                    <Label for="city">Ciudad (opcional)</Label>
                    <Input id="city" v-model="form.city" name="city" />
                    <InputError :message="errors.city" />
                </div>

                <div class="grid gap-2">
                    <Label for="landmark">Referencia (opcional)</Label>
                    <Input
                        id="landmark"
                        v-model="form.landmark"
                        name="landmark"
                        placeholder="Portón verde, frente a la tienda"
                    />
                    <InputError :message="errors.landmark" />
                </div>

                <div class="grid gap-2">
                    <Label for="items-0">¿Qué necesitas?</Label>
                    <p class="text-muted-foreground text-xs">
                        Un renglón por producto.
                    </p>

                    <div
                        v-for="(item, index) in form.items"
                        :key="index"
                        class="grid gap-1"
                    >
                        <div class="flex items-center gap-2">
                            <Input
                                :id="`items-${index}`"
                                v-model="form.items[index]"
                                :name="`items[${index}]`"
                                placeholder="1kg de jitomate"
                                class="flex-1"
                            />
                            <button
                                type="button"
                                class="border-sidebar-border/70 text-muted-foreground dark:border-sidebar-border shrink-0 rounded-md border px-3 py-2 text-sm disabled:opacity-40"
                                :disabled="form.items.length === 1"
                                :aria-label="`Quitar el renglón ${index + 1}`"
                                @click="removeItem(index)"
                            >
                                Quitar
                            </button>
                        </div>
                        <InputError :message="errors[`items.${index}`]" />
                    </div>

                    <button
                        type="button"
                        class="border-sidebar-border/70 dark:border-sidebar-border self-start rounded-md border px-3 py-2 text-sm font-medium"
                        @click="addItem"
                    >
                        Agregar otro
                    </button>

                    <InputError :message="errors.items" />
                </div>

                <div class="grid gap-2">
                    <Label for="notes">Notas (opcional)</Label>
                    <textarea
                        id="notes"
                        v-model="form.notes"
                        name="notes"
                        rows="3"
                        :class="fieldClass"
                    ></textarea>
                    <InputError :message="errors.notes" />
                </div>

                <!--
                    Clipped rather than display:none, which some bots skip — and
                    clipped rather than parked at -9999px, which stretches the
                    document and pushes the card off a phone screen.
                -->
                <div class="sr-only" aria-hidden="true">
                    <label for="company">Empresa</label>
                    <input
                        id="company"
                        v-model="form.company"
                        name="company"
                        type="text"
                        tabindex="-1"
                        autocomplete="off"
                    />
                </div>

                <Button type="submit" :disabled="processing">
                    {{ processing ? 'Enviando…' : 'Enviar pedido' }}
                </Button>
            </form>
        </div>
    </div>
</template>
