<?php

namespace Tests\Feature\Transfers;

use App\Models\Transfer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PruneTransfersTest extends TestCase
{
    use RefreshDatabase;

    public function test_expired_transfers_and_stale_drafts_are_pruned()
    {
        Storage::fake('local');

        $expired = Transfer::factory()->expired()->create();
        $active = Transfer::factory()->published()->create();
        $unlimited = Transfer::factory()->published()->create(['expires_at' => null]);
        $freshDraft = Transfer::factory()->create();
        $staleDraft = Transfer::factory()->create();
        $staleDraft->forceFill(['created_at' => now()->subDays(2)])->save();

        foreach ([$expired, $active, $staleDraft] as $transfer) {
            Storage::disk('local')->put($transfer->storageDirectory().'/file', 'x');
        }

        $this->artisan('transfers:prune')->assertSuccessful();

        $this->assertModelMissing($expired);
        $this->assertModelMissing($staleDraft);
        $this->assertModelExists($active);
        $this->assertModelExists($unlimited);
        $this->assertModelExists($freshDraft);

        Storage::disk('local')->assertMissing($expired->storageDirectory().'/file');
        Storage::disk('local')->assertMissing($staleDraft->storageDirectory().'/file');
        Storage::disk('local')->assertExists($active->storageDirectory().'/file');
    }
}
