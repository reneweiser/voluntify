<?php

namespace App\Actions;

use App\Enums\GuestListStatus;
use App\Models\GuestList;
use App\Models\Project;

class CreateGuestList
{
    public function execute(Project $project, array $data): GuestList
    {
        return $project->guestLists()->create([
            'scanner_id' => $data['scanner_id'],
            'name' => $data['name'],
            'status' => GuestListStatus::Draft,
            'gear_items' => $data['gear_items'] ?? null,
        ]);
    }
}
