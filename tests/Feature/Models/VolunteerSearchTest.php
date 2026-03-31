<?php

use App\Models\Volunteer;

beforeEach(function () {
    Volunteer::factory()->create(['first_name' => 'Alice', 'last_name' => 'Johnson', 'email' => 'alice@example.com']);
    Volunteer::factory()->create(['first_name' => 'Bob', 'last_name' => 'Smith', 'email' => 'bob@test.org']);
    Volunteer::factory()->create(['first_name' => 'Charlie', 'last_name' => 'Brown', 'email' => 'charlie@example.com']);
});

it('finds volunteers by partial name with short query', function () {
    $results = Volunteer::query()->search('Al')->get();

    expect($results)->toHaveCount(1)
        ->and($results->first()->full_name)->toBe('Alice Johnson');
});

it('finds volunteers by partial email with short query', function () {
    $results = Volunteer::query()->search('bo')->get();

    expect($results)->toHaveCount(1)
        ->and($results->first()->full_name)->toBe('Bob Smith');
});

it('finds volunteers by name using full-text search', function () {
    $results = Volunteer::query()->search('Alice')->get();

    expect($results)->toHaveCount(1)
        ->and($results->first()->full_name)->toBe('Alice Johnson');
});

it('finds volunteers by email using full-text search', function () {
    $results = Volunteer::query()->search('charlie@example')->get();

    expect($results)->toHaveCount(1)
        ->and($results->first()->full_name)->toBe('Charlie Brown');
});

it('finds volunteers by last_name with short query', function () {
    $results = Volunteer::query()->search('Sm')->get();

    expect($results)->toHaveCount(1)
        ->and($results->first()->full_name)->toBe('Bob Smith');
});

it('returns empty for no match', function () {
    $results = Volunteer::query()->search('Zzznotfound')->get();

    expect($results)->toHaveCount(0);
});
