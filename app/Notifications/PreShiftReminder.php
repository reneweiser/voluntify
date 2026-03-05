<?php

namespace App\Notifications;

use App\Enums\EmailTemplateType;
use App\Models\Event;
use App\Models\Shift;
use App\Services\EmailTemplateRenderer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PreShiftReminder extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Event $event,
        public Shift $shift,
        public EmailTemplateType $templateType,
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
        $this->shift->loadMissing('volunteerJob');
        $job = $this->shift->volunteerJob;

        $renderer = app(EmailTemplateRenderer::class);
        $rendered = $renderer->render(
            $this->templateType,
            $this->event,
            [
                'volunteer_name' => $notifiable->name,
                'event_name' => $this->event->name,
                'job_name' => $job->name,
                'shift_date' => $this->shift->starts_at->format('M d, Y'),
                'shift_time' => $this->shift->starts_at->format('g:i A').' — '.$this->shift->ends_at->format('g:i A'),
                'event_location' => $this->event->location ? "**Location:** {$this->event->location}" : '',
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

        return $mail;
    }
}
