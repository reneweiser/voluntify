<?php

namespace App\Actions;

use App\Models\Project;
use App\Models\VolunteerGear;
use Illuminate\Support\LazyCollection;

class ExportGearSummaryCsv
{
    public function execute(Project $project): LazyCollection
    {
        return LazyCollection::make(function () use ($project) {
            yield ['Helfer:in', 'E-Mail', 'Artikel', 'Größe', 'Abgeholt'];

            $gearRecords = VolunteerGear::whereHas('gearItem', fn ($q) => $q->where('project_id', $project->id))
                ->with(['volunteer', 'gearItem', 'pickups'])
                ->cursor();

            foreach ($gearRecords as $gear) {
                yield [
                    $gear->volunteer->full_name,
                    $gear->volunteer->email,
                    $gear->gearItem->name,
                    $gear->size ?? '',
                    $gear->isPickedUp() ? 'Ja' : 'Nein',
                ];
            }
        });
    }
}
