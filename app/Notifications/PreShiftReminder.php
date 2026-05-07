<?php

namespace App\Notifications;

use App\Enums\EmailTemplateType;
use App\Models\Event;
use App\Models\Shift;
use App\Notifications\Concerns\HasRetryStrategy;
use App\Notifications\Concerns\UsesOrganizationMailer;
use App\Services\EmailTemplateRenderer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PreShiftReminder extends Notification implements ShouldQueue
{
    use HasRetryStrategy;
    use Queueable;
    use UsesOrganizationMailer;

    public function __construct(
        public Event $event,
        public Shift $shift,
        public EmailTemplateType $templateType,
        public string $magicLinkToken,
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
        $timezone = $this->event->project->timezone ?? 'UTC';
        $shiftStartsAt = $this->shift->starts_at?->copy()->setTimezone($timezone);

        $cheatSheetUrl = $job->instructions
            ? route('events.jobs.cheat-sheet', ['publicToken' => $this->event->public_token, 'jobId' => $job->id])
            : '';
        $portalUrl = route('volunteer.portal', $this->magicLinkToken);

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
                'relativer_tag' => $this->relativeDayLabel($timezone),
                'job_name' => $job->name,
                'shift_date' => ($shiftStartsAt ?? $this->shift->shift_date->copy()->setTimezone($timezone))->format('d.m.Y'),
                'shift_time' => $this->shift->displayTimeRange($timezone),
                'event_location' => $this->event->location ? "**Ort:** {$this->event->location}" : '',
                'cheat_sheet_url' => $cheatSheetUrl ? "[Aufgaben-Infos anzeigen]({$cheatSheetUrl})" : '',
                'portal_link' => $portalUrl,
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

        $mail->action('Portal öffnen', $portalUrl);

        return $this->applyOrgMailer($mail, $this->event->organization, $this->event->project);
    }

    private function relativeDayLabel(string $timezone): string
    {
        $shiftStartsAt = $this->shift->starts_at?->copy()->setTimezone($timezone);

        if ($shiftStartsAt === null) {
            return 'am '.$this->shift->shift_date->copy()->setTimezone($timezone)->format('d.m.Y');
        }

        $today = now()->setTimezone($timezone);

        if ($shiftStartsAt->isSameDay($today)) {
            return 'heute';
        }

        if ($shiftStartsAt->isSameDay($today->copy()->addDay())) {
            return 'morgen';
        }

        return 'am '.$shiftStartsAt->format('d.m.Y');
    }
}
