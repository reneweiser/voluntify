<?php

use App\Models\Organization;

it('has a unique slug', function () {
    $org = Organization::factory()->create(['slug' => 'test-org']);

    expect(fn () => Organization::factory()->create(['slug' => 'test-org']))
        ->toThrow(\Illuminate\Database\UniqueConstraintViolationException::class);
});

it('encrypts and decrypts ai_api_key via Eloquent', function () {
    $org = Organization::factory()->withAiKey()->create();

    $reloaded = Organization::find($org->id);

    expect($reloaded->ai_api_key)->toBe($org->ai_api_key)
        ->and($reloaded->ai_api_key)->toStartWith('sk-test-');
});

it('stores null ai_api_key', function () {
    $org = Organization::factory()->create();

    expect($org->ai_api_key)->toBeNull();
});

it('hides ai_api_key from serialization', function () {
    $org = Organization::factory()->withAiKey()->create();

    $array = $org->toArray();

    expect($array)->not->toHaveKey('ai_api_key');
});
