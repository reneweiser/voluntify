<?php

namespace App\Events\Activity;

use App\Models\Event;
use App\Models\EventAnnouncement;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

class AnnouncementSent
{
    use Dispatchable;

    public function __construct(
        public readonly EventAnnouncement $announcement,
        public readonly Event $event,
        public readonly User $sender,
    ) {}
}
