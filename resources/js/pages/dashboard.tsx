import type { ReactNode } from 'react';
import { Head, Link } from '@inertiajs/react';
import {
    Database,
    Download,
    HardDrive,
    Lock,
    Plus,
    Send,
    Upload,
} from 'lucide-react';
import { CopyButton } from '@/components/copy-link';
import ExpiryBadge from '@/components/expiry-badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Progress } from '@/components/ui/progress';
import { formatBytes, formatDate } from '@/lib/format';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import transfers from '@/routes/transfers';
import type { Transfer } from '@/types';

type Props = {
    transfers: Transfer[];
    stats: { active: number; downloads: number; storage: number };
    disk: { free: number; total: number } | null;
};

function Stat({
    icon: Icon,
    label,
    value,
    hint,
    children,
    iconClassName,
}: {
    icon: typeof Send;
    label: string;
    value: string;
    hint?: string;
    children?: ReactNode;
    iconClassName?: string;
}) {
    return (
        <Card className="gap-0 py-4">
            <CardContent className="flex items-center gap-4 px-4">
                <div
                    className={cn(
                        'flex size-10 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary',
                        iconClassName,
                    )}
                >
                    <Icon className="size-5" />
                </div>
                <div className="min-w-0 flex-1">
                    <div className="text-sm text-muted-foreground">{label}</div>
                    <div className="text-xl font-semibold whitespace-nowrap tabular-nums">
                        {value}
                    </div>
                    {children}
                    {hint && (
                        <div className="mt-1 truncate text-xs text-muted-foreground">
                            {hint}
                        </div>
                    )}
                </div>
            </CardContent>
        </Card>
    );
}

function DiskStat({ disk }: { disk: { free: number; total: number } }) {
    const usedPercent = ((disk.total - disk.free) / disk.total) * 100;
    const freePercent = 100 - usedPercent;
    const critical = freePercent < 5;
    const low = freePercent < 15;

    return (
        <Stat
            icon={Database}
            label="Freier Speicher"
            value={formatBytes(disk.free)}
            hint={`von ${formatBytes(disk.total)} · ${Math.round(usedPercent)} % belegt`}
            iconClassName={cn(
                low && 'bg-amber-500/10 text-amber-600 dark:text-amber-400',
                critical && 'bg-red-500/10 text-red-600 dark:text-red-400',
            )}
        >
            <Progress
                value={usedPercent}
                className="mt-2 h-1.5"
                indicatorClassName={cn(
                    low && 'bg-amber-500',
                    critical && 'bg-red-500',
                )}
                aria-label={`${Math.round(usedPercent)} % belegt`}
                title={`${Math.round(usedPercent)} % belegt`}
            />
        </Stat>
    );
}

export default function Dashboard({ transfers: items, stats, disk }: Props) {
    return (
        <>
            <Head title="Transfers" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            Transfers
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Deine geteilten Dateien im Überblick
                        </p>
                    </div>
                    <Button asChild>
                        <Link href={transfers.create()}>
                            <Plus className="size-4" />
                            Neuer Transfer
                        </Link>
                    </Button>
                </div>

                <div
                    className={cn(
                        'grid gap-4 sm:grid-cols-2',
                        disk ? 'xl:grid-cols-4' : 'lg:grid-cols-3',
                    )}
                >
                    <Stat
                        icon={Send}
                        label="Aktive Transfers"
                        value={stats.active.toLocaleString('de-DE')}
                    />
                    <Stat
                        icon={Download}
                        label="Downloads gesamt"
                        value={stats.downloads.toLocaleString('de-DE')}
                    />
                    <Stat
                        icon={HardDrive}
                        label="Belegter Speicher"
                        value={formatBytes(stats.storage)}
                    />
                    {disk && <DiskStat disk={disk} />}
                </div>

                {items.length === 0 ? (
                    <Card className="items-center border-dashed py-16 text-center">
                        <CardContent className="flex flex-col items-center gap-4">
                            <div className="flex size-14 items-center justify-center rounded-full bg-primary/10 text-primary">
                                <Upload className="size-6" />
                            </div>
                            <div>
                                <h2 className="text-lg font-medium">
                                    Noch keine Transfers
                                </h2>
                                <p className="text-sm text-muted-foreground">
                                    Lade Dateien hoch und teile sie per Link
                                    oder E-Mail.
                                </p>
                            </div>
                            <Button asChild>
                                <Link href={transfers.create()}>
                                    <Plus className="size-4" />
                                    Ersten Transfer erstellen
                                </Link>
                            </Button>
                        </CardContent>
                    </Card>
                ) : (
                    <Card className="gap-0 overflow-hidden py-0">
                        <ul className="divide-y">
                            {items.map((transfer) => (
                                <li
                                    key={transfer.id}
                                    className="flex flex-col gap-3 p-4 transition-colors hover:bg-muted/40 sm:flex-row sm:items-center"
                                >
                                    <Link
                                        href={transfers.show(transfer.id)}
                                        className="min-w-0 flex-1"
                                    >
                                        <div className="flex items-center gap-2">
                                            <span className="truncate font-medium">
                                                {transfer.display_title}
                                            </span>
                                            {transfer.has_password && (
                                                <Lock
                                                    className="size-3.5 shrink-0 text-muted-foreground"
                                                    aria-label="Passwortgeschützt"
                                                />
                                            )}
                                        </div>
                                        <div className="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-muted-foreground">
                                            <span>
                                                {transfer.files_count}{' '}
                                                {transfer.files_count === 1
                                                    ? 'Datei'
                                                    : 'Dateien'}{' '}
                                                ·{' '}
                                                {formatBytes(
                                                    transfer.total_size,
                                                )}
                                            </span>
                                            <span>
                                                erstellt{' '}
                                                {formatDate(
                                                    transfer.published_at,
                                                )}
                                            </span>
                                            <span className="inline-flex items-center gap-1">
                                                <Download className="size-3.5" />
                                                {transfer.download_count}
                                            </span>
                                        </div>
                                    </Link>
                                    <div className="flex items-center gap-2">
                                        <ExpiryBadge
                                            expiresAt={transfer.expires_at}
                                            expired={transfer.is_expired}
                                        />
                                        {!transfer.is_expired && (
                                            <CopyButton value={transfer.url} />
                                        )}
                                    </div>
                                </li>
                            ))}
                        </ul>
                    </Card>
                )}
            </div>
        </>
    );
}

Dashboard.layout = {
    breadcrumbs: [
        {
            title: 'Transfers',
            href: dashboard(),
        },
    ],
};
