<?php

use App\Enums\AttendanceStatus;

it('has all five expected cases', function () {
    expect(AttendanceStatus::cases())->toHaveCount(5);
});

it('backs each case with correct string value', function () {
    expect(AttendanceStatus::OnTime->value)->toBe('on_time')
        ->and(AttendanceStatus::Late->value)->toBe('late')
        ->and(AttendanceStatus::NoShow->value)->toBe('no_show')
        ->and(AttendanceStatus::EnRoute->value)->toBe('en_route')
        ->and(AttendanceStatus::Excused->value)->toBe('excused');
});
