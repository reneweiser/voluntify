<?php

namespace App\Services;

use App\Enums\HintLocation;
use App\Models\HintText;
use App\Models\Project;

class HintTextResolver
{
    public function resolve(HintLocation $location, Project $project): ?string
    {
        $hint = HintText::query()
            ->where('project_id', $project->id)
            ->forLocation($location)
            ->enabled()
            ->first();

        return $hint?->text;
    }
}
