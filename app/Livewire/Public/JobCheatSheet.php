<?php

namespace App\Livewire\Public;

use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\VolunteerJob;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.public')]
#[Title('Job Instructions')]
class JobCheatSheet extends Component
{
    public Event $event;

    public VolunteerJob $job;

    public function mount(string $publicToken, int $jobId): void
    {
        $this->event = Event::where('public_token', $publicToken)
            ->where('status', EventStatus::Published)
            ->firstOrFail();

        $this->job = VolunteerJob::where('id', $jobId)
            ->where('event_id', $this->event->id)
            ->firstOrFail();

        if (empty($this->job->instructions)) {
            abort(404);
        }
    }
}
