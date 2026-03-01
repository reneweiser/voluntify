<?php

namespace App\Notifications;

use App\Models\Event;
use App\Models\Shift;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SignupConfirmation extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Event $event,
        public Shift $shift,
        public string $magicLinkToken,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $job = $this->shift->volunteerJob;

        return (new MailMessage)
            ->subject("You're signed up for {$this->event->name}!")
            ->greeting("Hello {$notifiable->name}!")
            ->line("You've been signed up for **{$this->event->name}**.")
            ->line("**Job:** {$job->name}")
            ->line("**Shift:** {$this->shift->starts_at->format('M d, Y g:i A')} — {$this->shift->ends_at->format('g:i A')}")
            ->when($this->event->location, fn (MailMessage $mail) => $mail->line("**Location:** {$this->event->location}"))
            ->line('You will receive your ticket with a QR code via a separate link.')
            ->line('Thank you for volunteering!');
    }
}
