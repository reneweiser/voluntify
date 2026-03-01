<?php

use App\Actions\DeleteShift;
use App\Exceptions\HasSignupsException;
use App\Models\Event;
use App\Models\Organization;
use App\Models\Shift;
use App\Models\ShiftSignup;
use App\Models\Volunteer;
use App\Models\VolunteerJob;

beforeEach(function () {
    $this->org = Organization::factory()->create();
    $this->event = Event::factory()->for($this->org)->create();
    $this->job = VolunteerJob::factory()->for($this->event)->create();
    $this->action = new DeleteShift;
});

it('deletes a shift with no signups', function () {
    $shift = Shift::factory()->for($this->job, 'volunteerJob')->create();

    $this->action->execute($shift);

    expect(Shift::find($shift->id))->toBeNull();
});

it('throws exception if shift has signups', function () {
    $shift = Shift::factory()->for($this->job, 'volunteerJob')->create();
    $volunteer = Volunteer::factory()->create();
    ShiftSignup::factory()->create(['shift_id' => $shift->id, 'volunteer_id' => $volunteer->id]);

    expect(fn () => $this->action->execute($shift))
        ->toThrow(HasSignupsException::class);
});
