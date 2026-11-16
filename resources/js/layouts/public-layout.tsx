import { usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import AppLogoIcon from '@/components/app-logo-icon';

export default function PublicLayout({ children }: { children: ReactNode }) {
    const { name } = usePage().props;

    return (
        <div className="flex min-h-svh flex-col bg-muted/40">
            <header className="flex items-center justify-center gap-2 px-6 py-6 text-sm font-semibold">
                <div className="flex size-8 items-center justify-center rounded-md bg-primary text-primary-foreground">
                    <AppLogoIcon className="size-5 fill-current" />
                </div>
                {String(name)}
            </header>
            <main className="flex flex-1 items-start justify-center px-4 pb-16 md:items-center">
                <div className="w-full max-w-xl">{children}</div>
            </main>
        </div>
    );
}
