const units = ['B', 'KB', 'MB', 'GB', 'TB'];

export function formatBytes(bytes: number, decimals = 1): string {
    if (!Number.isFinite(bytes) || bytes <= 0) {
        return '0 B';
    }

    const exponent = Math.min(
        Math.floor(Math.log(bytes) / Math.log(1024)),
        units.length - 1,
    );
    const value = bytes / Math.pow(1024, exponent);

    return `${value.toLocaleString('de-DE', {
        maximumFractionDigits: exponent === 0 ? 0 : decimals,
    })} ${units[exponent]}`;
}

export function formatDateTime(value: string | null | undefined): string {
    if (!value) {
        return '–';
    }

    return new Date(value).toLocaleString('de-DE', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

export function formatDate(value: string | null | undefined): string {
    if (!value) {
        return '–';
    }

    return new Date(value).toLocaleDateString('de-DE', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
    });
}

const relative = new Intl.RelativeTimeFormat('de-DE', { numeric: 'auto' });

export function formatRelative(value: string | null | undefined): string {
    if (!value) {
        return '–';
    }

    const diff = new Date(value).getTime() - Date.now();
    const abs = Math.abs(diff);
    const minute = 60_000;
    const hour = 60 * minute;
    const day = 24 * hour;

    if (abs < hour) {
        return relative.format(Math.round(diff / minute), 'minute');
    }

    if (abs < day) {
        return relative.format(Math.round(diff / hour), 'hour');
    }

    return relative.format(Math.round(diff / day), 'day');
}

export function expiryLabel(days: number): string {
    if (days === 0) {
        return 'Nie';
    }

    return days === 1 ? '1 Tag' : `${days} Tage`;
}
