<?php

namespace Tests\Feature\Transfers;

use App\Mail\TransferDownloaded;
use App\Models\Transfer;
use App\Models\TransferFile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PublicTransferTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Mail::fake();
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

    public function test_public_page_shows_files()
    {
        $transfer = Transfer::factory()->published()->create(['title' => 'Urlaub']);
        $this->addFile($transfer, 'foto.jpg', 'jpg');

        $this->get(route('public.transfers.show', $transfer->token))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('public/transfer')
                ->where('transfer.title', 'Urlaub')
                ->has('transfer.files', 1));
    }

    public function test_unknown_token_shows_unavailable_page()
    {
        $this->get(route('public.transfers.show', 'does-not-exist'))
            ->assertNotFound()
            ->assertInertia(fn ($page) => $page->component('public/unavailable'));
    }

    public function test_expired_and_draft_transfers_are_unavailable()
    {
        $expired = Transfer::factory()->expired()->create();
        $draft = Transfer::factory()->create();
        $file = $this->addFile($expired, 'a.txt', 'a');

        $this->get(route('public.transfers.show', $expired->token))->assertStatus(410);
        $this->get(route('public.transfers.download', [$expired->token, $file->id]))->assertNotFound();
        $this->get(route('public.transfers.show', $draft->token))->assertStatus(410);
    }

    public function test_file_can_be_downloaded_and_is_logged()
    {
        $transfer = Transfer::factory()->published()->create();
        $file = $this->addFile($transfer, 'bericht.pdf', 'PDF-CONTENT');

        $response = $this->get(route('public.transfers.download', [$transfer->token, $file->id]));

        $response->assertOk()->assertDownload('bericht.pdf');
        $this->assertSame(1, $transfer->fresh()->download_count);
        $this->assertDatabaseHas('transfer_downloads', ['transfer_id' => $transfer->id, 'transfer_file_id' => $file->id]);
        Mail::assertQueued(TransferDownloaded::class, fn ($mail) => $mail->hasTo($transfer->user->email));
    }

    public function test_download_notifications_are_throttled()
    {
        $transfer = Transfer::factory()->published()->create();
        $file = $this->addFile($transfer, 'a.txt', 'a');

        $this->get(route('public.transfers.download', [$transfer->token, $file->id]))->assertOk();
        $this->get(route('public.transfers.download', [$transfer->token, $file->id]))->assertOk();

        $this->assertSame(2, $transfer->fresh()->download_count);
        Mail::assertQueued(TransferDownloaded::class, 1);
    }

    public function test_files_of_other_transfers_cannot_be_downloaded()
    {
        $transfer = Transfer::factory()->published()->create();
        $other = Transfer::factory()->published()->create();
        $file = $this->addFile($other, 'secret.txt', 'secret');

        $this->get(route('public.transfers.download', [$transfer->token, $file->id]))->assertNotFound();
    }

    public function test_password_protected_transfer_requires_password()
    {
        $transfer = Transfer::factory()->published()->withPassword('geheim')->create();
        $file = $this->addFile($transfer, 'a.txt', 'a');

        $this->get(route('public.transfers.show', $transfer->token))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('public/password'));
        $this->get(route('public.transfers.download', [$transfer->token, $file->id]))->assertForbidden();
        $this->get(route('public.transfers.zip', $transfer->token))->assertForbidden();

        $this->post(route('public.transfers.unlock', $transfer->token), ['password' => 'falsch'])
            ->assertSessionHasErrors('password');

        $this->post(route('public.transfers.unlock', $transfer->token), ['password' => 'geheim'])
            ->assertRedirect(route('public.transfers.show', $transfer->token));

        $this->get(route('public.transfers.show', $transfer->token))
            ->assertInertia(fn ($page) => $page->component('public/transfer'));
        $this->get(route('public.transfers.download', [$transfer->token, $file->id]))->assertOk();
    }

    public function test_password_attempts_are_rate_limited()
    {
        $transfer = Transfer::factory()->published()->withPassword('geheim')->create();

        foreach (range(1, 5) as $ignored) {
            $this->post(route('public.transfers.unlock', $transfer->token), ['password' => 'falsch']);
        }

        $this->post(route('public.transfers.unlock', $transfer->token), ['password' => 'geheim'])
            ->assertTooManyRequests();
    }

    public function test_all_files_can_be_downloaded_as_zip()
    {
        $transfer = Transfer::factory()->published()->create(['title' => 'Mein Transfer']);
        $this->addFile($transfer, 'a.txt', 'AAA');
        $this->addFile($transfer, 'b.txt', 'BBB');

        $response = $this->get(route('public.transfers.zip', $transfer->token));
        $response->assertOk()->assertDownload('mein-transfer.zip');

        $zipPath = tempnam(sys_get_temp_dir(), 'zip');
        file_put_contents($zipPath, $response->streamedContent());

        $zip = new \ZipArchive;
        $this->assertTrue($zip->open($zipPath));
        $this->assertSame('AAA', $zip->getFromName('a.txt'));
        $this->assertSame('BBB', $zip->getFromName('b.txt'));
        $zip->close();
        unlink($zipPath);

        $this->assertDatabaseHas('transfer_downloads', ['transfer_id' => $transfer->id, 'transfer_file_id' => null]);
    }
}
