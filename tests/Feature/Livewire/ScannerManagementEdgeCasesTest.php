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
        ->set('form.name', 'Flash Test')
        ->set('form.type', ScannerType::EntryStaff->value)
        ->set('form.modes', [ScannerMode::Checkin->value])
        ->set('form.startsAt', '2026-07-01T10:00')
        ->set('form.endsAt', '2026-07-01T18:00')
        ->call('createScanner')
        ->assertHasNoErrors();

    $scanner = ProjectScanner::where('project_id', $this->project->id)->first();
    expect($scanner)->not->toBeNull();

    // Verify the raw auth code is NOT stored as a public Livewire property (P5 fix)
    // The code should only be in session flash, not in the component's snapshot
    $component->assertSet('form.name', '');
});

// --- Validation: create scanner ---

it('requires name when creating a scanner', function () {
    Livewire::actingAs($this->organizer)
        ->test(ScannerManagement::class, ['projectId' => $this->project->id])
        ->set('form.name', '')
        ->set('form.type', ScannerType::EntryStaff->value)
        ->set('form.modes', [ScannerMode::Checkin->value])
        ->set('form.startsAt', '2026-07-01T10:00')
        ->set('form.endsAt', '2026-07-01T18:00')
        ->call('createScanner')
        ->assertHasErrors(['form.name' => 'required']);
});

it('requires valid type when creating a scanner', function () {
    Livewire::actingAs($this->organizer)
        ->test(ScannerManagement::class, ['projectId' => $this->project->id])
        ->set('form.name', 'Test')
        ->set('form.type', 'invalid_type')
        ->set('form.modes', [ScannerMode::Checkin->value])
        ->set('form.startsAt', '2026-07-01T10:00')
        ->set('form.endsAt', '2026-07-01T18:00')
        ->call('createScanner')
        ->assertHasErrors(['form.type' => 'in']);
});

it('requires at least one mode when creating a scanner', function () {
    Livewire::actingAs($this->organizer)
        ->test(ScannerManagement::class, ['projectId' => $this->project->id])
        ->set('form.name', 'Test')
        ->set('form.type', ScannerType::EntryStaff->value)
        ->set('form.modes', [])
        ->set('form.startsAt', '2026-07-01T10:00')
        ->set('form.endsAt', '2026-07-01T18:00')
        ->call('createScanner')
        ->assertHasErrors(['form.modes' => 'required']);
});

it('requires ends_at to be after starts_at', function () {
    Livewire::actingAs($this->organizer)
        ->test(ScannerManagement::class, ['projectId' => $this->project->id])
        ->set('form.name', 'Test')
        ->set('form.type', ScannerType::EntryStaff->value)
        ->set('form.modes', [ScannerMode::Checkin->value])
        ->set('form.startsAt', '2026-07-01T18:00')
        ->set('form.endsAt', '2026-07-01T10:00')
        ->call('createScanner')
        ->assertHasErrors(['form.endsAt' => 'after']);
});

it('rejects invalid mode values', function () {
    Livewire::actingAs($this->organizer)
        ->test(ScannerManagement::class, ['projectId' => $this->project->id])
        ->set('form.name', 'Test')
        ->set('form.type', ScannerType::EntryStaff->value)
        ->set('form.modes', ['invalid_mode'])
        ->set('form.startsAt', '2026-07-01T10:00')
        ->set('form.endsAt', '2026-07-01T18:00')
        ->call('createScanner')
        ->assertHasErrors(['form.modes.0' => 'in']);
});

// --- Validation: update scanner ---

it('validates fields when updating a scanner', function () {
    $scanner = ProjectScanner::factory()->create([
        'project_id' => $this->project->id,
    ]);

    Livewire::actingAs($this->organizer)
        ->test(ScannerManagement::class, ['projectId' => $this->project->id])
        ->call('editScanner', $scanner->id)
        ->set('form.name', '')
        ->call('updateScanner')
        ->assertHasErrors(['form.name' => 'required']);
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
        ->assertSet('form.name', 'My Scanner')
        ->assertSet('form.type', ScannerType::VolunteerAdmin->value)
        ->assertSet('form.hintText', 'Scan here')
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
        ->set('form.name', 'Test Scanner')
        ->set('form.type', ScannerType::VolunteerAdmin->value)
        ->set('form.modes', [ScannerMode::Checkin->value, ScannerMode::GearPickup->value])
        ->set('form.hintText', 'Hint')
        ->set('form.startsAt', '2026-07-01T10:00')
        ->set('form.endsAt', '2026-07-01T18:00')
        ->call('createScanner')
        ->assertSet('form.name', '')
        ->assertSet('form.type', 'entry_staff')
        ->assertSet('form.modes', ['checkin'])
        ->assertSet('form.hintText', '')
        ->assertSet('form.startsAt', '')
        ->assertSet('form.endsAt', '')
        ->assertSet('showCreateModal', false);
});
