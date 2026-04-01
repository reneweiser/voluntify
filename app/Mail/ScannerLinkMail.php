<?php

namespace App\Mail;

use App\Models\ProjectScanner;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ScannerLinkMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public ProjectScanner $scanner,
        public string $url,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Scanner Access: {$this->scanner->name}",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.scanner-link',
            with: [
                'scannerName' => $this->scanner->name,
                'url' => $this->url,
                'startsAt' => $this->scanner->starts_at->format('M d, Y H:i'),
                'endsAt' => $this->scanner->ends_at->format('M d, Y H:i'),
            ],
        );
    }
}
