<?php

namespace Tests\Feature\GeoBlock;

use App\Models\Transfer;
use App\Models\User;
use App\Support\GeoIp\CountryResolver;
use App\Support\GeoIp\GeoIpDatabaseMissing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RestrictToCountriesTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<string, string|null> */
    protected array $countries = [
        '91.0.0.1' => 'DE',
        '81.0.0.1' => 'AT',
        '8.8.8.8' => 'US',
    ];

    protected bool $databaseMissing = false;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'geoblock.enabled' => true,
            'geoblock.countries' => ['DE'],
            'geoblock.allowed_ips' => [],
        ]);

        $this->app->instance(CountryResolver::class, new class($this) implements CountryResolver
        {
            public function __construct(private RestrictToCountriesTest $test) {}

            public function country(string $ip): ?string
            {
                return $this->test->lookup($ip);
            }
        });
    }

    public function lookup(string $ip): ?string
    {
        if ($this->databaseMissing) {
            throw new GeoIpDatabaseMissing('missing');
        }

        return $this->countries[$ip] ?? null;
    }

    protected function fromIp(string $ip): static
    {
        return $this->withServerVariables(['REMOTE_ADDR' => $ip]);
    }

    public function test_login_is_reachable_from_germany()
    {
        $this->fromIp('91.0.0.1')->get(route('login'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('geoipAttribution', true));
    }

    public function test_login_is_blocked_from_other_countries()
    {
        $this->fromIp('8.8.8.8')->get(route('login'))
            ->assertForbidden()
            ->assertInertia(fn ($page) => $page->component('public/blocked'));

        $this->fromIp('8.8.8.8')->post(route('login.store'), [
            'email' => 'x@example.com',
            'password' => 'password',
        ])->assertForbidden();
    }

    public function test_management_pages_are_blocked_even_when_logged_in()
    {
        $this->actingAs(User::factory()->create());

        $this->fromIp('8.8.8.8')->get(route('dashboard'))->assertForbidden();
        $this->fromIp('8.8.8.8')->postJson(route('transfers.store'))->assertForbidden();
        $this->fromIp('91.0.0.1')->get(route('dashboard'))->assertOk();
    }

    public function test_unknown_ips_are_blocked()
    {
        $this->fromIp('10.0.0.1')->get(route('login'))->assertForbidden();
    }

    public function test_requests_are_blocked_when_database_is_missing()
    {
        $this->databaseMissing = true;

        $this->fromIp('91.0.0.1')->get(route('login'))->assertForbidden();
    }

    public function test_public_download_links_are_reachable_worldwide()
    {
        $transfer = Transfer::factory()->published()->create();

        $this->fromIp('8.8.8.8')->get(route('public.transfers.show', $transfer->token))->assertOk();
        $this->fromIp('10.0.0.1')->get(route('public.transfers.show', 'unbekannt'))->assertNotFound();
    }

    public function test_allowlisted_ips_and_ranges_are_allowed()
    {
        config(['geoblock.allowed_ips' => ['8.8.8.8', '10.0.0.0/8', '2001:db8::/32']]);

        $this->fromIp('8.8.8.8')->get(route('login'))->assertOk();
        $this->fromIp('10.20.30.40')->get(route('login'))->assertOk();
        $this->fromIp('2001:db8::1')->get(route('login'))->assertOk();
        $this->fromIp('81.0.0.1')->get(route('login'))->assertForbidden();
    }

    public function test_additional_countries_can_be_configured()
    {
        config(['geoblock.countries' => ['DE', 'AT']]);

        $this->fromIp('81.0.0.1')->get(route('login'))->assertOk();
        $this->fromIp('8.8.8.8')->get(route('login'))->assertForbidden();
    }

    public function test_nothing_is_blocked_when_disabled()
    {
        config(['geoblock.enabled' => false]);

        $this->fromIp('8.8.8.8')->get(route('login'))->assertOk();
    }

    public function test_health_check_is_not_blocked()
    {
        $this->fromIp('8.8.8.8')->get('/up')->assertOk();
    }

    public function test_lookup_command_reports_country_and_access()
    {
        $this->artisan('geoip:lookup', ['ip' => '91.0.0.1'])
            ->expectsOutputToContain('DE')
            ->expectsOutputToContain('erlaubt')
            ->assertSuccessful();

        $this->artisan('geoip:lookup', ['ip' => '8.8.8.8'])
            ->expectsOutputToContain('gesperrt')
            ->assertSuccessful();
    }

    public function test_update_command_requires_maxmind_credentials()
    {
        Http::fake();
        config(['geoblock.maxmind.account_id' => null, 'geoblock.maxmind.license_key' => null]);

        $this->artisan('geoip:update', ['--source' => 'maxmind'])->assertFailed();
        Http::assertNothingSent();
    }

    public function test_update_command_reports_failed_download()
    {
        Http::fake(['download.maxmind.com/*' => Http::response('Unauthorized', 401)]);
        config(['geoblock.maxmind.account_id' => '123', 'geoblock.maxmind.license_key' => 'falsch']);

        $this->artisan('geoip:update', ['--source' => 'maxmind'])
            ->expectsOutputToContain('HTTP 401')
            ->assertFailed();
    }
}
