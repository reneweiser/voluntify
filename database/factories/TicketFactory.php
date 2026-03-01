<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\Ticket;
use App\Models\Volunteer;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Ticket>
 */
class TicketFactory extends Factory
{
    protected $model = Ticket::class;

    public function definition(): array
    {
        return [
            'volunteer_id' => Volunteer::factory(),
            'event_id' => Event::factory(),
            'jwt_token' => 'eyJ'.Str::random(100),
        ];
    }
}
