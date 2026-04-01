<?php

namespace App\Actions;

use App\Models\ProjectScanner;

class UpdateProjectScanner
{
    public function execute(ProjectScanner $scanner, array $data): ProjectScanner
    {
        $scanner->update([
            'name' => $data['name'] ?? $scanner->name,
            'type' => $data['type'] ?? $scanner->type,
            'modes' => $data['modes'] ?? $scanner->modes,
            'event_id' => array_key_exists('event_id', $data) ? $data['event_id'] : $scanner->event_id,
            'gear_item_ids' => array_key_exists('gear_item_ids', $data) ? $data['gear_item_ids'] : $scanner->gear_item_ids,
            'hint_text' => array_key_exists('hint_text', $data) ? $data['hint_text'] : $scanner->hint_text,
            'starts_at' => $data['starts_at'] ?? $scanner->starts_at,
            'ends_at' => $data['ends_at'] ?? $scanner->ends_at,
        ]);

        return $scanner;
    }
}
