<?php

use App\Enums\GuestListStatus;
use App\Enums\StaffRole;
use App\Jobs\ConfirmGuestListJob;
use App\Jobs\SendGuestInvitationsJob;
use App\Livewire\Projects\GuestListShow;
use App\Models\GuestEntry;
use App\Models\GuestGroup;
use App\Models\GuestList;
use App\Models\Organization;
use App\Models\Project;
use App\Models\ProjectScanner;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

beforeEach(function () {
    ['user' => $this->organizer, 'organization' => $this->org] = createUserWithOrganization(StaffRole::Organizer);
    $this->project = Project::factory()->for($this->org)->create();
    $this->scanner = ProjectScanner::factory()->create(['project_id' => $this->project->id]);
    $this->guestList = GuestList::factory()->create([
        'project_id' => $this->project->id,
        'scanner_id' => $this->scanner->id,
    ]);
    app()->instance(Organization::class, $this->org);
});

it('renders guest list with groups and entries', function () {
    $group = GuestGroup::factory()->create([
        'guest_list_id' => $this->guestList->id,
        'label' => 'DJ Soundwave',
        'guest_count' => 2,
    ]);
    GuestEntry::factory()->create(['guest_group_id' => $group->id, 'number' => 1]);
    GuestEntry::factory()->create(['guest_group_id' => $group->id, 'number' => 2]);

    Livewire::actingAs($this->organizer)
        ->test(GuestListShow::class, [
            'projectId' => $this->project->id,
            'guestListId' => $this->guestList->id,
        ])
        ->assertSee($this->guestList->name)
        ->assertSee('DJ Soundwave');
});

it('adds a group', function () {
    Livewire::actingAs($this->organizer)
        ->test(GuestListShow::class, [
            'projectId' => $this->project->id,
            'guestListId' => $this->guestList->id,
        ])
        ->set('newGroupLabel', 'Band Members')
        ->set('newGroupCount', 3)
        ->call('addGroup')
        ->assertHasNoErrors();

    $group = GuestGroup::where('guest_list_id', $this->guestList->id)->first();
    expect($group)->not->toBeNull()
        ->and($group->label)->toBe('Band Members')
        ->and($group->guest_count)->toBe(3)
        ->and($group->entries()->count())->toBe(3);
});

it('removes a group', function () {
    $group = GuestGroup::factory()->create([
        'guest_list_id' => $this->guestList->id,
        'label' => 'To Remove',
    ]);

    Livewire::actingAs($this->organizer)
        ->test(GuestListShow::class, [
            'projectId' => $this->project->id,
            'guestListId' => $this->guestList->id,
        ])
        ->call('removeGroup', $group->id)
        ->assertHasNoErrors();

    expect(GuestGroup::find($group->id))->toBeNull();
});

it('adds entry to group', function () {
    $group = GuestGroup::factory()->create([
        'guest_list_id' => $this->guestList->id,
        'guest_count' => 1,
    ]);
    GuestEntry::factory()->create(['guest_group_id' => $group->id, 'number' => 1]);

    Livewire::actingAs($this->organizer)
        ->test(GuestListShow::class, [
            'projectId' => $this->project->id,
            'guestListId' => $this->guestList->id,
        ])
        ->call('addEntry', $group->id)
        ->assertHasNoErrors();

    expect($group->entries()->count())->toBe(2)
        ->and($group->entries()->orderByDesc('number')->first()->number)->toBe(2);
});

it('removes an entry', function () {
    $group = GuestGroup::factory()->create([
        'guest_list_id' => $this->guestList->id,
    ]);
    $entry = GuestEntry::factory()->create(['guest_group_id' => $group->id, 'number' => 1]);

    Livewire::actingAs($this->organizer)
        ->test(GuestListShow::class, [
            'projectId' => $this->project->id,
            'guestListId' => $this->guestList->id,
        ])
        ->call('removeEntry', $entry->id)
        ->assertHasNoErrors();

    expect(GuestEntry::find($entry->id))->toBeNull();
});

it('updates entry name and email', function () {
    $group = GuestGroup::factory()->create([
        'guest_list_id' => $this->guestList->id,
    ]);
    $entry = GuestEntry::factory()->create([
        'guest_group_id' => $group->id,
        'number' => 1,
        'name' => 'Old Name',
        'email' => 'old@example.com',
    ]);

    Livewire::actingAs($this->organizer)
        ->test(GuestListShow::class, [
            'projectId' => $this->project->id,
            'guestListId' => $this->guestList->id,
        ])
        ->call('startEditEntry', $entry->id)
        ->set('entryName', 'New Name')
        ->set('entryEmail', 'new@example.com')
        ->call('saveEntry')
        ->assertHasNoErrors();

    $entry->refresh();
    expect($entry->name)->toBe('New Name')
        ->and($entry->email)->toBe('new@example.com');
});

