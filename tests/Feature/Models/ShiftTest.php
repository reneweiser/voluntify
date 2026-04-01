<?php

use App\Enums\AttendanceStatus;
use App\Models\Shift;
use Illuminate\Support\Carbon;

it('hasDefinedTimes returns true when starts_at is set', function () {
    $shift = Shift::factory()->create();

    expect($shift->hasDefinedTimes())->toBeTrue();
});

it('hasDefinedTimes returns false when starts_at is null', function () {
    $shift = Shift::factory()->withoutTimes('Ganzer Tag')->create();

    expect($shift->hasDefinedTimes())->toBeFalse();
});

it('displayTimeRange returns custom text when display_text is set', function () {
    $shift = Shift::factory()->withoutTimes('Flexibel')->create();

    expect($shift->displayTimeRange())->toBe('Flexibel');
});

it('displayTimeRange returns formatted time range when times are set', function () {
    $shift = Shift::factory()->create([
        'starts_at' => Carbon::parse('2026-07-01 09:00'),
        'ends_at' => Carbon::parse('2026-07-01 13:00'),
    ]);

    expect($shift->displayTimeRange())->toBe('09:00 – 13:00');
});

it('displayTimeRange returns display_text even when times are set', function () {
    $shift = Shift::factory()->create([
        'starts_at' => Carbon::parse('2026-07-01 09:00'),
        'ends_at' => Carbon::parse('2026-07-01 13:00'),
        'display_text' => 'Vormittag',
    ]);

    expect($shift->displayTimeRange())->toBe('Vormittag');
});

it('displayTimeRange returns empty string when no times and no display_text', function () {
    $shift = Shift::factory()->withoutTimes()->create();

    expect($shift->displayTimeRange())->toBe('');
});

it('attendanceStatusAt returns OnTime when no defined times', function () {
    $shift = Shift::factory()->withoutTimes('Ganzer Tag')->create();

    $status = $shift->attendanceStatusAt(Carbon::now());

    expect($status)->toBe(AttendanceStatus::OnTime);
});

it('attendanceStatusAt returns Late when scanned after deadline with times', function () {
    $shift = Shift::factory()->create([
        'starts_at' => Carbon::parse('2026-07-01 09:00'),
        'ends_at' => Carbon::parse('2026-07-01 13:00'),
    ]);

    $status = $shift->attendanceStatusAt(Carbon::parse('2026-07-01 09:30'));

    expect($status)->toBe(AttendanceStatus::Late);
});

it('attendanceStatusAt returns OnTime when scanned before start with times', function () {
    $shift = Shift::factory()->create([
        'starts_at' => Carbon::parse('2026-07-01 09:00'),
        'ends_at' => Carbon::parse('2026-07-01 13:00'),
    ]);

    $status = $shift->attendanceStatusAt(Carbon::parse('2026-07-01 08:30'));

    expect($status)->toBe(AttendanceStatus::OnTime);
});
