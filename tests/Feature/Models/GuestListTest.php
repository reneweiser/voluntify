<?php

use App\Enums\GuestListStatus;
use App\Enums\ScannerType;
use App\Models\GuestEntry;
use App\Models\GuestGroup;
use App\Models\GuestList;
use App\Models\Project;
use App\Models\ProjectScanner;

it('creates a guest list with correct attributes', function () {
    $project = Project::factory()->create();
    $scanner = ProjectScanner::factory()->for($project)->create(['type' => ScannerType::EntryStaff]);

    $guestList = GuestList::factory()->create([
        'project_id' => $project->id,
        'scanner_id' => $scanner->id,
        'name' => 'Kuenstler Hauptabend',
        'status' => GuestListStatus::Draft,
        'gear_items' => [1, 2, 3],
    ]);

    expect($guestList->exists)->toBeTrue()
        ->and($guestList->name)->toBe('Kuenstler Hauptabend')
        ->and($guestList->status)->toBe(GuestListStatus::Draft)
        ->and($guestList->gear_items)->toBe([1, 2, 3])
        ->and($guestList->confirmed_at)->toBeNull();
});

it('belongs to a project', function () {
    $project = Project::factory()->create();
    $guestList = GuestList::factory()->create(['project_id' => $project->id]);

    expect($guestList->project->id)->toBe($project->id);
});

it('belongs to a scanner', function () {
    $scanner = ProjectScanner::factory()->create();
    $guestList = GuestList::factory()->create(['scanner_id' => $scanner->id]);

    expect($guestList->scanner->id)->toBe($scanner->id);
});

it('has many groups', function () {
    $guestList = GuestList::factory()->create();
    GuestGroup::factory()->count(3)->for($guestList)->create();

    expect($guestList->groups)->toHaveCount(3);
});

it('has many entries through groups', function () {
    $guestList = GuestList::factory()->create();
    $group = GuestGroup::factory()->for($guestList)->create();
    GuestEntry::factory()->count(2)->for($group, 'group')->create();

    expect($guestList->entries)->toHaveCount(2);
});

it('scopes confirmed guest lists', function () {
    GuestList::factory()->create(['status' => GuestListStatus::Draft]);
    GuestList::factory()->confirmed()->create();

    expect(GuestList::confirmed()->count())->toBe(1);
});

it('scopes by project', function () {
    $project = Project::factory()->create();
    GuestList::factory()->create(['project_id' => $project->id]);
    GuestList::factory()->create(); // different project

    expect(GuestList::forProject($project->id)->count())->toBe(1);
});

it('reports confirmed and draft status correctly', function () {
    $draft = GuestList::factory()->create(['status' => GuestListStatus::Draft]);
    $confirmed = GuestList::factory()->confirmed()->create();

    expect($draft->isDraft())->toBeTrue()
        ->and($draft->isConfirmed())->toBeFalse()
        ->and($confirmed->isDraft())->toBeFalse()
        ->and($confirmed->isConfirmed())->toBeTrue();
});
