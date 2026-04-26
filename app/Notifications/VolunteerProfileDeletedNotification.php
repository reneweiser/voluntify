<?php

namespace App\Notifications;

use App\Models\Project;
use App\Notifications\Concerns\HasRetryStrategy;
use App\Notifications\Concerns\UsesOrganizationMailer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class VolunteerProfileDeletedNotification extends Notification implements ShouldQueue
{
    use HasRetryStrategy;
    use Queueable;
    use UsesOrganizationMailer;

    public function __construct(
        public string $volunteerName,
        public Project $project,
        public string $shiftSummary,
        public ?string $deletedByName = null,
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
        $deletionLine = $this->deletedByName
            ? "**{$this->volunteerName}** wurde im Projekt **{$this->project->name}** durch **{$this->deletedByName}** gelöscht."
            : "**{$this->volunteerName}** hat sein Volunteer-Profil im Projekt **{$this->project->name}** gelöscht.";

        $mail = (new MailMessage)
            ->subject("Volunteer-Profil gelöscht: {$this->volunteerName}")
            ->greeting("Hallo {$notifiable->name}!")
            ->line($deletionLine)
            ->line('Alle zugehörigen Daten (Anmeldungen, Tickets, Gear) wurden unwiderruflich entfernt.');

        if ($this->shiftSummary !== '') {
            $mail->line('**Betroffene kommende Schichten (jetzt unterbesetzt):**');
            foreach (explode("\n", $this->shiftSummary) as $line) {
                $trimmed = trim($line);
                if ($trimmed !== '') {
                    $mail->line($trimmed);
                }
            }
        }

        $mail->line('Bitte prüfe, ob eine Nachbesetzung erforderlich ist.');

        return $this->applyOrgMailer($mail, $this->project->organization, $this->project);
    }
}
