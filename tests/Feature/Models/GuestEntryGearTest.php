<?php

use App\Models\GuestEntry;
use App\Models\GuestEntryGear;
use App\Models\ProjectGearItem;
use Illuminate\Database\UniqueConstraintViolationException;

it('creates guest entry gear with correct attributes', function () {
    $entry = GuestEntry::factory()->create();
    $gearItem = ProjectGearItem::factory()->create();

    $gear = GuestEntryGear::factory()->create([
        'guest_entry_id' => $entry->id,
        'project_gear_item_id' => $gearItem->id,
        'quantity' => 3,
        'picked_up_count' => 1,
        'selection' => 'L',
        'status' => 'issued',
    ]);

    expect($gear->exists)->toBeTrue()
        ->and($gear->quantity)->toBe(3)
        ->and($gear->picked_up_count)->toBe(1)
        ->and($gear->selection)->toBe('L')
        ->and($gear->status)->toBe('issued');
});

it('belongs to a guest entry', function () {
    $entry = GuestEntry::factory()->create();
    $gear = GuestEntryGear::factory()->create(['guest_entry_id' => $entry->id]);

    expect($gear->entry->id)->toBe($entry->id);
});

it('belongs to a project gear item', function () {
    $gearItem = ProjectGearItem::factory()->create();
    $gear = GuestEntryGear::factory()->create(['project_gear_item_id' => $gearItem->id]);

    expect($gear->gearItem->id)->toBe($gearItem->id);
});

it('enforces unique constraint on entry and gear item', function () {
    $entry = GuestEntry::factory()->create();
    $gearItem = ProjectGearItem::factory()->create();

    GuestEntryGear::factory()->create([
        'guest_entry_id' => $entry->id,
        'project_gear_item_id' => $gearItem->id,
    ]);

    expect(fn () => GuestEntryGear::factory()->create([
        'guest_entry_id' => $entry->id,
        'project_gear_item_id' => $gearItem->id,
    ]))->toThrow(UniqueConstraintViolationException::class);
});

it('cascades delete from guest entry', function () {
    $entry = GuestEntry::factory()->create();
    GuestEntryGear::factory()->create(['guest_entry_id' => $entry->id]);

    expect(GuestEntryGear::count())->toBe(1);

    $entry->delete();

    expect(GuestEntryGear::count())->toBe(0);
});
