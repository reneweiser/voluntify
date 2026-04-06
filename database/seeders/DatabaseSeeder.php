<?php

namespace Database\Seeders;

use App\Enums\EventStatus;
use App\Enums\StaffRole;
use App\Models\Event;
use App\Models\Organization;
use App\Models\Project;
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

        // Project containing events
        $project = Project::factory()->for($org)->create([
            'name' => 'Spring Festival Weekend',
            'description' => 'A multi-event weekend festival with community activities.',
        ]);

        // Published event 1 — upcoming community fair
        $event1 = Event::factory()->for($org)->for($project)->published()->create([
            'name' => 'Spring Community Fair',
            'slug' => 'spring-community-fair',
            'starts_at' => now()->addWeeks(2),
            'ends_at' => now()->addWeeks(2)->addHours(8),
        ]);

        $this->seedEventData($event1);

        // Published event 2 — charity run
        $event2 = Event::factory()->for($org)->for($project)->published()->create([
            'name' => 'Annual Charity Run',
            'slug' => 'annual-charity-run',
            'starts_at' => now()->addMonth(),
            'ends_at' => now()->addMonth()->addHours(6),
        ]);

        $this->seedEventData($event2);

        // Draft event
        Event::factory()->for($org)->for($project)->create([
            'name' => 'Summer Gala (Draft)',
            'slug' => 'summer-gala',
            'status' => EventStatus::Draft,
        ]);

        // Second project for testing project-scoped access
        $project2 = Project::factory()->for($org)->create([
            'name' => 'Charity Auction Night',
            'description' => 'An exclusive charity auction event.',
        ]);

        Event::factory()->for($org)->for($project2)->published()->create([
            'name' => 'Art & Wine Auction',
            'slug' => 'art-wine-auction',
            'starts_at' => now()->addWeeks(3),
            'ends_at' => now()->addWeeks(3)->addHours(5),
        ]);

        // Project-only Organizer — assigned to project2 only
        $projectOrganizer = User::factory()->create([
            'name' => 'Project Organizer',
            'email' => 'project@example.com',
        ]);
        $project2->users()->attach($projectOrganizer, ['role' => StaffRole::Organizer]);
        $projectOrganizer->update(['current_organization_id' => $org->id]);
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
                        'shift_date' => $event->starts_at->toDateString(),
                        'starts_at' => $event->starts_at,
                        'ends_at' => $event->starts_at->copy()->addHours(4),
                    ],
                    [
                        'shift_date' => $event->starts_at->copy()->addHours(4)->toDateString(),
                        'starts_at' => $event->starts_at->copy()->addHours(4),
                        'ends_at' => $event->ends_at,
                    ],
                )
                ->create(['capacity' => 10]);

            foreach ($shifts as $shift) {
                $volunteers = Volunteer::factory()
                    ->count(3)
                    ->for($event->project)
                    ->create();
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
