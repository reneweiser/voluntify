<?php

use App\Enums\StaffRole;
use App\Livewire\Settings\MemberManagement;
use App\Models\Organization;
use App\Models\User;
use App\Notifications\AddedToOrganization;
use App\Notifications\StaffInvitation;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

beforeEach(function () {
    ['user' => $this->organizer, 'organization' => $this->org] = createUserWithOrganization(StaffRole::Organizer);
    app()->instance(Organization::class, $this->org);
});

it('renders the member management page for organizers', function () {
    $this->actingAs($this->organizer)
        ->get(route('settings.members'))
        ->assertOk()
        ->assertSeeLivewire(MemberManagement::class);
});

it('denies access to non-organizers', function () {
    $user = User::factory()->create();
    $this->org->users()->attach($user, ['role' => StaffRole::VolunteerAdmin]);

    $this->actingAs($user)
        ->get(route('settings.members'))
        ->assertForbidden();
});

it('lists organization members', function () {
    $member = User::factory()->create();
    $this->org->users()->attach($member, ['role' => StaffRole::VolunteerAdmin]);

    Livewire::actingAs($this->organizer)
        ->test(MemberManagement::class)
        ->assertSee($this->organizer->name)
        ->assertSee($member->name);
});

it('updates a member role via model binding', function () {
    $member = User::factory()->create();
    $this->org->users()->attach($member, ['role' => StaffRole::VolunteerAdmin]);

    Livewire::actingAs($this->organizer)
        ->test(MemberManagement::class)
        ->set("memberRoles.{$member->id}", 'entrance_staff');

    expect($this->org->users()->where('user_id', $member->id)->first()->pivot->role)
        ->toBe(StaffRole::EntranceStaff);
});

it('prevents self role change', function () {
    Livewire::actingAs($this->organizer)
        ->test(MemberManagement::class)
        ->call('updateRole', $this->organizer->id, 'volunteer_admin')
        ->assertHasErrors('role');
});

it('removes a member via email confirmation modal', function () {
    $member = User::factory()->create();
    $this->org->users()->attach($member, ['role' => StaffRole::VolunteerAdmin]);

    Livewire::actingAs($this->organizer)
        ->test(MemberManagement::class)
        ->call('confirmRemoveMember', $member->id)
        ->assertSet('showRemoveModal', true)
        ->assertSet('removeMemberId', $member->id)
        ->set('removeConfirmEmail', $member->email)
        ->call('removeMember')
        ->assertSet('showRemoveModal', false);

    expect($this->org->users()->where('user_id', $member->id)->exists())->toBeFalse();
});

it('prevents self removal', function () {
    Livewire::actingAs($this->organizer)
        ->test(MemberManagement::class)
        ->call('confirmRemoveMember', $this->organizer->id)
        ->assertHasErrors('member')
        ->assertSet('showRemoveModal', false);
});

it('rejects removal when email does not match', function () {
    $member = User::factory()->create();
    $this->org->users()->attach($member, ['role' => StaffRole::VolunteerAdmin]);

    Livewire::actingAs($this->organizer)
        ->test(MemberManagement::class)
        ->call('confirmRemoveMember', $member->id)
        ->set('removeConfirmEmail', 'wrong@example.com')
        ->call('removeMember')
        ->assertHasErrors('removeConfirmEmail')
        ->assertSet('showRemoveModal', true);

    expect($this->org->users()->where('user_id', $member->id)->exists())->toBeTrue();
});

it('requires email to confirm removal', function () {
    $member = User::factory()->create();
    $this->org->users()->attach($member, ['role' => StaffRole::VolunteerAdmin]);

    Livewire::actingAs($this->organizer)
        ->test(MemberManagement::class)
        ->call('confirmRemoveMember', $member->id)
        ->set('removeConfirmEmail', '')
        ->call('removeMember')
        ->assertHasErrors('removeConfirmEmail');

    expect($this->org->users()->where('user_id', $member->id)->exists())->toBeTrue();
});

