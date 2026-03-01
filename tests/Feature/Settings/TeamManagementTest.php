<?php

use App\Enums\StaffRole;
use App\Livewire\Settings\TeamManagement;
use App\Models\Organization;
use App\Models\User;
use App\Notifications\StaffInvitation;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

beforeEach(function () {
    ['user' => $this->organizer, 'organization' => $this->org] = createUserWithOrganization(StaffRole::Organizer);
    app()->instance(Organization::class, $this->org);
});

it('renders the team management page for organizers', function () {
    $this->actingAs($this->organizer)
        ->get(route('settings.team'))
        ->assertOk()
        ->assertSeeLivewire(TeamManagement::class);
});

it('denies access to non-organizers', function () {
    $user = User::factory()->create();
    $this->org->users()->attach($user, ['role' => StaffRole::VolunteerAdmin]);

    $this->actingAs($user)
        ->get(route('settings.team'))
        ->assertForbidden();
});

it('lists organization members', function () {
    $member = User::factory()->create();
    $this->org->users()->attach($member, ['role' => StaffRole::VolunteerAdmin]);

    Livewire::actingAs($this->organizer)
        ->test(TeamManagement::class)
        ->assertSee($this->organizer->name)
        ->assertSee($member->name);
});

it('updates a member role', function () {
    $member = User::factory()->create();
    $this->org->users()->attach($member, ['role' => StaffRole::VolunteerAdmin]);

    Livewire::actingAs($this->organizer)
        ->test(TeamManagement::class)
        ->call('updateRole', $member->id, 'entrance_staff');

    expect($this->org->users()->where('user_id', $member->id)->first()->pivot->role)
        ->toBe(StaffRole::EntranceStaff);
});

it('prevents self role change', function () {
    Livewire::actingAs($this->organizer)
        ->test(TeamManagement::class)
        ->call('updateRole', $this->organizer->id, 'volunteer_admin')
        ->assertHasErrors('role');
});

it('removes a member', function () {
    $member = User::factory()->create();
    $this->org->users()->attach($member, ['role' => StaffRole::VolunteerAdmin]);

    Livewire::actingAs($this->organizer)
        ->test(TeamManagement::class)
        ->call('removeMember', $member->id);

    expect($this->org->users()->where('user_id', $member->id)->exists())->toBeFalse();
});

it('prevents self removal', function () {
    Livewire::actingAs($this->organizer)
        ->test(TeamManagement::class)
        ->call('removeMember', $this->organizer->id)
        ->assertHasErrors('member');
});

it('invites a new user', function () {
    Notification::fake();

    Livewire::actingAs($this->organizer)
        ->test(TeamManagement::class)
        ->set('inviteName', 'New User')
        ->set('inviteEmail', 'newuser@example.com')
        ->set('inviteRole', 'volunteer_admin')
        ->call('inviteMember')
        ->assertDispatched('member-invited');

    $newUser = User::where('email', 'newuser@example.com')->first();
    expect($newUser)->not->toBeNull()
        ->and($newUser->must_change_password)->toBeTrue()
        ->and($this->org->users()->where('user_id', $newUser->id)->exists())->toBeTrue();

    Notification::assertSentTo($newUser, StaffInvitation::class);
});

it('prevents duplicate invitations', function () {
    $member = User::factory()->create();
    $this->org->users()->attach($member, ['role' => StaffRole::VolunteerAdmin]);

    Livewire::actingAs($this->organizer)
        ->test(TeamManagement::class)
        ->set('inviteName', $member->name)
        ->set('inviteEmail', $member->email)
        ->set('inviteRole', 'volunteer_admin')
        ->call('inviteMember')
        ->assertHasErrors('inviteEmail');
});

it('saves an AI API key', function () {
    Livewire::actingAs($this->organizer)
        ->test(TeamManagement::class)
        ->set('aiApiKey', 'sk-test-1234567890')
        ->call('saveAiApiKey')
        ->assertDispatched('ai-key-saved');

    $this->org->refresh();
    expect($this->org->ai_api_key)->toBe('sk-test-1234567890');
});

it('removes an AI API key', function () {
    $this->org->update(['ai_api_key' => 'sk-test-existing']);

    Livewire::actingAs($this->organizer)
        ->test(TeamManagement::class)
        ->call('removeAiApiKey')
        ->assertDispatched('ai-key-removed');

    $this->org->refresh();
    expect($this->org->ai_api_key)->toBeNull();
});

it('masks the AI API key', function () {
    $this->org->update(['ai_api_key' => 'sk-test-1234567890abcdef']);

    $component = Livewire::actingAs($this->organizer)
        ->test(TeamManagement::class);

    expect($component->get('maskedAiApiKey'))->toContain('sk-test-')
        ->and($component->get('maskedAiApiKey'))->toContain('*');
});
