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

class SignupConfirmation extends Notification implements ShouldQueue
{
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

        $shiftsSummary = $shifts->map(function (Shift $shift) {
            $job = $shift->volunteerJob;
            $dateRange = $shift->starts_at->format('d.m.Y H:i').' — '.$shift->ends_at->format('H:i');

            return "- {$job->name}: {$dateRange}";
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
                'shift_date' => $firstShift->starts_at->format('d.m.Y'),
                'shift_time' => $firstShift->starts_at->format('H:i').' — '.$firstShift->ends_at->format('H:i'),
                'event_location' => $this->event->location ? "**Ort:** {$this->event->location}" : '',
                'portal_link' => route('volunteer.ticket', $this->magicLinkToken),
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

        $ticketUrl = route('volunteer.ticket', $this->magicLinkToken);
        $mail->action('Ticket anzeigen', $ticketUrl);

        return $this->applyOrgMailer($mail, $this->event->organization);
    }
}
