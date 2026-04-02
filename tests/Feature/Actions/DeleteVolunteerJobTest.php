<?php

use App\Actions\DeleteVolunteerJob;
use App\Events\Activity\JobDeleted;
use App\Exceptions\HasSignupsException;
use App\Models\Event;
use App\Models\Organization;
use App\Models\Shift;
use App\Models\ShiftSignup;
use App\Models\User;
use App\Models\Volunteer;
use App\Models\VolunteerJob;
use Illuminate\Support\Facades\Event as EventFacade;

beforeEach(function () {
    $this->org = Organization::factory()->create();
    $this->event = Event::factory()->for($this->org)->create();
    $this->user = User::factory()->create();
    $this->action = new DeleteVolunteerJob;
});

it('deletes a job and its shifts', function () {
    $job = VolunteerJob::factory()->for($this->event)->create();
    Shift::factory()->for($job, 'volunteerJob')->count(2)->create();

    $this->action->execute($job, $this->user);

    expect(VolunteerJob::find($job->id))->toBeNull()
        ->and(Shift::where('volunteer_job_id', $job->id)->count())->toBe(0);
});

it('throws exception if job has signups', function () {
    $job = VolunteerJob::factory()->for($this->event)->create();
    $shift = Shift::factory()->for($job, 'volunteerJob')->create();
    $volunteer = Volunteer::factory()->create();
    ShiftSignup::factory()->create(['shift_id' => $shift->id, 'volunteer_id' => $volunteer->id]);

    expect(fn () => $this->action->execute($job, $this->user))
        ->toThrow(HasSignupsException::class);
});

it('dispatches JobDeleted activity event with causer', function () {
    EventFacade::fake([JobDeleted::class]);

    $job = VolunteerJob::factory()->for($this->event)->create();
    Shift::factory()->for($job, 'volunteerJob')->create();

    $this->action->execute($job, $this->user);

    EventFacade::assertDispatched(JobDeleted::class, fn ($e) => $e->causer->id === $this->user->id);
});
