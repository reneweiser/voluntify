<?php

namespace App\Livewire;

use App\Models\Organization;
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
}
