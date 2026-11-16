<?php

namespace App\Console\Commands;

use GeoIp2\Database\Reader;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use PharData;
use RecursiveIteratorIterator;
use Throwable;

class UpdateGeoIpDatabaseCommand extends Command
{
    protected $signature = 'geoip:update';

    protected $description = 'Die MaxMind-GeoLite2-Länderdatenbank herunterladen bzw. aktualisieren';

    public function handle(): int
    {
        $accountId = config('geoblock.maxmind.account_id');
        $licenseKey = config('geoblock.maxmind.license_key');
        $edition = config('geoblock.maxmind.edition');
        $target = (string) config('geoblock.database_path');

        if (! $accountId || ! $licenseKey) {
            $this->components->error('MAXMIND_ACCOUNT_ID und MAXMIND_LICENSE_KEY müssen in der .env gesetzt sein.');

            return self::FAILURE;
        }

        $workDir = storage_path('app/geoip/tmp-'.Str::random(8));
        File::ensureDirectoryExists($workDir);
        $archive = $workDir.'/database.tar.gz';

        try {
            $response = Http::withBasicAuth((string) $accountId, (string) $licenseKey)
                ->timeout(120)
                ->sink($archive)
                ->get("https://download.maxmind.com/geoip/databases/{$edition}/download", ['suffix' => 'tar.gz']);

            if (! $response->successful()) {
                $this->components->error("Download fehlgeschlagen (HTTP {$response->status()}). Account-ID und Lizenzschlüssel prüfen.");

                return self::FAILURE;
            }

            $database = $this->extractDatabase($archive, $workDir);

            // Make sure the file is a readable database before replacing the old one.
            (new Reader($database))->close();

            File::ensureDirectoryExists(dirname($target));
            File::move($database, $target);
        } catch (Throwable $exception) {
            $this->components->error('Aktualisierung fehlgeschlagen: '.$exception->getMessage());

            return self::FAILURE;
        } finally {
            File::deleteDirectory($workDir);
        }

        $this->components->info("GeoIP-Datenbank aktualisiert: {$target}");

        return self::SUCCESS;
    }

    protected function extractDatabase(string $archive, string $workDir): string
    {
        $phar = new PharData($archive);
        $phar->extractTo($workDir, null, true);

        foreach (new RecursiveIteratorIterator(new \RecursiveDirectoryIterator($workDir)) as $file) {
            if ($file->isFile() && str_ends_with($file->getFilename(), '.mmdb')) {
                return $file->getPathname();
            }
        }

        throw new \RuntimeException('Im Archiv wurde keine .mmdb-Datei gefunden.');
    }
}
