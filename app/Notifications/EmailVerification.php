<?php

namespace App\Notifications;

use App\Enums\EmailTemplateType;
use App\Models\Event;
use App\Notifications\Concerns\HasRetryStrategy;
use App\Notifications\Concerns\UsesOrganizationMailer;
use App\Services\EmailTemplateRenderer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EmailVerification extends Notification implements ShouldQueue
{
    use HasRetryStrategy;
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
        $isAnonymous = $notifiable instanceof AnonymousNotifiable;

        $renderer = app(EmailTemplateRenderer::class);
        $rendered = $renderer->render(
            EmailTemplateType::EmailVerification,
            $this->event,
            [
                // #81 - German placeholders (primary)
                'vorname' => $isAnonymous ? '' : $notifiable->first_name,
                'nachname' => $isAnonymous ? '' : $notifiable->last_name,
                // Legacy placeholders (backwards compatibility)
                'volunteer_name' => $isAnonymous ? '' : $notifiable->full_name,
                'event_name' => $this->event->name,
            ],
        );

        $greeting = $isAnonymous ? 'Hallo!' : "Hallo {$notifiable->first_name}!";

        $mail = (new MailMessage)
            ->subject($rendered['subject'])
            ->greeting($greeting);

        foreach (explode("\n", $rendered['body']) as $line) {
            $trimmed = trim($line);
            if ($trimmed !== '') {
                $mail->line($trimmed);
            }
        }

        $mail->action('E-Mail bestätigen', $this->verificationUrl);

        return $this->applyOrgMailer($mail, $this->event->organization, $this->event->project);
    }
}
