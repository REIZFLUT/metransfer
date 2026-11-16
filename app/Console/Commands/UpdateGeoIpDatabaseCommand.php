<?php

namespace App\Console\Commands;

use GeoIp2\Database\Reader;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use PharData;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use Throwable;

class UpdateGeoIpDatabaseCommand extends Command
{
    protected $signature = 'geoip:update
        {--source= : "dbip" (Standard, ohne Account) oder "maxmind" (Lizenzschlüssel nötig)}';

    protected $description = 'Die IP-Länderdatenbank herunterladen bzw. aktualisieren';

    public function handle(): int
    {
        $source = $this->option('source') ?: config('geoblock.source');
        $target = (string) config('geoblock.database_path');
        $workDir = storage_path('app/geoip/tmp-'.Str::random(8));

        File::ensureDirectoryExists($workDir);

        try {
            $database = match ($source) {
                'dbip' => $this->downloadDbIp($workDir),
                'maxmind' => $this->downloadMaxMind($workDir),
                default => throw new RuntimeException("Unbekannte Quelle \"{$source}\" (erlaubt: dbip, maxmind)."),
            };

            // Make sure the file is a readable country database before replacing the old one.
            $reader = new Reader($database);
            $type = $reader->metadata()->databaseType;
            $reader->close();

            if (! str_contains($type, 'Country')) {
                throw new RuntimeException("Unerwarteter Datenbanktyp \"{$type}\".");
            }

            File::ensureDirectoryExists(dirname($target));
            File::move($database, $target);
        } catch (Throwable $exception) {
            $this->components->error('Aktualisierung fehlgeschlagen: '.$exception->getMessage());

            return self::FAILURE;
        } finally {
            File::deleteDirectory($workDir);
        }

        $this->components->info("Länderdatenbank aktualisiert ({$type}): {$target}");

        return self::SUCCESS;
    }

    /**
     * DB-IP publishes a new file each month; early in the month the current
     * file may not exist yet, so fall back to the previous month.
     */
    protected function downloadDbIp(string $workDir): string
    {
        $archive = $workDir.'/dbip.mmdb.gz';

        foreach ([now(), now()->startOfMonth()->subMonth()] as $date) {
            $url = str_replace('{month}', $date->format('Y-m'), (string) config('geoblock.dbip.url'));
            $response = Http::timeout(180)->sink($archive)->get($url);

            if ($response->successful()) {
                return $this->gunzip($archive, $workDir.'/country.mmdb');
            }
        }

        throw new RuntimeException('Download von DB-IP fehlgeschlagen (HTTP '.$response->status().').');
    }

    protected function downloadMaxMind(string $workDir): string
    {
        $accountId = config('geoblock.maxmind.account_id');
        $licenseKey = config('geoblock.maxmind.license_key');
        $edition = config('geoblock.maxmind.edition');

        if (! $accountId || ! $licenseKey) {
            throw new RuntimeException('Für MaxMind müssen MAXMIND_ACCOUNT_ID und MAXMIND_LICENSE_KEY in der .env gesetzt sein.');
        }

        $archive = $workDir.'/database.tar.gz';

        $response = Http::withBasicAuth((string) $accountId, (string) $licenseKey)
            ->timeout(180)
            ->sink($archive)
            ->get("https://download.maxmind.com/geoip/databases/{$edition}/download", ['suffix' => 'tar.gz']);

        if (! $response->successful()) {
            throw new RuntimeException("Download von MaxMind fehlgeschlagen (HTTP {$response->status()}). Account-ID und Lizenzschlüssel prüfen.");
        }

        (new PharData($archive))->extractTo($workDir, null, true);

        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($workDir)) as $file) {
            if ($file->isFile() && str_ends_with($file->getFilename(), '.mmdb')) {
                return $file->getPathname();
            }
        }

        throw new RuntimeException('Im Archiv wurde keine .mmdb-Datei gefunden.');
    }

    protected function gunzip(string $source, string $target): string
    {
        $in = gzopen($source, 'rb');
        $out = fopen($target, 'wb');

        if ($in === false || $out === false) {
            throw new RuntimeException('Archiv konnte nicht entpackt werden.');
        }

        try {
            while (! gzeof($in)) {
                fwrite($out, (string) gzread($in, 1024 * 1024));
            }
        } finally {
            gzclose($in);
            fclose($out);
        }

        return $target;
    }
}
