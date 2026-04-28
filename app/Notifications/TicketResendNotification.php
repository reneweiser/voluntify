<?php

namespace App\Notifications;

use App\Models\Project;
use App\Notifications\Concerns\HasRetryStrategy;
use App\Notifications\Concerns\UsesOrganizationMailer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TicketResendNotification extends Notification implements ShouldQueue
{
    use HasRetryStrategy;
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
        $ticketUrl = route('volunteer.ticket', $this->plainToken);
        $projectName = $this->project->name;

        $mail = (new MailMessage)
            ->subject("Dein QR-Code für {$projectName}")
            ->greeting("Hallo {$notifiable->first_name}!")
            ->line("Hier ist dein QR-Code-Zugang für **{$projectName}**.")
            ->line('Klicke auf den Button, um dein Ticket mit QR-Code anzuzeigen.')
            ->action('Ticket anzeigen', $ticketUrl)
            ->line('Du kannst auch dein Volunteer-Portal über folgenden Link erreichen:')
            ->line("[Portal öffnen]({$portalUrl})");

        return $this->applyOrgMailer($mail, $this->project->organization, $this->project);
    }
}
