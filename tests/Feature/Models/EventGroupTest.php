<?php

use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\EventGroup;
use App\Models\Organization;

it('auto-generates a 32-char public_token on creation', function () {
    $group = EventGroup::factory()->create();

    expect($group->public_token)->toBeString()
        ->and(strlen($group->public_token))->toBe(32);
});

it('belongs to an organization', function () {
    $org = Organization::factory()->create();
    $group = EventGroup::factory()->for($org)->create();

    expect($group->organization->id)->toBe($org->id);
});

it('has many events', function () {
    $org = Organization::factory()->create();
    $group = EventGroup::factory()->for($org)->create();
    Event::factory()->for($org)->count(3)->create(['event_group_id' => $group->id]);

    expect($group->events)->toHaveCount(3);
});

it('scopes publishedEvents to published events ordered by starts_at', function () {
    $org = Organization::factory()->create();
    $group = EventGroup::factory()->for($org)->create();

    $laterEvent = Event::factory()->for($org)->published()->create([
        'event_group_id' => $group->id,
        'starts_at' => now()->addDays(10),
        'ends_at' => now()->addDays(10)->addHours(4),
    ]);
    $earlierEvent = Event::factory()->for($org)->published()->create([
        'event_group_id' => $group->id,
        'starts_at' => now()->addDays(5),
        'ends_at' => now()->addDays(5)->addHours(4),
    ]);
    Event::factory()->for($org)->create([
        'event_group_id' => $group->id,
        'status' => EventStatus::Draft,
    ]);

    $published = $group->publishedEvents;

    expect($published)->toHaveCount(2)
        ->and($published->first()->id)->toBe($earlierEvent->id)
        ->and($published->last()->id)->toBe($laterEvent->id);
});
