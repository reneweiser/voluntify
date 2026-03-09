<?php

namespace App\Livewire\Public;

use App\Models\EventGroup;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.public')]
#[Title('Event Group')]
class EventGroupPage extends Component
{
    public EventGroup $group;

    public function mount(string $publicToken): void
    {
        $this->group = EventGroup::where('public_token', $publicToken)
            ->firstOrFail();
    }

    public function render(): mixed
    {
        return view('livewire.public.event-group-page', [
            'events' => $this->group->publishedEvents()->withCount('volunteers')->get(),
        ]);
    }
}
