<?php

namespace App\Notifications;

use App\Enums\EmailTemplateType;
use App\Models\Event;
use App\Models\Shift;
use App\Models\ShiftSignup;
use App\Notifications\Concerns\UsesOrganizationMailer;
use App\Services\EmailTemplateRenderer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CancellationConfirmation extends Notification implements ShouldQueue
{
    use Queueable;
    use UsesOrganizationMailer;

    /**
     * @param  array<int>  $remainingShiftIds
     */
    public function __construct(
        public Event $event,
        public ShiftSignup $cancelledSignup,
        public array $remainingShiftIds,
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
        $this->cancelledSignup->loadMissing('shift.volunteerJob');
        $cancelledShift = $this->cancelledSignup->shift;
        $cancelledJob = $cancelledShift->volunteerJob;

        $tz = $this->event->project->timezone ?? 'UTC';
        $cancelledSummary = "- {$cancelledJob->name}: {$cancelledShift->shift_date->setTimezone($tz)->format('d.m.Y')} {$cancelledShift->displayTimeRange($tz)}";

        $remainingSection = '';
        if (! empty($this->remainingShiftIds)) {
            $remainingShifts = Shift::with('volunteerJob')
                ->whereIn('id', $this->remainingShiftIds)
                ->get();

            $remainingSummary = $remainingShifts->map(function (Shift $shift) use ($tz) {
                $job = $shift->volunteerJob;

                return "- {$job->name}: {$shift->shift_date->setTimezone($tz)->format('d.m.Y')} {$shift->displayTimeRange($tz)}";
            })->implode("\n");

            $remainingSection = "**Deine verbleibenden Schichten:**\n{$remainingSummary}";
        }

        $renderer = app(EmailTemplateRenderer::class);
        $rendered = $renderer->render(
            EmailTemplateType::CancellationConfirmation,
            $this->event,
            [
                'vorname' => $notifiable->first_name,
                'nachname' => $notifiable->last_name,
                'event_name' => $this->event->name,
                'cancelled_shift_summary' => $cancelledSummary,
                'remaining_shifts_section' => $remainingSection,
                'portal_link' => route('volunteer.ticket', $this->magicLinkToken),
                'kontakt_email' => $this->event->project?->contact_email
                    ?? $this->event->organization->smtp_from_address ?? '',
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

        if (! empty($this->remainingShiftIds)) {
            $ticketUrl = route('volunteer.ticket', $this->magicLinkToken);
            $mail->action('Ticket anzeigen', $ticketUrl);
        }

        return $this->applyOrgMailer($mail, $this->event->organization, $this->event->project);
    }
}
