<?php

namespace Database\Factories;

use App\Enums\CustomFieldType;
use App\Models\CustomRegistrationField;
use App\Models\Event;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CustomRegistrationField> */
class CustomRegistrationFieldFactory extends Factory
{
    protected $model = CustomRegistrationField::class;

    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'label' => fake()->words(2, true),
            'type' => CustomFieldType::Text,
            'options' => null,
            'required' => false,
            'sort_order' => 0,
        ];
    }

    public function required(): static
    {
        return $this->state(fn () => [
            'required' => true,
        ]);
    }

    public function select(array $choices = ['A', 'B']): static
    {
        return $this->state(fn () => [
            'type' => CustomFieldType::Select,
            'options' => ['choices' => $choices],
        ]);
    }

    public function checkbox(): static
    {
        return $this->state(fn () => [
            'type' => CustomFieldType::Checkbox,
        ]);
    }

    public function multiline(): static
    {
        return $this->state(fn () => [
            'options' => ['multiline' => true],
        ]);
    }
}
