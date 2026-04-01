<?php

use App\Actions\RestoreProject;
use App\Exceptions\DomainException;
use App\Models\Organization;
use App\Models\Project;

it('restores a pending-deletion project', function () {
    $org = Organization::factory()->create();
    $project = Project::factory()->for($org)->create(['deletion_requested_at' => now()]);

    $action = new RestoreProject;
    $result = $action->execute($project);

    expect($result->isPendingDeletion())->toBeFalse()
        ->and($result->deletion_requested_at)->toBeNull();
});

it('throws if project is not pending deletion', function () {
    $org = Organization::factory()->create();
    $project = Project::factory()->for($org)->create();

    $action = new RestoreProject;

    expect(fn () => $action->execute($project))
        ->toThrow(DomainException::class, 'Projekt ist nicht zur Löschung vorgemerkt.');
});
