<?php

use App\Actions\DeleteProject;
use App\Events\Activity\ProjectDeleted as ProjectDeletedActivity;
use App\Models\Event;
use App\Models\Organization;
use App\Models\Project;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event as EventFacade;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->org = Organization::factory()->create();
    $this->project = Project::factory()->for($this->org)->create();
    $this->user = User::factory()->create();
});

it('deletes the project record', function () {
    $action = new DeleteProject;

    $action->execute($this->project, $this->user);

    expect(Project::find($this->project->id))->toBeNull();
});

it('deletes the stored image', function () {
    Storage::fake('public');
    $image = UploadedFile::fake()->image('banner.jpg');
    $path = $image->store('projects/1', 'public');
    $this->project->update(['title_image_path' => $path]);

    $action = new DeleteProject;
    $action->execute($this->project, $this->user);

    Storage::disk('public')->assertMissing($path);
});

it('cascade-deletes member events', function () {
    $event = Event::factory()->for($this->org)->create(['project_id' => $this->project->id]);

    $action = new DeleteProject;
    $action->execute($this->project, $this->user);

    expect(Event::find($event->id))->toBeNull();
});

it('handles project with no image', function () {
    $this->project->update(['title_image_path' => null]);

    $action = new DeleteProject;
    $action->execute($this->project, $this->user);

    expect(Project::find($this->project->id))->toBeNull();
});

it('dispatches ProjectDeletedActivity event with causer', function () {
    EventFacade::fake([ProjectDeletedActivity::class]);

    $action = new DeleteProject;
    $action->execute($this->project, $this->user);

    EventFacade::assertDispatched(ProjectDeletedActivity::class, fn ($e) => $e->causer->id === $this->user->id);
});
