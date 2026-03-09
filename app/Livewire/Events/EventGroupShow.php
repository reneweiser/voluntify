<?php

namespace App\Livewire\Events;

use App\Actions\AssignEventsToGroup;
use App\Actions\DeleteEventGroup;
use App\Actions\RemoveEventFromGroup;
use App\Actions\UpdateEventGroup;
use App\Models\Event;
use App\Models\EventGroup;
use App\Models\Organization;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Title('Event Group')]
class EventGroupShow extends Component
{
    use WithFileUploads;

    public EventGroup $group;

    public string $name = '';

    public string $description = '';

    public $titleImage;

    public bool $editing = false;

    public string $selectedEventId = '';

    public function mount(int $groupId): void
    {
        $this->group = currentOrganization()->eventGroups()->findOrFail($groupId);

        Gate::authorize('view', $this->group);

        $this->fillForm();
    }

    #[Computed]
    public function organization(): Organization
    {
        return currentOrganization();
    }

    #[Computed]
    public function canManage(): bool
    {
        return Gate::allows('update', $this->group);
    }

    #[Computed]
    public function memberEvents(): \Illuminate\Database\Eloquent\Collection
    {
        return $this->group->events()->orderBy('starts_at')->get();
    }

    #[Computed]
    public function availableEvents(): \Illuminate\Database\Eloquent\Collection
    {
        return $this->organization->events()
            ->whereNull('event_group_id')
            ->orderBy('starts_at', 'desc')
            ->get();
    }

    #[Computed]
    public function publicUrl(): string
    {
        return route('event-groups.public', $this->group->public_token);
    }

    public function startEditing(): void
    {
        Gate::authorize('update', $this->group);
        $this->editing = true;
    }

    public function cancelEditing(): void
    {
        $this->editing = false;
        $this->fillForm();
        $this->resetValidation();
    }

    public function saveGroup(): void
    {
        Gate::authorize('update', $this->group);

        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'titleImage' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        $action = app(UpdateEventGroup::class);
        $this->group = $action->execute(
            eventGroup: $this->group,
            name: $this->name,
            description: $this->description ?: null,
            titleImage: $this->titleImage,
        );

        $this->titleImage = null;
        $this->editing = false;
        unset($this->memberEvents);
    }

    public function deleteImage(): void
    {
        Gate::authorize('update', $this->group);

        $action = app(UpdateEventGroup::class);
        $this->group = $action->execute(
            eventGroup: $this->group,
            name: $this->group->name,
            description: $this->group->description,
            removeTitleImage: true,
        );
    }

    public function assignEvent(): void
    {
        Gate::authorize('update', $this->group);

        if (! $this->selectedEventId) {
            return;
        }

        $action = app(AssignEventsToGroup::class);
        $action->execute($this->group, [(int) $this->selectedEventId]);

        $this->selectedEventId = '';
        unset($this->memberEvents, $this->availableEvents);
    }

    public function removeEvent(int $eventId): void
    {
        Gate::authorize('update', $this->group);

        $event = Event::findOrFail($eventId);

        $action = app(RemoveEventFromGroup::class);
        $action->execute($event);

        unset($this->memberEvents, $this->availableEvents);
    }

    public function deleteGroup(): void
    {
        Gate::authorize('delete', $this->group);

        $action = app(DeleteEventGroup::class);
        $action->execute($this->group);

        $this->redirectRoute('event-groups.index');
    }

    private function fillForm(): void
    {
        $this->name = $this->group->name;
        $this->description = $this->group->description ?? '';
    }
}
