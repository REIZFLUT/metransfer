import { Head, router, useForm } from '@inertiajs/react';
import { Download, ExternalLink, Lock, Mail, Trash2 } from 'lucide-react';
import { useState } from 'react';
import CopyLink from '@/components/copy-link';
import EmailChipsInput from '@/components/email-chips-input';
import ExpiryBadge from '@/components/expiry-badge';
import FileIcon from '@/components/file-icon';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { expiryLabel, formatBytes, formatDateTime } from '@/lib/format';
import { dashboard } from '@/routes';
import transfers from '@/routes/transfers';
import files from '@/routes/transfers/files';
import recipients from '@/routes/transfers/recipients';
import type { Transfer } from '@/types';

type Props = {
    transfer: Transfer &
        Required<Pick<Transfer, 'files' | 'recipients' | 'downloads'>>;
    expiryOptions: number[];
};

function shortAgent(agent: string | null): string {
    if (!agent) return 'Unbekannt';

    const browser = agent.match(/(Firefox|Edg|OPR|Chrome|Safari)\/[\d.]+/)?.[1];
    const os = agent.match(/(Windows|Mac OS X|Android|iPhone|iPad|Linux)/)?.[1];
    const names: Record<string, string> = { Edg: 'Edge', OPR: 'Opera' };

    return (
        [
            browser ? (names[browser] ?? browser) : null,
            os?.replace('Mac OS X', 'macOS'),
        ]
            .filter(Boolean)
            .join(' · ') || 'Unbekannt'
    );
}

