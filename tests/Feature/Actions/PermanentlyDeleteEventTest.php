<?php

use App\Actions\PermanentlyDeleteEvent;
use App\Models\Event;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Shift;
use App\Models\ShiftSignup;
use App\Models\VolunteerJob;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
    $this->org = Organization::factory()->create();
    $this->project = Project::factory()->for($this->org)->create();
    $this->action = new PermanentlyDeleteEvent;
});

it('permanently deletes the event from the database', function () {
    $event = Event::factory()->for($this->org)->for($this->project)->create();
    $eventId = $event->id;

    $this->action->execute($event);

    expect(Event::find($eventId))->toBeNull();
});

it('deletes the title image from storage when present', function () {
    Storage::disk('public')->put('events/1/banner.jpg', 'fake-image-content');
    $event = Event::factory()->for($this->org)->for($this->project)->create([
        'title_image_path' => 'events/1/banner.jpg',
    ]);

    $this->action->execute($event);

    Storage::disk('public')->assertMissing('events/1/banner.jpg');
});

it('succeeds when event has no title image', function () {
    $event = Event::factory()->for($this->org)->for($this->project)->create([
        'title_image_path' => null,
    ]);
    $eventId = $event->id;

    $this->action->execute($event);

    expect(Event::find($eventId))->toBeNull();
});

it('cascades deletion to jobs, shifts, and signups', function () {
    $event = Event::factory()->for($this->org)->for($this->project)->create();
    $job = VolunteerJob::factory()->for($event)->create();
    $shift = Shift::factory()->for($job, 'volunteerJob')->create();
    $signup = ShiftSignup::factory()->create(['shift_id' => $shift->id]);

    $this->action->execute($event);

    expect(VolunteerJob::find($job->id))->toBeNull()
        ->and(Shift::find($shift->id))->toBeNull()
        ->and(ShiftSignup::find($signup->id))->toBeNull();
});
