<?php

use App\Actions\InviteMember;
use App\Enums\StaffRole;
use App\Events\Activity\MemberInvited;
use App\Exceptions\MemberAlreadyExistsException;
use App\Models\Organization;
use App\Models\User;
use App\Notifications\AddedToOrganization;
use App\Notifications\StaffInvitation;
use Illuminate\Support\Facades\Event as EventFacade;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();
    EventFacade::fake([MemberInvited::class]);
    $this->org = Organization::factory()->create();
    $this->action = app(InviteMember::class);
});

it('creates a new user and attaches to organization', function () {
    $user = $this->action->execute($this->org, 'Jane Doe', 'jane@example.com', StaffRole::VolunteerAdmin);

    expect($user->exists)->toBeTrue()
        ->and($user->name)->toBe('Jane Doe')
        ->and($user->email)->toBe('jane@example.com')
        ->and($this->org->users()->where('user_id', $user->id)->exists())->toBeTrue();
});

it('sends staff invitation to new user with password', function () {
    $user = $this->action->execute($this->org, 'Jane Doe', 'jane@example.com', StaffRole::VolunteerAdmin);

    Notification::assertSentTo($user, StaffInvitation::class, function ($notification) {
        return $notification->organization->is($this->org)
            && strlen($notification->temporaryPassword) === 16;
    });
});

it('creates personal organization for new user', function () {
    $user = $this->action->execute($this->org, 'Jane Doe', 'jane@example.com', StaffRole::VolunteerAdmin);

    expect($user->personal_organization_id)->not->toBeNull();
    $personalOrg = Organization::find($user->personal_organization_id);
    expect($personalOrg)->not->toBeNull()
        ->and($personalOrg->name)->toBe("Jane Doe's Organization");
});

it('sets must_change_password for new user', function () {
    $user = $this->action->execute($this->org, 'Jane Doe', 'jane@example.com', StaffRole::VolunteerAdmin);

    expect($user->must_change_password)->toBeTrue();
});

it('attaches existing user to organization with role', function () {
    $existing = User::factory()->create(['email' => 'existing@example.com']);

    $user = $this->action->execute($this->org, 'Existing User', 'existing@example.com', StaffRole::EntranceStaff);

    expect($user->id)->toBe($existing->id)
        ->and($this->org->users()->where('user_id', $user->id)->first()->pivot->role)->toBe(StaffRole::EntranceStaff);
});

it('sends added-to-organization notification to existing user', function () {
    $existing = User::factory()->create(['email' => 'existing@example.com']);

    $this->action->execute($this->org, 'Existing User', 'existing@example.com', StaffRole::VolunteerAdmin);

    Notification::assertSentTo($existing, AddedToOrganization::class, function ($notification) {
        return $notification->organization->is($this->org)
            && $notification->role === StaffRole::VolunteerAdmin;
    });
});

it('does not send staff invitation to existing user', function () {
    $existing = User::factory()->create(['email' => 'existing@example.com']);

    $this->action->execute($this->org, 'Existing User', 'existing@example.com', StaffRole::VolunteerAdmin);

    Notification::assertNotSentTo($existing, StaffInvitation::class);
});

it('throws MemberAlreadyExistsException for duplicate member', function () {
    $existing = User::factory()->create();
    $this->org->users()->attach($existing, ['role' => StaffRole::Organizer]);

    expect(fn () => $this->action->execute($this->org, $existing->name, $existing->email, StaffRole::VolunteerAdmin))
        ->toThrow(MemberAlreadyExistsException::class, 'This user is already a member.');
});

it('dispatches MemberInvited event when causer is provided', function () {
    ['user' => $authUser] = createUserWithOrganization(StaffRole::Organizer);

    $this->action->execute($this->org, 'New User', 'new@example.com', StaffRole::VolunteerAdmin, $authUser);

    EventFacade::assertDispatched(MemberInvited::class, function ($e) use ($authUser) {
        return $e->organization->is($this->org)
            && $e->name === 'New User'
            && $e->email === 'new@example.com'
            && $e->role === StaffRole::VolunteerAdmin
            && $e->causer->is($authUser);
    });
});

it('skips event dispatch when causer is null', function () {
    $this->action->execute($this->org, 'New User', 'new@example.com', StaffRole::VolunteerAdmin);

    EventFacade::assertNotDispatched(MemberInvited::class);
});
