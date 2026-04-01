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

it('checks in a guest entry', function () {
    $entry = GuestEntry::factory()->for($this->group, 'group')->withQrToken()->create();

    $response = $this->postJson(route('scanner-api.guest-checkin', $this->scanner->id), [
        'guest_entry_id' => $entry->id,
    ], [
        'X-Scanner-Token' => $this->scanner->scanner_token,
    ]);

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('already_checked_in', false);

    expect($entry->fresh()->checked_in_at)->not->toBeNull();
});

it('returns already_checked_in for duplicate check-in', function () {
    $entry = GuestEntry::factory()->for($this->group, 'group')->withQrToken()->checkedIn()->create();

    $response = $this->postJson(route('scanner-api.guest-checkin', $this->scanner->id), [
        'guest_entry_id' => $entry->id,
    ], [
        'X-Scanner-Token' => $this->scanner->scanner_token,
    ]);

    $response->assertOk()
        ->assertJsonPath('already_checked_in', true);
});

it('rejects check-in from wrong scanner', function () {
    $otherScanner = ProjectScanner::factory()->active()->create([
        'project_id' => $this->project->id,
        'type' => ScannerType::EntryStaff,
    ]);
    $otherList = GuestList::factory()->confirmed()->create([
        'project_id' => $this->project->id,
        'scanner_id' => $otherScanner->id,
    ]);
    $otherGroup = GuestGroup::factory()->for($otherList)->create();
    $entry = GuestEntry::factory()->for($otherGroup, 'group')->withQrToken()->create();

    $response = $this->postJson(route('scanner-api.guest-checkin', $this->scanner->id), [
        'guest_entry_id' => $entry->id,
    ], [
        'X-Scanner-Token' => $this->scanner->scanner_token,
    ]);

    $response->assertNotFound();
});

it('rejects check-in from volunteer admin scanner type', function () {
    $vaScanner = ProjectScanner::factory()->active()->volunteerAdmin()->create([
        'project_id' => $this->project->id,
    ]);
    $entry = GuestEntry::factory()->for($this->group, 'group')->withQrToken()->create();

    $response = $this->postJson(route('scanner-api.guest-checkin', $vaScanner->id), [
        'guest_entry_id' => $entry->id,
    ], [
        'X-Scanner-Token' => $vaScanner->scanner_token,
    ]);

    $response->assertForbidden()
        ->assertJsonPath('error', 'Only entry staff scanners can check in guests.');
});

it('rejects check-in for entry from draft list', function () {
    $draftList = GuestList::factory()->create([
        'project_id' => $this->project->id,
        'scanner_id' => $this->scanner->id,
    ]);
    $draftGroup = GuestGroup::factory()->for($draftList)->create();
    $entry = GuestEntry::factory()->for($draftGroup, 'group')->create();

    $response = $this->postJson(route('scanner-api.guest-checkin', $this->scanner->id), [
        'guest_entry_id' => $entry->id,
    ], [
        'X-Scanner-Token' => $this->scanner->scanner_token,
    ]);

    $response->assertNotFound();
});
