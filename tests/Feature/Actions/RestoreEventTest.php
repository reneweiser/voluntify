<?php

use App\Actions\RestoreEvent;
use App\Exceptions\DomainException;
use App\Models\Event;
use App\Models\Organization;
use App\Models\Project;

it('restores a pending-deletion event', function () {
    $org = Organization::factory()->create();
    $project = Project::factory()->for($org)->create();
    $event = Event::factory()->for($org)->for($project)->create([
        'deletion_requested_at' => now(),
    ]);

    $action = new RestoreEvent;
    $result = $action->execute($event);

    expect($result->isPendingDeletion())->toBeFalse()
        ->and($result->deletion_requested_at)->toBeNull();
});

it('throws if event is not pending deletion', function () {
    $org = Organization::factory()->create();
    $project = Project::factory()->for($org)->create();
    $event = Event::factory()->for($org)->for($project)->create();

    $action = new RestoreEvent;

    expect(fn () => $action->execute($event))
        ->toThrow(DomainException::class, 'Event ist nicht zur Löschung vorgemerkt.');
});
