import { Head, Link } from '@inertiajs/react';
import { Download, HardDrive, Lock, Plus, Send, Upload } from 'lucide-react';
import { CopyButton } from '@/components/copy-link';
import ExpiryBadge from '@/components/expiry-badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { formatBytes, formatDate } from '@/lib/format';
import { dashboard } from '@/routes';
import transfers from '@/routes/transfers';
import type { Transfer } from '@/types';

type Props = {
    transfers: Transfer[];
    stats: { active: number; downloads: number; storage: number };
};

function Stat({
    icon: Icon,
    label,
    value,
}: {
    icon: typeof Send;
    label: string;
    value: string;
}) {
    return (
        <Card className="gap-0 py-4">
            <CardContent className="flex items-center gap-4 px-4">
                <div className="flex size-10 items-center justify-center rounded-lg bg-primary/10 text-primary">
                    <Icon className="size-5" />
                </div>
                <div>
                    <div className="text-sm text-muted-foreground">{label}</div>
                    <div className="text-xl font-semibold tabular-nums">
                        {value}
                    </div>
                </div>
            </CardContent>
        </Card>
    );
}

export default function Dashboard({ transfers: items, stats }: Props) {
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

                <div className="grid gap-4 sm:grid-cols-3">
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
