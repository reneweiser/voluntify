<?php

namespace App\Notifications;

use App\Models\Project;
use App\Notifications\Concerns\UsesOrganizationMailer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PortalAccessLink extends Notification implements ShouldQueue
{
    use Queueable;
    use UsesOrganizationMailer;

    public function __construct(
        public Project $project,
        public string $plainToken,
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
        $portalUrl = route('volunteer.portal', $this->plainToken);
        $projectName = $this->project->name;

        $mail = (new MailMessage)
            ->subject("Dein Zugangslink für {$projectName}")
            ->greeting("Hallo {$notifiable->first_name}!")
            ->line("Du hast einen neuen Zugangslink für **{$projectName}** angefordert.")
            ->line('Klicke auf den Button, um dein Volunteer-Portal zu öffnen.')
            ->action('Portal öffnen', $portalUrl)
            ->line('Dieser Link ist 72 Stunden gültig.')
            ->line('Falls du keinen Zugangslink angefordert hast, kannst du diese E-Mail ignorieren.');

        return $this->applyOrgMailer($mail, $this->project->organization, $this->project);
    }
}
