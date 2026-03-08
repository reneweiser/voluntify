<?php

use App\Actions\AssignGearToVolunteer;
use App\Exceptions\DomainException;
use App\Models\Event;
use App\Models\EventGearItem;
use App\Models\Organization;
use App\Models\Volunteer;
use App\Models\VolunteerGear;

beforeEach(function () {
    $this->org = Organization::factory()->create();
    $this->event = Event::factory()->for($this->org)->published()->create();
    $this->volunteer = Volunteer::factory()->verified()->create();
});

it('creates volunteer gear records for all gear items on an event', function () {
    $tshirt = EventGearItem::factory()->sized()->for($this->event)->create(['name' => 'T-Shirt']);
    $badge = EventGearItem::factory()->for($this->event)->create(['name' => 'Badge']);

    $action = new AssignGearToVolunteer;

    $action->execute($this->volunteer, $this->event, [$tshirt->id => 'L']);

    expect(VolunteerGear::count())->toBe(2);

    $tshirtGear = VolunteerGear::where('event_gear_item_id', $tshirt->id)->first();
    expect($tshirtGear->volunteer_id)->toBe($this->volunteer->id)
        ->and($tshirtGear->size)->toBe('L');

    $badgeGear = VolunteerGear::where('event_gear_item_id', $badge->id)->first();
    expect($badgeGear->volunteer_id)->toBe($this->volunteer->id)
        ->and($badgeGear->size)->toBeNull();
});

it('throws exception for invalid size', function () {
    $tshirt = EventGearItem::factory()->sized(['S', 'M', 'L'])->for($this->event)->create(['name' => 'T-Shirt']);

    $action = new AssignGearToVolunteer;

    $action->execute($this->volunteer, $this->event, [$tshirt->id => 'XXXL']);
})->throws(DomainException::class, 'Invalid size');

it('throws exception when size-required item has no size provided', function () {
    $tshirt = EventGearItem::factory()->sized(['S', 'M', 'L'])->for($this->event)->create(['name' => 'T-Shirt']);

    $action = new AssignGearToVolunteer;

    $action->execute($this->volunteer, $this->event, []);
})->throws(DomainException::class, 'Size is required');

it('does not create duplicates on re-assignment', function () {
    $tshirt = EventGearItem::factory()->sized()->for($this->event)->create(['name' => 'T-Shirt']);
    $badge = EventGearItem::factory()->for($this->event)->create(['name' => 'Badge']);

    $action = new AssignGearToVolunteer;

    $action->execute($this->volunteer, $this->event, [$tshirt->id => 'L']);
    $action->execute($this->volunteer, $this->event, [$tshirt->id => 'L']);

    expect(VolunteerGear::count())->toBe(2);
});
