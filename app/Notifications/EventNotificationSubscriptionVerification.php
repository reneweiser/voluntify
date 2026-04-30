<?php

namespace App\Notifications;

use App\Models\Event;
use App\Notifications\Concerns\HasRetryStrategy;
use App\Notifications\Concerns\UsesOrganizationMailer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EventNotificationSubscriptionVerification extends Notification implements ShouldQueue
{
    use HasRetryStrategy;
    use Queueable;
    use UsesOrganizationMailer;

    public function __construct(
        public Event $event,
        public string $verificationUrl,
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject(__('Confirm shift notifications for :event', ['event' => $this->event->name]))
            ->greeting(__('Hello!'))
            ->line(__('You asked to be notified when new shifts become available for :event.', ['event' => $this->event->name]))
            ->line(__('Please confirm your email address to activate this notification.'))
            ->action(__('Confirm notifications'), $this->verificationUrl);

        return $this->applyOrgMailer($mail, $this->event->organization, $this->event->project);
    }
}
