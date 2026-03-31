<?php

namespace Database\Factories;

use App\Enums\ArrivalMethod;
use App\Models\Event;
use App\Models\EventArrival;
use App\Models\Ticket;
use App\Models\User;
use App\Models\Volunteer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EventArrival>
 */
class EventArrivalFactory extends Factory
{
    protected $model = EventArrival::class;

    public function definition(): array
    {
        return [
            'ticket_id' => Ticket::factory(),
            'volunteer_id' => Volunteer::factory(),
            'event_id' => Event::factory(),
            'scanned_by' => User::factory(),
            'scanned_at' => now(),
            'method' => fake()->randomElement(ArrivalMethod::cases()),
            'flagged' => false,
            'flag_reason' => null,
        ];
    }
}
