<?php

use App\Models\Event;
use App\Models\EventGroup;
use App\Models\Organization;
use Illuminate\Support\Facades\Storage;

it('returns storage URL when title_image_path is set on Event', function () {
    Storage::fake('public');

    $event = Event::factory()->for(Organization::factory())->create([
        'title_image_path' => 'events/1/banner.jpg',
    ]);

    expect($event->titleImageUrl())->toBe(Storage::disk('public')->url('events/1/banner.jpg'));
});

it('returns null when title_image_path is null on Event', function () {
    $event = Event::factory()->for(Organization::factory())->create([
        'title_image_path' => null,
    ]);

    expect($event->titleImageUrl())->toBeNull();
});

it('returns storage URL for EventGroup when title_image_path is set', function () {
    Storage::fake('public');

    $group = EventGroup::factory()->create([
        'title_image_path' => 'event-groups/1/banner.jpg',
    ]);

    expect($group->titleImageUrl())->toBe(Storage::disk('public')->url('event-groups/1/banner.jpg'));
});

it('returns null for EventGroup when title_image_path is null', function () {
    $group = EventGroup::factory()->create([
        'title_image_path' => null,
    ]);

    expect($group->titleImageUrl())->toBeNull();
});
