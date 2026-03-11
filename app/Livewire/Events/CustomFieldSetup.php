<?php

namespace App\Livewire\Events;

use App\Enums\CustomFieldType;
use App\Exceptions\DomainException;
use App\Models\CustomRegistrationField;
use App\Models\Event;
use App\Support\CustomFieldTemplates;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Custom Fields')]
class CustomFieldSetup extends Component
{
    public Event $event;

    public string $newFieldLabel = '';

    public string $newFieldType = 'text';

    public string $newFieldOptions = '';

    public bool $newFieldRequired = false;

    public bool $newFieldMultiline = false;

    public bool $showSignupWarning = false;

    public function mount(int $eventId): void
    {
        $this->event = currentOrganization()->events()->findOrFail($eventId);

        Gate::authorize('manageCustomFields', $this->event);
    }

    #[Computed]
    public function customFields(): Collection
    {
        return $this->event->customRegistrationFields()->get();
    }

    public function addField(): void
    {
        Gate::authorize('manageCustomFields', $this->event);
        $result = $this->validateAndBuildOptions();
        if (! $result) {
            return;
        }

        if ($this->newFieldRequired && $this->eventHasSignups()) {
            $this->showSignupWarning = true;

            return;
        }

        $this->saveField(...$result);
    }

    public function confirmAddField(): void
    {
        $this->showSignupWarning = false;
        Gate::authorize('manageCustomFields', $this->event);
        $result = $this->validateAndBuildOptions();
        if (! $result) {
            return;
        }

        $this->saveField(...$result);
    }

    public function dismissWarning(): void
    {
        $this->showSignupWarning = false;
    }

    public function removeField(int $fieldId): void
    {
        Gate::authorize('manageCustomFields', $this->event);

        $this->event->customRegistrationFields()->where('id', $fieldId)->first()?->delete();

        unset($this->customFields);
    }

    public function applyTemplate(string $templateKey): void
    {
        $templates = CustomFieldTemplates::all();

        if (! isset($templates[$templateKey])) {
            return;
        }

        $template = $templates[$templateKey];

        $this->newFieldLabel = $template['label'];
        $this->newFieldType = $template['type'];
        $this->newFieldRequired = $template['required'];
        $this->newFieldMultiline = $template['options']['multiline'] ?? false;
        $this->newFieldOptions = isset($template['options']['choices'])
            ? implode(', ', $template['options']['choices'])
            : '';
    }

    /**
     * @return array<string, array{label: string, type: string, options: array<string, mixed>, required: bool}>
     */
    public function getTemplatesProperty(): array
    {
        return CustomFieldTemplates::all();
    }

    /** @return array{CustomFieldType, array<string, mixed>}|null */
    private function validateAndBuildOptions(): ?array
    {
        $this->validate([
            'newFieldLabel' => ['required', 'string', 'max:255'],
            'newFieldOptions' => $this->newFieldType === 'select' ? ['required', 'string'] : ['nullable'],
        ]);

        $type = CustomFieldType::from($this->newFieldType);
        $options = $this->buildOptions($type);

        try {
            $type->validateOptions($options);
        } catch (DomainException $e) {
            $this->addError('newFieldOptions', $e->getMessage());

            return null;
        }

        return [$type, $options];
    }

    private function saveField(CustomFieldType $type, array $options): void
    {
        $maxSort = $this->event->customRegistrationFields()->withTrashed()->max('sort_order') ?? 0;

        CustomRegistrationField::create([
            'event_id' => $this->event->id,
            'label' => $this->newFieldLabel,
            'type' => $type,
            'options' => $options ?: null,
            'required' => $this->newFieldRequired,
            'sort_order' => $maxSort + 1,
        ]);

        $this->reset('newFieldLabel', 'newFieldType', 'newFieldOptions', 'newFieldRequired', 'newFieldMultiline', 'showSignupWarning');
        unset($this->customFields);
    }

    private function buildOptions(CustomFieldType $type): array
    {
        $options = [];

        if ($type === CustomFieldType::Select && $this->newFieldOptions !== '') {
            $choices = array_map('trim', explode(',', $this->newFieldOptions));
            $options['choices'] = array_values(array_filter($choices));
        }

        if ($type === CustomFieldType::Text && $this->newFieldMultiline) {
            $options['multiline'] = true;
        }

        return $options;
    }

    private function eventHasSignups(): bool
    {
        return $this->event->volunteers()->exists();
    }
}
