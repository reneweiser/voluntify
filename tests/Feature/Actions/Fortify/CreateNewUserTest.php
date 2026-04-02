<?php

use App\Actions\Fortify\CreateNewUser;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->action = new CreateNewUser;
});

it('creates user with valid input', function () {
    $user = $this->action->create([
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'password' => 'SecureP@ss1',
        'password_confirmation' => 'SecureP@ss1',
    ]);

    expect($user->exists)->toBeTrue()
        ->and($user->name)->toBe('John Doe')
        ->and($user->email)->toBe('john@example.com');
});

it('creates personal organization for new user', function () {
    $user = $this->action->create([
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'password' => 'SecureP@ss1',
        'password_confirmation' => 'SecureP@ss1',
    ]);

    expect($user->personal_organization_id)->not->toBeNull();
    $personalOrg = Organization::find($user->personal_organization_id);
    expect($personalOrg)->not->toBeNull()
        ->and($personalOrg->name)->toBe("John Doe's Organization");
});

it('rejects missing name', function () {
    expect(fn () => $this->action->create([
        'email' => 'john@example.com',
        'password' => 'SecureP@ss1',
        'password_confirmation' => 'SecureP@ss1',
    ]))->toThrow(ValidationException::class);
});

it('rejects duplicate email', function () {
    User::factory()->create(['email' => 'taken@example.com']);

    expect(fn () => $this->action->create([
        'name' => 'John Doe',
        'email' => 'taken@example.com',
        'password' => 'SecureP@ss1',
        'password_confirmation' => 'SecureP@ss1',
    ]))->toThrow(ValidationException::class);
});

it('rejects weak password', function () {
    expect(fn () => $this->action->create([
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'password' => 'short',
        'password_confirmation' => 'short',
    ]))->toThrow(ValidationException::class);
});

it('rejects unconfirmed password', function () {
    expect(fn () => $this->action->create([
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'password' => 'SecureP@ss1',
        'password_confirmation' => 'DifferentP@ss1',
    ]))->toThrow(ValidationException::class);
});

it('hashes the password', function () {
    $user = $this->action->create([
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'password' => 'SecureP@ss1',
        'password_confirmation' => 'SecureP@ss1',
    ]);

    expect($user->password)->not->toBe('SecureP@ss1')
        ->and(Hash::check('SecureP@ss1', $user->password))->toBeTrue();
});
