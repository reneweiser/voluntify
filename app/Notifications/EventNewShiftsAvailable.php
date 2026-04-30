<?php

namespace App\Notifications;

use App\Models\Event;
use App\Notifications\Concerns\HasRetryStrategy;
use App\Notifications\Concerns\UsesOrganizationMailer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EventNewShiftsAvailable extends Notification implements ShouldQueue
{
    use HasRetryStrategy;
    use Queueable;
    use UsesOrganizationMailer;

    public function __construct(
        public Event $event,
        public string $signupUrl,
        public string $unsubscribeUrl,
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
            ->subject(__('New shifts are available for :event', ['event' => $this->event->name]))
            ->greeting(__('Hello!'))
            ->line(__('New shifts are now available for :event.', ['event' => $this->event->name]))
            ->line(__('Open the signup page to choose your shifts.'))
            ->action(__('Open signup page'), $this->signupUrl)
            ->line(__('If you no longer want these updates, you can unsubscribe here: :url', ['url' => $this->unsubscribeUrl]));

        return $this->applyOrgMailer($mail, $this->event->organization, $this->event->project);
    }
}
