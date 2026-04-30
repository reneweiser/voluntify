<?php

namespace App\Actions;

use App\Jobs\NotifyEventSubscribers;
use App\Models\Event;

class ScheduleEventSubscriberNotification
{
    public function execute(Event $event, bool $hadAvailability): void
    {
        if ($hadAvailability || $event->fresh()->publicSignupJobs()->isEmpty()) {
            return;
        }

        NotifyEventSubscribers::dispatch($event->id)->delay(now()->addSeconds(60));
    }
}
