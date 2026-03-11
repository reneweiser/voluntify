<?php

namespace App\Livewire\Public;

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
        $this->event = Event::publishedByToken($publicToken)->firstOrFail();

        $this->job = $this->event->volunteerJobs()
            ->whereNotNull('instructions')
            ->where('instructions', '!=', '')
            ->findOrFail($jobId);
    }
}
