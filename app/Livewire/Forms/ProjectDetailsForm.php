<?php

namespace App\Livewire\Forms;

use Livewire\Attributes\Validate;
use Livewire\Form;

class ProjectDetailsForm extends Form
{
    #[Validate('required|string|max:255')]
    public string $name = '';

    #[Validate('nullable|string')]
    public string $description = '';

    #[Validate('nullable|image|mimes:jpg,jpeg,png,webp|max:2048')]
    public $titleImage;

    #[Validate('nullable|string|max:255')]
    public string $senderName = '';

    #[Validate('nullable|email|max:255')]
    public string $contactEmail = '';

    #[Validate('boolean')]
    public bool $cancellationEnabled = false;

    public $cancellationCutoffHours = '';

    /**
     * Dynamic rules for fields that need runtime context.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'cancellationCutoffHours' => ['nullable', 'required_if:cancellationEnabled,true', 'integer', 'min:1', 'max:168'],
        ];
    }

    /**
     * Populate form fields from a project model.
     *
     * @param  array<string, mixed>  $data
     */
    public function fillFromProject(array $data): void
    {
        $this->name = $data['name'];
        $this->description = $data['description'];
        $this->senderName = $data['senderName'];
        $this->contactEmail = $data['contactEmail'];
        $this->cancellationEnabled = $data['cancellationEnabled'];
        $this->cancellationCutoffHours = $data['cancellationCutoffHours'];
    }
}
