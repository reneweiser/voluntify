<?php

namespace Database\Factories;

use App\Enums\EmailTemplateType;
use App\Models\EmailTemplate;
use App\Models\Event;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\EmailTemplate>
 */
class EmailTemplateFactory extends Factory
{
    protected $model = EmailTemplate::class;

    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'type' => EmailTemplateType::SignupConfirmation,
            'subject' => fake()->sentence(),
            'body' => fake()->paragraph(),
        ];
    }
}
