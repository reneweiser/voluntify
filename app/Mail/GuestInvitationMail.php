<?php

namespace App\Mail;

use App\Models\GuestEntry;
use App\Models\GuestList;
use Illuminate\Bus\Queueable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class GuestInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  Collection<int, GuestEntry>  $entries
     */
    public function __construct(
        public GuestList $guestList,
        public Collection $entries,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Your Guest Pass — {$this->guestList->name}",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.guest-invitation',
            with: [
                'guestListName' => $this->guestList->name,
                'projectName' => $this->guestList->project->name,
                'entries' => $this->entries,
            ],
        );
    }
}
