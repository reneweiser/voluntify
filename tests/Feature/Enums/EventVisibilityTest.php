<?php

use App\Enums\EventVisibility;
use App\Models\Event;

it('has public and private cases', function () {
    expect(EventVisibility::cases())->toHaveCount(2)
        ->and(EventVisibility::Public->value)->toBe('public')
        ->and(EventVisibility::Private->value)->toBe('private');
});

it('returns correct labels', function () {
    expect(EventVisibility::Public->label())->toBe('Public')
        ->and(EventVisibility::Private->label())->toBe('Private');
});

it('EventFactory private state creates private event', function () {
    $event = Event::factory()->private()->create();

    expect($event->visibility)->toBe(EventVisibility::Private);
});

it('EventFactory defaults to public visibility', function () {
    $event = Event::factory()->create();

    expect($event->visibility)->toBe(EventVisibility::Public);
});
