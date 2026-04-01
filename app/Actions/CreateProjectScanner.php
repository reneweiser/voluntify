<?php

namespace App\Actions;

use App\Models\Project;
use App\Models\ProjectScanner;
use Illuminate\Support\Facades\Hash;

class CreateProjectScanner
{
    public function execute(Project $project, array $data): ProjectScanner
    {
        $rawCode = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $scanner = $project->scanners()->create([
            'event_id' => $data['event_id'] ?? null,
            'name' => $data['name'],
            'type' => $data['type'],
            'modes' => $data['modes'] ?? null,
            'gear_item_ids' => $data['gear_item_ids'] ?? null,
            'hint_text' => $data['hint_text'] ?? null,
            'starts_at' => $data['starts_at'],
            'ends_at' => $data['ends_at'],
            'auth_code' => Hash::make($rawCode),
            'scanner_token' => bin2hex(random_bytes(32)),
        ]);

        $scanner->raw_auth_code = $rawCode;

        return $scanner;
    }
}
