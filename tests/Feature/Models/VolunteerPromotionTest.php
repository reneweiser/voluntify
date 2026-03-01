<?php

use App\Enums\StaffRole;
use App\Models\User;
use App\Models\Volunteer;
use App\Models\VolunteerPromotion;

it('belongs to a volunteer', function () {
    $volunteer = Volunteer::factory()->create();
    $promotion = VolunteerPromotion::factory()->for($volunteer)->create();

    expect($promotion->volunteer->id)->toBe($volunteer->id);
});

it('belongs to a user', function () {
    $user = User::factory()->create();
    $promotion = VolunteerPromotion::factory()->create(['user_id' => $user->id]);

    expect($promotion->user->id)->toBe($user->id);
});

it('belongs to a promoter', function () {
    $promoter = User::factory()->create();
    $promotion = VolunteerPromotion::factory()->create(['promoted_by' => $promoter->id]);

    expect($promotion->promoter->id)->toBe($promoter->id);
});

it('casts role to StaffRole enum', function () {
    $promotion = VolunteerPromotion::factory()->create(['role' => StaffRole::VolunteerAdmin]);

    expect($promotion->role)->toBe(StaffRole::VolunteerAdmin);
});

it('casts promoted_at to datetime', function () {
    $promotion = VolunteerPromotion::factory()->create();

    expect($promotion->promoted_at)->toBeInstanceOf(\Carbon\CarbonInterface::class);
});

it('enforces unique volunteer_id', function () {
    $volunteer = Volunteer::factory()->create();
    VolunteerPromotion::factory()->for($volunteer)->create();

    expect(fn () => VolunteerPromotion::factory()->for($volunteer)->create())
        ->toThrow(\Illuminate\Database\UniqueConstraintViolationException::class);
});
