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

    public ?int $entryEventId = null;

    public array $poolEventIds = [];

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
            'entryEventId' => ['required', 'integer', Rule::exists('events', 'id')->where('project_id', $this->component->projectId)],
            'poolEventIds' => ['required', 'array', 'min:1', function (string $attribute, mixed $value, $fail): void {
                if (! is_array($value)) {
                    return;
                }

                $poolEventIds = array_values(array_unique(array_map('intval', $value)));

                if ($this->entryEventId === null || ! in_array($this->entryEventId, $poolEventIds, true)) {
                    $fail('The selected entry event must be included in the pool events.');
                }
            }],
            'poolEventIds.*' => ['integer', Rule::exists('events', 'id')->where('project_id', $this->component->projectId)],
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
        $this->entryEventId = $data['entryEventId'];
        $this->poolEventIds = $data['poolEventIds'];
        $this->gearItemIds = $data['gearItemIds'];
        $this->hintText = $data['hintText'];
        $this->startsAt = $data['startsAt'];
        $this->endsAt = $data['endsAt'];
    }

    public function setDefaultScope(?int $eventId): void
    {
        if ($eventId === null) {
            return;
        }

        $this->entryEventId ??= $eventId;

        if ($this->poolEventIds === []) {
            $this->poolEventIds = [$this->entryEventId];
        }
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
            'entry_event_id' => $this->entryEventId,
            'pool_event_ids' => array_values(array_unique(array_map('intval', $this->poolEventIds))),
            'gear_item_ids' => ! empty($this->gearItemIds) ? $this->gearItemIds : null,
            'hint_text' => $this->hintText ?: null,
            'starts_at' => $this->startsAt,
            'ends_at' => $this->endsAt,
        ];
    }
}
