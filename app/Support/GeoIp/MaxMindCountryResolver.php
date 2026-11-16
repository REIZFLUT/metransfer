<?php

namespace App\Support\GeoIp;

use GeoIp2\Database\Reader;
use GeoIp2\Exception\AddressNotFoundException;
use InvalidArgumentException;

class MaxMindCountryResolver implements CountryResolver
{
    protected ?Reader $reader = null;

    public function __construct(protected string $databasePath) {}

    public function country(string $ip): ?string
    {
        try {
            return $this->reader()->country($ip)->country->isoCode;
        } catch (AddressNotFoundException|InvalidArgumentException) {
            return null;
        }
    }

    protected function reader(): Reader
    {
        if ($this->reader) {
            return $this->reader;
        }

        if (! is_file($this->databasePath)) {
            throw new GeoIpDatabaseMissing("GeoIP-Datenbank fehlt: {$this->databasePath}. Bitte `php artisan geoip:update` ausführen.");
        }

        return $this->reader = new Reader($this->databasePath);
    }
}
