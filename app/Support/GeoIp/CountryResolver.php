<?php

namespace App\Support\GeoIp;

interface CountryResolver
{
    /**
     * The ISO 3166-1 alpha-2 country code of the IP address, or null if it
     * cannot be determined.
     *
     * @throws GeoIpDatabaseMissing when no database is available
     */
    public function country(string $ip): ?string;
}
