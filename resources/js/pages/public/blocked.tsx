import { Head } from '@inertiajs/react';
import { Globe } from 'lucide-react';
import {
    Card,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';

export default function Blocked() {
    return (
        <>
            <Head title="Nicht verfügbar" />

            <Card className="mx-auto max-w-sm">
                <CardHeader className="text-center">
                    <div className="mx-auto mb-2 flex size-14 items-center justify-center rounded-full bg-muted text-muted-foreground">
                        <Globe className="size-7" />
                    </div>
                    <CardTitle className="text-xl">
                        Zugriff nicht möglich
                    </CardTitle>
                    <CardDescription>
                        Dieser Bereich ist aus deiner Region nicht erreichbar.
                        Download-Links, die dir jemand geschickt hat,
                        funktionieren weiterhin.
                    </CardDescription>
                </CardHeader>
            </Card>
        </>
    );
}
