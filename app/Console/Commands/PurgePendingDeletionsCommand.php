<?php

namespace App\Console\Commands;

use App\Actions\PermanentlyDeleteEvent;
use App\Actions\PermanentlyDeleteProject;
use App\Models\Event;
use App\Models\Project;
use Illuminate\Console\Command;

class PurgePendingDeletionsCommand extends Command
{
    protected $signature = 'app:purge-pending-deletions';

    protected $description = 'Permanently delete projects and events that have been pending deletion for 30+ days';

    public function handle(): void
    {
        $cutoff = now()->subDays(30);

        $events = Event::where('deletion_requested_at', '<', $cutoff)->get();
        $eventAction = app(PermanentlyDeleteEvent::class);

        foreach ($events as $event) {
            $eventAction->execute($event);
            $this->info("Permanently deleted event: {$event->name}");
        }

        $projects = Project::where('deletion_requested_at', '<', $cutoff)->get();
        $projectAction = app(PermanentlyDeleteProject::class);

        foreach ($projects as $project) {
            $projectAction->execute($project);
            $this->info("Permanently deleted project: {$project->name}");
        }

        $total = $events->count() + $projects->count();
        if ($total === 0) {
            $this->info('No pending deletions to purge.');
        } else {
            $this->info("Purged {$total} record(s).");
        }
    }
}
