<?php

use App\Actions\DeleteEventImage;
use App\Events\Activity\EventImageDeleted;
use App\Models\Event;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event as EventFacade;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->org = Organization::factory()->create();
    $this->event = Event::factory()->for($this->org)->create();
    $this->user = User::factory()->create();
});

it('removes file and nulls path', function () {
    Storage::fake('public');

    $image = UploadedFile::fake()->image('banner.jpg');
    $path = $image->store('events/'.$this->event->id, 'public');
    $this->event->update(['title_image_path' => $path]);

    $action = new DeleteEventImage;
    $result = $action->execute($this->event, $this->user);

    expect($result->title_image_path)->toBeNull();
    Storage::disk('public')->assertMissing($path);
});

it('handles event with no image', function () {
    $action = new DeleteEventImage;
    $result = $action->execute($this->event, $this->user);

    expect($result->title_image_path)->toBeNull();
});

it('dispatches EventImageDeleted activity event with causer', function () {
    Storage::fake('public');
    EventFacade::fake([EventImageDeleted::class]);

    $image = UploadedFile::fake()->image('banner.jpg');
    $path = $image->store('events/'.$this->event->id, 'public');
    $this->event->update(['title_image_path' => $path]);

    $action = new DeleteEventImage;
    $action->execute($this->event, $this->user);

    EventFacade::assertDispatched(EventImageDeleted::class, fn ($e) => $e->causer->id === $this->user->id);
});
