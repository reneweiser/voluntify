<?php

namespace App\Livewire;

use App\Models\Event;
use App\Models\Organization;
use App\Models\Shift;
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
}
