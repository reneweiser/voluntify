<?php

namespace App\Notifications;

use App\Models\Event;
use App\Models\ShiftSignup;
use App\Notifications\Concerns\HasRetryStrategy;
use App\Notifications\Concerns\UsesOrganizationMailer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ImmediateCancellationNotification extends Notification implements ShouldQueue
{
    use HasRetryStrategy;
    use Queueable;
    use UsesOrganizationMailer;

    public function __construct(
        public Event $event,
        public ShiftSignup $signup,
        public string $volunteerName,
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
        $this->signup->loadMissing('shift.volunteerJob');
        $shift = $this->signup->shift;
        $job = $shift->volunteerJob;
        $project = $this->event->project;

        $tz = $project->timezone ?? 'UTC';
        $timeInfo = $shift->hasDefinedTimes()
            ? $shift->shift_date->setTimezone($tz)->format('d.m.Y').' '.$shift->displayTimeRange($tz)
            : $shift->shift_date->setTimezone($tz)->format('d.m.Y').($shift->display_text ? " ({$shift->display_text})" : '');

        $mail = (new MailMessage)
            ->subject("Stornierung: {$this->volunteerName} — {$this->event->name}")
            ->greeting('Hallo!')
            ->line("**{$this->volunteerName}** hat eine Schichtanmeldung storniert:")
            ->line("- **Event:** {$this->event->name}")
            ->line("- **Aufgabe:** {$job->name}")
            ->line("- **Schicht:** {$timeInfo}")
            ->line('Bitte prüfe, ob eine Nachbesetzung erforderlich ist.');

        return $this->applyOrgMailer($mail, $this->event->organization, $project);
    }
}
