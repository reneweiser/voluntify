<?php

use App\Actions\DeleteEventImage;
use App\Models\Event;
use App\Models\Organization;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->org = Organization::factory()->create();
    $this->event = Event::factory()->for($this->org)->create();
});

it('removes file and nulls path', function () {
    Storage::fake('public');

    $image = UploadedFile::fake()->image('banner.jpg');
    $path = $image->store('events/'.$this->event->id, 'public');
    $this->event->update(['title_image_path' => $path]);

    $action = new DeleteEventImage;
    $result = $action->execute($this->event);

    expect($result->title_image_path)->toBeNull();
    Storage::disk('public')->assertMissing($path);
});

it('handles event with no image', function () {
    $action = new DeleteEventImage;
    $result = $action->execute($this->event);

    expect($result->title_image_path)->toBeNull();
});
