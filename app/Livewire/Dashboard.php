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
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('Dashboard')]
class Dashboard extends Component
{
    #[Url]
    public string $search = '';

    #[Computed]
    public function organization(): Organization
    {
        return currentOrganization();
    }

    #[Computed]
    public function userRole(): ?string
    {
        return auth()->user()->orgRoleFor($this->organization)?->value;
    }

    #[Computed]
    public function canCreateEvents(): bool
    {
        return Gate::allows('create', [Event::class, $this->organization]);
    }

    /**
     * Projects accessible to the current user, with aggregate metrics.
     */
    #[Computed]
    public function projects(): Collection
    {
        $user = auth()->user();

        $query = $this->organization->projects()
            ->active()
            ->withCount([
                'events' => fn ($q) => $q->published()->where('starts_at', '>=', now()),
                'volunteers',
            ])
            ->latest();

        if (! $user->isOrgOrganizerFor($this->organization)) {
            $assignedProjectIds = $user->projects()
                ->where('projects.organization_id', $this->organization->id)
                ->pluck('projects.id');

            $query->whereIn('id', $assignedProjectIds);
        }

        return $query->get();
    }

    /**
     * The next upcoming event across all accessible projects.
     */
    #[Computed]
    public function nextUpcomingEvent(): ?Event
    {
        return $this->scopedEvents()
            ->published()
            ->with('project')
            ->where('starts_at', '>=', now())
            ->orderBy('starts_at')
            ->first();
    }

    /**
     * Smart reminders for organizer attention.
     *
     * @return array<int, array{type: string, message: string, link: string|null}>
     */
    #[Computed]
    public function reminders(): array
    {
        $reminders = [];

        // Shifts needing volunteers
        $shiftsNeedingVolunteers = Shift::whereHas('volunteerJob', fn ($q) => $q->whereIn(
            'event_id',
            $this->scopedEvents()->published()->where('starts_at', '>=', now())->select('id')
        ))
            ->where(
                ShiftSignup::selectRaw('count(*)')
                    ->whereColumn('shift_signups.shift_id', 'shifts.id')
                    ->whereNull('shift_signups.cancelled_at'),
                '<',
                DB::raw('shifts.capacity')
            )
            ->count();

        if ($shiftsNeedingVolunteers > 0) {
            $reminders[] = [
                'type' => 'warning',
                'message' => "{$shiftsNeedingVolunteers} Schicht(en) brauchen noch Helfer:innen",
                'link' => null,
            ];
        }

        // Recent cancellations (last 24h)
        $recentCancellations = ShiftSignup::whereHas(
            'shift.volunteerJob',
            fn ($q) => $q->whereIn('event_id', $this->scopedEvents()->select('id'))
        )
            ->whereNotNull('cancelled_at')
            ->where('cancelled_at', '>=', now()->subDay())
            ->count();

        if ($recentCancellations > 0) {
            $reminders[] = [
                'type' => 'info',
                'message' => "{$recentCancellations} neue Stornierung(en) in den letzten 24 Stunden",
                'link' => null,
            ];
        }

        // Projects without scanners
        foreach ($this->projects as $project) {
            if ($project->events_count > 0 && $project->scanners()->count() === 0) {
                $reminders[] = [
                    'type' => 'warning',
                    'message' => "Projekt \"{$project->name}\" hat keine Scanner konfiguriert",
                    'link' => route('projects.scanners', $project),
                ];
            }
        }

        return $reminders;
    }

    /**
     * Global volunteer search across all projects.
     */
    #[Computed]
    public function searchResults(): Collection
    {
        if (strlen($this->search) < 2) {
            return new Collection;
        }

        $projectIds = $this->projects->pluck('id');

        return Volunteer::whereIn('project_id', $projectIds)
            ->where(function ($q) {
                $q->where('first_name', 'like', "%{$this->search}%")
                    ->orWhere('last_name', 'like', "%{$this->search}%")
                    ->orWhere('email', 'like', "%{$this->search}%");
            })
            ->with('project')
            ->limit(10)
            ->get();
    }

    /**
     * @return array{on_time: int, late: int, no_show: int, unmarked: int}
     */
    #[Computed]
    public function attendanceSummary(): array
    {
        $eventIds = $this->scopedEvents()->select('id');

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
    public function noShowRate(): float
    {
        $summary = $this->attendanceSummary;
        $total = $summary['on_time'] + $summary['late'] + $summary['no_show'];

        if ($total === 0) {
            return 0;
        }

        return round(($summary['no_show'] / $total) * 100, 1);
    }

    #[Computed]
    public function recentPastEvents(): Collection
    {
        $events = $this->scopedEvents()
            ->published()
            ->with('project.organization')
            ->where('ends_at', '<', now())
            ->withVolunteerCount()
            ->withCount('eventArrivals')
            ->orderByDesc('ends_at')
            ->limit(5)
            ->get();

        $user = auth()->user();
        $projectIds = $events->pluck('project_id')->unique()->values()->all();
        $user->preloadProjectRoles($projectIds);

        return $events;
    }

    private function scopedEvents(): HasMany
    {
        $user = auth()->user();
        $query = $this->organization->events();

        if (! $user->isOrgOrganizerFor($this->organization)) {
            $projectIds = $user->projects()
                ->where('projects.organization_id', $this->organization->id)
                ->pluck('projects.id');

            $query->whereIn('project_id', $projectIds);
        }

        return $query;
    }
}
