<?php

use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\Organization;
use App\Models\Project;

it('auto-generates a 32-char public_token on creation', function () {
    $project = Project::factory()->create();

    expect($project->public_token)->toBeString()
        ->and(strlen($project->public_token))->toBe(32);
});

it('belongs to an organization', function () {
    $org = Organization::factory()->create();
    $project = Project::factory()->for($org)->create();

    expect($project->organization->id)->toBe($org->id);
});

it('has many events', function () {
    $org = Organization::factory()->create();
    $project = Project::factory()->for($org)->create();
    Event::factory()->for($org)->count(3)->create(['project_id' => $project->id]);

    expect($project->events)->toHaveCount(3);
});

it('scopes publishedEvents to published events ordered by starts_at', function () {
    $org = Organization::factory()->create();
    $project = Project::factory()->for($org)->create();

    $laterEvent = Event::factory()->for($org)->published()->create([
        'project_id' => $project->id,
        'starts_at' => now()->addDays(10),
        'ends_at' => now()->addDays(10)->addHours(4),
    ]);
    $earlierEvent = Event::factory()->for($org)->published()->create([
        'project_id' => $project->id,
        'starts_at' => now()->addDays(5),
        'ends_at' => now()->addDays(5)->addHours(4),
    ]);
    Event::factory()->for($org)->create([
        'project_id' => $project->id,
        'status' => EventStatus::Draft,
    ]);

    $published = $project->publishedEvents;

    expect($published)->toHaveCount(2)
        ->and($published->first()->id)->toBe($earlierEvent->id)
        ->and($published->last()->id)->toBe($laterEvent->id);
});

it('includes PublishedClosed events in publishedEvents', function () {
    $org = Organization::factory()->create();
    $project = Project::factory()->for($org)->create();

    $openEvent = Event::factory()->for($org)->published()->create([
        'project_id' => $project->id,
        'starts_at' => now()->addDays(5),
        'ends_at' => now()->addDays(5)->addHours(4),
    ]);
    $closedEvent = Event::factory()->for($org)->publishedClosed()->create([
        'project_id' => $project->id,
        'starts_at' => now()->addDays(10),
        'ends_at' => now()->addDays(10)->addHours(4),
    ]);

    $published = $project->publishedEvents;

    expect($published)->toHaveCount(2)
        ->and($published->pluck('id')->all())->toBe([$openEvent->id, $closedEvent->id]);
});

it('excludes Draft and Archived events from publishedEvents', function () {
    $org = Organization::factory()->create();
    $project = Project::factory()->for($org)->create();

    Event::factory()->for($org)->create([
        'project_id' => $project->id,
        'status' => EventStatus::Draft,
    ]);
    Event::factory()->for($org)->archived()->create([
        'project_id' => $project->id,
    ]);

    expect($project->publishedEvents)->toHaveCount(0);
});
