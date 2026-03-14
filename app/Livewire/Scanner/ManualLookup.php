<?php

namespace App\Livewire\Scanner;

use App\Actions\RecordArrival;
use App\Actions\RecordAttendance;
use App\Enums\ArrivalMethod;
use App\Enums\StaffRole;
use App\Models\Event;
use App\Models\ShiftSignup;
use App\Models\Ticket;
use App\Models\Volunteer;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Manual Lookup')]
#[Layout('layouts.scanner')]
class ManualLookup extends Component
{
    public int $eventId;

    public ?Event $event = null;

    public string $search = '';

    public function mount(int $eventId): void
    {
        $organization = currentOrganization();

        $hasAccess = $organization->users()
            ->where('user_id', auth()->id())
            ->wherePivotIn('role', [StaffRole::Organizer, StaffRole::EntranceStaff, StaffRole::VolunteerAdmin])
            ->exists();

        if (! $hasAccess) {
            abort(403);
        }

        $this->event = $organization->events()->findOrFail($eventId);
        $this->eventId = $eventId;
    }

    /** @return Collection<int, Volunteer> */
    #[Computed]
    public function volunteers(): Collection
    {
        if (strlen($this->search) < 2) {
            return new Collection;
        }

        return Volunteer::query()
            ->forEvent($this->eventId)
            ->search($this->search)
            ->with([
                'shiftSignups.shift.volunteerJob',
                'shiftSignups.attendanceRecord',
                'eventArrivals' => fn ($q) => $q->where('event_id', $this->eventId),
                'tickets' => fn ($q) => $q->where('event_id', $this->eventId),
            ])
            ->get();
    }

    public function confirmArrival(int $volunteerId): void
    {
        $ticket = Ticket::where('volunteer_id', $volunteerId)
            ->where('event_id', $this->eventId)
            ->firstOrFail();

        $arrival = app(RecordArrival::class)->execute(
            ticket: $ticket,
            scannedBy: auth()->user(),
            method: ArrivalMethod::ManualLookup,
        );

        unset($this->volunteers);

        $this->dispatch('arrival-confirmed', volunteerId: $volunteerId, flagged: $arrival->flagged);
    }

    public function recordAttendance(int $signupId): void
    {
        Gate::authorize('markAttendance', $this->event);

        $eventJobIds = $this->event->volunteerJobs()->pluck('id');

        $signup = ShiftSignup::whereHas(
            'shift',
            fn ($q) => $q->whereIn('volunteer_job_id', $eventJobIds),
        )->with('shift')->findOrFail($signupId);

        $status = $signup->shift->attendanceStatusAt(now(), $this->event->attendance_grace_minutes);

        app(RecordAttendance::class)->execute(
            signup: $signup,
            status: $status,
            recordedBy: auth()->user(),
        );

        unset($this->volunteers);
    }

    public function updatedSearch(): void
    {
        unset($this->volunteers);
    }
}
