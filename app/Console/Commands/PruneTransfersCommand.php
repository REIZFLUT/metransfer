<?php

namespace App\Console\Commands;

use App\Models\Transfer;
use Illuminate\Console\Command;

class PruneTransfersCommand extends Command
{
    protected $signature = 'transfers:prune';

    protected $description = 'Abgelaufene Transfers und abgebrochene Uploads samt Dateien löschen';

    public function handle(): int
    {
        $expired = 0;
        $drafts = 0;

        Transfer::query()->expired()->each(function (Transfer $transfer) use (&$expired) {
            $transfer->delete();
            $expired++;
        });

        Transfer::query()->staleDrafts()->each(function (Transfer $transfer) use (&$drafts) {
            $transfer->delete();
            $drafts++;
        });

        $this->components->info("{$expired} abgelaufene Transfer(s) und {$drafts} abgebrochene Upload(s) gelöscht.");

        return self::SUCCESS;
    }
}
