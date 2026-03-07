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
            $dateRange = $shift->starts_at->format('M d, Y g:i A').' — '.$shift->ends_at->format('g:i A');

            return "- {$job->name}: {$dateRange}";
        })->implode("\n");

        $renderer = app(EmailTemplateRenderer::class);
        $rendered = $renderer->render(
            EmailTemplateType::SignupConfirmation,
            $this->event,
            [
                'volunteer_name' => $notifiable->name,
                'event_name' => $this->event->name,
                'shifts_summary' => $shiftsSummary,
                'job_name' => $firstJob->name,
                'shift_date' => $firstShift->starts_at->format('M d, Y'),
                'shift_time' => $firstShift->starts_at->format('g:i A').' — '.$firstShift->ends_at->format('g:i A'),
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

        $ticketUrl = route('volunteer.ticket', $this->magicLinkToken);
        $mail->action('View Your Ticket', $ticketUrl);

        return $this->applyOrgMailer($mail, $this->event->organization);
    }
}
