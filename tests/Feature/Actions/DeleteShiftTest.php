<?php

use App\Actions\DeleteShift;
use App\Events\Activity\ShiftDeleted;
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
    $this->job = VolunteerJob::factory()->for($this->event)->create();
    $this->user = User::factory()->create();
    $this->action = new DeleteShift;
});

it('deletes a shift with no signups', function () {
    $shift = Shift::factory()->for($this->job, 'volunteerJob')->create();

    $this->action->execute($shift, $this->user);

    expect(Shift::find($shift->id))->toBeNull();
});

it('throws exception if shift has signups', function () {
    $shift = Shift::factory()->for($this->job, 'volunteerJob')->create();
    $volunteer = Volunteer::factory()->create();
    ShiftSignup::factory()->create(['shift_id' => $shift->id, 'volunteer_id' => $volunteer->id]);

    expect(fn () => $this->action->execute($shift, $this->user))
        ->toThrow(HasSignupsException::class);
});

it('dispatches ShiftDeleted activity event with causer', function () {
    EventFacade::fake([ShiftDeleted::class]);

    $shift = Shift::factory()->for($this->job, 'volunteerJob')->create();

    $this->action->execute($shift, $this->user);

    EventFacade::assertDispatched(ShiftDeleted::class, fn ($e) => $e->causer->id === $this->user->id);
});
