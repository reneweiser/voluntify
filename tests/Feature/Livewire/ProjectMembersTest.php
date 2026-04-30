<?php

use App\Enums\StaffRole;
use App\Livewire\Projects\ProjectMembers;
use App\Models\Organization;
use App\Models\Project;
use App\Models\User;
use App\Models\Volunteer;
use App\Notifications\StaffInvitation;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

beforeEach(function () {
    ['user' => $this->organizer, 'organization' => $this->org] = createUserWithOrganization(StaffRole::Organizer);
    $this->project = Project::factory()->for($this->org)->create();
    app()->instance(Organization::class, $this->org);
});

it('renders for org organizer', function () {
    $this->actingAs($this->organizer)
        ->get(route('projects.members', $this->project))
        ->assertOk()
        ->assertSeeLivewire(ProjectMembers::class);
});

it('denies access to project organizer', function () {
    $projectOrganizer = User::factory()->create();
    $this->project->users()->attach($projectOrganizer, ['role' => StaffRole::Organizer]);

    $this->actingAs($projectOrganizer)
        ->get(route('projects.members', $this->project))
        ->assertForbidden();
});

it('denies access to non-member', function () {
    $outsider = User::factory()->create();

    $this->actingAs($outsider)
        ->get(route('projects.members', $this->project))
        ->assertForbidden();
});

it('lists project members', function () {
    $member = User::factory()->create(['name' => 'Alice Member']);
    $this->project->users()->attach($member, ['role' => StaffRole::Organizer]);

    Livewire::actingAs($this->organizer)
        ->test(ProjectMembers::class, ['projectId' => $this->project->id])
        ->assertSee('Alice Member');
});

it('shows inherited org organizers', function () {
    Livewire::actingAs($this->organizer)
        ->test(ProjectMembers::class, ['projectId' => $this->project->id])
        ->assertSee($this->organizer->name)
        ->assertSee('Organizer (inherited)');
});

it('adds a project member by email', function () {
    $user = User::factory()->create(['email' => 'newmember@example.com']);

    Livewire::actingAs($this->organizer)
        ->test(ProjectMembers::class, ['projectId' => $this->project->id])
        ->set('inviteEmail', 'newmember@example.com')
        ->call('inviteMember')
        ->assertHasNoErrors()
        ->assertDispatched('member-added');

    expect($this->project->users()->where('user_id', $user->id)->exists())->toBeTrue();
});

it('invites a volunteer email by creating a project member user', function () {
    Notification::fake();

    $volunteer = Volunteer::factory()->for($this->project)->create([
        'first_name' => 'Taylor',
        'last_name' => 'Volunteer',
        'email' => 'volunteer@example.com',
    ]);

    Livewire::actingAs($this->organizer)
        ->test(ProjectMembers::class, ['projectId' => $this->project->id])
        ->set('inviteEmail', $volunteer->email)
        ->call('inviteMember')
        ->assertHasNoErrors()
        ->assertDispatched('member-added');

    $newUser = User::where('email', $volunteer->email)->first();

    expect($newUser)->not->toBeNull()
        ->and($newUser->name)->toBe($volunteer->full_name)
        ->and($newUser->must_change_password)->toBeTrue()
        ->and($newUser->email_verified_at)->not->toBeNull()
        ->and($this->project->users()->where('user_id', $newUser->id)->exists())->toBeTrue()
        ->and($this->org->users()->where('user_id', $newUser->id)->exists())->toBeFalse();

    Notification::assertSentTo($newUser, StaffInvitation::class);
});

it('shows error when user is already a member', function () {
    $user = User::factory()->create(['email' => 'existing@example.com']);
    $this->project->users()->attach($user, ['role' => StaffRole::Organizer]);

    Livewire::actingAs($this->organizer)
        ->test(ProjectMembers::class, ['projectId' => $this->project->id])
        ->set('inviteEmail', 'existing@example.com')
        ->call('inviteMember')
        ->assertHasErrors('inviteEmail');
});

it('shows error when user is an org organizer', function () {
    Livewire::actingAs($this->organizer)
        ->test(ProjectMembers::class, ['projectId' => $this->project->id])
        ->set('inviteEmail', $this->organizer->email)
        ->call('inviteMember')
        ->assertHasErrors('inviteEmail');
});

it('removes a project member', function () {
    $member = User::factory()->create();
    $this->project->users()->attach($member, ['role' => StaffRole::Organizer]);

    Livewire::actingAs($this->organizer)
        ->test(ProjectMembers::class, ['projectId' => $this->project->id])
        ->call('confirmRemoveMember', $member->id)
        ->assertSet('showRemoveModal', true)
        ->call('removeMember')
        ->assertSet('showRemoveModal', false);

    expect($this->project->users()->where('user_id', $member->id)->exists())->toBeFalse();
});

it('validates email is required', function () {
    Livewire::actingAs($this->organizer)
        ->test(ProjectMembers::class, ['projectId' => $this->project->id])
        ->set('inviteEmail', '')
        ->call('inviteMember')
        ->assertHasErrors('inviteEmail');
});
