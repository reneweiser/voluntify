<?php

use App\Actions\CreateProject;
use App\Models\Organization;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->org = Organization::factory()->create();
});

it('creates a project for the organization', function () {
    $action = new CreateProject;

    $project = $action->execute(
        organization: $this->org,
        name: 'SKHC Festival',
        description: 'A multi-part festival',
    );

    expect($project->exists)->toBeTrue()
        ->and($project->organization_id)->toBe($this->org->id)
        ->and($project->name)->toBe('SKHC Festival')
        ->and($project->description)->toBe('A multi-part festival');
});

it('auto-generates a 32-char public_token', function () {
    $action = new CreateProject;

    $project = $action->execute(
        organization: $this->org,
        name: 'Token Project',
    );

    expect($project->public_token)->toBeString()
        ->and(strlen($project->public_token))->toBe(32);
});

it('allows nullable description', function () {
    $action = new CreateProject;

    $project = $action->execute(
        organization: $this->org,
        name: 'No Desc Project',
    );

    expect($project->description)->toBeNull();
});

it('stores title image when provided', function () {
    Storage::fake('public');

    $action = new CreateProject;
    $image = UploadedFile::fake()->image('banner.jpg', 1200, 400);

    $project = $action->execute(
        organization: $this->org,
        name: 'Image Project',
        titleImage: $image,
    );

    expect($project->title_image_path)->not->toBeNull();
    Storage::disk('public')->assertExists($project->title_image_path);
});

it('creates project without image', function () {
    $action = new CreateProject;

    $project = $action->execute(
        organization: $this->org,
        name: 'No Image Project',
    );

    expect($project->title_image_path)->toBeNull();
});
