import { Head } from '@inertiajs/react';
import { FileX2 } from 'lucide-react';
import {
    Card,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';

export default function PublicTransferUnavailable() {
    return (
        <>
            <Head title="Nicht verfügbar" />

            <Card className="mx-auto max-w-sm">
                <CardHeader className="text-center">
                    <div className="mx-auto mb-2 flex size-14 items-center justify-center rounded-full bg-muted text-muted-foreground">
                        <FileX2 className="size-7" />
                    </div>
                    <CardTitle className="text-xl">
                        Transfer nicht verfügbar
                    </CardTitle>
                    <CardDescription>
                        Dieser Link ist abgelaufen, wurde gelöscht oder ist
                        ungültig. Bitte wende dich an die Person, die ihn dir
                        geschickt hat.
                    </CardDescription>
                </CardHeader>
            </Card>
        </>
    );
}
