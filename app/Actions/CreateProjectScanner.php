<?php

namespace App\Actions;

use App\Models\Project;
use App\Models\ProjectScanner;

class CreateProjectScanner
{
    public function execute(Project $project, array $data): ProjectScanner
    {
        $rawCode = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $entryEventId = (int) $data['entry_event_id'];
        $poolEventIds = array_values(array_unique(array_map('intval', $data['pool_event_ids'])));
        $guestGroupIds = isset($data['guest_group_ids']) && is_array($data['guest_group_ids'])
            ? array_values(array_unique(array_map('intval', $data['guest_group_ids'])))
            : null;

        return $project->scanners()->create([
            'entry_event_id' => $entryEventId,
            'pool_event_ids' => $poolEventIds,
            'guest_group_ids' => $guestGroupIds !== [] ? $guestGroupIds : null,
            'requires_configuration_review' => false,
            'name' => $data['name'],
            'type' => $data['type'],
            'modes' => $data['modes'] ?? null,
            'gear_item_ids' => $data['gear_item_ids'] ?? null,
            'hint_text' => $data['hint_text'] ?? null,
            'starts_at' => $data['starts_at'],
            'ends_at' => $data['ends_at'],
            'auth_code' => $rawCode,
            'scanner_token' => bin2hex(random_bytes(32)),
        ]);
    }
}
