import { Head, useForm } from '@inertiajs/react';
import {
    AlertCircle,
    CheckCircle2,
    FilePlus2,
    Loader2,
    Send,
    Trash2,
    UploadCloud,
} from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import type { DragEvent, FormEvent } from 'react';
import EmailChipsInput from '@/components/email-chips-input';
import FileIcon from '@/components/file-icon';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Progress } from '@/components/ui/progress';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { useChunkedUpload } from '@/hooks/use-chunked-upload';
import { expiryLabel, formatBytes } from '@/lib/format';
import { requestJson } from '@/lib/http';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import transfers from '@/routes/transfers';
import files from '@/routes/transfers/files';

type Props = {
    chunkSize: number;
    maxFileSize: number;
    expiryOptions: number[];
    defaultExpiry: number;
};

type Phase = 'idle' | 'uploading' | 'publishing';

export default function CreateTransfer({
    chunkSize,
    maxFileSize,
    expiryOptions,
    defaultExpiry,
}: Props) {
    const {
        items,
        addFiles,
        removeFile,
        upload,
        abort,
        totalBytes,
        uploadedBytes,
    } = useChunkedUpload(chunkSize);
    const [transferId, setTransferId] = useState<number | null>(null);
    const [phase, setPhase] = useState<Phase>('idle');
    const [error, setError] = useState<string | null>(null);
    const [dragging, setDragging] = useState(false);
    const inputRef = useRef<HTMLInputElement>(null);
    const dragDepth = useRef(0);

    const form = useForm({
        title: '',
        message: '',
        expires_in_days: defaultExpiry,
        password: '',
        recipients: [] as string[],
        notify_on_download: true,
    });

    const busy = phase !== 'idle';
    const percent = totalBytes > 0 ? (uploadedBytes / totalBytes) * 100 : 0;

    useEffect(() => {
        if (!busy) {
            return;
        }

        const handler = (event: BeforeUnloadEvent) => {
            event.preventDefault();
        };

        window.addEventListener('beforeunload', handler);

        return () => window.removeEventListener('beforeunload', handler);
    }, [busy]);

    const add = (list: FileList | File[] | null) => {
        if (!list) {
            return;
        }

        const accepted = Array.from(list).filter(
            (file) => maxFileSize === 0 || file.size <= maxFileSize,
        );

        if (accepted.length < list.length) {
            setError(
                `Einige Dateien sind größer als ${formatBytes(maxFileSize)} und wurden ignoriert.`,
            );
        } else {
            setError(null);
        }

        addFiles(accepted);
    };

    const onDrop = (event: DragEvent) => {
        event.preventDefault();
        dragDepth.current = 0;
        setDragging(false);

        if (!busy) {
            add(event.dataTransfer.files);
        }
    };

    const onRemove = (key: string) => {
        const item = items.find((i) => i.key === key);

        if (item?.remoteId && transferId) {
            void requestJson(
                'delete',
                files.destroy.url({
                    transfer: transferId,
                    file: item.remoteId,
                }),
            ).catch(() => null);
        }

        removeFile(key);
    };

    const submit = async (event: FormEvent) => {
        event.preventDefault();

        if (items.length === 0) {
            setError('Bitte wähle mindestens eine Datei aus.');

            return;
        }

        setError(null);
        setPhase('uploading');

        try {
            let id = transferId;

            if (!id) {
                const created = await requestJson<{ id: number }>(
                    'post',
                    transfers.store.url(),
                );
                id = created.id;
                setTransferId(id);
            }

            await upload(id);

            setPhase('publishing');

            form.post(transfers.publish.url(id), {
                onError: (errors) => {
                    setPhase('idle');

                    if ('files' in errors) {
                        setError(errors.files as string);
                    }
                },
            });
        } catch (err) {
            setPhase('idle');

            if (err instanceof DOMException && err.name === 'AbortError') {
                setError(
                    'Upload angehalten. Mit „Übertragen“ geht es an derselben Stelle weiter.',
                );
            } else {
                setError(
                    err instanceof Error
                        ? err.message
                        : 'Der Upload ist fehlgeschlagen.',
                );
            }
        }
    };

    const recipientError = Object.entries(form.errors).find(([key]) =>
        key.startsWith('recipients'),
    )?.[1];

    return (
        <>
            <Head title="Neuer Transfer" />

            <form
                onSubmit={submit}
                className="grid flex-1 gap-6 p-4 md:p-6 lg:grid-cols-[1fr_380px]"
            >
                <div className="flex min-w-0 flex-col gap-4">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            Neuer Transfer
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Dateien auswählen, Optionen festlegen und den Link
                            teilen.
                        </p>
                    </div>

                    <label
                        onDragEnter={(event) => {
                            event.preventDefault();
                            dragDepth.current++;
                            setDragging(true);
                        }}
                        onDragOver={(event) => event.preventDefault()}
                        onDragLeave={() => {
                            dragDepth.current--;

                            if (dragDepth.current <= 0) {
                                setDragging(false);
                            }
                        }}
                        onDrop={onDrop}
                        className={cn(
                            'flex cursor-pointer flex-col items-center justify-center gap-3 rounded-xl border-2 border-dashed p-10 text-center transition-colors',
                            dragging
                                ? 'border-primary bg-primary/5'
                                : 'border-muted-foreground/25 hover:border-primary/50 hover:bg-muted/40',
                            busy && 'pointer-events-none opacity-60',
                        )}
                    >
                        <div className="flex size-14 items-center justify-center rounded-full bg-primary/10 text-primary">
                            <UploadCloud className="size-7" />
                        </div>
                        <div>
                            <div className="font-medium">
                                Dateien hierher ziehen
                            </div>
                            <div className="text-sm text-muted-foreground">
                                oder klicken, um Dateien auszuwählen
                                {maxFileSize > 0 &&
                                    ` · max. ${formatBytes(maxFileSize)} pro Datei`}
                            </div>
                        </div>
                        <input
                            ref={inputRef}
                            type="file"
                            multiple
                            className="sr-only"
                            disabled={busy}
                            onChange={(event) => {
                                add(event.target.files);
                                event.target.value = '';
                            }}
                        />
                    </label>

                    {error && (
                        <Alert variant="destructive">
                            <AlertCircle />
                            <AlertTitle>Hinweis</AlertTitle>
                            <AlertDescription>{error}</AlertDescription>
                        </Alert>
                    )}

                    {items.length > 0 && (
                        <Card className="gap-0 py-0">
                            <div className="flex items-center justify-between border-b px-4 py-3 text-sm">
                                <span className="font-medium">
                                    {items.length}{' '}
                                    {items.length === 1 ? 'Datei' : 'Dateien'}
                                </span>
                                <span className="text-muted-foreground tabular-nums">
                                    {formatBytes(totalBytes)}
                                </span>
                            </div>
                            <ul className="max-h-[50vh] divide-y overflow-y-auto">
                                {items.map((item) => {
                                    const itemPercent =
                                        item.file.size > 0
                                            ? (item.uploaded / item.file.size) *
                                              100
                                            : 100;

                                    return (
                                        <li
                                            key={item.key}
                                            className="flex items-center gap-3 px-4 py-3"
                                        >
                                            <FileIcon
                                                name={item.file.name}
                                                mime={item.file.type}
                                                className="size-5 shrink-0 text-muted-foreground"
                                            />
                                            <div className="min-w-0 flex-1">
                                                <div className="flex items-center justify-between gap-3 text-sm">
                                                    <span className="truncate">
                                                        {item.file.name}
                                                    </span>
                                                    <span className="shrink-0 text-muted-foreground tabular-nums">
                                                        {item.status ===
                                                        'uploading'
                                                            ? `${Math.floor(itemPercent)} %`
                                                            : formatBytes(
                                                                  item.file
                                                                      .size,
                                                              )}
                                                    </span>
                                                </div>
                                                {(item.status === 'uploading' ||
                                                    (item.uploaded > 0 &&
                                                        item.status !==
                                                            'done')) && (
                                                    <Progress
                                                        value={itemPercent}
                                                        className="mt-2 h-1.5"
                                                    />
                                                )}
                                                {item.status === 'error' && (
                                                    <p className="mt-1 text-xs text-red-600 dark:text-red-400">
                                                        {item.error}
                                                    </p>
                                                )}
                                            </div>
                                            {item.status === 'done' ? (
                                                <CheckCircle2
                                                    className="size-5 shrink-0 text-green-600"
                                                    aria-label="Hochgeladen"
                                                />
                                            ) : item.status === 'uploading' ? (
                                                <Loader2 className="size-5 shrink-0 animate-spin text-muted-foreground" />
                                            ) : (
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    size="icon"
                                                    disabled={busy}
                                                    onClick={() =>
                                                        onRemove(item.key)
                                                    }
                                                    aria-label={`${item.file.name} entfernen`}
                                                >
                                                    <Trash2 className="size-4" />
                                                </Button>
                                            )}
                                        </li>
                                    );
                                })}
                            </ul>
                            {!busy && (
                                <div className="border-t px-4 py-2">
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        onClick={() =>
                                            inputRef.current?.click()
                                        }
                                    >
                                        <FilePlus2 className="size-4" />
                                        Weitere Dateien hinzufügen
                                    </Button>
                                </div>
                            )}
                        </Card>
                    )}
                </div>

                <div className="flex flex-col gap-4">
                    <Card className="lg:sticky lg:top-4">
                        <CardHeader>
                            <CardTitle>Optionen</CardTitle>
                            <CardDescription>
                                Alles optional – nur die Dateien sind Pflicht.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="grid gap-5">
                            <div className="grid gap-2">
                                <Label htmlFor="title">Titel</Label>
                                <Input
                                    id="title"
                                    value={form.data.title}
                                    disabled={busy}
                                    onChange={(event) =>
                                        form.setData(
                                            'title',
                                            event.target.value,
                                        )
                                    }
                                    placeholder="z. B. Fotos vom Shooting"
                                />
                                <InputError message={form.errors.title} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="message">Nachricht</Label>
                                <Textarea
                                    id="message"
                                    rows={3}
                                    value={form.data.message}
                                    disabled={busy}
                                    onChange={(event) =>
                                        form.setData(
                                            'message',
                                            event.target.value,
                                        )
                                    }
                                    placeholder="Ein paar Worte an die Empfänger …"
                                />
                                <InputError message={form.errors.message} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="recipients">
                                    Per E-Mail senden an
                                </Label>
                                <EmailChipsInput
                                    id="recipients"
                                    value={form.data.recipients}
                                    disabled={busy}
                                    onChange={(value) =>
                                        form.setData('recipients', value)
                                    }
                                />
                                <p className="text-xs text-muted-foreground">
                                    Leer lassen, um nur einen Link zu erstellen.
                                </p>
                                <InputError message={recipientError} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="expires">Link gültig für</Label>
                                <Select
                                    value={String(form.data.expires_in_days)}
                                    disabled={busy}
                                    onValueChange={(value) =>
                                        form.setData(
                                            'expires_in_days',
                                            Number(value),
                                        )
                                    }
                                >
                                    <SelectTrigger
                                        id="expires"
                                        className="w-full"
                                    >
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {expiryOptions.map((days) => (
                                            <SelectItem
                                                key={days}
                                                value={String(days)}
                                            >
                                                {days === 0
                                                    ? 'Unbegrenzt'
                                                    : expiryLabel(days)}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError
                                    message={form.errors.expires_in_days}
                                />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="password">
                                    Passwort (optional)
                                </Label>
                                <PasswordInput
                                    id="password"
                                    autoComplete="new-password"
                                    value={form.data.password}
                                    disabled={busy}
                                    onChange={(event) =>
                                        form.setData(
                                            'password',
                                            event.target.value,
                                        )
                                    }
                                    placeholder="Kein Passwort"
                                />
                                <InputError message={form.errors.password} />
                            </div>

                            <div className="flex items-center gap-3">
                                <Checkbox
                                    id="notify"
                                    checked={form.data.notify_on_download}
                                    disabled={busy}
                                    onCheckedChange={(checked) =>
                                        form.setData(
                                            'notify_on_download',
                                            checked === true,
                                        )
                                    }
                                />
                                <Label htmlFor="notify" className="font-normal">
                                    Mich bei Downloads per E-Mail
                                    benachrichtigen
                                </Label>
                            </div>

                            {busy && (
                                <div className="grid gap-2">
                                    <div className="flex justify-between text-sm">
                                        <span>
                                            {phase === 'publishing'
                                                ? 'Wird abgeschlossen …'
                                                : 'Wird hochgeladen …'}
                                        </span>
                                        <span className="tabular-nums">
                                            {Math.floor(percent)} %
                                        </span>
                                    </div>
                                    <Progress value={percent} />
                                    <div className="text-xs text-muted-foreground tabular-nums">
                                        {formatBytes(uploadedBytes)} von{' '}
                                        {formatBytes(totalBytes)}
                                    </div>
                                </div>
                            )}

                            <div className="flex gap-2">
                                <Button
                                    type="submit"
                                    className="flex-1"
                                    disabled={busy || items.length === 0}
                                >
                                    {busy ? (
                                        <Loader2 className="size-4 animate-spin" />
                                    ) : (
                                        <Send className="size-4" />
                                    )}
                                    Übertragen
                                </Button>
                                {phase === 'uploading' && (
                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={abort}
                                    >
                                        Anhalten
                                    </Button>
                                )}
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </form>
        </>
    );
}

CreateTransfer.layout = {
    breadcrumbs: [
        { title: 'Transfers', href: dashboard() },
        { title: 'Neuer Transfer', href: transfers.create() },
    ],
};
