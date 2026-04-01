<?php

use App\Actions\UpdateGuestList;
use App\Enums\ScannerType;
use App\Models\GuestList;
use App\Models\Project;
use App\Models\ProjectScanner;

beforeEach(function () {
    $this->project = Project::factory()->create();
    $this->scanner = ProjectScanner::factory()->for($this->project)->create(['type' => ScannerType::EntryStaff]);
    $this->guestList = GuestList::factory()->create([
        'project_id' => $this->project->id,
        'scanner_id' => $this->scanner->id,
        'name' => 'Original Name',
        'gear_items' => [1, 2],
    ]);
});

it('updates name', function () {
    $action = new UpdateGuestList;

    $result = $action->execute($this->guestList, ['name' => 'Updated Name']);

    expect($result->name)->toBe('Updated Name');
});

it('updates scanner assignment', function () {
    $newScanner = ProjectScanner::factory()->for($this->project)->create(['type' => ScannerType::EntryStaff]);
    $action = new UpdateGuestList;

    $result = $action->execute($this->guestList, ['scanner_id' => $newScanner->id]);

    expect($result->scanner_id)->toBe($newScanner->id);
});

it('updates gear_items to null', function () {
    $action = new UpdateGuestList;

    $result = $action->execute($this->guestList, ['gear_items' => null]);

    expect($result->gear_items)->toBeNull();
});
