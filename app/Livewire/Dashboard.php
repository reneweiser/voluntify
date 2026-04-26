<?php

namespace App\Livewire;

use App\Actions\SetCurrentOrganization;
use App\Enums\AttendanceStatus;
use App\Models\AttendanceRecord;
use App\Models\Event;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Shift;
use App\Models\ShiftSignup;
use App\Models\User;
use App\Models\Volunteer;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
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
        /** @var User $user */
        $user = Auth::user();

        return $user->orgRoleFor($this->organization)?->value;
    }

    #[Computed]
    public function canCreateEvents(): bool
    {
        return Gate::allows('create', [Event::class, $this->organization]);
    }

    /**
     * Accessible organizations with safe cross-org previews.
     *
     * @return Collection<int, array{
     *     organization: Organization,
     *     is_active: bool,
     *     role: string|null,
     *     project_count: int,
     *     projects: EloquentCollection<int, Project>,
     *     remaining_project_count: int,
     *     upcoming_events: EloquentCollection<int, Event>
     * }>
     */
    #[Computed]
    public function discoverableOrganizations(): Collection
    {
        /** @var User $user */
        $user = Auth::user();

        return Organization::query()
            ->whereIn('id', $user->accessibleOrganizationIds())
            ->orderByRaw('id = ? desc', [$this->organization->id])
            ->orderBy('name')
            ->get()
            ->map(function (Organization $organization) use ($user): array {
                $visibleProjects = $this->visibleProjectsQuery($organization)
                    ->orderBy('name')
                    ->get(['id', 'organization_id', 'name']);

                $upcomingEvents = $this->scopedEventsFor($organization)
                    ->active()
                    ->published()
                    ->with('project:id,name')
                    ->where('starts_at', '>=', now())
                    ->orderBy('starts_at')
                    ->limit(3)
                    ->get(['id', 'organization_id', 'project_id', 'name', 'starts_at']);

                return [
                    'organization' => $organization,
                    'is_active' => $organization->is($this->organization),
                    'role' => $user->orgRoleFor($organization)?->value,
                    'project_count' => $visibleProjects->count(),
                    'projects' => $visibleProjects->take(3)->values(),
                    'remaining_project_count' => max($visibleProjects->count() - 3, 0),
                    'upcoming_events' => $upcomingEvents,
                ];
            });
    }

    /**
     * Projects accessible to the current user, with aggregate metrics.
     */
    #[Computed]
    public function projects(): EloquentCollection
    {
        return $this->visibleProjectsQuery($this->organization)
            ->withCount([
                'events' => fn ($q) => $q->published()->where('starts_at', '>=', now()),
                'volunteers',
                'scanners',
            ])
            ->latest()
            ->get();
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
            if ($project->events_count > 0 && $project->scanners_count === 0) {
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
    public function searchResults(): EloquentCollection
    {
        if (strlen($this->search) < 2) {
            return new EloquentCollection;
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
     * @return array{on_time: int, late: int, no_show: int, en_route: int, excused: int, unmarked: int}
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
        $enRoute = $counts[AttendanceStatus::EnRoute->value] ?? 0;
        $excused = $counts[AttendanceStatus::Excused->value] ?? 0;

        return [
            'on_time' => $onTime,
            'late' => $late,
            'no_show' => $noShow,
            'en_route' => $enRoute,
            'excused' => $excused,
            'unmarked' => $totalSignups - $onTime - $late - $noShow - $enRoute - $excused,
        ];
    }

    #[Computed]
    public function noShowRate(): float
    {
        $summary = $this->attendanceSummary;
        $total = $summary['on_time'] + $summary['late'] + $summary['no_show'] + $summary['en_route'] + $summary['excused'];

        if ($total === 0) {
            return 0;
        }

        return round(($summary['no_show'] / $total) * 100, 1);
    }

    #[Computed]
    public function recentPastEvents(): EloquentCollection
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

        /** @var User $user */
        $user = Auth::user();
        $projectIds = $events->pluck('project_id')->unique()->values()->all();
        $user->preloadProjectRoles($projectIds);

        return $events;
    }

    public function switchOrganization(int $organizationId, SetCurrentOrganization $action): void
    {
        $organization = Organization::findOrFail($organizationId);
        /** @var User $user */
        $user = Auth::user();

        Gate::authorize('view', $organization);

        $action->execute($user, $organization);

        $this->redirect(route('dashboard'), navigate: true);
    }

    private function visibleProjectsQuery(Organization $organization): HasMany
    {
        /** @var User $user */
        $user = Auth::user();

        $query = $organization->projects()->active();

        if (! $user->isOrgOrganizerFor($organization)) {
            $projectIds = $user->projects()
                ->where('projects.organization_id', $organization->id)
                ->pluck('projects.id');

            $query->whereIn('id', $projectIds);
        }

        return $query;
    }

    private function scopedEvents(): HasMany
    {
        return $this->scopedEventsFor($this->organization);
    }

    private function scopedEventsFor(Organization $organization): HasMany
    {
        /** @var User $user */
        $user = Auth::user();

        $query = $organization->events();

        if (! $user->isOrgOrganizerFor($organization)) {
            $query->whereIn('project_id', $this->visibleProjectsQuery($organization)->select('projects.id'));
        }

        return $query;
    }
}
