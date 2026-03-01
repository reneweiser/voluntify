<?php

use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\EventArrival;
use App\Models\Organization;
use App\Models\Ticket;
use App\Models\Volunteer;
use App\Models\VolunteerJob;

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

it('casts status to EventStatus enum', function () {
    $event = Event::factory()->published()->create();

    expect($event->status)->toBe(EventStatus::Published);
});

it('has published scope', function () {
    Event::factory()->create(['status' => EventStatus::Draft]);
    Event::factory()->published()->create();
    Event::factory()->archived()->create();

    expect(Event::published()->count())->toBe(1);
});

it('belongs to an organization', function () {
    $org = Organization::factory()->create();
    $event = Event::factory()->for($org)->create();

    expect($event->organization->id)->toBe($org->id);
});

it('has many volunteer jobs', function () {
    $event = Event::factory()->create();
    VolunteerJob::factory()->count(2)->for($event)->create();

    expect($event->volunteerJobs)->toHaveCount(2);
});

it('has many tickets', function () {
    $event = Event::factory()->create();
    Ticket::factory()->count(2)->for($event)->create();

    expect($event->tickets)->toHaveCount(2);
});

it('has many event arrivals', function () {
    $event = Event::factory()->create();
    $volunteer = Volunteer::factory()->create();
    $ticket = Ticket::factory()->for($event)->for($volunteer)->create();
    EventArrival::factory()->create([
        'ticket_id' => $ticket->id,
        'volunteer_id' => $volunteer->id,
        'event_id' => $event->id,
    ]);

    expect($event->eventArrivals)->toHaveCount(1);
});

it('enforces unique slug per organization', function () {
    $org = Organization::factory()->create();
    Event::factory()->for($org)->create(['slug' => 'annual-gala']);

    expect(fn () => Event::factory()->for($org)->create(['slug' => 'annual-gala']))
        ->toThrow(\Illuminate\Database\UniqueConstraintViolationException::class);
});

it('allows same slug in different organizations', function () {
    $orgA = Organization::factory()->create();
    $orgB = Organization::factory()->create();

    Event::factory()->for($orgA)->create(['slug' => 'annual-gala']);
    $eventB = Event::factory()->for($orgB)->create(['slug' => 'annual-gala']);

    expect($eventB)->toBeInstanceOf(Event::class);
});

it('casts starts_at and ends_at to datetime', function () {
    $event = Event::factory()->create();

    expect($event->starts_at)->toBeInstanceOf(\Carbon\CarbonInterface::class)
        ->and($event->ends_at)->toBeInstanceOf(\Carbon\CarbonInterface::class);
});

it('has factory states for published and archived', function () {
    $published = Event::factory()->published()->create();
    $archived = Event::factory()->archived()->create();

    expect($published->status)->toBe(EventStatus::Published)
        ->and($archived->status)->toBe(EventStatus::Archived);
});
