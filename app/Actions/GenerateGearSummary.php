<?php

namespace App\Actions;

use App\Models\Project;

class GenerateGearSummary
{
    /**
     * @return array<int, array{id: int, name: string, type: string, requires_size: bool, total_assigned: int, picked_up: int, pending: int}>
     */
    public function execute(Project $project): array
    {
        $gearItems = $project->gearItems()->withCount([
            'volunteerGear as total_assigned',
            'volunteerGear as picked_up' => fn ($q) => $q->whereHas('pickups'),
        ])->get();

        return $gearItems->map(fn ($item) => [
            'id' => $item->id,
            'name' => $item->name,
            'type' => $item->type->value,
            'requires_size' => $item->requires_size,
            'total_assigned' => $item->total_assigned,
            'picked_up' => $item->picked_up,
            'pending' => $item->total_assigned - $item->picked_up,
        ])->all();
    }
}
