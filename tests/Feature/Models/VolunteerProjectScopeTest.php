<?php

use App\Models\Event;
use App\Models\EventArrival;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Shift;
use App\Models\ShiftSignup;
use App\Models\Ticket;
use App\Models\Volunteer;
use App\Models\VolunteerJob;

beforeEach(function () {
    $this->org = Organization::factory()->create();
    $this->project = Project::factory()->for($this->org)->create();
    $this->event = Event::factory()->for($this->org)->for($this->project)->create();
});

it('forProject scopes volunteers by project_id', function () {
    $inProject = Volunteer::factory()->for($this->project)->create();
    $otherProject = Project::factory()->for($this->org)->create();
    $outOfProject = Volunteer::factory()->for($otherProject)->create();

    $result = Volunteer::forProject($this->project->id)->get();

    expect($result)->toHaveCount(1)
        ->and($result->first()->id)->toBe($inProject->id);
});

it('forEvent finds volunteers via shift signups', function () {
    $volunteer = Volunteer::factory()->for($this->project)->create();
    $job = VolunteerJob::factory()->for($this->event)->create();
    $shift = Shift::factory()->for($job, 'volunteerJob')->create();
    ShiftSignup::factory()->create(['volunteer_id' => $volunteer->id, 'shift_id' => $shift->id]);

    $result = Volunteer::forEvent($this->event->id)->get();

    expect($result)->toHaveCount(1)
        ->and($result->first()->id)->toBe($volunteer->id);
});

it('forEvent finds volunteers via event arrivals', function () {
    $volunteer = Volunteer::factory()->for($this->project)->create();
    $ticket = Ticket::factory()->for($volunteer)->for($this->project, 'project')->create();
    EventArrival::factory()->create([
        'volunteer_id' => $volunteer->id,
        'event_id' => $this->event->id,
        'ticket_id' => $ticket->id,
    ]);

    $result = Volunteer::forEvent($this->event->id)->get();

    expect($result)->toHaveCount(1)
        ->and($result->first()->id)->toBe($volunteer->id);
});

it('forEvent does not duplicate volunteers with both signups and arrivals', function () {
    $volunteer = Volunteer::factory()->for($this->project)->create();
    $job = VolunteerJob::factory()->for($this->event)->create();
    $shift = Shift::factory()->for($job, 'volunteerJob')->create();
    ShiftSignup::factory()->create(['volunteer_id' => $volunteer->id, 'shift_id' => $shift->id]);

    $ticket = Ticket::factory()->for($volunteer)->for($this->project, 'project')->create();
    EventArrival::factory()->create([
        'volunteer_id' => $volunteer->id,
        'event_id' => $this->event->id,
        'ticket_id' => $ticket->id,
    ]);

    $result = Volunteer::forEvent($this->event->id)->get();

    expect($result)->toHaveCount(1);
});

it('forEvent excludes volunteers from other events', function () {
    $otherEvent = Event::factory()->for($this->org)->for($this->project)->create();
    $volunteer = Volunteer::factory()->for($this->project)->create();
    $job = VolunteerJob::factory()->for($otherEvent)->create();
    $shift = Shift::factory()->for($job, 'volunteerJob')->create();
    ShiftSignup::factory()->create(['volunteer_id' => $volunteer->id, 'shift_id' => $shift->id]);

    $result = Volunteer::forEvent($this->event->id)->get();

    expect($result)->toHaveCount(0);
});

it('volunteer belongs to project', function () {
    $volunteer = Volunteer::factory()->for($this->project)->create();

    expect($volunteer->project->id)->toBe($this->project->id);
});

it('volunteer requires project_id', function () {
    $volunteer = Volunteer::factory()->for($this->project)->create();

    expect($volunteer->project_id)->toBe($this->project->id)
        ->and($volunteer->project_id)->not->toBeNull();
});
