<?php

namespace App\Actions;

use App\Models\Announcement;
use App\Models\Volunteer;
use App\Notifications\AnnouncementNotification;
use Illuminate\Support\Facades\Notification;

class SendAnnouncement
{
    public function execute(Announcement $announcement): void
    {
        if ($announcement->isSent()) {
            return;
        }

        $volunteers = Volunteer::query()
            ->forAnnouncementRecipients(
                projectId: $announcement->project_id,
                eventId: $announcement->event_id,
                jobId: $announcement->job_id,
                shiftId: $announcement->shift_id,
            )
            ->get();

        foreach ($volunteers as $volunteer) {
            $volunteer->notify(new AnnouncementNotification($announcement));
        }

        $notificationEmail = $announcement->event?->notification_email;

        if ($notificationEmail && $volunteers->isNotEmpty()) {
            Notification::route('mail', $notificationEmail)
                ->notify(new AnnouncementNotification($announcement, true, $volunteers->count()));
        }

        $announcement->update([
            'sent_at' => now(),
            'recipient_count' => $volunteers->count(),
        ]);
    }
}
