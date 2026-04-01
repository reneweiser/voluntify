<?php

use App\Models\Announcement;
use App\Models\Event;
use App\Models\HintText;
use App\Models\Organization;
use App\Models\Project;
use App\Models\ProjectGearItem;
use App\Models\ProjectScanner;
use App\Models\Shift;
use App\Models\ShiftSignup;
use App\Models\Volunteer;
use App\Models\VolunteerJob;

it('purges projects pending deletion for 30+ days', function () {
    $org = Organization::factory()->create();
    $project = Project::factory()->for($org)->create([
        'deletion_requested_at' => now()->subDays(31),
    ]);

    $this->artisan('app:purge-pending-deletions')
        ->assertSuccessful();

    expect(Project::find($project->id))->toBeNull();
});

it('does not purge projects pending deletion for less than 30 days', function () {
    $org = Organization::factory()->create();
    $project = Project::factory()->for($org)->create([
        'deletion_requested_at' => now()->subDays(15),
    ]);

    $this->artisan('app:purge-pending-deletions')
        ->assertSuccessful();

    expect(Project::find($project->id))->not->toBeNull();
});

it('purges events pending deletion for 30+ days', function () {
    $org = Organization::factory()->create();
    $project = Project::factory()->for($org)->create();
    $event = Event::factory()->for($org)->for($project)->create([
        'deletion_requested_at' => now()->subDays(31),
    ]);

    $this->artisan('app:purge-pending-deletions')
        ->assertSuccessful();

    expect(Event::find($event->id))->toBeNull();
});

it('does not purge events pending deletion for less than 30 days', function () {
    $org = Organization::factory()->create();
    $project = Project::factory()->for($org)->create();
    $event = Event::factory()->for($org)->for($project)->create([
        'deletion_requested_at' => now()->subDays(10),
    ]);

    $this->artisan('app:purge-pending-deletions')
        ->assertSuccessful();

    expect(Event::find($event->id))->not->toBeNull();
});

it('reports no work when nothing to purge', function () {
    $this->artisan('app:purge-pending-deletions')
        ->assertSuccessful()
        ->expectsOutputToContain('No pending deletions to purge.');
});

it('cascades deletion of all child records when purging a project', function () {
    $org = Organization::factory()->create();
    $project = Project::factory()->for($org)->create([
        'deletion_requested_at' => now()->subDays(31),
    ]);

    $event = Event::factory()->for($org)->for($project)->create();
    $job = VolunteerJob::factory()->for($event)->create();
    $shift = Shift::factory()->for($job, 'volunteerJob')->create();
    $volunteer = Volunteer::factory()->for($project)->verified()->create();
    $signup = ShiftSignup::factory()->for($shift)->for($volunteer)->create();
    $gearItem = ProjectGearItem::factory()->for($project)->create();
    $scanner = ProjectScanner::factory()->for($project)->create();
    $hintText = HintText::factory()->for($project)->create();
    $announcement = Announcement::factory()->for($project)->create();

    $this->artisan('app:purge-pending-deletions')
        ->assertSuccessful();

    expect(Project::find($project->id))->toBeNull()
        ->and(Event::find($event->id))->toBeNull()
        ->and(VolunteerJob::find($job->id))->toBeNull()
        ->and(Shift::find($shift->id))->toBeNull()
        ->and(Volunteer::find($volunteer->id))->toBeNull()
        ->and(ShiftSignup::find($signup->id))->toBeNull()
        ->and(ProjectGearItem::find($gearItem->id))->toBeNull()
        ->and(ProjectScanner::find($scanner->id))->toBeNull()
        ->and(HintText::find($hintText->id))->toBeNull()
        ->and(Announcement::find($announcement->id))->toBeNull();
});

it('does not purge project at exactly 30 days (strict greater-than boundary)', function () {
    $org = Organization::factory()->create();
    $project = Project::factory()->for($org)->create([
        'deletion_requested_at' => now()->subDays(30),
    ]);

    $this->artisan('app:purge-pending-deletions')
        ->assertSuccessful();

    // The command uses strict < comparison: subDays(30) means exactly 30 days,
    // which is NOT less than the cutoff of subDays(30). Purge requires >30 days.
    expect(Project::find($project->id))->not->toBeNull();
});
