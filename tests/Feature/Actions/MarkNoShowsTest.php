<?php

use App\Actions\MarkNoShows;
use App\Enums\AttendanceStatus;
use App\Models\AttendanceRecord;
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
    $this->volunteer = Volunteer::factory()->create();
});

it('marks signups as no-show for ended shifts', function () {
    $shift = Shift::factory()->for($this->job, 'volunteerJob')->create([
        'starts_at' => now()->subHours(5),
        'ends_at' => now()->subHours(3),
    ]);
    $signup = ShiftSignup::factory()->create([
        'volunteer_id' => $this->volunteer->id,
        'shift_id' => $shift->id,
    ]);

    $count = app(MarkNoShows::class)->execute();

    expect($count)->toBe(1);

    $record = AttendanceRecord::where('shift_signup_id', $signup->id)->first();
    expect($record)->not->toBeNull()
        ->and($record->status)->toBe(AttendanceStatus::NoShow)
        ->and($record->recorded_by)->toBeNull();
});

it('skips signups with existing attendance records', function () {
    $shift = Shift::factory()->for($this->job, 'volunteerJob')->create([
        'starts_at' => now()->subHours(5),
        'ends_at' => now()->subHours(3),
    ]);
    $signup = ShiftSignup::factory()->create([
        'volunteer_id' => $this->volunteer->id,
        'shift_id' => $shift->id,
    ]);

    AttendanceRecord::factory()->create([
        'shift_signup_id' => $signup->id,
        'status' => AttendanceStatus::OnTime,
    ]);

    $count = app(MarkNoShows::class)->execute();

    expect($count)->toBe(0);
});

it('skips future shifts', function () {
    $shift = Shift::factory()->for($this->job, 'volunteerJob')->create([
        'starts_at' => now()->addHours(1),
        'ends_at' => now()->addHours(3),
    ]);
    ShiftSignup::factory()->create([
        'volunteer_id' => $this->volunteer->id,
        'shift_id' => $shift->id,
    ]);

    $count = app(MarkNoShows::class)->execute();

    expect($count)->toBe(0);
});

it('skips cancelled signups', function () {
    $shift = Shift::factory()->for($this->job, 'volunteerJob')->create([
        'starts_at' => now()->subHours(5),
        'ends_at' => now()->subHours(3),
    ]);
    ShiftSignup::factory()->create([
        'volunteer_id' => $this->volunteer->id,
        'shift_id' => $shift->id,
        'cancelled_at' => now()->subDay(),
    ]);

    $count = app(MarkNoShows::class)->execute();

    expect($count)->toBe(0);
});

it('respects two-hour buffer', function () {
    $shift = Shift::factory()->for($this->job, 'volunteerJob')->create([
        'starts_at' => now()->subHours(2),
        'ends_at' => now()->subMinutes(90),
    ]);
    ShiftSignup::factory()->create([
        'volunteer_id' => $this->volunteer->id,
        'shift_id' => $shift->id,
    ]);

    $count = app(MarkNoShows::class)->execute();

    expect($count)->toBe(0);
});
