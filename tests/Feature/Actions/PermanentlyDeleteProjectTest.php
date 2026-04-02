<?php

use App\Actions\PermanentlyDeleteProject;
use App\Enums\StaffRole;
use App\Events\Activity\ProjectDeleted as ProjectDeletedActivity;
use App\Models\Event;
use App\Models\Organization;
use App\Models\Project;
use Illuminate\Support\Facades\Event as EventFacade;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
    $this->org = Organization::factory()->create();
    $this->action = new PermanentlyDeleteProject;
});

it('permanently deletes the project from the database', function () {
    $project = Project::factory()->for($this->org)->create();
    $projectId = $project->id;

    $this->action->execute($project);

    expect(Project::find($projectId))->toBeNull();
});

it('deletes the title image from storage when present', function () {
    Storage::disk('public')->put('projects/1/banner.jpg', 'fake-image-content');
    $project = Project::factory()->for($this->org)->create([
        'title_image_path' => 'projects/1/banner.jpg',
    ]);

    $this->action->execute($project);

    Storage::disk('public')->assertMissing('projects/1/banner.jpg');
});

it('succeeds when project has no title image', function () {
    $project = Project::factory()->for($this->org)->create([
        'title_image_path' => null,
    ]);
    $projectId = $project->id;

    $this->action->execute($project);

    expect(Project::find($projectId))->toBeNull();
});

it('cascades deletion to related events', function () {
    $project = Project::factory()->for($this->org)->create();
    $event = Event::factory()->for($this->org)->for($project)->create();

    $this->action->execute($project);

    expect(Event::find($event->id))->toBeNull();
});

it('dispatches activity event with correct data when authenticated', function () {
    EventFacade::fake([ProjectDeletedActivity::class]);

    ['user' => $user] = createUserWithOrganization(StaffRole::Organizer);
    $this->actingAs($user);

    $project = Project::factory()->for($this->org)->create(['name' => 'My Project']);
    $event = Event::factory()->for($this->org)->for($project)->create(['name' => 'Orphaned Event']);

    $this->action->execute($project);

    EventFacade::assertDispatched(ProjectDeletedActivity::class, function ($e) use ($user) {
        return $e->projectName === 'My Project'
            && $e->organizationId === $this->org->id
            && $e->orphanedEventNames === ['Orphaned Event']
            && $e->causer->is($user);
    });
});

it('skips activity event when not authenticated', function () {
    EventFacade::fake([ProjectDeletedActivity::class]);

    $project = Project::factory()->for($this->org)->create();

    $this->action->execute($project);

    EventFacade::assertNotDispatched(ProjectDeletedActivity::class);
});

it('captures orphaned event names before deletion', function () {
    EventFacade::fake([ProjectDeletedActivity::class]);

    ['user' => $user] = createUserWithOrganization(StaffRole::Organizer);
    $this->actingAs($user);

    $project = Project::factory()->for($this->org)->create();
    Event::factory()->for($this->org)->for($project)->create(['name' => 'Event A']);
    Event::factory()->for($this->org)->for($project)->create(['name' => 'Event B']);

    $this->action->execute($project);

    EventFacade::assertDispatched(ProjectDeletedActivity::class, function ($e) {
        return count($e->orphanedEventNames) === 2
            && in_array('Event A', $e->orphanedEventNames)
            && in_array('Event B', $e->orphanedEventNames);
    });
});
