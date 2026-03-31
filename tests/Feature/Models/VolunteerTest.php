<?php

use App\Models\Volunteer;

it('allows duplicate emails for application-level deduplication', function () {
    Volunteer::factory()->create(['email' => 'volunteer@example.com']);
    $second = Volunteer::factory()->create(['email' => 'volunteer@example.com']);

    expect($second->exists)->toBeTrue()
        ->and(Volunteer::where('email', 'volunteer@example.com')->count())->toBe(2);
});

it('returns full_name as first_name space last_name', function () {
    $volunteer = Volunteer::factory()->create([
        'first_name' => 'Alice',
        'last_name' => 'Johnson',
    ]);

    expect($volunteer->full_name)->toBe('Alice Johnson');
});

it('returns full_name for single-word names', function () {
    $volunteer = Volunteer::factory()->create([
        'first_name' => 'Prince',
        'last_name' => '',
    ]);

    expect($volunteer->full_name)->toBe('Prince ');
});
