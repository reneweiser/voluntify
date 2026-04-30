<?php

namespace App\Actions;

use App\Models\ProjectScanner;

class UpdateProjectScanner
{
    public function execute(ProjectScanner $scanner, array $data): ProjectScanner
    {
        $entryEventId = isset($data['entry_event_id']) ? (int) $data['entry_event_id'] : $scanner->entry_event_id;
        $poolEventIds = isset($data['pool_event_ids']) && is_array($data['pool_event_ids'])
            ? array_values(array_unique(array_map('intval', $data['pool_event_ids'])))
            : $scanner->configuredPoolEventIds();
        $guestGroupIds = array_key_exists('guest_group_ids', $data) && is_array($data['guest_group_ids'])
            ? array_values(array_unique(array_map('intval', $data['guest_group_ids'])))
            : $scanner->configuredGuestGroupIds();

        $scanner->update([
            'name' => $data['name'] ?? $scanner->name,
            'type' => $data['type'] ?? $scanner->type,
            'modes' => $data['modes'] ?? $scanner->modes,
            'entry_event_id' => $entryEventId,
            'pool_event_ids' => $poolEventIds,
            'guest_group_ids' => array_key_exists('guest_group_ids', $data)
                ? ($guestGroupIds !== [] ? $guestGroupIds : null)
                : $scanner->guest_group_ids,
            'requires_configuration_review' => $this->shouldClearReviewFlag($entryEventId, $poolEventIds) ? false : $scanner->requires_configuration_review,
            'gear_item_ids' => array_key_exists('gear_item_ids', $data) ? $data['gear_item_ids'] : $scanner->gear_item_ids,
            'hint_text' => array_key_exists('hint_text', $data) ? $data['hint_text'] : $scanner->hint_text,
            'starts_at' => $data['starts_at'] ?? $scanner->starts_at,
            'ends_at' => $data['ends_at'] ?? $scanner->ends_at,
        ]);

        return $scanner;
    }

    /** @param  array<int, int>  $poolEventIds */
    private function shouldClearReviewFlag(int $entryEventId, array $poolEventIds): bool
    {
        return $poolEventIds !== []
            && in_array($entryEventId, $poolEventIds, true);
    }
}
