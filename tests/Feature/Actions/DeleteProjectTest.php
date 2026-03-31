<?php

use App\Actions\DeleteProject;
use App\Models\Event;
use App\Models\Organization;
use App\Models\Project;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->org = Organization::factory()->create();
    $this->project = Project::factory()->for($this->org)->create();
});

it('deletes the project record', function () {
    $action = new DeleteProject;

    $action->execute($this->project);

    expect(Project::find($this->project->id))->toBeNull();
});

it('deletes the stored image', function () {
    Storage::fake('public');
    $image = UploadedFile::fake()->image('banner.jpg');
    $path = $image->store('projects/1', 'public');
    $this->project->update(['title_image_path' => $path]);

    $action = new DeleteProject;
    $action->execute($this->project);

    Storage::disk('public')->assertMissing($path);
});

it('cascade-deletes member events', function () {
    $event = Event::factory()->for($this->org)->create(['project_id' => $this->project->id]);

    $action = new DeleteProject;
    $action->execute($this->project);

    expect(Event::find($event->id))->toBeNull();
});

it('handles project with no image', function () {
    $this->project->update(['title_image_path' => null]);

    $action = new DeleteProject;
    $action->execute($this->project);

    expect(Project::find($this->project->id))->toBeNull();
});
