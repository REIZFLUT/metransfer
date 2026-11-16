<?php

namespace App\Mail;

use App\Models\Transfer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TransferShared extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Transfer $transfer)
    {
        $this->afterCommit();
    }

    public function envelope(): Envelope
    {
        $sender = $this->transfer->user;

        return new Envelope(
            replyTo: [new Address($sender->email, $sender->name)],
            subject: "{$sender->name} hat dir Dateien geschickt: {$this->transfer->displayTitle()}",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.transfers.shared',
            with: [
                'sender' => $this->transfer->user,
                'files' => $this->transfer->files()->orderBy('original_name')->get(),
                'url' => $this->transfer->publicUrl(),
            ],
        );
    }
}
