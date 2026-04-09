<?php

namespace App\Notifications;

use App\Models\Project;
use App\Models\ShiftSignup;
use App\Notifications\Concerns\HasRetryStrategy;
use App\Notifications\Concerns\UsesOrganizationMailer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

class CancellationDigestNotification extends Notification implements ShouldQueue
{
    use HasRetryStrategy;
    use Queueable;
    use UsesOrganizationMailer;

    /**
     * @param  Collection<int, ShiftSignup>  $cancellations
     */
    public function __construct(
        public Project $project,
        public Collection $cancellations,
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
            ->subject('Stornierungen der letzten 6 Stunden')
            ->greeting("Stornierungsbericht — {$this->project->name}")
            ->line("In den letzten 6 Stunden wurden **{$this->cancellations->count()}** Anmeldungen storniert:");

        foreach ($this->cancellations as $signup) {
            $volunteer = $signup->volunteer;
            $shift = $signup->shift;
            $job = $shift->volunteerJob;
            $event = $job->event;

            $tz = $this->project->timezone ?? 'UTC';
            $timeInfo = $shift->hasDefinedTimes()
                ? $shift->shift_date->setTimezone($tz)->format('d.m.Y').' '.$shift->displayTimeRange($tz)
                : $shift->shift_date->setTimezone($tz)->format('d.m.Y').($shift->display_text ? " ({$shift->display_text})" : '');

            $mail->line("- **{$volunteer->full_name}**: {$event->name} / {$job->name} — {$timeInfo}");
        }

        return $this->applyOrgMailer($mail, $this->project->organization, $this->project);
    }
}
