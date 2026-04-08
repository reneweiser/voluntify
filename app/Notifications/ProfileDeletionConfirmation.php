<?php

namespace App\Notifications;

use App\Models\Project;
use App\Notifications\Concerns\UsesOrganizationMailer;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ProfileDeletionConfirmation extends Notification
{
    use UsesOrganizationMailer;

    public function __construct(
        public Project $project,
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
            ->subject('Dein Profil wurde gelöscht')
            ->greeting("Hallo {$notifiable->first_name}!")
            ->line('Dein Volunteer-Profil und alle zugehörigen Daten wurden auf deine Anfrage hin gelöscht.')
            ->line('**Folgende Daten wurden unwiderruflich entfernt:**')
            ->line('- Alle Schicht-Anmeldungen')
            ->line('- Eventuelle Tickets und QR-Codes')
            ->line('- Gear-Zuweisungen')
            ->line('- Persönliche Daten (Name, E-Mail, Telefon)')
            ->line('Dieser Vorgang kann nicht rückgängig gemacht werden. Falls du erneut als Volunteer teilnehmen möchtest, musst du dich neu registrieren.')
            ->line('Vielen Dank für dein Engagement!');

        return $this->applyOrgMailer($mail, $this->project->organization, $this->project);
    }
}
