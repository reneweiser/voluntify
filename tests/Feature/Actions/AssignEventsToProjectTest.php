<?php

use App\Actions\AssignEventsToProject;
use App\Exceptions\DomainException;
use App\Models\Event;
use App\Models\Organization;
use App\Models\Project;

beforeEach(function () {
    $this->org = Organization::factory()->create();
    $this->project = Project::factory()->for($this->org)->create();
});

it('assigns events to the project', function () {
    $events = Event::factory()->for($this->org)->count(2)->create();

    $action = new AssignEventsToProject;
    $action->execute($this->project, $events->pluck('id')->all());

    expect($events[0]->fresh()->project_id)->toBe($this->project->id)
        ->and($events[1]->fresh()->project_id)->toBe($this->project->id);
});

it('is additive — does not remove existing project members', function () {
    $existing = Event::factory()->for($this->org)->create(['project_id' => $this->project->id]);
    $newEvent = Event::factory()->for($this->org)->create();

    $action = new AssignEventsToProject;
    $action->execute($this->project, [$newEvent->id]);

    expect($existing->fresh()->project_id)->toBe($this->project->id)
        ->and($newEvent->fresh()->project_id)->toBe($this->project->id);
});

it('reassigns event from another project silently', function () {
    $otherProject = Project::factory()->for($this->org)->create();
    $event = Event::factory()->for($this->org)->create(['project_id' => $otherProject->id]);

    $action = new AssignEventsToProject;
    $action->execute($this->project, [$event->id]);

    expect($event->fresh()->project_id)->toBe($this->project->id);
});

it('throws DomainException for cross-org events', function () {
    $otherOrg = Organization::factory()->create();
    $event = Event::factory()->for($otherOrg)->create();

    $action = new AssignEventsToProject;

    expect(fn () => $action->execute($this->project, [$event->id]))
        ->toThrow(DomainException::class);
});
