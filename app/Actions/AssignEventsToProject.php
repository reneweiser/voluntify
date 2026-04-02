<?php

namespace App\Actions;

use App\Events\Activity\EventAssignedToProject;
use App\Exceptions\DomainException;
use App\Models\Event;
use App\Models\Project;
use App\Models\User;

class AssignEventsToProject
{
    /**
     * @param  array<int>  $eventIds
     */
    public function execute(Project $project, array $eventIds, User $causer): void
    {
        $events = Event::whereIn('id', $eventIds)->get();

        foreach ($events as $event) {
            if ($event->organization_id !== $project->organization_id) {
                throw new DomainException('Cannot assign events from a different organization to this project.');
            }
        }

        Event::whereIn('id', $eventIds)->update(['project_id' => $project->id]);

        foreach ($events as $event) {
            EventAssignedToProject::dispatch($project, $event, $causer);
        }
    }
}
