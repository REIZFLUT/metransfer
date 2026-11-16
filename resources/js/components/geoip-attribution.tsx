import { usePage } from '@inertiajs/react';

/**
 * Attribution required by the DB-IP Lite license (CC BY 4.0).
 */
export default function GeoIpAttribution() {
    const { geoipAttribution } = usePage<{ geoipAttribution?: boolean }>()
        .props;

    if (!geoipAttribution) {
        return null;
    }

    return (
        <p className="text-center text-xs text-muted-foreground">
            <a
                href="https://db-ip.com"
                target="_blank"
                rel="noreferrer"
                className="underline-offset-4 hover:underline"
            >
                IP Geolocation by DB-IP
            </a>
        </p>
    );
}