it('confirms guest list and dispatches job', function () {
    Queue::fake();

    Livewire::actingAs($this->organizer)
        ->test(GuestListShow::class, [
            'projectId' => $this->project->id,
            'guestListId' => $this->guestList->id,
        ])
        ->call('confirmGuestList')
        ->assertHasNoErrors();

    $this->guestList->refresh();
    expect($this->guestList->status)->toBe(GuestListStatus::Confirmed)
        ->and($this->guestList->confirmed_at)->not->toBeNull();

    Queue::assertPushed(ConfirmGuestListJob::class);
});

it('rejects confirming already-confirmed list', function () {
    Queue::fake();

    $confirmedAt = now()->subHour();
    $this->guestList->update([
        'status' => GuestListStatus::Confirmed,
        'confirmed_at' => $confirmedAt,
    ]);

    Livewire::actingAs($this->organizer)
        ->test(GuestListShow::class, [
            'projectId' => $this->project->id,
            'guestListId' => $this->guestList->id,
        ])
        ->call('confirmGuestList')
        ->assertSee('Guest list is already confirmed.');

    // Confirm no additional job was dispatched
    Queue::assertNotPushed(ConfirmGuestListJob::class);
});

it('updates guest list name and scanner', function () {
    $newScanner = ProjectScanner::factory()->create(['project_id' => $this->project->id]);

    Livewire::actingAs($this->organizer)
        ->test(GuestListShow::class, [
            'projectId' => $this->project->id,
            'guestListId' => $this->guestList->id,
        ])
        ->call('openEditModal')
        ->set('editName', 'Updated Name')
        ->set('editScannerId', $newScanner->id)
        ->call('updateGuestList')
        ->assertHasNoErrors();

    $this->guestList->refresh();
    expect($this->guestList->name)->toBe('Updated Name')
        ->and($this->guestList->scanner_id)->toBe($newScanner->id);
});

it('validates required fields when updating guest list', function () {
    Livewire::actingAs($this->organizer)
        ->test(GuestListShow::class, [
            'projectId' => $this->project->id,
            'guestListId' => $this->guestList->id,
        ])
        ->call('openEditModal')
        ->set('editName', '')
        ->call('updateGuestList')
        ->assertHasErrors(['editName' => 'required']);
});

it('validates group label is required', function () {
    Livewire::actingAs($this->organizer)
        ->test(GuestListShow::class, [
            'projectId' => $this->project->id,
            'guestListId' => $this->guestList->id,
        ])
        ->set('newGroupLabel', '')
        ->set('newGroupCount', 2)
        ->call('addGroup')
        ->assertHasErrors(['newGroupLabel' => 'required']);
});

it('validates group count minimum', function () {
    Livewire::actingAs($this->organizer)
        ->test(GuestListShow::class, [
            'projectId' => $this->project->id,
            'guestListId' => $this->guestList->id,
        ])
        ->set('newGroupLabel', 'Test Group')
        ->set('newGroupCount', 0)
        ->call('addGroup')
        ->assertHasErrors(['newGroupCount' => 'min']);
});

it('cancels entry editing', function () {
    $group = GuestGroup::factory()->create([
        'guest_list_id' => $this->guestList->id,
    ]);
    $entry = GuestEntry::factory()->create([
        'guest_group_id' => $group->id,
        'number' => 1,
        'name' => 'Test Name',
    ]);

    Livewire::actingAs($this->organizer)
        ->test(GuestListShow::class, [
            'projectId' => $this->project->id,
            'guestListId' => $this->guestList->id,
        ])
        ->call('startEditEntry', $entry->id)
        ->assertSet('editingEntryId', $entry->id)
        ->call('cancelEditEntry')
        ->assertSet('editingEntryId', null)
        ->assertSet('entryName', '')
        ->assertSet('entryEmail', '');
});

it('denies access to non-member', function () {
    $outsider = User::factory()->create();

    $this->actingAs($outsider)
        ->get(route('guest-lists.show', [
            'projectId' => $this->project->id,
            'guestListId' => $this->guestList->id,
        ]))
        ->assertForbidden();
});

