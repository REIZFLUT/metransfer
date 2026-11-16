<?php

namespace App\Actions\Transfers;

use App\Mail\TransferDownloaded;
use App\Models\Transfer;
use App\Models\TransferDownload;
use App\Models\TransferFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class RecordTransferDownload
{
    /**
     * Log a download and notify the owner (rate limited per transfer).
     */
    public function handle(Transfer $transfer, Request $request, ?TransferFile $file = null): void
    {
        // Range requests for the same download (resume, media seeking) are only counted once.
        $range = (string) $request->header('Range', '');

        if ($range !== '' && ! Str::startsWith($range, 'bytes=0-')) {
            return;
        }

        $download = new TransferDownload([
            'transfer_file_id' => $file?->id,
            'ip_address' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 500, ''),
        ]);
        $download->transfer()->associate($transfer);
        $download->save();

        $transfer->increment('download_count');

        if (! $transfer->notify_on_download) {
            return;
        }

        $cooldown = (int) config('transfers.notification_cooldown_minutes');

        if ($transfer->last_notified_at && $transfer->last_notified_at->addMinutes($cooldown)->isFuture()) {
            return;
        }

        $transfer->forceFill(['last_notified_at' => now()])->save();

        Mail::to($transfer->user)->queue(new TransferDownloaded($transfer, $download));
    }
}
