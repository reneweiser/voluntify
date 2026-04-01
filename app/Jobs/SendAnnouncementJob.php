<?php

namespace App\Jobs;

use App\Actions\SendAnnouncement;
use App\Models\Announcement;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendAnnouncementJob implements ShouldQueue
{
    use Queueable;

    /** @var int[] */
    public array $backoff = [10, 30, 60];

    public int $tries = 3;

    public int $timeout = 60;

    public function __construct(
        public Announcement $announcement,
    ) {}

    public function handle(SendAnnouncement $action): void
    {
        $action->execute($this->announcement);
    }
}
