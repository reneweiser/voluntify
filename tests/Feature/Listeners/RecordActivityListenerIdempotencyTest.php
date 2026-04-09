<?php

use App\Events\Activity\SignupCancelled;
use App\Listeners\RecordActivityListener;
use App\Models\ActivityLog;
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
    $this->event = Event::factory()->for($this->org)->for($this->project)->published()->create();
    $this->job = VolunteerJob::factory()->for($this->event)->create();
    $this->shift = Shift::factory()->for($this->job, 'volunteerJob')->create([
        'starts_at' => now()->addDays(3),
        'ends_at' => now()->addDays(3)->addHours(2),
    ]);
    $this->volunteer = Volunteer::factory()->for($this->project)->verified()->create();
    $this->signup = ShiftSignup::factory()->create([
        'volunteer_id' => $this->volunteer->id,
        'shift_id' => $this->shift->id,
        'cancelled_at' => now(),
    ]);
});

it('creates activity log on first dispatch [#114]', function () {
    $listener = app(RecordActivityListener::class);
    $listener->handleSignupCancelled(new SignupCancelled($this->signup, $this->volunteer));

    expect(ActivityLog::where('action', 'cancelled')->count())->toBe(1);
});

it('prevents duplicate activity log on repeated dispatch [#114]', function () {
    $listener = app(RecordActivityListener::class);
    $event = new SignupCancelled($this->signup, $this->volunteer);

    $listener->handleSignupCancelled($event);
    $listener->handleSignupCancelled($event);

    expect(ActivityLog::where('action', 'cancelled')->count())->toBe(1);
});
