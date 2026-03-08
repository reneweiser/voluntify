<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\EventAnnouncement;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\EventAnnouncement>
 */
class EventAnnouncementFactory extends Factory
{
    protected $model = EventAnnouncement::class;

    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'subject' => fake()->sentence(),
            'body' => fake()->paragraph(),
            'sent_at' => now(),
            'sent_by' => User::factory(),
        ];
    }
}
