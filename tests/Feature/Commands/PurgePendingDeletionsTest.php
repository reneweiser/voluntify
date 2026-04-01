<?php

use App\Models\Event;
use App\Models\Organization;
use App\Models\Project;

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
