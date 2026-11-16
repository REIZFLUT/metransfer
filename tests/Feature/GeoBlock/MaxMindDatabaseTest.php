<?php

namespace Tests\Feature\GeoBlock;

use App\Support\GeoIp\GeoIpDatabaseMissing;
use App\Support\GeoIp\MaxMindCountryResolver;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use PharData;
use Tests\TestCase;

/**
 * Uses MaxMind's public test database (tests/Fixtures/GeoIP2-Country-Test.mmdb,
 * from github.com/maxmind/MaxMind-DB).
 */
class MaxMindDatabaseTest extends TestCase
{
    protected string $fixture;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fixture = base_path('tests/Fixtures/GeoIP2-Country-Test.mmdb');
    }

    public function test_resolver_reads_countries_from_the_database()
    {
        $resolver = new MaxMindCountryResolver($this->fixture);

        $this->assertSame('SE', $resolver->country('89.160.20.112'));
        $this->assertSame('GB', $resolver->country('2.125.160.216'));
        $this->assertNull($resolver->country('1.2.3.4'));
        $this->assertNull($resolver->country('kein-ip'));
    }

    public function test_resolver_throws_when_database_is_missing()
    {
        $this->expectException(GeoIpDatabaseMissing::class);

        (new MaxMindCountryResolver('/does/not/exist.mmdb'))->country('1.2.3.4');
    }

    public function test_update_command_downloads_and_installs_the_database()
    {
        $tmp = sys_get_temp_dir().'/geoip-test-'.uniqid();
        File::ensureDirectoryExists($tmp.'/GeoLite2-Country_20261110');
        copy($this->fixture, $tmp.'/GeoLite2-Country_20261110/GeoLite2-Country.mmdb');

        $tar = new PharData($tmp.'/db.tar');
        $tar->buildFromDirectory($tmp, '/GeoLite2/');
        $tar->compress(\Phar::GZ);

        $target = $tmp.'/installed/GeoLite2-Country.mmdb';

        config([
            'geoblock.maxmind.account_id' => '123',
            'geoblock.maxmind.license_key' => 'key',
            'geoblock.database_path' => $target,
        ]);

        Http::fake(['download.maxmind.com/*' => Http::response(file_get_contents($tmp.'/db.tar.gz'))]);

        $this->artisan('geoip:update', ['--source' => 'maxmind'])->assertSuccessful();

        Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Basic '.base64_encode('123:key'))
            && str_contains($request->url(), 'GeoLite2-Country/download'));

        $this->assertFileExists($target);
        $this->assertSame('SE', (new MaxMindCountryResolver($target))->country('89.160.20.112'));

        File::deleteDirectory($tmp);
    }

    public function test_update_command_downloads_dbip_database_without_account()
    {
        $tmp = sys_get_temp_dir().'/dbip-test-'.uniqid();
        File::ensureDirectoryExists($tmp);
        $target = $tmp.'/installed/country.mmdb';

        config(['geoblock.database_path' => $target, 'geoblock.source' => 'dbip']);

        $currentMonth = now()->format('Y-m');
        $previousMonth = now()->startOfMonth()->subMonth()->format('Y-m');

        // Current month not published yet -> falls back to the previous month.
        Http::fake([
            "download.db-ip.com/free/dbip-country-lite-{$currentMonth}.mmdb.gz" => Http::response('', 404),
            "download.db-ip.com/free/dbip-country-lite-{$previousMonth}.mmdb.gz" => Http::response(gzencode(file_get_contents($this->fixture))),
        ]);

        $this->artisan('geoip:update')->assertSuccessful();

        Http::assertSentCount(2);
        Http::assertSent(fn ($request) => ! $request->hasHeader('Authorization'));
        $this->assertSame('SE', (new MaxMindCountryResolver($target))->country('89.160.20.112'));

        File::deleteDirectory($tmp);
    }

    public function test_update_command_keeps_old_database_on_failure()
    {
        $tmp = sys_get_temp_dir().'/dbip-test-'.uniqid();
        File::ensureDirectoryExists($tmp);
        $target = $tmp.'/country.mmdb';
        copy($this->fixture, $target);

        config(['geoblock.database_path' => $target]);
        Http::fake(['download.db-ip.com/*' => Http::response(gzencode('kaputt'))]);

        $this->artisan('geoip:update')->assertFailed();

        $this->assertFileEquals($this->fixture, $target);
        File::deleteDirectory($tmp);
    }
}
