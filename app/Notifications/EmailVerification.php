<?php

namespace App\Notifications;

use App\Enums\EmailTemplateType;
use App\Models\Event;
use App\Notifications\Concerns\UsesOrganizationMailer;
use App\Services\EmailTemplateRenderer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EmailVerification extends Notification implements ShouldQueue
{
    use Queueable;
    use UsesOrganizationMailer;

    public function __construct(
        public Event $event,
        public string $verificationUrl,
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
        $renderer = app(EmailTemplateRenderer::class);
        $rendered = $renderer->render(
            EmailTemplateType::EmailVerification,
            $this->event,
            [
                // #81 - German placeholders (primary)
                'vorname' => $notifiable->first_name,
                'nachname' => $notifiable->last_name,
                // Legacy placeholders (backwards compatibility)
                'volunteer_name' => $notifiable->full_name,
                'event_name' => $this->event->name,
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

        $mail->action('E-Mail bestätigen & Anmeldung abschließen', $this->verificationUrl);

        return $this->applyOrgMailer($mail, $this->event->organization);
    }
}
