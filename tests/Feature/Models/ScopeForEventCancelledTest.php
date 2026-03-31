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

it('forEvent includes volunteers with cancelled signups', function () {
    $volunteer = Volunteer::factory()->for($this->project)->create();
    $job = VolunteerJob::factory()->for($this->event)->create();
    $shift = Shift::factory()->for($job, 'volunteerJob')->create();
    ShiftSignup::factory()->create([
        'volunteer_id' => $volunteer->id,
        'shift_id' => $shift->id,
        'cancelled_at' => now(),
    ]);

    $result = Volunteer::forEvent($this->event->id)->get();

    expect($result)->toHaveCount(1)
        ->and($result->first()->id)->toBe($volunteer->id);
});

it('forEvent includes volunteer with only an arrival and no signups', function () {
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

it('forEvent includes volunteer with cancelled signup AND arrival without duplicates', function () {
    $volunteer = Volunteer::factory()->for($this->project)->create();
    $job = VolunteerJob::factory()->for($this->event)->create();
    $shift = Shift::factory()->for($job, 'volunteerJob')->create();
    ShiftSignup::factory()->create([
        'volunteer_id' => $volunteer->id,
        'shift_id' => $shift->id,
        'cancelled_at' => now(),
    ]);

    $ticket = Ticket::factory()->for($volunteer)->for($this->project, 'project')->create();
    EventArrival::factory()->create([
        'volunteer_id' => $volunteer->id,
        'event_id' => $this->event->id,
        'ticket_id' => $ticket->id,
    ]);

    $result = Volunteer::forEvent($this->event->id)->get();

    expect($result)->toHaveCount(1);
});

it('forEvent excludes volunteer with no signups and no arrivals', function () {
    Volunteer::factory()->for($this->project)->create();

    $result = Volunteer::forEvent($this->event->id)->get();

    expect($result)->toHaveCount(0);
});

it('forEvent includes volunteers from multiple events independently', function () {
    $event2 = Event::factory()->for($this->org)->for($this->project)->create();

    $v1 = Volunteer::factory()->for($this->project)->create();
    $v2 = Volunteer::factory()->for($this->project)->create();

    $job1 = VolunteerJob::factory()->for($this->event)->create();
    $shift1 = Shift::factory()->for($job1, 'volunteerJob')->create();
    ShiftSignup::factory()->create(['volunteer_id' => $v1->id, 'shift_id' => $shift1->id]);

    $job2 = VolunteerJob::factory()->for($event2)->create();
    $shift2 = Shift::factory()->for($job2, 'volunteerJob')->create();
    ShiftSignup::factory()->create(['volunteer_id' => $v2->id, 'shift_id' => $shift2->id]);

    expect(Volunteer::forEvent($this->event->id)->get())->toHaveCount(1)
        ->and(Volunteer::forEvent($this->event->id)->first()->id)->toBe($v1->id)
        ->and(Volunteer::forEvent($event2->id)->get())->toHaveCount(1)
        ->and(Volunteer::forEvent($event2->id)->first()->id)->toBe($v2->id);
});
