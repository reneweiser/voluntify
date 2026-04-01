<?php

use App\Actions\UpdateProjectScanner;
use App\Enums\ScannerMode;
use App\Enums\ScannerType;
use App\Models\Event;
use App\Models\Project;
use App\Models\ProjectScanner;

beforeEach(function () {
    $this->project = Project::factory()->create();
    $this->scanner = ProjectScanner::factory()->create([
        'project_id' => $this->project->id,
        'name' => 'Original Name',
        'type' => ScannerType::EntryStaff,
        'modes' => [ScannerMode::Checkin->value],
        'hint_text' => 'Original hint',
        'starts_at' => '2026-07-01 10:00:00',
        'ends_at' => '2026-07-01 18:00:00',
    ]);
});

it('updates name and preserves other fields', function () {
    $action = new UpdateProjectScanner;

    $result = $action->execute($this->scanner, [
        'name' => 'Updated Name',
    ]);

    expect($result->name)->toBe('Updated Name')
        ->and($result->type)->toBe(ScannerType::EntryStaff)
        ->and($result->modes)->toBe([ScannerMode::Checkin->value]);
});

it('updates type and modes together', function () {
    $action = new UpdateProjectScanner;

    $result = $action->execute($this->scanner, [
        'type' => ScannerType::VolunteerAdmin->value,
        'modes' => [ScannerMode::Checkin->value, ScannerMode::GearPickup->value],
    ]);

    expect($result->type)->toBe(ScannerType::VolunteerAdmin)
        ->and($result->modes)->toBe([ScannerMode::Checkin->value, ScannerMode::GearPickup->value]);
});

it('updates time window', function () {
    $action = new UpdateProjectScanner;

    $result = $action->execute($this->scanner, [
        'starts_at' => '2026-08-01 08:00:00',
        'ends_at' => '2026-08-01 20:00:00',
    ]);

    expect($result->starts_at->format('Y-m-d H:i:s'))->toBe('2026-08-01 08:00:00')
        ->and($result->ends_at->format('Y-m-d H:i:s'))->toBe('2026-08-01 20:00:00');
});

it('clears nullable fields when explicitly set to null', function () {
    $event = Event::factory()->for($this->project)->create();

    $this->scanner->update([
        'event_id' => $event->id,
        'gear_item_ids' => [1, 2],
        'hint_text' => 'Some hint',
    ]);

    $action = new UpdateProjectScanner;

    $result = $action->execute($this->scanner, [
        'event_id' => null,
        'gear_item_ids' => null,
        'hint_text' => null,
    ]);

    expect($result->event_id)->toBeNull()
        ->and($result->gear_item_ids)->toBeNull()
        ->and($result->hint_text)->toBeNull();
});

it('sets event_id when provided', function () {
    $event = Event::factory()->for($this->project)->create();
    $action = new UpdateProjectScanner;

    $result = $action->execute($this->scanner, [
        'event_id' => $event->id,
    ]);

    expect($result->event_id)->toBe($event->id);
});

it('persists changes to database', function () {
    $action = new UpdateProjectScanner;

    $action->execute($this->scanner, [
        'name' => 'Persisted Name',
        'hint_text' => 'New hint',
    ]);

    $fresh = ProjectScanner::find($this->scanner->id);

    expect($fresh->name)->toBe('Persisted Name')
        ->and($fresh->hint_text)->toBe('New hint');
});

it('does not modify auth_code or scanner_token', function () {
    $originalAuthCode = $this->scanner->auth_code;
    $originalToken = $this->scanner->scanner_token;

    $action = new UpdateProjectScanner;
    $action->execute($this->scanner, [
        'name' => 'Changed Name',
    ]);

    $fresh = ProjectScanner::find($this->scanner->id);

    expect($fresh->auth_code)->toBe($originalAuthCode)
        ->and($fresh->scanner_token)->toBe($originalToken);
});
