<?php

use App\Enums\StaffRole;
use App\Livewire\Projects\GuestListIndex;
use App\Models\GuestList;
use App\Models\Organization;
use App\Models\Project;
use App\Models\ProjectScanner;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    ['user' => $this->organizer, 'organization' => $this->org] = createUserWithOrganization(StaffRole::Organizer);
    $this->project = Project::factory()->for($this->org)->create();
    $this->scanner = ProjectScanner::factory()->create(['project_id' => $this->project->id]);
    app()->instance(Organization::class, $this->org);
});

it('renders for org organizer', function () {
    $this->actingAs($this->organizer)
        ->get(route('guest-lists.index', $this->project))
        ->assertOk()
        ->assertSeeLivewire(GuestListIndex::class);
});

it('denies access to non-member', function () {
    $outsider = User::factory()->create();

    $this->actingAs($outsider)
        ->get(route('guest-lists.index', $this->project))
        ->assertForbidden();
});

it('creates guest list and redirects to show page', function () {
    Livewire::actingAs($this->organizer)
        ->test(GuestListIndex::class, ['projectId' => $this->project->id])
        ->set('name', 'VIP Guest List')
        ->set('scannerId', $this->scanner->id)
        ->call('createGuestList')
        ->assertHasNoErrors()
        ->assertRedirect();

    expect(GuestList::where('project_id', $this->project->id)->count())->toBe(1);

    $guestList = GuestList::where('project_id', $this->project->id)->first();
    expect($guestList->name)->toBe('VIP Guest List')
        ->and($guestList->scanner_id)->toBe($this->scanner->id);
});

it('displays guest lists', function () {
    $listA = GuestList::factory()->create([
        'project_id' => $this->project->id,
        'scanner_id' => $this->scanner->id,
        'name' => 'Alpha List',
    ]);
    $listB = GuestList::factory()->create([
        'project_id' => $this->project->id,
        'scanner_id' => $this->scanner->id,
        'name' => 'Beta List',
    ]);

    Livewire::actingAs($this->organizer)
        ->test(GuestListIndex::class, ['projectId' => $this->project->id])
        ->assertSee('Alpha List')
        ->assertSee('Beta List');
});

it('shows empty state when no guest lists', function () {
    Livewire::actingAs($this->organizer)
        ->test(GuestListIndex::class, ['projectId' => $this->project->id])
        ->assertSee('No guest lists');
});

it('validates required name when creating guest list', function () {
    Livewire::actingAs($this->organizer)
        ->test(GuestListIndex::class, ['projectId' => $this->project->id])
        ->set('name', '')
        ->set('scannerId', $this->scanner->id)
        ->call('createGuestList')
        ->assertHasErrors(['name' => 'required']);
});

it('validates required scanner when creating guest list', function () {
    Livewire::actingAs($this->organizer)
        ->test(GuestListIndex::class, ['projectId' => $this->project->id])
        ->set('name', 'Test List')
        ->set('scannerId', null)
        ->call('createGuestList')
        ->assertHasErrors(['scannerId' => 'required']);
});

it('validates scanner belongs to project', function () {
    $otherProject = Project::factory()->for($this->org)->create();
    $otherScanner = ProjectScanner::factory()->create(['project_id' => $otherProject->id]);

    Livewire::actingAs($this->organizer)
        ->test(GuestListIndex::class, ['projectId' => $this->project->id])
        ->set('name', 'Test List')
        ->set('scannerId', $otherScanner->id)
        ->call('createGuestList')
        ->assertHasErrors(['scannerId']);
});

it('deletes a guest list', function () {
    $guestList = GuestList::factory()->create([
        'project_id' => $this->project->id,
        'scanner_id' => $this->scanner->id,
        'name' => 'To Delete',
    ]);

    Livewire::actingAs($this->organizer)
        ->test(GuestListIndex::class, ['projectId' => $this->project->id])
        ->call('deleteGuestList', $guestList->id)
        ->assertHasNoErrors();

    expect(GuestList::find($guestList->id))->toBeNull();
});

it('denies access to volunteer admin role', function () {
    ['user' => $va, 'organization' => $org] = createUserWithOrganization(StaffRole::VolunteerAdmin);
    $project = Project::factory()->for($org)->create();
    app()->instance(Organization::class, $org);

    $this->actingAs($va)
        ->get(route('guest-lists.index', $project))
        ->assertForbidden();
});
