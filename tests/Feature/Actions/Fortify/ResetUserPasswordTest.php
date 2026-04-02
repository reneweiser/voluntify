<?php

use App\Actions\Fortify\ResetUserPassword;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->action = new ResetUserPassword;
    $this->user = User::factory()->create();
});

it('resets password with valid input', function () {
    $this->action->reset($this->user, [
        'password' => 'NewSecureP@ss1',
        'password_confirmation' => 'NewSecureP@ss1',
    ]);

    $this->user->refresh();

    expect(Hash::check('NewSecureP@ss1', $this->user->password))->toBeTrue();
});

it('hashes the new password', function () {
    $this->action->reset($this->user, [
        'password' => 'NewSecureP@ss1',
        'password_confirmation' => 'NewSecureP@ss1',
    ]);

    $this->user->refresh();

    expect($this->user->password)->not->toBe('NewSecureP@ss1');
});

it('rejects unconfirmed password', function () {
    expect(fn () => $this->action->reset($this->user, [
        'password' => 'NewSecureP@ss1',
        'password_confirmation' => 'DifferentP@ss1',
    ]))->toThrow(ValidationException::class);
});

it('rejects weak password', function () {
    expect(fn () => $this->action->reset($this->user, [
        'password' => 'short',
        'password_confirmation' => 'short',
    ]))->toThrow(ValidationException::class);
});

it('does not modify other user attributes', function () {
    $originalName = $this->user->name;
    $originalEmail = $this->user->email;

    $this->action->reset($this->user, [
        'password' => 'NewSecureP@ss1',
        'password_confirmation' => 'NewSecureP@ss1',
    ]);

    $this->user->refresh();

    expect($this->user->name)->toBe($originalName)
        ->and($this->user->email)->toBe($originalEmail);
});
