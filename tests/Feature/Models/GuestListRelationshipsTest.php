<?php

use App\Enums\ScannerType;
use App\Models\GuestList;
use App\Models\Project;
use App\Models\ProjectScanner;
use Illuminate\Database\QueryException;

it('project has many guest lists', function () {
    $project = Project::factory()->create();
    GuestList::factory()->count(2)->create(['project_id' => $project->id]);

    expect($project->guestLists)->toHaveCount(2);
});

it('scanner has many guest lists', function () {
    $scanner = ProjectScanner::factory()->create(['type' => ScannerType::EntryStaff]);
    GuestList::factory()->count(2)->create(['scanner_id' => $scanner->id]);

    expect($scanner->guestLists)->toHaveCount(2);
});

it('prevents deleting a scanner that has guest lists', function () {
    $scanner = ProjectScanner::factory()->create(['type' => ScannerType::EntryStaff]);
    GuestList::factory()->create(['scanner_id' => $scanner->id]);

    expect(fn () => $scanner->delete())->toThrow(QueryException::class);
});

it('cascades delete when project is deleted', function () {
    $project = Project::factory()->create();
    $scanner = ProjectScanner::factory()->for($project)->create();
    GuestList::factory()->create([
        'project_id' => $project->id,
        'scanner_id' => $scanner->id,
    ]);

    expect(GuestList::count())->toBe(1);

    $project->delete();

    expect(GuestList::count())->toBe(0);
});
