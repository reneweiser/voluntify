<?php

use App\Actions\CreateGuestList;
use App\Enums\GuestListStatus;
use App\Enums\ScannerType;
use App\Models\GuestList;
use App\Models\Project;
use App\Models\ProjectGearItem;
use App\Models\ProjectScanner;

beforeEach(function () {
    $this->project = Project::factory()->create();
    $this->scanner = ProjectScanner::factory()->for($this->project)->create(['type' => ScannerType::EntryStaff]);
});

it('creates a guest list in draft status', function () {
    $action = new CreateGuestList;

    $result = $action->execute($this->project, [
        'scanner_id' => $this->scanner->id,
        'name' => 'Kuenstler Hauptabend',
    ]);

    expect($result)->toBeInstanceOf(GuestList::class)
        ->and($result->exists)->toBeTrue()
        ->and($result->project_id)->toBe($this->project->id)
        ->and($result->scanner_id)->toBe($this->scanner->id)
        ->and($result->name)->toBe('Kuenstler Hauptabend')
        ->and($result->status)->toBe(GuestListStatus::Draft)
        ->and($result->confirmed_at)->toBeNull();
});

it('accepts gear_items array', function () {
    $gearItem = ProjectGearItem::factory()->for($this->project)->create();
    $action = new CreateGuestList;

    $result = $action->execute($this->project, [
        'scanner_id' => $this->scanner->id,
        'name' => 'With Gear',
        'gear_items' => [$gearItem->id],
    ]);

    expect($result->gear_items)->toBe([$gearItem->id]);
});

it('creates with null gear_items when not provided', function () {
    $action = new CreateGuestList;

    $result = $action->execute($this->project, [
        'scanner_id' => $this->scanner->id,
        'name' => 'No Gear',
    ]);

    expect($result->gear_items)->toBeNull();
});
