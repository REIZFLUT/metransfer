<?php

namespace App\Console\Commands;

use App\Support\GeoIp\CountryResolver;
use App\Support\GeoIp\GeoIpDatabaseMissing;
use Illuminate\Console\Command;
use Symfony\Component\HttpFoundation\IpUtils;

class GeoIpLookupCommand extends Command
{
    protected $signature = 'geoip:lookup {ip : IP-Adresse}';

    protected $description = 'Prüfen, aus welchem Land eine IP kommt und ob sie den Login-Bereich erreichen darf';

    public function handle(CountryResolver $resolver): int
    {
        $ip = (string) $this->argument('ip');

        if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
            $this->components->error('Ungültige IP-Adresse.');

            return self::FAILURE;
        }

        try {
            $country = $resolver->country($ip);
        } catch (GeoIpDatabaseMissing $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $countries = config('geoblock.countries');
        $allowlist = config('geoblock.allowed_ips');
        $allowlisted = $allowlist !== [] && IpUtils::checkIp($ip, $allowlist);
        $allowed = $allowlisted || ($country !== null && in_array($country, $countries, true));

        $this->components->twoColumnDetail('IP', $ip);
        $this->components->twoColumnDetail('Land', $country ?? 'unbekannt');
        $this->components->twoColumnDetail('Erlaubte Länder', implode(', ', $countries));
        $this->components->twoColumnDetail('Auf der Allowlist', $allowlisted ? 'ja' : 'nein');
        $this->components->twoColumnDetail('Zugriff', $allowed ? '<fg=green>erlaubt</>' : '<fg=red>gesperrt</>');

        if (! config('geoblock.enabled')) {
            $this->components->warn('Die Länder-Sperre ist derzeit deaktiviert (GEOBLOCK_ENABLED=false).');
        }

        return self::SUCCESS;
    }
}
