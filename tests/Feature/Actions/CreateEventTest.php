<?php

use App\Actions\CreateEvent;
use App\Enums\EventStatus;
use App\Events\Activity\EventCreated;
use App\Models\Organization;
use App\Models\Project;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->org = Organization::factory()->create();
    $this->project = Project::factory()->for($this->org, 'organization')->create();
    $this->user = User::factory()->create();
});

it('creates a draft event for the organization', function () {
    $action = new CreateEvent;

    $event = $action->execute(
        organization: $this->org,
        project: $this->project,
        name: 'Summer Carnival',
        description: 'A fun summer event',
        location: 'Central Park',
        startsAt: Carbon::parse('2026-07-01 10:00'),
        endsAt: Carbon::parse('2026-07-01 18:00'),
        causer: $this->user,
    );

    expect($event->exists)->toBeTrue()
        ->and($event->organization_id)->toBe($this->org->id)
        ->and($event->name)->toBe('Summer Carnival')
        ->and($event->status)->toBe(EventStatus::Draft)
        ->and($event->description)->toBe('A fun summer event')
        ->and($event->location)->toBe('Central Park');
});

it('generates a slug from the event name', function () {
    $action = new CreateEvent;

    $event = $action->execute(
        organization: $this->org,
        project: $this->project,
        name: 'My Great Event',
        description: null,
        location: null,
        startsAt: Carbon::parse('2026-07-01 10:00'),
        endsAt: Carbon::parse('2026-07-01 18:00'),
        causer: $this->user,
    );

    expect($event->slug)->toBe('my-great-event');
});

it('auto-generates a public token', function () {
    $action = new CreateEvent;

    $event = $action->execute(
        organization: $this->org,
        project: $this->project,
        name: 'Token Event',
        description: null,
        location: null,
        startsAt: Carbon::parse('2026-07-01 10:00'),
        endsAt: Carbon::parse('2026-07-01 18:00'),
        causer: $this->user,
    );

    expect($event->public_token)->toBeString()
        ->and(strlen($event->public_token))->toBe(32);
});

it('allows nullable description and location', function () {
    $action = new CreateEvent;

    $event = $action->execute(
        organization: $this->org,
        project: $this->project,
        name: 'Minimal Event',
        description: null,
        location: null,
        startsAt: Carbon::parse('2026-07-01 10:00'),
        endsAt: Carbon::parse('2026-07-01 18:00'),
        causer: $this->user,
    );

    expect($event->description)->toBeNull()
        ->and($event->location)->toBeNull();
});

it('appends numeric suffix for duplicate slugs within same organization', function () {
    $action = new CreateEvent;

    $first = $action->execute(
        organization: $this->org,
        project: $this->project,
        name: 'Summer Carnival',
        description: null,
        location: null,
        startsAt: Carbon::parse('2026-07-01 10:00'),
        endsAt: Carbon::parse('2026-07-01 18:00'),
        causer: $this->user,
    );

    $second = $action->execute(
        organization: $this->org,
        project: $this->project,
        name: 'Summer Carnival',
        description: null,
        location: null,
        startsAt: Carbon::parse('2026-08-01 10:00'),
        endsAt: Carbon::parse('2026-08-01 18:00'),
        causer: $this->user,
    );

    expect($first->slug)->toBe('summer-carnival')
        ->and($second->slug)->toBe('summer-carnival-2');
});

it('stores title image when provided', function () {
    Storage::fake('public');

    $action = new CreateEvent;
    $image = UploadedFile::fake()->image('banner.jpg', 1200, 400);

    $event = $action->execute(
        organization: $this->org,
        project: $this->project,
        name: 'Image Event',
        description: null,
        location: null,
        startsAt: Carbon::parse('2026-07-01 10:00'),
        endsAt: Carbon::parse('2026-07-01 18:00'),
        titleImage: $image,
        causer: $this->user,
    );

    expect($event->title_image_path)->not->toBeNull();
    Storage::disk('public')->assertExists($event->title_image_path);
});

it('creates event without image', function () {
    $action = new CreateEvent;

    $event = $action->execute(
        organization: $this->org,
        project: $this->project,
        name: 'No Image Event',
        description: null,
        location: null,
        startsAt: Carbon::parse('2026-07-01 10:00'),
        endsAt: Carbon::parse('2026-07-01 18:00'),
        causer: $this->user,
    );

    expect($event->title_image_path)->toBeNull();
});

it('allows same slug in different organizations', function () {
    $action = new CreateEvent;
    $otherOrg = Organization::factory()->create();
    $otherProject = Project::factory()->for($otherOrg, 'organization')->create();

    $first = $action->execute(
        organization: $this->org,
        project: $this->project,
        name: 'Summer Carnival',
        description: null,
        location: null,
        startsAt: Carbon::parse('2026-07-01 10:00'),
        endsAt: Carbon::parse('2026-07-01 18:00'),
        causer: $this->user,
    );

    $second = $action->execute(
        organization: $otherOrg,
        project: $otherProject,
        name: 'Summer Carnival',
        description: null,
        location: null,
        startsAt: Carbon::parse('2026-07-01 10:00'),
        endsAt: Carbon::parse('2026-07-01 18:00'),
        causer: $this->user,
    );

    expect($first->slug)->toBe('summer-carnival')
        ->and($second->slug)->toBe('summer-carnival');
});

it('dispatches EventCreated activity event with causer', function () {
    Event::fake([EventCreated::class]);

    $action = new CreateEvent;

    $action->execute(
        organization: $this->org,
        project: $this->project,
        name: 'Dispatch Test',
        description: null,
        location: null,
        startsAt: Carbon::parse('2026-07-01 10:00'),
        endsAt: Carbon::parse('2026-07-01 18:00'),
        causer: $this->user,
    );

    Event::assertDispatched(EventCreated::class, fn ($e) => $e->causer->id === $this->user->id);
});
