<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\Ticket;
use App\Models\Volunteer;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Ticket>
 */
class TicketFactory extends Factory
{
    protected $model = Ticket::class;

    public function definition(): array
    {
        return [
            'volunteer_id' => Volunteer::factory(),
            'project_id' => Project::factory(),
            'jwt_token' => 'eyJ'.Str::random(100),
        ];
    }
}
