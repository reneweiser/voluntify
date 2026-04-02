<?php

use App\Actions\ExportGearSummaryCsv;
use App\Models\Organization;
use App\Models\Project;
use App\Models\ProjectGearItem;
use App\Models\Volunteer;
use App\Models\VolunteerGear;
use App\Models\VolunteerGearPickup;

beforeEach(function () {
    $this->org = Organization::factory()->create();
    $this->project = Project::factory()->for($this->org)->create();
    $this->action = new ExportGearSummaryCsv;
});

it('returns header row for project with no gear', function () {
    $rows = $this->action->execute($this->project)->toArray();

    expect($rows)->toHaveCount(1)
        ->and($rows[0])->toBe(['Helfer:in', 'E-Mail', 'Artikel', 'Größe', 'Abgeholt']);
});

it('exports gear records with correct columns', function () {
    $volunteer = Volunteer::factory()->for($this->project)->create([
        'first_name' => 'Alice',
        'last_name' => 'Smith',
        'email' => 'alice@example.com',
    ]);
    $gearItem = ProjectGearItem::factory()->for($this->project)->create(['name' => 'Badge']);
    VolunteerGear::factory()->create([
        'project_gear_item_id' => $gearItem->id,
        'volunteer_id' => $volunteer->id,
    ]);

    $rows = $this->action->execute($this->project)->toArray();

    // Header + 1 data row
    expect($rows)->toHaveCount(2)
        ->and($rows[1][0])->toBe('Alice Smith')
        ->and($rows[1][1])->toBe('alice@example.com')
        ->and($rows[1][2])->toBe('Badge');
});

it('shows size when present and empty when null', function () {
    $volunteer = Volunteer::factory()->for($this->project)->create();
    $sizedItem = ProjectGearItem::factory()->sized()->for($this->project)->create(['name' => 'T-Shirt']);
    $plainItem = ProjectGearItem::factory()->for($this->project)->create(['name' => 'Lanyard']);

    VolunteerGear::factory()->create([
        'project_gear_item_id' => $sizedItem->id,
        'volunteer_id' => $volunteer->id,
        'size' => 'L',
    ]);
    VolunteerGear::factory()->create([
        'project_gear_item_id' => $plainItem->id,
        'volunteer_id' => $volunteer->id,
        'size' => null,
    ]);

    $rows = $this->action->execute($this->project)->toArray();

    $dataRows = array_slice($rows, 1);
    $sizedRow = collect($dataRows)->firstWhere(2, 'T-Shirt');
    $plainRow = collect($dataRows)->firstWhere(2, 'Lanyard');

    expect($sizedRow[3])->toBe('L')
        ->and($plainRow[3])->toBe('');
});

it('shows Ja for picked up and Nein for not picked up', function () {
    $volunteer = Volunteer::factory()->for($this->project)->create();
    $item1 = ProjectGearItem::factory()->for($this->project)->create(['name' => 'Picked']);
    $item2 = ProjectGearItem::factory()->for($this->project)->create(['name' => 'Not Picked']);

    $gear1 = VolunteerGear::factory()->create([
        'project_gear_item_id' => $item1->id,
        'volunteer_id' => $volunteer->id,
    ]);
    VolunteerGearPickup::factory()->create(['volunteer_gear_id' => $gear1->id]);

    VolunteerGear::factory()->create([
        'project_gear_item_id' => $item2->id,
        'volunteer_id' => $volunteer->id,
    ]);

    $rows = $this->action->execute($this->project)->toArray();

    $dataRows = array_slice($rows, 1);
    $pickedRow = collect($dataRows)->firstWhere(2, 'Picked');
    $notPickedRow = collect($dataRows)->firstWhere(2, 'Not Picked');

    expect($pickedRow[4])->toBe('Ja')
        ->and($notPickedRow[4])->toBe('Nein');
});

it('only exports gear for the specified project', function () {
    $otherProject = Project::factory()->for($this->org)->create();

    $volunteer = Volunteer::factory()->for($this->project)->create();
    $thisItem = ProjectGearItem::factory()->for($this->project)->create(['name' => 'Ours']);
    VolunteerGear::factory()->create([
        'project_gear_item_id' => $thisItem->id,
        'volunteer_id' => $volunteer->id,
    ]);

    $otherVolunteer = Volunteer::factory()->for($otherProject)->create();
    $otherItem = ProjectGearItem::factory()->for($otherProject)->create(['name' => 'Theirs']);
    VolunteerGear::factory()->create([
        'project_gear_item_id' => $otherItem->id,
        'volunteer_id' => $otherVolunteer->id,
    ]);

    $rows = $this->action->execute($this->project)->toArray();

    // Header + 1 data row (only ours)
    expect($rows)->toHaveCount(2)
        ->and($rows[1][2])->toBe('Ours');
});
