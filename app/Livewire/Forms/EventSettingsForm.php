<?php

namespace App\Livewire\Forms;

use App\Enums\EventVisibility;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Validate;
use Livewire\Form;

class EventSettingsForm extends Form
{
    #[Validate('required|string|max:255')]
    public string $name = '';

    #[Validate('nullable|string')]
    public string $description = '';

    #[Validate('nullable|string|max:255')]
    public string $location = '';

    #[Validate('required|date')]
    public string $startsAt = '';

    #[Validate('required|date|after:startsAt')]
    public string $endsAt = '';

    #[Validate('nullable|image|mimes:jpg,jpeg,png,webp|max:2048')]
    public $titleImage;

    #[Validate('nullable|integer|min:0|max:120')]
    public $attendanceGraceMinutes = '';

    public string $visibility = 'public';

    #[Validate('nullable|email|max:255')]
    public string $notificationEmail = '';

    /**
     * Dynamic rules for fields that need runtime context.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'visibility' => ['required', Rule::in(array_column(EventVisibility::cases(), 'value'))],
        ];
    }

    /**
     * Populate form fields from an event model.
     *
     * @param  array<string, mixed>  $data
     */
    public function fillFromEvent(array $data): void
    {
        $this->name = $data['name'];
        $this->description = $data['description'];
        $this->location = $data['location'];
        $this->startsAt = $data['startsAt'];
        $this->endsAt = $data['endsAt'];
        $this->attendanceGraceMinutes = $data['attendanceGraceMinutes'];
        $this->visibility = $data['visibility'];
        $this->notificationEmail = $data['notificationEmail'];
    }
}
