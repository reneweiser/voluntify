<?php

use App\Actions\CreateAdminWithOrganization;
use App\Enums\StaffRole;
use App\Exceptions\DomainException;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->action = new CreateAdminWithOrganization;
});

it('creates user and organization with organizer role', function () {
    $user = $this->action->execute('Admin User', 'admin@example.com', 'SecureP@ss1', 'Test Org');

    expect($user->exists)->toBeTrue()
        ->and($user->name)->toBe('Admin User')
        ->and($user->email)->toBe('admin@example.com');

    $org = Organization::where('name', 'Test Org')->first();
    expect($org)->not->toBeNull()
        ->and($org->users()->where('user_id', $user->id)->first()->pivot->role)->toBe(StaffRole::Organizer);
});

it('sets email as verified', function () {
    $user = $this->action->execute('Admin User', 'admin@example.com', 'SecureP@ss1', 'Test Org');

    expect($user->email_verified_at)->not->toBeNull();
});

it('does not require password change', function () {
    $user = $this->action->execute('Admin User', 'admin@example.com', 'SecureP@ss1', 'Test Org');

    expect($user->must_change_password)->toBeFalse();
});

it('generates unique slug for organization', function () {
    Organization::factory()->create(['slug' => 'test-org']);

    $this->action->execute('Admin User', 'admin@example.com', 'SecureP@ss1', 'Test Org');

    $org = Organization::where('name', 'Test Org')->first();
    expect($org->slug)->not->toBeEmpty()
        ->and($org->slug)->not->toBe('test-org');
});

it('throws DomainException for duplicate email', function () {
    User::factory()->create(['email' => 'existing@example.com']);

    expect(fn () => $this->action->execute('Admin User', 'existing@example.com', 'SecureP@ss1', 'Test Org'))
        ->toThrow(DomainException::class, 'A user with email [existing@example.com] already exists.');
});

it('hashes the password', function () {
    $user = $this->action->execute('Admin User', 'admin@example.com', 'SecureP@ss1', 'Test Org');

    expect(Hash::check('SecureP@ss1', $user->password))->toBeTrue()
        ->and($user->password)->not->toBe('SecureP@ss1');
});
