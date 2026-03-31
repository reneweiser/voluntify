<?php

use App\Models\Event;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Shift;
use App\Models\ShiftSignup;
use App\Models\Volunteer;
use App\Models\VolunteerJob;

beforeEach(function () {
    $this->org = Organization::factory()->create();
    $this->project = Project::factory()->for($this->org)->create();
});

it('counts volunteers for an event with signups', function () {
    $event = Event::factory()->for($this->org)->for($this->project)->create();
    $job = VolunteerJob::factory()->for($event)->create();
    $shift = Shift::factory()->for($job, 'volunteerJob')->create();

    $v1 = Volunteer::factory()->for($this->project)->create();
    $v2 = Volunteer::factory()->for($this->project)->create();
    ShiftSignup::factory()->create(['volunteer_id' => $v1->id, 'shift_id' => $shift->id]);
    ShiftSignup::factory()->create(['volunteer_id' => $v2->id, 'shift_id' => $shift->id]);

    $result = Event::where('id', $event->id)->withVolunteerCount()->first();

    expect($result->volunteer_count)->toBe(2);
});

it('returns zero volunteer count when event has no signups', function () {
    $event = Event::factory()->for($this->org)->for($this->project)->create();

    $result = Event::where('id', $event->id)->withVolunteerCount()->first();

    expect($result->volunteer_count)->toBe(0);
});

it('does not count volunteers from a different project', function () {
    $event = Event::factory()->for($this->org)->for($this->project)->create();
    $otherProject = Project::factory()->for($this->org)->create();

    // Volunteer in a different project with signup to different event
    $otherEvent = Event::factory()->for($this->org)->for($otherProject)->create();
    $otherJob = VolunteerJob::factory()->for($otherEvent)->create();
    $otherShift = Shift::factory()->for($otherJob, 'volunteerJob')->create();
    $otherVol = Volunteer::factory()->for($otherProject)->create();
    ShiftSignup::factory()->create(['volunteer_id' => $otherVol->id, 'shift_id' => $otherShift->id]);

    $result = Event::where('id', $event->id)->withVolunteerCount()->first();

    expect($result->volunteer_count)->toBe(0);
});

it('counts each volunteer only once even with multiple shift signups', function () {
    $event = Event::factory()->for($this->org)->for($this->project)->create();
    $job = VolunteerJob::factory()->for($event)->create();
    $shift1 = Shift::factory()->for($job, 'volunteerJob')->create();
    $shift2 = Shift::factory()->for($job, 'volunteerJob')->create();

    $volunteer = Volunteer::factory()->for($this->project)->create();
    ShiftSignup::factory()->create(['volunteer_id' => $volunteer->id, 'shift_id' => $shift1->id]);
    ShiftSignup::factory()->create(['volunteer_id' => $volunteer->id, 'shift_id' => $shift2->id]);

    $result = Event::where('id', $event->id)->withVolunteerCount()->first();

    expect($result->volunteer_count)->toBe(1);
});

it('works correctly with multiple events in same query', function () {
    $event1 = Event::factory()->for($this->org)->for($this->project)->create();
    $event2 = Event::factory()->for($this->org)->for($this->project)->create();

    $job1 = VolunteerJob::factory()->for($event1)->create();
    $shift1 = Shift::factory()->for($job1, 'volunteerJob')->create();
    $v1 = Volunteer::factory()->for($this->project)->create();
    ShiftSignup::factory()->create(['volunteer_id' => $v1->id, 'shift_id' => $shift1->id]);

    $job2 = VolunteerJob::factory()->for($event2)->create();
    $shift2 = Shift::factory()->for($job2, 'volunteerJob')->create();
    $v2 = Volunteer::factory()->for($this->project)->create();
    $v3 = Volunteer::factory()->for($this->project)->create();
    ShiftSignup::factory()->create(['volunteer_id' => $v2->id, 'shift_id' => $shift2->id]);
    ShiftSignup::factory()->create(['volunteer_id' => $v3->id, 'shift_id' => $shift2->id]);

    $results = Event::whereIn('id', [$event1->id, $event2->id])
        ->withVolunteerCount()
        ->get()
        ->keyBy('id');

    expect($results[$event1->id]->volunteer_count)->toBe(1)
        ->and($results[$event2->id]->volunteer_count)->toBe(2);
});
