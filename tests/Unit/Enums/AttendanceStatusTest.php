<?php

use App\Enums\AttendanceStatus;

it('has exactly three cases', function () {
    expect(AttendanceStatus::cases())->toHaveCount(3);
});

it('has the correct backing values', function (AttendanceStatus $case, string $value) {
    expect($case->value)->toBe($value);
})->with([
    [AttendanceStatus::OnTime, 'on_time'],
    [AttendanceStatus::Late, 'late'],
    [AttendanceStatus::NoShow, 'no_show'],
]);
