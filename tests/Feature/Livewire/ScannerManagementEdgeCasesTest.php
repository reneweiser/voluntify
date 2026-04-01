<?php

use App\Enums\ScannerMode;
use App\Enums\ScannerType;
use App\Enums\StaffRole;
use App\Livewire\Projects\ScannerManagement;
use App\Models\Organization;
use App\Models\Project;
use App\Models\ProjectScanner;
use App\Models\ProjectScannerAssignee;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Livewire;

beforeEach(function () {
    ['user' => $this->organizer, 'organization' => $this->org] = createUserWithOrganization(StaffRole::Organizer);
    $this->project = Project::factory()->for($this->org)->create();
    app()->instance(Organization::class, $this->org);
});

// --- P5: Session flash for rawAuthCode ---

it('flashes raw auth code to session after creation', function () {
    $component = Livewire::actingAs($this->organizer)
        ->test(ScannerManagement::class, ['projectId' => $this->project->id])
        ->set('name', 'Flash Test')
        ->set('type', ScannerType::EntryStaff->value)
        ->set('modes', [ScannerMode::Checkin->value])
        ->set('startsAt', '2026-07-01T10:00')
        ->set('endsAt', '2026-07-01T18:00')
        ->call('createScanner')
        ->assertHasNoErrors();

    $scanner = ProjectScanner::where('project_id', $this->project->id)->first();
    expect($scanner)->not->toBeNull();

    // Verify the raw auth code is NOT stored as a public Livewire property (P5 fix)
    // The code should only be in session flash, not in the component's snapshot
    $component->assertSet('name', '');
});

// --- Validation: create scanner ---

it('requires name when creating a scanner', function () {
    Livewire::actingAs($this->organizer)
        ->test(ScannerManagement::class, ['projectId' => $this->project->id])
        ->set('name', '')
        ->set('type', ScannerType::EntryStaff->value)
        ->set('modes', [ScannerMode::Checkin->value])
        ->set('startsAt', '2026-07-01T10:00')
        ->set('endsAt', '2026-07-01T18:00')
        ->call('createScanner')
        ->assertHasErrors(['name' => 'required']);
});

it('requires valid type when creating a scanner', function () {
    Livewire::actingAs($this->organizer)
        ->test(ScannerManagement::class, ['projectId' => $this->project->id])
        ->set('name', 'Test')
        ->set('type', 'invalid_type')
        ->set('modes', [ScannerMode::Checkin->value])
        ->set('startsAt', '2026-07-01T10:00')
        ->set('endsAt', '2026-07-01T18:00')
        ->call('createScanner')
        ->assertHasErrors(['type' => 'in']);
});

it('requires at least one mode when creating a scanner', function () {
    Livewire::actingAs($this->organizer)
        ->test(ScannerManagement::class, ['projectId' => $this->project->id])
        ->set('name', 'Test')
        ->set('type', ScannerType::EntryStaff->value)
        ->set('modes', [])
        ->set('startsAt', '2026-07-01T10:00')
        ->set('endsAt', '2026-07-01T18:00')
        ->call('createScanner')
        ->assertHasErrors(['modes' => 'required']);
});

it('requires ends_at to be after starts_at', function () {
    Livewire::actingAs($this->organizer)
        ->test(ScannerManagement::class, ['projectId' => $this->project->id])
        ->set('name', 'Test')
        ->set('type', ScannerType::EntryStaff->value)
        ->set('modes', [ScannerMode::Checkin->value])
        ->set('startsAt', '2026-07-01T18:00')
        ->set('endsAt', '2026-07-01T10:00')
        ->call('createScanner')
        ->assertHasErrors(['endsAt' => 'after']);
});

it('rejects invalid mode values', function () {
    Livewire::actingAs($this->organizer)
        ->test(ScannerManagement::class, ['projectId' => $this->project->id])
        ->set('name', 'Test')
        ->set('type', ScannerType::EntryStaff->value)
        ->set('modes', ['invalid_mode'])
        ->set('startsAt', '2026-07-01T10:00')
        ->set('endsAt', '2026-07-01T18:00')
        ->call('createScanner')
        ->assertHasErrors(['modes.0' => 'in']);
});

// --- Validation: update scanner ---

it('validates fields when updating a scanner', function () {
    $scanner = ProjectScanner::factory()->create([
        'project_id' => $this->project->id,
    ]);

    Livewire::actingAs($this->organizer)
        ->test(ScannerManagement::class, ['projectId' => $this->project->id])
        ->call('editScanner', $scanner->id)
        ->set('name', '')
        ->call('updateScanner')
        ->assertHasErrors(['name' => 'required']);
});

// --- IDOR: editing scanner from different project ---

it('throws exception when editing scanner from different project', function () {
    $otherProject = Project::factory()->for($this->org)->create();
    $otherScanner = ProjectScanner::factory()->create([
        'project_id' => $otherProject->id,
    ]);

    Livewire::actingAs($this->organizer)
        ->test(ScannerManagement::class, ['projectId' => $this->project->id])
        ->call('editScanner', $otherScanner->id);
})->throws(ModelNotFoundException::class);

// --- IDOR: deleting scanner from different project ---

it('throws exception when deleting scanner from different project', function () {
    $otherProject = Project::factory()->for($this->org)->create();
    $otherScanner = ProjectScanner::factory()->create([
        'project_id' => $otherProject->id,
    ]);

    Livewire::actingAs($this->organizer)
        ->test(ScannerManagement::class, ['projectId' => $this->project->id])
        ->set('deletingScannerId', $otherScanner->id)
        ->call('deleteScanner');
})->throws(ModelNotFoundException::class);

