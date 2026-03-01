<?php

use App\Enums\StaffRole;

it('has exactly three cases', function () {
    expect(StaffRole::cases())->toHaveCount(3);
});

it('has the correct backing values', function (StaffRole $case, string $value) {
    expect($case->value)->toBe($value);
})->with([
    [StaffRole::Organizer, 'organizer'],
    [StaffRole::VolunteerAdmin, 'volunteer_admin'],
    [StaffRole::EntranceStaff, 'entrance_staff'],
]);
