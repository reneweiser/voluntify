<?php

use App\Enums\ScannerMode;
use App\Enums\ScannerType;
use App\Models\Event;
use App\Models\Project;
use App\Models\ProjectScanner;
use Carbon\Carbon;

// --- Boundary conditions: exact starts_at and ends_at ---

it('is active at exact starts_at time', function () {
    Carbon::setTestNow('2026-07-01 10:00:00');

    $scanner = ProjectScanner::factory()->create([
        'starts_at' => Carbon::parse('2026-07-01 10:00:00'),
        'ends_at' => Carbon::parse('2026-07-01 14:00:00'),
    ]);

    expect($scanner->isActive())->toBeTrue()
        ->and($scanner->status)->toBe('active');

    Carbon::setTestNow();
});

it('is active at exact ends_at time', function () {
    Carbon::setTestNow('2026-07-01 14:00:00');

    $scanner = ProjectScanner::factory()->create([
        'starts_at' => Carbon::parse('2026-07-01 10:00:00'),
        'ends_at' => Carbon::parse('2026-07-01 14:00:00'),
    ]);

    expect($scanner->isActive())->toBeTrue()
        ->and($scanner->status)->toBe('active');

    Carbon::setTestNow();
});

it('is expired one second after ends_at', function () {
    Carbon::setTestNow('2026-07-01 14:00:01');

    $scanner = ProjectScanner::factory()->create([
        'starts_at' => Carbon::parse('2026-07-01 10:00:00'),
        'ends_at' => Carbon::parse('2026-07-01 14:00:00'),
    ]);

    expect($scanner->isActive())->toBeFalse()
        ->and($scanner->isExpired())->toBeTrue()
        ->and($scanner->status)->toBe('expired');

    Carbon::setTestNow();
});

it('is scheduled one second before starts_at', function () {
    Carbon::setTestNow('2026-07-01 09:59:59');

    $scanner = ProjectScanner::factory()->create([
        'starts_at' => Carbon::parse('2026-07-01 10:00:00'),
        'ends_at' => Carbon::parse('2026-07-01 14:00:00'),
    ]);

    expect($scanner->isActive())->toBeFalse()
        ->and($scanner->isExpired())->toBeFalse()
        ->and($scanner->status)->toBe('scheduled');

    Carbon::setTestNow();
});

// --- Scope: scheduled ---

it('scopes scheduled scanners correctly', function () {
    Carbon::setTestNow('2026-07-01 08:00:00');

    ProjectScanner::factory()->create([
        'starts_at' => Carbon::parse('2026-07-01 10:00:00'),
        'ends_at' => Carbon::parse('2026-07-01 14:00:00'),
    ]);

    ProjectScanner::factory()->create([
        'starts_at' => Carbon::parse('2026-07-01 06:00:00'),
        'ends_at' => Carbon::parse('2026-07-01 10:00:00'),
    ]);

    expect(ProjectScanner::scheduled()->count())->toBe(1);

    Carbon::setTestNow();
});

// --- Event relationship ---

it('belongs to an event when event_id is set', function () {
    $project = Project::factory()->create();
    $event = Event::factory()->for($project)->create();
    $scanner = ProjectScanner::factory()->create([
        'project_id' => $project->id,
        'event_id' => $event->id,
    ]);

    expect($scanner->event->id)->toBe($event->id);
});

it('has null event when event_id is null', function () {
    $scanner = ProjectScanner::factory()->create(['event_id' => null]);

    expect($scanner->event)->toBeNull();
});

// --- Type casting ---

it('casts type to ScannerType enum', function () {
    $scanner = ProjectScanner::factory()->create([
        'type' => ScannerType::VolunteerAdmin,
    ]);

    expect($scanner->type)->toBeInstanceOf(ScannerType::class)
        ->and($scanner->type)->toBe(ScannerType::VolunteerAdmin);
});

// --- Modes and gear_item_ids casting ---

it('casts modes as array', function () {
    $scanner = ProjectScanner::factory()->create([
        'modes' => [ScannerMode::Checkin->value, ScannerMode::GearPickup->value],
    ]);

    $fresh = ProjectScanner::find($scanner->id);

    expect($fresh->modes)->toBeArray()
        ->and($fresh->modes)->toBe([ScannerMode::Checkin->value, ScannerMode::GearPickup->value]);
});

it('casts gear_item_ids as array', function () {
    $scanner = ProjectScanner::factory()->create([
        'gear_item_ids' => [10, 20, 30],
    ]);

    $fresh = ProjectScanner::find($scanner->id);

    expect($fresh->gear_item_ids)->toBeArray()
        ->and($fresh->gear_item_ids)->toBe([10, 20, 30]);
});

it('handles null gear_item_ids gracefully', function () {
    $scanner = ProjectScanner::factory()->create([
        'gear_item_ids' => null,
    ]);

    expect($scanner->gear_item_ids)->toBeNull();
});

// --- windowOpensSoon with currently active scanners (should not match) ---

it('does not include active scanners in windowOpensSoon', function () {
    Carbon::setTestNow('2026-07-01 12:00:00');

    ProjectScanner::factory()->active()->create();

    $results = ProjectScanner::windowOpensSoon(30)->get();

    expect($results)->toHaveCount(0);

    Carbon::setTestNow();
});

// --- Multiple active scanners ---

it('returns all active scanners from scope', function () {
    Carbon::setTestNow('2026-07-01 12:00:00');

    ProjectScanner::factory()->count(3)->active()->create();
    ProjectScanner::factory()->expired()->create();
    ProjectScanner::factory()->scheduled()->create();

    expect(ProjectScanner::active()->count())->toBe(3);

    Carbon::setTestNow();
});
