<?php

namespace App\Livewire\Forms;

use Illuminate\Validation\Rule;
use Livewire\Attributes\Validate;
use Livewire\Form;

class ScannerForm extends Form
{
    #[Validate('required|string|max:255')]
    public string $name = '';

    #[Validate('required|string|in:entry_staff,volunteer_admin')]
    public string $type = 'entry_staff';

    #[Validate('required|array|min:1')]
    public array $modes = ['checkin'];

    public ?int $eventId = null;

    public array $gearItemIds = [];

    public string $hintText = '';

    #[Validate('required|date')]
    public string $startsAt = '';

    #[Validate('required|date|after:startsAt')]
    public string $endsAt = '';

    /**
     * Dynamic rules for fields that need runtime context.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'modes.*' => ['string', 'in:checkin,gear_pickup'],
            'eventId' => ['nullable', Rule::exists('events', 'id')->where('project_id', $this->component->projectId)],
        ];
    }

    /**
     * Populate form fields from a scanner model.
     *
     * @param  array<string, mixed>  $data
     */
    public function fillFromScanner(array $data): void
    {
        $this->name = $data['name'];
        $this->type = $data['type'];
        $this->modes = $data['modes'];
        $this->eventId = $data['eventId'];
        $this->gearItemIds = $data['gearItemIds'];
        $this->hintText = $data['hintText'];
        $this->startsAt = $data['startsAt'];
        $this->endsAt = $data['endsAt'];
    }

    /**
     * Return the form data formatted for the action.
     *
     * @return array<string, mixed>
     */
    public function toActionData(): array
    {
        return [
            'name' => $this->name,
            'type' => $this->type,
            'modes' => $this->modes,
            'event_id' => $this->eventId,
            'gear_item_ids' => ! empty($this->gearItemIds) ? $this->gearItemIds : null,
            'hint_text' => $this->hintText ?: null,
            'starts_at' => $this->startsAt,
            'ends_at' => $this->endsAt,
        ];
    }
}
