import { Head } from '@inertiajs/react';
import AppearanceTabs from '@/components/appearance-tabs';
import Heading from '@/components/heading';
import { edit as editAppearance } from '@/routes/appearance';

export default function Appearance() {
    return (
        <>
            <Head title="Darstellung" />

            <h1 className="sr-only">Darstellung</h1>

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title="Darstellung"
                    description="Darstellung der Oberfläche anpassen"
                />
                <AppearanceTabs />
            </div>
        </>
    );
}

Appearance.layout = {
    breadcrumbs: [
        {
            title: 'Darstellung',
            href: editAppearance(),
        },
    ],
};
