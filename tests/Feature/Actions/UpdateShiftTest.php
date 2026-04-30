<?php

use App\Actions\UpdateShift;
use App\Events\Activity\ShiftUpdated;
use App\Exceptions\DomainException;
use App\Models\Event;
use App\Models\Organization;
use App\Models\Shift;
use App\Models\ShiftSignup;
use App\Models\User;
use App\Models\Volunteer;
use App\Models\VolunteerJob;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event as EventFacade;

beforeEach(function () {
    $this->org = Organization::factory()->create();
    $this->event = Event::factory()->for($this->org)->create();
    $this->job = VolunteerJob::factory()->for($this->event)->create();
    $this->user = User::factory()->create();
    $this->action = new UpdateShift;
});

it('updates shift times and capacity', function () {
    $shift = Shift::factory()->for($this->job, 'volunteerJob')->create();

    $updated = $this->action->execute(
        shift: $shift,
        shiftDate: Carbon::parse('2026-07-01'),
        startsAt: Carbon::parse('2026-07-01 14:00'),
        endsAt: Carbon::parse('2026-07-01 18:00'),
        capacity: 20,
        isActive: true,
        causer: $this->user,
    );

    expect($updated->capacity)->toBe(20)
        ->and($updated->shift_date->format('Y-m-d'))->toBe('2026-07-01')
        ->and($updated->starts_at->format('H:i'))->toBe('14:00')
        ->and($updated->ends_at->format('H:i'))->toBe('18:00');
});

it('throws DomainException when reducing capacity below current signups', function () {
    $shift = Shift::factory()->for($this->job, 'volunteerJob')->create(['capacity' => 5]);

    foreach (range(1, 3) as $i) {
        $volunteer = Volunteer::factory()->create();
        ShiftSignup::factory()->create(['shift_id' => $shift->id, 'volunteer_id' => $volunteer->id]);
    }

    expect(fn () => $this->action->execute(
        shift: $shift,
        shiftDate: Carbon::parse('2026-07-01'),
        startsAt: Carbon::parse('2026-07-01 14:00'),
        endsAt: Carbon::parse('2026-07-01 18:00'),
        capacity: 2,
        isActive: true,
        causer: $this->user,
    ))->toThrow(DomainException::class, 'Cannot reduce capacity below current number of signups.');
});

it('allows reducing capacity to exactly current signups', function () {
    $shift = Shift::factory()->for($this->job, 'volunteerJob')->create(['capacity' => 5]);

    foreach (range(1, 3) as $i) {
        $volunteer = Volunteer::factory()->create();
        ShiftSignup::factory()->create(['shift_id' => $shift->id, 'volunteer_id' => $volunteer->id]);
    }

    $updated = $this->action->execute(
        shift: $shift,
        shiftDate: Carbon::parse('2026-07-01'),
        startsAt: Carbon::parse('2026-07-01 14:00'),
        endsAt: Carbon::parse('2026-07-01 18:00'),
        capacity: 3,
        isActive: true,
        causer: $this->user,
    );

    expect($updated->capacity)->toBe(3);
});

it('dispatches ShiftUpdated activity event with causer', function () {
    EventFacade::fake([ShiftUpdated::class]);

    $shift = Shift::factory()->for($this->job, 'volunteerJob')->create(['capacity' => 5]);

    $this->action->execute(
        shift: $shift,
        shiftDate: Carbon::parse('2026-07-01'),
        startsAt: Carbon::parse('2026-07-01 14:00'),
        endsAt: Carbon::parse('2026-07-01 18:00'),
        capacity: 20,
        isActive: true,
        causer: $this->user,
    );

    EventFacade::assertDispatched(ShiftUpdated::class, fn ($e) => $e->causer->id === $this->user->id);
});

it('can deactivate a shift', function () {
    $shift = Shift::factory()->for($this->job, 'volunteerJob')->create(['is_active' => true]);

    $updated = $this->action->execute(
        shift: $shift,
        shiftDate: $shift->shift_date,
        startsAt: $shift->starts_at,
        endsAt: $shift->ends_at,
        capacity: $shift->capacity,
        isActive: false,
        causer: $this->user,
    );

    expect($updated->is_active)->toBeFalse();
});
