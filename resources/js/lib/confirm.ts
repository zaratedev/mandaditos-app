import { reactive } from 'vue';

export interface ConfirmOptions {
    title: string;
    description: string;
    confirmLabel?: string;
    cancelLabel?: string;
    destructive?: boolean;
}

interface ConfirmState extends Required<ConfirmOptions> {
    open: boolean;
}

const defaults = {
    confirmLabel: 'Continuar',
    cancelLabel: 'Cancelar',
    destructive: false,
};

/**
 * The question currently on screen. Only ConfirmDialog reads it, and there is a
 * single one of those mounted in the layout, so only one question can be open.
 */
export const confirmState = reactive<ConfirmState>({
    open: false,
    title: '',
    description: '',
    ...defaults,
});

let answer: ((confirmed: boolean) => void) | null = null;

/**
 * Ask the user to confirm an action, in a dialog that belongs to the app instead of
 * to the browser. Resolves true only when they pick the confirm button: cancelling,
 * escape, a click outside, leaving the page and a second question taking this one's
 * place all answer false, so a caller that acts on true never acts on a dismissal.
 */
export function confirm(options: ConfirmOptions): Promise<boolean> {
    // A second question replaces the first; whoever was waiting gets a no.
    answer?.(false);

    Object.assign(confirmState, defaults, options, { open: true });

    return new Promise<boolean>((resolve) => {
        answer = resolve;
    });
}

/**
 * Close the open question with the user's answer. ConfirmDialog owns this.
 */
export function settleConfirm(confirmed: boolean): void {
    confirmState.open = false;

    answer?.(confirmed);
    answer = null;
}
