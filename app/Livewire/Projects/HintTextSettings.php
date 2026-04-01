<?php

namespace App\Livewire\Projects;

use App\Enums\HintLocation;
use App\Models\HintText;
use App\Models\Project;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Hint Texts')]
class HintTextSettings extends Component
{
    #[Locked]
    public Project $project;

    public ?string $editingLocation = null;

    public string $editText = '';

    public function mount(int $projectId): void
    {
        $this->project = currentOrganization()->projects()->findOrFail($projectId);

        Gate::authorize('update', $this->project);
    }

    /**
     * @return array<string, array{text: string|null, enabled: bool}>
     */
    public function getHintsProperty(): array
    {
        $existing = $this->project->hintTexts()->get()->keyBy(fn (HintText $h) => $h->location->value);

        $hints = [];
        foreach (HintLocation::cases() as $location) {
            $hint = $existing->get($location->value);
            $hints[$location->value] = [
                'text' => $hint?->text,
                'enabled' => $hint?->enabled ?? false,
            ];
        }

        return $hints;
    }

    public function startEditing(string $location): void
    {
        Gate::authorize('update', $this->project);

        $locationEnum = HintLocation::from($location);
        $this->editingLocation = $location;
        $this->editText = $this->hints[$locationEnum->value]['text'] ?? '';
    }

    public function cancelEditing(): void
    {
        $this->editingLocation = null;
        $this->editText = '';
        $this->resetValidation();
    }

    public function saveHint(): void
    {
        Gate::authorize('update', $this->project);

        if (! $this->editingLocation) {
            return;
        }

        $this->validate([
            'editText' => ['required', 'string', 'max:2000'],
        ]);

        $location = HintLocation::from($this->editingLocation);

        HintText::updateOrCreate(
            [
                'project_id' => $this->project->id,
                'location' => $location,
            ],
            [
                'text' => $this->editText,
                'enabled' => true,
            ],
        );

        $this->editingLocation = null;
        $this->editText = '';
        unset($this->hints);
    }

    public function toggleEnabled(string $location): void
    {
        Gate::authorize('update', $this->project);

        $locationEnum = HintLocation::from($location);

        $hint = $this->project->hintTexts()
            ->forLocation($locationEnum)
            ->first();

        if ($hint) {
            $hint->update(['enabled' => ! $hint->enabled]);
            unset($this->hints);
        }
    }

    public function deleteHint(string $location): void
    {
        Gate::authorize('update', $this->project);

        $locationEnum = HintLocation::from($location);

        $this->project->hintTexts()
            ->forLocation($locationEnum)
            ->delete();

        unset($this->hints);
    }
}
