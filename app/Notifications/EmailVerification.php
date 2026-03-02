<?php

namespace App\Notifications;

use App\Enums\EmailTemplateType;
use App\Models\Event;
use App\Services\EmailTemplateRenderer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EmailVerification extends Notification implements ShouldQueue
{
    use Queueable;

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
                'volunteer_name' => $notifiable->name,
                'event_name' => $this->event->name,
            ],
        );

        $mail = (new MailMessage)
            ->subject($rendered['subject'])
            ->greeting("Hello {$notifiable->name}!");

        foreach (explode("\n", $rendered['body']) as $line) {
            $trimmed = trim($line);
            if ($trimmed !== '') {
                $mail->line($trimmed);
            }
        }

        $mail->action('Verify Email & Complete Signup', $this->verificationUrl);

        return $mail;
    }
}
