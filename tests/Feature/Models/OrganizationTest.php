<?php

use App\Enums\StaffRole;
use App\Models\Event;
use App\Models\Organization;
use App\Models\User;

it('has a unique slug', function () {
    $org = Organization::factory()->create(['slug' => 'test-org']);

    expect(fn () => Organization::factory()->create(['slug' => 'test-org']))
        ->toThrow(\Illuminate\Database\UniqueConstraintViolationException::class);
});

it('has users with pivot role', function () {
    $org = Organization::factory()->create();
    $user = User::factory()->create();

    $org->users()->attach($user, ['role' => StaffRole::Organizer]);

    $pivotUser = $org->users()->first();
    expect($pivotUser->pivot->role)->toBe(StaffRole::Organizer);
});

it('has many events', function () {
    $org = Organization::factory()->create();
    Event::factory()->count(3)->for($org)->create();

    expect($org->events)->toHaveCount(3);
});

it('encrypts ai_api_key', function () {
    $org = Organization::factory()->withAiKey()->create();

    expect($org->ai_api_key)->toBeString()
        ->and($org->ai_api_key)->toStartWith('sk-test-');

    $raw = \Illuminate\Support\Facades\DB::table('organizations')
        ->where('id', $org->id)
        ->value('ai_api_key');

    expect($raw)->not->toBe($org->ai_api_key);
});

it('stores null ai_api_key', function () {
    $org = Organization::factory()->create();

    expect($org->ai_api_key)->toBeNull();
});
