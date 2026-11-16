<?php

namespace Tests\Feature;

use App\Mail\TransferShared;
use App\Models\Transfer;
use App\Models\TransferFile;
use App\Models\User;
use App\Support\Markdown;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    protected function addFile(Transfer $transfer, string $name, string $contents): TransferFile
    {
        $file = $transfer->files()->create([
            'original_name' => $name,
            'path' => $transfer->storageDirectory().'/'.md5($name),
            'size' => strlen($contents),
        ]);
        $file->forceFill(['received_bytes' => strlen($contents), 'completed_at' => now()])->save();
        Storage::disk('local')->put($file->path, $contents);

        return $file;
    }

    public function test_uploaded_html_is_never_served_as_html()
    {
        $transfer = Transfer::factory()->published()->create();
        $file = $this->addFile($transfer, 'evil.html', '<html><script>alert(document.cookie)</script></html>');

        $response = $this->get(route('public.transfers.download', [$transfer->token, $file->id]));

        $response->assertOk()
            ->assertHeader('Content-Type', 'application/octet-stream')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Content-Security-Policy', "default-src 'none'; sandbox");
        $this->assertStringStartsWith('attachment;', (string) $response->headers->get('Content-Disposition'));
    }

    public function test_owner_download_uses_the_same_safe_headers()
    {
        $transfer = Transfer::factory()->published()->create();
        $file = $this->addFile($transfer, 'bild.svg', '<svg onload="alert(1)"></svg>');

        $this->actingAs($transfer->user)
            ->get(route('transfers.files.download', [$transfer, $file]))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/octet-stream')
            ->assertHeader('Content-Security-Policy', "default-src 'none'; sandbox");
    }

    public function test_zip_download_is_sandboxed()
    {
        $transfer = Transfer::factory()->published()->create();
        $this->addFile($transfer, 'a.txt', 'a');
        $this->addFile($transfer, 'b.txt', 'b');

        $this->get(route('public.transfers.zip', $transfer->token))
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Content-Security-Policy', "default-src 'none'; sandbox");
    }

    public function test_pages_send_security_headers_with_csp_nonce()
    {
        $response = $this->get(route('login'));

        $response->assertOk()
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Referrer-Policy', 'same-origin')
            ->assertHeader('X-Content-Type-Options', 'nosniff');

        $csp = (string) $response->headers->get('Content-Security-Policy');
        $this->assertStringContainsString("frame-ancestors 'none'", $csp);
        $this->assertStringContainsString("object-src 'none'", $csp);
        $this->assertMatchesRegularExpression("/script-src 'self' 'nonce-([A-Za-z0-9]+)'/", $csp);

        preg_match("/'nonce-([A-Za-z0-9]+)'/", $csp, $matches);
        $this->assertStringContainsString('<script nonce="'.$matches[1].'">', $response->getContent());
        $this->assertFalse($response->headers->has('X-Powered-By'));
    }

    public function test_response_headers_stay_small_enough_for_nginx()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $this->assertFalse($response->headers->has('Link'));

        // nginx' default proxy/fastcgi buffer is 4 KB; larger headers cause 502 Bad Gateway.
        $this->assertLessThan(3072, strlen((string) $response->headers));
    }

    public function test_appearance_cookie_cannot_inject_into_the_page()
    {
        $payload = "x';alert(1);//";

        $response = $this->withUnencryptedCookie('appearance', $payload)->get(route('login'));

        $response->assertOk();
        $this->assertStringNotContainsString('alert(1)', $response->getContent());
        $this->assertStringContainsString("const appearance = 'system';", $response->getContent());
    }

    public function test_file_names_cannot_inject_markdown_into_mails()
    {
        $transfer = Transfer::factory()->published()->create(['title' => null]);
        $this->addFile($transfer, '[Jetzt klicken](https://evil.example).pdf', 'x');
        $this->addFile($transfer, 'zweite.txt', 'y');

        $html = (new TransferShared($transfer->fresh()))->render();

        $this->assertStringNotContainsString('href="https://evil.example"', $html);
        $this->assertStringContainsString('Jetzt klicken', $html);
    }

    public function test_markdown_escape()
    {
        $this->assertSame('\\[a\\]\\(b\\)', Markdown::escape('[a](b)'));
        $this->assertSame('Fotos 2026', Markdown::escape('Fotos 2026'));
    }
}
