<?php

namespace App\Actions;

use App\Models\Project;
use Illuminate\Support\Facades\DB;

class CloneProject
{
    public function __construct(
        private CloneEvent $cloneEvent,
    ) {}

    public function execute(Project $project, ?int $dateOffsetDays = null): Project
    {
        return DB::transaction(function () use ($project, $dateOffsetDays) {
            $project->load(['events', 'gearItems', 'customRegistrationFields', 'hintTexts', 'scanners']);

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
                $this->cloneEvent->execute($event, $clonedProject->id, $dateOffsetDays);
            }

            foreach ($project->gearItems as $gearItem) {
                $clonedGear = $gearItem->replicate(['id', 'project_id', 'created_at', 'updated_at']);
                $clonedGear->project_id = $clonedProject->id;
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
                $clonedScanner->save();
            }

            return $clonedProject->fresh();
        });
    }
}
