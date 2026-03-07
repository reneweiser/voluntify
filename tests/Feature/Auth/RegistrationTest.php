<?php

use App\Models\User;

test('registration route returns 404', function () {
    $response = $this->get('/register');

    $response->assertNotFound();
});

test('registration POST route returns 404', function () {
    $response = $this->post('/register', [
        'name' => 'John Doe',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertNotFound();
});

test('unverified users cannot access the dashboard', function () {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertRedirect(route('verification.notice'));
});
