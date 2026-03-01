<?php

use App\Actions\GenerateMagicLink;
use App\Models\MagicLinkToken;
use App\Models\Volunteer;
use App\ValueObjects\HashedToken;

beforeEach(function () {
    $this->volunteer = Volunteer::factory()->create();
    $this->action = new GenerateMagicLink;
});

it('creates a magic link token for a volunteer', function () {
    $result = $this->action->execute($this->volunteer);

    expect($result['token'])->toBeInstanceOf(MagicLinkToken::class)
        ->and($result['token']->exists)->toBeTrue()
        ->and($result['token']->volunteer_id)->toBe($this->volunteer->id)
        ->and($result['plainToken'])->toBeString()
        ->and(strlen($result['plainToken']))->toBe(64);
});

it('hashes the token with SHA-256', function () {
    $result = $this->action->execute($this->volunteer);

    $hashedToken = HashedToken::fromPlaintext($result['plainToken']);

    expect($result['token']->token_hash)->toBe($hashedToken->hash);
});

it('sets expiry to 72 hours', function () {
    $result = $this->action->execute($this->volunteer);

    expect($result['token']->expires_at->diffInHours(now(), true))->toBeBetween(71, 73);
});

it('returns the plain token for email URL', function () {
    $result = $this->action->execute($this->volunteer);

    // Verify the plain token is NOT what's stored in the database
    expect($result['token']->token_hash)->not->toBe($result['plainToken']);
});
