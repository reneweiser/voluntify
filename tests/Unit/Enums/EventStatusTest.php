<?php

use App\Enums\EventStatus;

it('has exactly three cases', function () {
    expect(EventStatus::cases())->toHaveCount(3);
});

it('has the correct backing values', function (EventStatus $case, string $value) {
    expect($case->value)->toBe($value);
})->with([
    [EventStatus::Draft, 'draft'],
    [EventStatus::Published, 'published'],
    [EventStatus::Archived, 'archived'],
]);
