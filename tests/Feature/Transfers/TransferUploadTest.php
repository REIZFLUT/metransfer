<?php

namespace Tests\Feature\Transfers;

use App\Enums\TransferStatus;
use App\Mail\TransferShared;
use App\Models\Transfer;
use App\Models\TransferFile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class TransferUploadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        config(['transfers.chunk_size' => 8]);
    }

    /**
     * @return TestResponse<\Illuminate\Http\Response>
     */
    protected function sendChunk(Transfer $transfer, TransferFile $file, int $offset, string $data): TestResponse
    {
        return $this->call(
            'POST',
            route('transfers.files.chunks.store', [$transfer, $file]),
            server: [
                'CONTENT_TYPE' => 'application/octet-stream',
                'CONTENT_LENGTH' => (string) strlen($data),
                'HTTP_UPLOAD_OFFSET' => (string) $offset,
                'HTTP_ACCEPT' => 'application/json',
            ],
            content: $data,
        );
    }

    /**
     * @return array{0: User, 1: Transfer, 2: TransferFile}
     */
    protected function draftWithFile(string $contents): array
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $transferId = $this->postJson(route('transfers.store'))->assertCreated()->json('id');
        $transfer = Transfer::findOrFail($transferId);

        $fileId = $this->postJson(route('transfers.files.store', $transfer), [
            'name' => '../../etc/hallo welt.txt',
            'size' => strlen($contents),
            'mime_type' => 'text/plain',
        ])->assertCreated()->json('id');

        return [$user, $transfer, TransferFile::findOrFail($fileId)];
    }

    public function test_guests_cannot_create_transfers()
    {
        $this->postJson(route('transfers.store'))->assertUnauthorized();
        $this->get(route('transfers.create'))->assertRedirect(route('login'));
    }

    public function test_file_names_are_sanitized()
    {
        [, , $file] = $this->draftWithFile('abc');

        $this->assertSame('hallo welt.txt', $file->original_name);
    }

    public function test_file_can_be_uploaded_in_chunks()
    {
        $contents = 'Hallo MeTransfer! 1234567890';
        [, $transfer, $file] = $this->draftWithFile($contents);

        foreach (str_split($contents, 8) as $index => $chunk) {
            $this->sendChunk($transfer, $file, $index * 8, $chunk)->assertOk();
        }

        $file->refresh();
        $this->assertTrue($file->isComplete());
        $this->assertSame($contents, Storage::disk('local')->get($file->path));
    }

    public function test_offset_mismatch_returns_current_offset_for_resume()
    {
        [, $transfer, $file] = $this->draftWithFile('0123456789ABCDEF');

        $this->sendChunk($transfer, $file, 0, '01234567')->assertOk()->assertJson(['offset' => 8]);

        // Client retries an old chunk / skips ahead: server tells it where to continue.
        $this->sendChunk($transfer, $file, 0, '01234567')->assertStatus(409)->assertJson(['offset' => 8]);
        $this->sendChunk($transfer, $file, 12, 'CDEF')->assertStatus(409)->assertJson(['offset' => 8]);

        $this->getJson(route('transfers.files.show', [$transfer, $file]))->assertJson(['received_bytes' => 8]);

        $this->sendChunk($transfer, $file, 8, '89ABCDEF')->assertOk()->assertJson(['complete' => true]);
        $this->assertSame('0123456789ABCDEF', Storage::disk('local')->get($file->fresh()->path));
    }

    public function test_chunks_larger_than_the_limit_are_rejected()
    {
        [, $transfer, $file] = $this->draftWithFile(str_repeat('x', 20));

        $this->sendChunk($transfer, $file, 0, str_repeat('x', 9))->assertStatus(413);
    }

    public function test_chunks_cannot_exceed_declared_size()
    {
        [, $transfer, $file] = $this->draftWithFile('abc');

        $this->sendChunk($transfer, $file, 0, 'abcdef')->assertStatus(422);
    }

    public function test_other_users_cannot_upload_into_a_transfer()
    {
        [, $transfer, $file] = $this->draftWithFile('abc');

        $this->actingAs(User::factory()->create());

        $this->sendChunk($transfer, $file, 0, 'abc')->assertForbidden();
        $this->postJson(route('transfers.files.store', $transfer), ['name' => 'x', 'size' => 1])->assertForbidden();
    }

    public function test_transfer_can_be_published_and_sent_to_recipients()
    {
        Mail::fake();

        [, $transfer, $file] = $this->draftWithFile('abc');
        $this->sendChunk($transfer, $file, 0, 'abc')->assertOk();

        $this->post(route('transfers.publish', $transfer), [
            'title' => 'Fotos',
            'message' => 'Viel Spaß',
            'expires_in_days' => 7,
            'password' => 'geheim',
            'recipients' => ['A@Example.com', 'a@example.com', 'b@example.com'],
            'notify_on_download' => true,
        ])->assertRedirect(route('transfers.show', $transfer));

        $transfer->refresh();
        $this->assertSame(TransferStatus::Ready, $transfer->status);
        $this->assertTrue($transfer->isPasswordProtected());
        $this->assertTrue($transfer->expires_at->between(now()->addDays(7)->subMinute(), now()->addDays(7)->addMinute()));
        $this->assertSame(['a@example.com', 'b@example.com'], $transfer->recipients()->orderBy('email')->pluck('email')->all());

        Mail::assertQueued(TransferShared::class, 2);
        Mail::assertQueued(TransferShared::class, fn (TransferShared $mail) => $mail->hasTo('b@example.com'));
    }

    public function test_transfer_cannot_be_published_with_incomplete_files()
    {
        [, $transfer, $file] = $this->draftWithFile('abcdef');
        $this->sendChunk($transfer, $file, 0, 'abc')->assertOk();

        $this->post(route('transfers.publish', $transfer), ['expires_in_days' => 7])
            ->assertSessionHasErrors('files');

        $this->assertSame(TransferStatus::Draft, $transfer->fresh()->status);
    }

    public function test_invalid_expiry_is_rejected()
    {
        [, $transfer, $file] = $this->draftWithFile('abc');
        $this->sendChunk($transfer, $file, 0, 'abc');

        $this->post(route('transfers.publish', $transfer), ['expires_in_days' => 5])
            ->assertSessionHasErrors('expires_in_days');
    }

    public function test_owner_can_view_dashboard_and_transfer()
    {
        $transfer = Transfer::factory()->published()->create();
        $this->actingAs($transfer->user);

        $this->get(route('dashboard'))->assertOk()->assertInertia(fn ($page) => $page
            ->component('dashboard')
            ->has('transfers', 1));

        $this->get(route('transfers.show', $transfer))->assertOk()->assertInertia(fn ($page) => $page
            ->component('transfers/show')
            ->where('transfer.id', $transfer->id));
    }

    public function test_other_users_cannot_view_or_delete_a_transfer()
    {
        $transfer = Transfer::factory()->published()->create();
        $this->actingAs(User::factory()->create());

        $this->get(route('transfers.show', $transfer))->assertForbidden();
        $this->delete(route('transfers.destroy', $transfer))->assertForbidden();
        $this->assertModelExists($transfer);
    }

    public function test_owner_can_delete_a_transfer_and_its_files()
    {
        $transfer = Transfer::factory()->published()->create();
        Storage::disk('local')->put($transfer->storageDirectory().'/abc', 'data');
        $this->actingAs($transfer->user);

        $this->delete(route('transfers.destroy', $transfer))->assertRedirect(route('dashboard'));

        $this->assertModelMissing($transfer);
        Storage::disk('local')->assertMissing($transfer->storageDirectory().'/abc');
    }

    public function test_expiry_can_be_extended_and_password_removed()
    {
        $transfer = Transfer::factory()->published()->withPassword()->create(['expires_at' => now()->addDay()]);
        $this->actingAs($transfer->user);

        $this->patch(route('transfers.update', $transfer), ['expires_in_days' => 0, 'remove_password' => true])
            ->assertRedirect();

        $transfer->refresh();
        $this->assertNull($transfer->expires_at);
        $this->assertFalse($transfer->isPasswordProtected());
    }
}
