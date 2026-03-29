<?php

use App\Enums\StaffRole;
use App\Models\Organization;
use App\Models\User;

test('creates first user and organization', function () {
    $this->artisan('app:setup', [
        '--name' => 'Alice Admin',
        '--email' => 'alice@example.com',
        '--password' => 'password123',
        '--org' => 'Community Helpers',
    ])->assertSuccessful();

    $user = User::where('email', 'alice@example.com')->first();

    expect($user)->not->toBeNull()
        ->and($user->name)->toBe('Alice Admin')
        ->and($user->email_verified_at)->not->toBeNull()
        ->and($user->personal_organization_id)->not->toBeNull();

    $org = Organization::where('name', 'Community Helpers')->first();

    expect($org)->not->toBeNull()
        ->and($org->users()->where('user_id', $user->id)->first()->pivot->role)->toBe(StaffRole::Organizer);
});

test('fails when users already exist', function () {
    User::factory()->create();

    $this->artisan('app:setup', [
        '--name' => 'Alice Admin',
        '--email' => 'alice@example.com',
        '--password' => 'password123',
        '--org' => 'Community Helpers',
    ])->assertFailed()
        ->expectsOutputToContain('Users already exist');
});

test('fails with invalid email', function () {
    $this->artisan('app:setup', [
        '--name' => 'Alice Admin',
        '--email' => 'not-an-email',
        '--password' => 'password123',
        '--org' => 'Community Helpers',
    ])->assertFailed()
        ->expectsOutputToContain('Invalid email');
});

test('fails with short password', function () {
    $this->artisan('app:setup', [
        '--name' => 'Alice Admin',
        '--email' => 'alice@example.com',
        '--password' => 'short',
        '--org' => 'Community Helpers',
    ])->assertFailed()
        ->expectsOutputToContain('at least 8 characters');
});
