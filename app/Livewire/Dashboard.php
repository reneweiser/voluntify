<?php

namespace App\Livewire;

use App\Enums\AttendanceStatus;
use App\Models\AttendanceRecord;
use App\Models\Event;
use App\Models\Organization;
use App\Models\Shift;
use App\Models\ShiftSignup;
use App\Models\Volunteer;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Dashboard')]
class Dashboard extends Component
{
    #[Computed]
    public function organization(): ?Organization
    {
        return app()->bound(Organization::class) ? app(Organization::class) : null;
    }

    #[Computed]
    public function userRole(): ?string
    {
        if (! $this->organization) {
            return null;
        }

        return $this->organization->users()
            ->where('user_id', auth()->id())
            ->first()
            ?->pivot
            ?->role
            ?->value;
    }

    #[Computed]
    public function upcomingEventsCount(): int
    {
        if (! $this->organization) {
            return 0;
        }

        return $this->organization->events()
            ->published()
            ->where('starts_at', '>=', now())
            ->count();
    }

    #[Computed]
    public function totalVolunteersCount(): int
    {
        if (! $this->organization) {
            return 0;
        }

        return Volunteer::whereHas(
            'tickets',
            fn ($q) => $q->whereIn('event_id', $this->organization->events()->select('id'))
        )->count();
    }

    #[Computed]
    public function shiftsNeedingAttention(): int
    {
        if (! $this->organization) {
            return 0;
        }

        return Shift::whereHas('volunteerJob', fn ($q) => $q->whereIn(
            'event_id',
            $this->organization->events()
                ->published()
                ->where('starts_at', '>=', now())
                ->select('id')
        ))
            ->whereColumn(
                DB::raw('(SELECT COUNT(*) FROM shift_signups WHERE shift_signups.shift_id = shifts.id)'),
                '<',
                'capacity'
            )
            ->count();
    }

    #[Computed]
    public function upcomingEvents(): Collection
    {
        if (! $this->organization) {
            return new Collection;
        }

        return $this->organization->events()
            ->published()
            ->where('starts_at', '>=', now())
            ->withCount('volunteers')
            ->orderBy('starts_at')
            ->limit(5)
            ->get();
    }

    #[Computed]
    public function canCreateEvents(): bool
    {
        if (! $this->organization) {
            return false;
        }

        return Gate::allows('create', [Event::class, $this->organization]);
    }

    #[Computed]
    public function noShowRate(): float
    {
        if (! $this->organization) {
            return 0;
        }

        $eventIds = $this->organization->events()->select('id');

        $total = AttendanceRecord::whereHas(
            'shiftSignup.shift.volunteerJob',
            fn ($q) => $q->whereIn('event_id', $eventIds)
        )->count();

        if ($total === 0) {
            return 0;
        }

        $noShows = AttendanceRecord::whereHas(
            'shiftSignup.shift.volunteerJob',
            fn ($q) => $q->whereIn('event_id', $eventIds)
        )->where('status', AttendanceStatus::NoShow)->count();

        return round(($noShows / $total) * 100, 1);
    }

    /**
     * @return array{on_time: int, late: int, no_show: int, unmarked: int}
     */
    #[Computed]
    public function attendanceSummary(): array
    {
        if (! $this->organization) {
            return ['on_time' => 0, 'late' => 0, 'no_show' => 0, 'unmarked' => 0];
        }

        $eventIds = $this->organization->events()->select('id');

        $totalSignups = ShiftSignup::whereHas(
            'shift.volunteerJob',
            fn ($q) => $q->whereIn('event_id', $eventIds)
        )->count();

        $counts = AttendanceRecord::whereHas(
            'shiftSignup.shift.volunteerJob',
            fn ($q) => $q->whereIn('event_id', $eventIds)
        )
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        $onTime = $counts[AttendanceStatus::OnTime->value] ?? 0;
        $late = $counts[AttendanceStatus::Late->value] ?? 0;
        $noShow = $counts[AttendanceStatus::NoShow->value] ?? 0;

        return [
            'on_time' => $onTime,
            'late' => $late,
            'no_show' => $noShow,
            'unmarked' => $totalSignups - $onTime - $late - $noShow,
        ];
    }

    #[Computed]
    public function recentPastEvents(): Collection
    {
        if (! $this->organization) {
            return new Collection;
        }

        return $this->organization->events()
            ->published()
            ->where('ends_at', '<', now())
            ->withCount(['volunteers', 'eventArrivals'])
            ->orderByDesc('ends_at')
            ->limit(5)
            ->get();
    }
}
