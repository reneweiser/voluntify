<?php

use App\Actions\AssignGearToVolunteer;
use App\Exceptions\DomainException;
use App\Models\Event;
use App\Models\Organization;
use App\Models\Project;
use App\Models\ProjectGearItem;
use App\Models\Volunteer;
use App\Models\VolunteerGear;
use App\Models\VolunteerJob;

beforeEach(function () {
    $this->org = Organization::factory()->create();
    $this->project = Project::factory()->for($this->org)->create();
    $this->event = Event::factory()->for($this->org)->for($this->project)->published()->create();
    $this->volunteer = Volunteer::factory()->for($this->project)->verified()->create();
});

it('creates volunteer gear records for all gear items on an event', function () {
    $tshirt = ProjectGearItem::factory()->sized()->for($this->project)->create(['name' => 'T-Shirt']);
    $badge = ProjectGearItem::factory()->for($this->project)->create(['name' => 'Badge']);

    $action = new AssignGearToVolunteer;

    $action->execute($this->volunteer, $this->event, [$tshirt->id => 'L', $badge->id => null]);

    expect(VolunteerGear::count())->toBe(2);

    $tshirtGear = VolunteerGear::where('project_gear_item_id', $tshirt->id)->first();
    expect($tshirtGear->volunteer_id)->toBe($this->volunteer->id)
        ->and($tshirtGear->size)->toBe('L');

    $badgeGear = VolunteerGear::where('project_gear_item_id', $badge->id)->first();
    expect($badgeGear->volunteer_id)->toBe($this->volunteer->id)
        ->and($badgeGear->size)->toBeNull();
});

it('throws exception for invalid size', function () {
    $tshirt = ProjectGearItem::factory()->sized(['S', 'M', 'L'])->for($this->project)->create(['name' => 'T-Shirt']);

    $action = new AssignGearToVolunteer;

    $action->execute($this->volunteer, $this->event, [$tshirt->id => 'XXXL']);
})->throws(DomainException::class, 'Invalid size');

it('throws exception when sized item is explicitly in gearSelections with null value', function () {
    $tshirt = ProjectGearItem::factory()->sized(['S', 'M', 'L'])->for($this->project)->create(['name' => 'T-Shirt']);

    $action = new AssignGearToVolunteer;

    $action->execute($this->volunteer, $this->event, [$tshirt->id => null]);
})->throws(DomainException::class, 'Size is required');

it('throws domain exception when sized item has null available_sizes', function () {
    $tshirt = ProjectGearItem::factory()->for($this->project)->create([
        'name' => 'T-Shirt',
        'requires_size' => true,
        'available_sizes' => null,
    ]);

    $action = new AssignGearToVolunteer;

    $action->execute($this->volunteer, $this->event, [$tshirt->id => 'M']);
})->throws(DomainException::class, 'Invalid size');

it('does not create duplicates on re-assignment', function () {
    $tshirt = ProjectGearItem::factory()->sized()->for($this->project)->create(['name' => 'T-Shirt']);
    $badge = ProjectGearItem::factory()->for($this->project)->create(['name' => 'Badge']);

    $action = new AssignGearToVolunteer;

    $action->execute($this->volunteer, $this->event, [$tshirt->id => 'L', $badge->id => null]);
    $action->execute($this->volunteer, $this->event, [$tshirt->id => 'L', $badge->id => null]);

    expect(VolunteerGear::count())->toBe(2);
});

it('skips Typ 1 sized items when not in gearSelections (no form shown)', function () {
    $tshirt = ProjectGearItem::factory()->sized(['S', 'M', 'L'])->for($this->project)->create(['name' => 'T-Shirt']);

    $action = new AssignGearToVolunteer;

    $action->execute($this->volunteer, $this->event, []);

    expect(VolunteerGear::count())->toBe(0);
});

it('auto-assigns Typ 2 gear with quantity_entitled', function () {
    $drinks = ProjectGearItem::factory()->quantity(3)->for($this->project)->create(['name' => 'Drink Tokens']);

    $action = new AssignGearToVolunteer;
    $action->execute($this->volunteer, $this->event);

    $gear = VolunteerGear::where('project_gear_item_id', $drinks->id)->first();
    expect($gear)->not->toBeNull()
        ->and($gear->quantity_entitled)->toBe(3)
        ->and($gear->volunteer_id)->toBe($this->volunteer->id);
});

it('assigns Typ 2 alongside Typ 1', function () {
    $tshirt = ProjectGearItem::factory()->sized()->for($this->project)->create(['name' => 'T-Shirt']);
    $drinks = ProjectGearItem::factory()->quantity(2)->for($this->project)->create(['name' => 'Drinks']);

    $action = new AssignGearToVolunteer;
    $action->execute($this->volunteer, $this->event, [$tshirt->id => 'L']);

    expect(VolunteerGear::count())->toBe(2);

    $tshirtGear = VolunteerGear::where('project_gear_item_id', $tshirt->id)->first();
    expect($tshirtGear->size)->toBe('L')
        ->and($tshirtGear->quantity_entitled)->toBeNull();

    $drinkGear = VolunteerGear::where('project_gear_item_id', $drinks->id)->first();
    expect($drinkGear->quantity_entitled)->toBe(2)
        ->and($drinkGear->size)->toBeNull();
});

it('skips Typ 2 gear when volunteerJobIds do not match job_ids', function () {
    $jobA = VolunteerJob::factory()->for($this->event)->create();
    $jobB = VolunteerJob::factory()->for($this->event)->create();

    $drinks = ProjectGearItem::factory()->quantity(3)->for($this->project)->create([
        'name' => 'Drinks',
        'job_ids' => [$jobA->id],
    ]);

    $action = new AssignGearToVolunteer;
    $action->execute($this->volunteer, $this->event, [], [$jobB->id]);

    expect(VolunteerGear::count())->toBe(0);
});

it('assigns Typ 2 gear when volunteerJobIds has at least one match', function () {
    $jobA = VolunteerJob::factory()->for($this->event)->create();
    $jobB = VolunteerJob::factory()->for($this->event)->create();

    $drinks = ProjectGearItem::factory()->quantity(3)->for($this->project)->create([
        'name' => 'Drinks',
        'job_ids' => [$jobA->id, $jobB->id],
    ]);

    $action = new AssignGearToVolunteer;
    $action->execute($this->volunteer, $this->event, [], [$jobA->id]);

    expect(VolunteerGear::count())->toBe(1);
    expect(VolunteerGear::first()->quantity_entitled)->toBe(3);
});

it('assigns Typ 2 gear when job_ids is null (no restriction)', function () {
    $drinks = ProjectGearItem::factory()->quantity(5)->for($this->project)->create([
        'name' => 'Drinks',
        'job_ids' => null,
    ]);

    $action = new AssignGearToVolunteer;
    $action->execute($this->volunteer, $this->event);

    expect(VolunteerGear::count())->toBe(1);
    expect(VolunteerGear::first()->quantity_entitled)->toBe(5);
});

it('does not duplicate Typ 2 assignment on re-execution', function () {
    $drinks = ProjectGearItem::factory()->quantity(3)->for($this->project)->create(['name' => 'Drinks']);

    $action = new AssignGearToVolunteer;
    $action->execute($this->volunteer, $this->event);
    $action->execute($this->volunteer, $this->event);

    expect(VolunteerGear::count())->toBe(1);
});
