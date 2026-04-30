<?php

namespace App\Notifications;

use App\Enums\EmailTemplateType;
use App\Models\Event;
use App\Notifications\Concerns\HasRetryStrategy;
use App\Notifications\Concerns\UsesOrganizationMailer;
use App\Services\EmailTemplateRenderer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class VolunteerEventUpdatedNotification extends Notification implements ShouldQueue
{
    use HasRetryStrategy;
    use Queueable;
    use UsesOrganizationMailer;

    public function __construct(
        public Event $event,
        public string $organizerNote,
        public string $magicLinkToken,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $renderer = app(EmailTemplateRenderer::class);
        $rendered = $renderer->render(
            EmailTemplateType::EventUpdated,
            $this->event,
            [
                'vorname' => $notifiable->first_name,
                'nachname' => $notifiable->last_name,
                'event_name' => $this->event->name,
                'organizer_note' => $this->organizerNote,
                'portal_link' => route('volunteer.portal', $this->magicLinkToken),
            ],
        );

        $mail = (new MailMessage)
            ->subject($rendered['subject'])
            ->greeting("Hallo {$notifiable->first_name}!");

        foreach (explode("\n", $rendered['body']) as $line) {
            $trimmed = trim($line);

            if ($trimmed !== '') {
                $mail->line($trimmed);
            }
        }

        $portalUrl = route('volunteer.portal', $this->magicLinkToken);
        $mail->action('Portal oeffnen', $portalUrl);

        return $this->applyOrgMailer($mail, $this->event->organization, $this->event->project);
    }
}
