<?php

namespace App\Events\Activity;

use App\Models\Announcement;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

class AnnouncementSent
{
    use Dispatchable;

    public function __construct(
        public readonly Announcement $announcement,
        public readonly User $sender,
    ) {}
}
