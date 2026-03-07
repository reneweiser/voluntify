<?php

use App\Enums\StaffRole;
use App\Models\Organization;
use App\Models\User;

test('creates admin user and organization with correct attributes', function () {
    $this->artisan('app:create-admin', [
        '--name' => 'Jane Admin',
        '--email' => 'jane@example.com',
        '--password' => 'secret123',
        '--organization' => 'Helpers United',
    ])->assertSuccessful();

    $user = User::where('email', 'jane@example.com')->first();

    expect($user)->not->toBeNull()
        ->and($user->name)->toBe('Jane Admin')
        ->and($user->email_verified_at)->not->toBeNull()
        ->and($user->must_change_password)->toBeFalse();

    $org = Organization::where('name', 'Helpers United')->first();

    expect($org)->not->toBeNull()
        ->and($org->slug)->toBe('helpers-united');

    expect($user->organizations()->first()->pivot->role)->toBe(StaffRole::Organizer);
});

test('rejects duplicate email with error output', function () {
    User::factory()->create(['email' => 'taken@example.com']);

    $this->artisan('app:create-admin', [
        '--name' => 'Duplicate',
        '--email' => 'taken@example.com',
        '--password' => 'secret123',
        '--organization' => 'Some Org',
    ])->assertFailed()
        ->expectsOutputToContain('taken@example.com');
});

test('handles slug collision for organizations with similar names', function () {
    Organization::factory()->create(['name' => 'Test Org', 'slug' => 'test-org']);

    $this->artisan('app:create-admin', [
        '--name' => 'Admin',
        '--email' => 'admin@example.com',
        '--password' => 'secret123',
        '--organization' => 'Test Org',
    ])->assertSuccessful();

    $org = Organization::where('name', 'Test Org')->where('slug', 'test-org-2')->first();

    expect($org)->not->toBeNull();
});
