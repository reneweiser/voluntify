<?php

namespace App\Notifications;

use App\Models\Announcement;
use App\Notifications\Concerns\HasRetryStrategy;
use App\Notifications\Concerns\UsesOrganizationMailer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AnnouncementNotification extends Notification implements ShouldQueue
{
    use HasRetryStrategy;
    use Queueable;
    use UsesOrganizationMailer;

    public function __construct(
        public Announcement $announcement,
        public bool $isArchiveCopy = false,
        public int $archiveRecipientCount = 0,
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
        $subject = $this->isArchiveCopy
            ? '[Archiv-Kopie] '.$this->announcement->subject
            : $this->announcement->subject;

        $mail = (new MailMessage)
            ->subject($subject)
            ->greeting($this->isArchiveCopy ? 'Hallo!' : "Hallo {$notifiable->first_name}!");

        if ($this->isArchiveCopy) {
            $mail->line("Kopie einer Nachricht, die an {$this->archiveRecipientCount} Volunteers versendet wurde.");
        }

        foreach (explode("\n", $this->announcement->body) as $line) {
            $trimmed = trim($line);
            if ($trimmed !== '') {
                $mail->line($trimmed);
            }
        }

        $project = $this->announcement->project;

        return $this->applyOrgMailer($mail, $project->organization, $project);
    }
}
