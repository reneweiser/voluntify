<?php

use App\Models\Volunteer;

beforeEach(function () {
    Volunteer::factory()->create(['name' => 'Alice Johnson', 'email' => 'alice@example.com']);
    Volunteer::factory()->create(['name' => 'Bob Smith', 'email' => 'bob@test.org']);
    Volunteer::factory()->create(['name' => 'Charlie Brown', 'email' => 'charlie@example.com']);
});

it('finds volunteers by partial name with short query', function () {
    $results = Volunteer::query()->search('Al')->get();

    expect($results)->toHaveCount(1)
        ->and($results->first()->name)->toBe('Alice Johnson');
});

it('finds volunteers by partial email with short query', function () {
    $results = Volunteer::query()->search('bo')->get();

    expect($results)->toHaveCount(1)
        ->and($results->first()->name)->toBe('Bob Smith');
});

it('finds volunteers by name using full-text search', function () {
    $results = Volunteer::query()->search('Alice')->get();

    expect($results)->toHaveCount(1)
        ->and($results->first()->name)->toBe('Alice Johnson');
});

it('finds volunteers by email using full-text search', function () {
    $results = Volunteer::query()->search('charlie@example')->get();

    expect($results)->toHaveCount(1)
        ->and($results->first()->name)->toBe('Charlie Brown');
});

it('returns empty for no match', function () {
    $results = Volunteer::query()->search('Zzznotfound')->get();

    expect($results)->toHaveCount(0);
});
