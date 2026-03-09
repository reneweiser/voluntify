<?php

namespace App\Livewire\Events;

use App\Actions\CreateEventGroup;
use App\Models\EventGroup;
use App\Models\Organization;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Title('Event Groups')]
class EventGroupList extends Component
{
    use WithFileUploads;

    public string $groupName = '';

    public string $groupDescription = '';

    public $groupTitleImage;

    public bool $showCreateModal = false;

    #[Computed]
    public function organization(): Organization
    {
        return currentOrganization();
    }

    #[Computed]
    public function groups(): \Illuminate\Database\Eloquent\Collection
    {
        return $this->organization->eventGroups()
            ->withCount('events')
            ->latest()
            ->get();
    }

    #[Computed]
    public function canCreateGroups(): bool
    {
        return Gate::allows('create', [EventGroup::class, $this->organization]);
    }

    public function mount(): void
    {
        Gate::authorize('viewAny', [EventGroup::class, $this->organization]);
    }

    public function createGroup(): void
    {
        Gate::authorize('create', [EventGroup::class, $this->organization]);

        $this->validate([
            'groupName' => ['required', 'string', 'max:255'],
            'groupDescription' => ['nullable', 'string'],
            'groupTitleImage' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        $action = app(CreateEventGroup::class);

        $group = $action->execute(
            organization: $this->organization,
            name: $this->groupName,
            description: $this->groupDescription ?: null,
            titleImage: $this->groupTitleImage,
        );

        $this->reset('groupName', 'groupDescription', 'groupTitleImage', 'showCreateModal');

        $this->redirectRoute('event-groups.show', $group);
    }
}
