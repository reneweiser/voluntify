<?php

use App\Actions\DeleteEventImage;
use App\Actions\UpdateEvent;
use App\Exceptions\DomainException;
use App\Models\Event;
use App\Models\Organization;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->org = Organization::factory()->create();
    $this->action = new UpdateEvent;
});

it('updates event fields', function () {
    $event = Event::factory()->for($this->org)->create();

    $updated = $this->action->execute(
        event: $event,
        name: 'Updated Name',
        description: 'New description',
        location: 'New Location',
        startsAt: Carbon::parse('2026-08-01 10:00'),
        endsAt: Carbon::parse('2026-08-01 18:00'),
    );

    expect($updated->name)->toBe('Updated Name')
        ->and($updated->slug)->toBe('updated-name')
        ->and($updated->description)->toBe('New description')
        ->and($updated->location)->toBe('New Location');
});

it('cannot update archived events', function () {
    $event = Event::factory()->for($this->org)->archived()->create();

    expect(fn () => $this->action->execute(
        event: $event,
        name: 'Updated Name',
        description: null,
        location: null,
        startsAt: Carbon::parse('2026-08-01 10:00'),
        endsAt: Carbon::parse('2026-08-01 18:00'),
    ))->toThrow(DomainException::class, 'Cannot update an archived event.');
});

it('can update published events', function () {
    $event = Event::factory()->for($this->org)->published()->create();

    $updated = $this->action->execute(
        event: $event,
        name: 'Updated Published',
        description: null,
        location: null,
        startsAt: Carbon::parse('2026-08-01 10:00'),
        endsAt: Carbon::parse('2026-08-01 18:00'),
    );

    expect($updated->name)->toBe('Updated Published');
});

it('appends numeric suffix when slug collides with another event', function () {
    Event::factory()->for($this->org)->create(['slug' => 'same-name']);
    $event = Event::factory()->for($this->org)->create(['slug' => 'original']);

    $updated = $this->action->execute(
        event: $event,
        name: 'Same Name',
        description: null,
        location: null,
        startsAt: Carbon::parse('2026-08-01 10:00'),
        endsAt: Carbon::parse('2026-08-01 18:00'),
    );

    expect($updated->slug)->toBe('same-name-2');
});

it('stores title image when updating event', function () {
    Storage::fake('public');

    $event = Event::factory()->for($this->org)->create();
    $image = UploadedFile::fake()->image('banner.jpg', 1200, 400);

    $updated = $this->action->execute(
        event: $event,
        name: $event->name,
        description: $event->description,
        location: $event->location,
        startsAt: $event->starts_at,
        endsAt: $event->ends_at,
        titleImage: $image,
    );

    expect($updated->title_image_path)->not->toBeNull();
    Storage::disk('public')->assertExists($updated->title_image_path);
});

it('replaces old image when updating with new image', function () {
    Storage::fake('public');

    $oldImage = UploadedFile::fake()->image('old.jpg');
    $oldPath = $oldImage->store('events/1', 'public');

    $event = Event::factory()->for($this->org)->create(['title_image_path' => $oldPath]);
    $newImage = UploadedFile::fake()->image('new.jpg', 1200, 400);

    $updated = $this->action->execute(
        event: $event,
        name: $event->name,
        description: $event->description,
        location: $event->location,
        startsAt: $event->starts_at,
        endsAt: $event->ends_at,
        titleImage: $newImage,
    );

    Storage::disk('public')->assertMissing($oldPath);
    Storage::disk('public')->assertExists($updated->title_image_path);
});

it('deletes event image', function () {
    Storage::fake('public');

    $image = UploadedFile::fake()->image('banner.jpg');
    $path = $image->store('events/1', 'public');

    $event = Event::factory()->for($this->org)->create(['title_image_path' => $path]);

    $deleteAction = new DeleteEventImage;
    $updated = $deleteAction->execute($event);

    expect($updated->title_image_path)->toBeNull();
    Storage::disk('public')->assertMissing($path);
});

it('keeps same slug when name does not change', function () {
    $event = Event::factory()->for($this->org)->create(['name' => 'My Event', 'slug' => 'my-event']);

    $updated = $this->action->execute(
        event: $event,
        name: 'My Event',
        description: 'Updated description',
        location: null,
        startsAt: Carbon::parse('2026-08-01 10:00'),
        endsAt: Carbon::parse('2026-08-01 18:00'),
    );

    expect($updated->slug)->toBe('my-event');
});
