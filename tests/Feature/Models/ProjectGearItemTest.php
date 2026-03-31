<?php

use App\Enums\GearItemType;
use App\Models\Project;
use App\Models\ProjectGearItem;
use App\Models\VolunteerGear;

it('belongs to a project', function () {
    $project = Project::factory()->create();
    $item = ProjectGearItem::factory()->for($project)->create();

    expect($item->project->id)->toBe($project->id);
});

it('has many volunteer gear records', function () {
    $item = ProjectGearItem::factory()->create();
    VolunteerGear::factory()->count(2)->create(['project_gear_item_id' => $item->id]);

    expect($item->volunteerGear)->toHaveCount(2);
});

it('casts type to GearItemType enum', function () {
    $item = ProjectGearItem::factory()->create(['type' => 'size_selection']);

    expect($item->type)->toBe(GearItemType::SizeSelection);
});

it('casts requires_size to boolean', function () {
    $item = ProjectGearItem::factory()->create(['requires_size' => true]);

    expect($item->requires_size)->toBeTrue();
});

it('casts available_sizes to array', function () {
    $item = ProjectGearItem::factory()->create(['available_sizes' => ['S', 'M', 'L']]);

    expect($item->available_sizes)->toBe(['S', 'M', 'L']);
});

it('stores null available_sizes when not sized', function () {
    $item = ProjectGearItem::factory()->create(['requires_size' => false, 'available_sizes' => null]);

    expect($item->available_sizes)->toBeNull();
});

it('factory sized state creates sized item with default sizes', function () {
    $item = ProjectGearItem::factory()->sized()->create();

    expect($item->requires_size)->toBeTrue()
        ->and($item->available_sizes)->toBe(['XS', 'S', 'M', 'L', 'XL', 'XXL']);
});

it('factory quantity state sets correct type', function () {
    $item = ProjectGearItem::factory()->quantity()->create();

    expect($item->type)->toBe(GearItemType::Quantity);
});

it('casts available_states to array', function () {
    $item = ProjectGearItem::factory()->create(['available_states' => ['new', 'used']]);

    expect($item->available_states)->toBe(['new', 'used']);
});

it('stores null available_states by default', function () {
    $item = ProjectGearItem::factory()->create();

    expect($item->available_states)->toBeNull();
});
