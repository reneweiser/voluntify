<?php

namespace App\Livewire\Events;

use App\Actions\RecordAttendance;
use App\Enums\AttendanceStatus;
use App\Models\Event;
use App\Models\Organization;
use App\Models\ShiftSignup;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Attendance')]
class AttendanceTracker extends Component
{
    public Event $event;

    public ?int $selectedShiftId = null;

    public function mount(int $eventId): void
    {
        $this->event = app(Organization::class)->events()->findOrFail($eventId);

        Gate::authorize('markAttendance', $this->event);
    }

    #[Computed]
    public function shifts(): Collection
    {
        return $this->event->volunteerJobs()
            ->with(['shifts' => fn ($q) => $q->withCount([
                'signups',
                'signups as attended_count' => fn ($q) => $q->has('attendanceRecord'),
            ])->orderBy('starts_at')])
            ->get()
            ->pluck('shifts')
            ->flatten()
            ->each(fn ($shift) => $shift->job_name = $shift->volunteerJob->name);
    }

    #[Computed]
    public function signups(): Collection
    {
        if (! $this->selectedShiftId) {
            return new Collection;
        }

        return ShiftSignup::where('shift_id', $this->selectedShiftId)
            ->whereHas('shift.volunteerJob', fn ($q) => $q->where('event_id', $this->event->id))
            ->with([
                'volunteer.eventArrivals' => fn ($q) => $q->where('event_id', $this->event->id),
                'attendanceRecord',
            ])
            ->get();
    }

    public function markStatus(int $signupId, string $status): void
    {
        Gate::authorize('markAttendance', $this->event);

        $signup = ShiftSignup::whereHas('shift.volunteerJob', fn ($q) => $q->where('event_id', $this->event->id))
            ->findOrFail($signupId);

        $attendanceStatus = AttendanceStatus::from($status);

        $record = app(RecordAttendance::class)->execute($signup, $attendanceStatus, auth()->user());

        if ($record->conflictDetected) {
            session()->flash('conflict', __('Warning: This volunteer was scanned at the event entrance but is being marked as No Show.'));
        }

        unset($this->signups, $this->shifts);
    }
}
