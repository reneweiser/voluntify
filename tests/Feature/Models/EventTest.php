<?php

use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\Organization;

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

it('has published scope', function () {
    Event::factory()->create(['status' => EventStatus::Draft]);
    Event::factory()->published()->create();
    Event::factory()->archived()->create();

    expect(Event::published()->count())->toBe(1);
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