it('prevents removing group from another guest list', function () {
    $otherList = GuestList::factory()->create([
        'project_id' => $this->project->id,
        'scanner_id' => $this->scanner->id,
    ]);
    $otherGroup = GuestGroup::factory()->for($otherList)->create();

    $this->expectException(ModelNotFoundException::class);

    Livewire::actingAs($this->organizer)
        ->test(GuestListShow::class, [
            'projectId' => $this->project->id,
            'guestListId' => $this->guestList->id,
        ])
        ->call('removeGroup', $otherGroup->id);
});

it('prevents removing entry from another guest list', function () {
    $otherList = GuestList::factory()->create([
        'project_id' => $this->project->id,
        'scanner_id' => $this->scanner->id,
    ]);
    $otherGroup = GuestGroup::factory()->for($otherList)->create();
    $otherEntry = GuestEntry::factory()->create(['guest_group_id' => $otherGroup->id]);

    $this->expectException(ModelNotFoundException::class);

    Livewire::actingAs($this->organizer)
        ->test(GuestListShow::class, [
            'projectId' => $this->project->id,
            'guestListId' => $this->guestList->id,
        ])
        ->call('removeEntry', $otherEntry->id);
});

it('shows send pending invitations button for confirmed list with unsent entries', function () {
    $this->guestList->update([
        'status' => GuestListStatus::Confirmed,
        'confirmed_at' => now(),
    ]);

    $group = GuestGroup::factory()->create(['guest_list_id' => $this->guestList->id]);
    GuestEntry::factory()->withQrToken()->create([
        'guest_group_id' => $group->id,
        'email' => 'pending@example.com',
        'invitation_sent_at' => null,
    ]);

    Livewire::actingAs($this->organizer)
        ->test(GuestListShow::class, [
            'projectId' => $this->project->id,
            'guestListId' => $this->guestList->id,
        ])
        ->assertSee('Send Pending Invitations');
});

it('does not show send pending invitations button for draft list', function () {
    Livewire::actingAs($this->organizer)
        ->test(GuestListShow::class, [
            'projectId' => $this->project->id,
            'guestListId' => $this->guestList->id,
        ])
        ->assertDontSee('Send Pending Invitations');
});

it('does not show send pending invitations button when all invitations already sent', function () {
    $this->guestList->update([
        'status' => GuestListStatus::Confirmed,
        'confirmed_at' => now(),
    ]);

    $group = GuestGroup::factory()->create(['guest_list_id' => $this->guestList->id]);
    GuestEntry::factory()->withQrToken()->create([
        'guest_group_id' => $group->id,
        'email' => 'sent@example.com',
        'invitation_sent_at' => now(),
    ]);

    Livewire::actingAs($this->organizer)
        ->test(GuestListShow::class, [
            'projectId' => $this->project->id,
            'guestListId' => $this->guestList->id,
        ])
        ->assertDontSee('Send Pending Invitations');
});

it('dispatches invitation jobs only for unsent entries when sending pending invitations', function () {
    Queue::fake();

    $this->guestList->update([
        'status' => GuestListStatus::Confirmed,
        'confirmed_at' => now(),
    ]);

    $group = GuestGroup::factory()->create(['guest_list_id' => $this->guestList->id]);

    // Unsent entries with email + qr_token
    GuestEntry::factory()->withQrToken()->create([
        'guest_group_id' => $group->id,
        'email' => 'new1@example.com',
        'invitation_sent_at' => null,
    ]);
    GuestEntry::factory()->withQrToken()->create([
        'guest_group_id' => $group->id,
        'email' => 'new1@example.com', // duplicate email, should dispatch once
        'invitation_sent_at' => null,
    ]);
    GuestEntry::factory()->withQrToken()->create([
        'guest_group_id' => $group->id,
        'email' => 'new2@example.com',
        'invitation_sent_at' => null,
    ]);

    // Already sent — should NOT be re-dispatched
    GuestEntry::factory()->withQrToken()->create([
        'guest_group_id' => $group->id,
        'email' => 'already@example.com',
        'invitation_sent_at' => now(),
    ]);

    // No email — should be skipped
    GuestEntry::factory()->withQrToken()->create([
        'guest_group_id' => $group->id,
        'email' => null,
        'invitation_sent_at' => null,
    ]);

    Livewire::actingAs($this->organizer)
        ->test(GuestListShow::class, [
            'projectId' => $this->project->id,
            'guestListId' => $this->guestList->id,
        ])
        ->call('sendPendingInvitations')
        ->assertHasNoErrors();

    Queue::assertPushed(SendGuestInvitationsJob::class, 2); // 2 unique unsent emails

    // Verify invitation_sent_at was set
    expect(GuestEntry::where('guest_group_id', $group->id)
        ->whereNotNull('email')
        ->whereNull('invitation_sent_at')
        ->count())->toBe(0);
});
