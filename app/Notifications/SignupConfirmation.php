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

class SignupConfirmation extends Notification implements ShouldQueue
{
    use HasRetryStrategy;
    use Queueable;
    use UsesOrganizationMailer;

    /**
     * @param  array<int>  $shiftIds
     */
    public function __construct(
        public Event $event,
        public array $shiftIds,
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
        $shifts = Shift::with('volunteerJob')->whereIn('id', $this->shiftIds)->get();

        $firstShift = $shifts->first();
        $firstJob = $firstShift->volunteerJob;

        $tz = $this->event->project->timezone ?? 'UTC';
        $shiftsSummary = $shifts->map(function (Shift $shift) use ($tz) {
            $job = $shift->volunteerJob;

            return "- {$job->name}: {$shift->shift_date->setTimezone($tz)->format('d.m.Y')} {$shift->displayTimeRange($tz)}";
        })->implode("\n");

        $renderer = app(EmailTemplateRenderer::class);
        $rendered = $renderer->render(
            EmailTemplateType::SignupConfirmation,
            $this->event,
            [
                // #81 - German placeholders (primary)
                'vorname' => $notifiable->first_name,
                'nachname' => $notifiable->last_name,
                'telefon' => $notifiable->phone ?? '',
                // Legacy placeholders (backwards compatibility)
                'volunteer_name' => $notifiable->full_name,
                'event_name' => $this->event->name,
                'shifts_summary' => $shiftsSummary,
                'job_name' => $firstJob->name,
                'shift_date' => $firstShift->shift_date->setTimezone($tz)->format('d.m.Y'),
                'shift_time' => $firstShift->displayTimeRange($tz),
                'event_location' => $this->event->location ? "**Ort:** {$this->event->location}" : '',
                'portal_link' => route('volunteer.portal', $this->magicLinkToken),
                'project_name' => $this->event->project?->name ?? '',
                'kontakt_email' => $this->event->project?->contact_email ?? $this->event->organization->smtp_from_address ?? '',
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
        $mail->action('Portal öffnen', $portalUrl);

        return $this->applyOrgMailer($mail, $this->event->organization, $this->event->project);
    }
}
