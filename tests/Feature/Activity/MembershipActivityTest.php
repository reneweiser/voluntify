<?php

use App\Actions\AddProjectMember;
use App\Actions\RemoveProjectMember;
use App\Enums\ActivityCategory;
use App\Enums\ScannerType;
use App\Enums\StaffRole;
use App\Events\Activity\ScannerAssigneeAdded;
use App\Events\Activity\ScannerAssigneeRemoved;
use App\Models\ActivityLog;
use App\Models\Project;
use App\Models\ProjectScanner;
use App\Models\User;

beforeEach(function () {
    ['user' => $this->organizer, 'organization' => $this->org] = createUserWithOrganization(StaffRole::Organizer);
    $this->project = Project::factory()->for($this->org)->create();
});

it('logs activity when a project member is added', function () {
    $newUser = User::factory()->create();

    app(AddProjectMember::class)->execute($this->project, $newUser, $this->organizer);

    $log = ActivityLog::forOrganization($this->org->id)->forProject($this->project->id)->latest('id')->first();

    expect($log)->not->toBeNull()
        ->and($log->action)->toBe('added')
        ->and($log->category)->toBe(ActivityCategory::Member)
        ->and($log->project_id)->toBe($this->project->id)
        ->and($log->causer_id)->toBe($this->organizer->id)
        ->and($log->subject_id)->toBe($newUser->id)
        ->and($log->properties['role'])->toBe('organizer');
});

it('logs activity when a project member is removed', function () {
    $member = User::factory()->create();
    $this->project->users()->attach($member, ['role' => StaffRole::Organizer]);

    app(RemoveProjectMember::class)->execute($this->project, $member, $this->organizer);

    $log = ActivityLog::forOrganization($this->org->id)->forProject($this->project->id)->latest('id')->first();

    expect($log)->not->toBeNull()
        ->and($log->action)->toBe('removed')
        ->and($log->category)->toBe(ActivityCategory::Member)
        ->and($log->project_id)->toBe($this->project->id)
        ->and($log->properties['user_name'])->toBe($member->name);
});

it('logs activity when a scanner assignee is added', function () {
    $scanner = ProjectScanner::factory()->for($this->project)->create(['type' => ScannerType::EntryStaff]);

    ScannerAssigneeAdded::dispatch($scanner, 'staff@example.com', $this->organizer);

    $log = ActivityLog::forProject($this->project->id)->where('action', 'assignee_added')->first();

    expect($log)->not->toBeNull()
        ->and($log->category)->toBe(ActivityCategory::Member)
        ->and($log->properties['email'])->toBe('staff@example.com')
        ->and($log->properties['scanner_name'])->toBe($scanner->name);
});

it('logs activity when a scanner assignee is removed', function () {
    $scanner = ProjectScanner::factory()->for($this->project)->create(['type' => ScannerType::EntryStaff]);

    ScannerAssigneeRemoved::dispatch($scanner, 'staff@example.com', $this->organizer);

    $log = ActivityLog::forProject($this->project->id)->where('action', 'assignee_removed')->first();

    expect($log)->not->toBeNull()
        ->and($log->category)->toBe(ActivityCategory::Member)
        ->and($log->properties['email'])->toBe('staff@example.com');
});

it('filters activity logs by project_id', function () {
    $otherProject = Project::factory()->for($this->org)->create();
    $newUser = User::factory()->create();

    app(AddProjectMember::class)->execute($this->project, $newUser, $this->organizer);

    expect(ActivityLog::forProject($this->project->id)->count())->toBe(1)
        ->and(ActivityLog::forProject($otherProject->id)->count())->toBe(0);
});

it('uses Member category for all membership activities', function () {
    $newUser = User::factory()->create();
    app(AddProjectMember::class)->execute($this->project, $newUser, $this->organizer);

    $logs = ActivityLog::forProject($this->project->id)->get();

    expect($logs->every(fn ($log) => $log->category === ActivityCategory::Member))->toBeTrue();
});
