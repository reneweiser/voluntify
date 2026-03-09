<?php

use App\Actions\CreateEventGroup;
use App\Models\Organization;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->org = Organization::factory()->create();
});

it('creates an event group for the organization', function () {
    $action = new CreateEventGroup;

    $group = $action->execute(
        organization: $this->org,
        name: 'SKHC Festival',
        description: 'A multi-part festival',
    );

    expect($group->exists)->toBeTrue()
        ->and($group->organization_id)->toBe($this->org->id)
        ->and($group->name)->toBe('SKHC Festival')
        ->and($group->description)->toBe('A multi-part festival');
});

it('auto-generates a 32-char public_token', function () {
    $action = new CreateEventGroup;

    $group = $action->execute(
        organization: $this->org,
        name: 'Token Group',
    );

    expect($group->public_token)->toBeString()
        ->and(strlen($group->public_token))->toBe(32);
});

it('allows nullable description', function () {
    $action = new CreateEventGroup;

    $group = $action->execute(
        organization: $this->org,
        name: 'No Desc Group',
    );

    expect($group->description)->toBeNull();
});

it('stores title image when provided', function () {
    Storage::fake('public');

    $action = new CreateEventGroup;
    $image = UploadedFile::fake()->image('banner.jpg', 1200, 400);

    $group = $action->execute(
        organization: $this->org,
        name: 'Image Group',
        titleImage: $image,
    );

    expect($group->title_image_path)->not->toBeNull();
    Storage::disk('public')->assertExists($group->title_image_path);
});

it('creates group without image', function () {
    $action = new CreateEventGroup;

    $group = $action->execute(
        organization: $this->org,
        name: 'No Image Group',
    );

    expect($group->title_image_path)->toBeNull();
});
