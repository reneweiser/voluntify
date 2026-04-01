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
