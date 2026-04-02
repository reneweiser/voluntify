<?php

use App\Actions\UpdateProject;
use App\Events\Activity\ProjectUpdated as ProjectUpdatedActivity;
use App\Models\Organization;
use App\Models\Project;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
    $this->org = Organization::factory()->create();
    $this->user = User::factory()->create();
    $this->image = UploadedFile::fake()->image('original.jpg', 800, 600);
    $this->imagePath = $this->image->store('projects/1', 'public');
    $this->project = Project::factory()->for($this->org)->create([
        'title_image_path' => $this->imagePath,
    ]);
});

it('updates name and description', function () {
    $action = new UpdateProject;

    $project = $action->execute(
        project: $this->project,
        name: 'Updated Name',
        description: 'Updated description',
        causer: $this->user,
    );

    expect($project->name)->toBe('Updated Name')
        ->and($project->description)->toBe('Updated description');
});

it('replaces image and deletes old one', function () {
    $action = new UpdateProject;
    $newImage = UploadedFile::fake()->image('new.jpg', 1200, 400);

    $project = $action->execute(
        project: $this->project,
        name: $this->project->name,
        titleImage: $newImage,
        causer: $this->user,
    );

    Storage::disk('public')->assertMissing($this->imagePath);
    Storage::disk('public')->assertExists($project->title_image_path);
    expect($project->title_image_path)->not->toBe($this->imagePath);
});

it('removes image when removeTitleImage is true', function () {
    $action = new UpdateProject;

    $project = $action->execute(
        project: $this->project,
        name: $this->project->name,
        removeTitleImage: true,
        causer: $this->user,
    );

    Storage::disk('public')->assertMissing($this->imagePath);
    expect($project->title_image_path)->toBeNull();
});

it('keeps existing image when no new image and removeTitleImage is false', function () {
    $action = new UpdateProject;

    $project = $action->execute(
        project: $this->project,
        name: 'New Name',
        causer: $this->user,
    );

    Storage::disk('public')->assertExists($this->imagePath);
    expect($project->title_image_path)->toBe($this->imagePath);
});

it('updates sender_name and contact_email', function () {
    $action = new UpdateProject;

    $project = $action->execute(
        project: $this->project,
        name: $this->project->name,
        senderName: 'Festival Team',
        contactEmail: 'info@festival.de',
        causer: $this->user,
    );

    expect($project->sender_name)->toBe('Festival Team')
        ->and($project->contact_email)->toBe('info@festival.de');
});

it('allows sender_name and contact_email to be nullable', function () {
    $action = new UpdateProject;

    $this->project->update(['sender_name' => 'Old Name', 'contact_email' => 'old@test.de']);

    $project = $action->execute(
        project: $this->project,
        name: $this->project->name,
        senderName: null,
        contactEmail: null,
        causer: $this->user,
    );

    expect($project->sender_name)->toBeNull()
        ->and($project->contact_email)->toBeNull();
});

it('dispatches ProjectUpdatedActivity event with causer', function () {
    Event::fake([ProjectUpdatedActivity::class]);

    $action = new UpdateProject;

    $action->execute(
        project: $this->project,
        name: 'Changed Name',
        causer: $this->user,
    );

    Event::assertDispatched(ProjectUpdatedActivity::class, fn ($e) => $e->causer->id === $this->user->id);
});
