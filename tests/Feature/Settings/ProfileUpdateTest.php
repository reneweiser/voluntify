<?php

use App\Livewire\Settings\Profile;
use App\Models\Organization;
use Livewire\Livewire;

test('profile page is displayed', function () {
    ['user' => $user, 'organization' => $org] = createUserWithOrganization();

    $this->actingAs($user)
        ->get(route('profile.edit'))
        ->assertOk();
});

test('profile page shows a linked running version in settings', function () {
    ['user' => $user, 'organization' => $org] = createUserWithOrganization();

    config()->set('app.version', 'abc1234def567890');

    $this->actingAs($user)
        ->get(route('profile.edit'))
        ->assertOk()
        ->assertSee('Version abc1234')
        ->assertSeeHtml('href="https://github.com/reneweiser/voluntify/commit/abc1234def567890"');
});

test('profile page hides running version when it is missing', function () {
    ['user' => $user, 'organization' => $org] = createUserWithOrganization();

    config()->set('app.version', null);

    $this->actingAs($user)
        ->get(route('profile.edit'))
        ->assertOk()
        ->assertDontSee('Version');
});

test('profile information can be updated', function () {
    ['user' => $user, 'organization' => $org] = createUserWithOrganization();
    app()->instance(Organization::class, $org);

    $this->actingAs($user);

    $response = Livewire::test(Profile::class)
        ->set('name', 'Test User')
        ->set('email', 'test@example.com')
        ->call('updateProfileInformation');

    $response->assertHasNoErrors();

    $user->refresh();

    expect($user->name)->toEqual('Test User');
    expect($user->email)->toEqual('test@example.com');
    expect($user->email_verified_at)->toBeNull();
});

test('email verification status is unchanged when email address is unchanged', function () {
    ['user' => $user, 'organization' => $org] = createUserWithOrganization();
    app()->instance(Organization::class, $org);

    $this->actingAs($user);

    $response = Livewire::test(Profile::class)
        ->set('name', 'Test User')
        ->set('email', $user->email)
        ->call('updateProfileInformation');

    $response->assertHasNoErrors();

    expect($user->refresh()->email_verified_at)->not->toBeNull();
});

test('user can delete their account', function () {
    ['user' => $user, 'organization' => $org] = createUserWithOrganization();
    app()->instance(Organization::class, $org);

    $this->actingAs($user);

    $response = Livewire::test('settings.delete-user-form')
        ->set('password', 'password')
        ->call('deleteUser');

    $response
        ->assertHasNoErrors()
        ->assertRedirect('/');

    expect($user->fresh())->toBeNull();
    expect(auth()->check())->toBeFalse();
});

test('correct password must be provided to delete account', function () {
    ['user' => $user, 'organization' => $org] = createUserWithOrganization();
    app()->instance(Organization::class, $org);

    $this->actingAs($user);

    $response = Livewire::test('settings.delete-user-form')
        ->set('password', 'wrong-password')
        ->call('deleteUser');

    $response->assertHasErrors(['password']);

    expect($user->fresh())->not->toBeNull();
});
