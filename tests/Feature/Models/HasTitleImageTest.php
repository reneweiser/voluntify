<?php

use App\Models\Event;
use App\Models\Organization;
use App\Models\Project;
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

it('returns storage URL for Project when title_image_path is set', function () {
    Storage::fake('public');

    $project = Project::factory()->create([
        'title_image_path' => 'projects/1/banner.jpg',
    ]);

    expect($project->titleImageUrl())->toBe(Storage::disk('public')->url('projects/1/banner.jpg'));
});

it('returns null for Project when title_image_path is null', function () {
    $project = Project::factory()->create([
        'title_image_path' => null,
    ]);

    expect($project->titleImageUrl())->toBeNull();
});
