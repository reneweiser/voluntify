<?php

namespace Database\Seeders;

use App\Enums\EventStatus;
use App\Enums\StaffRole;
use App\Models\Event;
use App\Models\Organization;
use App\Models\Shift;
use App\Models\ShiftSignup;
use App\Models\User;
use App\Models\Volunteer;
use App\Models\VolunteerJob;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        $org = Organization::factory()->create([
            'name' => 'Voluntify Demo Org',
            'slug' => 'voluntify-demo',
        ]);

        $org->users()->attach($user, ['role' => StaffRole::Organizer]);

        // Published event 1 — upcoming community fair
        $event1 = Event::factory()->for($org)->published()->create([
            'name' => 'Spring Community Fair',
            'slug' => 'spring-community-fair',
            'starts_at' => now()->addWeeks(2),
            'ends_at' => now()->addWeeks(2)->addHours(8),
        ]);

        $this->seedEventData($event1);

        // Published event 2 — charity run
        $event2 = Event::factory()->for($org)->published()->create([
            'name' => 'Annual Charity Run',
            'slug' => 'annual-charity-run',
            'starts_at' => now()->addMonth(),
            'ends_at' => now()->addMonth()->addHours(6),
        ]);

        $this->seedEventData($event2);

        // Draft event
        Event::factory()->for($org)->create([
            'name' => 'Summer Gala (Draft)',
            'slug' => 'summer-gala',
            'status' => EventStatus::Draft,
        ]);
    }

    private function seedEventData(Event $event): void
    {
        $jobNames = ['Registration Desk', 'Setup Crew', 'First Aid'];

        foreach ($jobNames as $jobName) {
            $job = VolunteerJob::factory()->for($event)->create([
                'name' => $jobName,
            ]);

            $shifts = Shift::factory()
                ->count(2)
                ->for($job)
                ->sequence(
                    [
                        'starts_at' => $event->starts_at,
                        'ends_at' => $event->starts_at->copy()->addHours(4),
                    ],
                    [
                        'starts_at' => $event->starts_at->copy()->addHours(4),
                        'ends_at' => $event->ends_at,
                    ],
                )
                ->create(['capacity' => 10]);

            foreach ($shifts as $shift) {
                $volunteers = Volunteer::factory()->count(3)->create();
                foreach ($volunteers as $volunteer) {
                    ShiftSignup::factory()
                        ->for($volunteer)
                        ->for($shift)
                        ->create();
                }
            }
        }
    }
}
