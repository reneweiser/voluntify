<?php

use App\DataTransferObjects\SignupData;
use App\Models\Event;

it('holds all signup data properties', function () {
    $event = new Event;
    $dto = new SignupData(
        name: 'John',
        email: 'john@example.com',
        event: $event,
        shiftIds: [1, 2],
        phone: '+15551234567',
        gearSelections: [1 => 'M'],
        customFieldResponses: [3 => 'Vegan'],
    );

    expect($dto->name)->toBe('John')
        ->and($dto->email)->toBe('john@example.com')
        ->and($dto->event)->toBe($event)
        ->and($dto->shiftIds)->toBe([1, 2])
        ->and($dto->phone)->toBe('+15551234567')
        ->and($dto->gearSelections)->toBe([1 => 'M'])
        ->and($dto->customFieldResponses)->toBe([3 => 'Vegan']);
});

it('defaults optional fields to null', function () {
    $event = new Event;
    $dto = new SignupData(
        name: 'Jane',
        email: 'jane@example.com',
        event: $event,
        shiftIds: [1],
    );

    expect($dto->phone)->toBeNull()
        ->and($dto->gearSelections)->toBeNull()
        ->and($dto->customFieldResponses)->toBeNull();
});