function SettingsCard({ transfer, expiryOptions }: Props) {
    const [extend, setExtend] = useState<string>('');
    const form = useForm({
        notify_on_download: transfer.notify_on_download,
        password: '',
        remove_password: false,
    });

    const saveExpiry = () => {
        router.patch(
            transfers.update.url(transfer.id),
            { expires_in_days: Number(extend) },
            {
                preserveScroll: true,
                onSuccess: () => setExtend(''),
            },
        );
    };

    return (
        <Card>
            <CardHeader>
                <CardTitle>Einstellungen</CardTitle>
                <CardDescription>
                    {transfer.expires_at
                        ? `Gültig bis ${formatDateTime(transfer.expires_at)}`
                        : 'Dieser Link läuft nicht ab.'}
                </CardDescription>
            </CardHeader>
            <CardContent className="grid gap-6">
                <div className="grid gap-2">
                    <Label htmlFor="extend">
                        Gültigkeit neu setzen (ab jetzt)
                    </Label>
                    <div className="flex gap-2">
                        <Select value={extend} onValueChange={setExtend}>
                            <SelectTrigger id="extend" className="w-full">
                                <SelectValue placeholder="Zeitraum wählen" />
                            </SelectTrigger>
                            <SelectContent>
                                {expiryOptions.map((days) => (
                                    <SelectItem key={days} value={String(days)}>
                                        {days === 0
                                            ? 'Unbegrenzt'
                                            : expiryLabel(days)}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <Button
                            type="button"
                            variant="outline"
                            disabled={extend === ''}
                            onClick={saveExpiry}
                        >
                            Speichern
                        </Button>
                    </div>
                </div>

                <form
                    className="grid gap-4"
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.patch(transfers.update.url(transfer.id), {
                            preserveScroll: true,
                            onSuccess: () =>
                                form.reset('password', 'remove_password'),
                        });
                    }}
                >
                    <div className="grid gap-2">
                        <Label htmlFor="password">
                            {transfer.has_password
                                ? 'Neues Passwort'
                                : 'Passwort festlegen'}
                        </Label>
                        <PasswordInput
                            id="password"
                            autoComplete="new-password"
                            value={form.data.password}
                            disabled={form.data.remove_password}
                            onChange={(event) =>
                                form.setData('password', event.target.value)
                            }
                            placeholder={
                                transfer.has_password
                                    ? 'Unverändert lassen'
                                    : 'Kein Passwort'
                            }
                        />
                        <InputError message={form.errors.password} />
                        {transfer.has_password && (
                            <div className="flex items-center gap-3">
                                <Checkbox
                                    id="remove_password"
                                    checked={form.data.remove_password}
                                    onCheckedChange={(checked) =>
                                        form.setData(
                                            'remove_password',
                                            checked === true,
                                        )
                                    }
                                />
                                <Label
                                    htmlFor="remove_password"
                                    className="font-normal"
                                >
                                    Passwortschutz entfernen
                                </Label>
                            </div>
                        )}
                    </div>

                    <div className="flex items-center gap-3">
                        <Checkbox
                            id="notify"
                            checked={form.data.notify_on_download}
                            onCheckedChange={(checked) =>
                                form.setData(
                                    'notify_on_download',
                                    checked === true,
                                )
                            }
                        />
                        <Label htmlFor="notify" className="font-normal">
                            Bei Downloads per E-Mail benachrichtigen
                        </Label>
                    </div>

                    <Button
                        type="submit"
                        variant="outline"
                        disabled={form.processing || !form.isDirty}
                        className="justify-self-start"
                    >
                        Änderungen speichern
                    </Button>
                </form>
            </CardContent>
        </Card>
    );
}

function RecipientsCard({ transfer }: Pick<Props, 'transfer'>) {
    const form = useForm({ recipients: [] as string[] });
    const error = Object.entries(form.errors).find(([key]) =>
        key.startsWith('recipients'),
    )?.[1];

    return (
        <Card>
            <CardHeader>
                <CardTitle>Empfänger</CardTitle>
                <CardDescription>
                    Den Link per E-Mail verschicken.
                </CardDescription>
            </CardHeader>
            <CardContent className="grid gap-4">
                {transfer.recipients.length > 0 ? (
                    <ul className="grid gap-2 text-sm">
                        {transfer.recipients.map((recipient) => (
                            <li
                                key={recipient.id}
                                className="flex items-center justify-between gap-2"
                            >
                                <span className="flex min-w-0 items-center gap-2">
                                    <Mail className="size-4 shrink-0 text-muted-foreground" />
                                    <span className="truncate">
                                        {recipient.email}
                                    </span>
                                </span>
                                <span className="shrink-0 text-xs text-muted-foreground">
                                    {formatDateTime(recipient.sent_at)}
                                </span>
                            </li>
                        ))}
                    </ul>
                ) : (
                    <p className="text-sm text-muted-foreground">
                        Noch an niemanden verschickt.
                    </p>
                )}

                {!transfer.is_expired && (
                    <form
                        className="grid gap-2"
                        onSubmit={(event) => {
                            event.preventDefault();
                            form.post(recipients.store.url(transfer.id), {
                                preserveScroll: true,
                                onSuccess: () => form.reset(),
                            });
                        }}
                    >
                        <EmailChipsInput
                            value={form.data.recipients}
                            onChange={(value) =>
                                form.setData('recipients', value)
                            }
                            placeholder="Weitere Empfänger hinzufügen"
                        />
                        <InputError message={error} />
                        <Button
                            type="submit"
                            variant="outline"
                            className="justify-self-start"
                            disabled={
                                form.processing ||
                                form.data.recipients.length === 0
                            }
                        >
                            <Mail className="size-4" />
                            Senden
                        </Button>
                    </form>
                )}
            </CardContent>
        </Card>
    );
}

export default function ShowTransfer({ transfer, expiryOptions }: Props) {
    return (
        <>
            <Head title={transfer.display_title} />

            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div className="min-w-0">
                        <div className="flex flex-wrap items-center gap-2">
                            <h1 className="truncate text-2xl font-semibold tracking-tight">
                                {transfer.display_title}
                            </h1>
                            {transfer.has_password && (
                                <Lock
                                    className="size-4 text-muted-foreground"
                                    aria-label="Passwortgeschützt"
                                />
                            )}
                            <ExpiryBadge
                                expiresAt={transfer.expires_at}
                                expired={transfer.is_expired}
                            />
                        </div>
                        <p className="mt-1 text-sm text-muted-foreground">
                            {transfer.files_count}{' '}
                            {transfer.files_count === 1 ? 'Datei' : 'Dateien'} ·{' '}
                            {formatBytes(transfer.total_size)} · erstellt am{' '}
                            {formatDateTime(transfer.published_at)} ·{' '}
                            {transfer.download_count} Download(s)
                        </p>
                    </div>
                    <div className="flex gap-2">
                        <Button variant="outline" asChild>
                            <a
                                href={transfer.url}
                                target="_blank"
                                rel="noreferrer"
                            >
                                <ExternalLink className="size-4" />
                                Öffnen
                            </a>
                        </Button>
                        <Dialog>
                            <DialogTrigger asChild>
                                <Button variant="destructive">
                                    <Trash2 className="size-4" />
                                    Löschen
                                </Button>
                            </DialogTrigger>
                            <DialogContent>
                                <DialogTitle>Transfer löschen?</DialogTitle>
                                <DialogDescription>
                                    Der Link funktioniert danach nicht mehr und
                                    alle Dateien werden endgültig vom Server
                                    gelöscht.
                                </DialogDescription>
                                <DialogFooter className="gap-2">
                                    <DialogClose asChild>
                                        <Button variant="secondary">
                                            Abbrechen
                                        </Button>
                                    </DialogClose>
                                    <Button
                                        variant="destructive"
                                        onClick={() =>
                                            router.delete(
                                                transfers.destroy.url(
                                                    transfer.id,
                                                ),
                                            )
                                        }
                                    >
                                        Endgültig löschen
                                    </Button>
                                </DialogFooter>
                            </DialogContent>
                        </Dialog>
                    </div>
                </div>

                {!transfer.is_expired && (
                    <Card className="gap-3">
                        <CardHeader>
                            <CardTitle>Download-Link</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <CopyLink url={transfer.url} />
                        </CardContent>
                    </Card>
                )}

                <div className="grid gap-6 lg:grid-cols-[1fr_380px]">
                    <div className="flex min-w-0 flex-col gap-6">
                        {transfer.message && (
                            <Card>
                                <CardHeader>
                                    <CardTitle>Nachricht</CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <p className="text-sm whitespace-pre-line">
                                        {transfer.message}
                                    </p>
                                </CardContent>
                            </Card>
                        )}

                        <Card className="gap-0 pb-0">
                            <CardHeader className="pb-4">
                                <CardTitle>Dateien</CardTitle>
                            </CardHeader>
                            <ul className="divide-y border-t">
                                {transfer.files.map((file) => (
                                    <li
                                        key={file.id}
                                        className="flex items-center gap-3 px-6 py-3"
                                    >
                                        <FileIcon
                                            name={file.name}
                                            mime={file.mime_type}
                                            className="size-5 shrink-0 text-muted-foreground"
                                        />
                                        <span className="min-w-0 flex-1 truncate text-sm">
                                            {file.name}
                                        </span>
                                        <span className="shrink-0 text-sm text-muted-foreground tabular-nums">
                                            {formatBytes(file.size)}
                                        </span>
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            asChild
                                        >
                                            <a
                                                href={files.download.url({
                                                    transfer: transfer.id,
                                                    file: file.id,
                                                })}
                                                aria-label={`${file.name} herunterladen`}
                                            >
                                                <Download className="size-4" />
                                            </a>
                                        </Button>
                                    </li>
                                ))}
                            </ul>
                        </Card>

                        <Card className="gap-0 pb-0">
                            <CardHeader className="pb-4">
                                <CardTitle>Download-Verlauf</CardTitle>
                                <CardDescription>
                                    Die letzten 100 Downloads
                                </CardDescription>
                            </CardHeader>
                            {transfer.downloads.length === 0 ? (
                                <p className="border-t px-6 py-4 text-sm text-muted-foreground">
                                    Noch keine Downloads.
                                </p>
                            ) : (
                                <ul className="divide-y border-t text-sm">
                                    {transfer.downloads.map((download) => (
                                        <li
                                            key={download.id}
                                            className="flex flex-wrap items-center justify-between gap-x-4 gap-y-1 px-6 py-3"
                                        >
                                            <span className="min-w-0 truncate">
                                                {download.file_name ??
                                                    'Alle Dateien (ZIP)'}
                                            </span>
                                            <span className="text-muted-foreground">
                                                {formatDateTime(
                                                    download.created_at,
                                                )}{' '}
                                                ·{' '}
                                                {shortAgent(
                                                    download.user_agent,
                                                )}{' '}
                                                · {download.ip_address}
                                            </span>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </Card>
                    </div>

                    <div className="flex flex-col gap-6">
                        <SettingsCard
                            transfer={transfer}
                            expiryOptions={expiryOptions}
                        />
                        <RecipientsCard transfer={transfer} />
                    </div>
                </div>
            </div>
        </>
    );
}

ShowTransfer.layout = (props: Props) => ({
    breadcrumbs: [
        { title: 'Transfers', href: dashboard() },
        {
            title: props.transfer.display_title,
            href: transfers.show(props.transfer.id),
        },
    ],
});