it('invites a new user', function () {
    Notification::fake();

    Livewire::actingAs($this->organizer)
        ->test(MemberManagement::class)
        ->set('inviteName', 'New User')
        ->set('inviteEmail', 'newuser@example.com')
        ->set('inviteRole', 'volunteer_admin')
        ->call('inviteMember')
        ->assertDispatched('member-invited');

    $newUser = User::where('email', 'newuser@example.com')->first();
    expect($newUser)->not->toBeNull()
        ->and($newUser->must_change_password)->toBeTrue()
        ->and($newUser->email_verified_at)->not->toBeNull()
        ->and($this->org->users()->where('user_id', $newUser->id)->exists())->toBeTrue()
        ->and($newUser->organizations)->toHaveCount(2);

    $personalOrg = $newUser->organizations->first(fn ($o) => $o->id !== $this->org->id);
    expect($personalOrg->name)->toBe("New User's Organization");

    Notification::assertSentTo($newUser, StaffInvitation::class);
});

it('prevents duplicate invitations', function () {
    $member = User::factory()->create();
    $this->org->users()->attach($member, ['role' => StaffRole::VolunteerAdmin]);

    Livewire::actingAs($this->organizer)
        ->test(MemberManagement::class)
        ->set('inviteName', $member->name)
        ->set('inviteEmail', $member->email)
        ->set('inviteRole', 'volunteer_admin')
        ->call('inviteMember')
        ->assertHasErrors('inviteEmail');
});

it('attaches an existing user and sends AddedToOrganization notification', function () {
    Notification::fake();

    $existingUser = User::factory()->create(['email' => 'existing@example.com']);

    Livewire::actingAs($this->organizer)
        ->test(MemberManagement::class)
        ->set('inviteName', 'Ignored Name')
        ->set('inviteEmail', 'existing@example.com')
        ->set('inviteRole', 'entrance_staff')
        ->call('inviteMember')
        ->assertDispatched('member-invited');

    expect(User::where('email', 'existing@example.com')->count())->toBe(1)
        ->and($this->org->users()->where('user_id', $existingUser->id)->exists())->toBeTrue()
        ->and($this->org->users()->where('user_id', $existingUser->id)->first()->pivot->role)
        ->toBe(StaffRole::EntranceStaff);

    Notification::assertSentTo($existingUser, AddedToOrganization::class, function ($notification) {
        return $notification->organization->id === $this->org->id
            && $notification->role === StaffRole::EntranceStaff;
    });
    Notification::assertNotSentTo($existingUser, StaffInvitation::class);
});

it('does not send AddedToOrganization when inviting a new user', function () {
    Notification::fake();

    Livewire::actingAs($this->organizer)
        ->test(MemberManagement::class)
        ->set('inviteName', 'Brand New')
        ->set('inviteEmail', 'brandnew@example.com')
        ->set('inviteRole', 'volunteer_admin')
        ->call('inviteMember')
        ->assertDispatched('member-invited');

    $newUser = User::where('email', 'brandnew@example.com')->first();
    Notification::assertSentTo($newUser, StaffInvitation::class);
    Notification::assertNotSentTo($newUser, AddedToOrganization::class);
});

it('validates invite name is required', function () {
    Livewire::actingAs($this->organizer)
        ->test(MemberManagement::class)
        ->set('inviteName', '')
        ->set('inviteEmail', 'valid@example.com')
        ->set('inviteRole', 'volunteer_admin')
        ->call('inviteMember')
        ->assertHasErrors(['inviteName']);
});

it('validates invite email format', function () {
    Livewire::actingAs($this->organizer)
        ->test(MemberManagement::class)
        ->set('inviteName', 'Test')
        ->set('inviteEmail', 'not-an-email')
        ->set('inviteRole', 'volunteer_admin')
        ->call('inviteMember')
        ->assertHasErrors(['inviteEmail']);
});

it('validates invite role must be valid', function () {
    Livewire::actingAs($this->organizer)
        ->test(MemberManagement::class)
        ->set('inviteName', 'Test')
        ->set('inviteEmail', 'test@example.com')
        ->set('inviteRole', 'superadmin')
        ->call('inviteMember')
        ->assertHasErrors(['inviteRole']);
});

it('resets form fields after successful invite', function () {
    Notification::fake();

    Livewire::actingAs($this->organizer)
        ->test(MemberManagement::class)
        ->set('inviteName', 'New User')
        ->set('inviteEmail', 'newuser-reset@example.com')
        ->set('inviteRole', 'entrance_staff')
        ->call('inviteMember')
        ->assertSet('inviteName', '')
        ->assertSet('inviteEmail', '')
        ->assertSet('inviteRole', 'volunteer_admin');
});
