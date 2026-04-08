<?php

namespace App\Actions;

use App\Enums\GearItemType;
use App\Models\Project;

class GenerateGearSummary
{
    /**
     * @return array<int, array{id: int, name: string, type: string, requires_size: bool, total_assigned: int, picked_up: int, pending: int, total_entitled: int|null, total_picked_up_quantity: int|null}>
     */
    public function execute(Project $project): array
    {
        $gearItems = $project->gearItems()
            ->with(['volunteerGear.pickups'])
            ->withCount([
                'volunteerGear as total_assigned',
                'volunteerGear as picked_up' => fn ($q) => $q->whereHas('pickups'),
            ])
            ->get();

        return $gearItems->map(function ($item) {
            $base = [
                'id' => $item->id,
                'name' => $item->name,
                'type' => $item->type->value,
                'requires_size' => $item->requires_size,
                'total_assigned' => $item->total_assigned,
            ];

            if ($item->type === GearItemType::Quantity) {
                $totalEntitled = (int) $item->volunteerGear->sum('quantity_entitled');
                $totalPickedUpQty = (int) $item->volunteerGear->flatMap->pickups->sum('quantity');

                $base['total_entitled'] = $totalEntitled;
                $base['total_picked_up_quantity'] = $totalPickedUpQty;
                $base['picked_up'] = $totalPickedUpQty;
                $base['pending'] = $totalEntitled - $totalPickedUpQty;
            } else {
                $base['total_entitled'] = null;
                $base['total_picked_up_quantity'] = null;
                $base['picked_up'] = $item->picked_up;
                $base['pending'] = $item->total_assigned - $item->picked_up;
            }

            return $base;
        })->all();
    }
}
