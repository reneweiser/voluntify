<?php

use App\Actions\GenerateGearSummary;
use App\Models\Organization;
use App\Models\Project;
use App\Models\ProjectGearItem;
use App\Models\Volunteer;
use App\Models\VolunteerGear;
use App\Models\VolunteerGearPickup;

beforeEach(function () {
    $this->org = Organization::factory()->create();
    $this->project = Project::factory()->for($this->org)->create();
    $this->action = new GenerateGearSummary;
});

it('returns empty array when no gear items', function () {
    $result = $this->action->execute($this->project);

    expect($result)->toBeEmpty();
});

it('counts total assigned correctly', function () {
    $item = ProjectGearItem::factory()->for($this->project)->create();
    $v1 = Volunteer::factory()->for($this->project)->create();
    $v2 = Volunteer::factory()->for($this->project)->create();
    VolunteerGear::factory()->create(['project_gear_item_id' => $item->id, 'volunteer_id' => $v1->id]);
    VolunteerGear::factory()->create(['project_gear_item_id' => $item->id, 'volunteer_id' => $v2->id]);

    $result = $this->action->execute($this->project);

    expect($result)->toHaveCount(1)
        ->and($result[0]['total_assigned'])->toBe(2);
});

it('counts picked up correctly', function () {
    $item = ProjectGearItem::factory()->for($this->project)->create();
    $v1 = Volunteer::factory()->for($this->project)->create();
    $v2 = Volunteer::factory()->for($this->project)->create();
    $gear1 = VolunteerGear::factory()->create(['project_gear_item_id' => $item->id, 'volunteer_id' => $v1->id]);
    VolunteerGear::factory()->create(['project_gear_item_id' => $item->id, 'volunteer_id' => $v2->id]);

    VolunteerGearPickup::create([
        'volunteer_gear_id' => $gear1->id,
        'picked_up_at' => now(),
    ]);

    $result = $this->action->execute($this->project);

    expect($result[0]['picked_up'])->toBe(1)
        ->and($result[0]['pending'])->toBe(1);
});

it('returns multiple items', function () {
    ProjectGearItem::factory()->for($this->project)->create(['name' => 'T-Shirt']);
    ProjectGearItem::factory()->for($this->project)->create(['name' => 'Badge']);

    $result = $this->action->execute($this->project);

    expect($result)->toHaveCount(2);
});

it('returns null total_entitled and total_picked_up_quantity for Typ 1 items', function () {
    ProjectGearItem::factory()->for($this->project)->create(['name' => 'Badge']);

    $result = $this->action->execute($this->project);

    expect($result[0]['total_entitled'])->toBeNull()
        ->and($result[0]['total_picked_up_quantity'])->toBeNull();
});

it('includes total_entitled and total_picked_up_quantity for Typ 2 items', function () {
    $item = ProjectGearItem::factory()->quantity(3)->for($this->project)->create(['name' => 'Drinks']);
    $v1 = Volunteer::factory()->for($this->project)->create();
    $v2 = Volunteer::factory()->for($this->project)->create();
    $gear1 = VolunteerGear::factory()->withQuantity(3)->create(['project_gear_item_id' => $item->id, 'volunteer_id' => $v1->id]);
    $gear2 = VolunteerGear::factory()->withQuantity(3)->create(['project_gear_item_id' => $item->id, 'volunteer_id' => $v2->id]);

    VolunteerGearPickup::factory()->create(['volunteer_gear_id' => $gear1->id, 'quantity' => 2]);
    VolunteerGearPickup::factory()->create(['volunteer_gear_id' => $gear2->id, 'quantity' => 1]);

    $result = $this->action->execute($this->project);

    expect($result[0]['total_entitled'])->toBe(6)
        ->and($result[0]['total_picked_up_quantity'])->toBe(3)
        ->and($result[0]['pending'])->toBe(3);
});

it('calculates pending correctly for Typ 2 when fully picked up', function () {
    $item = ProjectGearItem::factory()->quantity(2)->for($this->project)->create(['name' => 'Tokens']);
    $v1 = Volunteer::factory()->for($this->project)->create();
    $gear1 = VolunteerGear::factory()->withQuantity(2)->create(['project_gear_item_id' => $item->id, 'volunteer_id' => $v1->id]);

    VolunteerGearPickup::factory()->create(['volunteer_gear_id' => $gear1->id, 'quantity' => 1]);
    VolunteerGearPickup::factory()->create(['volunteer_gear_id' => $gear1->id, 'quantity' => 1]);

    $result = $this->action->execute($this->project);

    expect($result[0]['total_entitled'])->toBe(2)
        ->and($result[0]['total_picked_up_quantity'])->toBe(2)
        ->and($result[0]['pending'])->toBe(0);
});
