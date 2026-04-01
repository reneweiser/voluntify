<?php

use App\Actions\DeleteProjectScanner;
use App\Models\Project;
use App\Models\ProjectScanner;
use App\Models\ProjectScannerAssignee;

it('deletes a scanner from database', function () {
    $scanner = ProjectScanner::factory()->create();
    $scannerId = $scanner->id;

    $action = new DeleteProjectScanner;
    $action->execute($scanner);

    expect(ProjectScanner::find($scannerId))->toBeNull();
});

it('cascades deletion to assignees', function () {
    $scanner = ProjectScanner::factory()->create();
    $assignees = ProjectScannerAssignee::factory()->count(3)->for($scanner, 'projectScanner')->create();

    $assigneeIds = $assignees->pluck('id');

    $action = new DeleteProjectScanner;
    $action->execute($scanner);

    foreach ($assigneeIds as $id) {
        expect(ProjectScannerAssignee::find($id))->toBeNull();
    }
});

it('does not delete other scanners in the same project', function () {
    $project = Project::factory()->create();
    $scanner1 = ProjectScanner::factory()->for($project)->create();
    $scanner2 = ProjectScanner::factory()->for($project)->create();

    $action = new DeleteProjectScanner;
    $action->execute($scanner1);

    expect(ProjectScanner::find($scanner1->id))->toBeNull()
        ->and(ProjectScanner::find($scanner2->id))->not->toBeNull();
});
