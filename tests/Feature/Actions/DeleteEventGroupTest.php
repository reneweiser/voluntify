<?php

use App\Actions\DeleteEventGroup;
use App\Models\Event;
use App\Models\EventGroup;
use App\Models\Organization;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->org = Organization::factory()->create();
    $this->group = EventGroup::factory()->for($this->org)->create();
});

it('deletes the event group record', function () {
    $action = new DeleteEventGroup;

    $action->execute($this->group);

    expect(EventGroup::find($this->group->id))->toBeNull();
});

it('deletes the stored image', function () {
    Storage::fake('public');
    $image = UploadedFile::fake()->image('banner.jpg');
    $path = $image->store('event-groups/1', 'public');
    $this->group->update(['title_image_path' => $path]);

    $action = new DeleteEventGroup;
    $action->execute($this->group);

    Storage::disk('public')->assertMissing($path);
});

it('ungroups member events — sets event_group_id to null', function () {
    $event = Event::factory()->for($this->org)->create(['event_group_id' => $this->group->id]);

    $action = new DeleteEventGroup;
    $action->execute($this->group);

    expect($event->fresh()->event_group_id)->toBeNull()
        ->and($event->fresh()->exists)->toBeTrue();
});

it('handles group with no image', function () {
    $this->group->update(['title_image_path' => null]);

    $action = new DeleteEventGroup;
    $action->execute($this->group);

    expect(EventGroup::find($this->group->id))->toBeNull();
});
