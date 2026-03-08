<?php

namespace App\Livewire;

use App\Enums\ActivityCategory;
use App\Enums\StaffRole;
use App\Models\ActivityLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Activity Log')]
class ActivityFeed extends Component
{
    use WithPagination;

    public string $eventFilter = '';

    public string $categoryFilter = '';

    public string $actorFilter = '';

    public string $dateFrom = '';

    public string $dateTo = '';

    public function mount(): void
    {
        $org = currentOrganization();
        $isOrganizer = $org->users()
            ->where('user_id', auth()->id())
            ->wherePivot('role', StaffRole::Organizer)
            ->exists();

        if (! $isOrganizer) {
            abort(403);
        }
    }

    #[Computed]
    public function activities(): LengthAwarePaginator
    {
        $query = ActivityLog::query()
            ->forOrganization(currentOrganization()->id)
            ->latest();

        if ($this->eventFilter) {
            $query->forEvent((int) $this->eventFilter);
        }

        if ($this->categoryFilter) {
            $category = ActivityCategory::tryFrom($this->categoryFilter);
            if ($category) {
                $query->forCategory($category);
            }
        }

        if ($this->actorFilter) {
            $user = User::find($this->actorFilter);
            if ($user) {
                $query->forCauser($user);
            }
        }

        if ($this->dateFrom && $this->dateTo) {
            $query->inDateRange(
                Carbon::parse($this->dateFrom)->startOfDay(),
                Carbon::parse($this->dateTo)->endOfDay(),
            );
        } elseif ($this->dateFrom) {
            $query->where('created_at', '>=', Carbon::parse($this->dateFrom)->startOfDay());
        } elseif ($this->dateTo) {
            $query->where('created_at', '<=', Carbon::parse($this->dateTo)->endOfDay());
        }

        return $query->paginate(25);
    }

    #[Computed]
    public function events(): Collection
    {
        return currentOrganization()->events()->orderBy('name')->get(['id', 'name']);
    }

    #[Computed]
    public function members(): Collection
    {
        return currentOrganization()->users()->orderBy('name')->get(['users.id', 'users.name']);
    }

    public function clearFilters(): void
    {
        $this->eventFilter = '';
        $this->categoryFilter = '';
        $this->actorFilter = '';
        $this->dateFrom = '';
        $this->dateTo = '';
        $this->resetPage();
        unset($this->activities);
    }

    public function updated(string $property): void
    {
        $this->resetPage();
        unset($this->activities);
    }
}
