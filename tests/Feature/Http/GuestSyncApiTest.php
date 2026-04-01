<?php

use App\Enums\ScannerType;
use App\Models\GuestEntry;
use App\Models\GuestGroup;
use App\Models\GuestList;
use App\Models\Project;
use App\Models\ProjectScanner;
use Carbon\Carbon;

beforeEach(function () {
    Carbon::setTestNow('2026-07-01 12:00:00');
    $this->project = Project::factory()->create();
    $this->scanner = ProjectScanner::factory()->active()->create([
        'project_id' => $this->project->id,
        'type' => ScannerType::EntryStaff,
    ]);
    $this->guestList = GuestList::factory()->confirmed()->create([
        'project_id' => $this->project->id,
        'scanner_id' => $this->scanner->id,
    ]);
    $this->group = GuestGroup::factory()->for($this->guestList)->create();
});

afterEach(function () {
    Carbon::setTestNow();
});

it('batch syncs guest check-ins', function () {
    $entry1 = GuestEntry::factory()->for($this->group, 'group')->withQrToken()->create(['number' => 1]);
    $entry2 = GuestEntry::factory()->for($this->group, 'group')->withQrToken()->create(['number' => 2]);

    $response = $this->postJson(route('scanner-api.guest-sync', $this->scanner->id), [
        'guest_checkins' => [
            ['guest_entry_id' => $entry1->id, 'checked_in_at' => '2026-07-01 19:30:00'],
            ['guest_entry_id' => $entry2->id, 'checked_in_at' => '2026-07-01 19:31:00'],
        ],
    ], [
        'X-Scanner-Token' => $this->scanner->scanner_token,
    ]);

    $response->assertOk()
        ->assertJsonStructure(['guest_entries']);

    expect($entry1->fresh()->checked_in_at)->not->toBeNull()
        ->and($entry2->fresh()->checked_in_at)->not->toBeNull();
});

it('handles empty guest_checkins array', function () {
    $response = $this->postJson(route('scanner-api.guest-sync', $this->scanner->id), [
        'guest_checkins' => [],
    ], [
        'X-Scanner-Token' => $this->scanner->scanner_token,
    ]);

    $response->assertOk()
        ->assertJsonStructure(['guest_entries']);
});

it('skips already checked-in entries during sync', function () {
    $originalTime = Carbon::parse('2026-07-01 18:00:00');
    $entry = GuestEntry::factory()->for($this->group, 'group')->withQrToken()->create([
        'number' => 1,
        'checked_in_at' => $originalTime,
    ]);

    $response = $this->postJson(route('scanner-api.guest-sync', $this->scanner->id), [
        'guest_checkins' => [
            ['guest_entry_id' => $entry->id, 'checked_in_at' => '2026-07-01 19:30:00'],
        ],
    ], [
        'X-Scanner-Token' => $this->scanner->scanner_token,
    ]);

    $response->assertOk();

    // Original check-in time should be preserved
    expect($entry->fresh()->checked_in_at->toDateTimeString())->toBe('2026-07-01 18:00:00');
});

it('rejects sync from volunteer admin scanner type', function () {
    $vaScanner = ProjectScanner::factory()->active()->volunteerAdmin()->create([
        'project_id' => $this->project->id,
    ]);

    $response = $this->postJson(route('scanner-api.guest-sync', $vaScanner->id), [
        'guest_checkins' => [],
    ], [
        'X-Scanner-Token' => $vaScanner->scanner_token,
    ]);

    $response->assertForbidden()
        ->assertJsonPath('error', 'Only entry staff scanners can sync guest check-ins.');
});

it('silently skips entries belonging to another scanner during sync', function () {
    $otherScanner = ProjectScanner::factory()->active()->create([
        'project_id' => $this->project->id,
        'type' => ScannerType::EntryStaff,
    ]);
    $otherList = GuestList::factory()->confirmed()->create([
        'project_id' => $this->project->id,
        'scanner_id' => $otherScanner->id,
    ]);
    $otherGroup = GuestGroup::factory()->for($otherList)->create();
    $otherEntry = GuestEntry::factory()->for($otherGroup, 'group')->withQrToken()->create(['number' => 1]);

    $response = $this->postJson(route('scanner-api.guest-sync', $this->scanner->id), [
        'guest_checkins' => [
            ['guest_entry_id' => $otherEntry->id, 'checked_in_at' => '2026-07-01 19:30:00'],
        ],
    ], [
        'X-Scanner-Token' => $this->scanner->scanner_token,
    ]);

    $response->assertOk();

    // Entry should NOT be checked in because it belongs to another scanner
    expect($otherEntry->fresh()->checked_in_at)->toBeNull();
});
