<?php

use App\Actions\UpdateEventGroup;
use App\Models\EventGroup;
use App\Models\Organization;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
    $this->org = Organization::factory()->create();
    $this->image = UploadedFile::fake()->image('original.jpg', 800, 600);
    $this->imagePath = $this->image->store('event-groups/1', 'public');
    $this->group = EventGroup::factory()->for($this->org)->create([
        'title_image_path' => $this->imagePath,
    ]);
});

it('updates name and description', function () {
    $action = new UpdateEventGroup;

    $group = $action->execute(
        eventGroup: $this->group,
        name: 'Updated Name',
        description: 'Updated description',
    );

    expect($group->name)->toBe('Updated Name')
        ->and($group->description)->toBe('Updated description');
});

it('replaces image and deletes old one', function () {
    $action = new UpdateEventGroup;
    $newImage = UploadedFile::fake()->image('new.jpg', 1200, 400);

    $group = $action->execute(
        eventGroup: $this->group,
        name: $this->group->name,
        titleImage: $newImage,
    );

    Storage::disk('public')->assertMissing($this->imagePath);
    Storage::disk('public')->assertExists($group->title_image_path);
    expect($group->title_image_path)->not->toBe($this->imagePath);
});

it('removes image when removeTitleImage is true', function () {
    $action = new UpdateEventGroup;

    $group = $action->execute(
        eventGroup: $this->group,
        name: $this->group->name,
        removeTitleImage: true,
    );

    Storage::disk('public')->assertMissing($this->imagePath);
    expect($group->title_image_path)->toBeNull();
});

it('keeps existing image when no new image and removeTitleImage is false', function () {
    $action = new UpdateEventGroup;

    $group = $action->execute(
        eventGroup: $this->group,
        name: 'New Name',
    );

    Storage::disk('public')->assertExists($this->imagePath);
    expect($group->title_image_path)->toBe($this->imagePath);
});
