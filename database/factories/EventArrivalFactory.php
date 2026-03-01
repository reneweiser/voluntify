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
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\EventArrival>
 */
class EventArrivalFactory extends Factory
{
    protected $model = EventArrival::class;

    public function definition(): array
    {
        $volunteer = Volunteer::factory();
        $event = Event::factory();

        return [
            'ticket_id' => Ticket::factory()->state([
                'volunteer_id' => $volunteer,
                'event_id' => $event,
            ]),
            'volunteer_id' => $volunteer,
            'event_id' => $event,
            'scanned_by' => User::factory(),
            'scanned_at' => now(),
            'method' => fake()->randomElement(ArrivalMethod::cases()),
            'flagged' => false,
            'flag_reason' => null,
        ];
    }
}
