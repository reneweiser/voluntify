<?php

namespace App\Actions;

use App\Events\Activity\AnnouncementSent;
use App\Models\Event;
use App\Models\EventAnnouncement;
use App\Models\User;
use App\Models\Volunteer;
use App\Notifications\EventAnnouncementNotification;
use Illuminate\Support\Facades\Notification;

class SendEventAnnouncement
{
    public function execute(Event $event, string $subject, string $body, User $sender): EventAnnouncement
    {
        $announcement = EventAnnouncement::create([
            'event_id' => $event->id,
            'subject' => $subject,
            'body' => $body,
            'sent_at' => now(),
            'sent_by' => $sender->id,
        ]);

        $volunteers = Volunteer::query()
            ->whereHas('shiftSignups', fn ($q) => $q
                ->active()
                ->whereHas('shift.volunteerJob', fn ($q) => $q->where('event_id', $event->id))
            )
            ->whereNotNull('email_verified_at')
            ->get();

        $event->loadMissing('organization');

        Notification::send($volunteers, new EventAnnouncementNotification($event, $subject, $body));

        AnnouncementSent::dispatch($announcement, $event, $sender);

        return $announcement;
    }
}
