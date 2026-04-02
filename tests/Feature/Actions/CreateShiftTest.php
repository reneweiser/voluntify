<?php

use App\Actions\CreateShift;
use App\Events\Activity\ShiftCreated;
use App\Models\Event;
use App\Models\Organization;
use App\Models\User;
use App\Models\VolunteerJob;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event as EventFacade;

beforeEach(function () {
    $this->org = Organization::factory()->create();
    $this->event = Event::factory()->for($this->org)->create();
    $this->job = VolunteerJob::factory()->for($this->event)->create();
    $this->user = User::factory()->create();
    $this->action = new CreateShift;
});

it('creates a shift with date and times', function () {
    $shift = $this->action->execute(
        job: $this->job,
        shiftDate: Carbon::parse('2026-07-01'),
        startsAt: Carbon::parse('2026-07-01 09:00'),
        endsAt: Carbon::parse('2026-07-01 13:00'),
        capacity: 10,
        causer: $this->user,
    );

    expect($shift->exists)->toBeTrue()
        ->and($shift->volunteer_job_id)->toBe($this->job->id)
        ->and($shift->capacity)->toBe(10)
        ->and($shift->shift_date->format('Y-m-d'))->toBe('2026-07-01')
        ->and($shift->starts_at->format('H:i'))->toBe('09:00')
        ->and($shift->ends_at->format('H:i'))->toBe('13:00')
        ->and($shift->display_text)->toBeNull();
});

it('creates a shift with date only and no times', function () {
    $shift = $this->action->execute(
        job: $this->job,
        shiftDate: Carbon::parse('2026-07-01'),
        startsAt: null,
        endsAt: null,
        capacity: 5,
        displayText: 'Ganzer Tag',
        causer: $this->user,
    );

    expect($shift->exists)->toBeTrue()
        ->and($shift->shift_date->format('Y-m-d'))->toBe('2026-07-01')
        ->and($shift->starts_at)->toBeNull()
        ->and($shift->ends_at)->toBeNull()
        ->and($shift->display_text)->toBe('Ganzer Tag')
        ->and($shift->capacity)->toBe(5);
});

it('creates a shift with custom display text overriding time display', function () {
    $shift = $this->action->execute(
        job: $this->job,
        shiftDate: Carbon::parse('2026-07-01'),
        startsAt: Carbon::parse('2026-07-01 09:00'),
        endsAt: Carbon::parse('2026-07-01 13:00'),
        capacity: 10,
        displayText: 'Vormittag',
        causer: $this->user,
    );

    expect($shift->display_text)->toBe('Vormittag')
        ->and($shift->displayTimeRange())->toBe('Vormittag');
});

it('dispatches ShiftCreated activity event with causer', function () {
    EventFacade::fake([ShiftCreated::class]);

    $this->action->execute(
        job: $this->job,
        shiftDate: Carbon::parse('2026-07-01'),
        startsAt: Carbon::parse('2026-07-01 09:00'),
        endsAt: Carbon::parse('2026-07-01 13:00'),
        capacity: 10,
        causer: $this->user,
    );

    EventFacade::assertDispatched(ShiftCreated::class, fn ($e) => $e->causer->id === $this->user->id);
});
