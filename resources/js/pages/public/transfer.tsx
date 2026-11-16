import { Head } from '@inertiajs/react';
import { Clock, Download, FolderDown } from 'lucide-react';
import FileIcon from '@/components/file-icon';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { formatBytes, formatDateTime } from '@/lib/format';
import { download, zip } from '@/routes/public/transfers';
import type { TransferFile } from '@/types';

type Props = {
    transfer: {
        token: string;
        title: string;
        message: string | null;
        sender: string;
        expires_at: string | null;
        total_size: number;
        files: TransferFile[];
    };
};

export default function PublicTransfer({ transfer }: Props) {
    const count = transfer.files.length;

    return (
        <>
            <Head title={transfer.title} />

            <Card>
                <CardHeader className="text-center">
                    <div className="mx-auto mb-2 flex size-14 items-center justify-center rounded-full bg-primary/10 text-primary">
                        <FolderDown className="size-7" />
                    </div>
                    <CardTitle className="text-xl">{transfer.title}</CardTitle>
                    <CardDescription>
                        {transfer.sender} hat dir{' '}
                        {count === 1 ? 'eine Datei' : `${count} Dateien`} (
                        {formatBytes(transfer.total_size)}) geschickt.
                    </CardDescription>
                </CardHeader>

                <CardContent className="grid gap-5">
                    {transfer.message && (
                        <blockquote className="rounded-lg border-l-4 border-primary/40 bg-muted/60 px-4 py-3 text-sm whitespace-pre-line">
                            {transfer.message}
                        </blockquote>
                    )}

                    {count > 1 && (
                        <Button size="lg" asChild>
                            <a href={zip.url(transfer.token)}>
                                <Download className="size-4" />
                                Alle herunterladen (ZIP)
                            </a>
                        </Button>
                    )}

                    <ul className="divide-y rounded-lg border">
                        {transfer.files.map((file) => (
                            <li
                                key={file.id}
                                className="flex items-center gap-3 px-4 py-3"
                            >
                                <FileIcon
                                    name={file.name}
                                    mime={file.mime_type}
                                    className="size-5 shrink-0 text-muted-foreground"
                                />
                                <div className="min-w-0 flex-1">
                                    <div className="truncate text-sm font-medium">
                                        {file.name}
                                    </div>
                                    <div className="text-xs text-muted-foreground">
                                        {formatBytes(file.size)}
                                    </div>
                                </div>
                                <Button
                                    variant={
                                        count === 1 ? 'default' : 'outline'
                                    }
                                    size="sm"
                                    asChild
                                >
                                    <a
                                        href={download.url({
                                            token: transfer.token,
                                            file: file.id,
                                        })}
                                    >
                                        <Download className="size-4" />
                                        Download
                                    </a>
                                </Button>
                            </li>
                        ))}
                    </ul>

                    {transfer.expires_at && (
                        <p className="flex items-center justify-center gap-1.5 text-xs text-muted-foreground">
                            <Clock className="size-3.5" />
                            Verfügbar bis {formatDateTime(transfer.expires_at)}
                        </p>
                    )}
                </CardContent>
            </Card>
        </>
    );
}
