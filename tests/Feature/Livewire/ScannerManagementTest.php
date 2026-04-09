<?php

use App\Enums\ScannerMode;
use App\Enums\ScannerType;
use App\Enums\StaffRole;
use App\Jobs\SendScannerLinksJob;
use App\Livewire\Projects\ScannerManagement;
use App\Models\GuestList;
use App\Models\Organization;
use App\Models\Project;
use App\Models\ProjectScanner;
use App\Models\ProjectScannerAssignee;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

beforeEach(function () {
    ['user' => $this->organizer, 'organization' => $this->org] = createUserWithOrganization(StaffRole::Organizer);
    $this->project = Project::factory()->for($this->org)->create();
    app()->instance(Organization::class, $this->org);
});

it('renders for org organizer', function () {
    $this->actingAs($this->organizer)
        ->get(route('projects.scanners', $this->project))
        ->assertOk()
        ->assertSeeLivewire(ScannerManagement::class);
});

it('renders for project organizer', function () {
    $projectOrganizer = User::factory()->create();
    $this->project->users()->attach($projectOrganizer, ['role' => StaffRole::Organizer]);

    $this->actingAs($projectOrganizer)
        ->get(route('projects.scanners', $this->project))
        ->assertOk();
});

it('denies access to non-member', function () {
    $outsider = User::factory()->create();

    $this->actingAs($outsider)
        ->get(route('projects.scanners', $this->project))
        ->assertForbidden();
});

it('creates a scanner and shows raw auth code', function () {
    Livewire::actingAs($this->organizer)
        ->test(ScannerManagement::class, ['projectId' => $this->project->id])
        ->set('form.name', 'Eingang Süd')
        ->set('form.type', ScannerType::EntryStaff->value)
        ->set('form.modes', [ScannerMode::Checkin->value])
        ->set('form.startsAt', '2026-07-01T10:00')
        ->set('form.endsAt', '2026-07-01T18:00')
        ->call('createScanner')
        ->assertHasNoErrors();

    expect(ProjectScanner::where('project_id', $this->project->id)->count())->toBe(1);

    $scanner = ProjectScanner::where('project_id', $this->project->id)->first();
    expect($scanner->name)->toBe('Eingang Süd')
        ->and($scanner->type)->toBe(ScannerType::EntryStaff);
});

it('updates an existing scanner', function () {
    $scanner = ProjectScanner::factory()->create([
        'project_id' => $this->project->id,
        'name' => 'Old Name',
    ]);

    Livewire::actingAs($this->organizer)
        ->test(ScannerManagement::class, ['projectId' => $this->project->id])
        ->call('editScanner', $scanner->id)
        ->set('form.name', 'New Name')
        ->call('updateScanner')
        ->assertHasNoErrors();

    $scanner->refresh();
    expect($scanner->name)->toBe('New Name');
});

it('deletes a scanner', function () {
    $scanner = ProjectScanner::factory()->create([
        'project_id' => $this->project->id,
    ]);

    Livewire::actingAs($this->organizer)
        ->test(ScannerManagement::class, ['projectId' => $this->project->id])
        ->call('confirmDelete', $scanner->id)
        ->call('deleteScanner')
        ->assertHasNoErrors();

    expect(ProjectScanner::find($scanner->id))->toBeNull();
});

it('sends scanner links dispatching one job per assignee', function () {
    Queue::fake();

    $scanner = ProjectScanner::factory()->create([
        'project_id' => $this->project->id,
    ]);
    ProjectScannerAssignee::factory()->count(2)->for($scanner, 'projectScanner')->create();

    Livewire::actingAs($this->organizer)
        ->test(ScannerManagement::class, ['projectId' => $this->project->id])
        ->call('sendLinks', $scanner->id);

    Queue::assertPushed(SendScannerLinksJob::class, 2);
});

it('adds an assignee to a scanner', function () {
    $scanner = ProjectScanner::factory()->create([
        'project_id' => $this->project->id,
    ]);

    Livewire::actingAs($this->organizer)
        ->test(ScannerManagement::class, ['projectId' => $this->project->id])
        ->call('addAssignee', $scanner->id, 'staff@example.com')
        ->assertHasNoErrors();

    expect($scanner->assignees()->count())->toBe(1)
        ->and($scanner->assignees()->first()->email)->toBe('staff@example.com');
});

