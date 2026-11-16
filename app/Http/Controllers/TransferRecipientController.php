<?php

namespace App\Http\Controllers;

use App\Actions\Transfers\SendTransferToRecipients;
use App\Http\Requests\Transfers\StoreTransferRecipientsRequest;
use App\Models\Transfer;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class TransferRecipientController extends Controller
{
    public function store(StoreTransferRecipientsRequest $request, Transfer $transfer, SendTransferToRecipients $sendToRecipients): RedirectResponse
    {
        abort_unless($transfer->isAvailable(), 409, 'Dieser Transfer ist nicht mehr verfügbar.');

        $sent = $sendToRecipients->handle($transfer, $request->validated('recipients'));

        Inertia::flash('toast', ['type' => 'success', 'message' => "Link an {$sent} Empfänger verschickt."]);

        return back();
    }
}
