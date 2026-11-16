<?php

namespace App\Mail;

use App\Models\Transfer;
use App\Models\TransferDownload;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TransferDownloaded extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Transfer $transfer, public TransferDownload $download)
    {
        $this->afterCommit();
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Dein Transfer wurde heruntergeladen: {$this->transfer->displayTitle()}",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.transfers.downloaded',
            with: [
                'fileName' => $this->download->file?->original_name,
                'url' => route('transfers.show', $this->transfer),
            ],
        );
    }
}
