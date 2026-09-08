const currency = new Intl.NumberFormat('es-MX', {
    style: 'currency',
    currency: 'MXN',
});

export function money(value: number | string | null | undefined): string {
    if (value === null || value === undefined || value === '') {
        return '—';
    }

    const amount = typeof value === 'string' ? parseFloat(value) : value;

    if (Number.isNaN(amount)) {
        return '—';
    }

    return currency.format(amount);
}

/**
 * Render a plain Y-m-d value the way the rest of the app writes dates: 08/09/2026.
 * Split rather than parsed, so a bare Y-m-d is never shifted by the browser timezone.
 */
export function shortDate(value: string | null | undefined): string {
    if (!value) {
        return '—';
    }

    const [year, month, day] = value.split('-');

    return year && month && day ? `${day}/${month}/${year}` : value;
}

/**
 * The same date without the year, for axis labels where space is tight: 08/09.
 */
export function shortDayMonth(value: string | null | undefined): string {
    if (!value) {
        return '';
    }

    const [, month, day] = value.split('-');

    return month && day ? `${day}/${month}` : value;
}

export function statusBadgeClass(status: string): string {
    const map: Record<string, string> = {
        requested: 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300',
        confirmed: 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300',
        assigned: 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300',
        purchasing: 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300',
        purchased: 'bg-cyan-100 text-cyan-700 dark:bg-cyan-900/40 dark:text-cyan-300',
        on_the_way: 'bg-purple-100 text-purple-700 dark:bg-purple-900/40 dark:text-purple-300',
        delivered: 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300',
        cancelled: 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300',
    };

    return map[status] ?? map.requested;
}

export function paymentBadgeClass(status: string): string {
    return status === 'paid'
        ? 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300'
        : 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300';
}
