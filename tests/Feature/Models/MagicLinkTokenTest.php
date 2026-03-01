<?php

use App\Models\MagicLinkToken;
use App\Models\Volunteer;

it('belongs to a volunteer', function () {
    $volunteer = Volunteer::factory()->create();
    $token = MagicLinkToken::factory()->for($volunteer)->create();

    expect($token->volunteer->id)->toBe($volunteer->id);
});

it('casts expires_at to datetime', function () {
    $token = MagicLinkToken::factory()->create();

    expect($token->expires_at)->toBeInstanceOf(\Carbon\CarbonInterface::class);
});
