<?php

namespace App\Livewire\Events;

use App\Models\Event;
use App\Models\ProjectGearItem;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Gear Setup')]
class EventGearSetup extends Component
{
    public Event $event;

    public string $newItemName = '';

    public string $newItemType = 'size_selection';

    public ?int $newItemQuantityPerVolunteer = null;

    public bool $newItemRequiresSize = false;

    public string $newItemSizes = '';

    public function mount(int $eventId): void
    {
        $this->event = currentOrganization()->events()->findOrFail($eventId);

        Gate::authorize('manageGear', $this->event);
    }

    #[Computed]
    public function gearItems(): Collection
    {
        return $this->event->project->gearItems()->get();
    }

    public function addItem(): void
    {
        Gate::authorize('manageGear', $this->event);

        $rules = [
            'newItemName' => ['required', 'string', 'max:255'],
            'newItemType' => ['required', Rule::in(['size_selection', 'quantity'])],
        ];

        if ($this->newItemType === 'quantity') {
            $rules['newItemQuantityPerVolunteer'] = ['required', 'integer', 'min:1'];
        } else {
            $rules['newItemSizes'] = ['required_if:newItemRequiresSize,true', 'string', 'max:500', 'regex:/[a-zA-Z0-9]/'];
        }

        $this->validate($rules);

        $sizes = null;
        if ($this->newItemType === 'size_selection' && $this->newItemRequiresSize && $this->newItemSizes !== '') {
            $sizes = array_map('trim', explode(',', $this->newItemSizes));
            $sizes = array_values(array_filter($sizes));
        }

        if ($this->newItemType === 'size_selection' && $this->newItemRequiresSize && empty($sizes)) {
            $this->addError('newItemSizes', __('At least one valid size is required.'));

            return;
        }

        $maxSort = $this->event->project->gearItems()->max('sort_order') ?? 0;

        ProjectGearItem::create([
            'project_id' => $this->event->project_id,
            'name' => $this->newItemName,
            'type' => $this->newItemType,
            'quantity_per_volunteer' => $this->newItemType === 'quantity' ? $this->newItemQuantityPerVolunteer : null,
            'requires_size' => $this->newItemType === 'size_selection' ? $this->newItemRequiresSize : false,
            'available_sizes' => $this->newItemType === 'size_selection' && $this->newItemRequiresSize ? $sizes : null,
            'sort_order' => $maxSort + 1,
        ]);

        $this->reset('newItemName', 'newItemType', 'newItemQuantityPerVolunteer', 'newItemRequiresSize', 'newItemSizes');
        unset($this->gearItems);
    }

    public function removeItem(int $itemId): void
    {
        Gate::authorize('manageGear', $this->event);

        $this->event->project->gearItems()->where('id', $itemId)->delete();

        unset($this->gearItems);
    }
}
