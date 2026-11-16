<?php

namespace App\Actions\Transfers;

use App\Mail\TransferShared;
use App\Models\Transfer;
use Illuminate\Support\Facades\Mail;

class SendTransferToRecipients
{
    /**
     * Store the given recipients and queue a notification mail for each.
     *
     * @param  list<string>  $emails
     */
    public function handle(Transfer $transfer, array $emails): int
    {
        $sent = 0;

        foreach ($emails as $email) {
            $recipient = $transfer->recipients()->firstOrCreate(['email' => $email]);

            Mail::to($recipient->email)->queue(new TransferShared($transfer));

            $recipient->forceFill(['sent_at' => now()])->save();
            $sent++;
        }

        return $sent;
    }
}
