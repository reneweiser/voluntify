<?php

namespace App\Livewire\Events;

use App\Models\Organization;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Events')]
class EventList extends Component
{
    public string $statusFilter = '';

    #[Computed]
    public function events(): \Illuminate\Database\Eloquent\Collection
    {
        $query = app(Organization::class)->events()->latest('starts_at');

        if ($this->statusFilter) {
            $query->where('status', $this->statusFilter);
        }

        return $query->get();
    }

    public function setStatusFilter(string $status): void
    {
        $this->statusFilter = $this->statusFilter === $status ? '' : $status;

        unset($this->events);
    }
}
