<?php

namespace App\Notifications;

use App\Enums\EmailTemplateType;
use App\Models\Event;
use App\Models\Shift;
use App\Notifications\Concerns\UsesOrganizationMailer;
use App\Services\EmailTemplateRenderer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PreShiftReminder extends Notification implements ShouldQueue
{
    use Queueable;
    use UsesOrganizationMailer;

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

        $cheatSheetUrl = $job->instructions
            ? route('events.jobs.cheat-sheet', ['publicToken' => $this->event->public_token, 'jobId' => $job->id])
            : '';

        $renderer = app(EmailTemplateRenderer::class);
        $rendered = $renderer->render(
            $this->templateType,
            $this->event,
            [
                // #81 - German placeholders (primary)
                'vorname' => $notifiable->first_name,
                'nachname' => $notifiable->last_name,
                // Legacy placeholders (backwards compatibility)
                'volunteer_name' => $notifiable->full_name,
                'event_name' => $this->event->name,
                'job_name' => $job->name,
                'shift_date' => $this->shift->shift_date->format('d.m.Y'),
                'shift_time' => $this->shift->displayTimeRange(),
                'event_location' => $this->event->location ? "**Ort:** {$this->event->location}" : '',
                'cheat_sheet_url' => $cheatSheetUrl ? "[Aufgaben-Infos anzeigen]({$cheatSheetUrl})" : '',
                'portal_link' => '', // Will be set when portal URL is available
                'kontakt_email' => $this->event->project?->contact_email ?? $this->event->organization->smtp_from_address ?? '',
                'project_name' => $this->event->project?->name ?? '',
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

        return $this->applyOrgMailer($mail, $this->event->organization, $this->event->project);
    }
}
