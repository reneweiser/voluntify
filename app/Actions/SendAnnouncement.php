<?php

namespace App\Actions;

use App\Models\Announcement;
use App\Models\Volunteer;
use App\Notifications\AnnouncementNotification;

class SendAnnouncement
{
    public function execute(Announcement $announcement): void
    {
        if ($announcement->isSent()) {
            return;
        }

        $query = Volunteer::where('project_id', $announcement->project_id)
            ->whereNotNull('email_verified_at');

        if ($announcement->event_id) {
            $query->whereHas('shiftSignups', function ($q) use ($announcement) {
                $q->active()->whereHas('shift.volunteerJob', function ($jq) use ($announcement) {
                    $jq->where('event_id', $announcement->event_id);

                    if ($announcement->job_id) {
                        $jq->where('id', $announcement->job_id);
                    }
                });

                if ($announcement->shift_id) {
                    $q->where('shift_id', $announcement->shift_id);
                }
            });
        }

        $volunteers = $query->get();

        foreach ($volunteers as $volunteer) {
            $volunteer->notify(new AnnouncementNotification($announcement));
        }

        $announcement->update([
            'sent_at' => now(),
            'recipient_count' => $volunteers->count(),
        ]);
    }
}
