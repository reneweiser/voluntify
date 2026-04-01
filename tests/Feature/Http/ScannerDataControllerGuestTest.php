<?php

use App\Enums\GuestListStatus;
use App\Enums\ScannerType;
use App\Models\GuestEntry;
use App\Models\GuestEntryGear;
use App\Models\GuestGroup;
use App\Models\GuestList;
use App\Models\Project;
use App\Models\ProjectGearItem;
use App\Models\ProjectScanner;
use Carbon\Carbon;

beforeEach(function () {
    Carbon::setTestNow('2026-07-01 12:00:00');
    $this->project = Project::factory()->create();
    $this->scanner = ProjectScanner::factory()->active()->create([
        'project_id' => $this->project->id,
        'type' => ScannerType::EntryStaff,
    ]);
});

afterEach(function () {
    Carbon::setTestNow();
});

it('includes guest entries in data response for entry staff scanner', function () {
    $guestList = GuestList::factory()->confirmed()->create([
        'project_id' => $this->project->id,
        'scanner_id' => $this->scanner->id,
    ]);
    $group = GuestGroup::factory()->for($guestList)->create(['label' => 'DJ Soundwave', 'guest_count' => 2]);
    GuestEntry::factory()->for($group, 'group')->withQrToken()->create(['number' => 1, 'name' => 'DJ']);
    GuestEntry::factory()->for($group, 'group')->withQrToken()->create(['number' => 2]);

    $response = $this->getJson(route('scanner-api.data', $this->scanner->id), [
        'X-Scanner-Token' => $this->scanner->scanner_token,
    ]);

    $response->assertOk()
        ->assertJsonCount(2, 'guest_entries')
        ->assertJsonPath('guest_entries.0.group_label', 'DJ Soundwave')
        ->assertJsonPath('guest_entries.0.name', 'DJ');

    expect($response->json('guest_entries.0.qr_token'))->not->toBeNull();
});

it('excludes draft guest lists from data response', function () {
    $guestList = GuestList::factory()->create([
        'project_id' => $this->project->id,
        'scanner_id' => $this->scanner->id,
        'status' => GuestListStatus::Draft,
    ]);
    $group = GuestGroup::factory()->for($guestList)->create();
    GuestEntry::factory()->for($group, 'group')->create();

    $response = $this->getJson(route('scanner-api.data', $this->scanner->id), [
        'X-Scanner-Token' => $this->scanner->scanner_token,
    ]);

    $response->assertOk()
        ->assertJsonCount(0, 'guest_entries');
});

it('only includes guest entries from this scanner for entry staff', function () {
    $otherScanner = ProjectScanner::factory()->active()->create([
        'project_id' => $this->project->id,
        'type' => ScannerType::EntryStaff,
    ]);

    $myList = GuestList::factory()->confirmed()->create([
        'project_id' => $this->project->id,
        'scanner_id' => $this->scanner->id,
    ]);
    $myGroup = GuestGroup::factory()->for($myList)->create();
    GuestEntry::factory()->for($myGroup, 'group')->withQrToken()->create();

    $otherList = GuestList::factory()->confirmed()->create([
        'project_id' => $this->project->id,
        'scanner_id' => $otherScanner->id,
    ]);
    $otherGroup = GuestGroup::factory()->for($otherList)->create();
    GuestEntry::factory()->for($otherGroup, 'group')->withQrToken()->create();

    $response = $this->getJson(route('scanner-api.data', $this->scanner->id), [
        'X-Scanner-Token' => $this->scanner->scanner_token,
    ]);

    $response->assertOk()
        ->assertJsonCount(1, 'guest_entries');
});

it('includes guest entries with gear for volunteer admin scanner', function () {
    $vaScanner = ProjectScanner::factory()->active()->volunteerAdmin()->create([
        'project_id' => $this->project->id,
    ]);

    $guestList = GuestList::factory()->confirmed()->create([
        'project_id' => $this->project->id,
        'scanner_id' => $this->scanner->id, // linked to entry staff scanner
    ]);
    $group = GuestGroup::factory()->for($guestList)->create();
    $entryWithGear = GuestEntry::factory()->for($group, 'group')->withQrToken()->create();
    $gearItem = ProjectGearItem::factory()->for($this->project)->create();
    GuestEntryGear::factory()->create([
        'guest_entry_id' => $entryWithGear->id,
        'project_gear_item_id' => $gearItem->id,
    ]);
    GuestEntry::factory()->for($group, 'group')->withQrToken()->create(); // no gear

    $response = $this->getJson(route('scanner-api.data', $vaScanner->id), [
        'X-Scanner-Token' => $vaScanner->scanner_token,
    ]);

    $response->assertOk()
        ->assertJsonCount(1, 'guest_entries');

    // Volunteer Admin should NOT have qr_token in response
    expect($response->json('guest_entries.0'))->not->toHaveKey('qr_token');
});

it('returns empty guest entries when no guest lists exist', function () {
    $response = $this->getJson(route('scanner-api.data', $this->scanner->id), [
        'X-Scanner-Token' => $this->scanner->scanner_token,
    ]);

    $response->assertOk()
        ->assertJsonCount(0, 'guest_entries');
});

it('includes available_sizes and available_states in VA scanner gear data', function () {
    $vaScanner = ProjectScanner::factory()->active()->volunteerAdmin()->create([
        'project_id' => $this->project->id,
    ]);

    $guestList = GuestList::factory()->confirmed()->create([
        'project_id' => $this->project->id,
        'scanner_id' => $this->scanner->id,
    ]);
    $group = GuestGroup::factory()->for($guestList)->create();
    $entry = GuestEntry::factory()->for($group, 'group')->withQrToken()->create();
    $gearItem = ProjectGearItem::factory()->for($this->project)->create([
        'available_sizes' => ['S', 'M', 'L', 'XL'],
        'available_states' => ['issued', 'returned'],
    ]);
    GuestEntryGear::factory()->create([
        'guest_entry_id' => $entry->id,
        'project_gear_item_id' => $gearItem->id,
    ]);

    $response = $this->getJson(route('scanner-api.data', $vaScanner->id), [
        'X-Scanner-Token' => $vaScanner->scanner_token,
    ]);

    $response->assertOk()
        ->assertJsonCount(1, 'guest_entries');

    $gearData = $response->json('guest_entries.0.gear.0');
    expect($gearData['available_sizes'])->toBe(['S', 'M', 'L', 'XL'])
        ->and($gearData['available_states'])->toBe(['issued', 'returned']);
});
