<?php

use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\Organization;
use App\Models\Project;
use Illuminate\Database\UniqueConstraintViolationException;

it('auto-generates a public_token on creation', function () {
    $event = Event::factory()->create();

    expect($event->public_token)
        ->toBeString()
        ->toHaveLength(32);
});

it('generates unique public tokens', function () {
    $tokens = Event::factory()
        ->count(5)
        ->create()
        ->pluck('public_token')
        ->unique();

    expect($tokens)->toHaveCount(5);
});

it('does not overwrite an explicit public_token', function () {
    $event = Event::factory()->create(['public_token' => 'abcdefghijklmnopqrstuvwxyz123456']);

    expect($event->public_token)->toBe('abcdefghijklmnopqrstuvwxyz123456');
});

it('has published scope that includes PublishedOpen', function () {
    Event::factory()->create(['status' => EventStatus::Draft]);
    Event::factory()->published()->create();
    Event::factory()->archived()->create();

    expect(Event::published()->count())->toBe(1);
});

it('has published scope that includes PublishedClosed', function () {
    Event::factory()->publishedClosed()->create();

    expect(Event::published()->count())->toBe(1);
});

it('has published scope that includes both PublishedOpen and PublishedClosed', function () {
    Event::factory()->published()->create();
    Event::factory()->publishedClosed()->create();
    Event::factory()->create(['status' => EventStatus::Draft]);
    Event::factory()->archived()->create();

    expect(Event::published()->count())->toBe(2);
});

it('enforces unique slug per organization', function () {
    $org = Organization::factory()->create();
    Event::factory()->for($org)->create(['slug' => 'annual-gala']);

    expect(fn () => Event::factory()->for($org)->create(['slug' => 'annual-gala']))
        ->toThrow(UniqueConstraintViolationException::class);
});

it('allows same slug in different organizations', function () {
    $orgA = Organization::factory()->create();
    $orgB = Organization::factory()->create();

    Event::factory()->for($orgA)->create(['slug' => 'annual-gala']);
    $eventB = Event::factory()->for($orgB)->create(['slug' => 'annual-gala']);

    expect($eventB)->toBeInstanceOf(Event::class);
});

it('factory auto-creates project in same organization', function () {
    $org = Organization::factory()->create();
    $event = Event::factory()->for($org)->create();

    expect($event->project_id)->not->toBeNull();

    $project = Project::find($event->project_id);
    expect($project)->not->toBeNull()
        ->and($project->organization_id)->toBe($org->id);
});

it('factory respects explicit project_id', function () {
    $org = Organization::factory()->create();
    $project = Project::factory()->for($org)->create();
    $event = Event::factory()->for($org)->create(['project_id' => $project->id]);

    expect($event->project_id)->toBe($project->id);
});
