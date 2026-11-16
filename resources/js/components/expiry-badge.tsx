import { Clock, Infinity as InfinityIcon } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { formatDateTime, formatRelative } from '@/lib/format';

export default function ExpiryBadge({
    expiresAt,
    expired,
}: {
    expiresAt: string | null;
    expired: boolean;
}) {
    if (!expiresAt) {
        return (
            <Badge variant="secondary">
                <InfinityIcon className="size-3" />
                Unbegrenzt
            </Badge>
        );
    }

    if (expired) {
        return <Badge variant="destructive">Abgelaufen</Badge>;
    }

    const soon = new Date(expiresAt).getTime() - Date.now() < 24 * 3600_000;

    return (
        <Badge
            variant={soon ? 'outline' : 'secondary'}
            title={`Läuft ab am ${formatDateTime(expiresAt)}`}
            className={
                soon
                    ? 'border-amber-500/50 text-amber-700 dark:text-amber-400'
                    : undefined
            }
        >
            <Clock className="size-3" />
            läuft ab {formatRelative(expiresAt)}
        </Badge>
    );
}
