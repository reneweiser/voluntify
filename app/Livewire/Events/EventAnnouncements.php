<?php

namespace App\Livewire\Events;

use App\Actions\SendEventAnnouncement;
use App\Models\Event;
use App\Models\Volunteer;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Announcements')]
class EventAnnouncements extends Component
{
    public Event $event;

    public string $subject = '';

    public string $body = '';

    public function mount(int $eventId): void
    {
        $this->event = currentOrganization()->events()->findOrFail($eventId);

        Gate::authorize('update', $this->event);
    }

    #[Computed]
    public function recipientCount(): int
    {
        return Volunteer::query()
            ->whereHas('shiftSignups', fn ($q) => $q
                ->active()
                ->whereHas('shift.volunteerJob', fn ($q) => $q->where('event_id', $this->event->id))
            )
            ->whereNotNull('email_verified_at')
            ->count();
    }

    #[Computed]
    public function history(): Collection
    {
        return $this->event->announcements()
            ->with('sender')
            ->latest('sent_at')
            ->get();
    }

    public function send(): void
    {
        Gate::authorize('update', $this->event);

        $this->validate([
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
        ]);

        app(SendEventAnnouncement::class)->execute(
            $this->event,
            $this->subject,
            $this->body,
            auth()->user(),
        );

        $this->reset('subject', 'body');
        unset($this->history, $this->recipientCount);
    }
}