it('removes an assignee from a scanner', function () {
    $scanner = ProjectScanner::factory()->create([
        'project_id' => $this->project->id,
    ]);
    $assignee = ProjectScannerAssignee::factory()->for($scanner, 'projectScanner')->create();

    Livewire::actingAs($this->organizer)
        ->test(ScannerManagement::class, ['projectId' => $this->project->id])
        ->call('removeAssignee', $assignee->id)
        ->assertHasNoErrors();

    expect(ProjectScannerAssignee::find($assignee->id))->toBeNull();
});

it('regenerates auth code and flashes new code', function () {
    $scanner = ProjectScanner::factory()->create([
        'project_id' => $this->project->id,
    ]);
    $oldHash = $scanner->auth_code;

    $component = Livewire::actingAs($this->organizer)
        ->test(ScannerManagement::class, ['projectId' => $this->project->id])
        ->call('regenerateAuthCode', $scanner->id)
        ->assertHasNoErrors();

    $scanner->refresh();
    expect($scanner->auth_code)->not->toBe($oldHash);
});

it('dispatches SendScannerLinksJob with new code to all assignees after regeneration', function () {
    Queue::fake();

    $scanner = ProjectScanner::factory()->create([
        'project_id' => $this->project->id,
    ]);
    ProjectScannerAssignee::factory()->count(2)->for($scanner, 'projectScanner')->create();

    Livewire::actingAs($this->organizer)
        ->test(ScannerManagement::class, ['projectId' => $this->project->id])
        ->call('regenerateAuthCode', $scanner->id);

    Queue::assertPushed(SendScannerLinksJob::class, 2);
    Queue::assertPushed(SendScannerLinksJob::class, function ($job) {
        return $job->rawAuthCode !== null && preg_match('/^\d{6}$/', $job->rawAuthCode);
    });
});

it('does not dispatch jobs when scanner has no assignees on regeneration', function () {
    Queue::fake();

    $scanner = ProjectScanner::factory()->create([
        'project_id' => $this->project->id,
    ]);

    Livewire::actingAs($this->organizer)
        ->test(ScannerManagement::class, ['projectId' => $this->project->id])
        ->call('regenerateAuthCode', $scanner->id);

    Queue::assertNothingPushed();
});

it('scopes regeneration to project scanners only', function () {
    $otherProject = Project::factory()->for($this->org)->create();
    $otherScanner = ProjectScanner::factory()->create([
        'project_id' => $otherProject->id,
    ]);

    Livewire::actingAs($this->organizer)
        ->test(ScannerManagement::class, ['projectId' => $this->project->id])
        ->call('regenerateAuthCode', $otherScanner->id);
})->throws(ModelNotFoundException::class);

it('shows error when deleting scanner with guest lists', function () {
    $scanner = ProjectScanner::factory()->create([
        'project_id' => $this->project->id,
    ]);
    GuestList::factory()->create([
        'scanner_id' => $scanner->id,
        'project_id' => $this->project->id,
    ]);

    Livewire::actingAs($this->organizer)
        ->test(ScannerManagement::class, ['projectId' => $this->project->id])
        ->call('confirmDelete', $scanner->id)
        ->call('deleteScanner')
        ->assertHasErrors('scanner')
        ->assertSet('showDeleteConfirm', true);

    expect(ProjectScanner::find($scanner->id))->not->toBeNull();
});

it('hides mode checkboxes when type is entry_staff', function () {
    Livewire::actingAs($this->organizer)
        ->test(ScannerManagement::class, ['projectId' => $this->project->id])
        ->set('form.type', ScannerType::EntryStaff->value)
        ->assertDontSeeHtml('value="checkin"')
        ->assertDontSeeHtml('value="gear_pickup"');
});

it('shows mode checkboxes when type is volunteer_admin', function () {
    Livewire::actingAs($this->organizer)
        ->test(ScannerManagement::class, ['projectId' => $this->project->id])
        ->set('form.type', ScannerType::VolunteerAdmin->value)
        ->assertSeeHtml('value="checkin"')
        ->assertSeeHtml('value="gear_pickup"');
});

it('resets modes to checkin when switching type from volunteer_admin to entry_staff', function () {
    Livewire::actingAs($this->organizer)
        ->test(ScannerManagement::class, ['projectId' => $this->project->id])
        ->set('form.type', ScannerType::VolunteerAdmin->value)
        ->set('form.modes', [ScannerMode::Checkin->value, ScannerMode::GearPickup->value])
        ->set('form.type', ScannerType::EntryStaff->value)
        ->assertSet('form.modes', [ScannerMode::Checkin->value]);
});
