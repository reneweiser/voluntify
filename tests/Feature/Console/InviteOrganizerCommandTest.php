<?php

use App\Enums\StaffRole;
use App\Models\Organization;
use App\Models\User;
use App\Notifications\AddedToOrganization;
use App\Notifications\StaffInvitation;
use Illuminate\Support\Facades\Notification;

test('invites new user as organizer', function () {
    Notification::fake();

    $org = Organization::factory()->create(['name' => 'Helpers United', 'slug' => 'helpers-united']);

    $this->artisan('app:invite-organizer', [
        '--name' => 'Jane Doe',
        '--email' => 'jane@example.com',
        '--organization' => 'helpers-united',
    ])->assertSuccessful();

    $user = User::where('email', 'jane@example.com')->first();

    expect($user)->not->toBeNull()
        ->and($user->name)->toBe('Jane Doe')
        ->and($user->must_change_password)->toBeTrue()
        ->and($user->organizations()->where('organization_id', $org->id)->first()->pivot->role)->toBe(StaffRole::Organizer);

    Notification::assertSentTo($user, StaffInvitation::class);
});

test('invites existing user as organizer', function () {
    Notification::fake();

    $org = Organization::factory()->create(['slug' => 'helpers-united']);
    $user = User::factory()->create(['email' => 'existing@example.com']);

    $this->artisan('app:invite-organizer', [
        '--name' => $user->name,
        '--email' => 'existing@example.com',
        '--organization' => 'helpers-united',
    ])->assertSuccessful();

    expect($user->organizations()->where('organization_id', $org->id)->first()->pivot->role)->toBe(StaffRole::Organizer);

    Notification::assertSentTo($user, AddedToOrganization::class);
});

test('fails when organization slug not found', function () {
    $this->artisan('app:invite-organizer', [
        '--name' => 'Jane Doe',
        '--email' => 'jane@example.com',
        '--organization' => 'nonexistent',
    ])->assertFailed()
        ->expectsOutputToContain('Organization not found');
});

test('fails when user is already a member', function () {
    $org = Organization::factory()->create(['slug' => 'helpers-united']);
    $user = User::factory()->create(['email' => 'member@example.com']);
    $org->users()->attach($user, ['role' => StaffRole::Organizer]);

    $this->artisan('app:invite-organizer', [
        '--name' => $user->name,
        '--email' => 'member@example.com',
        '--organization' => 'helpers-united',
    ])->assertFailed()
        ->expectsOutputToContain('already a member');
});

test('fails with invalid email', function () {
    Organization::factory()->create(['slug' => 'helpers-united']);

    $this->artisan('app:invite-organizer', [
        '--name' => 'Jane Doe',
        '--email' => 'not-an-email',
        '--organization' => 'helpers-united',
    ])->assertFailed()
        ->expectsOutputToContain('Invalid email');
});
