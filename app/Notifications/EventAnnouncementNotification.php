<?php

namespace App\Notifications;

use App\Models\Event;
use App\Notifications\Concerns\UsesOrganizationMailer;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EventAnnouncementNotification extends Notification
{
    use Queueable;
    use UsesOrganizationMailer;

    public function __construct(
        public Event $event,
        public string $announcementSubject,
        public string $announcementBody,
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
        $mail = (new MailMessage)
            ->subject($this->announcementSubject)
            ->greeting("Hello {$notifiable->name}!")
            ->line("Update from **{$this->event->name}**:")
            ->line($this->announcementBody);

        return $this->applyOrgMailer($mail, $this->event->organization);
    }
}
