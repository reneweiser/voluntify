<?php

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
    $this->entryStaffScanner = ProjectScanner::factory()->active()->create([
        'project_id' => $this->project->id,
        'type' => ScannerType::EntryStaff,
    ]);
    $this->vaScanner = ProjectScanner::factory()->active()->volunteerAdmin()->create([
        'project_id' => $this->project->id,
    ]);
    $this->guestList = GuestList::factory()->confirmed()->create([
        'project_id' => $this->project->id,
        'scanner_id' => $this->entryStaffScanner->id,
    ]);
    $this->group = GuestGroup::factory()->for($this->guestList)->create();
    $this->gearItem = ProjectGearItem::factory()->for($this->project)->create();
});

afterEach(function () {
    Carbon::setTestNow();
});

it('records Typ-1 selection and status', function () {
    $entry = GuestEntry::factory()->for($this->group, 'group')->withQrToken()->create();
    $gear = GuestEntryGear::factory()->create([
        'guest_entry_id' => $entry->id,
        'project_gear_item_id' => $this->gearItem->id,
    ]);

    $response = $this->postJson(route('scanner-api.guest-gear-pickup', $this->vaScanner->id), [
        'guest_entry_gear_id' => $gear->id,
        'selection' => 'L',
        'status' => 'issued',
    ], [
        'X-Scanner-Token' => $this->vaScanner->scanner_token,
    ]);

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('guest_entry_gear.selection', 'L')
        ->assertJsonPath('guest_entry_gear.status', 'issued');
});

it('increments Typ-2 picked_up_count', function () {
    $entry = GuestEntry::factory()->for($this->group, 'group')->withQrToken()->create();
    $gear = GuestEntryGear::factory()->create([
        'guest_entry_id' => $entry->id,
        'project_gear_item_id' => $this->gearItem->id,
        'quantity' => 3,
        'picked_up_count' => 1,
    ]);

    $response = $this->postJson(route('scanner-api.guest-gear-pickup', $this->vaScanner->id), [
        'guest_entry_gear_id' => $gear->id,
        'quantity' => 1,
    ], [
        'X-Scanner-Token' => $this->vaScanner->scanner_token,
    ]);

    $response->assertOk()
        ->assertJsonPath('guest_entry_gear.picked_up_count', 2);
});

it('rejects pickup exceeding quantity', function () {
    $entry = GuestEntry::factory()->for($this->group, 'group')->withQrToken()->create();
    $gear = GuestEntryGear::factory()->create([
        'guest_entry_id' => $entry->id,
        'project_gear_item_id' => $this->gearItem->id,
        'quantity' => 3,
        'picked_up_count' => 3,
    ]);

    $response = $this->postJson(route('scanner-api.guest-gear-pickup', $this->vaScanner->id), [
        'guest_entry_gear_id' => $gear->id,
        'quantity' => 1,
    ], [
        'X-Scanner-Token' => $this->vaScanner->scanner_token,
    ]);

    $response->assertStatus(500);
});

it('rejects gear pickup from entry staff scanner type', function () {
    $entry = GuestEntry::factory()->for($this->group, 'group')->withQrToken()->create();
    $gear = GuestEntryGear::factory()->create([
        'guest_entry_id' => $entry->id,
        'project_gear_item_id' => $this->gearItem->id,
    ]);

    $response = $this->postJson(route('scanner-api.guest-gear-pickup', $this->entryStaffScanner->id), [
        'guest_entry_gear_id' => $gear->id,
        'selection' => 'M',
    ], [
        'X-Scanner-Token' => $this->entryStaffScanner->scanner_token,
    ]);

    $response->assertForbidden()
        ->assertJsonPath('error', 'Only gear-enabled scanners can record guest gear pickups.');
});

it('rejects gear pickup for guests outside the configured gear scanner guest groups', function () {
    $gearScanner = ProjectScanner::factory()->active()->gear()->create([
        'project_id' => $this->project->id,
    ]);

    $allowedList = GuestList::factory()->confirmed()->create([
        'project_id' => $this->project->id,
        'scanner_id' => $this->entryStaffScanner->id,
    ]);
    $allowedGroup = GuestGroup::factory()->for($allowedList)->create();

    $blockedList = GuestList::factory()->confirmed()->create([
        'project_id' => $this->project->id,
        'scanner_id' => $this->entryStaffScanner->id,
    ]);
    $blockedGroup = GuestGroup::factory()->for($blockedList)->create();

    $blockedEntry = GuestEntry::factory()->for($blockedGroup, 'group')->withQrToken()->create();
    $blockedGear = GuestEntryGear::factory()->create([
        'guest_entry_id' => $blockedEntry->id,
        'project_gear_item_id' => $this->gearItem->id,
    ]);

    $gearScanner->update(['guest_group_ids' => [$allowedGroup->id]]);

    $this->postJson(route('scanner-api.guest-gear-pickup', $gearScanner->id), [
        'guest_entry_gear_id' => $blockedGear->id,
        'selection' => 'M',
    ], [
        'X-Scanner-Token' => $gearScanner->scanner_token,
    ])->assertNotFound();
});

it('rejects gear pickup for entry from draft list', function () {
    $draftList = GuestList::factory()->create([
        'project_id' => $this->project->id,
        'scanner_id' => $this->entryStaffScanner->id,
    ]);
    $draftGroup = GuestGroup::factory()->for($draftList)->create();
    $entry = GuestEntry::factory()->for($draftGroup, 'group')->create();
    $gear = GuestEntryGear::factory()->create([
        'guest_entry_id' => $entry->id,
        'project_gear_item_id' => $this->gearItem->id,
    ]);

    $response = $this->postJson(route('scanner-api.guest-gear-pickup', $this->vaScanner->id), [
        'guest_entry_gear_id' => $gear->id,
        'selection' => 'L',
    ], [
        'X-Scanner-Token' => $this->vaScanner->scanner_token,
    ]);

    $response->assertNotFound();
});
