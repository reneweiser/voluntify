<?php

use App\Enums\ScannerMode;
use App\Enums\ScannerType;
use App\Models\Project;
use App\Models\ProjectScanner;
use App\Models\ProjectScannerAssignee;
use Carbon\Carbon;

it('creates a scanner with correct attributes', function () {
    $scanner = ProjectScanner::factory()->create([
        'name' => 'Eingang Süd',
        'type' => ScannerType::EntryStaff,
        'modes' => [ScannerMode::Checkin->value],
    ]);

    expect($scanner->exists)->toBeTrue()
        ->and($scanner->name)->toBe('Eingang Süd')
        ->and($scanner->type)->toBe(ScannerType::EntryStaff)
        ->and($scanner->modes)->toBe([ScannerMode::Checkin->value]);
});

it('belongs to a project', function () {
    $project = Project::factory()->create();
    $scanner = ProjectScanner::factory()->for($project)->create();

    expect($scanner->project->id)->toBe($project->id);
});

it('has many assignees', function () {
    $scanner = ProjectScanner::factory()->create();
    ProjectScannerAssignee::factory()->count(3)->for($scanner, 'projectScanner')->create();

    expect($scanner->assignees)->toHaveCount(3);
});

it('returns active status when within time window', function () {
    Carbon::setTestNow('2026-07-01 12:00:00');

    $scanner = ProjectScanner::factory()->create([
        'starts_at' => Carbon::parse('2026-07-01 10:00:00'),
        'ends_at' => Carbon::parse('2026-07-01 14:00:00'),
    ]);

    expect($scanner->status)->toBe('active')
        ->and($scanner->isActive())->toBeTrue()
        ->and($scanner->isExpired())->toBeFalse();

    Carbon::setTestNow();
});

it('returns scheduled status before window starts', function () {
    Carbon::setTestNow('2026-07-01 08:00:00');

    $scanner = ProjectScanner::factory()->create([
        'starts_at' => Carbon::parse('2026-07-01 10:00:00'),
        'ends_at' => Carbon::parse('2026-07-01 14:00:00'),
    ]);

    expect($scanner->status)->toBe('scheduled')
        ->and($scanner->isActive())->toBeFalse()
        ->and($scanner->isExpired())->toBeFalse();

    Carbon::setTestNow();
});

it('isScheduled returns true before starts_at', function () {
    Carbon::setTestNow('2026-07-01 08:00:00');

    $scanner = ProjectScanner::factory()->create([
        'starts_at' => Carbon::parse('2026-07-01 10:00:00'),
        'ends_at' => Carbon::parse('2026-07-01 14:00:00'),
    ]);

    expect($scanner->isScheduled())->toBeTrue();

    Carbon::setTestNow();
});

it('isScheduled returns false when active', function () {
    Carbon::setTestNow('2026-07-01 12:00:00');

    $scanner = ProjectScanner::factory()->create([
        'starts_at' => Carbon::parse('2026-07-01 10:00:00'),
        'ends_at' => Carbon::parse('2026-07-01 14:00:00'),
    ]);

    expect($scanner->isScheduled())->toBeFalse();

    Carbon::setTestNow();
});

it('returns expired status after window ends', function () {
    Carbon::setTestNow('2026-07-01 16:00:00');

    $scanner = ProjectScanner::factory()->create([
        'starts_at' => Carbon::parse('2026-07-01 10:00:00'),
        'ends_at' => Carbon::parse('2026-07-01 14:00:00'),
    ]);

    expect($scanner->status)->toBe('expired')
        ->and($scanner->isActive())->toBeFalse()
        ->and($scanner->isExpired())->toBeTrue();

    Carbon::setTestNow();
});

it('checks mode presence correctly', function () {
    $scanner = ProjectScanner::factory()->create([
        'modes' => [ScannerMode::Checkin->value, ScannerMode::GearPickup->value],
    ]);

    expect($scanner->hasMode(ScannerMode::Checkin->value))->toBeTrue()
        ->and($scanner->hasMode(ScannerMode::GearPickup->value))->toBeTrue()
        ->and($scanner->hasMode('nonexistent'))->toBeFalse();
});

it('handles null modes gracefully', function () {
    $scanner = ProjectScanner::factory()->create(['modes' => null]);

    expect($scanner->hasMode(ScannerMode::Checkin->value))->toBeFalse();
});

it('scopes active scanners correctly', function () {
    Carbon::setTestNow('2026-07-01 12:00:00');

    ProjectScanner::factory()->create([
        'starts_at' => Carbon::parse('2026-07-01 10:00:00'),
        'ends_at' => Carbon::parse('2026-07-01 14:00:00'),
    ]);

    ProjectScanner::factory()->create([
        'starts_at' => Carbon::parse('2026-07-01 16:00:00'),
        'ends_at' => Carbon::parse('2026-07-01 20:00:00'),
    ]);

    expect(ProjectScanner::active()->count())->toBe(1);

    Carbon::setTestNow();
});

it('scopes window opens soon correctly', function () {
    Carbon::setTestNow('2026-07-01 09:45:00');

    $soonScanner = ProjectScanner::factory()->create([
        'starts_at' => Carbon::parse('2026-07-01 10:00:00'),
        'ends_at' => Carbon::parse('2026-07-01 14:00:00'),
    ]);

    ProjectScanner::factory()->create([
        'starts_at' => Carbon::parse('2026-07-01 14:00:00'),
        'ends_at' => Carbon::parse('2026-07-01 18:00:00'),
    ]);

    $results = ProjectScanner::windowOpensSoon(30)->get();

    expect($results)->toHaveCount(1)
        ->and($results->first()->id)->toBe($soonScanner->id);

    Carbon::setTestNow();
});

it('hides auth_code and scanner_token in JSON serialization', function () {
    $scanner = ProjectScanner::factory()->create();
    $json = $scanner->toArray();

    expect($json)->not->toHaveKey('auth_code')
        ->and($json)->not->toHaveKey('scanner_token');
});

it('hides email in assignee JSON serialization', function () {
    $assignee = ProjectScannerAssignee::factory()->create();
    $json = $assignee->toArray();

    expect($json)->not->toHaveKey('email');
});
