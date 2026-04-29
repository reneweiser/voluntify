<?php

namespace App\Actions;

use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CloneProject
{
    public function __construct(
        private CloneEvent $cloneEvent,
    ) {}

    public function execute(Project $project, User $causer, ?int $dateOffsetDays = null): Project
    {
        return DB::transaction(function () use ($project, $causer, $dateOffsetDays) {
            $project->load(['events', 'gearItems', 'customRegistrationFields', 'hintTexts', 'scanners']);
            $eventMap = [];

            $clonedProject = $project->replicate([
                'id',
                'public_token',
                'title_image_path',
                'created_at',
                'updated_at',
                'deletion_requested_at',
                'website_published',
            ]);

            $clonedProject->name = "{$project->name} (Copy)";
            $clonedProject->website_published = false;
            $clonedProject->save();

            foreach ($project->events as $event) {
                $clonedEvent = $this->cloneEvent->execute($event, $causer, targetProjectId: $clonedProject->id, dateOffsetDays: $dateOffsetDays);
                $eventMap[$event->id] = $clonedEvent->id;
            }

            foreach ($project->gearItems as $gearItem) {
                $clonedGear = $gearItem->replicate(['id', 'project_id', 'created_at', 'updated_at']);
                $clonedGear->project_id = $clonedProject->id;
                $clonedGear->job_ids = null;
                $clonedGear->save();
            }

            foreach ($project->customRegistrationFields as $field) {
                $clonedField = $field->replicate(['id', 'project_id', 'created_at', 'updated_at']);
                $clonedField->project_id = $clonedProject->id;
                $clonedField->event_id = null;
                $clonedField->save();
            }

            foreach ($project->hintTexts as $hint) {
                $clonedHint = $hint->replicate(['id', 'project_id', 'created_at', 'updated_at']);
                $clonedHint->project_id = $clonedProject->id;
                $clonedHint->save();
            }

            foreach ($project->scanners as $scanner) {
                $clonedScanner = $scanner->replicate([
                    'id',
                    'project_id',
                    'scanner_token',
                    'created_at',
                    'updated_at',
                ]);
                $clonedScanner->project_id = $clonedProject->id;
                $clonedScanner->scanner_token = bin2hex(random_bytes(32));
                $clonedScanner->entry_event_id = $scanner->entry_event_id !== null ? ($eventMap[$scanner->entry_event_id] ?? null) : null;
                $clonedScanner->pool_event_ids = is_array($scanner->pool_event_ids)
                    ? collect($scanner->pool_event_ids)
                        ->map(fn ($eventId) => $eventMap[(int) $eventId] ?? null)
                        ->filter()
                        ->values()
                        ->all()
                    : null;
                $clonedScanner->save();
            }

            return $clonedProject->fresh();
        });
    }
}