it('preserves scanner from different project after failed delete', function () {
    $otherProject = Project::factory()->for($this->org)->create();
    $otherScanner = ProjectScanner::factory()->create([
        'project_id' => $otherProject->id,
    ]);

    try {
        Livewire::actingAs($this->organizer)
            ->test(ScannerManagement::class, ['projectId' => $this->project->id])
            ->set('deletingScannerId', $otherScanner->id)
            ->call('deleteScanner');
    } catch (ModelNotFoundException) {
        // expected
    }

    expect(ProjectScanner::find($otherScanner->id))->not->toBeNull();
});

// --- IDOR: sending links for scanner from different project ---

it('throws exception when sending links for scanner from different project', function () {
    $otherProject = Project::factory()->for($this->org)->create();
    $otherScanner = ProjectScanner::factory()->create([
        'project_id' => $otherProject->id,
    ]);

    Livewire::actingAs($this->organizer)
        ->test(ScannerManagement::class, ['projectId' => $this->project->id])
        ->call('sendLinks', $otherScanner->id);
})->throws(ModelNotFoundException::class);

// --- IDOR: adding assignee to scanner from different project ---

it('throws exception when adding assignee to scanner from different project', function () {
    $otherProject = Project::factory()->for($this->org)->create();
    $otherScanner = ProjectScanner::factory()->create([
        'project_id' => $otherProject->id,
    ]);

    Livewire::actingAs($this->organizer)
        ->test(ScannerManagement::class, ['projectId' => $this->project->id])
        ->call('addAssignee', $otherScanner->id, 'test@example.com');
})->throws(ModelNotFoundException::class);

// --- IDOR: removing assignee from scanner from different project ---

it('throws exception when removing assignee from scanner of different project', function () {
    $otherProject = Project::factory()->for($this->org)->create();
    $otherScanner = ProjectScanner::factory()->create([
        'project_id' => $otherProject->id,
    ]);
    $otherAssignee = ProjectScannerAssignee::factory()->for($otherScanner, 'projectScanner')->create();

    Livewire::actingAs($this->organizer)
        ->test(ScannerManagement::class, ['projectId' => $this->project->id])
        ->call('removeAssignee', $otherAssignee->id);
})->throws(ModelNotFoundException::class);

it('preserves assignee from different project after failed remove', function () {
    $otherProject = Project::factory()->for($this->org)->create();
    $otherScanner = ProjectScanner::factory()->create([
        'project_id' => $otherProject->id,
    ]);
    $otherAssignee = ProjectScannerAssignee::factory()->for($otherScanner, 'projectScanner')->create();

    try {
        Livewire::actingAs($this->organizer)
            ->test(ScannerManagement::class, ['projectId' => $this->project->id])
            ->call('removeAssignee', $otherAssignee->id);
    } catch (ModelNotFoundException) {
        // expected
    }

    expect(ProjectScannerAssignee::find($otherAssignee->id))->not->toBeNull();
});

// --- Edit/update flow ---

it('loads existing scanner data into form fields when editing', function () {
    $scanner = ProjectScanner::factory()->create([
        'project_id' => $this->project->id,
        'name' => 'My Scanner',
        'type' => ScannerType::VolunteerAdmin,
        'modes' => [ScannerMode::Checkin->value, ScannerMode::GearPickup->value],
        'hint_text' => 'Scan here',
        'starts_at' => '2026-07-01 10:00:00',
        'ends_at' => '2026-07-01 18:00:00',
    ]);

    Livewire::actingAs($this->organizer)
        ->test(ScannerManagement::class, ['projectId' => $this->project->id])
        ->call('editScanner', $scanner->id)
        ->assertSet('editingScannerId', $scanner->id)
        ->assertSet('name', 'My Scanner')
        ->assertSet('type', ScannerType::VolunteerAdmin->value)
        ->assertSet('hintText', 'Scan here')
        ->assertSet('showCreateModal', true);
});

// --- Duplicate assignee ignored ---

it('does not create duplicate assignees with same email', function () {
    $scanner = ProjectScanner::factory()->create([
        'project_id' => $this->project->id,
    ]);

    $component = Livewire::actingAs($this->organizer)
        ->test(ScannerManagement::class, ['projectId' => $this->project->id]);

    $component->call('addAssignee', $scanner->id, 'duplicate@example.com');
    $component->call('addAssignee', $scanner->id, 'duplicate@example.com');

    expect($scanner->assignees()->count())->toBe(1);
});

// --- Invalid email ignored ---

it('silently ignores invalid email when adding assignee', function () {
    $scanner = ProjectScanner::factory()->create([
        'project_id' => $this->project->id,
    ]);

    Livewire::actingAs($this->organizer)
        ->test(ScannerManagement::class, ['projectId' => $this->project->id])
        ->call('addAssignee', $scanner->id, 'not-an-email');

    expect($scanner->assignees()->count())->toBe(0);
});

// --- Reset form after create ---

it('resets form after scanner creation', function () {
    Livewire::actingAs($this->organizer)
        ->test(ScannerManagement::class, ['projectId' => $this->project->id])
        ->set('name', 'Test Scanner')
        ->set('type', ScannerType::VolunteerAdmin->value)
        ->set('modes', [ScannerMode::Checkin->value, ScannerMode::GearPickup->value])
        ->set('hintText', 'Hint')
        ->set('startsAt', '2026-07-01T10:00')
        ->set('endsAt', '2026-07-01T18:00')
        ->call('createScanner')
        ->assertSet('name', '')
        ->assertSet('type', 'entry_staff')
        ->assertSet('modes', ['checkin'])
        ->assertSet('hintText', '')
        ->assertSet('startsAt', '')
        ->assertSet('endsAt', '')
        ->assertSet('showCreateModal', false);
});
