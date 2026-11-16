<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Country restriction
    |--------------------------------------------------------------------------
    |
    | When enabled, login and all management pages are only reachable from
    | the listed countries (ISO 3166-1 alpha-2 codes). Public download links
    | (/t/...) stay reachable worldwide. IP addresses that cannot be mapped
    | to a country are blocked, unless they are on the allowlist.
    |
    */

    'enabled' => (bool) env('GEOBLOCK_ENABLED', false),

    'countries' => array_values(array_filter(array_map(
        fn (string $code) => strtoupper(trim($code)),
        explode(',', (string) env('GEOBLOCK_COUNTRIES', 'DE')),
    ))),

    /*
    | IP addresses or CIDR ranges that are always allowed, comma-separated,
    | e.g. "203.0.113.10,198.51.100.0/24,2001:db8::/32".
    */

    'allowed_ips' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('GEOBLOCK_ALLOWED_IPS', '')),
    ))),

    /*
    | Reverse proxies whose X-Forwarded-For header is trusted, so the real
    | visitor IP is used: "*" (e.g. behind Cloudflare) or a comma-separated
    | list of proxy IPs/CIDRs. Leave empty when visitors connect directly.
    */

    'trusted_proxies' => env('TRUSTED_PROXIES'),

    /*
    |--------------------------------------------------------------------------
    | Country database
    |--------------------------------------------------------------------------
    |
    | `php artisan geoip:update` downloads the IP-to-country database to the
    | given path; the scheduler refreshes it weekly.
    |
    | Sources:
    |  - "dbip" (default): DB-IP "IP to Country Lite", free without an account,
    |    licensed under CC BY 4.0 (https://db-ip.com), updated monthly.
    |  - "maxmind": MaxMind GeoLite2 Country, requires a free account and
    |    license key (https://www.maxmind.com/en/geolite2/signup).
    |
    */

    'source' => env('GEOIP_SOURCE', 'dbip'),

    'database_path' => env('GEOIP_DATABASE_PATH', storage_path('app/geoip/country.mmdb')),

    'dbip' => [
        'url' => env('DBIP_URL', 'https://download.db-ip.com/free/dbip-country-lite-{month}.mmdb.gz'),
    ],

    'maxmind' => [
        'account_id' => env('MAXMIND_ACCOUNT_ID'),
        'license_key' => env('MAXMIND_LICENSE_KEY'),
        'edition' => env('MAXMIND_EDITION', 'GeoLite2-Country'),
    ],

];
